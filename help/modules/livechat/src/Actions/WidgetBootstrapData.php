<?php

namespace Livechat\Actions;

use Ai\AiAgent\Models\AiAgent;
use App\Core\Modules;
use App\Core\WidgetFlags;
use Common\Settings\Themes\CssTheme;
use Common\Websockets\GetWebsocketCredentialsForClient;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use App\Team\LoadAllCompactAgents;
use Common\Core\AppUrl;
use Common\Core\Bootstrap\BaseBootstrapData;
use Livechat\Chats\BuildNewChatGreeting;
use Livechat\Widget\Users\WidgetCustomerResource;
use Livechat\Widget\WidgetConversationLoader;

class WidgetBootstrapData extends BaseBootstrapData
{
    public array $data = [];
    public ?CssTheme $initialTheme = null;

    public function __construct()
    {
        $this->initData();
    }

    protected function initData(): void
    {
        $conversationId = WidgetFlags::conversationId();
        $knowledgeScopeTag = WidgetFlags::knowledgeScopeTag();
        $aiAgent = Modules::aiInstalled()
            ? AiAgent::getCurrentlyActive()
            : null;

        $customer = Auth::user();
        $activeConversation = (new WidgetConversationLoader())->activeConversationFor(
            $customer,
            $conversationId,
        );

        $settings = settings()->getUnflattened();
        $settings['base_url'] = config('app.url');
        $settings['html_base_uri'] = app(AppUrl::class)->htmlBaseUri;
        $settings[
            'broadcasting'
        ] = (new GetWebsocketCredentialsForClient())->execute();

        $this->data = [
            'themes' => $this->getThemes(),
            'activeConversationData' => $activeConversation,
            'user' => new WidgetCustomerResource($customer),
            'guest_role' => app('guestRole')?->load('permissions'),
            'settings' => $settings,
            'agents' => (new LoadAllCompactAgents())->execute(),
            'sessionId' => Str::uuid(),
            'isMobile' => WidgetFlags::isMobile(),
            'newChatGreeting' => (new BuildNewChatGreeting(
                $customer,
                flowId: WidgetFlags::flowId(),
                aiAgent: $aiAgent,
            ))->execute(),
            'csrf_token' => csrf_token(),
            'knowledgeScopeTag' => $knowledgeScopeTag,
            'aiAgent' => $aiAgent
                ? [
                    'id' => $aiAgent->id,
                    'name' => $aiAgent->config['name'] ?? 'AI assistant',
                    'image' => $aiAgent->config['image'] ?? null,
                    'enabled' => $aiAgent->enabled,
                ]
                : null,
        ];

        $this->setLocalizationData();
        $this->setInitialTheme();
        $this->setUploadingTypes();

        if ($this->data['user']) {
            $this->data['user']->createOrTouchSession();
        }
    }

    public function getThemes(): Collection
    {
        $themes = CssTheme::query()
            ->where(
                'type',
                settings('chatWidget.inheritThemes') ? 'site' : 'chatWidget',
            )
            ->where(function (Builder $builder) {
                $builder
                    ->where('default_dark', true)
                    ->orWhere('default_light', true);
            })
            ->get();

        if ($themes->isEmpty()) {
            $themes = CssTheme::query()->limit(2)->get();
        }

        return $themes;
    }

    protected function setInitialTheme(): void
    {
        $themes = $this->data['themes'];

        if ($defaultTheme = settings('chatWidget.defaultTheme')) {
            // when default theme is set to system, use light theme
            // initially as there's no way to get user's preference
            // without javascript. Correct theme variables will be set once front end loads.
            if ($defaultTheme === 'system' || $defaultTheme === 'light') {
                $this->initialTheme = $themes
                    ->where('default_light', true)
                    ->first();
            } else {
                $this->initialTheme = $themes
                    ->where('default_dark', true)
                    ->first();
            }
        }

        // finally, fallback to default light theme
        if (!$this->initialTheme) {
            $this->initialTheme =
                $themes->where('default_light', true)->first() ??
                $themes->first();
        }
    }
}
