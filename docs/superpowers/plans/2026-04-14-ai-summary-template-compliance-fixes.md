# AI Summary Template Compliance Fixes Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Enforce two template compliance rules in the AI summary PDF: always render the `Gaps:` line in Key Relationships, and always render row placeholders as `Not available` instead of omitting rows.

**Architecture:** Keep existing report generation flow (`ReportTemplateFormatter` -> Blade render) and fix rendering behavior at the Blade layer to be deterministic. Add focused feature tests that fail under current conditional rendering and pass after deterministic placeholders are rendered.

**Tech Stack:** Laravel, Blade, Pest/PHPUnit, DomPDF view rendering tests

---

## File Structure

- Modify: `resources/views/reports/ai_summary_pdf.blade.php`
- Modify: `tests/Feature/AI/ConversationAiReportPdfTemplateTest.php`

### Task 1: Lock Failing Tests For Placeholder and Gaps Behavior

**Files:**
- Modify: `tests/Feature/AI/ConversationAiReportPdfTemplateTest.php`
- Read-only reference: `resources/views/reports/ai_summary_pdf.blade.php`

- [ ] **Step 1: Add failing test for always-on Gaps line**

```php
it('always renders a Gaps line in key relationships section', function () {
    $formatter = app(\App\Services\AI\Reports\ReportTemplateFormatter::class);
    $formatted = $formatter->format([], ['crm' => []], 'overall');

    // Force explicit "Not available" to validate placeholder rendering path.
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
```

- [ ] **Step 2: Add failing test for deterministic Not available rows**

```php
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
```

- [ ] **Step 3: Run focused test file to confirm failure**

Run:

```bash
php artisan test tests/Feature/AI/ConversationAiReportPdfTemplateTest.php
```

Expected: FAIL because current Blade suppresses `Gaps` and hides rows when value is `Not available`.

- [ ] **Step 4: Commit failing test scaffolding**

```bash
git add tests/Feature/AI/ConversationAiReportPdfTemplateTest.php
git commit -m "test: capture ai summary pdf gaps and placeholder compliance rules"
```

### Task 2: Implement Deterministic Blade Rendering

**Files:**
- Modify: `resources/views/reports/ai_summary_pdf.blade.php`
- Test against: `tests/Feature/AI/ConversationAiReportPdfTemplateTest.php`

- [ ] **Step 1: Replace conditional Account Status rows with explicit placeholders**

Use deterministic rendering:

```blade
<ul class="list">
    <li><span class="label">Status:</span> {{ $accountStatus['status'] ?? 'Not available' }}</li>
    <li><span class="label">Health Score:</span> {{ $accountStatus['health'] ?? 'Not available' }} - {{ $accountStatus['health_reason'] ?? 'Not available' }}</li>
    <li>
        <span class="label">ARR:</span> {{ $accountStatus['arr'] ?? 'Not available' }} |
        <span class="label">MRR:</span> {{ $accountStatus['mrr'] ?? 'Not available' }} |
        <span class="label">Renewal:</span> {{ $accountStatus['renewal'] ?? 'Not available' }}
    </li>
</ul>
```

- [ ] **Step 2: Always render Gaps line in Key Relationships**

Replace conditional `Coverage Insights` block with unconditional `Gaps` line:

```blade
<p class="gaps"><span class="label">Gaps:</span> {{ $relationshipGaps ?? 'Not available' }}</p>
```

- [ ] **Step 3: Replace conditional Deals and Engagement rows with explicit placeholders**

Use deterministic rendering:

```blade
<li><span class="label">Active Deals:</span> {{ $deals['active_deals'] ?? 'Not available' }}</li>
<li><span class="label">Top Deal:</span> {{ $deals['top_deal'] ?? 'Not available' }}</li>
<li><span class="label">Expansion Potential:</span> {{ $deals['expansion_potential'] ?? 'Not available' }}</li>
<li>
    <span class="label">Notable Past Deals:</span>
    Won: {{ $deals['notable_won'] ?? 'Not available' }} |
    Lost: {{ $deals['notable_lost'] ?? 'Not available' }} |
    Stalled: {{ $deals['notable_stalled'] ?? 'Not available' }}
</li>
```

```blade
<li><span class="label">Usage:</span> {{ $healthSignals['usage'] ?? 'Not available' }}</li>
<li><span class="label">Support:</span> {{ $healthSignals['support'] ?? 'Not available' }}</li>
<li><span class="label">Sentiment:</span> {{ $healthSignals['sentiment'] ?? 'Not available' }}</li>
<li><span class="label">Engagement Pattern:</span> {{ $healthSignals['engagement_pattern'] ?? 'Not available' }}</li>
```

- [ ] **Step 4: Run tests to verify pass**

Run:

```bash
php artisan test tests/Feature/AI/ConversationAiReportPdfTemplateTest.php
```

Expected: PASS.

- [ ] **Step 5: Commit implementation**

```bash
git add resources/views/reports/ai_summary_pdf.blade.php tests/Feature/AI/ConversationAiReportPdfTemplateTest.php
git commit -m "feat: enforce deterministic gaps and placeholder rows in ai summary pdf"
```

### Task 3: Regression Verification and Manual PDF Sanity

**Files:**
- No new files
- Verify output path through `ConversationAiReportController`

- [ ] **Step 1: Run report validator regression**

```bash
php artisan test tests/Feature/AI/ReportValidatorTest.php
```

Expected: PASS.

- [ ] **Step 2: Run report API coverage**

```bash
php artisan test tests/Feature/AI/ConversationAiReportApiTest.php
```

Expected: PASS (or environment-specific DB driver error documented as blocker).

- [ ] **Step 3: Manual render check for heading and placeholders**

Generate a report in app and confirm:
- `Key Relationships` always includes `Gaps: ...`
- `Account Status`, `Deals`, and `Engagement` rows always present even with sparse data

- [ ] **Step 4: Commit verification notes if needed**

```bash
git add <only-if-any-verification-artifacts>
git commit -m "chore: verify ai summary template compliance behavior"
```

---

## Self-Review Checklist (Completed)

- Spec coverage: Plan addresses both requested compliance gaps directly.
- Placeholder scan: No TODO/TBD placeholders in implementation steps.
- Consistency: Tests and Blade changes target the same labels and behavior.
