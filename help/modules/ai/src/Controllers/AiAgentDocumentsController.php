<?php

namespace Ai\Controllers;

use Ai\AiAgent\DeleteAiAgent;
use Ai\AiAgent\Models\AiAgentDocument;
use Common\Core\BaseController;
use Common\Database\Datasource\Datasource;
use Illuminate\Support\Facades\DB;

class AiAgentDocumentsController extends BaseController
{
    public function index()
    {
        $this->authorize('update', 'aiAgent');

        $datasource = new Datasource(
            AiAgentDocument::with(['tags', 'fileEntry']),
            request()->all(),
        );

        $pagination = $datasource->paginate()->through(
            fn(AiAgentDocument $document) => [
                'id' => $document->id,
                'scan_pending' => $document->scan_pending,
                'scan_failed' => $document->scan_failed,
                'created_at' => $document->created_at,
                'updated_at' => $document->updated_at,
                'tags' => $document->tags->pluck('name'),
                'file_entry' => $document->fileEntry
                    ? [
                        'id' => $document->fileEntry->id,
                        'name' => $document->fileEntry->name,
                        'mime' => $document->fileEntry->mime,
                        'type' => $document->fileEntry->type,
                    ]
                    : null,
            ],
        );

        return $this->success([
            'pagination' => $pagination,
        ]);
    }

    public function show(int $documentId)
    {
        $this->authorize('update', 'aiAgent');

        $document = AiAgentDocument::with('fileEntry')->findOrFail($documentId);

        return $this->success([
            'document' => $document,
        ]);
    }

    public function destroy(string $documentIds)
    {
        $this->authorize('update', 'aiAgent');
        $this->blockOnDemoSite();

        $documentIds = explode(',', $documentIds);

        (new DeleteAiAgent())->deleteDocuments($documentIds);

        return $this->success();
    }
}
