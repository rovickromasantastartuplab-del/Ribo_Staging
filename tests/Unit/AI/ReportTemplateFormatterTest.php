<?php

namespace Tests\Unit\AI;

use App\Services\AI\Reports\ReportTemplateFormatter;
use Tests\TestCase;

class ReportTemplateFormatterTest extends TestCase
{
    public function test_it_emits_required_sections_in_fixed_order(): void
    {
        $formatter = app(ReportTemplateFormatter::class);
        $formatted = $formatter->format([], ['crm' => []], 'overall');

        $this->assertSame([
            'Client Account Snapshot',
            'Account Status',
            'Executive Insights',
            'Key Relationships',
            'Deals & Pipeline Snapshot',
            'Engagement & Health Signals',
            'Key Risks',
            'Growth Opportunities',
            'Recommended Actions (Next 30-60 Days)',
        ], array_column($formatted['sections'], 'title'));
    }

    public function test_it_backfills_missing_values_with_not_available(): void
    {
        $formatter = app(ReportTemplateFormatter::class);
        $formatted = $formatter->format([], ['crm' => []], 'leads-only');

        $this->assertSame('Not available', $formatted['account_status']['arr']);
        $this->assertSame('Not available', $formatted['deals']['top_deal']);
    }

    public function test_it_normalizes_recommended_actions_into_role_action_priority_lines(): void
    {
        $formatter = app(ReportTemplateFormatter::class);

        $formatted = $formatter->format([
            'role_based_actions' => [
                'sales' => ['Build ROI case'],
                'csm' => ['Secure VP review'],
                'support' => ['Resolve workflow tickets'],
            ],
        ], ['crm' => []], 'overall');

        $actions = $formatted['recommended_actions'];

        $this->assertStringContainsString('Sales -> Build ROI case -> High', $actions[0]);
        $this->assertStringContainsString('CSM -> Secure VP review -> High', $actions[1]);
    }

    public function test_it_derives_engagement_signals_from_activity_stream_meta(): void
    {
        $formatter = app(ReportTemplateFormatter::class);

        $formatted = $formatter->format(
            [],
            [
                'crm' => [],
                'activity_streams' => [
                    'lead' => [
                        ['title' => 'Support ticket escalated'],
                    ],
                    'opportunity' => [],
                    'meta' => [
                        'lead_included_count' => 14,
                        'opportunity_included_count' => 8,
                        'lead_scanned_count' => 20,
                        'opportunity_scanned_count' => 10,
                    ],
                ],
            ],
            'overall'
        );

        $signals = $formatted['engagement_health_signals'];

        $this->assertNotSame('Not available', $signals['usage']);
        $this->assertNotSame('Not available', $signals['support']);
        $this->assertNotSame('Not available', $signals['engagement_pattern']);
    }

    public function test_it_populates_relationship_strength_defaults_from_crm_relationships(): void
    {
        $formatter = app(ReportTemplateFormatter::class);

        $formatted = $formatter->format(
            [],
            [
                'crm' => [
                    'relationships' => [
                        [
                            'name' => 'Lian',
                            'role' => 'Sales Associate',
                        ],
                    ],
                ],
            ],
            'overall'
        );

        $row = $formatted['key_relationships'][0];

        $this->assertSame('Stakeholder', $row['type']);
        $this->assertSame('Medium', $row['strength']);
        $this->assertNotSame('Not available', $formatted['relationship_gaps']);
    }

    public function test_it_derives_renewal_from_opportunity_close_dates_when_financial_renewal_missing(): void
    {
        $formatter = app(ReportTemplateFormatter::class);

        $formatted = $formatter->format(
            [],
            [
                'crm' => [
                    'financials' => [
                        'arr' => 120000,
                        'mrr' => 10000,
                    ],
                    'opportunities' => [
                        [
                            'name' => 'Expansion A',
                            'close_date' => '2026-09-30',
                        ],
                        [
                            'name' => 'Expansion B',
                            'close_date' => '2026-08-15',
                        ],
                    ],
                ],
            ],
            'overall'
        );

        $this->assertSame('2026-08-15', $formatted['account_status']['renewal']);
    }

    public function test_it_sanitizes_relationship_name_to_plain_person_name(): void
    {
        $formatter = app(ReportTemplateFormatter::class);

        $formatted = $formatter->format(
            [
                'key_relationships' => [
                    'Rovick Romasanta - Primary contact for this account.',
                ],
            ],
            [
                'crm' => [],
            ],
            'overall'
        );

        $row = $formatted['key_relationships'][0];

        $this->assertSame('Rovick Romasanta', $row['name']);
    }

    public function test_it_prefers_crm_relationship_names_over_ai_narrative_strings(): void
    {
        $formatter = app(ReportTemplateFormatter::class);

        $formatted = $formatter->format(
            [
                'key_relationships' => [
                    'Rovick Romasanta - Primary contact for this account.',
                ],
            ],
            [
                'crm' => [
                    'relationships' => [
                        [
                            'name' => 'Rovick Romasanta',
                            'role' => 'Partner',
                        ],
                    ],
                ],
            ],
            'overall'
        );

        $row = $formatted['key_relationships'][0];

        $this->assertSame('Rovick Romasanta', $row['name']);
        $this->assertSame('Partner', $row['role']);
    }

    public function test_it_recovers_flattened_key_relationship_pairs_into_single_row(): void
    {
        $formatter = app(ReportTemplateFormatter::class);

        $formatted = $formatter->format(
            [
                'key_relationships' => ['name', 'Rovick Romasanta', 'role', 'Decision-Maker', 'strength', 'Strong'],
            ],
            [
                'crm' => [],
            ],
            'overall'
        );

        $this->assertCount(1, $formatted['key_relationships']);
        $this->assertSame('Rovick Romasanta', $formatted['key_relationships'][0]['name']);
        $this->assertSame('Decision-Maker', $formatted['key_relationships'][0]['role']);
        $this->assertSame('Strong', $formatted['key_relationships'][0]['strength']);
    }
}
