<?php

namespace Ai\Controllers;

use Ai\AiAgent\DeleteAiAgent;
use Ai\AiAgent\Ingest\Snippets\IngestSnippets;
use Ai\AiAgent\Models\AiAgent;
use Ai\AiAgent\Models\AiAgentChunk;
use Ai\AiAgent\Models\AiAgentSnippet;
use Common\Core\BaseController;
use Common\Database\Datasource\Datasource;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class AiAgentSnippetsController extends BaseController
{
    public function index()
    {
        $this->authorize('update', 'aiAgent');

        $datasource = new Datasource(
            AiAgentSnippet::with('tags')->select([
                'ai_agent_snippets.id',
                'title',
                'used_by_ai_agent',
                'scan_pending',
                'ai_agent_snippets.created_at',
            ]),
            request()->all(),
        );

        $pagination = $datasource->paginate()->through(
            fn(AiAgentSnippet $snippet) => [
                'id' => $snippet->id,
                'title' => $snippet->title,
                'used_by_ai_agent' => $snippet->used_by_ai_agent,
                'scan_pending' => $snippet->scan_pending,
                'created_at' => $snippet->created_at,
                'tags' => $snippet->tags->pluck('name'),
            ],
        );

        return $this->success([
            'pagination' => $pagination,
        ]);
    }

    public function show(int $snippetId)
    {
        $this->authorize('update', 'aiAgent');

        $snippet = AiAgentSnippet::findOrFail($snippetId);

        return $this->success([
            'snippet' => $snippet,
        ]);
    }

    public function store()
    {
        $this->authorize('update', 'aiAgent');
        $this->blockOnDemoSite();

        $data = $this->validate(request(), [
            'title' => 'required|string|min:3',
            'body' => 'required|string|min:100',
            'aiAgentId' => 'required|exists:ai_agents,id',
        ]);

        $aiAgent = AiAgent::findOrFail($data['aiAgentId']);

        $snippet = $aiAgent->snippets()->create([
            'title' => $data['title'],
            'body' => $data['body'],
            'used_by_ai_agent' => true,
            'scan_pending' => true,
        ]);

        return $this->success([
            'snippet' => $snippet,
        ]);
    }

    public function update(int $snippetId)
    {
        $this->authorize('update', 'aiAgent');
        $this->blockOnDemoSite();

        $snippet = AiAgentSnippet::findOrFail($snippetId);

        $data = $this->validate(request(), [
            'title' => 'required|string|min:3',
            'body' => 'required|string|min:100',
        ]);

        if ($data['body'] !== $snippet->body && $snippet->used_by_ai_agent) {
            $data['scan_pending'] = true;
        }

        $snippet = $snippet->update($data);

        return $this->success([
            'snippet' => $snippet,
        ]);
    }

    public function destroy(string $snippetIds)
    {
        $this->authorize('update', 'aiAgent');
        $this->blockOnDemoSite();

        $snippetIds = explode(',', $snippetIds);

        (new DeleteAiAgent())->deleteSnippets($snippetIds);

        return $this->success();
    }

    public function ingest()
    {
        $this->authorize('update', 'aiAgent');
        $this->blockOnDemoSite();

        $data = $this->validate(request(), [
            'all' => 'boolean',
            'snippetIds' => 'array',
        ]);

        AiAgentSnippet::query()
            ->when(
                !Arr::get($data, 'all'),
                fn($query) => $query->whereIn('id', $data['snippetIds']),
            )
            ->where('used_by_ai_agent', false)
            ->update([
                'used_by_ai_agent' => true,
                'scan_pending' => true,
                'scan_started_at' => null,
            ]);

        (new IngestSnippets())->execute();

        return $this->success();
    }

    public function uningest()
    {
        $this->authorize('update', 'aiAgent');
        $this->blockOnDemoSite();

        $data = $this->validate(request(), [
            'all' => 'boolean',
            'snippetIds' => 'array',
        ]);

        AiAgentChunk::query()
            ->where('chunkable_type', AiAgentSnippet::MODEL_TYPE)
            ->when(
                !Arr::get($data, 'all'),
                fn($query) => $query->whereIn(
                    'chunkable_id',
                    $data['snippetIds'],
                ),
            )
            ->delete();

        AiAgentSnippet::query()
            ->when(
                !Arr::get($data, 'all'),
                fn($query) => $query->whereIn('id', $data['snippetIds']),
            )
            ->update([
                'used_by_ai_agent' => false,
                'scan_pending' => false,
                'scan_started_at' => null,
            ]);

        return $this->success();
    }
}
