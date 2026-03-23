<?php

namespace App\Http\Controllers;

use App\Models\GmailAccount;
use App\Models\EmailThread;
use App\Models\EmailMessage;
use App\Services\GmailService;
use App\Models\LeadStatus;
use App\Models\LeadSource;
use App\Models\AccountIndustry;
use App\Models\Campaign;
use App\Models\User;
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

        // Compute actual unread count with sync filtering
        $unreadCountQuery = EmailThread::where('created_by', $companyId)->where('is_read', false);
        if ($gmailAccount && $gmailAccount->sync_strategy === 'categories' && !empty($gmailAccount->sync_categories)) {
            $syncCategories = $gmailAccount->sync_categories;
            $hasPrimary = in_array('PRIMARY', $syncCategories);
            
            $unreadCountQuery->where(function($q) use ($syncCategories, $hasPrimary) {
                foreach ($syncCategories as $category) {
                    if ($category !== 'PRIMARY') {
                        $q->orWhereJsonContains('labels', 'CATEGORY_' . strtoupper($category));
                    }
                }
                
                if ($hasPrimary) {
                    $q->orWhere(function($sq) {
                        $otherCategories = ['CATEGORY_SOCIAL', 'CATEGORY_PROMOTIONS', 'CATEGORY_UPDATES', 'CATEGORY_FORUMS'];
                        foreach ($otherCategories as $other) {
                            $sq->whereJsonDoesntContain('labels', $other);
                        }
                    });
                }
            });
        }
        $unreadCount = $unreadCountQuery->count();

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
            ] : null,
            'leadStatuses' => LeadStatus::where('created_by', createdBy())->where('status', 'active')
                ->orderBy('order', 'asc')->orderBy('id', 'asc')
                ->get(['id', 'name', 'color']),
            'leadSources' => LeadSource::where('created_by', createdBy())->where('status', 'active')
                ->get(['id', 'name']),
            'accountIndustries' => AccountIndustry::where('created_by', createdBy())->where('status', 'active')->get(['id', 'name']),
            'campaigns' => Campaign::where('created_by', createdBy())->where('status', 'active')
                ->get(['id', 'name']),
            'users' => User::where('created_by', createdBy())->where('status', 'active')->get(['id', 'name', 'email']),
        ]);
    }

    /**
     * Fetch threads for a specific folder/filter.
     */
    public function threads(Request $request)
    {
        $user = auth()->user();
        $companyId = $user->creatorId();
        $folder = $request->get('folder', 'inbox');
        $search = $request->get('search');

        $query = EmailThread::with(['leads', 'contacts', 'latestMessage', 'assignments:id,name,avatar'])
            ->where('created_by', $companyId)
            ->orderByDesc('last_message_at');

        // Apply sync strategy filtering if in category mode
        $gmailAccount = GmailAccount::where('user_id', $companyId)->first();
        if (!$gmailAccount) {
            $gmailAccount = GmailAccount::whereHas('user', function($q) use ($companyId) {
                $q->where('created_by', $companyId);
            })->first();
        }

        if ($gmailAccount && $gmailAccount->sync_strategy === 'categories' && !empty($gmailAccount->sync_categories)) {
            $syncCategories = $gmailAccount->sync_categories;
            $hasPrimary = in_array('PRIMARY', $syncCategories);

            $query->where(function($q) use ($syncCategories, $hasPrimary) {
                foreach ($syncCategories as $category) {
                    if ($category !== 'PRIMARY') {
                        $q->orWhereJsonContains('labels', 'CATEGORY_' . strtoupper($category));
                    }
                }
                
                if ($hasPrimary) {
                    $q->orWhere(function($sq) {
                        $otherCategories = ['CATEGORY_SOCIAL', 'CATEGORY_PROMOTIONS', 'CATEGORY_UPDATES', 'CATEGORY_FORUMS'];
                        foreach ($otherCategories as $other) {
                            $sq->whereJsonDoesntContain('labels', $other);
                        }
                    });
                }
            });
        }

        // Apply search filter
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('subject', 'like', "%{$search}%")
                  ->orWhere('snippet', 'like', "%{$search}%")
                  ->orWhereJsonContains('participants', $search);
            });
        }

        // Apply folder/status filtering
        if ($folder === 'sent') {
            $query->whereHas('messages', function ($q) use ($companyId) {
                $gmailAccount = GmailAccount::whereHas('user', function($qu) use ($companyId) {
                    $qu->where('id', $companyId)->orWhere('created_by', $companyId);
                })->first();

                if ($gmailAccount) {
                    $q->where('from_email', strtolower($gmailAccount->gmail_address));
                }
            });
        } elseif ($folder === 'unassigned') {
            // Smart Unassigned: exclude if explicitly linked AND exclude if participant matches known Lead/Contact email
            $query->whereDoesntHave('leads')
                ->whereDoesntHave('contacts')
                ->where(function ($q) use ($companyId) {
                    $q->whereNotExists(function ($sq) use ($companyId) {
                        $sq->selectRaw(1)
                            ->from('leads')
                            ->where('leads.created_by', $companyId)
                            ->whereRaw("email_threads.participants LIKE CONCAT('%', leads.email, '%')");
                    })->whereNotExists(function ($sq) use ($companyId) {
                        $sq->selectRaw(1)
                            ->from('contacts')
                            ->where('contacts.created_by', $companyId)
                            ->whereRaw("email_threads.participants LIKE CONCAT('%', contacts.email, '%')");
                    });
                });
        } elseif ($folder === 'unassigned_staff') {
            // New folder: threads not assigned to any staff member
            $query->whereDoesntHave('assignments');
        } elseif ($folder === 'my_assignments') {
            // New folder: threads assigned to the current user
            $query->whereHas('assignments', function($q) use ($user) {
                $q->where('user_id', $user->id);
            });
        } elseif ($folder === 'history') {
            // In history view, we might want to show everything, including closed
        }

        // Default to "Open" status unless explicitly requesting history or a specific status
        if (!in_array($folder, ['history', 'closed']) && !$request->has('status')) {
            $query->where(function($q) {
                $q->where('status', 'Open')->orWhereNull('status');
            });
        } elseif ($folder === 'closed') {
            $query->where('status', 'Closed');
        }

        $threads = $query->paginate(20);

        // Pre-fetch matching leads for threads that aren't linked yet
        $threads->getCollection()->transform(function ($thread) use ($companyId) {
            $thread->suggested_leads = [];
            if ($thread->leads->isEmpty()) {
                $emails = [];
                if (is_array($thread->participants)) {
                    foreach ($thread->participants as $p) {
                        $p = trim($p);
                        // Extract email from "Name <email@example.com>"
                        if (preg_match('/<([^>]+)>/', $p, $matches)) {
                            $emails[] = strtolower(trim($matches[1]));
                        } else {
                            // Try to extract any email-like string from the participant
                            if (preg_match('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $p, $matches)) {
                                $emails[] = strtolower(trim($matches[0]));
                            }
                        }
                    }
                }
                
                $emails = array_unique(array_filter($emails));
                
                if (!empty($emails)) {
                    $thread->suggested_leads = \App\Models\Lead::where('created_by', $companyId)
                        ->where(function($q) use ($emails) {
                            foreach ($emails as $email) {
                                $q->orWhereRaw('LOWER(email) = ?', [$email]);
                            }
                        })
                        ->get(['id', 'name', 'email']);
                }
            }
            return $thread;
        });

        return response()->json($threads);
    }

    /**
     * Update thread metadata (status, priority, follow_up_at).
     */
    public function update(Request $request, EmailThread $thread)
    {
        if ($thread->created_by !== auth()->user()->creatorId()) {
            abort(403);
        }

        $validated = $request->validate([
            'status' => 'nullable|string|in:Open,Closed',
            'priority' => 'nullable|string|in:Low,Medium,High',
            'follow_up_at' => 'nullable|date',
        ]);

        $thread->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Thread updated successfully.',
            'thread' => $thread->load(['messages.media', 'messages.sender', 'leads.leadStatus', 'contacts', 'assignments:id,name,avatar'])
        ]);
    }

    /**
     * Assign staff to a thread.
     */
    public function assign(Request $request, EmailThread $thread)
    {
        if ($thread->created_by !== auth()->user()->creatorId()) {
            abort(403);
        }

        $request->validate([
            'user_ids' => 'present|array',
            'user_ids.*' => 'exists:users,id'
        ]);

        $thread->assignments()->sync($request->user_ids);

        return response()->json([
            'success' => true,
            'message' => 'Thread assignments updated.',
            'thread' => $thread->load(['messages.media', 'messages.sender', 'leads.leadStatus', 'contacts', 'assignments:id,name,avatar'])
        ]);
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
                ->with('sender:id,name,avatar')
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
                        'user' => $msg->sender ? [
                            'id' => $msg->sender->id,
                            'name' => $msg->sender->name,
                            'avatar' => $msg->sender->avatar
                        ] : null, 
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

            $perPage = 20;
            $page = $request->get('page', 1);
            $offset = ($page - 1) * $perPage;
            
            $paginated = $merged->slice($offset, $perPage)->values();
            
            return response()->json([
                'data' => $paginated,
                'current_page' => (int)$page,
                'last_page' => ceil($merged->count() / $perPage),
                'total' => $merged->count()
            ]);
        }

        $activities = $gmailAccount->activities()
            ->with('user:id,name,avatar')
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($activities);
    }

    /**
     * Link a thread to an existing lead.
     */
    public function linkToLead(Request $request, EmailThread $thread)
    {
        $validated = $request->validate([
            'lead_id' => 'required|exists:leads,id',
        ]);

        $companyId = auth()->user()->creatorId();
        
        // Ensure the lead belongs to the user/company
        $lead = \App\Models\Lead::where('id', $validated['lead_id'])
            ->where('created_by', $companyId)
            ->firstOrFail();

        // Attach using pivot table
        $thread->leads()->syncWithoutDetaching([$lead->id => ['matched_via' => 'manual_link']]);

        return back()->with('success', 'Thread successfully linked to lead: ' . $lead->name);
    }

    /**
     * Perform a deep sync for a specific contact's historical emails.
     */
    public function syncContactHistory(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $companyId = auth()->user()->creatorId();
        $email = $request->get('email');
        $pageToken = $request->get('pageToken');

        // Find the Gmail account for this company
        $gmailAccount = GmailAccount::where('user_id', $companyId)->first();
        if (!$gmailAccount) {
            $gmailAccount = GmailAccount::whereHas('user', function($q) use ($companyId) {
                $q->where('created_by', $companyId);
            })->first();
        }

        if (!$gmailAccount) {
            return response()->json(['error' => 'No Gmail account connected'], 400);
        }

        try {
            $service = new GmailService($gmailAccount);
            
            // BUG-FIX: Ensure token is fresh before deep sync (solves 401)
            $service->refreshTokenIfNeeded();
            
            $stats = $service->syncContactHistory($email, $pageToken);

            return response()->json([
                'success' => true,
                'message' => "Successfully updated history for {$email}.",
                'stats' => $stats,
                'nextPageToken' => $stats['nextPageToken'] ?? null
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Paged background sync for the general inbox (Infinite Scroll).
     */
    public function syncInboxHistory(Request $request)
    {
        $companyId = auth()->user()->creatorId();
        
        $gmailAccount = GmailAccount::where('user_id', $companyId)->first();
        if (!$gmailAccount) {
            $gmailAccount = GmailAccount::whereHas('user', function($q) use ($companyId) {
                $q->where('created_by', $companyId);
            })->first();
        }

        if (!$gmailAccount) {
            return response()->json(['error' => 'No Gmail account connected'], 400);
        }

        try {
            $service = new GmailService($gmailAccount);
            $service->refreshTokenIfNeeded();

            $stats = $service->syncThreads(20, $gmailAccount->next_page_token);

            return response()->json([
                'success' => true,
                'stats' => $stats,
                'next_page_token' => $gmailAccount->fresh()->next_page_token
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
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

        // OPTIMIZATION: Use cursor to iterate through threads to avoid memory exhaustion (Fix 2.1)
        // Select only necessary columns and eager load names only
        $threads = EmailThread::where('created_by', $companyId)
            ->select(['id', 'participants', 'last_message_at'])
            ->with(['leads:id,name', 'contacts:id,name'])
            ->orderByDesc('last_message_at')
            ->cursor();

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
                }
            }
        }

        $perPage = 20;
        $page = $request->get('page', 1);
        $offset = ($page - 1) * $perPage;
        
        $sorted = collect($participants)->sortByDesc('last_activity_at')->values();
        $paginated = $sorted->slice($offset, $perPage)->values();
        
        return response()->json([
            'data' => $paginated,
            'current_page' => (int)$page,
            'last_page' => ceil($sorted->count() / $perPage),
            'total' => $sorted->count()
        ]);
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

        $thread->load(['messages.media', 'leads.leadStatus', 'contacts', 'assignments:id,name,avatar']);

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
                // Dispatch async sync so the user doesn't wait for Gmail sync (Fix 2.2)
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
                // Dispatch async sync so the user doesn't wait for Gmail sync (Fix 2.2)
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
