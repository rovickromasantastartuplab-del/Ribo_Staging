<?php

namespace App\Http\Controllers;

use App\Models\EmailTemplate;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EmailTemplateController extends Controller
{
    public function index(Request $request)
    {
        $query = EmailTemplate::with('emailTemplateLangs');

        // Search functionality
        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%')
                ->orWhere('from', 'like', '%' . $request->search . '%');
        }

        // Sorting
        $sortField = $request->get('sort_field', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');
        $query->orderBy($sortField, $sortDirection);

        // Pagination
        $perPage = $request->get('per_page', 10);
        $templates = $query->paginate($perPage);

        return Inertia::render('email-templates/index', [
            'templates' => $templates,
            'filters' => $request->only(['search', 'sort_field', 'sort_direction', 'per_page'])
        ]);
    }

    public function show(EmailTemplate $emailTemplate)
    {
        $template = $emailTemplate->load('emailTemplateLangs');
        $languages = json_decode(file_get_contents(resource_path('lang/language.json')), true);

        // Template-specific variables
        $variables = [];

        if ($template->name === 'User Created') {
            $variables = [
                '{app_url}' => 'App URL',
                '{user_name}' => 'User Name',
                '{user_email}' => 'User Email',
                '{user_password}' => 'User Password',
                '{user_type}' => 'User Type',
                '{company_name}' => 'Company Name'
            ];
        } elseif ($template->name === 'Lead Assigned') {
            $variables = [
                '{lead_name}' => 'Lead Name',
                '{assigned_user_name}' => 'Assign User',
                '{lead_email}' => 'Lead Email',
                '{lead_phone}' => 'Lead Phone',
                '{lead_company}' => 'Lead Company',
                '{company_name}' => 'Company Name'
            ];
        } elseif ($template->name === 'Lead Moved') {
            $variables = [
                '{lead_name}' => 'Lead Name',
                '{assigned_user_name}' => 'Assign User',
                '{old_lead_stage}' => 'Old Status',
                '{new_lead_stage}' => 'New Status',
                '{lead_email}' => 'Lead Email',
                '{lead_phone}' => 'Lead Phone',
                '{lead_company}' => 'Lead Company',
                '{company_name}' => 'Company Name'
            ];
        } elseif ($template->name === 'Quote Created') {
            $variables = [
                '{quote_number}' => 'Quote Number',
                '{quote_name}' => 'Quote Name',
                '{billing_contact_name}' => 'Billing Contact Name',
                '{account_name}' => 'Account Name',
                '{quote_total}' => 'Quote Total Amount',
                '{quote_valid_until}' => 'Quote Valid Until Date',
                '{quote_status}' => 'Quote Status',
                '{assigned_user_name}' => 'Assigned User Name',
                '{assigned_user_email}' => 'Assigned User Email',
                '{company_name}' => 'Company Name'
            ];
        } elseif ($template->name === 'Quote Status Changed') {
            $variables = [
                '{quote_number}' => 'Quote Number',
                '{quote_name}' => 'Quote Name',
                '{billing_contact_name}' => 'Billing Contact Name',
                '{account_name}' => 'Account Name',
                '{quote_total}' => 'Quote Total Amount',
                '{quote_valid_until}' => 'Quote Valid Until Date',
                '{old_quote_status}' => 'Old Quote Status',
                '{new_quote_status}' => 'New Quote Status',
                '{assigned_user_name}' => 'Assigned User Name',
                '{assigned_user_email}' => 'Assigned User Email',
                '{company_name}' => 'Company Name'
            ];
        } elseif ($template->name === 'Task Assigned') {
            $variables = [
                '{task_title}' => 'Task Title',
                '{assigned_user_name}' => 'Assigned User Name',
                '{project_name}' => 'Project Name',
                '{task_priority}' => 'Task Priority',
                '{task_due_date}' => 'Task Due Date',
                '{task_status}' => 'Task Status',
                '{task_estimated_hours}' => 'Task Estimated Hours',
                '{task_description}' => 'Task Description',
                '{creator_name}' => 'Creator Name',
                '{creator_email}' => 'Creator Email',
                '{company_name}' => 'Company Name'
            ];
        } elseif ($template->name === 'Meeting Invitation') {
            $variables = [
                '{meeting_title}' => 'Meeting Title',
                '{attendee_name}' => 'Attendee Name',
                '{meeting_date}' => 'Meeting Date',
                '{meeting_start_time}' => 'Meeting Start Time',
                '{meeting_end_time}' => 'Meeting End Time',
                '{meeting_location}' => 'Meeting Location',
                '{meeting_description}' => 'Meeting Description',
                '{company_name}' => 'Company Name'
            ];
        } elseif ($template->name === 'Case Created') {
            $variables = [
                '{contact_name}' => 'Contact Name',
                '{assigned_user_name}' => 'Assigned User Name',
                '{case_subject}' => 'Case Subject',
                '{case_priority}' => 'Case Priority',
                '{case_status}' => 'Case Status',
                '{case_created_date}' => 'Case Created Date',
                '{case_description}' => 'Case Description',
                '{company_name}' => 'Company Name'
            ];
        } elseif ($template->name === 'Opportunity Created') {
            $variables = [
                '{opportunity_name}' => 'Opportunity Name',
                '{assigned_user_name}' => 'Assigned User Name',
                '{account_name}' => 'Account Name',
                '{contact_name}' => 'Contact Name',
                '{opportunity_stage}' => 'Opportunity Stage',
                '{opportunity_amount}' => 'Opportunity Amount',
                '{opportunity_close_date}' => 'Opportunity Close Date',
                '{opportunity_description}' => 'Opportunity Description',
                '{company_name}' => 'Company Name'
            ];
        } elseif ($template->name === 'Opportunity Status Changed') {
            $variables = [
                '{opportunity_name}' => 'Opportunity Name',
                '{assigned_user_name}' => 'Assigned User Name',
                '{old_opportunity_stage}' => 'Old Opportunity Stage',
                '{new_opportunity_stage}' => 'New Opportunity Stage',
                '{account_name}' => 'Account Name',
                '{contact_name}' => 'Contact Name',
                '{opportunity_amount}' => 'Opportunity Amount',
                '{opportunity_close_date}' => 'Opportunity Close Date',
                '{opportunity_description}' => 'Opportunity Description',
                '{company_name}' => 'Company Name'
            ];
        }

        return Inertia::render('email-templates/show', [
            'template' => $template,
            'languages' => $languages,
            'variables' => $variables
        ]);
    }

    public function updateSettings(EmailTemplate $emailTemplate, Request $request)
    {
        try {
            $request->validate([
                'from' => 'required|string|max:255'
            ]);

            $emailTemplate->update([
                'from' => $request->from
            ]);

            return redirect()->back()->with('success', __('Template settings updated successfully.'));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', __('Failed to update template settings: :error', ['error' => $e->getMessage()]));
        }
    }

    public function updateContent(EmailTemplate $emailTemplate, Request $request)
    {
        try {
            $request->validate([
                'lang' => 'required|string|max:10',
                'subject' => 'required|string|max:255',
                'content' => 'required|string'
            ]);

            $emailTemplate->emailTemplateLangs()
                ->where('lang', $request->lang)
                ->update([
                    'subject' => $request->subject,
                    'content' => $request->content
                ]);

            return redirect()->back()->with('success', __('Email content updated successfully.'));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', __('Failed to update email content: :error', ['error' => $e->getMessage()]));
        }
    }
}
