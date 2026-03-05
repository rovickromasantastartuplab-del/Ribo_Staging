<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\LeadEvent;
use App\Models\Setting;
use App\Models\AiClassificationResult;
use Illuminate\Support\Facades\Log;
use OpenAI;

class ClassifyLeadIntentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $leadEventId;

    public function __construct($leadEventId)
    {
        $this->leadEventId = $leadEventId;
    }

    public function handle()
    {
        $event = LeadEvent::with(['lead', 'contact'])->find($this->leadEventId);
        if (!$event || empty($event->summary_text)) {
            return;
        }

        $apiKey = Setting::where('key', 'chatgptKey')->value('value');
        if (!$apiKey) {
            Log::warning('Omnichannel AI: ChatGPT API Key not configured. Cannot classify LeadEvent ' . $event->id);
            return; // Skip classification if no API key
        }

        $model = Setting::where('key', 'chatgptModel')->value('value') ?? 'gpt-3.5-turbo';

        // Fetch active Opportunity Stages logically out of settings (assuming we have stages in DB)
        $stages = \App\Models\OpportunityStage::pluck('name', 'id')->toArray();
        $stagesJson = json_encode($stages);

        $systemPrompt = "You are an AI sales assistant. Your job is to classify the intent of an inbound lead inquiry.
Here are the current sales pipeline stages available in our CRM: $stagesJson
Read the user's message and return a pure JSON object (no markdown) with this schema:
{
    \"suggested_stage_id\": <integer of best matching stage from the list, or null if none match>,
    \"confidence_score\": <integer from 0 to 100>,
    \"reasons\": [\"reason 1\", \"reason 2\"]
}";

        $client = OpenAI::client($apiKey);

        try {
            $response = $client->chat()->create([
                'model' => $model,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $event->summary_text]
                ],
                // Forcing JSON if supported by model, otherwise instructions suffice
                'response_format' => ['type' => 'json_object'],
                'temperature' => 0.2, // Low temp for more deterministic classification
            ]);

            $content = $response->choices[0]->message->content;
            $data = json_decode($content, true);

            if ($data && isset($data['confidence_score'])) {
                // Save Result
                $classification = AiClassificationResult::create([
                    'lead_event_id' => $event->id,
                    'suggested_stage_label' => $data['reasons'][0] ?? 'Unclassified',
                    'mapped_stage_id' => $data['suggested_stage_id'] ?? null,
                    'confidence_score' => $data['confidence_score'],
                    'reasons_json' => $data['reasons'] ?? [],
                    'model_version' => $model,
                ]);

                // Update Lead
                $event->lead->update([
                    'ai_suggested_stage_id' => $classification->mapped_stage_id,
                    'ai_confidence_score' => $classification->confidence_score,
                    'ai_last_classified_at' => now(),
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Omnichannel AI Classification Error: ' . $e->getMessage());
        }
    }
}
