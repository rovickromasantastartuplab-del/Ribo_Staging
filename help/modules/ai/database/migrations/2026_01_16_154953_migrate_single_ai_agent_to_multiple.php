<?php

use Ai\AiAgent\Models\AiAgent;
use Ai\AiAgent\Models\AiAgentDocument;
use Ai\AiAgent\Models\AiAgentFlow;
use Ai\AiAgent\Models\AiAgentSnippet;
use Ai\AiAgent\Models\AiAgentTool;
use Ai\AiAgent\Models\AiAgentWebpage;
use Ai\AiAgent\Models\AiAgentWebsite;
use App\HelpCenter\Models\HcArticle;
use Common\Settings\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $currentSettings = Setting::where('name', 'aiAgent')->first();

        if ($currentSettings && AiAgent::count() === 0) {
            $agent = AiAgent::create([
                'enabled' => true,
                'config' => $currentSettings->value,
            ]);

            $agent->flows()->sync(AiAgentFlow::all()->pluck('id'));
            $agent->tools()->sync(AiAgentTool::all()->pluck('id'));
            $agent->snippets()->sync(AiAgentSnippet::all()->pluck('id'));
            $agent->websites()->sync(AiAgentWebsite::all()->pluck('id'));
            $agent->webpages()->sync(AiAgentWebpage::all()->pluck('id'));
            $agent->documents()->sync(AiAgentDocument::all()->pluck('id'));

            if (Schema::hasColumn('articles', 'used_by_ai_agent')) {
                $agent
                    ->articles()
                    ->sync(
                        HcArticle::where('used_by_ai_agent', true)
                            ->get()
                            ->pluck('id'),
                    );
            }
        }

        $this->changeMenuItems();
    }

    protected function changeMenuItems()
    {
        $menus = Setting::where('name', 'menus')->first()->value ?? [];

        foreach ($menus as &$menu) {
            if (in_array('dashboard-sidebar', $menu['positions'])) {
                foreach ($menu['items'] as &$item) {
                    if ($item['action'] === '/dashboard/ai-agent') {
                        $item['action'] = '/dashboard/ai-agents';
                    }
                }
            }

            if (in_array('admin-sidebar', $menu['positions'])) {
                foreach ($menu['items'] as &$item) {
                    if ($item['action'] === '/admin/ai-agent') {
                        $item['action'] = '/admin/ai-agents';
                    }
                }
            }
        }

        Setting::where('name', 'menus')->update(['value' => $menus]);
    }
};
