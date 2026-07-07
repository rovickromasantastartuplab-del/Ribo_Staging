<?php

namespace App\Services;

use App\Mail\LeadCaptureAutoReply;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\LeadCaptureForm;
use App\Models\LeadCaptureSubmission;
use App\Models\LeadStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class LeadCaptureService
{
    /**
     * Ingest a public form submission: create a new Lead or, if one already
     * exists (matched by email OR phone within the company), preserve it and
     * append a timeline activity. The existing lead's fields are never modified.
     *
     * @param  array  $data   Validated lead field values keyed by field name.
     * @param  array  $meta   ['ip_address' => ..., 'user_agent' => ...]
     */
    public function ingest(LeadCaptureForm $form, array $data, array $meta = []): LeadCaptureSubmission
    {
        $companyId = $form->created_by;
        $email = isset($data['email']) ? strtolower(trim($data['email'])) : null;
        $phone = isset($data['phone']) ? trim($data['phone']) : null;

        $existing = $this->findDuplicate($companyId, $email, $phone);

        if ($existing) {
            $lead = $existing;
            $outcome = 'duplicate';
            $this->logSubmissionActivity($lead, $form);
        } else {
            $lead = $this->createLead($form, $data, $email, $phone);
            $outcome = 'new';
            $this->logSubmissionActivity($lead, $form);
            // Reuse the existing assignment-email pipeline used by LeadController.
            if (!IsDemo()) {
                event(new \App\Events\LeadAssigned($lead));
            }
        }

        $submission = LeadCaptureSubmission::create([
            'lead_capture_form_id' => $form->id,
            'created_by' => $companyId,
            'lead_id' => $lead->id,
            'outcome' => $outcome,
            'payload' => $data,
            'ip_address' => $meta['ip_address'] ?? null,
            'user_agent' => $meta['user_agent'] ?? null,
            'submitted_at' => now(),
        ]);

        $this->sendAutoReply($form, $lead, $email);

        return $submission;
    }

    /**
     * Find an existing lead for this company matching the email OR phone.
     */
    protected function findDuplicate(int $companyId, ?string $email, ?string $phone): ?Lead
    {
        if (!$email && !$phone) {
            return null;
        }

        return Lead::where('created_by', $companyId)
            ->where(function ($q) use ($email, $phone) {
                if ($email) {
                    $q->orWhereRaw('LOWER(email) = ?', [$email]);
                }
                if ($phone) {
                    $q->orWhere('phone', $phone);
                }
            })
            ->first();
    }

    protected function createLead(LeadCaptureForm $form, array $data, ?string $email, ?string $phone): Lead
    {
        $statusId = $form->default_lead_status_id
            ?? LeadStatus::where('created_by', $form->created_by)->orderBy('order')->orderBy('id')->value('id');

        return Lead::create([
            'name' => $data['name'],
            'email' => $email,
            'phone' => $phone,
            'company' => $data['company'] ?? null,
            'position' => $data['position'] ?? null,
            'website' => $data['website'] ?? null,
            'address' => $data['address'] ?? null,
            'notes' => $data['notes'] ?? null,
            'status' => 'active',
            'created_by' => $form->created_by,
            'assigned_to' => $form->default_assigned_to,
            'lead_source_id' => $form->lead_source_id,
            'lead_status_id' => $statusId,
            'campaign_id' => $form->campaign_id,
            'last_activity_at' => now(),
        ]);
    }

    /**
     * Append the "Submitted {Form}" timeline entry. For duplicates we bump
     * last_activity_at quietly so the LeadObserver does not log a field change.
     */
    protected function logSubmissionActivity(Lead $lead, LeadCaptureForm $form): void
    {
        LeadActivity::create([
            'lead_id' => $lead->id,
            'user_id' => $form->created_by,
            'activity_type' => 'lead_capture_submission',
            'title' => 'Submitted ' . $form->name,
            'description' => $form->type === 'staff_assisted'
                ? 'Captured via staff-assisted form'
                : 'Captured via public form',
            'new_values' => ['lead_capture_form_id' => $form->id],
            'created_by' => $form->created_by,
        ]);

        $lead->forceFill(['last_activity_at' => now()])->saveQuietly();
    }

    protected function sendAutoReply(LeadCaptureForm $form, Lead $lead, ?string $email): void
    {
        if (!$form->auto_reply_enabled || !$email) {
            return;
        }

        try {
            \App\Services\MailConfigService::setDynamicConfig();
            Mail::to($email)->send(new LeadCaptureAutoReply($form, $lead));
        } catch (\Throwable $e) {
            Log::warning('Lead capture auto-reply failed: ' . $e->getMessage());
        }
    }
}
