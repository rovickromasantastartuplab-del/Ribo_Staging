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
    public function index(Request $request)
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

        $unreadCount = $this->getGlobalUnreadCount($companyId, $gmailAccount);

        return Inertia::render('conversations/index', [
            'initialFolder' => 'inbox',
            'selectedThreadId' => $request->thread_id ? (int) $request->thread_id : null,
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

        // Default to "Open" status unless explicitly requesting history, archive, closed, or a specific status
        if (!in_array($folder, ['history', 'closed', 'archive']) && !$request->has('status')) {
            $query->where(function($q) {
                $q->where('status', 'Open')->orWhereNull('status');
            });
        } elseif ($folder === 'closed') {
            $query->where('status', 'Closed');
        } elseif ($folder === 'archive') {
            $query->where('status', 'Archive');
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

        $unreadCount = $this->getGlobalUnreadCount($companyId, $gmailAccount);

        return response()->json([
            'threads' => $threads,
            'unread_count' => $unreadCount,
        ]);
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
            'status' => 'nullable|string|in:Open,Closed,Archive',
            'priority' => 'nullable|string|in:Low,Medium,High',
            'follow_up_at' => 'nullable|date',
        ]);

        $statusChanged = isset($validated['status']) && $validated['status'] !== $thread->status;
        $wasArchive = $thread->status === 'Archive';

        $thread->update($validated);
        $thread = $thread->fresh();

        // Handle Gmail Sync (Archive/Unarchive)
        if ($statusChanged) {
            try {
                $account = $thread->gmailAccount;
                if ($account) {
                    $service = new \App\Services\GmailService($account);
                    
                    if ($thread->status === 'Archive') {
                        // Moving TO archive
                        $service->archiveThread($thread->gmail_thread_id);
                    } elseif ($wasArchive && in_array($thread->status, ['Open', 'Closed'])) {
                        // Restoring FROM archive
                        $service->unarchiveThread($thread->gmail_thread_id);
                    }
                }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Failed to sync Archive/Unarchive status to Gmail', [
                    'thread_id' => $thread->id,
                    'new_status' => $thread->status,
                    'was_archive' => $wasArchive,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Thread updated successfully.',
            'thread' => $thread->load(['messages.media', 'messages.sender', 'leads.leadStatus', 'contacts', 'assignments:id,name,avatar', 'gmailAccount'])
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
        $thread = $thread->fresh();

        return response()->json([
            'success' => true,
            'message' => 'Thread assignments updated.',
            'thread' => $thread->load(['messages.media', 'messages.sender', 'leads.leadStatus', 'contacts', 'assignments:id,name,avatar', 'gmailAccount'])
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
            
            // 0. Resolve Identity (Lead or Contact if provided)
            $leadId = $request->get('lead_id');
            $contactId = $request->get('contact_id');
            $participantEmails = [$email];
            
            $lead = null;
            $contact = null;

            if ($leadId) {
                $lead = \App\Models\Lead::where('created_by', $companyId)->find($leadId);
                if ($lead && $lead->email) $participantEmails[] = $lead->email;
            }
            if ($contactId) {
                $contact = \App\Models\Contact::where('created_by', $companyId)->find($contactId);
                if ($contact && $contact->email) $participantEmails[] = $contact->email;
            }
            
            $participantEmails = array_unique(array_filter($participantEmails));

            // 1. System Activities (User Logs)
            $systemActivities = \App\Models\GmailAccountActivity::where('gmail_account_id', $gmailAccount->id)
                ->where(function($q) use ($participantEmails) {
                    foreach ($participantEmails as $e) {
                        $q->orWhere('description', 'like', "%{$e}%");
                    }
                })
                ->with('user:id,name,avatar')
                ->get()
                ->map(function($act) {
                    return [
                        'id' => 'sync_' . $act->id,
                        'activity_type' => 'system_log',
                        'title' => $act->type ? str_replace('_', ' ', ucfirst($act->type)) : __('System Activity'),
                        'description' => $act->description,
                        'created_at' => $act->created_at,
                        'user' => $act->user ? [
                            'id' => $act->user->id,
                            'name' => $act->user->name,
                            'avatar' => $act->user->avatar_url
                        ] : null,
                    ];
                });

            // 2. Email Messages (Sent/Received)
            $emailActivities = \App\Models\EmailMessage::where('created_by', $companyId)
                ->where(function($q) use ($participantEmails) {
                    foreach ($participantEmails as $e) {
                        $q->orWhere('from_email', $e)
                          ->orWhereJsonContains('to_emails', $e)
                          ->orWhereJsonContains('cc_emails', $e);
                    }
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
                            'avatar' => $msg->sender->avatar_url
                        ] : null, 
                        'metadata' => [
                            'thread_id' => $msg->email_thread_id,
                        ],
                    ];
                });

            // 3. CRM Activities (if Lead exist)
            $crmActivities = collect();
            
            $activeLeadId = $leadId ?: ($lead ? $lead->id : null);
            if (!$activeLeadId) {
                $lead = \App\Models\Lead::where('created_by', $companyId)->where('email', $email)->first();
                $activeLeadId = $lead?->id;
            }

            if ($activeLeadId) {
                $activities = \App\Models\LeadActivity::where('lead_id', $activeLeadId)
                    ->with('user:id,name,avatar')
                    ->get()
                    ->map(function($act) {
                        return [
                            'id' => 'crm_' . $act->id,
                            'activity_type' => 'crm_log',
                            'title' => __('Lead Activity'),
                            'description' => $act->description || $act->comment,
                            'created_at' => $act->created_at,
                            'user' => $act->user ? [
                                'id' => $act->user->id,
                                'name' => $act->user->name,
                                'avatar' => $act->user->avatar_url
                            ] : null,
                        ];
                    });
                $crmActivities = $crmActivities->concat($activities);
            }

            // Merge and sort
            $merged = collect($systemActivities)
                ->concat($emailActivities)
                ->concat($crmActivities)
                ->sort(function($a, $b) {
                    // Sort by created_at DESC (primary)
                    $timeA = strtotime($a['created_at']);
                    $timeB = strtotime($b['created_at']);
                    if ($timeA != $timeB) {
                        return $timeB <=> $timeA;
                    }
                    // Sort by id DESC (secondary, stable tie-breaker)
                    return strcmp($b['id'], $a['id']);
                })
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

        $activities->getCollection()->transform(function($act) {
            if ($act->user) {
                $act->user->append('avatar_url');
            }
            return $act;
        });

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
            ->with(['leads:id,name,email', 'contacts:id,name,email']) // Eager load email for leads/contacts
            ->orderByDesc('last_message_at')
            ->cursor();

        $participants = [];

        foreach ($threads as $thread) {
            $linkedLead = $thread->leads->first();
            $linkedContact = $thread->contacts->first();
            
            // Priority 1: Group by Lead
            if ($linkedLead) {
                $pId = "lead_{$linkedLead->id}";
                if (!isset($participants[$pId])) {
                    $participants[$pId] = [
                        'type' => 'lead',
                        'id' => $linkedLead->id,
                        'name' => $linkedLead->name,
                        'email' => $linkedLead->email,
                        'last_activity_at' => $thread->last_message_at,
                    ];
                } else if ($thread->last_message_at > $participants[$pId]['last_activity_at']) {
                    $participants[$pId]['last_activity_at'] = $thread->last_message_at;
                }
                continue; // Move to next thread
            }

            // Priority 2: Group by Contact
            if ($linkedContact) {
                $pId = "contact_{$linkedContact->id}";
                if (!isset($participants[$pId])) {
                    $participants[$pId] = [
                        'type' => 'contact',
                        'id' => $linkedContact->id,
                        'name' => $linkedContact->name,
                        'email' => $linkedContact->email,
                        'last_activity_at' => $thread->last_message_at,
                    ];
                } else if ($thread->last_message_at > $participants[$pId]['last_activity_at']) {
                    $participants[$pId]['last_activity_at'] = $thread->last_message_at;
                }
                continue;
            }

            // Priority 3: Fallback to unique email (for unlinked participants)
            $threadParticipants = $thread->participants ?? [];
            foreach ($threadParticipants as $pEmail) {
                $pEmail = strtolower($pEmail);
                if ($pEmail === $companyEmail) continue;
                if ($search && !str_contains($pEmail, strtolower($search))) continue;

                if (!isset($participants[$pEmail])) {
                    $participants[$pEmail] = [
                        'type' => 'email',
                        'name' => explode('@', $pEmail)[0],
                        'email' => $pEmail,
                        'last_activity_at' => $thread->last_message_at,
                    ];
                } else if ($thread->last_message_at > $participants[$pEmail]['last_activity_at']) {
                    $participants[$pEmail]['last_activity_at'] = $thread->last_message_at;
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
    public function show(EmailThread $thread, Request $request)
    {
        // Ensure user has access to this company's threads
        if ($thread->created_by !== auth()->user()->creatorId()) {
            abort(403);
        }

        // Refresh to ensure we have the very latest state (especially after an async sync/reply)
        $thread = $thread->fresh();

        $perPage = $request->input('per_page', 30);
        
        $messagesPaginated = $thread->messages()
            ->with(['media', 'sender'])
            ->reorder('sent_at', 'desc')
            ->paginate($perPage);

        // Return newest to oldest for flex-col-reverse display
        $messages = collect($messagesPaginated->items())->values();

        $thread->load(['leads.leadStatus', 'contacts', 'assignments:id,name,avatar', 'gmailAccount']);
        
        // Ensure relations are attached so they are serialized in the response
        $thread->setRelation('messages', $messages);

        // Mark as read when user opens the thread
        if (!$thread->is_read) {
            $thread->update(['is_read' => true]);
        }
        
        return response()->json([
            'thread' => $thread,
            'messages_pagination' => [
                'current_page' => $messagesPaginated->currentPage(),
                'last_page' => $messagesPaginated->lastPage(),
                'has_more' => $messagesPaginated->hasMorePages(),
                'total' => $messagesPaginated->total(),
            ],
            'unread_count' => $this->getGlobalUnreadCount($thread->created_by, $thread->gmailAccount),
        ]);
    }

    /**
     * Compose a new email thread.
     */
    public function compose(Request $request)
    {
        $user = auth()->user();

        // Company owners bypass; staff must have send-conversations permission
        if ($user->type !== 'company' && !$user->can('send-conversations')) {
            abort(403, 'You do not have permission to send emails.');
        }

        $request->validate([
            'to' => 'required|email',
            'subject' => 'required|string|max:255',
            'body' => 'required|string',
            'cc' => 'nullable|string',
            'bcc' => 'nullable|string',
        ]);

        try {
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

            // Parse CC and BCC from comma-separated strings
            $cc = array_filter(array_map('trim', explode(',', $request->cc ?? '')));
            $bcc = array_filter(array_map('trim', explode(',', $request->bcc ?? '')));


            $success = $service->sendMessage(
                $request->to,
                $request->subject,
                $request->body,
                null,
                null,
                $cc,
                $attachments,
                $bcc
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

        $user = auth()->user();

        // Company owners bypass; staff must have reply-conversations permission
        if ($user->type === 'staff') {
            if (!$user->can('reply-conversations')) {
                return response()->json(['error' => 'You do not have permission to reply.'], 403);
            }
        }

        $request->validate([
            'body' => 'required|string',
            'cc' => 'nullable|string',
            'bcc' => 'nullable|string',
            'primary_to' => 'nullable|string|email',
            'reply_to_message_id' => 'nullable|exists:email_messages,id'
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

            if ($request->primary_to) {
                $primaryRecipient = $request->primary_to;
            } else {
                $primaryRecipient = $externalParticipants->first();
            }
            
            $ccRecipients = $externalParticipants->filter(fn($e) => strtolower($e) !== strtolower($primaryRecipient))->values()->all();

            // Resolve the specific message to reply to for proper In-Reply-To threading
            if ($request->reply_to_message_id) {
                $targetMessage = \App\Models\EmailMessage::find($request->reply_to_message_id);
                $replyToHeader = $targetMessage?->message_id_header;
            } else {
                $latestMessage = $thread->latestMessage;
                $replyToHeader = $latestMessage?->message_id_header;
            }
            
            $service = new \App\Services\GmailService($account);

            $attachments = $request->hasFile('attachments') ? $request->file('attachments') : [];

            // Parse CC and BCC from comma-separated strings
            $cc = array_filter(array_map('trim', explode(',', $request->cc ?? '')));
            $bcc = array_filter(array_map('trim', explode(',', $request->bcc ?? '')));

            $success = $service->sendMessage(
                $primaryRecipient,
                $thread->subject,
                $request->body,
                $thread->gmail_thread_id,
                $replyToHeader,
                $cc,
                $attachments,
                $bcc
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

    /**
     * Calculate global unread count for the company's active inbox filters.
     */
    private function getGlobalUnreadCount(int $companyId, ?GmailAccount $gmailAccount): int
    {
        $unreadCountQuery = EmailThread::where('created_by', $companyId)
            ->where('is_read', false)
            ->where(function($q) {
                $q->where('status', 'Open')->orWhereNull('status');
            });

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

        return $unreadCountQuery->count();
    }
}
