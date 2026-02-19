<?php

namespace Livechat\Widget;

use App\Conversations\Models\Conversation;
use Ai\AiAgent\Conversations\AiAgentBroker;
use Ai\AiAgent\Models\AiAgent;
use App\Core\Modules;
use App\Core\WidgetFlags;

class HandleLatestUserMessage
{
    public function __construct(protected Conversation $conversation) {}

    public function execute()
    {
        // if AI agent disabled or conversation is escalated to human, bail
        if (
            !Modules::aiInstalled() ||
            $this->conversation->assigned_to !== Conversation::ASSIGNED_BOT ||
            (!AiAgent::getCurrentlyActive()?->enabled &&
                !WidgetFlags::isAiAgentPreviewMode())
        ) {
            return;
        }

        return (new AiAgentBroker(
            $this->conversation,
        ))->handleLatestUserMessage();
    }
}
