<?php

namespace App\Http\Controllers;

use App\Models\ChannelAccount;
use App\Models\EmailThread;
use App\Jobs\SyncChannelAccountJob;
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
        $isOwner = $user->type === 'company' || $user->id === $companyId;

        $channelAccount = ChannelAccount::where('user_id', $companyId)->where('type', 'gmail')->first();

        if (!$channelAccount) {
            return Inertia::render('gmail/index', [
                'threads' => [],
                'channelAccount' => null,
                'isOwner' => $isOwner,
            ]);
        }

        $query = EmailThread::where('channel_account_id', $channelAccount->id);

        // Apply sync strategy filtering
        if ($channelAccount->getConfig('sync_strategy') === 'categories' && !empty($channelAccount->getConfig('sync_categories'))) {
            $syncCategories = $channelAccount->getConfig('sync_categories');
            $hasPrimary = in_array('PRIMARY', $syncCategories);

            $query->where(function($q) use ($syncCategories, $hasPrimary) {
                foreach ($syncCategories as $category) {
                    if ($category !== 'PRIMARY') {
                        $q->orWhereJsonContains('tags', 'CATEGORY_' . strtoupper($category));
                    }
                }
                
                if ($hasPrimary) {
                    $q->orWhere(function($sq) {
                        $otherCategories = ['CATEGORY_SOCIAL', 'CATEGORY_PROMOTIONS', 'CATEGORY_UPDATES', 'CATEGORY_FORUMS'];
                        foreach ($otherCategories as $other) {
                            $sq->whereJsonDoesntContain('tags', $other);
                        }
                    });
                }
            });
        }

        $query->orderBy('last_message_at', 'desc');

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
            'channelAccount' => [
                'id' => $channelAccount->id,
                'gmail_address' => $channelAccount->email_address,
                'last_sync_at' => $channelAccount->last_sync_at?->toIso8601String(),
                'sync_status' => $channelAccount->sync_status,
                'sync_error' => $channelAccount->sync_error,
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

        $channelAccount = ChannelAccount::where('user_id', $companyId)->where('type', 'gmail')->firstOrFail();

        $thread = EmailThread::where('channel_account_id', $channelAccount->id)
            ->where('external_thread_id', $threadId)
            ->with('messages')
            ->firstOrFail();

        // Mark as read
        if (!$thread->is_read) {
            $thread->update(['is_read' => true]);
        }

        return Inertia::render('gmail/show', [
            'thread' => $thread,
            'messages' => $thread->messages,
            'channelAccount' => [
                'gmail_address' => $channelAccount->email_address,
            ],
        ]);
    }

    /**
     * Trigger a sync for the user's Gmail account.
     */
    public function syncNow()
    {
        $user = auth()->user();
        $companyId = $user->creatorId();
        $isOwner = $user->type === 'company' || $user->id === $companyId;

        if (!$isOwner) {
            return redirect()->back()->with('error', 'Only company owners can sync the Gmail account.');
        }

        $channelAccount = ChannelAccount::where('user_id', $companyId)->where('type', 'gmail')->first();

        if (!$channelAccount) {
            return redirect()->back()->with('error', 'No Gmail account connected.');
        }

        if ($channelAccount->sync_status === 'syncing') {
            return redirect()->back()->with('info', 'A sync is already in progress.');
        }

        // Use dispatchSync for immediate feedback in this controller's context
        SyncChannelAccountJob::dispatch($channelAccount->id);

        return redirect()->back()->with('success', 'Gmail sync started successfully.');
    }

    /**
     * Disconnect the user's Gmail account.
     */
    public function disconnect()
    {
        $user = auth()->user();
        $companyId = $user->creatorId();
        $isOwner = $user->type === 'company' || $user->id === $companyId;

        if (!$isOwner) {
            return redirect()->back()->with('error', 'Only company owners can disconnect the Gmail account.');
        }

        $channelAccount = ChannelAccount::where('user_id', $companyId)->where('type', 'gmail')->first();

        if ($channelAccount) {
            $channelAccount->delete();
            return redirect()->back()->with('success', 'Gmail account disconnected successfully.');
        }

        return redirect()->back()->with('error', 'No Gmail account found to disconnect.');
    }

    /**
     * Update Gmail sync settings (strategy and categories).
     */
    public function updateSyncSettings(Request $request)
    {
        $user = auth()->user();
        $companyId = $user->creatorId();

        $channelAccount = ChannelAccount::where('user_id', $companyId)->where('type', 'gmail')->first();

        if (!$channelAccount) {
            return redirect()->back()->with('error', 'No Gmail account connected.');
        }

        $validated = $request->validate([
            'gmail_sync_strategy' => 'required|in:all,categories,contacts',
            'gmail_sync_categories' => 'required_if:gmail_sync_strategy,categories|array',
            'gmail_sync_categories.*' => 'string|in:PRIMARY,SOCIAL,PROMOTIONS,UPDATES,FORUMS',
        ]);

        $channelAccount->update([
            'configuration' => array_merge($channelAccount->configuration, [
                'sync_strategy' => $validated['gmail_sync_strategy'],
                'sync_categories' => $validated['gmail_sync_categories'] ?? null,
            ])
        ]);

        return redirect()->back()->with('success', 'Gmail sync settings updated successfully.');
    }
}
