<?php

use App\Services\AI\Reports\ReportTemplateFormatter;

it('renders fixed template section headings in order', function () {
    $formatter = app(ReportTemplateFormatter::class);

    $formatted = $formatter->format([
        'executive_insights' => ['Insight 1', 'Insight 2', 'Insight 3'],
    ], ['crm' => []], 'overall');

    $html = view('reports.ai_summary_pdf', [
        'job' => (object) ['id' => 99, 'scope' => 'overall', 'completed_at' => now()],
        'result' => [],
        'context' => [],
        'formatted' => $formatted,
    ])->render();

    expect($html)->toContain('Account Status');
    expect($html)->toContain('Executive Insights');
    expect($html)->toContain('Deals &amp; Pipeline Snapshot');
    expect($html)->toContain('Recommended Actions (Next 30-60 Days)');
});

it('prints required labels and role-action-priority lines', function () {
    $formatter = app(\App\Services\AI\Reports\ReportTemplateFormatter::class);
    $formatted = $formatter->format([], ['crm' => []], 'overall');

    $html = view('reports.ai_summary_pdf', [
        'job' => (object) ['id' => 101, 'scope' => 'overall', 'completed_at' => now()],
        'result' => [],
        'context' => [],
        'formatted' => $formatted,
    ])->render();

    expect($html)->toContain('Deals &amp; Pipeline Snapshot');
    expect($html)->toContain('Engagement &amp; Health Signals');
    expect($html)->toContain('Sales -&gt;');
    expect($html)->toContain('High');
});

it('renders key relationships section with fallback message when relationship rows are unavailable', function () {
    $formatter = app(\App\Services\AI\Reports\ReportTemplateFormatter::class);
    $formatted = $formatter->format([], ['crm' => []], 'overall');

    $html = view('reports.ai_summary_pdf', [
        'job' => (object) ['id' => 102, 'scope' => 'overall', 'completed_at' => now()],
        'result' => [],
        'context' => [],
        'formatted' => $formatted,
    ])->render();

    expect($html)->toContain('Key Relationships');
    expect($html)->toContain('No relationship data available for this report context.');
});

it('always renders a Gaps line in key relationships section', function () {
    $formatter = app(ReportTemplateFormatter::class);
    $formatted = $formatter->format([], ['crm' => []], 'overall');

    $formatted['relationship_gaps'] = 'Not available';

    $html = view('reports.ai_summary_pdf', [
        'job' => (object) ['id' => 201, 'scope' => 'overall', 'completed_at' => now()],
        'result' => [],
        'context' => [],
        'formatted' => $formatted,
    ])->render();

    expect($html)->toContain('Gaps:');
    expect($html)->toContain('Not available');
});

it('renders not available placeholders instead of omitting rows', function () {
    $formatted = [
        'account_status' => [
            'status' => 'Not available',
            'health' => 'Not available',
            'health_reason' => 'Not available',
            'arr' => 'Not available',
            'mrr' => 'Not available',
            'renewal' => 'Not available',
        ],
        'deals' => [
            'active_deals' => 'Not available',
            'top_deal' => 'Not available',
            'expansion_potential' => 'Not available',
            'notable_won' => 'Not available',
            'notable_lost' => 'Not available',
            'notable_stalled' => 'Not available',
        ],
        'engagement_health_signals' => [
            'usage' => 'Not available',
            'support' => 'Not available',
            'sentiment' => 'Not available',
            'engagement_pattern' => 'Not available',
        ],
        'executive_insights' => [],
        'key_relationships' => [],
        'relationship_gaps' => 'Not available',
        'key_risks' => [],
        'growth_opportunities' => [],
        'recommended_actions' => [],
        'additional_context' => [],
    ];

    $html = view('reports.ai_summary_pdf', [
        'job' => (object) ['id' => 202, 'scope' => 'overall', 'completed_at' => now()],
        'result' => [],
        'context' => [],
        'formatted' => $formatted,
    ])->render();

    expect($html)->toContain('Status:');
    expect($html)->toContain('ARR:');
    expect($html)->toContain('Usage:');
    expect(substr_count($html, 'Not available'))->toBeGreaterThanOrEqual(6);
});

