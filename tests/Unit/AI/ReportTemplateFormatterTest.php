<?php

namespace Tests\Unit\AI;

use App\Services\AI\Reports\ReportTemplateFormatter;
use Tests\TestCase;

class ReportTemplateFormatterTest extends TestCase
{
    public function test_it_emits_required_sections_in_fixed_order(): void
    {
        $formatter = new ReportTemplateFormatter();
        $formatted = $formatter->format([], ['crm' => []], 'overall');

        $this->assertSame([
            'Account Status',
            'Executive Insights',
            'Key Relationships',
            'Deals & Pipeline Snapshot',
            'Engagement & Health Signals',
            'Key Risks',
            'Growth Opportunities',
            'Recommended Actions (Next 30–60 Days)',
        ], array_column($formatted['sections'], 'title'));
    }

    public function test_it_backfills_missing_values_with_not_available(): void
    {
        $formatter = new ReportTemplateFormatter();
        $formatted = $formatter->format([], ['crm' => []], 'leads-only');

        $this->assertSame('Not available', $formatted['account_status']['arr']);
        $this->assertSame('Not available', $formatted['deals']['top_deal']);
    }

    public function test_it_normalizes_recommended_actions_into_role_action_priority_lines(): void
    {
        $formatter = new ReportTemplateFormatter();

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
}
