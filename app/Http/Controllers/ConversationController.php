<?php

namespace App\Http\Controllers;

use App\Models\GmailAccount;
use App\Models\EmailThread;
use App\Models\EmailMessage;
use App\Services\GmailService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Log;

class ConversationController extends Controller
{
    /**
     * Display the main Conversations Hub.
     */
    public function index()
    {
        $user = auth()->user();
        $companyId = $user->creatorId();
        $isOwner = $user->type === 'company' || $user->id === $companyId;
        
        // Find any Gmail account belonging to this company (shared account)
        $gmailAccount = GmailAccount::where('user_id', $companyId)->first();

        // If not found on owner, check if any staff has one (legacy support)
        if (!$gmailAccount) {
            $gmailAccount = GmailAccount::whereHas('user', function($q) use ($companyId) {
                $q->where('created_by', $companyId);
            })->first();
        }

        // Compute actual unread count
        $unreadCount = $gmailAccount
            ? EmailThread::where('created_by', $companyId)->where('is_read', false)->count()
            : 0;

        return Inertia::render('conversations/index', [
            'initialFolder' => 'inbox',
            'unreadCount' => $unreadCount,
            'companyId' => $companyId,
            'isOwner' => $isOwner,
            'gmailAccount' => $gmailAccount ? [
                'id' => $gmailAccount->id,
                'email' => $gmailAccount->gmail_address,
                'last_sync_at' => $gmailAccount->last_sync_at?->toIso8601String(),
                'sync_status' => $gmailAccount->sync_status,
                'sync_error' => $gmailAccount->sync_error,
            ] : null
        ]);
    }

    /**
     * Fetch threads for a specific folder/filter.
     */
    public function threads(Request $request)
    {
        $companyId = auth()->user()->creatorId();
        $folder = $request->get('folder', 'inbox');
        $search = $request->get('search');

        $query = EmailThread::with(['leads', 'contacts', 'latestMessage'])
            ->where('created_by', $companyId)
            ->orderByDesc('last_message_at');

        // BUG-09: Apply search filter
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('subject', 'like', "%{$search}%")
                  ->orWhere('snippet', 'like', "%{$search}%")
                  ->orWhereJsonContains('participants', $search);
            });
        }

        // Apply folder filtering
        if ($folder === 'sent') {
            // BUG-11: Check if ANY message in the thread is from the connected account
            $query->whereHas('messages', function ($q) use ($companyId) {
                $gmailAccount = GmailAccount::whereHas('user', function($qu) use ($companyId) {
                    $qu->where('id', $companyId)->orWhere('created_by', $companyId);
                })->first();

                if ($gmailAccount) {
                    $q->where('from_email', strtolower($gmailAccount->gmail_address));
                }
            });
        } elseif ($folder === 'unassigned') {
            // BUG-10: Unassigned = threads not linked to any Lead or Contact
            $query->whereDoesntHave('leads')->whereDoesntHave('contacts');
        }

        return response()->json($query->paginate(20));
    }

    /**
     * Fetch activities/history for the Gmail account, optionally filtered by email.
     */
    public function activities(Request $request)
    {
        $companyId = auth()->user()->creatorId();
        $email = $request->get('email');
        
        $gmailAccount = GmailAccount::where('user_id', $companyId)->first();
        if (!$gmailAccount) {
            $gmailAccount = GmailAccount::whereHas('user', function($q) use ($companyId) {
                $q->where('created_by', $companyId);
            })->first();
        }

        if (!$gmailAccount) {
            return response()->json(['data' => []]);
        }

        if ($email) {
            // CONSOLIDATED ACTIVITY VIEW FOR A SPECIFIC PERSON
            $email = trim(strtolower($email));
            $companyEmail = strtolower($gmailAccount->gmail_address);
            
            // 1. Gmail Account Activity (system logs)
            $systemActivities = \App\Models\GmailAccountActivity::where('gmail_account_id', $gmailAccount->id)
                ->where('description', 'like', "%{$email}%")
                ->with('user:id,name,avatar')
                ->get();

            // 2. Email Messages (Sent/Received)
            $emailActivities = \App\Models\EmailMessage::where('created_by', $companyId)
                ->where(function($q) use ($email) {
                    $q->where('from_email', $email)
                      ->orWhereJsonContains('to_emails', $email)
                      ->orWhereJsonContains('cc_emails', $email);
                })
                ->get()
                ->map(function($msg) use ($companyEmail) {
                    $isOutbound = strtolower($msg->from_email) === $companyEmail;
                    $to = is_array($msg->to_emails) ? implode(', ', $msg->to_emails) : $msg->to_emails;
                    
                    return [
                        'id' => 'msg_' . $msg->id,
                        'activity_type' => $isOutbound ? 'email_sent' : 'email_received',
                        'title' => $isOutbound ? "Email sent to {$to}" : "Email received from {$msg->from_email}",
                        'description' => '<b>' . $msg->subject . '</b><br/><br/>' . $msg->body_preview,
                        'created_at' => $msg->sent_at,
                        'user' => null, 
                    ];
                });

            // 3. CRM Activities (if Lead exists)
            $crmActivities = collect();
            
            $lead = \App\Models\Lead::where('created_by', $companyId)->where('email', $email)->first();
            if ($lead) {
                $activities = \App\Models\LeadActivity::where('lead_id', $lead->id)
                    ->with('user:id,name,avatar')
                    ->get();
                $crmActivities = $crmActivities->concat($activities);
            }

            // Merge and sort
            $merged = collect($systemActivities)
                ->concat($emailActivities)
                ->concat($crmActivities)
                ->sortByDesc('created_at')
                ->values();

            return response()->json(['data' => $merged->take(50)]);
        }

        $activities = $gmailAccount->activities()
            ->with('user:id,name,avatar')
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($activities);
    }

    /**
     * Fetch unique participants who have emailed the company.
     */
    public function historyParticipants(Request $request)
    {
        $companyId = auth()->user()->creatorId();
        $search = $request->get('search');

        $gmailAccount = GmailAccount::where('user_id', $companyId)->first();
        if (!$gmailAccount) {
            $gmailAccount = GmailAccount::whereHas('user', function($q) use ($companyId) {
                $q->where('created_by', $companyId);
            })->first();
        }
        
        $companyEmail = $gmailAccount ? strtolower($gmailAccount->gmail_address) : null;

        $threads = EmailThread::where('created_by', $companyId)
            ->with(['leads', 'contacts'])
            ->orderByDesc('last_message_at')
            ->get();

        $participants = [];

        foreach ($threads as $thread) {
            $threadParticipants = $thread->participants ?? [];
            foreach ($threadParticipants as $pEmail) {
                $pEmail = strtolower($pEmail);
                if ($pEmail === $companyEmail) continue;
                if ($search && !str_contains($pEmail, strtolower($search))) continue;

                if (!isset($participants[$pEmail])) {
                    $name = $thread->leads->first()?->name 
                        ?? $thread->contacts->first()?->name 
                        ?? explode('@', $pEmail)[0];

                    $participants[$pEmail] = [
                        'email' => $pEmail,
                        'name' => $name,
                        'avatar' => null,
                        'last_activity_at' => $thread->last_message_at,
                    ];
                } else {
                    if ($thread->last_message_at > $participants[$pEmail]['last_activity_at']) {
                        $participants[$pEmail]['last_activity_at'] = $thread->last_message_at;
                    }
                }
            }
        }

        return response()->json(['data' => array_values($participants)]);
    }

    /**
     * Display a specific thread's full history.
     */
    public function show(EmailThread $thread)
    {
        // Ensure user has access to this company's threads
        if ($thread->created_by !== auth()->user()->creatorId()) {
            abort(403);
        }

        $thread->load(['messages.media', 'leads.leadStatus', 'contacts']);

        // Mark as read when user opens the thread
        if (!$thread->is_read) {
            $thread->update(['is_read' => true]);
        }
        
        return response()->json($thread);
    }

    /**
     * Compose a new email thread.
     */
    public function compose(Request $request)
    {
        $request->validate([
            'to' => 'required|email',
            'subject' => 'required|string|max:255',
            'body' => 'required|string',
        ]);

        try {
            $user = auth()->user();
            $companyId = $user->creatorId();
            
            $account = \App\Models\GmailAccount::where('user_id', $companyId)->first();
            if (!$account) {
                $account = \App\Models\GmailAccount::whereHas('user', function($q) use ($companyId) {
                    $q->where('created_by', $companyId);
                })->first();
            }

            if (!$account) {
                return response()->json(['error' => 'No connected Gmail account found.'], 422);
            }

            $service = new \App\Services\GmailService($account);
            
            $attachments = $request->hasFile('attachments') ? $request->file('attachments') : [];


            $success = $service->sendMessage(
                $request->to,
                $request->subject,
                $request->body,
                null,
                null,
                [],
                $attachments
            );

            if ($success) {
                \Illuminate\Support\Facades\Log::info('Email sent successfully, starting synchronous sync', ['account_id' => $account->id]);
                
                // Dispatch async sync to fetch the newly sent message into the DB
                \App\Jobs\SyncGmailThreadsJob::dispatchSync($account->id);
                
                \Illuminate\Support\Facades\Log::info('Synchronous sync completed after email send');
                
                return response()->json(['message' => 'Email sent successfully.']);
            }

            return response()->json(['error' => 'Failed to send email via Gmail API.'], 500);
            
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to compose email', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage()
            ]);
            return response()->json(['error' => 'An unexpected error occurred: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Send a reply to a thread.
     */
    public function reply(Request $request, EmailThread $thread)
    {
        // Ensure user has access to this company's threads
        if ($thread->created_by !== auth()->user()->creatorId()) {
            abort(403);
        }

        $request->validate([
            'body' => 'required|string',
        ]);

        try {
            $account = $thread->gmailAccount;
            if (!$account) {
                return response()->json(['error' => 'Gmail account not found for this thread.'], 422);
            }

            $service = new GmailService($account);
            
            // Build recipient list: all external participants
            $participants = $thread->participants ?? [];
            $accountEmail = strtolower($account->gmail_address);

            $externalParticipants = collect($participants)->filter(function ($email) use ($accountEmail) {
                return strtolower($email) !== $accountEmail;
            })->values();

            if ($externalParticipants->isEmpty()) {
                return response()->json(['error' => 'No recipient found for this thread.'], 422);
            }

            // First external participant is the primary TO; rest are CC
            $primaryRecipient = $externalParticipants->first();
            $ccRecipients = $externalParticipants->slice(1)->values()->all();

            // Get the latest message's Message-ID for proper In-Reply-To threading
            $latestMessage = $thread->latestMessage;
            $inReplyTo = $latestMessage?->message_id_header;

            $replyAttachments = $request->hasFile('attachments') ? $request->file('attachments') : [];


            $success = $service->sendMessage(
                $primaryRecipient,
                "Re: " . $thread->subject,
                $request->body,
                $thread->gmail_thread_id,
                $inReplyTo,
                $ccRecipients,
                $replyAttachments
            );

            if ($success) {
                \Illuminate\Support\Facades\Log::info('Reply sent successfully, starting synchronous sync', ['account_id' => $account->id]);

                // Dispatch async sync ΓÇö the GmailSyncCompleted event will refresh the UI in real time
                \App\Jobs\SyncGmailThreadsJob::dispatchSync($account->id);
                
                \Illuminate\Support\Facades\Log::info('Synchronous sync completed after reply send');

                return response()->json(['message' => 'Reply sent successfully.']);
            }

            return response()->json(['error' => 'Failed to send reply via Gmail API.'], 500);
            
        } catch (\Exception $e) {
            Log::error('Failed to send reply', [
                'thread_id' => $thread->id,
                'error' => $e->getMessage()
            ]);
            return response()->json(['error' => 'An unexpected error occurred: ' . $e->getMessage()], 500);
        }
    }
}
