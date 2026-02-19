<?php

use Common\Settings\Models\Setting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    public function up(): void
    {
        $menus = Setting::where('name', 'menus')->first()->value ?? [];

        foreach ($menus as &$menu) {
            if (in_array('dashboard-sidebar', $menu['positions'])) {
                array_splice($menu['items'], 4, 0, [
                    [
                        'label' => 'AI Agents',
                        'action' => '/dashboard/ai-agents',
                        'type' => 'route',
                        'id' => 'fzfw41',
                        'permissions' => ['ai_agent.update'],
                    ],
                ]);
            }

            if (in_array('admin-sidebar', $menu['positions'])) {
                array_splice($menu['items'], 8, 0, [
                    [
                        'label' => 'AI Agents',
                        'action' => '/admin/ai-agents',
                        'type' => 'route',
                        'id' => 'fzvw42',
                        'permissions' => ['ai_agent.update'],
                    ],
                ]);
            }
        }

        Setting::where('name', 'menus')->update(['value' => $menus]);
    }
};
