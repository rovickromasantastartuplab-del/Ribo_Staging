<?php

use Illuminate\Support\Facades\Schema;

it('has conversation ai tables', function () {
    expect(Schema::hasTable('ai_triage_results'))->toBeTrue();
    expect(Schema::hasTable('ai_memory_summaries'))->toBeTrue();
    expect(Schema::hasTable('ai_tasks'))->toBeTrue();
    expect(Schema::hasTable('ai_draft_runs'))->toBeTrue();
    expect(Schema::hasTable('ai_report_jobs'))->toBeTrue();
    expect(Schema::hasTable('ai_feedback_logs'))->toBeTrue();
    expect(Schema::hasTable('ai_usage_logs'))->toBeTrue();
});

it('uses tenant-scoped created_by columns on conversation ai tables', function () {
    expect(Schema::hasColumn('ai_triage_results', 'created_by'))->toBeTrue();
    expect(Schema::hasColumn('ai_memory_summaries', 'created_by'))->toBeTrue();
    expect(Schema::hasColumn('ai_tasks', 'created_by'))->toBeTrue();
    expect(Schema::hasColumn('ai_draft_runs', 'created_by'))->toBeTrue();
    expect(Schema::hasColumn('ai_report_jobs', 'created_by'))->toBeTrue();
    expect(Schema::hasColumn('ai_feedback_logs', 'created_by'))->toBeTrue();
    expect(Schema::hasColumn('ai_usage_logs', 'created_by'))->toBeTrue();
});
