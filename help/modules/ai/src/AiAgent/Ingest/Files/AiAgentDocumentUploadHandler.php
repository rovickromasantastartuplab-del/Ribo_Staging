<?php

namespace Ai\AiAgent\Ingest\Files;

use Ai\AiAgent\Models\AiAgent;
use Common\Files\FileEntry;

class AiAgentDocumentUploadHandler
{
    public function handle(FileEntry $fileEntry, array $data): FileEntry
    {
        $aiAgent = AiAgent::findOrFail($data['aiAgentId']);

        $document = $aiAgent->documents()->create([
            'file_entry_id' => $fileEntry->id,
            'language' => $data['documentLanguage'] ?? 'en',
        ]);

        IngestAiAgentDocumentJob::dispatch($document);

        return $fileEntry;
    }
}
