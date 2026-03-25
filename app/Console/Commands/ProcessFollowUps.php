<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\EmailThread;
use App\Models\User;
use App\Notifications\ConversationFollowUp;
use Illuminate\Support\Facades\Log;

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
        // Find all threads where the follow-up date has arrived or passed, and is not null
        $threads = EmailThread::with(['assignments', 'gmailAccount'])
            ->whereNotNull('follow_up_at')
            ->where('follow_up_at', '<=', now())
            ->get();

        if ($threads->isEmpty()) {
            return 0; // Nothing to process
        }

        foreach ($threads as $thread) {
            try {
                $owner = User::find($thread->created_by);
                $isFollowUpEnabled = getSetting('conversation_follow_up_enabled', 'on', $owner->id ?? null) === 'on';

                if ($isFollowUpEnabled && $owner) {
                    $usersToNotify = $thread->assignments;

                    // Fallback to company owner if no assignments
                    if ($usersToNotify->isEmpty()) {
                        $usersToNotify = collect([$owner]);
                    }

                    foreach ($usersToNotify as $user) {
                        // Send database notification
                        $user->notify(new ConversationFollowUp($thread));

                        // Send stylized email via EmailTemplateService
                        try {
                            $templateService = app(\App\Services\EmailTemplateService::class);
                            $business = \App\Models\Business::where('user_id', $owner->id)->first();
                            
                            $variables = [
                                '{thread_subject}' => $thread->subject ?: 'Conversation Follow Up',
                                '{assigned_user_name}' => $user->name,
                                '{company_name}' => $business ? $business->name : getSetting('company_name', 'Company', $owner->id),
                                '{view_link}' => url('/conversations?thread_id=' . $thread->id)
                            ];

                            $templateService->sendTemplateEmail(
                                'Conversation Follow Up',
                                $variables,
                                $user->email,
                                $business,
                                $user->name
                            );
                        } catch (\Exception $emailEx) {
                            Log::error('Template email failed: ' . $emailEx->getMessage());
                        }
                    }
                }
                $statusUpdate = [];
                if (in_array($thread->status, ['Closed', 'Archive'])) {
                    $statusUpdate['status'] = 'Open';
                }

                // Nullify the follow_up_at date so we don't notify again (Strict Rule: No Schema Changes)
                $statusUpdate['follow_up_at'] = null;

                $thread->update($statusUpdate);

            } catch (\Exception $e) {
                Log::error('Failed to process follow-up for thread: ' . $thread->id, [
                    'error' => $e->getMessage()
                ]);
            }
        }

        return 0;
    }
}
