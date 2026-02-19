<?php

namespace Ai\AiAgent\Ingest\Web;

use Illuminate\Console\Command;

class ProcessWebpageIngestQueueCommand extends Command
{
    protected $signature = 'ingest:webpages';

    public function handle(): void
    {
        $result = (new WebsiteIngester())->processWebpageIngestQueue();

        if ($result) {
            $this->info(
                sprintf(
                    'Ingested %d out of %d pending webpages.',
                    $result['processedPages'],
                    $result['totalPagesInQueue'],
                ),
            );
        } else {
            $this->info('No pages to ingest.');
        }
    }
}
