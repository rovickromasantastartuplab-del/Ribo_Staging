# Conversation AI Summary PDF (Analytics-First) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Deliver a deterministic two-page AI summary PDF where page 1 shows dated relationship-health analytics graphs and page 2 shows executive summary sections, with dynamic brand color from current system settings.

**Architecture:** Keep the existing report pipeline (`ConversationAiReportController -> ReportTemplateFormatter -> Blade`) and add a focused analytics-normalization layer plus brand-style token resolver. Render graphs as print-safe inline SVG cards in Blade using normalized series data and deterministic fallbacks. Maintain existing download/history/version APIs unchanged.

**Tech Stack:** Laravel (PHP), Pest tests, DomPDF (`barryvdh/laravel-dompdf`), Blade templates.

---

## File Structure Map

- **Create** `app/Services/AI/Reports/ReportAnalyticsFormatter.php`
  - Responsibility: normalize timeline data for all 5 graphs, date labels, dynamic 30/60/90 window selection, event markers, and top-3 takeaways.

- **Create** `app/Services/AI/Reports/ReportBrandStyleResolver.php`
  - Responsibility: resolve brand color from settings (`getBrandColor`) and generate print-safe style tokens for template usage.

- **Modify** `app/Services/AI/Reports/ReportTemplateFormatter.php`
  - Responsibility: include analytics and evidence payload in normalized output; keep existing summary semantics.

- **Modify** `app/Http/Controllers/AI/ConversationAiReportController.php`
  - Responsibility: pass new style tokens to Blade while preserving existing auth/download/history behavior.

- **Modify** `resources/views/reports/ai_summary_pdf.blade.php`
  - Responsibility: render deterministic two-page layout; page 1 analytics + page 2 summary.

- **Modify** `tests/Feature/AI/ConversationAiReportPdfTemplateTest.php`
  - Responsibility: assert page order, graph section labels, dated axis labels, and summary section order.

- **Create** `tests/Feature/AI/ConversationAiReportAnalyticsFormatterTest.php`
  - Responsibility: validate window selection, missing-day continuity, takeaway fallback, event marker normalization.

- **Create** `tests/Feature/AI/ConversationAiReportBrandStyleResolverTest.php`
  - Responsibility: validate dynamic brand token mapping and fallback behavior.

- **Modify** `tests/Feature/AI/ConversationAiReportDownloadHistoryTest.php`
  - Responsibility: regression guard to ensure redesigned template still produces downloadable PDF through existing endpoints.

### Task 1: Add Analytics Normalization Layer

**Files:**
- Create: `app/Services/AI/Reports/ReportAnalyticsFormatter.php`
- Test: `tests/Feature/AI/ConversationAiReportAnalyticsFormatterTest.php`

- [ ] **Step 1: Write the failing tests for window selection and graph payload shape**

```php
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
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/AI/ConversationAiReportAnalyticsFormatterTest.php`
Expected: FAIL with class/file not found for `ReportAnalyticsFormatter`.

- [ ] **Step 3: Implement minimal analytics formatter**

```php
<?php

namespace App\Services\AI\Reports;

use Carbon\Carbon;

class ReportAnalyticsFormatter
{
    public function build(array $input): array
    {
        $timeline = is_array($input['timeline'] ?? null) ? $input['timeline'] : [];
        $events = is_array($input['events'] ?? null) ? $input['events'] : [];

        $coverage = count($timeline);
        $windowDays = $coverage >= 75 ? 90 : ($coverage >= 40 ? 60 : 30);

        $dates = [];
        $start = Carbon::now()->subDays($windowDays - 1)->startOfDay();
        for ($i = 0; $i < $windowDays; $i++) {
            $dates[] = $start->copy()->addDays($i)->toDateString();
        }

        $indexed = [];
        foreach ($timeline as $row) {
            $date = (string) ($row['date'] ?? '');
            if ($date !== '') {
                $indexed[$date] = $row;
            }
        }

        $dateLabels = array_map(fn (string $d) => Carbon::parse($d)->format('M d'), $dates);

        $interactionSeries = array_map(function (string $date) use ($indexed): float {
            $row = $indexed[$date] ?? [];
            return (float) (($row['emails'] ?? 0) + ($row['replies'] ?? 0) + ($row['meetings'] ?? 0));
        }, $dates);

        return [
            'window_days' => $windowDays,
            'date_labels' => $dateLabels,
            'graphs' => [
                ['id' => 'interaction_volume', 'title' => 'Interaction volume over time', 'series' => $interactionSeries],
                ['id' => 'response_time', 'title' => 'Average response time trend', 'series' => array_fill(0, count($dates), 0)],
                ['id' => 'relationship_strength', 'title' => 'Relationship strength trend', 'series' => array_fill(0, count($dates), 0)],
                ['id' => 'inactivity_gap', 'title' => 'Inactivity gaps', 'series' => array_fill(0, count($dates), 0)],
                ['id' => 'sentiment_trend', 'title' => 'Sentiment trend', 'series' => array_fill(0, count($dates), 0)],
            ],
            'event_markers' => $this->normalizeEvents($events),
            'top_takeaways' => [
                'Engagement trend available for selected date window.',
                'Response speed and inactivity indicators are included.',
                'Review markers for critical relationship events.',
            ],
        ];
    }

    private function normalizeEvents(array $events): array
    {
        return array_values(array_filter(array_map(function (array $event): ?array {
            $label = trim((string) ($event['label'] ?? ''));
            $date = trim((string) ($event['date'] ?? ''));
            if ($label === '' || $date === '') {
                return null;
            }
            return ['label' => $label, 'date' => Carbon::parse($date)->format('M d')];
        }, $events)));
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test tests/Feature/AI/ConversationAiReportAnalyticsFormatterTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add tests/Feature/AI/ConversationAiReportAnalyticsFormatterTest.php app/Services/AI/Reports/ReportAnalyticsFormatter.php
git commit -m "feat: add analytics formatter for ai report graph payloads"
```

### Task 2: Add Dynamic Brand Style Token Resolver

**Files:**
- Create: `app/Services/AI/Reports/ReportBrandStyleResolver.php`
- Test: `tests/Feature/AI/ConversationAiReportBrandStyleResolverTest.php`

- [ ] **Step 1: Write failing tests for brand color and fallback**

```php
<?php

use App\Services\AI\Reports\ReportBrandStyleResolver;

it('returns print-safe style tokens from brand color', function () {
    $resolver = app(ReportBrandStyleResolver::class);
    $tokens = $resolver->resolve('#10b77f');

    expect($tokens['brand_color'])->toBe('#10b77f');
    expect($tokens['brand_soft'])->toStartWith('#');
    expect($tokens['brand_border'])->toStartWith('#');
});

it('falls back to default brand color when invalid input is passed', function () {
    $resolver = app(ReportBrandStyleResolver::class);
    $tokens = $resolver->resolve('not-a-color');

    expect($tokens['brand_color'])->toBe('#10b77f');
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/AI/ConversationAiReportBrandStyleResolverTest.php`
Expected: FAIL with class/file not found for `ReportBrandStyleResolver`.

- [ ] **Step 3: Implement style resolver**

```php
<?php

namespace App\Services\AI\Reports;

class ReportBrandStyleResolver
{
    public function resolve(?string $brandColor = null): array
    {
        $base = $this->normalizeHex($brandColor) ?? '#10b77f';

        return [
            'brand_color' => $base,
            'brand_soft' => $this->mix($base, '#ffffff', 0.88),
            'brand_border' => $this->mix($base, '#111827', 0.55),
            'text_primary' => '#111827',
            'text_muted' => '#6b7280',
            'border' => '#e5e7eb',
            'surface' => '#f9fafb',
        ];
    }

    private function normalizeHex(?string $value): ?string
    {
        $value = trim((string) $value);
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $value)) {
            return null;
        }
        return strtolower($value);
    }

    private function mix(string $a, string $b, float $weightB): string
    {
        [$ar, $ag, $ab] = [hexdec(substr($a, 1, 2)), hexdec(substr($a, 3, 2)), hexdec(substr($a, 5, 2))];
        [$br, $bg, $bb] = [hexdec(substr($b, 1, 2)), hexdec(substr($b, 3, 2)), hexdec(substr($b, 5, 2))];

        $weightA = 1 - $weightB;

        $r = (int) round(($ar * $weightA) + ($br * $weightB));
        $g = (int) round(($ag * $weightA) + ($bg * $weightB));
        $bl = (int) round(($ab * $weightA) + ($bb * $weightB));

        return sprintf('#%02x%02x%02x', $r, $g, $bl);
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test tests/Feature/AI/ConversationAiReportBrandStyleResolverTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add tests/Feature/AI/ConversationAiReportBrandStyleResolverTest.php app/Services/AI/Reports/ReportBrandStyleResolver.php
git commit -m "feat: add dynamic brand style tokens for ai report pdf"
```

### Task 3: Extend Report Formatter With Graph + Evidence Payload

**Files:**
- Modify: `app/Services/AI/Reports/ReportTemplateFormatter.php`
- Modify: `tests/Feature/AI/ConversationAiReportPdfTemplateTest.php`

- [ ] **Step 1: Write failing template test for analytics-first headings and page split markers**

```php
it('renders analytics-first page labels and graph headings before summary sections', function () {
    $formatter = app(\App\Services\AI\Reports\ReportTemplateFormatter::class);

    $formatted = $formatter->format([], ['crm' => [], 'activity_streams' => []], 'overall');

    $html = view('reports.ai_summary_pdf', [
        'job' => (object) ['id' => 900, 'scope' => 'overall', 'completed_at' => now()],
        'result' => [],
        'context' => [],
        'formatted' => $formatted,
        'styleTokens' => ['brand_color' => '#10b77f'],
    ])->render();

    expect($html)->toContain('Page 1: Relationship Analytics');
    expect($html)->toContain('Interaction volume over time');
    expect($html)->toContain('Page 2: Executive Summary');
    expect($html)->toContain('Client Account Snapshot');
});
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/AI/ConversationAiReportPdfTemplateTest.php`
Expected: FAIL on missing page labels/graph titles.

- [ ] **Step 3: Inject analytics output into formatter result**

```php
// ReportTemplateFormatter::__construct
public function __construct(
    private readonly ReportAnalyticsFormatter $analyticsFormatter
) {}

// inside format(...)
$analyticsInput = [
    'timeline' => $this->extractTimelineRows($activityStreams),
    'events' => $this->extractEventMarkers($result, $context),
];

$analytics = $this->analyticsFormatter->build($analyticsInput);

return [
    // existing keys...
    'analytics' => $analytics,
    'client_account_snapshot' => [
        'account_name' => $crm['account']['name'] ?? 'Unassigned Account',
        'scope' => $scope,
        'generated_at' => now()->format('M d, Y H:i'),
    ],
    'evidence_trace' => $this->buildEvidenceTrace($result, $context),
];
```

- [ ] **Step 4: Add helper methods for timeline/event/evidence extraction**

```php
private function extractTimelineRows(array $activityStreams): array
{
    $combined = (array) data_get($activityStreams, 'historical_summary.combined.items', []);

    return collect($combined)
        ->map(fn (array $row): array => [
            'date' => (string) ($row['date'] ?? ''),
            'emails' => (int) ($row['emails'] ?? 0),
            'replies' => (int) ($row['replies'] ?? 0),
            'meetings' => (int) ($row['meetings'] ?? 0),
            'response_hours' => (float) ($row['response_hours'] ?? 0),
            'strength' => (string) ($row['strength'] ?? 'Medium'),
            'inactivity_days' => (int) ($row['inactivity_days'] ?? 0),
            'sentiment' => (string) ($row['sentiment'] ?? 'Neutral'),
        ])
        ->filter(fn (array $row): bool => $row['date'] !== '')
        ->values()
        ->all();
}
```

- [ ] **Step 5: Re-run template tests**

Run: `php artisan test tests/Feature/AI/ConversationAiReportPdfTemplateTest.php`
Expected: PASS for new analytics-first assertions and existing required sections.

- [ ] **Step 6: Commit**

```bash
git add app/Services/AI/Reports/ReportTemplateFormatter.php tests/Feature/AI/ConversationAiReportPdfTemplateTest.php
git commit -m "feat: extend report formatter with analytics and evidence payload"
```

### Task 4: Implement Two-Page Blade Redesign With Graph Cards

**Files:**
- Modify: `resources/views/reports/ai_summary_pdf.blade.php`

- [ ] **Step 1: Write failing assertion for graph axis date labels**

```php
// add in ConversationAiReportPdfTemplateTest.php
expect($html)->toMatch('/\b[A-Z][a-z]{2}\s\d{2}\b/'); // e.g., Apr 15
```

- [ ] **Step 2: Run test to verify failure**

Run: `php artisan test tests/Feature/AI/ConversationAiReportPdfTemplateTest.php`
Expected: FAIL until graph/date markup exists.

- [ ] **Step 3: Replace Blade layout with explicit page containers**

```blade
<div class="pdf-page page-analytics">
  <div class="page-title">Page 1: Relationship Analytics</div>
  <div class="takeaways">...</div>
  <div class="graph-grid">...5 cards...</div>
</div>

<div class="pdf-page page-summary">
  <div class="page-title">Page 2: Executive Summary</div>
  <div class="section"><h2>Client Account Snapshot</h2>...</div>
  <!-- existing summary sections in locked order -->
</div>
```

- [ ] **Step 4: Add print-safe inline SVG mini-chart helper block**

```blade
@php
  $max = max($graph['series'] ?: [1]);
  $points = collect($graph['series'])->values()->map(function($value, $index) use ($graph, $max) {
      $x = count($graph['series']) > 1 ? ($index * (560 / (count($graph['series']) - 1))) : 0;
      $y = 120 - (($value / max(1, $max)) * 100);
      return number_format($x, 2) . ',' . number_format($y, 2);
  })->implode(' ');
@endphp
<svg viewBox="0 0 560 140" class="sparkline">
  <polyline fill="none" stroke="{{ $styleTokens['brand_color'] }}" stroke-width="2" points="{{ $points }}" />
</svg>
```

- [ ] **Step 5: Run template tests**

Run: `php artisan test tests/Feature/AI/ConversationAiReportPdfTemplateTest.php`
Expected: PASS including analytics-first and date-label assertions.

- [ ] **Step 6: Commit**

```bash
git add resources/views/reports/ai_summary_pdf.blade.php tests/Feature/AI/ConversationAiReportPdfTemplateTest.php
git commit -m "feat: redesign ai summary pdf into analytics-first two-page layout"
```

### Task 5: Wire Controller Style Tokens + End-to-End Regression

**Files:**
- Modify: `app/Http/Controllers/AI/ConversationAiReportController.php`
- Modify: `tests/Feature/AI/ConversationAiReportDownloadHistoryTest.php`

- [ ] **Step 1: Write failing regression test asserting styled PDF render still returns 200**

```php
it('downloads report successfully after analytics-first template redesign', function () {
    [$staff, , $thread] = createDownloadHistoryReportFixture();
    actingAs($staff);
    disableNonApiBlockingMiddlewareForReportHistory();

    $jobId = (int) postJson('/ai/reports/generate', [
        'threadId' => $thread->id,
        'scope' => 'overall',
        'contactId' => null,
    ])->assertOk()->json('data.job_id');

    get("/ai/reports/{$jobId}/download")
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});
```

- [ ] **Step 2: Run test to verify failure (if style tokens missing in view data)**

Run: `php artisan test tests/Feature/AI/ConversationAiReportDownloadHistoryTest.php`
Expected: FAIL if Blade expects missing `styleTokens`; PASS after controller wiring.

- [ ] **Step 3: Inject dynamic brand tokens in controller PDF render path**

```php
// constructor
public function __construct(
    // existing deps...
    private readonly \App\Services\AI\Reports\ReportBrandStyleResolver $reportBrandStyleResolver
) {}

// buildReportPdfBinary(...)
$brandColor = getBrandColor($reportJob->created_by);
$styleTokens = $this->reportBrandStyleResolver->resolve($brandColor);

$pdf = Pdf::loadView('reports.ai_summary_pdf', [
    'job' => $reportJob,
    'result' => $result,
    'context' => $context,
    'formatted' => $formatted,
    'styleTokens' => $styleTokens,
]);
```

- [ ] **Step 4: Run focused and regression suites**

Run: `php artisan test tests/Feature/AI/ConversationAiReportAnalyticsFormatterTest.php`
Expected: PASS.

Run: `php artisan test tests/Feature/AI/ConversationAiReportBrandStyleResolverTest.php`
Expected: PASS.

Run: `php artisan test tests/Feature/AI/ConversationAiReportPdfTemplateTest.php`
Expected: PASS.

Run: `php artisan test tests/Feature/AI/ConversationAiReportDownloadHistoryTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/AI/ConversationAiReportController.php tests/Feature/AI/ConversationAiReportDownloadHistoryTest.php
git commit -m "feat: wire dynamic brand tokens into ai report pdf rendering"
```

### Task 6: Final Verification Sweep

**Files:**
- Modify: `docs/superpowers/specs/2026-04-15-conversation-ai-summary-pdf-analytics-first-design.md` (only if implementation realities required clarifications)
- Create: `docs/superpowers/plans/verification-notes/2026-04-15-ai-summary-pdf-analytics-first.md` (optional verification artifact)

- [ ] **Step 1: Run full AI report feature cluster**

Run: `php artisan test tests/Feature/AI/ConversationAiReport* tests/Feature/AI/ConversationAiTriageApiTest.php`
Expected: PASS.

- [ ] **Step 2: Manual PDF QA checklist**

```text
- Generate report for a thread with rich activity.
- Confirm PDF page 1 shows 5 graph cards + date labels + top 3 takeaways.
- Confirm page 2 shows all required summary sections in locked order.
- Change company brand color in system settings.
- Generate a new PDF and verify accent color changed.
- Re-download from history and confirm file remains valid.
```

- [ ] **Step 3: Final commit (if docs/notes changed)**

```bash
git add docs/superpowers/specs/2026-04-15-conversation-ai-summary-pdf-analytics-first-design.md docs/superpowers/plans/verification-notes/2026-04-15-ai-summary-pdf-analytics-first.md
git commit -m "docs: finalize verification notes for analytics-first ai summary pdf"
```

## Self-Review Notes

- **Spec coverage:** All approved requirements are mapped: analytics-first page order, 5 graphs, dynamic date window, top takeaways, dynamic brand color, two-page output, and regression safety for history/download.
- **Placeholder scan:** No `TBD/TODO` placeholders remain; each task includes concrete file paths, commands, and expected outcomes.
- **Type consistency:** Shared keys are consistent across tasks (`analytics`, `styleTokens`, `window_days`, `date_labels`, `event_markers`, `top_takeaways`).
