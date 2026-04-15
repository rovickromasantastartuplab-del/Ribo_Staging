<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\AiUsageLog;
use App\Services\AI\AiUsageCostCalculator;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class AiUsageLogSeeder extends Seeder
{
    public function run(): void
    {
        // Ensure we have some companies
        $companies = User::where('type', 'company')->get();
        
        if ($companies->isEmpty()) {
            $this->command->warn('No companies found. Please run UserSeeder first.');
            return;
        }

        $models = ['gpt-4o-mini', 'gpt-4o', 'claude-3-5-sonnet'];
        $features = ['triage', 'draft', 'follow-up'];
        $calculator = app(AiUsageCostCalculator::class);

        // Generate logs for the last 45 days so we have some "older" data too
        for ($i = 45; $i >= 0; $i--) {
            $date = now()->subDays($i);
            
            // Random number of requests per day
            $requestCount = rand(5, 20);
            
            for ($j = 0; $j < $requestCount; $j++) {
                $company = $companies->random();
                $model = $models[array_rand($models)];
                $feature = $features[array_rand($features)];
                
                $promptTokens = rand(100, 2000);
                $completionTokens = rand(50, 1500);
                $totalTokens = $promptTokens + $completionTokens;
                
                $cost = $calculator->calculate($model, $promptTokens, $completionTokens);
                
                AiUsageLog::create([
                    'created_by' => $company->id,
                    'feature' => $feature,
                    'model_version' => $model,
                    'prompt_tokens' => $promptTokens,
                    'completion_tokens' => $completionTokens,
                    'total_tokens' => $totalTokens,
                    'estimated_cost' => $cost,
                    'metadata_json' => [
                        'status' => rand(0, 100) < 5 ? 'failure' : 'success', // 5% failure rate
                        'thread_id' => rand(1000, 9999)
                    ],
                    'requested_at' => $date->copy()->addMinutes(rand(0, 1440)),
                ]);
            }
        }

        $this->command->info('AI Usage Logs seeded successfully.');
    }
}
