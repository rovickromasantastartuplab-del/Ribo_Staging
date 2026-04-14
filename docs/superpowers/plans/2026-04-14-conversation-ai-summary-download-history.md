# Conversation AI Summary Download History Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add persistent summary-download version history for conversation AI reports, with re-download support and per-version download counters visible in a modal for all company users.

**Architecture:** Extend the current report download flow in `ConversationAiReportController` with a dedicated persistence service that stores one version per `ai_report_job_id`, writes/reuses a PDF file in private storage, and atomically updates counters. Expose two new read/download endpoints for history and version re-download, then add a modal in `AiTriageCard` to consume those APIs.

**Tech Stack:** Laravel (controllers, migrations, Eloquent, Pest feature tests), React + TypeScript + Inertia UI, Axios, shadcn dialog/table primitives, DomPDF.

---

## File Structure

- Create: `database/migrations/2026_04_14_120000_create_ai_report_versions_table.php`
- Create: `app/Models/AiReportVersion.php`
- Create: `app/Services/AI/ConversationAiReportVersionService.php`
- Modify: `app/Http/Controllers/AI/ConversationAiReportController.php`
- Modify: `routes/web.php`
- Create: `tests/Feature/AI/ConversationAiReportDownloadHistoryTest.php`
- Create: `resources/js/pages/conversations/components/AiReportHistoryDialog.tsx`
- Modify: `resources/js/pages/conversations/components/AiTriageCard.tsx`

Each file has one role:
- Migration + model define persistence boundary.
- Version service owns save/retrieve/increment logic.
- Controller only orchestrates auth + HTTP responses.
- New feature test file validates backend behavior end-to-end.
- New dialog component encapsulates history UI.
- `AiTriageCard` wires interactions.

### Task 1: Add Persistence Schema and Model

**Files:**
- Create: `database/migrations/2026_04_14_120000_create_ai_report_versions_table.php`
- Create: `app/Models/AiReportVersion.php`
- Test: `tests/Feature/AI/ConversationAiReportDownloadHistoryTest.php`

- [ ] **Step 1: Write failing migration-level feature test for first download persistence**

```php
it('creates one ai report version row on first successful download', function () {
    [$staff, $company, $thread] = createReportFixture();
    actingAs($staff);
    disableNonApiBlockingMiddlewareForReport();

    $response = postJson('/ai/reports/generate', [
        'threadId' => $thread->id,
        'scope' => 'overall',
        'contactId' => null,
    ])->assertOk();

    $jobId = (int) $response->json('data.job_id');

    get("/ai/reports/{$jobId}/download")->assertOk();

    $this->assertDatabaseHas('ai_report_versions', [
        'created_by' => $company->id,
        'email_thread_id' => $thread->id,
        'ai_report_job_id' => $jobId,
        'download_count' => 1,
    ]);
});
```

- [ ] **Step 2: Run targeted test and verify it fails (missing table/model)**

Run: `php artisan test tests/Feature/AI/ConversationAiReportDownloadHistoryTest.php --filter="creates one ai report version row"`
Expected: FAIL with SQL error for missing `ai_report_versions` table.

- [ ] **Step 3: Create migration**

```php
Schema::create('ai_report_versions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
    $table->foreignId('email_thread_id')->constrained('email_threads')->cascadeOnDelete();
    $table->foreignId('ai_report_job_id')->constrained('ai_report_jobs')->cascadeOnDelete();
    $table->string('scope', 30)->default('overall');
    $table->json('snapshot_json')->nullable();
    $table->string('pdf_path')->nullable();
    $table->string('template_version', 50)->nullable();
    $table->unsignedInteger('download_count')->default(0);
    $table->timestamp('first_downloaded_at')->nullable();
    $table->timestamp('last_downloaded_at')->nullable();
    $table->foreignId('last_downloaded_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamps();

    $table->unique('ai_report_job_id');
    $table->index(['created_by', 'email_thread_id']);
    $table->index('last_downloaded_at');
});
```

- [ ] **Step 4: Create model with casts and relationships**

```php
class AiReportVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'created_by',
        'email_thread_id',
        'ai_report_job_id',
        'scope',
        'snapshot_json',
        'pdf_path',
        'template_version',
        'download_count',
        'first_downloaded_at',
        'last_downloaded_at',
        'last_downloaded_by',
    ];

    protected $casts = [
        'snapshot_json' => 'array',
        'first_downloaded_at' => 'datetime',
        'last_downloaded_at' => 'datetime',
    ];

    public function lastDownloader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_downloaded_by');
    }
}
```

- [ ] **Step 5: Re-run targeted test to verify next failure point moves to controller logic**

Run: `php artisan test tests/Feature/AI/ConversationAiReportDownloadHistoryTest.php --filter="creates one ai report version row"`
Expected: FAIL because controller does not yet write version row.

- [ ] **Step 6: Commit schema/model groundwork**

```bash
git add database/migrations/2026_04_14_120000_create_ai_report_versions_table.php app/Models/AiReportVersion.php tests/Feature/AI/ConversationAiReportDownloadHistoryTest.php
git commit -m "feat: add ai report version persistence schema"
```

### Task 2: Implement Version Persistence in Existing Download Flow

**Files:**
- Create: `app/Services/AI/ConversationAiReportVersionService.php`
- Modify: `app/Http/Controllers/AI/ConversationAiReportController.php`
- Test: `tests/Feature/AI/ConversationAiReportDownloadHistoryTest.php`

- [ ] **Step 1: Add failing test for repeated download counter increment**

```php
it('increments download_count when same report job is downloaded again', function () {
    [$staff, $company, $thread] = createReportFixture();
    actingAs($staff);
    disableNonApiBlockingMiddlewareForReport();

    $jobId = (int) postJson('/ai/reports/generate', [
        'threadId' => $thread->id,
        'scope' => 'overall',
        'contactId' => null,
    ])->assertOk()->json('data.job_id');

    get("/ai/reports/{$jobId}/download")->assertOk();
    get("/ai/reports/{$jobId}/download")->assertOk();

    $this->assertDatabaseHas('ai_report_versions', [
        'ai_report_job_id' => $jobId,
        'download_count' => 2,
    ]);
    expect(\App\Models\AiReportVersion::query()->where('ai_report_job_id', $jobId)->count())->toBe(1);
});
```

- [ ] **Step 2: Run targeted test and confirm failure**

Run: `php artisan test tests/Feature/AI/ConversationAiReportDownloadHistoryTest.php --filter="increments download_count"`
Expected: FAIL because count remains unchanged or row missing.

- [ ] **Step 3: Implement version service (upsert + increment + storage path)**

```php
class ConversationAiReportVersionService
{
    public function storagePath(int $companyId, int $jobId): string
    {
        return "ai-reports/{$companyId}/{$jobId}.pdf";
    }

    public function recordSuccessfulDownload(AiReportJob $job, int $userId, string $pdfBinary, string $templateVersion = 'ai-summary-v1'): AiReportVersion
    {
        $path = $this->storagePath((int) $job->created_by, (int) $job->id);
        Storage::disk('local')->put($path, $pdfBinary);

        $version = AiReportVersion::query()->firstOrCreate(
            ['ai_report_job_id' => $job->id],
            [
                'created_by' => $job->created_by,
                'email_thread_id' => $job->email_thread_id,
                'scope' => (string) ($job->scope ?? 'overall'),
                'snapshot_json' => [
                    'result' => $job->result_payload_json,
                    'context' => $job->context_payload_json,
                ],
                'pdf_path' => $path,
                'template_version' => $templateVersion,
                'first_downloaded_at' => now(),
            ]
        );

        $version->increment('download_count');
        $version->forceFill([
            'last_downloaded_at' => now(),
            'last_downloaded_by' => $userId,
            'pdf_path' => $path,
        ])->save();

        return $version->refresh();
    }
}
```

- [ ] **Step 4: Refactor controller download action to use version service**

```php
$pdfContent = $pdf->output();
$this->reportVersionService->recordSuccessfulDownload(
    $reportJob,
    (int) auth()->id(),
    $pdfContent,
    'ai-summary-v1'
);

return response($pdfContent, 200, [
    'Content-Type' => 'application/pdf',
    'Content-Disposition' => "attachment; filename=AI-Summary-Report-{$reportJob->id}.pdf",
]);
```

- [ ] **Step 5: Re-run tests for creation + increment**

Run: `php artisan test tests/Feature/AI/ConversationAiReportDownloadHistoryTest.php --filter="ai report version|increments download_count"`
Expected: PASS for both tests.

- [ ] **Step 6: Commit controller/service behavior**

```bash
git add app/Services/AI/ConversationAiReportVersionService.php app/Http/Controllers/AI/ConversationAiReportController.php tests/Feature/AI/ConversationAiReportDownloadHistoryTest.php
git commit -m "feat: persist and count ai report downloads"
```

### Task 3: Add History and Version Re-download APIs

**Files:**
- Modify: `app/Http/Controllers/AI/ConversationAiReportController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/AI/ConversationAiReportDownloadHistoryTest.php`

- [ ] **Step 1: Add failing tests for history list and version download endpoint**

```php
it('returns report download history rows for a thread', function () {
    [$staff, , $thread] = createReportFixture();
    actingAs($staff);
    disableNonApiBlockingMiddlewareForReport();

    $jobId = (int) postJson('/ai/reports/generate', [
        'threadId' => $thread->id,
        'scope' => 'overall',
        'contactId' => null,
    ])->assertOk()->json('data.job_id');

    get("/ai/reports/{$jobId}/download")->assertOk();

    getJson("/ai/reports/history/{$thread->id}")
        ->assertOk()
        ->assertJsonPath('data.0.ai_report_job_id', $jobId)
        ->assertJsonPath('data.0.download_count', 1);
});

it('re-downloads by version id and increments counter', function () {
    [$staff, , $thread] = createReportFixture();
    actingAs($staff);
    disableNonApiBlockingMiddlewareForReport();

    $jobId = (int) postJson('/ai/reports/generate', [
        'threadId' => $thread->id,
        'scope' => 'overall',
        'contactId' => null,
    ])->assertOk()->json('data.job_id');

    get("/ai/reports/{$jobId}/download")->assertOk();

    $versionId = \App\Models\AiReportVersion::query()->where('ai_report_job_id', $jobId)->value('id');

    get("/ai/reports/version/{$versionId}/download")
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    $this->assertDatabaseHas('ai_report_versions', [
        'id' => $versionId,
        'download_count' => 2,
    ]);
});
```

- [ ] **Step 2: Run tests and verify expected endpoint-not-found failures**

Run: `php artisan test tests/Feature/AI/ConversationAiReportDownloadHistoryTest.php --filter="history rows|re-downloads by version"`
Expected: FAIL with 404 for missing routes/actions.

- [ ] **Step 3: Add routes**

```php
Route::get('/ai/reports/history/{thread}', [ConversationAiReportController::class, 'history']);
Route::get('/ai/reports/version/{version}/download', [ConversationAiReportController::class, 'downloadVersion']);
```

- [ ] **Step 4: Implement controller actions with company-boundary authorization**

```php
public function history(EmailThread $thread): JsonResponse
{
    $companyId = (int) auth()->user()->creatorId();
    abort_if((int) $thread->created_by !== $companyId, 403);

    $rows = AiReportVersion::query()
        ->where('created_by', $companyId)
        ->where('email_thread_id', $thread->id)
        ->with(['lastDownloader:id,name'])
        ->orderByDesc('last_downloaded_at')
        ->get();

    return response()->json([
        'data' => $rows->map(fn (AiReportVersion $row) => [
            'id' => $row->id,
            'ai_report_job_id' => $row->ai_report_job_id,
            'scope' => $row->scope,
            'download_count' => $row->download_count,
            'first_downloaded_at' => optional($row->first_downloaded_at)->toIso8601String(),
            'last_downloaded_at' => optional($row->last_downloaded_at)->toIso8601String(),
            'last_downloaded_by' => $row->lastDownloader ? ['id' => $row->lastDownloader->id, 'name' => $row->lastDownloader->name] : null,
            'created_at' => optional($row->created_at)->toIso8601String(),
        ]),
    ]);
}
```

```php
public function downloadVersion(AiReportVersion $version)
{
    $companyId = (int) auth()->user()->creatorId();
    abort_if((int) $version->created_by !== $companyId, 403);

    $reportJob = $this->reportService->get(
        AiReportJob::query()->findOrFail($version->ai_report_job_id),
        $companyId
    );

    return $this->download($reportJob);
}
```

- [ ] **Step 5: Add forbidden-access regression test**

```php
it('forbids history access across companies', function () {
    [$staffA] = createReportFixture();
    [, , $threadB] = createReportFixture();

    actingAs($staffA);
    disableNonApiBlockingMiddlewareForReport();

    getJson("/ai/reports/history/{$threadB->id}")->assertForbidden();
});
```

- [ ] **Step 6: Run full backend report suites**

Run:
- `php artisan test tests/Feature/AI/ConversationAiReportDownloadHistoryTest.php`
- `php artisan test tests/Feature/AI/ConversationAiReportApiTest.php`

Expected: PASS.

- [ ] **Step 7: Commit API additions**

```bash
git add app/Http/Controllers/AI/ConversationAiReportController.php routes/web.php tests/Feature/AI/ConversationAiReportDownloadHistoryTest.php
git commit -m "feat: add ai report download history and version endpoints"
```

### Task 4: Add Missing-File Regeneration Fallback

**Files:**
- Modify: `app/Services/AI/ConversationAiReportVersionService.php`
- Modify: `app/Http/Controllers/AI/ConversationAiReportController.php`
- Test: `tests/Feature/AI/ConversationAiReportDownloadHistoryTest.php`

- [ ] **Step 1: Add failing test for missing stored file fallback**

```php
it('regenerates pdf from snapshot when stored file is missing', function () {
    [$staff, , $thread] = createReportFixture();
    actingAs($staff);
    disableNonApiBlockingMiddlewareForReport();

    $jobId = (int) postJson('/ai/reports/generate', [
        'threadId' => $thread->id,
        'scope' => 'overall',
        'contactId' => null,
    ])->assertOk()->json('data.job_id');

    get("/ai/reports/{$jobId}/download")->assertOk();

    $version = \App\Models\AiReportVersion::query()->where('ai_report_job_id', $jobId)->firstOrFail();
    \Illuminate\Support\Facades\Storage::disk('local')->delete((string) $version->pdf_path);

    get("/ai/reports/version/{$version->id}/download")
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    expect(\Illuminate\Support\Facades\Storage::disk('local')->exists((string) $version->fresh()->pdf_path))->toBeTrue();
});
```

- [ ] **Step 2: Run failing test**

Run: `php artisan test tests/Feature/AI/ConversationAiReportDownloadHistoryTest.php --filter="regenerates pdf from snapshot"`
Expected: FAIL because controller/service does not attempt restore.

- [ ] **Step 3: Implement restore helper in version service**

```php
public function getOrRestorePdf(AiReportVersion $version, callable $regenerate): string
{
    $path = (string) ($version->pdf_path ?? '');

    if ($path !== '' && Storage::disk('local')->exists($path)) {
        return (string) Storage::disk('local')->get($path);
    }

    $binary = $regenerate();
    $fallbackPath = $this->storagePath((int) $version->created_by, (int) $version->ai_report_job_id);
    Storage::disk('local')->put($fallbackPath, $binary);

    $version->forceFill(['pdf_path' => $fallbackPath])->save();

    return $binary;
}
```

- [ ] **Step 4: Use restore helper in `downloadVersion` flow and keep counter updates success-only**

```php
$binary = $this->reportVersionService->getOrRestorePdf($version, function () use ($reportJob) {
    return $this->buildReportPdfBinary($reportJob);
});

$this->reportVersionService->markRedownload($version, (int) auth()->id());

return response($binary, 200, [
    'Content-Type' => 'application/pdf',
    'Content-Disposition' => "attachment; filename=AI-Summary-Report-{$reportJob->id}.pdf",
]);
```

- [ ] **Step 5: Run fallback and regression tests**

Run: `php artisan test tests/Feature/AI/ConversationAiReportDownloadHistoryTest.php`
Expected: PASS.

- [ ] **Step 6: Commit fallback behavior**

```bash
git add app/Services/AI/ConversationAiReportVersionService.php app/Http/Controllers/AI/ConversationAiReportController.php tests/Feature/AI/ConversationAiReportDownloadHistoryTest.php
git commit -m "feat: restore missing ai report pdf files from snapshots"
```

### Task 5: Build Download History Modal UI

**Files:**
- Create: `resources/js/pages/conversations/components/AiReportHistoryDialog.tsx`
- Modify: `resources/js/pages/conversations/components/AiTriageCard.tsx`

- [ ] **Step 1: Add failing type usage by referencing new dialog in `AiTriageCard`**

```tsx
<AiReportHistoryDialog threadId={data.email_thread_id} />
```

- [ ] **Step 2: Run type check and verify failure**

Run: `npm run types`
Expected: FAIL with missing import/component file.

- [ ] **Step 3: Implement `AiReportHistoryDialog` component with fetch + table + download action**

```tsx
type HistoryRow = {
  id: number;
  ai_report_job_id: number;
  scope: string;
  download_count: number;
  first_downloaded_at: string | null;
  last_downloaded_at: string | null;
  last_downloaded_by: { id: number; name: string } | null;
};

export default function AiReportHistoryDialog({ threadId }: { threadId: number | null | undefined }) {
  const [open, setOpen] = useState(false);
  const [rows, setRows] = useState<HistoryRow[]>([]);
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    if (!open || !threadId) return;
    setLoading(true);
    axios.get(`/ai/reports/history/${threadId}`)
      .then((res) => setRows(Array.isArray(res.data?.data) ? res.data.data : []))
      .catch(() => toast.error('Failed to load download history.'))
      .finally(() => setLoading(false));
  }, [open, threadId]);

  // table rows + button that calls /ai/reports/version/{id}/download as blob
}
```

- [ ] **Step 4: Update `AiTriageCard` UI actions to include history modal trigger**

```tsx
<div className="flex items-center gap-2">
  <AiReportHistoryDialog threadId={data.email_thread_id} />
  <Button ...>Download Summary Report</Button>
</div>
```

- [ ] **Step 5: Re-run type + lint checks**

Run:
- `npm run types`
- `npm run lint`

Expected: PASS.

- [ ] **Step 6: Commit frontend modal integration**

```bash
git add resources/js/pages/conversations/components/AiReportHistoryDialog.tsx resources/js/pages/conversations/components/AiTriageCard.tsx
git commit -m "feat: add ai summary download history modal"
```

### Task 6: Final Verification and Rollout Safety Check

**Files:**
- Modify (if needed after verification): any touched file listed above

- [ ] **Step 1: Run complete AI report backend tests**

Run:
- `php artisan test tests/Feature/AI/ConversationAiReportApiTest.php`
- `php artisan test tests/Feature/AI/ConversationAiReportDownloadHistoryTest.php`
- `php artisan test tests/Feature/AI/ConversationAiAuthorizationTest.php`

Expected: PASS.

- [ ] **Step 2: Run frontend static checks**

Run:
- `npm run types`
- `npm run lint`

Expected: PASS.

- [ ] **Step 3: Manual QA smoke flow in conversation UI**

```text
1) Open a conversation with AI triage.
2) Click Download Summary Report (first time).
3) Open Summary Download History modal.
4) Verify one row exists with download_count=1.
5) Click Download in modal.
6) Re-open modal and verify download_count=2 and last downloader updated.
7) Login as another user in same company and verify history is visible.
8) Verify cross-company user cannot access the history endpoint.
```

- [ ] **Step 4: Commit any final fixes from verification**

```bash
git add app/Http/Controllers/AI/ConversationAiReportController.php app/Services/AI/ConversationAiReportVersionService.php resources/js/pages/conversations/components/AiReportHistoryDialog.tsx resources/js/pages/conversations/components/AiTriageCard.tsx tests/Feature/AI/ConversationAiReportDownloadHistoryTest.php routes/web.php
git commit -m "chore: finalize ai report download history verification fixes"
```

## Self-Review Checklist

- Spec coverage:
  - Version persistence and `download_count` rules: covered by Tasks 1-2.
  - History and version re-download APIs: covered by Task 3.
  - Missing-file regeneration: covered by Task 4.
  - Modal UX for all company users: covered by Task 5.
  - Verification and access boundaries: covered by Task 6.
- Placeholder scan: no `TODO`/`TBD`/ambiguous “handle edge cases” placeholders remain.
- Type consistency:
  - Uses `ai_report_job_id` as unique version identity throughout.
  - Reuses `download_count`, `last_downloaded_at`, `last_downloaded_by` consistently across migration/model/API/UI.
