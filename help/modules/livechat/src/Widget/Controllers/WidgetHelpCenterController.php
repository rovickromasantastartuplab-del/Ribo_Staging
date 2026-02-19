<?php

namespace Livechat\Widget\Controllers;

use App\HelpCenter\Actions\HcLandingPageLoader;
use App\HelpCenter\Models\HcArticle;
use Common\Core\BaseController;
use App\Core\WidgetFlags;

class WidgetHelpCenterController extends BaseController
{
    public function helpCenterData()
    {
        $data = (new HcLandingPageLoader())->loadData([
            'categoryLimit' => 30,
            'articleLimit' => 50,
            'tag' => WidgetFlags::knowledgeScopeTag(),
        ]);

        return $this->success($data);
    }

    public function homeArticleList()
    {
        $tag = WidgetFlags::knowledgeScopeTag();
        $articles = HcArticle::query()
            ->take(4)
            ->filterByVisibleToRole()
            ->where('draft', false)
            ->orderBy('views', 'desc')
            ->when(
                $tag,
                fn($query) => $query
                    ->whereRelation('tags', 'tags.name', $tag)
                    ->orWhereRelation('categories.tags', 'tags.name', $tag),
            )
            ->get()
            ->loadPath()
            ->map(
                fn(HcArticle $article) => [
                    'id' => $article->id,
                    'title' => $article->title,
                    'slug' => $article->slug,
                    'path' => $article->path,
                ],
            );

        return response()->json([
            'articles' => $articles,
        ]);
    }
}
