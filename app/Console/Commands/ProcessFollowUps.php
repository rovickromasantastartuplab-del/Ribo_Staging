<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\EmailThread;
use App\Models\User;
use App\Models\ThreadFollowUpQueue;
use App\Models\ThreadFollowUpStage;
use App\Models\EmailOpenLog;
use App\Models\EmailClickLog;
use App\Notifications\ConversationFollowUp;
use App\Notifications\AutomatedFollowUpSent;
use Illuminate\Support\Facades\Notification;
use App\Services\GmailService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class ProcessFollowUps extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'conversations:follow-up';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process conversation threads that are due for follow-up today/now.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // ──────────────────────────────────────────────────────────────
        // Automated Follow-ups (Primary)
        // ──────────────────────────────────────────────────────────────
        $this->processAutomatedFollowUps();

        return 0;
    }

    /**
     * Process outbound automated follow-up queue items that are due.
     */
    private function processAutomatedFollowUps(): void
    {
        $dueItems = ThreadFollowUpQueue::with(['stage.emailThread.gmailAccount', 'stage.emailThread.leads', 'stage.emailThread.contacts'])
            ->where('status', 'pending')
            ->where('scheduled_at', '<=', now())
            ->get();

        if ($dueItems->isEmpty()) {
            $this->info('No automated follow-ups are due.');
            return;
        }

        $this->info('Found ' . $dueItems->count() . ' automated follow-up(s) to process.');

        foreach ($dueItems as $item) {
            try {
                $stage = $item->stage;
                $thread = $stage?->emailThread;

                if (!$thread || !$thread->gmailAccount) {
                    $item->update(['status' => 'cancelled', 'cancelled_reason' => 'missing_thread_or_account']);
                    continue;
                }

                // Cancel if thread is closed
                if ($thread->status === 'Closed') {
                    $item->update(['status' => 'cancelled', 'cancelled_reason' => 'thread_closed']);
                    $this->info("Queue #{$item->id}: cancelled (thread closed).");
                    continue;
                }

                // Authenticate as the thread owner for GmailService
                $owner = User::find($thread->created_by);
                if ($owner) {
                    Auth::login($owner);
                }

                $gmailService = new GmailService($thread->gmailAccount);

                // Evaluate trigger condition
                $conditionMet = $this->evaluateTrigger($stage->trigger_type, $item, $gmailService);

                if ($conditionMet) {
                    $item->update(['status' => 'cancelled', 'cancelled_reason' => 'condition_met']);
                    $this->info("Queue #{$item->id}: cancelled (condition met — {$stage->trigger_type}).");
                    continue;
                }

                // Resolve merge tags
                $resolvedBody = $this->resolveMergeTags($stage->body, $thread, $item);

                // Send the follow-up reply
                $newMessageId = $gmailService->sendFollowUpReply($item, $resolvedBody);

                if ($newMessageId) {
                    $item->update(['status' => 'sent', 'sent_at' => now()]);
                    $this->info("Queue #{$item->id}: sent (stage {$stage->stage_number}).");

                    // Notify assigned staff, company owner, and the connected Gmail account
                    try {
                        $recipients = collect();
                        
                        // Add all assigned users
                        $assignedUsers = $thread->assignments()->get();
                        $recipients = $recipients->merge($assignedUsers);

                        // Add the creator (Company Owner)
                        $creator = User::find($thread->created_by);
                        if ($creator) {
                            $recipients->push($creator);
                        }

                        // Remove duplicates and notify registered users
                        $recipients = $recipients->unique('id');
                        
                        if ($recipients->isNotEmpty()) {
                            Notification::send($recipients, new AutomatedFollowUpSent($thread, $item));
                        }

                        // Also notify the connected Gmail account if it's not one of the registered recipients
                        $connectedEmail = $thread->gmailAccount?->gmail_address;
                        if ($connectedEmail) {
                            $isAlreadyNotified = $recipients->contains(function ($user) use ($connectedEmail) {
                                return strtolower($user->email) === strtolower($connectedEmail);
                            });

                            if (!$isAlreadyNotified) {
                                Notification::route('mail', $connectedEmail)->notify(new AutomatedFollowUpSent($thread, $item));
                            }
                        }
                    } catch (\Exception $ne) {
                        Log::error("Failed to send internal notification for automated follow-up queue #{$item->id}", [
                            'error' => $ne->getMessage()
                        ]);
                    }

                    // Chain to next stage if one exists
                    $this->scheduleNextStage($stage, $item, $newMessageId);
                } else {
                    Log::error("Automated follow-up send failed for queue #{$item->id}.");
                }

            } catch (\Exception $e) {
                Log::error("Failed to process automated follow-up queue #{$item->id}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Evaluate whether the trigger condition has been met (recipient replied, opened, or clicked).
     * Returns true if the condition IS met (meaning we should CANCEL the follow-up).
     */
    private function evaluateTrigger(string $triggerType, ThreadFollowUpQueue $item, GmailService $gmailService): bool
    {
        return match ($triggerType) {
            'no_reply' => $gmailService->hasReply($item->gmail_thread_id, $item->recipient_email),
            'no_open'  => EmailOpenLog::where('gmail_message_id', $item->gmail_message_id)->exists(),
            'no_click' => EmailClickLog::where('gmail_message_id', $item->gmail_message_id)->exists(),
            'drip'     => false, // Always send
            default    => false,
        };
    }

    /**
     * Resolve merge tags in the email body using linked Lead or Contact data.
     */
    private function resolveMergeTags(string $body, EmailThread $thread, ThreadFollowUpQueue $item): string
    {
        $firstName = '';
        $lastName = '';
        $company = '';
        $email = $item->recipient_email;

        // Try Lead first, then Contact
        $lead = $thread->leads->first();
        $contact = $thread->contacts->first();

        if ($lead) {
            $nameParts = explode(' ', $lead->name ?? '', 2);
            $firstName = $nameParts[0] ?? '';
            $lastName = $nameParts[1] ?? '';
            $company = $lead->company ?? '';
            $email = $lead->email ?? $email;
        } elseif ($contact) {
            $nameParts = explode(' ', $contact->name ?? '', 2);
            $firstName = $nameParts[0] ?? '';
            $lastName = $nameParts[1] ?? '';
            $company = $contact->account?->name ?? '';
            $email = $contact->email ?? $email;
        }

        // Sender name from the Gmail account owner
        $senderName = $thread->gmailAccount?->user?->name ?? '';

        // Build tracking pixel tag
        $pixelUrl = route('tracking.pixel', ['messageId' => $item->gmail_message_id]) . '?e=' . urlencode($item->recipient_email);
        $trackingPixel = '<img src="' . $pixelUrl . '" width="1" height="1" style="display:none" alt="" />';

        $replacements = [
            '{FirstName}'     => $firstName,
            '{LastName}'      => $lastName,
            '{Company}'       => $company,
            '{Email}'         => $email,
            '{SenderName}'    => $senderName,
            '{TrackingPixel}' => $trackingPixel,
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $body);
    }

    /**
     * Schedule the next stage in the sequence after a successful send.
     */
    private function scheduleNextStage(ThreadFollowUpStage $currentStage, ThreadFollowUpQueue $sentItem, string $newMessageId): void
    {
        $nextStage = ThreadFollowUpStage::where('email_thread_id', $currentStage->email_thread_id)
            ->where('stage_number', $currentStage->stage_number + 1)
            ->first();

        if (!$nextStage) {
            return; // No more stages in the sequence
        }

        ThreadFollowUpQueue::create([
            'thread_follow_up_stage_id' => $nextStage->id,
            'recipient_email'           => $sentItem->recipient_email,
            'gmail_thread_id'           => $sentItem->gmail_thread_id,
            'gmail_message_id'          => $newMessageId,
            'status'                    => 'pending',
            'scheduled_at'              => now()->addDays($nextStage->delay_days),
        ]);
    }
}
