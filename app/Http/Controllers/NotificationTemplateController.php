<?php

namespace App\Http\Controllers;

use App\Models\NotificationTemplate;
use Illuminate\Http\Request;
use Inertia\Inertia;

class NotificationTemplateController extends Controller
{
    public function index(Request $request)
    {
        $query = NotificationTemplate::with('notificationTemplateLangs');

        // Filter by type if provided
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        // Search functionality
        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        // Sorting
        $sortField = $request->get('sort_field', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');
        $query->orderBy($sortField, $sortDirection);

        // Pagination
        $perPage = $request->get('per_page', 10);
        $templates = $query->paginate($perPage);

        return Inertia::render('notification-templates/index', [
            'templates' => $templates,
            'filters' => $request->only(['search', 'type', 'sort_field', 'sort_direction', 'per_page']),
            'types' => NotificationTemplate::getAvailableTypes()
        ]);
    }

    public function show(NotificationTemplate $notificationTemplate)
    {
        // Load company-specific content
        $template = $notificationTemplate->load(['notificationTemplateLangs' => function ($query) {
            if (auth()->user()->type === 'company') {
                $query->where('created_by', createdBy());
            }
        }]);
        $languagesArray = json_decode(file_get_contents(resource_path('lang/language.json')), true);
        $languages = [];
        foreach ($languagesArray as $lang) {
            $languages[$lang['code']] = $lang['name'];
        }

        // Template-specific variables based on notification type
        $variables = [];

        if ($template->name === 'Lead Create') {
            $variables = [
                '{lead_name}' => 'Lead Name',
                '{company_name}' => 'Company Name'
            ];
        } elseif ($template->name === 'Opportunity create') {
            $variables = [
                '{opportunity_name}' => 'Opportunity Name',
                '{amount}' => 'Opportunity Amount',
                '{account_name}' => 'Account Name',
                '{close_date}' => 'Close Date',
                '{company_name}' => 'Company Name'

            ];
        } elseif ($template->name === 'Account create') {
            $variables = [
                '{account_name}' => 'Account Name',
                '{company_name}' => 'Company Name'

            ];
        } elseif ($template->name === 'Quote Create') {
            $variables = [
                '{quote_number}' => 'Quote Number',
                '{account_name}' => 'Account Name',
                '{total_amount}' => 'Total Amount',
                '{valid_until}' => 'Valid Until Date',
                '{company_name}' => 'Company Name'
            ];
        } elseif ($template->name === 'Case Create') {
            $variables = [
                '{case_subject}' => 'Case Subject',
                '{company_name}' => 'Company Name'
            ];
        } elseif ($template->name === 'Meeting Create') {
            $variables = [
                '{meeting_subject}' => 'Meeting Subject',
                '{meeting_date}' => 'Meeting Date',
                '{meeting_time}' => 'Meeting Time',
                '{attendee_count}' => 'Attendee Count',
                '{company_name}' => 'Company Name'
            ];
        }

        return Inertia::render('notification-templates/show', [
            'template' => $template,
            'languages' => $languages,
            'variables' => $variables
        ]);
    }



    public function updateContent(NotificationTemplate $notificationTemplate, Request $request)
    {
        try {
            $request->validate([
                'lang' => 'required|string|max:10',
                'title' => 'required|string|max:255',
                'content' => 'required|string'
            ]);

            $notificationTemplate->notificationTemplateLangs()
                ->where('lang', $request->lang)
                ->where('created_by', createdBy())
                ->updateOrCreate(
                    [
                        'parent_id' => $notificationTemplate->id,
                        'lang' => $request->lang,
                        'created_by' => createdBy()
                    ],
                    [
                        'title' => $request->title,
                        'content' => $request->content
                    ]
                );

            return redirect()->back()->with('success', __('Notification content updated successfully.'));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', __('Failed to update notification content: :error', ['error' => $e->getMessage()]));
        }
    }
}
