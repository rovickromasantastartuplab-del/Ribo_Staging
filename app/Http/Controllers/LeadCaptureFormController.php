<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\LeadCaptureForm;
use App\Models\LeadSource;
use App\Models\LeadStatus;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Milon\Barcode\Facades\DNS2DFacade as DNS2D;

class LeadCaptureFormController extends Controller
{
    public function index(Request $request)
    {
        $query = LeadCaptureForm::query()
            ->where('created_by', createdBy())
            ->withCount([
                'submissions',
                'submissions as new_leads_count' => fn ($q) => $q->where('outcome', 'new'),
                'submissions as duplicate_count' => fn ($q) => $q->where('outcome', 'duplicate'),
            ])
            ->with(['leadSource:id,name', 'campaign:id,name', 'defaultAssignee:id,name']);

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                    ->orWhere('description', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->filled('type') && $request->type !== 'all') {
            $query->where('type', $request->type);
        }

        $query->orderBy($request->sort_field ?? 'id', $request->sort_direction ?? 'desc');

        $forms = $query->paginate($request->per_page ?? 10)->withQueryString();

        // Expose the public URL to the frontend for copy/QR.
        $forms->getCollection()->transform(function ($form) {
            $form->public_url = $form->publicUrl();
            return $form;
        });

        return Inertia::render('lead-capture/forms/index', [
            'forms' => $forms,
            'leadSources' => LeadSource::where('created_by', createdBy())->where('status', 'active')->get(['id', 'name']),
            'campaigns' => Campaign::where('created_by', createdBy())->get(['id', 'name']),
            'leadStatuses' => LeadStatus::where('created_by', createdBy())->orderBy('order')->get(['id', 'name', 'color']),
            'users' => $this->assignableUsers(),
            'allowedFields' => LeadCaptureForm::ALLOWED_FIELDS,
            'defaultFieldsConfig' => LeadCaptureForm::defaultFieldsConfig(),
            'planLimits' => $this->planLimits(),
            'canWhiteLabel' => $this->canWhiteLabel(),
            'filters' => $request->all(['search', 'type', 'sort_field', 'sort_direction', 'per_page']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateForm($request);

        $limits = $this->planLimits();
        if ($limits && !$limits['can_create']) {
            return redirect()->back()->with('error', __('Form limit reached. Your plan allows maximum :max capture forms.', ['max' => $limits['max_forms']]));
        }

        $validated['created_by'] = createdBy();
        $validated['slug'] = LeadCaptureForm::generateSlug($validated['name']);
        $validated['lead_source_id'] = $this->resolveLeadSource($request);
        $validated['fields_config'] = $this->sanitizeFieldsConfig($request->input('fields_config'));

        LeadCaptureForm::create($validated);

        return redirect()->back()->with('success', __('Capture form created successfully.'));
    }

    public function update(Request $request, LeadCaptureForm $form)
    {
        $this->authorizeForm($form);

        $validated = $this->validateForm($request);
        $validated['lead_source_id'] = $this->resolveLeadSource($request);
        $validated['fields_config'] = $this->sanitizeFieldsConfig($request->input('fields_config'));

        $form->update($validated);

        return redirect()->back()->with('success', __('Capture form updated successfully.'));
    }

    public function destroy(LeadCaptureForm $form)
    {
        $this->authorizeForm($form);
        $form->delete();

        return redirect()->back()->with('success', __('Capture form deleted successfully.'));
    }

    public function toggleStatus(LeadCaptureForm $form)
    {
        $this->authorizeForm($form);
        $form->update(['is_active' => !$form->is_active]);

        return redirect()->back()->with('success', __('Capture form status updated.'));
    }

    /**
     * Download a PNG QR code that points at the form's public URL.
     */
    public function qr(LeadCaptureForm $form)
    {
        $this->authorizeForm($form);

        $png = base64_decode(DNS2D::getBarcodePNG($form->publicUrl(), 'QRCODE', 8, 8));

        return response($png, 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => 'attachment; filename="' . $form->slug . '-qr.png"',
        ]);
    }

    // ----- helpers -----------------------------------------------------------

    private function validateForm(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|in:customer_facing,staff_assisted',
            'campaign_id' => 'nullable|exists:campaigns,id',
            'default_assigned_to' => 'nullable|exists:users,id',
            'default_lead_status_id' => 'nullable|exists:lead_statuses,id',
            'fields_config' => 'nullable|array',
            'company_name' => 'nullable|string|max:255',
            'logo_media_id' => 'nullable|integer',
            'submit_button_text' => 'nullable|string|max:255',
            'thank_you_message' => 'nullable|string',
            'theme' => 'nullable|array',
            'auto_reply_enabled' => 'boolean',
            'auto_reply_subject' => 'nullable|string|max:255',
            'auto_reply_body' => 'nullable|string',
            'is_active' => 'boolean',
            // Either pick an existing source...
            'lead_source_id' => 'nullable|exists:lead_sources,id',
            // ...or supply a channel name to find-or-create.
            'channel' => 'nullable|string|max:255',
        ]);
    }

    private function authorizeForm(LeadCaptureForm $form): void
    {
        abort_unless($form->created_by === createdBy(), 404);
    }

    /**
     * Resolve the form's lead source: explicit id wins; otherwise find-or-create
     * a source for the given channel name (mirrors LeadImport behavior).
     */
    private function resolveLeadSource(Request $request): ?int
    {
        if ($request->filled('lead_source_id')) {
            return (int) $request->input('lead_source_id');
        }

        $channel = trim((string) $request->input('channel'));
        if ($channel === '') {
            return null;
        }

        return LeadSource::firstOrCreate(
            ['name' => $channel, 'created_by' => createdBy()],
            ['status' => 'active']
        )->id;
    }

    /**
     * Keep only allowed fields; force "name" visible + required.
     */
    private function sanitizeFieldsConfig($config): array
    {
        if (!is_array($config) || empty($config)) {
            return LeadCaptureForm::defaultFieldsConfig();
        }

        $clean = [];
        foreach ($config as $row) {
            $field = $row['field'] ?? null;
            if (!in_array($field, LeadCaptureForm::ALLOWED_FIELDS, true)) {
                continue;
            }
            $clean[$field] = [
                'field' => $field,
                'visible' => $field === 'name' ? true : (bool) ($row['visible'] ?? false),
                'required' => $field === 'name' ? true : (bool) ($row['required'] ?? false),
            ];
        }

        // Ensure name is always present.
        if (!isset($clean['name'])) {
            $clean['name'] = ['field' => 'name', 'visible' => true, 'required' => true];
        }

        return array_values($clean);
    }

    private function assignableUsers()
    {
        return User::where(function ($q) {
            $q->where('id', createdBy())->orWhere('created_by', createdBy());
        })->where('type', '!=', 'superadmin')->get(['id', 'name']);
    }

    private function planLimits(): ?array
    {
        $company = Auth::user()->type === 'company' ? Auth::user() : Auth::user()->creator;
        $plan = $company?->getCurrentPlan();

        if (!$plan || !($plan->max_lead_capture_forms > 0)) {
            return null; // unlimited or unknown
        }

        $count = LeadCaptureForm::where('created_by', createdBy())->count();

        return [
            'current_forms' => $count,
            'max_forms' => $plan->max_lead_capture_forms,
            'can_create' => $count < $plan->max_lead_capture_forms,
        ];
    }

    private function canWhiteLabel(): bool
    {
        $company = Auth::user()->type === 'company' ? Auth::user() : Auth::user()->creator;
        $plan = $company?->getCurrentPlan();

        return $plan && $plan->enable_branding === 'on';
    }
}
