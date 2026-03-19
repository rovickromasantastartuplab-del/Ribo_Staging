<?php

namespace App\Http\Controllers;

use App\Models\GmailAccount;
use App\Models\EmailThread;
use App\Jobs\SyncGmailThreadsJob;
use Illuminate\Http\Request;
use Inertia\Inertia;

class GmailController extends Controller
{
    /**
     * Display the Gmail inbox with synced threads.
     */
    public function threads(Request $request)
    {
        $user = auth()->user();
        $companyId = $user->creatorId();

        $gmailAccount = GmailAccount::where('user_id', $companyId)->first();

        if (!$gmailAccount) {
            return Inertia::render('gmail/index', [
                'threads' => [],
                'gmailAccount' => null,
            ]);
        }

        $query = EmailThread::where('gmail_account_id', $gmailAccount->id)
            ->orderBy('last_message_at', 'desc');

        // Search filter
        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('subject', 'like', "%{$search}%")
                  ->orWhere('snippet', 'like', "%{$search}%")
                  ->orWhereJsonContains('participants', $search);
            });
        }

        $threads = $query->paginate(25);

        return Inertia::render('gmail/index', [
            'threads' => $threads,
            'gmailAccount' => [
                'id' => $gmailAccount->id,
                'gmail_address' => $gmailAccount->gmail_address,
                'last_sync_at' => $gmailAccount->last_sync_at?->toIso8601String(),
                'sync_status' => $gmailAccount->sync_status,
                'sync_error' => $gmailAccount->sync_error,
            ],
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    /**
     * Display a single email thread with all messages.
     */
    public function showThread(string $threadId)
    {
        $user = auth()->user();
        $companyId = $user->creatorId();

        $gmailAccount = GmailAccount::where('user_id', $companyId)->firstOrFail();

        $thread = EmailThread::where('gmail_account_id', $gmailAccount->id)
            ->where('gmail_thread_id', $threadId)
            ->with('messages')
            ->firstOrFail();

        // Mark as read
        if (!$thread->is_read) {
            $thread->update(['is_read' => true]);
        }

        return Inertia::render('gmail/show', [
            'thread' => $thread,
            'messages' => $thread->messages,
            'gmailAccount' => [
                'gmail_address' => $gmailAccount->gmail_address,
            ],
        ]);
    }

    /**
     * Disconnect the user's Gmail account.
     */
    public function disconnect()
    {
        $user = auth()->user();
        $companyId = $user->creatorId();

        $gmailAccount = GmailAccount::where('user_id', $companyId)->first();

        if ($gmailAccount) {
            // This will cascade delete email_threads and email_messages
            $gmailAccount->delete();
        }

        return redirect('/settings#integrations-settings')
            ->with('success', 'Gmail account disconnected successfully.');
    }

    /**
     * Trigger an immediate sync for the user's Gmail account.
     */
    public function syncNow()
    {
        $user = auth()->user();
        $companyId = $user->creatorId();

        $gmailAccount = GmailAccount::where('user_id', $companyId)->first();

        if (!$gmailAccount) {
            return redirect()->back()->with('error', 'No Gmail account connected.');
        }

        if ($gmailAccount->sync_status === 'syncing') {
            return redirect()->back()->with('info', 'A sync is already in progress.');
        }

        SyncGmailThreadsJob::dispatchSync($gmailAccount->id);

        return redirect()->back()->with('success', 'Gmail sync completed successfully.');
    }
}
