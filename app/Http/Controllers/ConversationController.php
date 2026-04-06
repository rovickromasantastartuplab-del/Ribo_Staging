<?php

namespace App\Http\Controllers;

use App\Models\GmailAccount;
use App\Models\EmailThread;
use App\Models\EmailMessage;
use App\Models\ThreadFollowUpStage;
use App\Models\ThreadFollowUpQueue;
use App\Services\GmailService;
use App\Models\LeadStatus;
use App\Models\LeadSource;
use App\Models\AccountIndustry;
use App\Models\Campaign;
use App\Models\User;
use App\Models\Account;
use App\Models\Opportunity;
use App\Models\OpportunityStage;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
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
            'opportunityStages' => OpportunityStage::where('created_by', createdBy())->where('status', 'active')
                ->orderBy('order', 'asc')->orderBy('id', 'asc')
                ->get(['id', 'name', 'color', 'probability']),
        ]);
    }

    /**
     * Attach opportunities to each linked lead (matched by account email = lead email).
     */
    protected function attachLeadOpportunities(EmailThread $thread): void
    {
        $companyId = $thread->created_by;
        foreach ($thread->leads as $lead) {
            $opps = collect();
            if ($lead->email) {
                $email = strtolower(trim($lead->email));
                $accountIds = Account::where('created_by', $companyId)
                    ->whereRaw('LOWER(email) = ?', [$email])
                    ->pluck('id');
                if ($accountIds->isNotEmpty()) {
                    $opps = Opportunity::whereIn('account_id', $accountIds)
                        ->where('created_by', $companyId)
                        ->with('opportunityStage')
                        ->orderByDesc('updated_at')
                        ->get();
                }
            }
            $lead->setRelation('opportunities', $opps);
        }
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
     * Fetch all threads with a follow-up date for the calendar.
     */
    public function calendarEvents(Request $request)
    {
        $companyId = auth()->user()->creatorId();
        
        // Fetch pending automated follow-ups from the queue
        $queueItems = ThreadFollowUpQueue::where('status', 'pending')
            ->whereNotNull('scheduled_at')
            ->whereHas('stage.emailThread', function($q) use ($companyId) {
                $q->where('created_by', $companyId);
            })
            ->with(['stage.emailThread'])
            ->get();

        $events = $queueItems->map(function ($item) {
            $thread = $item->stage->emailThread;
            $title = $thread->subject ?: 'Automated Follow up (No Subject)';
            if (empty($thread->subject) && is_array($thread->participants) && count($thread->participants) > 0) {
                $firstParticipant = $thread->participants[0];
                if (preg_match('/^(.*)</', $firstParticipant, $matches)) {
                    $title = trim($matches[1]) ?: $title;
                } else {
                    $title = $firstParticipant;
                }
            }
            
            return [
                'id' => $thread->id,
                'queue_id' => $item->id,
                'title' => $title . " (Stage " . $item->stage->stage_number . ")",
                'start' => $item->scheduled_at->toIso8601String(),
                'allDay' => false,
                'type' => 'automated_follow_up',
                'status' => $thread->status
            ];
        });

        return response()->json($events);
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

        $thread->load([
            'messages' => function ($query) {
                $query->with(['media', 'sender'])->reorder('sent_at', 'desc');
            },
            'leads.leadStatus',
            'contacts',
            'assignments:id,name,avatar',
            'gmailAccount',
        ]);
        $this->attachLeadOpportunities($thread);

        return response()->json([
            'success' => true,
            'message' => 'Thread updated successfully.',
            'thread' => $thread,
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

        $thread->load([
            'messages' => function ($query) {
                $query->with(['media', 'sender'])->reorder('sent_at', 'desc');
            },
            'leads.leadStatus',
            'contacts',
            'assignments:id,name,avatar',
            'gmailAccount',
        ]);
        $this->attachLeadOpportunities($thread);

        return response()->json([
            'success' => true,
            'message' => 'Thread assignments updated.',
            'thread' => $thread,
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

        $service = new GmailService($thread->gmailAccount);

        // Return newest to oldest for flex-col-reverse display
        $messages = collect($messagesPaginated->items())->values();

        // Inject live attachment metadata from Gmail for messages without local storage
        $messages->each(function($msg) use ($service) {
            if ($msg->media->isEmpty()) {
                // This is a lightweight call to fetch only the file names/IDs from Gmail
                $msg->live_attachments = $service->getMessageAttachmentsInfo($msg->gmail_message_id);
            }
        });

        $thread->load(['leads.leadStatus', 'contacts', 'assignments:id,name,avatar', 'gmailAccount']);
        $this->attachLeadOpportunities($thread);

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
     * Proxy a download request directly from the Gmail API to the user's browser.
     * This avoids storing the file on the CRM's server.
     */
    public function downloadAttachment(Request $request, EmailMessage $message, string $attachmentId)
    {
        if ($message->created_by !== auth()->user()->creatorId()) {
            abort(403);
        }

        $filename = $request->query('filename', 'attachment');

        $service = new GmailService($message->thread->gmailAccount);
        $result = $service->downloadAttachmentRaw($message->gmail_message_id, $attachmentId);

        if (!$result) {
            abort(404, 'Attachment not found or failed to download from Gmail.');
        }

        return response()->streamDownload(function () use ($result) {
            echo $result['data'];
        }, $filename);
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

    /**
     * Save follow-up stages for a thread.
     */
    public function storeFollowUpStages(Request $request, EmailThread $thread)
    {
        if ($thread->created_by !== auth()->user()->creatorId()) {
            abort(403);
        }

        $request->validate([
            'stages' => 'array|max:3',
            'stages.*.trigger_type' => 'required|in:no_reply,no_open,no_click,drip',
            'stages.*.delay_days' => 'required|integer|min:1|max:90',
            'stages.*.subject' => 'required|string|max:255',
            'stages.*.body' => 'required|string',
        ]);

        // Replace all existing stages for this thread (wrapped in transaction to prevent partial state)
        DB::transaction(function () use ($thread, $request) {
            $thread->followUpStages()->delete();

            if ($request->has('stages') && is_array($request->stages)) {
                foreach ($request->stages as $index => $stageData) {
                    $stage = ThreadFollowUpStage::create([
                        'email_thread_id' => $thread->id,
                        'stage_number' => $index + 1,
                        'trigger_type' => $stageData['trigger_type'],
                        'delay_days' => $stageData['delay_days'],
                        'subject' => $stageData['subject'],
                        'body' => $stageData['body'],
                    ]);

                    // Auto-kickoff for Stage 1
                    if ($index === 0) {
                        // Find the latest message in this thread to act as the anchor
                        // We typically follow up based on our last message to them
                        $lastMessage = $thread->messages()->whereNotNull('gmail_message_id')->latest('sent_at')->first();
                        
                        if ($lastMessage) {
                            // Determine recipient: if we sent the last message, send to 'to_emails'
                            // If they sent the last message, send to 'from_email'
                            $myEmail = $thread->gmailAccount->gmail_address;
                            $recipient = $lastMessage->from_email === $myEmail 
                                ? ($lastMessage->to_emails[0] ?? null) 
                                : $lastMessage->from_email;

                            if ($recipient) {
                                ThreadFollowUpQueue::create([
                                    'thread_follow_up_stage_id' => $stage->id,
                                    'recipient_email' => $recipient,
                                    'gmail_thread_id' => $thread->gmail_thread_id,
                                    'gmail_message_id' => $lastMessage->gmail_message_id,
                                    'status' => 'pending',
                                    'scheduled_at' => ($lastMessage->sent_at ?? now())->addDays($stage->delay_days),
                                ]);
                            }
                        }
                    }
                }
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Follow-up stages saved.',
            'stages' => $thread->followUpStages()->get(),
        ]);
    }

    /**
     * Get follow-up stages and queue status for a thread.
     */
    public function getFollowUpStages(EmailThread $thread)
    {
        if ($thread->created_by !== auth()->user()->creatorId()) {
            abort(403);
        }

        $stages = $thread->followUpStages()->with(['queueItems' => function ($q) {
            $q->orderBy('scheduled_at');
        }])->get();

        return response()->json([
            'stages' => $stages,
        ]);
    }

    /**
     * Get default follow-up templates for the frontend.
     */
    public function getFollowUpTemplates()
    {
        return response()->json([
            'templates' => [
                [
                    'id' => 'nudge',
                    'name' => 'Gentle Nudge',
                    'subject' => 'Re: {Subject}',
                    'body' => view('emails.followups.nudge')->render(),
                ],
                [
                    'id' => 'value_add',
                    'name' => 'Value Add',
                    'subject' => 'Quick resource for {Company}',
                    'body' => view('emails.followups.value_add')->render(),
                ],
                [
                    'id' => 'breakup',
                    'name' => 'Break-up',
                    'subject' => 'Closing files',
                    'body' => view('emails.followups.breakup')->render(),
                ],
            ]
        ]);
    }
}
