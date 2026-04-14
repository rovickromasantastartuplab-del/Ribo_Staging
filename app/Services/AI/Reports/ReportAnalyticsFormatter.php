<?php

namespace App\Services\AI\Reports;

use Carbon\Carbon;

class ReportAnalyticsFormatter
{
    public function build(array $input): array
    {
        $timeline = is_array($input['timeline'] ?? null) ? $input['timeline'] : [];
        $events = is_array($input['events'] ?? null) ? $input['events'] : [];

        $windowDays = $this->resolveWindowDays($timeline);
        $dates = $this->buildDateRange($windowDays);
        $indexed = $this->indexTimeline($timeline);

        $labels = array_map(
            static fn (string $date): string => Carbon::parse($date)->format('M d'),
            $dates
        );

        $interactionSeries = [];
        $responseTimeSeries = [];
        $relationshipStrengthSeries = [];
        $inactivityGapSeries = [];
        $sentimentSeries = [];

        foreach ($dates as $date) {
            $row = $indexed[$date] ?? [];

            $emails = (float) ($row['emails'] ?? 0);
            $replies = (float) ($row['replies'] ?? 0);
            $meetings = (float) ($row['meetings'] ?? 0);

            $interactionSeries[] = $emails + $replies + $meetings;
            $responseTimeSeries[] = (float) ($row['response_hours'] ?? 0);
            $relationshipStrengthSeries[] = $this->mapStrength((string) ($row['strength'] ?? 'Medium'));
            $inactivityGapSeries[] = (float) ($row['inactivity_days'] ?? 0);
            $sentimentSeries[] = $this->mapSentiment((string) ($row['sentiment'] ?? 'Neutral'));
        }

        $takeaways = $this->buildTakeaways($interactionSeries, $responseTimeSeries, $inactivityGapSeries);

        return [
            'window_days' => $windowDays,
            'date_labels' => $labels,
            'graphs' => [
                [
                    'id' => 'interaction_volume',
                    'title' => 'Interaction volume over time',
                    'series' => $interactionSeries,
                ],
                [
                    'id' => 'response_time',
                    'title' => 'Average response time trend',
                    'series' => $responseTimeSeries,
                ],
                [
                    'id' => 'relationship_strength',
                    'title' => 'Relationship strength trend (Strong/Medium/Weak)',
                    'series' => $relationshipStrengthSeries,
                ],
                [
                    'id' => 'inactivity_gap',
                    'title' => 'Inactivity gaps (days since last meaningful touch)',
                    'series' => $inactivityGapSeries,
                ],
                [
                    'id' => 'sentiment_trend',
                    'title' => 'Sentiment trend (positive/neutral/negative)',
                    'series' => $sentimentSeries,
                ],
            ],
            'event_markers' => $this->normalizeEvents($events),
            'top_takeaways' => $takeaways,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $timeline
     */
    private function resolveWindowDays(array $timeline): int
    {
        if (count($timeline) >= 75) {
            return 90;
        }

        if (count($timeline) >= 40) {
            return 60;
        }

        return 30;
    }

    /**
     * @return array<int, string>
     */
    private function buildDateRange(int $windowDays): array
    {
        $start = Carbon::now()->startOfDay()->subDays($windowDays - 1);
        $range = [];

        for ($i = 0; $i < $windowDays; $i++) {
            $range[] = $start->copy()->addDays($i)->toDateString();
        }

        return $range;
    }

    /**
     * @param  array<int, array<string, mixed>>  $timeline
     * @return array<string, array<string, mixed>>
     */
    private function indexTimeline(array $timeline): array
    {
        $indexed = [];
        foreach ($timeline as $row) {
            if (!is_array($row)) {
                continue;
            }

            $date = trim((string) ($row['date'] ?? ''));
            if ($date === '') {
                continue;
            }

            $indexed[$date] = $row;
        }

        return $indexed;
    }

    private function mapStrength(string $value): float
    {
        return match (strtolower(trim($value))) {
            'strong' => 3.0,
            'weak' => 1.0,
            default => 2.0,
        };
    }

    private function mapSentiment(string $value): float
    {
        return match (strtolower(trim($value))) {
            'positive' => 1.0,
            'negative' => -1.0,
            default => 0.0,
        };
    }

    /**
     * @param  array<int, float>  $interactionSeries
     * @param  array<int, float>  $responseSeries
     * @param  array<int, float>  $gapSeries
     * @return array<int, string>
     */
    private function buildTakeaways(array $interactionSeries, array $responseSeries, array $gapSeries): array
    {
        $totalInteractions = array_sum($interactionSeries);
        $avgResponse = count($responseSeries) > 0
            ? round(array_sum($responseSeries) / count($responseSeries), 1)
            : 0.0;
        $maxGap = count($gapSeries) > 0 ? (int) max($gapSeries) : 0;

        if ($totalInteractions <= 0 && $avgResponse <= 0 && $maxGap <= 0) {
            return [
                'Not available: activity trend is too sparse for a reliable momentum signal.',
                'Not available: response-time trend could not be derived from available data.',
                'Not available: inactivity risk requires additional timeline coverage.',
            ];
        }

        return [
            "Interaction momentum: {$totalInteractions} total touchpoints in selected window.",
            "Responsiveness trend: average response time is {$avgResponse} hours.",
            "Risk watch: longest inactivity gap in window is {$maxGap} days.",
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $events
     * @return array<int, array<string, string>>
     */
    private function normalizeEvents(array $events): array
    {
        $normalized = [];

        foreach ($events as $event) {
            if (!is_array($event)) {
                continue;
            }

            $label = trim((string) ($event['label'] ?? ''));
            $date = trim((string) ($event['date'] ?? ''));

            if ($label === '' || $date === '') {
                continue;
            }

            $normalized[] = [
                'label' => $label,
                'date' => Carbon::parse($date)->format('M d'),
            ];
        }

        return $normalized;
    }
}

