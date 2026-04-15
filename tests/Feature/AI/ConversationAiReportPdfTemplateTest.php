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

    expect($html)->toContain('Page 1: Relationship Analytics');
    expect($html)->toContain('Page 2: Executive Summary');
    expect($html)->toContain('Interaction volume over time');
    expect($html)->toContain('Average response time trend');
    expect($html)->toContain('Client Account Snapshot');
    expect($html)->toContain('Account Status');
    expect($html)->toContain('Executive Insights');
    expect($html)->toContain('Deals &amp; Pipeline Snapshot');
    expect($html)->toContain('Recommended Actions (Next 30-60 Days)');

    expect(strpos($html, 'Page 1: Relationship Analytics'))->toBeLessThan(strpos($html, 'Page 2: Executive Summary'));
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

it('prefers normalized status when status_value is invalid enum text', function () {
    $formatter = app(ReportTemplateFormatter::class);

    $formatted = $formatter->format([
        'status_value' => 'Closed Lost',
        'normalized_status' => 'At Risk',
    ], ['crm' => []], 'leads-only');

    $html = view('reports.ai_summary_pdf', [
        'job' => (object) ['id' => 203, 'scope' => 'leads-only', 'completed_at' => now()],
        'result' => [],
        'context' => [],
        'formatted' => $formatted,
    ])->render();

    expect($html)->toContain('Status:');
    expect($html)->toContain('At Risk');
    expect($html)->not->toContain('Status:</span> Stable');
});

it('prints explicit date labels on graph axis', function () {
    $formatter = app(ReportTemplateFormatter::class);

    $formatted = $formatter->format([], ['crm' => []], 'overall');

    $html = view('reports.ai_summary_pdf', [
        'job' => (object) ['id' => 204, 'scope' => 'overall', 'completed_at' => now()],
        'result' => [],
        'context' => [],
        'formatted' => $formatted,
    ])->render();

    expect($html)->toMatch('/\b[A-Z][a-z]{2}\s\d{2}\b/');
});

