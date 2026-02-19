<?php

namespace Ai\Controllers;

use Ai\AiAgent\DeleteAiAgent;
use Ai\AiAgent\Models\AiAgent;
use Common\Core\BaseController;

class AiAgentsController extends BaseController
{
    public function index()
    {
        $this->authorize('update', 'aiAgent');

        $aiAgents = AiAgent::with([
            'flows' => fn($query) => $query->select([
                'ai_agent_flows.id',
                'name',
            ]),
        ])->get();

        return $this->success([
            'aiAgents' => $aiAgents,
        ]);
    }

    public function store()
    {
        $this->authorize('update', 'aiAgent');
        $this->blockOnDemoSite();

        $data = request()->validate([
            'name' => 'required|string|min:3',
        ]);

        $aiAgent = AiAgent::create([
            'enabled' => true,
            'config' => [
                'name' => $data['name'],
                'greetingType' => 'basicGreeting',
                'basicGreeting' => [
                    'message' => 'Hello! How can I help you today?',
                    'flowIds' => [],
                ],
            ],
        ]);

        return $this->success([
            'aiAgent' => $aiAgent,
        ]);
    }

    public function update(int $aiAgentId)
    {
        $this->authorize('update', 'aiAgent');
        $this->blockOnDemoSite();

        $aiAgent = AiAgent::findOrFail($aiAgentId);

        $data = request()->validate([
            'enabled' => 'boolean|nullable',
            'config.name' => 'string|min:3',
            'config.personality' => 'string',
            'config.greetingType' => 'string',
            'config.initialFlowId' => 'integer|nullable',
            'config.basicGreeting' => 'array',
            'config.cantAssist' => 'array',
            'config.image' => 'string|nullable',
            'config.transfer' => 'array',
        ]);

        $aiAgent->enabled = $data['enabled'] ?? $aiAgent->enabled;

        $newConfig = $aiAgent->config;
        foreach ($data['config'] ?? [] as $key => $value) {
            $newConfig[$key] = $value;
        }
        $aiAgent->config = $newConfig;

        $aiAgent->save();

        return $this->success([
            'aiAgent' => $aiAgent,
        ]);
    }

    public function destroy(int $aiAgentId)
    {
        $this->authorize('update', 'aiAgent');
        $this->blockOnDemoSite();

        if (AiAgent::count() === 1) {
            return $this->error(__('At least one AI agent is required'));
        }

        (new DeleteAiAgent())->delete($aiAgentId);

        return $this->success();
    }
}
