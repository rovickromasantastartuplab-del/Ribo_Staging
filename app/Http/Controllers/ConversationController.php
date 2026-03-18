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
        
        $gmailAccount = GmailAccount::where('user_id', $companyId)->first();

        return Inertia::render('conversations/index', [
            'initialFolder' => 'inbox',
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
        $folder = $request->get('folder', 'inbox');
        $query = EmailThread::with(['leads', 'contacts', 'latestMessage'])
            ->where('created_by', auth()->user()->creatorId())
            ->orderByDesc('last_message_at');

        // Apply folder logic (basic implementation)
        if ($folder === 'sent') {
            // Placeholder: filter for threads where last message is sent by user
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
            
            // Determine recipient (the first participant that is NOT the account itself)
            $participants = $thread->participants ?? [];
            $recipient = collect($participants)->first(function ($email) use ($account) {
                return strtolower($email) !== strtolower($account->gmail_address);
            });

            if (!$recipient) {
                return response()->json(['error' => 'No recipient found for this thread.'], 422);
            }

            // Get the latest message to find the Message-ID for In-Reply-To
            $latestMessage = $thread->latestMessage;
            $inReplyTo = null;
            // Note: We'd ideally store the Message-ID header in the database. 
            // If we don't have it, Gmail still tries to thread by threadId.

            $success = $service->sendMessage(
                $recipient,
                "Re: " . $thread->subject,
                $request->body,
                $thread->gmail_thread_id,
                $inReplyTo
            );

            if ($success) {
                // Immediately sync this thread so the reply appears in the UI
                \App\Jobs\SyncGmailThreadsJob::dispatchSync($account->id);
                
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
