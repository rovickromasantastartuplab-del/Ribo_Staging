<?php

namespace App\Services\AI;

class AiUsageCostCalculator
{
    /**
     * Pricing per 1,000,000 tokens (USD)
     * [input_price, output_price]
     */
    protected array $pricing = [
        'gpt-4o' => [2.50, 10.00],
        'gpt-4o-mini' => [0.15, 0.60],
        'gpt-3.5-turbo' => [0.50, 1.50],
        'claude-3-5-sonnet' => [3.00, 15.00],
        'claude-3-haiku' => [0.25, 1.25],
        'gemini-1.5-pro' => [3.50, 10.50],
        'gemini-1.5-flash' => [0.075, 0.30],
    ];

    /**
     * Calculate estimated cost for an AI request.
     */
    public function calculate(?string $model, int $promptTokens, int $completionTokens): float
    {
        if (!$model) {
            return 0;
        }

        // Find best match for model pricing
        $priceKey = collect(array_keys($this->pricing))->first(function ($key) use ($model) {
            return str_contains(strtolower($model), $key);
        });

        $prices = $this->pricing[$priceKey] ?? $this->pricing['gpt-4o-mini'];

        $inputCost = ($promptTokens / 1000000) * $prices[0];
        $outputCost = ($completionTokens / 1000000) * $prices[1];

        return round($inputCost + $outputCost, 6);
    }
}
