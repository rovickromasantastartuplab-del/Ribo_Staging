<?php

namespace Ai\Controllers;

use Ai\AiAgent\Models\AiAgent;
use Common\Core\BaseController;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AiAgentKnowledgeController extends BaseController
{
    public function index()
    {
        $this->authorize('update', 'aiAgent');

        $aiAgent = AiAgent::findOrFail(request('aiAgentId'));

        $websites = $aiAgent
            ->websites()
            ->get([
                'ai_agent_websites.id',
                'url',
                'scan_pending',
                'ai_agent_websites.updated_at',
            ]);

        $documents = $aiAgent
            ->documents()
            ->with([
                'fileEntry' => fn(BelongsTo $q) => $q->select([
                    'file_entries.id',
                    'name',
                    'mime',
                    'type',
                ]),
            ])
            ->get([
                'ai_agent_documents.id',
                'file_entry_id',
                'scan_pending',
                'scan_failed',
                'ai_agent_documents.updated_at',
            ]);

        $articles = $aiAgent
            ->articles()
            ->get([
                'articles.id',
                'title',
                'scan_pending',
                'articles.updated_at',
            ]);

        $snippets = $aiAgent
            ->snippets()
            ->where('used_by_ai_agent', true)
            ->get([
                'ai_agent_snippets.id',
                'title',
                'scan_pending',
                'ai_agent_snippets.updated_at',
            ]);

        $websites = $this->prepareResponse($websites);
        $documents = $this->prepareResponse($documents);
        $articles = $this->prepareResponse($articles);
        $snippets = $this->prepareResponse($snippets);

        $ingesting =
            $websites['ingesting'] ||
            $documents['ingesting'] ||
            $articles['ingesting'] ||
            $snippets['ingesting'];

        return $this->success([
            'websites' => $websites,
            'documents' => $documents,
            'articles' => $articles,
            'snippets' => $snippets,
            'ingesting' => $ingesting,
        ]);
    }

    protected function prepareResponse(Collection $items): array
    {
        $visibleItems = $items->take(3);
        $otherItems = $items->slice(3);

        return [
            'items' => $visibleItems,
            'ingesting' => $items->some(fn($i) => $i->scan_pending),
            'more' => [
                'count' => $otherItems->count(),
                'ingesting' => $otherItems->some(fn($i) => $i->scan_pending),
            ],
        ];
    }
}
