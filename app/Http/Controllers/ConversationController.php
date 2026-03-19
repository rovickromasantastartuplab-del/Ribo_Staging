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

        $query = EmailThread::with(['leads', 'contacts', 'latestMessage'])
            ->where('created_by', $companyId)
            ->orderByDesc('last_message_at');

        // Apply folder filtering by Gmail labels
        if ($folder === 'sent') {
            $query->whereHas('latestMessage', function ($q) use ($companyId) {
                // Find the Gmail account for this company (could be owner's or staff's)
                $gmailAccount = GmailAccount::whereHas('user', function($qu) use ($companyId) {
                    $qu->where('id', $companyId)->orWhere('created_by', $companyId);
                })->first();

                if ($gmailAccount) {
                    $q->where('from_email', strtolower($gmailAccount->gmail_address));
                }
            });
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

        $thread->load(['messages', 'leads', 'contacts']);
        
        return response()->json($thread);
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
