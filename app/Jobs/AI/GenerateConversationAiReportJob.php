<?php

namespace App\Jobs\AI;

use App\Services\AI\ConversationAiReportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateConversationAiReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $reportJobId
    ) {
    }

    public function handle(ConversationAiReportService $reportService): void
    {
        $reportService->process($this->reportJobId);
    }
}
