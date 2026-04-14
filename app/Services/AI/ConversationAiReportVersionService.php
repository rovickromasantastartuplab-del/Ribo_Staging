<?php

namespace App\Services\AI;

use App\Models\AiReportJob;
use App\Models\AiReportVersion;
use Illuminate\Support\Facades\Storage;

class ConversationAiReportVersionService
{
    public function storagePath(int $companyId, int $jobId): string
    {
        return "ai-reports/{$companyId}/{$jobId}.pdf";
    }

    public function recordSuccessfulDownload(
        AiReportJob $job,
        int $userId,
        string $pdfBinary,
        string $templateVersion = 'ai-summary-v1'
    ): AiReportVersion {
        $path = $this->storagePath((int) $job->created_by, (int) $job->id);
        Storage::disk('local')->put($path, $pdfBinary);

        $now = now();

        $version = AiReportVersion::query()->firstOrCreate(
            ['ai_report_job_id' => (int) $job->id],
            [
                'created_by' => (int) $job->created_by,
                'email_thread_id' => (int) $job->email_thread_id,
                'scope' => (string) ($job->scope ?? 'overall'),
                'snapshot_json' => [
                    'result' => $job->result_payload_json,
                    'context' => $job->context_payload_json,
                ],
                'pdf_path' => $path,
                'template_version' => $templateVersion,
                'first_downloaded_at' => $now,
            ]
        );

        $version->increment('download_count');

        $version->forceFill([
            'last_downloaded_at' => $now,
            'last_downloaded_by' => $userId,
            'pdf_path' => $path,
        ])->save();

        return $version->refresh();
    }

    public function getOrRestorePdf(AiReportVersion $version, callable $regenerate): string
    {
        $path = (string) ($version->pdf_path ?? '');

        if ($path !== '' && Storage::disk('local')->exists($path)) {
            return (string) Storage::disk('local')->get($path);
        }

        $binary = $regenerate();
        $fallbackPath = $this->storagePath((int) $version->created_by, (int) $version->ai_report_job_id);

        Storage::disk('local')->put($fallbackPath, $binary);

        $version->forceFill([
            'pdf_path' => $fallbackPath,
        ])->save();

        return $binary;
    }

    public function markRedownload(AiReportVersion $version, int $userId): AiReportVersion
    {
        $now = now();

        $version->increment('download_count');

        $version->forceFill([
            'first_downloaded_at' => $version->first_downloaded_at ?? $now,
            'last_downloaded_at' => $now,
            'last_downloaded_by' => $userId,
        ])->save();

        return $version->refresh();
    }
}
