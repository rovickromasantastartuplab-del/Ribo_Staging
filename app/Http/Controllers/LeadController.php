<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\LeadStatus;
use App\Models\LeadSource;
use App\Models\User;
use App\Exports\LeadExport;
use App\Imports\LeadImport;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class LeadController extends Controller
{
    public function show($id)
    {
        $lead = Lead::with(['leadStatus', 'leadSource', 'assignedUser', 'creator', 'campaign.campaignType', 'accountIndustry', 'activities.user', 'comments.user'])
            ->where('created_by', createdBy())
            ->where(function ($q) {
                if (auth()->user()->type === 'company' || auth()->user()->can('manage-leads') || auth()->user()->can('view-leads')) {
                    $q->where('created_by', createdBy());
                } else {
                    $q->where('assigned_to', auth()->id());
                }
            })
            ->where('id', $id)
            ->firstOrFail();

        // Get related accounts if lead was converted
        $relatedAccounts = [];
        if ($lead->is_converted) {
            $relatedAccounts = \App\Models\Account::where('created_by', createdBy())
                ->where(function ($q) use ($lead) {
                    $q->where('name', 'like', '%' . $lead->company . '%')
                        ->orWhere('email', $lead->email);
                })
                ->with(['accountType', 'accountIndustry'])
                ->get();
        }

        // Get related contacts if lead was converted
        $relatedContacts = [];
        if ($lead->is_converted) {
            $relatedContacts = \App\Models\Contact::where('created_by', createdBy())
                ->where(function ($q) use ($lead) {
                    $q->where('name', $lead->name)
                        ->orWhere('email', $lead->email);
                })
                ->with(['account'])
                ->get();
        }

        // Get related meetings (both as parent and as attendee)
        $parentMeetings = \App\Models\Meeting::where('created_by', createdBy())
            ->where('parent_module', 'lead')
            ->where('parent_id', $id)
            ->with(['creator', 'assignedUser'])
            ->get();

        $attendeeMeetings = \App\Models\Meeting::where('created_by', createdBy())
            ->whereHas('attendees', function ($q) use ($id) {
                $q->where('attendee_type', 'lead')
                    ->where('attendee_id', $id);
            })
            ->with(['creator', 'assignedUser'])
            ->get();

        // Get related calls (both as parent and as attendee)
        $parentCalls = \App\Models\Call::where('created_by', createdBy())
            ->where('parent_module', 'lead')
            ->where('parent_id', $id)
            ->with(['creator', 'assignedUser'])
            ->get()
            ->map(function ($call) {
                $call->type = 'call';
                return $call;
            });

        $attendeeCalls = \App\Models\Call::where('created_by', createdBy())
            ->whereHas('attendees', function ($q) use ($id) {
                $q->where('attendee_type', 'lead')
                    ->where('attendee_id', $id);
            })
            ->with(['creator', 'assignedUser'])
            ->get()
            ->map(function ($call) {
                $call->type = 'call';
                return $call;
            });

        $meetings = $parentMeetings->merge($attendeeMeetings)->merge($parentCalls)->merge($attendeeCalls)->unique('id')->sortByDesc('start_date')->values();

        $activities = $lead->activities()->orderBy('created_at', 'asc')->get()->map(function ($a) {
            $a->is_lead_event = false;
            return $a;
        });

        $leadEvents = \App\Models\LeadEvent::where('lead_id', $lead->id)->get()->map(function ($e) {
            return (object) [
                'id' => 'evt_' . $e->id,
                'created_at' => $e->received_at,
                'title' => 'Received message via ' . ucfirst(str_replace('_', ' ', $e->channel)),
                'description' => $e->summary_text,
                'activity_type' => 'message',
                'is_lead_event' => true,
                'channel' => $e->channel,
                'user' => (object) ['name' => 'System (Omnichannel)', 'avatar' => null]
            ];
        });

        $streamItems = collect($activities)->merge($leadEvents)->sortBy('created_at')->values();

        return Inertia::render('leads/show', [
            'lead' => $lead,
            'streamItems' => $streamItems,
            'comments' => $lead->comments,
            'relatedAccounts' => $relatedAccounts,
            'relatedContacts' => $relatedContacts,
            'meetings' => $meetings
        ]);
    }

    public function index(Request $request)
    {
        $query = Lead::query()
            ->with(['leadStatus', 'leadSource', 'assignedUser', 'campaign', 'accountIndustry'])
            ->where(function ($q) {
                if (auth()->user()->type === 'company' || auth()->user()->can('manage-leads') || auth()->user()->can('view-leads')) {
                    $q->where('created_by', createdBy());
                } else {
                    $q->where('assigned_to', auth()->id());
                }
            });

        // Handle search
        if ($request->has('search') && !empty($request->search)) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                    ->orWhere('email', 'like', '%' . $request->search . '%')
                    ->orWhere('phone', 'like', '%' . $request->search . '%')
                    ->orWhere('company', 'like', '%' . $request->search . '%');
            });
        }



        // Handle filters
        if ($request->has('lead_status_id') && !empty($request->lead_status_id) && $request->lead_status_id !== 'all') {
            $query->where('lead_status_id', $request->lead_status_id);
        }

        if ($request->has('lead_source_id') && !empty($request->lead_source_id) && $request->lead_source_id !== 'all') {
            $query->where('lead_source_id', $request->lead_source_id);
        }

        if ($request->has('status') && !empty($request->status) && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->has('is_converted') && $request->is_converted !== 'all') {
            $query->where('is_converted', $request->is_converted === '1');
        }

        if ($request->has('assigned_to') && !empty($request->assigned_to) && $request->assigned_to !== 'all') {
            $query->where('assigned_to', $request->assigned_to);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Handle sorting
        if ($request->has('sort_field') && !empty($request->sort_field)) {
            $query->orderBy($request->sort_field, $request->sort_direction ?? 'asc');
        } else {
            $query->orderBy('id', 'desc');
        }

        if ($request->view === 'kanban' || empty($request->view)) {
            // Kanban view: don't fetch leads here — the frontend loads them
            // lazily per-column via the /leads/kanban API endpoint.
            $leads = collect(['data' => []]);

        } else {
            $leads = $query->paginate($request->per_page ?? 10);

            // Remove per_page from URL if it's default value
            if ($request->per_page == 10) {
                $request->query->remove('per_page');
            }
        }

        // Get data for dropdowns
        $isCompany = auth()->user()->hasRole('company');
        $canManageLeads = auth()->user()->can('manage-leads');
        $canViewAccounts = auth()->user()->can('manage-accounts') || auth()->user()->can('view-accounts');
        $canViewCampaigns = auth()->user()->can('manage-campaigns') || auth()->user()->can('view-campaigns');

        $leadStatuses = LeadStatus::where('created_by', createdBy())->where('status', 'active')
            ->orderBy('order', 'asc')->orderBy('id', 'asc')
            ->get(['id', 'name', 'color']);

        $leadSources = LeadSource::where('created_by', createdBy())->where('status', 'active')
            ->get(['id', 'name']);

        $accounts = \App\Models\Account::where('created_by', createdBy())->where('status', 'active')
            ->when(!$isCompany && !$canViewAccounts, function ($q) {
                $q->where('assigned_to', auth()->id());
            })
            ->get(['id', 'name']);

        $campaigns = \App\Models\Campaign::where('created_by', createdBy())->where('status', 'active')
            ->when(!$isCompany && !$canViewCampaigns, function ($q) {
                $q->where('assigned_to', auth()->id());
            })
            ->get(['id', 'name']);

        $accountIndustries = \App\Models\AccountIndustry::where('created_by', createdBy())->where('status', 'active')->get(['id', 'name']);

        $accountTypes = \App\Models\AccountType::where('created_by', createdBy())->where('status', 'active')->get(['id', 'name']);

        $users = [];
        if ($isCompany) {
            $users = \App\Models\User::where('created_by', createdBy())
                ->where('type', '!=', 'company')
                ->select('id', 'name', 'email')
                ->get();
        }

        return Inertia::render('leads/index', [
            'leads' => $leads,
            'leadStatuses' => $leadStatuses,
            'leadSources' => $leadSources,
            'accounts' => $accounts,
            'campaigns' => $campaigns,
            'accountIndustries' => $accountIndustries,
            'accountTypes' => $accountTypes,
            'users' => $users,
            'samplePath' => route('lead.download.template'),
            'filters' => $request->all(['view', 'search', 'lead_status_id', 'lead_source_id', 'status', 'is_converted', 'assigned_to', 'date_from', 'date_to', 'sort_field', 'sort_direction', 'per_page']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email:filter|max:255',
            'phone' => 'nullable|string|max:255',
            'company' => 'nullable|string|max:255',
            'account_name' => 'nullable|string|max:255',
            'account_industry_id' => 'nullable|exists:account_industries,id',
            'website' => 'nullable|string|max:255',
            'position' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'notes' => 'nullable|string',
            'value' => 'nullable|numeric|min:0',
            'lead_status_id' => 'nullable|exists:lead_statuses,id',
            'lead_source_id' => 'nullable|exists:lead_sources,id',
            'campaign_id' => 'nullable|exists:campaigns,id',
            'status' => 'nullable|in:active,inactive',
            'assigned_to' => 'nullable|exists:users,id',
            'email_thread_id' => 'nullable|exists:email_threads,id',
        ]);

        $validated['created_by'] = createdBy();
        $validated['status'] = $validated['status'] ?? 'active';

        // Auto-assign to current user if staff user
        if (auth()->user()->type !== 'company') {
            $validated['assigned_to'] = auth()->id();
        }

        $lead = Lead::create($validated);

        if ($lead && $request->has('email_thread_id')) {
            $thread = \App\Models\EmailThread::where('id', $request->email_thread_id)
                ->where('created_by', createdBy())
                ->first();
            if ($thread) {
                $thread->leads()->syncWithoutDetaching([$lead->id => ['matched_via' => 'manual_add_as_lead']]);
            }
        }

        if ($lead && !IsDemo()) {
            event(new \App\Events\LeadAssigned($lead));
        }

        // Check for errors and combine them
        $emailError = session()->pull('email_error');
        $twilioError = session()->pull('twilio_error');

        $errors = [];
        if ($emailError) {
            $errors[] = __('Email send failed: ') . $emailError;
        }
        if ($twilioError) {
            $errors[] = __('SMS send failed: ') . $twilioError;
        }

        if (!empty($errors)) {
            $message = __('Lead created successfully, but ') . implode(', ', $errors);
            return redirect()->back()->with('warning', $message);
        }

        return redirect()->back()->with('success', __('Lead created successfully.'));
    }

    public function update(Request $request, $leadId)
    {
        $lead = Lead::where('id', $leadId)
            ->where('created_by', createdBy())
            ->where(function ($q) {
                if (auth()->user()->type === 'company' || auth()->user()->can('manage-leads') || auth()->user()->can('view-leads')) {
                    $q->where('created_by', createdBy());
                } else {
                    $q->where('assigned_to', auth()->id());
                }
            })
            ->first();

        if ($lead) {
            try {
                $validated = $request->validate([
                    'name' => 'required|string|max:255',
                    'email' => 'nullable|email:filter|max:255',
                    'phone' => 'nullable|string|max:255',
                    'company' => 'nullable|string|max:255',
                    'account_name' => 'nullable|string|max:255',
                    'account_industry_id' => 'nullable|exists:account_industries,id',
                    'website' => 'nullable|string|max:255',
                    'position' => 'nullable|string|max:255',
                    'address' => 'nullable|string',
                    'notes' => 'nullable|string',
                    'value' => 'nullable|numeric|min:0',
                    'lead_status_id' => 'nullable|exists:lead_statuses,id',
                    'lead_source_id' => 'nullable|exists:lead_sources,id',
                    'campaign_id' => 'nullable|exists:campaigns,id',
                    'status' => 'nullable|in:active,inactive',
                    'assigned_to' => 'nullable|exists:users,id',
                ]);

                // Auto-assign to current user if staff user
                if (auth()->user()->type !== 'company') {
                    $validated['assigned_to'] = auth()->id();
                }

                $lead->fill($validated);

                if (isEmailTemplateEnabled('Lead Moved', createdBy()) && $lead->isDirty('lead_status_id')) {
                    $old = $lead->getOriginal('lead_status_id');
                    $new = $lead->lead_status_id;
                    $oldStatusName = \App\Models\LeadStatus::find($old)?->name ?? 'N/A';
                    $newStatusName = \App\Models\LeadStatus::find($new)?->name ?? 'N/A';

                    event(new \App\Events\LeadStatusChanged($lead, $oldStatusName, $newStatusName));
                }
                $lead->update($validated);

                return redirect()->back()->with('success', __('Lead updated successfully.'));
            } catch (\Exception $e) {
                return redirect()->back()->with('error', $e->getMessage() ?: __('Failed to update lead.'));
            }
        } else {
            return redirect()->back()->with('error', __('Lead not found.'));
        }
    }

    public function destroy($leadId)
    {
        $lead = Lead::where('id', $leadId)
            ->where('created_by', createdBy())
            ->where(function ($q) {
                if (auth()->user()->type === 'company' || auth()->user()->can('manage-leads') || auth()->user()->can('view-leads')) {
                    $q->where('created_by', createdBy());
                } else {
                    $q->where('assigned_to', auth()->id());
                }
            })
            ->first();

        if ($lead) {
            try {
                $lead->delete();
                return redirect()->back()->with('success', __('Lead deleted successfully.'));
            } catch (\Exception $e) {
                return redirect()->back()->with('error', $e->getMessage() ?: __('Failed to delete lead.'));
            }
        } else {
            return redirect()->back()->with('error', __('Lead not found.'));
        }
    }

    public function toggleStatus($leadId)
    {
        $lead = Lead::where('id', $leadId)
            ->where('created_by', createdBy())
            ->where(function ($q) {
                if (auth()->user()->type === 'company' || auth()->user()->can('manage-leads') || auth()->user()->can('view-leads')) {
                    $q->where('created_by', createdBy());
                } else {
                    $q->where('assigned_to', auth()->id());
                }
            })
            ->first();

        if ($lead) {
            try {
                $lead->status = $lead->status === 'active' ? 'inactive' : 'active';
                $lead->save();

                return redirect()->back()->with('success', __('Lead status updated successfully.'));
            } catch (\Exception $e) {
                return redirect()->back()->with('error', $e->getMessage() ?: __('Failed to update lead status.'));
            }
        } else {
            return redirect()->back()->with('error', __('Lead not found.'));
        }
    }

    public function deleteActivities($id)
    {
        $lead = Lead::where('id', $id)
            ->where('created_by', createdBy())
            ->where(function ($q) {
                if (auth()->user()->type === 'company' || auth()->user()->can('manage-leads') || auth()->user()->can('view-leads')) {
                    $q->where('created_by', createdBy());
                } else {
                    $q->where('assigned_to', auth()->id());
                }
            })
            ->firstOrFail();

        \App\Models\LeadActivity::where('lead_id', $lead->id)->delete();

        return redirect()->back()->with('success', __('All activities deleted successfully.'));
    }

    public function deleteActivity($leadId, $activityId)
    {
        $lead = Lead::where('id', $leadId)
            ->where('created_by', createdBy())
            ->where(function ($q) {
                if (auth()->user()->type === 'company' || auth()->user()->can('manage-leads') || auth()->user()->can('view-leads')) {
                    $q->where('created_by', createdBy());
                } else {
                    $q->where('assigned_to', auth()->id());
                }
            })
            ->firstOrFail();

        \App\Models\LeadActivity::where('id', $activityId)
            ->where('lead_id', $lead->id)
            ->delete();

        return redirect()->back()->with('success', __('Activity deleted successfully.'));
    }

    public function updateStatus(Request $request, $leadId)
    {
        $validated = $request->validate([
            'lead_status_id' => 'required|exists:lead_statuses,id',
        ]);

        $lead = Lead::where('id', $leadId)
            ->where('created_by', createdBy())
            ->where(function ($q) {
                if (auth()->user()->type === 'company' || auth()->user()->can('manage-leads') || auth()->user()->can('view-leads')) {
                    $q->where('created_by', createdBy());
                } else {
                    $q->where('assigned_to', auth()->id());
                }
            })
            ->firstOrFail();

        $lead->update([
            'lead_status_id' => $validated['lead_status_id']
        ]);

        $lead->load(['leadStatus', 'leadSource', 'assignedUser']);

        if ($request->wantsJson() || $request->expectsJson()) {
            return response()->json([
                'message' => __('Lead status updated successfully.'),
                'lead' => $lead
            ]);
        }

        return redirect()->back()->with('success', __('Lead status updated successfully.'));
    }

    public function kanban(Request $request)
    {
        $leadStatuses = LeadStatus::where('created_by', createdBy())
            ->where('status', 'active')
            ->orderBy('order', 'asc')
            ->orderBy('id', 'asc')
            ->get(['id', 'name', 'color']);

        // Build the base query with access control and filters
        $baseQuery = Lead::with(['leadStatus', 'leadSource', 'assignedUser'])
            ->where(function ($q) {
                if (auth()->user()->type === 'company' || auth()->user()->can('manage-leads') || auth()->user()->can('view-leads')) {
                    $q->where('created_by', createdBy());
                } else {
                    $q->where('assigned_to', auth()->id());
                }
            });

        // Handle search
        if ($request->has('search') && !empty($request->search)) {
            $baseQuery->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                    ->orWhere('email', 'like', '%' . $request->search . '%')
                    ->orWhere('phone', 'like', '%' . $request->search . '%')
                    ->orWhere('company', 'like', '%' . $request->search . '%');
            });
        }

        // Handle filters
        if ($request->has('lead_status_id') && !empty($request->lead_status_id) && $request->lead_status_id !== 'all') {
            $baseQuery->where('lead_status_id', $request->lead_status_id);
        }

        if ($request->has('lead_source_id') && !empty($request->lead_source_id) && $request->lead_source_id !== 'all') {
            $baseQuery->where('lead_source_id', $request->lead_source_id);
        }

        if ($request->has('status') && !empty($request->status) && $request->status !== 'all') {
            $baseQuery->where('status', $request->status);
        }

        if ($request->has('is_converted') && $request->is_converted !== 'all') {
            $baseQuery->where('is_converted', $request->is_converted === '1');
        }

        if ($request->has('assigned_to') && !empty($request->assigned_to) && $request->assigned_to !== 'all') {
            $baseQuery->where('assigned_to', $request->assigned_to);
        }

        if ($request->filled('date_from')) {
            $baseQuery->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $baseQuery->whereDate('created_at', '<=', $request->date_to);
        }

        $perPage = max(1, min(100, intval($request->per_page ?? 20)));

        // If a specific status_id is requested, return paginated leads for that column only
        if ($request->has('status_id') && !empty($request->status_id)) {
            $statusId = intval($request->status_id);
            $page = max(1, intval($request->page ?? 1));

            $columnQuery = clone $baseQuery;
            $columnQuery->where('lead_status_id', $statusId)->orderBy('id', 'desc');

            $total = $columnQuery->count();
            $leads = $columnQuery->forPage($page, $perPage)->get();

            return response()->json([
                'leads' => $leads->values(),
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => (int) ceil($total / $perPage),
                'has_more' => ($page * $perPage) < $total,
            ]);
        }

        // Initial load: return statuses with total counts only (no lead data for speed)
        $columnMeta = [];
        foreach ($leadStatuses as $status) {
            $countQuery = clone $baseQuery;
            $total = $countQuery->where('lead_status_id', $status->id)->count();

            $columnMeta[$status->id] = [
                'status' => $status,
                'total' => $total,
            ];
        }

        return response()->json([
            'columnMeta' => $columnMeta,
            'leadStatuses' => $leadStatuses->toArray(),
            'per_page' => $perPage,
        ]);
    }

    public function convertToAccount(Request $request, $leadId)
    {
        $lead = Lead::where('id', $leadId)
            ->where('created_by', createdBy())
            ->where(function ($q) {
                if (auth()->user()->type === 'company' || auth()->user()->can('manage-leads') || auth()->user()->can('view-leads')) {
                    $q->where('created_by', createdBy());
                } else {
                    $q->where('assigned_to', auth()->id());
                }
            })
            ->firstOrFail();

        $validated = $request->validate([
            'account_type_id' => 'required|exists:account_types,id',
            'website' => 'nullable|string|max:255',
            'address' => 'nullable|string',
        ]);

        // Create account from lead
        $account = \App\Models\Account::create([
            'name' => $lead->company ?: $lead->name,
            'email' => $lead->email,
            'phone' => $lead->phone,
            'website' => $validated['website'] ?? $lead->website,
            'account_type_id' => $validated['account_type_id'],
            'account_industry_id' => $lead->account_industry_id,
            'billing_address' => $validated['address'] ?? $lead->address,
            'assigned_to' => $lead->assigned_to,
            'status' => 'active',
            'created_by' => createdBy(),
        ]);

        // Mark lead as converted
        $lead->update(['is_converted' => true]);

        return redirect()->back()->with('success', __('Lead converted to account successfully.'));
    }

    public function convertToContact(Request $request, $leadId)
    {
        $lead = Lead::where('id', $leadId)
            ->where('created_by', createdBy())
            ->where(function ($q) {
                if (auth()->user()->type === 'company' || auth()->user()->can('manage-leads') || auth()->user()->can('view-leads')) {
                    $q->where('created_by', createdBy());
                } else {
                    $q->where('assigned_to', auth()->id());
                }
            })
            ->firstOrFail();

        $validated = $request->validate([
            'account_id' => 'required|exists:accounts,id',
            'position' => 'nullable|string|max:255',
            'address' => 'nullable|string',
        ]);

        // Create contact from lead
        $contact = \App\Models\Contact::create([
            'name' => $lead->name,
            'email' => $lead->email,
            'phone' => $lead->phone,
            'position' => $validated['position'] ?? $lead->position,
            'address' => $validated['address'] ?? $lead->address,
            'account_id' => $validated['account_id'],
            'assigned_to' => $lead->assigned_to,
            'status' => 'active',
            'created_by' => createdBy(),
        ]);

        // Mark lead as converted
        $lead->update(['is_converted' => true]);

        return redirect()->back()->with('success', __('Lead converted to contact successfully.'));
    }

    public function fileExport(Request $request)
    {
        if (!auth()->user()->can('export-leads')) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        $name = 'lead_' . date('Y-m-d_H-i-s');
        return Excel::download(new LeadExport($request), $name . '.xlsx');
    }

    public function downloadTemplate()
    {
        if (!auth()->user()->can('import-leads')) {
            return response()->json(['error' => __('Permission denied.')], 403);
        }

        $headers = [
            'name',
            'email',
            'phone',
            'company',
            'account_name',
            'website',
            'position',
            'address',
            'notes',
            'value',
            'status',
            'lead_status',
            'lead_source',
            'account_industry',
            'campaign'
        ];

        $callback = function () use ($headers) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $headers);
            fclose($file);
        };

        return response()->stream($callback, 200, [
            "Content-type" => "text/csv",
            "Content-Disposition" => "attachment; filename=sample-lead.csv",
            "Pragma" => "no-cache",
            "Cache-Control" => "must-revalidate, post-check=0, pre-check=0",
            "Expires" => "0"
        ]);
    }

    public function parseFile(Request $request)
    {
        if (!auth()->user()->can('import-leads')) {
            return response()->json(['error' => __('Permission denied.')], 403);
        }

        $request->validate([
            'file' => 'required|mimes:csv,txt,xls,xlsx|max:65536',
        ]);

        try {
            ini_set('max_execution_time', '300');
            set_time_limit(300);

            $file = $request->file('file');
            $extension = strtolower($file->getClientOriginalExtension());

            // Store the file temporarily for later import
            $importDir = storage_path('app/imports');
            if (!file_exists($importDir)) {
                mkdir($importDir, 0755, true);
            }
            $tempFileName = 'import_' . auth()->id() . '_' . time() . '_' . uniqid() . '.' . $extension;
            $file->move($importDir, $tempFileName);
            $storedFilePath = $importDir . '/' . $tempFileName;

            $headers = [];
            $previewData = [];

            if (in_array($extension, ['csv', 'txt'])) {
                // Use native PHP fgetcsv for memory efficiency
                $handle = fopen($storedFilePath, 'r');
                if ($handle === false) {
                    throw new \Exception('Could not open file');
                }

                // Read header row
                $headerRow = fgetcsv($handle);
                if ($headerRow === false) {
                    fclose($handle);
                    throw new \Exception('File is empty or invalid');
                }
                $headers = array_map('trim', $headerRow);
                // Strip BOM from first column (Excel-exported CSVs add invisible \xEF\xBB\xBF)
                if (!empty($headers[0])) {
                    $headers[0] = preg_replace('/^\x{FEFF}/u', '', $headers[0]);
                }
                $headers = array_filter($headers, fn($h) => $h !== '');

                // Read only first 3 data rows for preview
                $previewCount = 0;
                while (($row = fgetcsv($handle)) !== false && $previewCount < 3) {
                    $rowData = [];
                    foreach ($headers as $idx => $headerName) {
                        $rowData[$headerName] = isset($row[$idx]) ? trim($row[$idx]) : '';
                    }
                    if (!empty(array_filter($rowData, fn($v) => $v !== ''))) {
                        $previewData[] = $rowData;
                        $previewCount++;
                    }
                }
                fclose($handle);
            } else {
                // XLS/XLSX: use PhpSpreadsheet but only read headers + 3 preview rows
                $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($storedFilePath);
                $worksheet = $spreadsheet->getActiveSheet();
                $highestColumn = $worksheet->getHighestColumn();
                $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);

                $headerMap = [];
                for ($col = 1; $col <= $highestColumnIndex; $col++) {
                    $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
                    $value = $worksheet->getCell($colLetter . '1')->getValue();
                    if ($value !== null && $value !== '') {
                        $strValue = trim((string) $value);
                        $headers[] = $strValue;
                        $headerMap[$colLetter] = $strValue;
                    }
                }

                // Read only first 3 data rows for preview
                $highestRow = min($worksheet->getHighestRow(), 5); // header + up to 4 rows (to get 3 non-empty)
                for ($row = 2; $row <= $highestRow && count($previewData) < 3; $row++) {
                    $rowData = [];
                    foreach ($headerMap as $colLetter => $headerName) {
                        $colValue = $worksheet->getCell($colLetter . $row)->getValue();
                        $rowData[$headerName] = $colValue !== null ? trim((string) $colValue) : '';
                    }
                    if (!empty(array_filter($rowData, fn($v) => $v !== ''))) {
                        $previewData[] = $rowData;
                    }
                }

                // Free memory
                $spreadsheet->disconnectWorksheets();
                unset($spreadsheet);
            }

            return response()->json([
                'excelColumns' => array_values($headers),
                'previewData' => $previewData,
                'tempFile' => $tempFileName,
            ]);
        } catch (\Throwable $e) {
            // Clean up stored file on error
            if (isset($storedFilePath) && file_exists($storedFilePath)) {
                unlink($storedFilePath);
            }
            return response()->json(['error' => __('Failed to parse file: :error', ['error' => $e->getMessage()])], 500);
        }
    }

    public function bulkDelete(Request $request)
    {
        if (!auth()->user()->can('delete-leads')) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:leads,id',
        ]);

        try {
            $leadsQuery = Lead::whereIn('id', $validated['ids'])
                ->where('created_by', createdBy());

            // Check view/manage scope essentially (users can only delete theirs unless admin/manager)
            $leadsQuery->where(function ($q) {
                if (auth()->user()->type === 'company' || auth()->user()->can('manage-leads') || auth()->user()->can('view-leads')) {
                    $q->where('created_by', createdBy());
                } else {
                    $q->where('assigned_to', auth()->id());
                }
            });

            $leadsParamsCount = $leadsQuery->count();

            if ($leadsParamsCount === 0) {
                return redirect()->back()->with('warning', __('No valid leads selected to delete.'));
            }

            $leadsQuery->delete();

            return redirect()->back()->with('success', __('Successfully deleted :count leads.', ['count' => $leadsParamsCount]));

        } catch (\Exception $e) {
            return redirect()->back()->with('error', __('Failed to delete leads: :error', ['error' => $e->getMessage()]));
        }
    }

    public function fileImport(Request $request)
    {
        if (!auth()->user()->can('import-leads')) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        $rules = [
            'mapping' => 'required|array',
            'tempFile' => 'required|string',
        ];

        $validator = \Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            $messages = $validator->getMessageBag();
            return redirect()->back()->with('error', $messages->first());
        }

        try {
            ini_set('max_execution_time', '600');
            set_time_limit(600);

            $mapping = $request->mapping; // { dbField => excelColumn }
            $tempFileName = basename($request->tempFile); // Sanitize filename
            $storedFilePath = storage_path('app/imports/' . $tempFileName);

            if (!file_exists($storedFilePath)) {
                return redirect()->back()->with('error', __('Import file not found. Please re-upload the file.'));
            }

            $extension = strtolower(pathinfo($storedFilePath, PATHINFO_EXTENSION));

            // Create a new mapped CSV for LeadImport
            $mappedFile = storage_path('app/imports/mapped_' . time() . '_' . uniqid() . '.csv');
            $outHandle = fopen($mappedFile, 'w');

            // Write headers (database field names)
            $dbFields = array_keys($mapping);
            fputcsv($outHandle, $dbFields);

            if (in_array($extension, ['csv', 'txt'])) {
                // Stream the original CSV line by line
                $inHandle = fopen($storedFilePath, 'r');
                $headerRow = fgetcsv($inHandle);
                // Strip BOM from first column (Excel-exported CSVs add invisible \xEF\xBB\xBF)
                if (!empty($headerRow[0])) {
                    $headerRow[0] = preg_replace('/^\x{FEFF}/u', '', $headerRow[0]);
                }
                $headerRow = array_map('trim', $headerRow);

                // Build index map: excelColumnName => column index
                $colIndexMap = [];
                foreach ($headerRow as $idx => $colName) {
                    $colIndexMap[$colName] = $idx;
                }

                while (($row = fgetcsv($inHandle)) !== false) {
                    $mappedRow = [];
                    foreach ($mapping as $dbField => $excelColumn) {
                        $colIdx = $colIndexMap[$excelColumn] ?? null;
                        $mappedRow[] = ($colIdx !== null && isset($row[$colIdx])) ? trim($row[$colIdx]) : '';
                    }
                    // Skip completely empty rows
                    if (!empty(array_filter($mappedRow, fn($v) => $v !== ''))) {
                        fputcsv($outHandle, $mappedRow);
                    }
                }
                fclose($inHandle);
            } else {
                // XLS/XLSX: use PhpSpreadsheet to read
                $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($storedFilePath);
                $worksheet = $spreadsheet->getActiveSheet();
                $highestColumn = $worksheet->getHighestColumn();
                $highestRow = $worksheet->getHighestRow();
                $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);

                // Read headers from spreadsheet
                $headerMap = [];
                for ($col = 1; $col <= $highestColumnIndex; $col++) {
                    $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
                    $value = $worksheet->getCell($colLetter . '1')->getValue();
                    if ($value !== null && $value !== '') {
                        $headerMap[trim((string) $value)] = $colLetter;
                    }
                }

                // Read data rows and apply mapping
                for ($row = 2; $row <= $highestRow; $row++) {
                    $mappedRow = [];
                    foreach ($mapping as $dbField => $excelColumn) {
                        $colLetter = $headerMap[$excelColumn] ?? null;
                        if ($colLetter) {
                            $cellValue = $worksheet->getCell($colLetter . $row)->getValue();
                            $mappedRow[] = $cellValue !== null ? trim((string) $cellValue) : '';
                        } else {
                            $mappedRow[] = '';
                        }
                    }
                    if (!empty(array_filter($mappedRow, fn($v) => $v !== ''))) {
                        fputcsv($outHandle, $mappedRow);
                    }
                }

                $spreadsheet->disconnectWorksheets();
                unset($spreadsheet);
            }

            fclose($outHandle);

            $import = new LeadImport();
            Excel::import($import, $mappedFile);

            // Clean up temp files
            if (file_exists($storedFilePath)) {
                unlink($storedFilePath);
            }
            if (file_exists($mappedFile)) {
                unlink($mappedFile);
            }

            $message = __('Import completed: :added leads added, :skipped leads skipped', [
                'added' => $import->getAddedCount(),
                'skipped' => $import->getSkippedCount()
            ]);

            return redirect()->back()
                ->with('success', $message)
                ->with('skippedRows', $import->getSkippedRows());
        } catch (\Exception $e) {
            // Clean up on error
            if (isset($storedFilePath) && file_exists($storedFilePath)) {
                unlink($storedFilePath);
            }
            if (isset($mappedFile) && file_exists($mappedFile)) {
                unlink($mappedFile);
            }
            return redirect()->back()->with('error', __('Failed to import: :error', ['error' => $e->getMessage()]));
        }
    }
}
