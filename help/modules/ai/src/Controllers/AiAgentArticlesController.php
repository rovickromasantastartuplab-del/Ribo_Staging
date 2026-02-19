<?php

namespace Ai\Controllers;

use Ai\AiAgent\Ingest\Articles\IngestArticles;
use Ai\AiAgent\Models\AiAgent;
use Ai\AiAgent\Models\AiAgentChunk;
use App\HelpCenter\Models\HcArticle;
use Common\Core\BaseController;
use Illuminate\Support\Arr;

class AiAgentArticlesController extends BaseController
{
    public function ingestArticles()
    {
        $this->authorize('update', 'aiAgent');
        $this->blockOnDemoSite();

        $data = $this->validate(request(), [
            'all' => 'boolean',
            'articleIds' => 'array',
            'aiAgentId' => 'required|exists:ai_agents,id',
        ]);

        $aiAgent = AiAgent::findOrFail($data['aiAgentId']);

        $articleIds = HcArticle::when(
            !Arr::get($data, 'all'),
            fn($query) => $query->whereIn('id', $data['articleIds']),
            fn($query) => $query->where('draft', false),
        )->pluck('id');

        HcArticle::whereIn('id', $articleIds)->update([
            'scan_pending' => true,
            'scan_started_at' => null,
        ]);

        $aiAgent->articles()->syncWithoutDetaching($articleIds);

        (new IngestArticles())->execute();

        return $this->success();
    }

    public function uningestArticles()
    {
        $this->authorize('update', 'aiAgent');
        $this->blockOnDemoSite();

        $data = $this->validate(request(), [
            'all' => 'boolean',
            'articleIds' => 'array',
            'aiAgentId' => 'required|exists:ai_agents,id',
        ]);

        $aiAgent = AiAgent::findOrFail($data['aiAgentId']);

        AiAgentChunk::query()
            ->where('chunkable_type', HcArticle::MODEL_TYPE)
            ->when(
                !Arr::get($data, 'all'),
                fn($query) => $query->whereIn(
                    'chunkable_id',
                    $data['articleIds'],
                ),
            )
            ->delete();

        $articleIds = HcArticle::query()
            ->when(
                !Arr::get($data, 'all'),
                fn($query) => $query->whereIn('id', $data['articleIds']),
            )
            ->pluck('id');

        HcArticle::whereIn('id', $articleIds)->update([
            'scan_pending' => false,
            'scan_started_at' => null,
        ]);

        $aiAgent->articles()->detach($articleIds);

        return $this->success();
    }
}
