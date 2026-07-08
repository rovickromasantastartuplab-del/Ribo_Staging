<?php

namespace App\Http\Controllers;

use App\Models\LeadCaptureForm;
use App\Models\LeadCaptureSubmission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class LeadCaptureReportController extends Controller
{
    public function index(Request $request)
    {
        $companyId = createdBy();

        $submissions = LeadCaptureSubmission::where('created_by', $companyId);

        $totalForms = LeadCaptureForm::where('created_by', $companyId)->count();
        $activeForms = LeadCaptureForm::where('created_by', $companyId)->where('is_active', true)->count();
        $totalSubmissions = (clone $submissions)->count();
        $newLeads = (clone $submissions)->where('outcome', 'new')->count();
        $duplicateMatches = (clone $submissions)->where('outcome', 'duplicate')->count();

        // Leads per form
        $leadsPerForm = LeadCaptureForm::where('created_by', $companyId)
            ->withCount([
                'submissions as total',
                'submissions as new_leads' => fn ($q) => $q->where('outcome', 'new'),
            ])
            ->orderByDesc('total')
            ->get(['id', 'name'])
            ->map(fn ($f) => [
                'name' => $f->name,
                'total' => $f->total,
                'new_leads' => $f->new_leads,
            ]);

        // Leads per campaign (via the form's campaign)
        $leadsPerCampaign = LeadCaptureSubmission::query()
            ->where('lead_capture_submissions.created_by', $companyId)
            ->join('lead_capture_forms', 'lead_capture_forms.id', '=', 'lead_capture_submissions.lead_capture_form_id')
            ->leftJoin('campaigns', 'campaigns.id', '=', 'lead_capture_forms.campaign_id')
            ->select(DB::raw("COALESCE(campaigns.name, 'No Campaign') as name"), DB::raw('COUNT(*) as total'))
            ->groupBy('name')
            ->orderByDesc('total')
            ->get();

        // Submission trend — last 30 days
        $trend = LeadCaptureSubmission::where('created_by', $companyId)
            ->where('submitted_at', '>=', now()->subDays(30))
            ->select(DB::raw('DATE(submitted_at) as date'), DB::raw('COUNT(*) as total'))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return Inertia::render('lead-capture/reports/index', [
            'stats' => [
                'total_forms' => $totalForms,
                'active_forms' => $activeForms,
                'total_submissions' => $totalSubmissions,
                'new_leads' => $newLeads,
                'duplicate_matches' => $duplicateMatches,
            ],
            'leadsPerForm' => $leadsPerForm,
            'leadsPerCampaign' => $leadsPerCampaign,
            'trend' => $trend,
        ]);
    }
}
