<?php

namespace App\Imports;

use App\Models\Lead;
use App\Models\User;
use App\Events\LeadAssigned;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterImport;

class LeadImport implements ToModel, WithHeadingRow, WithEvents
{
    private $addedCount = 0;
    private $skippedCount = 0;
    private $skippedRows = [];
    private $currentRow = 0;
    private $leads = [];

    public function model(array $row)
    {
        $this->currentRow++;

        // Skip if both name and email are empty
        if (empty($row['name']) && empty($row['email'])) {
            $this->skippedCount++;
            $this->skippedRows[] = [
                'row' => $this->currentRow + 1, // +1 for header row
                'name' => $row['name'] ?? '',
                'email' => $row['email'] ?? '',
                'reason' => __('Empty name and email'),
            ];
            return null;
        }

        // Check for duplicate email
        if (!empty($row['email'])) {
            $leadByEmail = Lead::where('email', $row['email'])->where('created_by', createdBy())->first();
            if ($leadByEmail) {
                $this->skippedCount++;
                $this->skippedRows[] = [
                    'row' => $this->currentRow + 1,
                    'name' => $row['name'] ?? '',
                    'email' => $row['email'] ?? '',
                    'reason' => __('Duplicate email'),
                ];
                return null;
            }
        }

        $leadData = [
            'name' => $row['name'] ?? '',
            'email' => $row['email'] ?? '',
            'phone' => $row['phone'] ?? '',
            'company' => $row['company'] ?? '',
            'account_name' => $row['account_name'] ?? '',
            'website' => $row['website'] ?? '',
            'position' => $row['position'] ?? '',
            'address' => $row['address'] ?? '',
            'notes' => $row['notes'] ?? '',
            'value' => is_numeric($row['value'] ?? null) ? (float) ($row['value']) : null,
            'status' => in_array($row['status'] ?? 'active', ['active', 'inactive']) ? ($row['status'] ?? 'active') : 'active',
            'is_converted' => false,
            'created_by' => createdBy(),
        ];

        // Lead Status
        $leadStatusValue = substr(trim($row['lead_status'] ?? ''), 0, 255);
        if (!empty($leadStatusValue)) {
            $leadStatus = \App\Models\LeadStatus::firstOrCreate(
                ['name' => $leadStatusValue, 'created_by' => createdBy()],
                ['color' => '#3b82f6', 'status' => 'active']
            );
        } else {
            $leadStatus = \App\Models\LeadStatus::where('created_by', createdBy())->first()
                ?? \App\Models\LeadStatus::create(['name' => 'New', 'color' => '#3b82f6', 'status' => 'active', 'created_by' => createdBy()]);
        }
        $leadData['lead_status_id'] = $leadStatus->id;

        // Lead Source
        $leadSourceValue = substr(trim($row['lead_source'] ?? ''), 0, 255);
        if (!empty($leadSourceValue)) {
            $leadSource = \App\Models\LeadSource::firstOrCreate(
                ['name' => $leadSourceValue, 'created_by' => createdBy()],
                ['status' => 'active']
            );
        } else {
            $leadSource = \App\Models\LeadSource::where('created_by', createdBy())->first()
                ?? \App\Models\LeadSource::create(['name' => 'Imported', 'status' => 'active', 'created_by' => createdBy()]);
        }
        $leadData['lead_source_id'] = $leadSource->id;

        // Account Industry
        $accountIndustryValue = substr(trim($row['account_industry'] ?? ''), 0, 255);
        if (!empty($accountIndustryValue)) {
            $accountIndustry = \App\Models\AccountIndustry::firstOrCreate(
                ['name' => $accountIndustryValue, 'created_by' => createdBy()],
                ['status' => 'active']
            );
            $leadData['account_industry_id'] = $accountIndustry->id;
        } else {
            $leadData['account_industry_id'] = \App\Models\AccountIndustry::where('created_by', createdBy())->value('id');
        }

        // Campaign
        $campaignValue = substr(trim($row['campaign'] ?? ''), 0, 255);
        if (!empty($campaignValue)) {
            $campaignType = \App\Models\CampaignType::where('created_by', createdBy())->first()
                ?? \App\Models\CampaignType::create(['name' => 'Imported', 'created_by' => createdBy()]);

            $campaign = \App\Models\Campaign::firstOrCreate(
                ['name' => $campaignValue, 'created_by' => createdBy()],
                [
                    'status' => 'active',
                    'start_date' => now(),
                    'end_date' => now()->addMonth(),
                    'budget' => 0,
                    'campaign_type_id' => $campaignType->id
                ]
            );
            $leadData['campaign_id'] = $campaign->id;
        } else {
            $leadData['campaign_id'] = null;
        }

        if (!auth()->user()->hasRole('company')) {
            $leadData['assigned_to'] = auth()->id();
        }

        $this->addedCount++;
        return new Lead($leadData);
    }

    public function registerEvents(): array
    {
        return [
            AfterImport::class => function (AfterImport $event) {
                // Fire LeadAssigned event for all imported leads
                Lead::where('created_by', createdBy())
                    ->whereDate('created_at', today())
                    ->orderBy('id', 'desc')
                    ->take($this->addedCount)
                    ->get()
                    ->each(function ($lead) {
                    event(new LeadAssigned($lead));
                });
            },
        ];
    }

    public function getAddedCount()
    {
        return $this->addedCount;
    }

    public function getSkippedCount()
    {
        return $this->skippedCount;
    }

    public function getSkippedRows()
    {
        return $this->skippedRows;
    }
}
