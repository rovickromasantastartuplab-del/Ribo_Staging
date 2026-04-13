<?php

namespace App\Services\AI\Reports;

class ReportTemplateFormatter
{
    public function format(array $result, array $context, string $scope): array
    {
        $crm = is_array($context['crm'] ?? null) ? $context['crm'] : [];
        $financials = is_array($crm['financials'] ?? null) ? $crm['financials'] : [];
        $opportunities = is_array($crm['opportunities'] ?? null) ? $crm['opportunities'] : [];
        $relationships = $this->normalizeRelationships($result['key_relationships'] ?? ($crm['relationships'] ?? []));
        $riskOpportunity = $this->normalizeStringArray($result['risks_and_opportunities'] ?? []);

        return [
            'sections' => [
                ['title' => 'Account Status'],
                ['title' => 'Executive Insights'],
                ['title' => 'Key Relationships'],
                ['title' => 'Deals & Pipeline Snapshot'],
                ['title' => 'Engagement & Health Signals'],
                ['title' => 'Key Risks'],
                ['title' => 'Growth Opportunities'],
                ['title' => 'Recommended Actions (Next 30–60 Days)'],
            ],
            'account_status' => [
                'status' => $this->enum($result['status_value'] ?? null, ['Strategic', 'Growth', 'At Risk', 'Stable'], 'Stable'),
                'health' => $this->enum($result['health_score'] ?? null, ['High', 'Medium', 'Low'], 'Medium'),
                'health_reason' => $this->firstNonEmptyString([
                    $result['account_status_reason'] ?? null,
                    $result['account_status'] ?? null,
                    $result['summary'] ?? null,
                ], 'Not available'),
                'arr' => $this->money($financials['arr'] ?? null),
                'mrr' => $this->money($financials['mrr'] ?? null),
                'renewal' => $this->firstNonEmptyString([
                    $financials['renewal'] ?? null,
                    $financials['renewal_date'] ?? null,
                ], 'Not available'),
            ],
            'deals' => [
                'active_deals' => $this->firstNonEmptyString([
                    isset($financials['active_deals_count']) ? (string) $financials['active_deals_count'] : null,
                    count($opportunities) > 0 ? (string) count($opportunities) : null,
                ], 'Not available'),
                'top_deal' => $this->firstNonEmptyString([$opportunities[0]['name'] ?? null], 'Not available'),
                'expansion_potential' => $this->money($financials['expansion_potential'] ?? null),
                'notable_won' => $this->firstNonEmptyString([$result['notable_deals']['won'] ?? null], 'Not available'),
                'notable_lost' => $this->firstNonEmptyString([$result['notable_deals']['lost'] ?? null], 'Not available'),
                'notable_stalled' => $this->firstNonEmptyString([$result['notable_deals']['stalled'] ?? null], 'Not available'),
            ],
            'engagement_health_signals' => [
                'usage' => $this->firstNonEmptyString([$result['usage_signal'] ?? null], 'Not available'),
                'support' => $this->firstNonEmptyString([$result['support_signal'] ?? null], 'Not available'),
                'sentiment' => $this->firstNonEmptyString([$result['sentiment_signal'] ?? null], 'Not available'),
                'engagement_pattern' => $this->firstNonEmptyString([$result['engagement_pattern'] ?? null], 'Not available'),
            ],
            'recommended_actions' => $this->buildRoleActions($result),
            'executive_insights' => $this->normalizeExecutiveInsights($result['executive_insights'] ?? ($result['key_insights'] ?? [])),
            'key_relationships' => $relationships,
            'relationship_gaps' => $this->firstNonEmptyString([$result['relationship_gaps'] ?? null], 'Not available'),
            'key_risks' => $this->extractRiskBullets($riskOpportunity),
            'growth_opportunities' => $this->extractOpportunityBullets($riskOpportunity),
            'additional_context' => $this->normalizeStringArray($result['additional_context'] ?? []),
            'scope' => $scope,
        ];
    }

    private function buildRoleActions(array $result): array
    {
        $sales = $this->firstNonEmptyString([$result['role_based_actions']['sales'][0] ?? null], 'Not available');
        $csm = $this->firstNonEmptyString([$result['role_based_actions']['csm'][0] ?? null], 'Not available');
        $support = $this->firstNonEmptyString([$result['role_based_actions']['support'][0] ?? null], 'Not available');
        $exec = $this->firstNonEmptyString([$result['role_based_actions']['exec_sponsor'][0] ?? null], 'Not available');

        return [
            "Sales -> {$sales} -> High",
            "CSM -> {$csm} -> High",
            "Support/Product -> {$support} -> Medium",
            "Exec Sponsor -> {$exec} -> Medium",
        ];
    }

    private function enum(?string $value, array $allowed, string $fallback): string
    {
        return in_array($value, $allowed, true) ? $value : $fallback;
    }

    private function money(mixed $value): string
    {
        if (!is_numeric($value)) {
            return 'Not available';
        }

        return '$' . number_format((float) $value, 2);
    }

    private function normalizeStringArray(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn ($item): string => trim((string) $item),
            $value
        ), static fn (string $item): bool => $item !== ''));
    }

    private function firstNonEmptyString(array $candidates, string $fallback): string
    {
        foreach ($candidates as $candidate) {
            $value = trim((string) $candidate);
            if ($value !== '') {
                return $value;
            }
        }

        return $fallback;
    }

    private function normalizeExecutiveInsights(mixed $value): array
    {
        $insights = array_slice($this->normalizeStringArray($value), 0, 5);

        if (count($insights) < 3) {
            $insights = array_pad($insights, 3, 'Not available');
        }

        return $insights;
    }

    private function normalizeRelationships(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $rows = [];
        foreach ($value as $item) {
            if (is_array($item)) {
                $rows[] = [
                    'name' => trim((string) ($item['name'] ?? '')) ?: 'Not available',
                    'role' => trim((string) ($item['role'] ?? '')) ?: 'Not available',
                    'type' => trim((string) ($item['type'] ?? '')) ?: 'Not available',
                    'strength' => trim((string) ($item['strength'] ?? '')) ?: 'Not available',
                ];
                continue;
            }

            $line = trim((string) $item);
            if ($line === '') {
                continue;
            }

            $rows[] = [
                'name' => $line,
                'role' => 'Not available',
                'type' => 'Not available',
                'strength' => 'Not available',
            ];
        }

        return $rows;
    }

    private function extractRiskBullets(array $items): array
    {
        $risks = array_values(array_filter($items, static function (string $item): bool {
            return (bool) preg_match('/risk|threat|block|issue|friction|concern|stall/i', $item);
        }));

        return count($risks) > 0 ? array_slice($risks, 0, 4) : ['Not available'];
    }

    private function extractOpportunityBullets(array $items): array
    {
        $opportunities = array_values(array_filter($items, static function (string $item): bool {
            return (bool) preg_match('/opportun|expansion|upsell|cross-sell|grow|unlock/i', $item);
        }));

        return count($opportunities) > 0 ? array_slice($opportunities, 0, 4) : ['Not available'];
    }
}
