<?php

use App\Conversations\Models\Conversation;
use App\HelpCenter\Models\HcArticle;
use App\Models\User;
use Common\Tags\Tag;

return [
    'meilisearch' => [
        'index-settings' => [
            HcArticle::class => [],
            User::class => [],
            Tag::class => [],
            Conversation::class => [
                'stopWords' => ['the', 'a', 'an'],
                'rankingRules' => [
                    'updated_at:desc',
                    'words',
                    'typo',
                    'proximity',
                    'attribute',
                    'exactness',
                ],
            ],
        ],
    ],
];
