<?php

namespace App\Http\Controllers;

use App\Models\LeadCaptureForm;
use App\Services\LeadCaptureService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class LeadCaptureSubmitController extends Controller
{
    public function __construct(private LeadCaptureService $captureService)
    {
    }

    /**
     * Render the public capture form page (no auth).
     */
    public function show(string $form)
    {
        $model = LeadCaptureForm::where('slug', $form)->where('is_active', true)->firstOrFail();

        return Inertia::render('lead-capture/public-form', [
            'form' => $this->publicFormPayload($model),
            'recaptcha' => $this->recaptchaPublicConfig(),
            'submitted' => session('lead_capture_submitted', false),
        ]);
    }

    public function store(Request $request, string $form)
    {
        $model = LeadCaptureForm::where('slug', $form)->where('is_active', true)->firstOrFail();

        $this->verifyRecaptcha($request);

        $data = $this->validateSubmission($request, $model);

        $this->captureService->ingest($model, $data, [
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()->route('lead-capture.public.thank-you', ['form' => $model->slug]);
    }

    public function thankYou(string $form)
    {
        $model = LeadCaptureForm::where('slug', $form)->firstOrFail();

        return Inertia::render('lead-capture/thank-you', [
            'form' => $this->publicFormPayload($model),
        ]);
    }

    // ----- helpers -----------------------------------------------------------

    private function validateSubmission(Request $request, LeadCaptureForm $model): array
    {
        $config = is_array($model->fields_config) && $model->fields_config
            ? $model->fields_config
            : LeadCaptureForm::defaultFieldsConfig();

        $rules = [];
        foreach ($config as $row) {
            $field = $row['field'] ?? null;
            if (!in_array($field, LeadCaptureForm::ALLOWED_FIELDS, true)) {
                continue;
            }
            if ($field !== 'name' && empty($row['visible'])) {
                continue; // hidden fields aren't accepted
            }

            $required = $field === 'name' ? true : !empty($row['required']);
            $base = $required ? 'required' : 'nullable';

            $rules[$field] = match ($field) {
                'email' => $base . '|email:filter|max:255',
                'notes', 'address' => $base . '|string|max:5000',
                default => $base . '|string|max:255',
            };
        }

        return $request->validate($rules);
    }

    private function verifyRecaptcha(Request $request): void
    {
        $enabled = filter_var(getSetting('recaptchaEnabled'), FILTER_VALIDATE_BOOLEAN);
        if (!$enabled) {
            return;
        }

        $secret = getSetting('recaptchaSecretKey');
        if (!$secret) {
            return; // enabled but not configured — don't block submissions
        }

        $token = $request->input('recaptcha_token') ?: $request->input('g-recaptcha-response');

        $response = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
            'secret' => $secret,
            'response' => $token,
            'remoteip' => $request->ip(),
        ]);

        if (!($response->json('success') ?? false)) {
            throw ValidationException::withMessages([
                'recaptcha_token' => __('reCAPTCHA verification failed. Please try again.'),
            ]);
        }
    }

    private function recaptchaPublicConfig(): array
    {
        $enabled = (bool) getSetting('recaptchaEnabled') && (bool) getSetting('recaptchaSiteKey');

        return [
            'enabled' => $enabled,
            'site_key' => $enabled ? getSetting('recaptchaSiteKey') : null,
            'version' => getSetting('recaptchaVersion', 'v2'),
        ];
    }

    private function publicFormPayload(LeadCaptureForm $model): array
    {
        $config = is_array($model->fields_config) && $model->fields_config
            ? $model->fields_config
            : LeadCaptureForm::defaultFieldsConfig();

        $logoUrl = null;
        if ($model->logo_media_id) {
            $media = \Spatie\MediaLibrary\MediaCollections\Models\Media::find($model->logo_media_id);
            $logoUrl = $media?->getUrl();
        }

        // Branding-enabled plans replace the "Powered by RIBO" footer with the
        // company's own name; everyone else keeps the RIBO attribution.
        $company = $model->creator;
        $plan = $company?->getCurrentPlan();
        $whiteLabel = $plan && $plan->enable_branding === 'on';
        $poweredBy = $whiteLabel
            ? ($model->company_name ?: $company?->name)
            : 'RIBO';

        return [
            'slug' => $model->slug,
            'name' => $model->name,
            'description' => $model->description,
            'company_name' => $model->company_name,
            'logo_url' => $logoUrl,
            'submit_button_text' => $model->submit_button_text ?: __('Submit'),
            'thank_you_message' => $model->thank_you_message ?: __('Thank you! We have received your submission and will be in touch soon.'),
            'fields' => array_values(array_filter($config, fn ($r) => ($r['field'] ?? null) === 'name' || !empty($r['visible']))),
            'theme' => $model->theme,
            'powered_by' => $poweredBy,
            'action_url' => route('lead-capture.public.submit', ['form' => $model->slug]),
        ];
    }
}
