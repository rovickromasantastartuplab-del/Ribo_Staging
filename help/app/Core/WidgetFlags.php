<?php

namespace App\Core;

class WidgetFlags
{
    public static function scopedHcCategoryId(): int|null
    {
        $id = request()->header('X-Scoped-Hc-Category-Id');
        return $id ? (int) $id : null;
    }

    public static function isAiAgentPreviewMode(): bool
    {
        return request()->header('X-Ai-Agent-Preview-Mode') === 'true';
    }

    public static function isLivechatWidget(): bool
    {
        return request()->header('X-Chat-Widget') === 'true' ||
            request()->get('_xChatWidget') === 'true' ||
            str_starts_with(request()->path(), 'lc/widget');
    }

    public static function aiAgentId(): int|null
    {
        $id = request()->header('X-Widget-Ai-Agent-Id') ?? request()->get('xWidgetAiAgentId');
        return $id ? (int) $id : null;
    }

    public static function knowledgeScopeTag(): string|null
    {
        return request()->header('X-Widget-Knowledge-Scope-Tag') ?? request()->get('xWidgetKnowledgeScopeTag');
    }

    public static function conversationId(): int|null
    {
        $id = request()->header('X-Widget-Conversation-Id') ?? request()->get('xWidgetConversationId');
        return $id ? (int) $id : null;
    }

    public static function isMobile(): bool
    {
        $val = request()->header('X-Widget-Is-Mobile') ?? request()->get('xWidgetIsMobile');
        return $val === 'true' || $val === true || $val === '1' || $val === 1;
    }

    public static function flowId(): int|null
    {
        $id = request()->header('X-Widget-Flow-Id') ?? request()->get('xWidgetFlowId');
        return $id ? (int) $id : null;
    }
}
