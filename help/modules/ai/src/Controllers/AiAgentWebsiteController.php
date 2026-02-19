<?php

namespace Ai\Controllers;

use Ai\AiAgent\DeleteAiAgent;
use Ai\AiAgent\Ingest\Web\WebsiteIngester;
use Ai\AiAgent\Models\AiAgent;
use Ai\AiAgent\Models\AiAgentWebpage;
use Ai\AiAgent\Models\AiAgentWebsite;
use Common\Core\BaseController;
use Common\Database\Datasource\Datasource;
use Illuminate\Support\Facades\DB;

class AiAgentWebsiteController extends BaseController
{
    public function index()
    {
        $this->authorize('update', 'aiAgent');

        $aiAgent = AiAgent::findOrFail(request('aiAgentId'));

        $datasource = new Datasource(
            $aiAgent->websites()->with('tags'),
            request()->all(),
        );

        $pagination = $datasource->paginate()->through(
            fn(AiAgentWebsite $website) => [
                'id' => $website->id,
                'title' => $website->title,
                'url' => $website->url,
                'tags' => $website->tags->pluck('name'),
                'scan_pending' => $website->scan_pending,
                'updated_at' => $website->updated_at,
            ],
        );

        return $this->success([
            'pagination' => $pagination,
        ]);
    }

    public function indexPages(int $websiteId)
    {
        $this->authorize('update', 'aiAgent');

        $website = AiAgentWebsite::findOrFail($websiteId);

        $datasource = new Datasource(
            $website->webpages()->with('tags')->selectAllExceptContent(),
            request()->all(),
        );

        $pagination = $datasource->paginate()->through(
            fn(AiAgentWebpage $webpage) => [
                'id' => $webpage->id,
                'title' => $webpage->title,
                'url' => $webpage->url,
                'tags' => $webpage->tags->pluck('name'),
                'fully_scanned' => $webpage->fully_scanned,
                'scan_pending' => $webpage->scan_pending,
                'updated_at' => $webpage->updated_at,
                'ai_agent_website_id' => $webpage->ai_agent_website_id,
            ],
        );

        return $this->success([
            'pagination' => $pagination,
            'website' => $website,
        ]);
    }

    public function showPage(int $websiteId, int $webpageId)
    {
        $this->authorize('update', 'aiAgent');

        $website = AiAgentWebsite::findOrFail($websiteId);
        $webpage = $website->webpages()->findOrFail($webpageId);

        return $this->success([
            'website' => $website,
            'webpage' => $webpage,
        ]);
    }

    public function store()
    {
        $this->authorize('update', 'aiAgent');
        $this->blockOnDemoSite();

        $data = $this->validate(request(), [
            'url' => 'required|url',
            'scanType' => 'in:full,nested,single',
            'scrapeConfig' => 'array',
            'aiAgentId' => 'required|exists:ai_agents,id',
        ]);

        $aiAgent = AiAgent::findOrFail($data['aiAgentId']);

        (new WebsiteIngester())->startWebsiteIngest(
            $aiAgent,
            $data['url'],
            $data['scanType'],
            $data['scrapeConfig'] ?? [],
        );

        return $this->success();
    }

    public function destroyWebsite(int $websiteId)
    {
        $this->authorize('update', 'aiAgent');
        $this->blockOnDemoSite();

        (new DeleteAiAgent())->deleteWebsites([$websiteId]);

        return $this->success();
    }

    public function destroyPage(int $websiteId, string $webpageIds)
    {
        $this->authorize('update', 'aiAgent');
        $this->blockOnDemoSite();

        $count = AiAgentWebpage::where(
            'ai_agent_website_id',
            $websiteId,
        )->count();

        if ($count === 1) {
            return $this->error('Website must have at least one page');
        }

        (new DeleteAiAgent())->deleteWebpages(explode(',', $webpageIds));

        return $this->success();
    }

    public function syncPageContent(int $webpageId)
    {
        $this->authorize('update', 'aiAgent');
        $this->blockOnDemoSite();

        $webpage = AiAgentWebpage::findOrFail($webpageId);

        (new WebsiteIngester())->syncExistingWebpage($webpage);

        return $this->success();
    }

    public function syncWebsite(int $websiteId)
    {
        $this->authorize('update', 'aiAgent');
        $this->blockOnDemoSite();

        $data = $this->validate(request(), [
            'aiAgentId' => 'required|exists:ai_agents,id',
        ]);

        $aiAgent = AiAgent::findOrFail($data['aiAgentId']);

        $website = AiAgentWebsite::findOrFail($websiteId);
        (new WebsiteIngester())->startWebsiteIngest(
            $aiAgent,
            $website->url,
            $website->scan_type,
        );

        return $this->success();
    }
}
