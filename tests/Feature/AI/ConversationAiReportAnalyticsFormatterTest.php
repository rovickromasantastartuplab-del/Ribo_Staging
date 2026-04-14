<?php

use App\Services\AI\Reports\ReportAnalyticsFormatter;

it('selects 90 day window when activity coverage is high', function () {
    $formatter = app(ReportAnalyticsFormatter::class);

    $timeline = collect(range(0, 95))->map(fn ($i) => [
        'date' => now()->subDays(95 - $i)->toDateString(),
        'emails' => 1,
        'replies' => 1,
        'meetings' => 0,
    ])->all();

    $analytics = $formatter->build([
        'timeline' => $timeline,
        'events' => [],
    ]);

    expect($analytics['window_days'])->toBe(90);
    expect($analytics['graphs'])->toHaveCount(5);
    expect($analytics['graphs'][0]['id'])->toBe('interaction_volume');
});

it('keeps missing dates in sequence with zero-filled values', function () {
    $formatter = app(ReportAnalyticsFormatter::class);

    $analytics = $formatter->build([
        'timeline' => [
            ['date' => now()->subDays(2)->toDateString(), 'emails' => 2],
            ['date' => now()->toDateString(), 'emails' => 4],
        ],
        'events' => [],
    ]);

    $labels = $analytics['date_labels'];

    expect(count($labels))->toBeGreaterThanOrEqual(3);
    expect($analytics['graphs'][0]['series'])->toHaveCount(count($labels));
});

