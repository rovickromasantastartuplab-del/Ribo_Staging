<?php

namespace Ai\AiAgent;

use Ai\AiAgent\Models\AiAgent;
use Ai\AiAgent\Models\AiAgentDocument;
use Ai\AiAgent\Models\AiAgentFlow;
use Ai\AiAgent\Models\AiAgentSnippet;
use Ai\AiAgent\Models\AiAgentTool;
use Ai\AiAgent\Models\AiAgentWebpage;
use Ai\AiAgent\Models\AiAgentWebsite;
use App\HelpCenter\Models\HcArticle;
use Common\Files\Actions\Deletion\PermanentlyDeleteEntries;
use Illuminate\Support\Facades\DB;

class DeleteAiAgent
{
    public function delete(int $aiAgentId)
    {
        $aiAgent = AiAgent::findOrFail($aiAgentId);

        // chunkables
        $this->deleteSnippets(
            $aiAgent->snippets()->get()->pluck('id')->toArray(),
        );
        $this->deleteDocuments(
            $aiAgent->documents()->get()->pluck('id')->toArray(),
        );
        $this->deleteWebsites(
            $aiAgent->websites()->get()->pluck('id')->toArray(),
        );
        $this->detachArticles(
            $aiAgent->articles()->get()->pluck('id')->toArray(),
        );

        // other
        $this->deleteTools($aiAgent->tools()->get()->pluck('id')->toArray());
        $this->deleteFlows($aiAgent->flows()->get()->pluck('id')->toArray());

        $aiAgent->delete();
    }

    public function deleteDocuments(array $documentIds)
    {
        $this->deleteChunkables(
            $documentIds,
            AiAgentDocument::MODEL_TYPE,
            function ($orphanedDocumentIds) {
                $fileEntryIds = DB::table('ai_agent_documents')
                    ->whereIn('id', $orphanedDocumentIds)
                    ->pluck('file_entry_id')
                    ->unique();

                (new PermanentlyDeleteEntries())->execute($fileEntryIds);
            },
        );
    }

    public function deleteWebsites(array $websiteIds)
    {
        $webpageIds = AiAgentWebpage::whereIn(
            'ai_agent_website_id',
            $websiteIds,
        )->pluck('id');

        $this->deleteWebpages($webpageIds->toArray());
        $this->deleteChunkables($websiteIds, AiAgentWebsite::MODEL_TYPE);
    }

    public function deleteWebpages(array $webpageIds)
    {
        $this->deleteChunkables($webpageIds, AiAgentWebpage::MODEL_TYPE);
    }

    public function deleteSnippets(array $snippetIds)
    {
        $this->deleteChunkables($snippetIds, AiAgentSnippet::MODEL_TYPE);
    }

    protected function deleteChunkables(
        array $chunkableIds,
        string $chunkableType,
        callable|null $detachRelationsCallback = null,
    ) {
        DB::transaction(function () use (
            $chunkableIds,
            $chunkableType,
            $detachRelationsCallback,
        ) {
            // detach chunkables from ai agent
            DB::table('ai_agentables')
                ->where('ai_agentable_type', $chunkableType)
                ->whereIn('ai_agentable_id', $chunkableIds)
                ->delete();

            // find chunkables that are still attached to other AI agents
            $stillAttachedChunkableIds = DB::table('ai_agentables')
                ->where('ai_agentable_type', $chunkableType)
                ->whereIn('ai_agentable_id', $chunkableIds)
                ->pluck('ai_agentable_id')
                ->toArray();

            // find chunkables that are no longer attached to any AI agent
            $orphanedChunkableIds = array_diff(
                $chunkableIds,
                $stillAttachedChunkableIds,
            );

            if (!empty($orphanedChunkableIds)) {
                // detach chunks
                DB::table('ai_agent_chunks')
                    ->where('chunkable_type', $chunkableType)
                    ->whereIn('chunkable_id', $orphanedChunkableIds)
                    ->delete();

                // detach tags
                DB::table('taggables')
                    ->where('taggable_type', $chunkableType)
                    ->whereIn('taggable_id', $orphanedChunkableIds)
                    ->delete();

                if ($detachRelationsCallback) {
                    $detachRelationsCallback($orphanedChunkableIds);
                }

                // delete chunkables
                $namespace = modelTypeToNamespace($chunkableType);
                DB::table((new $namespace())->getTable())
                    ->whereIn('id', $orphanedChunkableIds)
                    ->delete();
            }
        });
    }

    // articles are special because they exist outside ai agent context,
    // so we should not delete them or detach tags from them.
    protected function detachArticles(array $articleIds)
    {
        DB::transaction(function () use ($articleIds) {
            // detach articles from ai agent
            DB::table('ai_agentables')
                ->where('ai_agentable_type', HcArticle::MODEL_TYPE)
                ->whereIn('ai_agentable_id', $articleIds)
                ->delete();

            // find articles that are still attached to other AI agents
            $stillAttachedArticleIds = DB::table('ai_agentables')
                ->where('ai_agentable_type', HcArticle::MODEL_TYPE)
                ->whereIn('ai_agentable_id', $articleIds)
                ->pluck('ai_agentable_id')
                ->toArray();

            // find articles that are no longer attached to any AI agent
            $orphanedArticleIds = array_diff(
                $articleIds,
                $stillAttachedArticleIds,
            );

            if (!empty($orphanedArticleIds)) {
                // detach chunks
                DB::table('ai_agent_chunks')
                    ->where('chunkable_type', HcArticle::MODEL_TYPE)
                    ->whereIn('chunkable_id', $orphanedArticleIds)
                    ->delete();
            }
        });
    }

    public function deleteFlows(array $flowIds)
    {
        foreach ($flowIds as $flowId) {
            $flow = AiAgentFlow::find($flowId);
            if ($flow) {
                $flow->attachments()->detach();
                $flow->aiAgents()->detach();
                $flow->delete();
            }
        }
    }

    public function deleteTools(array $toolIds)
    {
        foreach ($toolIds as $toolId) {
            $tool = AiAgentTool::findOrFail($toolId);

            if ($tool) {
                $toolResponseIds = DB::table('tool_responses')
                    ->where('tool_id', $toolId)
                    ->pluck('id');
                DB::table('ai_agent_session_tool_response')
                    ->whereIn('tool_response_id', $toolResponseIds)
                    ->delete();
                DB::table('tool_responses')
                    ->whereIn('tool_id', $toolResponseIds)
                    ->delete();

                $tool->aiAgents()->detach();
                $tool->delete();
            }
        }
    }
}
