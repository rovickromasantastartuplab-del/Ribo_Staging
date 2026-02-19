<?php

namespace Ai\AiAgent\Conversations;

use Ai\AiAgent\Conversations\Actions\ChatWithLlm;
use Ai\AiAgent\Conversations\Actions\ClassifyUserMessage;
use Ai\AiAgent\Conversations\Actions\MessageCreator;
use Ai\AiAgent\Conversations\Data\ClassifierResponse;
use Ai\AiAgent\Flows\AiAgentFlowExecutor;
use Ai\AiAgent\Models\AiAgent;
use Ai\AiAgent\Models\AiAgentFlow;
use Ai\AiAgent\Tools\ToolBoundToConversation;
use App\Conversations\Agent\Actions\ConversationsAssigner;
use App\Conversations\Models\Conversation;
use App\Conversations\Models\ConversationItem;
use App\Core\WidgetFlags;
use Common\AI\Chat\AssistantMessage;
use Common\AI\Chat\UserMessage;
use Common\AI\Llm;
use Common\AI\Providers\ProviderParams;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use League\HTMLToMarkdown\HtmlConverter;
use Livechat\Streaming\EventEmitter;

class AiAgentBroker
{
    protected readonly MessageCreator $messageCreator;
    protected readonly Collection $messages;
    protected readonly AiAgent $aiAgent;

    public function __construct(protected Conversation $conversation)
    {
        $this->messageCreator = new MessageCreator($this->conversation);

        $this->messages = $this->conversation
            ->items()
            ->orderBy('id', 'desc')
            ->limit(15)
            ->get()
            ->reverse()
            ->values()
            ->map(function (ConversationItem $message) {
                if (
                    $message->type === 'message' &&
                    $message->body &&
                    is_string($message->body)
                ) {
                    $message->body = $this->htmlToMarkdown($message->body);
                }

                return $message;
            });

        $this->aiAgent = AiAgent::getCurrentlyActive();
    }

    public function handleLatestUserMessage()
    {
        // if last message is not by user, bail
        $latestMessage = $this->messages->last();
        if ($latestMessage->author !== Conversation::AUTHOR_USER) {
            return;
        }

        EventEmitter::debug('handlingLatestMessage', [
            'message' => $latestMessage->toArray(),
            'aiAgentId' => $this->aiAgent->id,
        ]);

        if ($this->maybeHandleWithFlow()) {
            return;
        }

        if ($latestMessage->type === 'message') {
            $this->handleWithLlm();
        }
    }

    protected function maybeHandleWithFlow(): bool
    {
        if (
            $this->messages->isNotEmpty() &&
            $this->conversation->aiAgentSession?->isActive()
        ) {
            return (new AiAgentFlowExecutor($this->conversation))
                ->usingMessages($this->messages)
                ->execute()
                ->executedAnyNodes();
        }

        return false;
    }

    protected function handleWithLlm()
    {
        EventEmitter::typing();

        if (
            $this->messages->last()->author !== Conversation::AUTHOR_USER ||
            !$this->messages->last()->body
        ) {
            EventEmitter::error('User message is invalid', [
                'message' => $this->messages->last()->toArray(),
            ]);
            return;
        }

        try {
            $flowsWithIntent = $this->aiAgent
                ->flows()
                ->whereNotNull('intent')
                ->limit(20)
                ->get();

            $response = (new ClassifyUserMessage())->execute(
                $this->getConversationHistory(),
                $flowsWithIntent,
            );

            if ($response->code->isFlowIntent()) {
                $index = $response->code->flowIntentCodeToIndex();
                $flow = $flowsWithIntent[$index] ?? null;
                if ($flow) {
                    $this->triggerFlow($flow);
                }
            } elseif ($response->code->isTransferToHuman()) {
                $this->transferToHuman();
            } else {
                $this->chatWithLlm($response);
            }
        } catch (\Throwable $e) {
            if ($this->aiAgent->getConfig('transfer.type') === 'instruction') {
                $this->messageCreator->genericMessage(
                    __(
                        "Sorry, I'm having some issues. Can you try asking your question again?",
                    ),
                );
            } else {
                $this->messageCreator->genericErrorMessage();
            }

            EventEmitter::error($e->getMessage());
            Log::error($e);
        }
    }

    protected function chatWithLlm(ClassifierResponse $classifierResponse)
    {
        $conversationHistory = $this->getConversationHistory();

        $response = (new ChatWithLlm())->execute(
            $classifierResponse,
            $conversationHistory,
            tools: $this->buildTools(),
            aiAgent: $this->aiAgent,
        );

        $this->messageCreator->genericMessage($response->output);

        if ($classifierResponse->code->isTransferToHuman()) {
            (new ConversationsAssigner())->assignConversationToFirstAvailableAgent(
                $this->conversation,
                addEvent: true,
            );
        }
    }

    protected function transferToHuman()
    {
        if ($this->aiAgent->getConfig('transfer.type') === 'instruction') {
            $response = Llm::resolveProvider(
                new ProviderParams(
                    systemPrompt: 'Classifier has determined that user has asked to speak to human agent. ' .
                        $this->aiAgent->getConfig('transfer.instruction') .
                        '. Match the language and tone of conversation history.',
                    messages: $this->getConversationHistory(),
                ),
            )->generateText();

            $this->messageCreator->genericMessage($response->output);
        } else {
            $response = Llm::resolveProvider(
                new ProviderParams(
                    systemPrompt: 'Classifier has determined that user has asked to speak to human agent. Let user know that you are transferring the conversation to a member of the team. Match the language and tone of conversation history.',
                    messages: $this->getConversationHistory(),
                ),
            )->generateText();

            $this->messageCreator->genericMessage($response->output);

            (new ConversationsAssigner())->assignConversationToFirstAvailableAgent(
                $this->conversation,
                addEvent: true,
            );
        }
    }

    protected function getConversationHistory(): Collection
    {
        return $this->messages
            ->filter(
                fn($message) => $message->type === 'message' &&
                    strlen($message->body) >= 1,
            )
            ->map(
                fn($message) => $message->author === Conversation::AUTHOR_USER
                    ? new UserMessage($message->body)
                    : new AssistantMessage($message->body),
            );
    }

    protected function triggerFlow(int|AiAgentFlow $flow)
    {
        EventEmitter::debug('flowIntentMatched', [
            'flow' => $flow->toArray(),
        ]);

        $executedAnyNodes = (new AiAgentFlowExecutor($this->conversation))
            ->setActiveFlow($flow)
            ->usingMessages($this->messages)
            ->execute()
            ->executedAnyNodes();

        return $executedAnyNodes;
    }

    protected function buildTools(): Collection
    {
        return $this->aiAgent
            ->tools()
            ->whereActive()
            ->where('allow_direct_use', true)
            ->limit(20)
            ->get()
            ->map(
                fn($tool) => new ToolBoundToConversation(
                    $tool,
                    $this->conversation,
                ),
            );
    }

    /**
     * Convert conversation history messages from html to markdown,
     * otherwise model might start responding in html as well.
     */
    protected function htmlToMarkdown(string $body): string
    {
        // no html tags in body
        if ($body === strip_tags($body)) {
            return $body;
        }

        $converter = new HtmlConverter([
            'strip_tags' => true,
            'strip_placeholder_links' => true,
            'header_style' => 'atx',
        ]);

        return $converter->convert($body);
    }
}
