<?php

namespace App\Services\AI\Reports;

class ActivityStreamDigestBuilder
{
    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    public function build(array $items): array
    {
        $types = [];
        $timestamps = [];
        $support = 0;
        $risk = 0;
        $positive = 0;
        $riskSnippets = [];
        $positiveSnippets = [];

        foreach ($items as $item) {
            $type = strtolower(trim((string) ($item['activity_type'] ?? 'unknown')));
            $types[$type] = ($types[$type] ?? 0) + 1;

            $createdAt = trim((string) ($item['created_at'] ?? ''));
            if ($createdAt !== '') {
                $timestamps[] = $createdAt;
            }

            $rawTitle = trim((string) ($item['title'] ?? ''));
            $rawDesc = trim((string) ($item['description'] ?? ''));
            $displayText = $rawTitle . ($rawDesc !== '' ? ": {$rawDesc}" : "");
            $text = strtolower($displayText);

            if ($text !== '') {
                if ((bool) preg_match('/support|ticket|incident|bug|error|escalat|issue/', $text)) {
                    $support++;
                }
                
                if ((bool) preg_match('/risk|block|delay|stalled|churn|friction|concern|lost/', $text)) {
                    $risk++;
                    if (count($riskSnippets) < 10) {
                        $riskSnippets[] = "[{$createdAt}] {$displayText}";
                    }
                }
                
                if ((bool) preg_match('/positive|win|progress|momentum|expansion|upsell|success|closed won/', $text)) {
                    $positive++;
                    if (count($positiveSnippets) < 10) {
                        $positiveSnippets[] = "[{$createdAt}] {$displayText}";
                    }
                }
            }
        }

        sort($timestamps);

        return [
            'total_count' => count($items),
            'by_activity_type' => $types,
            'support_event_count' => $support,
            'risk_event_count' => $risk,
            'positive_event_count' => $positive,
            'oldest_at' => $timestamps[0] ?? null,
            'latest_at' => $timestamps[count($timestamps) - 1] ?? null,
            'significant_risk_events' => array_slice($riskSnippets, 0, 8),
            'significant_positive_events' => array_slice($positiveSnippets, 0, 8),
        ];
    }
}
