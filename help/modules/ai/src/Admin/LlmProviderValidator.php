<?php

namespace Ai\Admin;

use Common\AI\Llm;
use Common\AI\Providers\LlmProvider;
use Common\AI\Providers\ProviderParams;
use Common\Settings\Validators\SettingsValidator;
use Exception;
use GuzzleHttp\Exception\ClientException;

class LlmProviderValidator implements SettingsValidator
{
    const KEYS = [
        'llm_provider',
        'openai_api_key',
        'openai_text_model',
        'anthropic_api_key',
        'anthropic_text_model',
        'gemini_api_key',
        'gemini_text_model',
    ];

    public function fails($values)
    {
        $this->setConfigDynamically($values);

        foreach ($this->getProvidersToCheck($values) as $provider) {
            try {
                Llm::resolveProvider(
                    new ProviderParams(prompt: 'Hello, how are you?'),
                    provider: LlmProvider::from($provider),
                )->generateText();
            } catch (Exception $e) {
                return [
                    'server.llm_provider' => $e->getMessage(),
                ];
            }
        }
    }

    private function setConfigDynamically($settings)
    {
        foreach ($settings as $key => $value) {
            [$providerName, $settingName] = explode('_', $key, 2);
            config()->set("services.$providerName.$settingName", $value);

            if ($settingName === 'api_key') {
                config()->set("prism.providers.$providerName.api_key", $value);
            }
        }
    }

    protected function getProvidersToCheck(array $values): array
    {
        $providers = ['openai', 'anthropic', 'gemini', 'openrouter'];
        $providersToCheck = [];

        foreach ($providers as $provider) {
            foreach ($values as $key => $value) {
                if (str_contains($key, $provider)) {
                    $providersToCheck[] = $provider;
                }
            }
        }

        return array_unique($providersToCheck);
    }
}
