<?php

use Ai\AiAgent\Models\AiAgentChunk;
use Ai\AiAgent\Models\AiAgentDocument;
use Ai\AiAgent\Models\AiAgentFlow;
use Ai\AiAgent\Models\AiAgentSnippet;
use Ai\AiAgent\Models\AiAgentTool;
use Ai\AiAgent\Models\AiAgentWebpage;
use Ai\AiAgent\Models\AiAgentWebsite;
use App\Conversations\Models\Conversation;
use App\HelpCenter\Models\HcArticle;
use App\Models\User;

return [
    'meilisearch' => [
        'index-settings' => [
            AiAgentChunk::class => [
                'displayedAttributes' => [
                    'id',
                    '_vectors',
                    'content',
                    'parent_chunk_id',
                    'tags',
                ],
                'embedders' => [
                    AiAgentChunk::MODEL_TYPE => [
                        'source' => 'userProvided',
                        'dimensions' => 1536,
                    ],
                ],
            ],
        ],
    ],
    'mysql' => [
        'index-settings' => [
            AiAgentWebsite::class => [],
            AiAgentWebpage::class => [],
            AiAgentDocument::class => [],
            AiAgentSnippet::class => [],
            AiAgentFlow::class => [],
            AiAgentTool::class => [],
        ],
    ],
];
