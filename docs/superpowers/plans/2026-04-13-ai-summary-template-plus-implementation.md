# AI Summary Template-Plus Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Enforce the supervisor PDF structure across all report scopes while preserving optional additive insights.

**Architecture:** Keep AI output as structured JSON, normalize it through a deterministic formatter, and render a strict section-ordered Blade template. Validation guardrails run before PDF rendering so sparse or malformed data still produces a complete report.

**Tech Stack:** Laravel (PHP), Pest/PHPUnit, Blade, barryvdh/laravel-dompdf

---

## File Structure

- Create: `app/Services/AI/Reports/ReportTemplateFormatter.php`
- Create: `tests/Unit/AI/ReportTemplateFormatterTest.php`
- Modify: `app/Http/Controllers/AI/ConversationAiReportController.php`
- Modify: `resources/views/reports/ai_summary_pdf.blade.php`
- Modify: `app/Services/AI/Prompts/ReportPromptFactory.php`
- Modify: `app/Services/AI/Skills/ReportSkill.php`
- Modify: `tests/Feature/AI/ReportValidatorTest.php`
- Create: `tests/Feature/AI/ConversationAiReportPdfTemplateTest.php`

### Task 1: Build Deterministic Template Formatter (TDD)

**Files:**
- Create: `tests/Unit/AI/ReportTemplateFormatterTest.php`
- Create: `app/Services/AI/Reports/ReportTemplateFormatter.php`

- [ ] **Step 1: Write the failing formatter unit tests**

```php
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
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Unit/AI/ReportTemplateFormatterTest.php`
Expected: FAIL with class-not-found for `ReportTemplateFormatter`.

- [ ] **Step 3: Write minimal formatter implementation**

```php
<?php

namespace App\Services\AI\Reports;

class ReportTemplateFormatter
{
    public function format(array $result, array $context, string $scope): array
    {
        return [
            'sections' => [
                ['title' => 'Account Status'],
                ['title' => 'Executive Insights'],
                ['title' => 'Key Relationships'],
                ['title' => 'Deals & Pipeline Snapshot'],
                ['title' => 'Engagement & Health Signals'],
                ['title' => 'Key Risks'],
                ['title' => 'Growth Opportunities'],
                ['title' => 'Recommended Actions (Next 30–60 Days)'],
            ],
            'account_status' => [
                'status' => $this->enum($result['status'] ?? null, ['Strategic', 'Growth', 'At Risk', 'Stable'], 'Stable'),
                'health' => $this->enum($result['health_score'] ?? null, ['High', 'Medium', 'Low'], 'Medium'),
                'arr' => $this->money($context['crm']['financials']['arr'] ?? null),
                'mrr' => $this->money($context['crm']['financials']['mrr'] ?? null),
                'renewal' => $context['crm']['financials']['renewal_date'] ?? 'Not available',
            ],
            'deals' => [
                'top_deal' => $context['crm']['opportunities'][0]['name'] ?? 'Not available',
            ],
            'recommended_actions' => $this->buildRoleActions($result),
        ];
    }

    private function buildRoleActions(array $result): array
    {
        $sales = $result['role_based_actions']['sales'][0] ?? 'No action provided';
        $csm = $result['role_based_actions']['csm'][0] ?? 'No action provided';
        $support = $result['role_based_actions']['support'][0] ?? 'No action provided';

        return [
            "Sales -> {$sales} -> High",
            "CSM -> {$csm} -> High",
            "Support/Product -> {$support} -> Medium",
            'Exec Sponsor -> Not available -> Medium',
        ];
    }

    private function enum(?string $value, array $allowed, string $fallback): string
    {
        return in_array($value, $allowed, true) ? $value : $fallback;
    }

    private function money(mixed $value): string
    {
        if (!is_numeric($value)) {
            return 'Not available';
        }

        return '$' . number_format((float) $value, 2);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Unit/AI/ReportTemplateFormatterTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add tests/Unit/AI/ReportTemplateFormatterTest.php app/Services/AI/Reports/ReportTemplateFormatter.php
git commit -m "test: add deterministic report template formatter coverage"
```

### Task 2: Integrate Formatter Into PDF Download Path (TDD)

**Files:**
- Modify: `app/Http/Controllers/AI/ConversationAiReportController.php`
- Create: `tests/Feature/AI/ConversationAiReportPdfTemplateTest.php`

- [ ] **Step 1: Write failing feature test for strict section rendering order**

```php
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
    expect($html)->toContain('Recommended Actions (Next 30–60 Days)');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/AI/ConversationAiReportPdfTemplateTest.php`
Expected: FAIL because view/controller does not yet consume `formatted` structure.

- [ ] **Step 3: Wire formatter in controller and pass payload to view**

```php
// app/Http/Controllers/AI/ConversationAiReportController.php
use App\Services\AI\Reports\ReportTemplateFormatter;

public function __construct(
    private readonly ConversationAiReportService $reportService,
    private readonly ConversationAiRules $rules,
    private readonly ConversationAiTelemetryService $telemetryService,
    private readonly ReportTemplateFormatter $reportTemplateFormatter,
) {
}

public function download(AiReportJob $job)
{
    // existing authorization + result checks...

    $formatted = $this->reportTemplateFormatter->format(
        $result,
        $context,
        (string) ($reportJob->scope ?? 'overall')
    );

    $pdf = Pdf::loadView('reports.ai_summary_pdf', [
        'job' => $reportJob,
        'result' => $result,
        'context' => $context,
        'formatted' => $formatted,
    ]);

    return $pdf->download("AI-Summary-Report-{$reportJob->id}.pdf");
}
```

- [ ] **Step 4: Run feature test to verify it passes**

Run: `php artisan test tests/Feature/AI/ConversationAiReportPdfTemplateTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/AI/ConversationAiReportController.php tests/Feature/AI/ConversationAiReportPdfTemplateTest.php
git commit -m "feat: inject report template formatter into pdf download path"
```

### Task 3: Rewrite Blade To Strict Skeleton + Optional Additional Context (TDD)

**Files:**
- Modify: `resources/views/reports/ai_summary_pdf.blade.php`
- Modify: `tests/Feature/AI/ConversationAiReportPdfTemplateTest.php`

- [ ] **Step 1: Add failing assertions for strict skeleton labels and action line format**

```php
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
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/AI/ConversationAiReportPdfTemplateTest.php`
Expected: FAIL because old Blade still uses `Financial Snapshot`, `Deals`, and `Role-Based Actions` layout.

- [ ] **Step 3: Replace Blade sections with strict template order and fallback rendering**

```blade
<h1>Client Account Snapshot</h1>

<div class="section">
    <h2>Account Status</h2>
    <ul class="list">
        <li>Status: {{ $formatted['account_status']['status'] }}</li>
        <li>Health Score: {{ $formatted['account_status']['health'] }} — {{ $formatted['account_status']['health_reason'] ?? 'Not available' }}</li>
        <li>ARR: {{ $formatted['account_status']['arr'] }} | MRR: {{ $formatted['account_status']['mrr'] }} | Renewal: {{ $formatted['account_status']['renewal'] }}</li>
    </ul>
</div>

<div class="section">
    <h2>Executive Insights</h2>
    <ul class="list">
        @foreach($formatted['executive_insights'] as $item)
            <li>{{ $item }}</li>
        @endforeach
    </ul>
</div>

<div class="section">
    <h2>Recommended Actions (Next 30–60 Days)</h2>
    <ul class="list">
        @foreach($formatted['recommended_actions'] as $line)
            <li>{{ $line }}</li>
        @endforeach
    </ul>
</div>

@if(!empty($formatted['additional_context']))
<div class="section">
    <h2>Additional Context</h2>
    <ul class="list">
        @foreach($formatted['additional_context'] as $line)
            <li>{{ $line }}</li>
        @endforeach
    </ul>
</div>
@endif
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/AI/ConversationAiReportPdfTemplateTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add resources/views/reports/ai_summary_pdf.blade.php tests/Feature/AI/ConversationAiReportPdfTemplateTest.php
git commit -m "feat: enforce supervisor report skeleton in pdf blade"
```

### Task 4: Add Guardrails In Prompt + Skill Validation (TDD)

**Files:**
- Modify: `tests/Feature/AI/ReportValidatorTest.php`
- Modify: `app/Services/AI/Prompts/ReportPromptFactory.php`
- Modify: `app/Services/AI/Skills/ReportSkill.php`

- [ ] **Step 1: Add failing validator tests for enum + cardinality + action formatting**

```php
public function test_it_normalizes_invalid_status_and_health_to_allowed_values(): void
{
    $mockClient = Mockery::mock(OpenAiConversationClient::class);
    $mockClient->shouldReceive('generateReport')->andReturn([
        'summary' => 'Summary',
        'key_insights' => ['One'],
        'next_actions' => ['One'],
        'account_status' => 'Status: Excellent',
        'health_score' => 'Critical',
    ]);

    $skill = new ReportSkill($this->mockPromptFactory, $mockClient);
    $response = $skill->generate($this->mockJob, ['enabled' => true]);

    $this->assertContains($response['result']['normalized_status'], ['Strategic', 'Growth', 'At Risk', 'Stable']);
    $this->assertContains($response['result']['normalized_health_score'], ['High', 'Medium', 'Low']);
}

public function test_it_enforces_executive_insights_count_bounds(): void
{
    $mockClient = Mockery::mock(OpenAiConversationClient::class);
    $mockClient->shouldReceive('generateReport')->andReturn([
        'summary' => 'Summary',
        'key_insights' => ['One'],
        'next_actions' => ['One'],
        'executive_insights' => ['a','b','c','d','e','f'],
    ]);

    $skill = new ReportSkill($this->mockPromptFactory, $mockClient);
    $response = $skill->generate($this->mockJob, ['enabled' => true]);

    $this->assertCount(5, $response['result']['executive_insights']);
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/AI/ReportValidatorTest.php --filter="normalizes_invalid_status|enforces_executive_insights_count_bounds"`
Expected: FAIL because normalized fields and strict count enforcement do not exist yet.

- [ ] **Step 3: Implement prompt contract + normalization guardrails**

```php
// ReportPromptFactory.php output rules additions
// - status_value: Strategic|Growth|At Risk|Stable
// - health_score: High|Medium|Low
// - executive_insights: 3-5 bullets
// - recommended_actions_lines: Role -> Action -> Priority

// ReportSkill.php (inside ensureStructuredSections)
$data['normalized_status'] = $this->normalizeEnum(
    $data['status_value'] ?? null,
    ['Strategic', 'Growth', 'At Risk', 'Stable'],
    'Stable'
);

$data['normalized_health_score'] = $this->normalizeEnum(
    $data['health_score'] ?? null,
    ['High', 'Medium', 'Low'],
    'Medium'
);

$data['executive_insights'] = array_slice($this->normalizeStringArray($data['executive_insights'] ?? []), 0, 5);
if (count($data['executive_insights']) < 3) {
    $data['executive_insights'] = array_pad($data['executive_insights'], 3, 'Not available');
}
```

- [ ] **Step 4: Run validator tests to verify they pass**

Run: `php artisan test tests/Feature/AI/ReportValidatorTest.php`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Services/AI/Prompts/ReportPromptFactory.php app/Services/AI/Skills/ReportSkill.php tests/Feature/AI/ReportValidatorTest.php
git commit -m "feat: add report guardrails for template-plus normalization"
```

### Task 5: Full Verification + Final Commit Hygiene

**Files:**
- Modify as needed from previous tasks only

- [ ] **Step 1: Run focused report test suite**

Run:

```bash
php artisan test tests/Unit/AI/ReportTemplateFormatterTest.php tests/Feature/AI/ConversationAiReportPdfTemplateTest.php tests/Feature/AI/ReportValidatorTest.php tests/Unit/AI/ConversationAiReportServiceTest.php
```

Expected: PASS in environment with required DB driver.

- [ ] **Step 2: Run API regression for report flow**

Run:

```bash
php artisan test tests/Feature/AI/ConversationAiReportApiTest.php
```

Expected: PASS; if sqlite driver is missing, capture exact failure and mark as environment blocker.

- [ ] **Step 3: Manual PDF smoke check**

Run:

```bash
php artisan test tests/Feature/AI/ConversationAiReportApiTest.php --filter="streams generated report as a pdf file"
```

Expected: PASS with `content-type: application/pdf`.

- [ ] **Step 4: Review diff for skeleton compliance**

Run:

```bash
git diff -- app/Services/AI/Reports/ReportTemplateFormatter.php resources/views/reports/ai_summary_pdf.blade.php app/Services/AI/Skills/ReportSkill.php app/Services/AI/Prompts/ReportPromptFactory.php
```

Expected: All required sections present in fixed order with deterministic fallback handling.

- [ ] **Step 5: Commit final integration**

```bash
git add app/Services/AI/Reports/ReportTemplateFormatter.php app/Http/Controllers/AI/ConversationAiReportController.php resources/views/reports/ai_summary_pdf.blade.php app/Services/AI/Prompts/ReportPromptFactory.php app/Services/AI/Skills/ReportSkill.php tests/Unit/AI/ReportTemplateFormatterTest.php tests/Feature/AI/ConversationAiReportPdfTemplateTest.php tests/Feature/AI/ReportValidatorTest.php
git commit -m "feat: enforce template-plus ai summary pdf format"
```
