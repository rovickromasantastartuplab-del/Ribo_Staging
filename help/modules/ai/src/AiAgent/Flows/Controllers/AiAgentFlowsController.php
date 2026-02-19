<?php

namespace Ai\AiAgent\Flows\Controllers;

use Ai\AiAgent\DeleteAiAgent;
use Ai\AiAgent\Models\AiAgent;
use Ai\AiAgent\Models\AiAgentFlow;
use Common\Core\BaseController;
use Common\Database\Datasource\Datasource;

class AiAgentFlowsController extends BaseController
{
    public function index()
    {
        $this->authorize('update', 'aiAgent');

        $aiAgent = AiAgent::findOrFail(request('aiAgentId'));

        $datasource = new Datasource(
            $aiAgent
                ->flows()
                ->select([
                    'ai_agent_flows.id',
                    'name',
                    'intent',
                    'activation_count',
                    'ai_agent_flows.updated_at',
                    'ai_agent_flows.created_at',
                ]),
            request()->all(),
        );

        return $this->success([
            'pagination' => $datasource->paginate(),
        ]);
    }

    public function show(int $flowId)
    {
        $this->authorize('update', 'aiAgent');

        $flow = AiAgentFlow::findOrFail($flowId);

        return $this->success([
            'flow' => $flow,
        ]);
    }

    public function store()
    {
        $this->authorize('update', 'aiAgent');
        $this->blockOnDemoSite();

        $data = request()->validate([
            'name' => 'required|string',
            'intent' => 'string',
            'ai_agent_id' => 'integer',
        ]);

        $aiAgent = AiAgent::findOrFail($data['ai_agent_id']);

        $flow = $aiAgent->flows()->create([
            'name' => $data['name'],
            'intent' => $data['intent'] ?? null,
            'config' => [],
        ]);

        $flow->syncAttachments();

        return $this->success([
            'flow' => $flow,
        ]);
    }

    public function update(int $flowId)
    {
        $this->authorize('update', 'aiAgent');
        $this->blockOnDemoSite();

        $data = request()->validate([
            'name' => 'string',
            'intent' => 'string|nullable',
            'config' => 'array',
        ]);

        $flow = AiAgentFlow::findOrFail($flowId);

        $flow->update($data);
        $flow->syncAttachments();

        return $this->success([
            'flow' => $flow,
        ]);
    }

    public function destroy(int $id)
    {
        $this->authorize('update', 'aiAgent');
        $this->blockOnDemoSite();

        (new DeleteAiAgent())->deleteFlows([$id]);

        return $this->success();
    }

    public function list()
    {
        $this->authorize('update', 'aiAgent');

        $aiAgent = AiAgent::findOrFail(request('aiAgentId'));

        return $this->success([
            'flows' => $aiAgent->flows()->limit(50)->get()->map(
                fn(AiAgentFlow $flow) => [
                    'id' => $flow->id,
                    'name' => $flow->name,
                ],
            ),
        ]);
    }
}
