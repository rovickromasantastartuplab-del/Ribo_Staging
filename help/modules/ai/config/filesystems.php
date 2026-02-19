<?php

use Ai\AiAgent\Ingest\Files\AiAgentDocumentUploadHandler;

return [
    'upload_types' => [
        'aiDocuments' => [
            'visibility' => 'private',
            'label' => 'AI agent documents',
            'description' => 'Documents uploaded in AI agent knowledge page.',
            'handler' => AiAgentDocumentUploadHandler::class,
            'defaults' => [
                'max_file_size' => '41943040', //40mb
                'accept' => [
                    '.pdf',
                    '.doc',
                    '.docx',
                    '.ppt',
                    '.pptx',
                    '.xls',
                    '.xlsx',
                    '.csv',
                    '.json',
                    '.xml',
                    '.txt',
                ],
            ],
        ],
    ],
];
