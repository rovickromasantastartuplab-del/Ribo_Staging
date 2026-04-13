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

