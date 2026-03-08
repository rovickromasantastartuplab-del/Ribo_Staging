<?php

namespace App\Services\Omnichannel;

use App\Models\Contact;
use App\Models\Lead;
use App\Models\LeadEvent;
use Illuminate\Support\Str;

class LeadEventTrackerService
{
    protected ContactMatcherService $contactMatcher;

    public function __construct(ContactMatcherService $contactMatcher)
    {
        $this->contactMatcher = $contactMatcher;
    }

    /**
     * Processes an incoming parsed omnichannel payload into the CRM.
     */
    public function recordInboundEvent(array $payload, int $companyId)
    {
        // 1. Identify or Create the Contact
        $contact = $this->contactMatcher->matchOrCreate([
            'channel' => $payload['channel'],
            'email' => $payload['email'] ?? null,
            'phone' => $payload['phone'] ?? null,
            'name' => $payload['name'] ?? null,
            'facebook_psid' => $payload['facebook_psid'] ?? null,
            'whatsapp_phone_e164' => $payload['whatsapp_phone_e164'] ?? null,
        ], $companyId);

        // 2. Identify active Lead or create new Lead
        // We look for an open lead for this contact. If none, create one.
        $lead = Lead::where('created_by', $companyId)
            ->whereHas('activities', function ($q) use ($contact) {
                // Simplified link based on email/phone matching. In a full system, Link Lead to Contact directly.
                // Since Lead doesn't have contact_id natively in this CRM, we match by email/phone
            })->whereIn('email', [$contact->email])
            ->whereNotIn('status', ['lost', 'converted', 'junk'])
            ->first();

        if (!$lead) {
            $lead = Lead::where('created_by', $companyId)
                ->where('phone', $contact->phone)
                ->whereNotIn('status', ['lost', 'converted', 'junk'])
                ->first();
        }

        if (!$lead) {
            $lead = Lead::create([
                'name' => $contact->name ?? 'New Inbound Lead',
                'email' => $contact->email,
                'phone' => $contact->phone,
                'status' => 'new',
                'description' => 'Created via ' . $payload['channel'],
                'created_by' => $companyId,
                'last_activity_at' => now(),
            ]);
        } else {
            $lead->update([
                'last_activity_at' => now(),
            ]);
        }

        // 3. Store the tracking event
        $event = LeadEvent::create([
            'lead_id' => $lead->id,
            'contact_id' => $contact->id,
            'channel' => $payload['channel'],
            'external_event_id' => $payload['external_event_id'] ?? Str::uuid()->toString(),
            'external_actor_key' => $payload['external_actor_key'] ?? 'unknown',
            'received_at' => now(),
            'summary_text' => $payload['summary_text'] ?? 'New inbound message received',
            'snippet_text' => $payload['snippet_text'] ?? '',
            'has_media' => $payload['has_media'] ?? false,
            'media_type' => $payload['media_type'] ?? null,
            'payload_min_json' => $payload['raw_payload'] ?? [],
        ]);

        // 4. Trigger Async AI Intent Classification
        \App\Jobs\ClassifyLeadIntentJob::dispatch($event->id);

        return $event;
    }
}
