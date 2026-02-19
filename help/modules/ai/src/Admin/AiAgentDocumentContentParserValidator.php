<?php

namespace Ai\Admin;

use Ai\AiAgent\Ingest\Parsing\MarkitdownParser;
use Common\Settings\Validators\SettingsValidator;
use Exception;

class AiAgentDocumentContentParserValidator implements SettingsValidator
{
    const KEYS = ['ai_agent_document_parser'];

    public function fails($values)
    {
        if ($values['ai_agent_document_parser'] === 'basic') {
            return;
        }

        $this->setConfigDynamically($values);

        try {
            (new MarkitdownParser())->parseContent(
                '<html><body><h1>Hello, world!</h1></body></html>',
            );
        } catch (Exception $e) {
            return [
                'server.ai_agent_document_parser' => $e->getMessage(),
            ];
        }
    }

    private function setConfigDynamically($settings)
    {
        config()->set(
            'app.ai_agent_document_parser',
            $settings['ai_agent_document_parser'],
        );
    }
}
