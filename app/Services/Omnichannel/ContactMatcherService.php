<?php

namespace App\Services\Omnichannel;

use App\Models\Account;
use App\Models\Contact;
use Illuminate\Support\Str;

class ContactMatcherService
{
    /**
     * Finds or creates a Contact based on incoming channel data.
     * Prevents duplicate leads by unifying around email or phone.
     */
    public function matchOrCreate(array $data, int $companyId)
    {
        $channel = $data['channel'] ?? 'unknown';
        $email = $data['email'] ?? null;
        $phone = $data['phone'] ?? null;
        $psid = $data['facebook_psid'] ?? null;
        $whatsappMap = $data['whatsapp_phone_e164'] ?? null;

        $query = Contact::where('created_by', $companyId)->where(function ($q) use ($email, $phone, $psid, $whatsappMap) {
            // High priority match: Email
            if ($email) {
                $q->orWhere('email', $email);
            }

            // Secondary priority match: Phone
            if ($phone) {
                $q->orWhere('phone', $phone);
            }

            // Tertiary priority: Channel specific IDs
            if ($psid) {
                $q->orWhere('facebook_psid', $psid);
            }

            if ($whatsappMap) {
                $q->orWhere('whatsapp_phone_e164', $whatsappMap);
            }
        });

        $contact = $query->first();

        // If no contact exists, we create a new one
        if (!$contact) {
            // Resolve account_id: match by company name or use default inbound account
            $accountId = $this->resolveAccountId($data, $companyId);

            $contact = Contact::create([
                'name' => $data['name'] ?? 'Unknown Lead ' . Str::random(5),
                'email' => $email,
                'phone' => $phone,
                'account_id' => $accountId,
                'facebook_psid' => $psid,
                'whatsapp_phone_e164' => $whatsappMap,
                'last_inbound_channel' => $channel,
                'last_inbound_at' => now(),
                'created_by' => $companyId,
            ]);
        } else {
            // Update channel tracking metrics if contact already exists
            $contact->update([
                'last_inbound_channel' => $channel,
                'last_inbound_at' => now(),
            ]);

            // Append channel IDs if they were previously unknown for this contact
            if ($psid && !$contact->facebook_psid) {
                $contact->update(['facebook_psid' => $psid]);
            }
            if ($whatsappMap && !$contact->whatsapp_phone_e164) {
                $contact->update(['whatsapp_phone_e164' => $whatsappMap]);
            }
        }

        return $contact;
    }

    /**
     * Resolve the account_id for a new inbound contact.
     * 1. If a company name is in the payload, try to match an existing account.
     * 2. Otherwise, find or create a default "Inbound Leads" account for this company.
     */
    private function resolveAccountId(array $data, int $companyId): int
    {
        $companyName = $data['company'] ?? null;

        // Try to match by company name if provided
        if ($companyName) {
            $account = Account::where('created_by', $companyId)
                ->where('name', $companyName)
                ->first();

            if ($account) {
                return $account->id;
            }
        }

        // Find or create a default "Inbound Leads" account
        $defaultAccount = Account::firstOrCreate(
            [
                'created_by' => $companyId,
                'name' => 'Inbound Leads',
            ],
            [
                'email' => null,
                'phone' => null,
                'status' => 'active',
            ]
        );

        return $defaultAccount->id;
    }
}
