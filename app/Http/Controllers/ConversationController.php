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
     * Display a specific thread's full history.
     */
    public function show(EmailThread $thread)
    {
        // Ensure user has access to this company's threads
        if ($thread->created_by !== auth()->user()->creatorId()) {
            abort(403);
        }

        $thread->load(['messages', 'leads.leadStatus', 'contacts']);

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
            
            $success = $service->sendMessage(
                $request->to,
                $request->subject,
                $request->body
            );

            if ($success) {
                // Dispatch async sync to fetch the newly sent message into the DB
                \App\Jobs\SyncGmailThreadsJob::dispatch($account->id);
                
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

            $success = $service->sendMessage(
                $primaryRecipient,
                "Re: " . $thread->subject,
                $request->body,
                $thread->gmail_thread_id,
                $inReplyTo,
                $ccRecipients
            );

            if ($success) {
                // Dispatch async sync — the GmailSyncCompleted event will refresh the UI in real time
                \App\Jobs\SyncGmailThreadsJob::dispatch($account->id);
                
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
