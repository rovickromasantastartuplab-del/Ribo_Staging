<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\ChannelAccount;
use App\Jobs\SyncChannelAccountJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ChannelAccountController extends Controller
{
    /**
     * List all connected channel accounts.
     */
    public function index()
    {
        $accounts = ChannelAccount::where('user_id', auth()->user()->creatorId())
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($accounts);
    }

    /**
     * Store a new IMAP/SMTP channel account.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'email_address' => 'required|email',
            'imap_host' => 'required|string',
            'imap_port' => 'required|integer',
            'imap_encryption' => 'required|in:ssl,tls,none',
            'imap_username' => 'required|string',
            'imap_password' => 'required|string',
            'smtp_host' => 'required|string',
            'smtp_port' => 'required|integer',
            'smtp_encryption' => 'required|in:ssl,tls,none',
            'smtp_username' => 'required|string',
            'smtp_password' => 'required|string',
        ]);

        try {
            $account = ChannelAccount::create([
                'user_id' => auth()->user()->creatorId(),
                'type' => 'smtp_imap',
                'email_address' => $validated['email_address'],
                'configuration' => [
                    'imap_host' => $validated['imap_host'],
                    'imap_port' => $validated['imap_port'],
                    'imap_encryption' => $validated['imap_encryption'],
                    'imap_username' => $validated['imap_username'],
                    'imap_password' => $validated['imap_password'],
                    'smtp_host' => $validated['smtp_host'],
                    'smtp_port' => $validated['smtp_port'],
                    'smtp_encryption' => $validated['smtp_encryption'],
                    'smtp_username' => $validated['smtp_username'],
                    'smtp_password' => $validated['smtp_password'],
                ],
                'sync_status' => 'pending'
            ]);

            // Trigger initial sync
            SyncChannelAccountJob::dispatch($account->id);

            return redirect()->back()->with('success', 'Account connected successfully. Initial sync started.');
        } catch (\Exception $e) {
            Log::error('Failed to connect IMAP/SMTP account', ['error' => $e->getMessage()]);
            return redirect()->back()->withErrors(['error' => 'Failed to connect account: ' . $e->getMessage()]);
        }
    }

    /**
     * Delete a channel account.
     */
    public function destroy(ChannelAccount $account)
    {
        if ($account->user_id !== auth()->user()->creatorId()) {
            abort(403);
        }

        try {
            $account->delete();
            return redirect()->back()->with('success', 'Account disconnected successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => 'Failed to disconnect account.']);
        }
    }

    /**
     * Trigger a manual sync.
     */
    public function sync(ChannelAccount $account)
    {
        if ($account->user_id !== auth()->user()->creatorId()) {
            abort(403);
        }

        SyncChannelAccountJob::dispatch($account->id);

        return redirect()->back()->with('success', 'Sync triggered successfully.');
    }
}
