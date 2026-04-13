<?php

namespace App\Services\AI\Reports;

class ReportTemplateFormatter
{
    public function format(array $result, array $context, string $scope): array
    {
        $crm = is_array($context['crm'] ?? null) ? $context['crm'] : [];
        $financials = is_array($crm['financials'] ?? null) ? $crm['financials'] : [];
        $opportunities = is_array($crm['opportunities'] ?? null) ? $crm['opportunities'] : [];
        $activityStreams = is_array($context['activity_streams'] ?? null) ? $context['activity_streams'] : [];
        $activityMeta = is_array($activityStreams['meta'] ?? null) ? $activityStreams['meta'] : [];
        $totalIncludedActivity = (int) ($activityMeta['lead_included_count'] ?? 0) + (int) ($activityMeta['opportunity_included_count'] ?? 0);

        $crmRelationships = $this->normalizeRelationships($crm['relationships'] ?? [], $totalIncludedActivity);
        $relationships = count($crmRelationships) > 0
            ? $crmRelationships
            : $this->normalizeRelationships($result['key_relationships'] ?? [], $totalIncludedActivity);

        $engagementSignals = $this->deriveEngagementSignals($result, $activityStreams, $activityMeta);

        $scavengePool = array_merge(
            [$result['summary'] ?? ''],
            $result['executive_insights'] ?? [],
            $result['key_insights'] ?? []
        );

        $risks = $this->normalizeExecutiveInsights($result['key_risks'] ?? []);
        if (count($risks) === 0) {
            $risks = $this->extractRiskBullets($scavengePool);
        }

        $opportunities = $this->normalizeExecutiveInsights($result['growth_opportunities'] ?? []);
        if (count($opportunities) === 0) {
            $opportunities = $this->extractOpportunityBullets($scavengePool);
        }

        return [
            'sections' => [
                ['title' => 'Account Status'],
                ['title' => 'Executive Insights'],
                ['title' => 'Key Relationships'],
                ['title' => 'Deals & Pipeline Snapshot'],
                ['title' => 'Engagement & Health Signals'],
                ['title' => 'Key Risks'],
                ['title' => 'Growth Opportunities'],
                ['title' => 'Recommended Actions (Next 30-60 Days)'],
            ],
            'account_status' => [
                'status' => $this->enum($result['status_value'] ?? ($result['normalized_status'] ?? null), ['Strategic', 'Growth', 'At Risk', 'Stable'], 'Stable'),
                'health' => $this->enum($result['health_score'] ?? ($result['normalized_health_score'] ?? null), ['High', 'Medium', 'Low'], 'Medium'),
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
                    $this->deriveRenewalDate($opportunities),
                ], 'Not available'),
            ],
            'deals' => [
                'active_deals' => $this->firstNonEmptyString([
                    isset($financials['active_deals_count']) ? (string) $financials['active_deals_count'] : null,
                    count($opportunities) > 0 ? (string) count($opportunities) : null,
                ], 'Not available'),
                'top_deal' => $this->firstNonEmptyString([$opportunities[0]['name'] ?? null], 'Not available'),
                'expansion_potential' => $this->deriveExpansionPotential($financials, $opportunities),
                'notable_won' => $this->firstNonEmptyString([
                    $result['notable_deals']['won'] ?? null,
                    $this->deriveNotableDeal($opportunities, ['won', 'closed won', 'success']),
                ], 'Not available'),
                'notable_lost' => $this->firstNonEmptyString([
                    $result['notable_deals']['lost'] ?? null,
                    $this->deriveNotableDeal($opportunities, ['lost', 'closed lost']),
                ], 'Not available'),
                'notable_stalled' => $this->firstNonEmptyString([
                    $result['notable_deals']['stalled'] ?? null,
                    $this->deriveNotableDeal($opportunities, ['stalled', 'stuck', 'hold', 'blocked']),
                ], 'Not available'),
            ],
            'engagement_health_signals' => $engagementSignals,
            'recommended_actions' => $this->buildRoleActions($result),
            'executive_insights' => $this->normalizeExecutiveInsights($result['executive_insights'] ?? ($result['key_insights'] ?? [])),
            'key_relationships' => $relationships,
            'relationship_gaps' => $this->firstNonEmptyString([
                $result['relationship_gaps'] ?? null,
                $this->deriveRelationshipGap($relationships),
            ], 'Not available'),
            'key_risks' => $risks,
            'growth_opportunities' => $opportunities,
            'additional_context' => $this->normalizeStringArray($result['additional_context'] ?? []),
            'scope' => $scope,
        ];
    }

    private function buildRoleActions(array $result): array
    {
        $roleActions = $result['role_based_actions'] ?? [];

        $sales = $this->sanitizeAction($this->firstNonEmptyString([$roleActions['sales'][0] ?? null], 'Review current commercial motion and send targeted follow-up'));
        $csm = $this->sanitizeAction($this->firstNonEmptyString([$roleActions['csm'][0] ?? null], 'Validate relationship health and confirm next stakeholder touchpoint'));
        $support = $this->sanitizeAction($this->firstNonEmptyString([$roleActions['support'][0] ?? null], 'Review open blockers and prepare resolution plan'));
        $exec = $this->sanitizeAction($this->firstNonEmptyString([$roleActions['exec_sponsor'][0] ?? null], 'Engage senior stakeholder on cross-functional value'));

        return [
            "Sales -> {$sales} -> High",
            "CSM -> {$csm} -> High",
            "Support/Product -> {$support} -> Medium",
            "Exec Sponsor -> {$exec} -> Medium",
        ];
    }

    private function enum(mixed $value, array $allowed, string $fallback): string
    {
        $normalized = trim((string) $value);

        return in_array($normalized, $allowed, true) ? $normalized : $fallback;
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
        return $this->normalizeStringArray($value);
    }

    private function normalizeRelationships(mixed $value, int $activityCount = 0): array
    {
        if (!is_array($value)) {
            return [];
        }

        $rows = [];
        foreach ($value as $item) {
            if (is_array($item)) {
                $role = trim((string) ($item['role'] ?? '')) ?: 'Stakeholder';
                $strength = trim((string) ($item['strength'] ?? '')) ?: $this->inferRelationshipStrength($activityCount);
                $rows[] = [
                    'name' => $this->extractCleanName((string) ($item['name'] ?? '')),
                    'role' => $role,
                    'strength' => $this->enum($strength, ['Strong', 'Medium', 'Weak'], 'Medium'),
                ];
                continue;
            }

            $line = trim((string) $item);
            if ($line === '') {
                continue;
            }

            $rows[] = [
                'name' => $this->extractCleanName($line),
                'role' => 'Stakeholder',
                'strength' => $this->inferRelationshipStrength($activityCount),
            ];
        }

        return $rows;
    }

    private function inferRelationshipStrength(int $activityCount): string
    {
        if ($activityCount >= 20) {
            return 'Strong';
        }
        if ($activityCount >= 1) {
            return 'Medium';
        }

        return 'Medium';
    }

    private function extractRiskBullets(array $items): array
    {
        $risks = array_values(array_filter(array_map('strval', $items), static function (string $item): bool {
            return (bool) preg_match('/risk|threat|block|issue|friction|concern|stall|delay|decline|churn|cancel|stopp/i', $item);
        }));

        return count($risks) > 0 ? array_slice($risks, 0, 4) : [];
    }

    private function extractOpportunityBullets(array $items): array
    {
        $opportunities = array_values(array_filter(array_map('strval', $items), static function (string $item): bool {
            return (bool) preg_match('/opportun|expansion|upsell|cross-sell|grow|unlock|interest|demo|referral|potential/i', $item);
        }));

        return count($opportunities) > 0 ? array_slice($opportunities, 0, 4) : [];
    }

    private function deriveRelationshipGap(array $relationships): string
    {
        if (count($relationships) === 0) {
            return 'No mapped stakeholder relationships in CRM context.';
        }

        $hasDecisionMakerRole = collect($relationships)->contains(function (array $row): bool {
            $role = strtolower((string) ($row['role'] ?? ''));
            return (bool) preg_match('/vp|chief|c[- ]?level|director|head|owner|founder|ceo|cto|cfo|coo|president/', $role);
        });

        if (!$hasDecisionMakerRole) {
            return 'No explicit decision-maker relationship is mapped.';
        }

        if (count($relationships) < 2) {
            return 'Relationship map appears single-threaded across stakeholders.';
        }

        return 'Relationship coverage appears multi-threaded.';
    }

    private function deriveExpansionPotential(array $financials, array $opportunities): string
    {
        if (is_numeric($financials['expansion_potential'] ?? null)) {
            return $this->money($financials['expansion_potential']);
        }

        $amounts = array_values(array_filter(array_map(static fn (array $opp): float => (float) ($opp['amount'] ?? 0), $opportunities), static fn (float $amount): bool => $amount > 0));
        if (count($amounts) === 0) {
            return 'Not available';
        }

        rsort($amounts);
        $total = array_sum($amounts);
        $top = $amounts[0];
        $derived = max(0, $total - $top);

        if ($derived <= 0 && $top > 0) {
            $derived = $top * 0.25;
        }

        return $this->money($derived);
    }

    private function deriveRenewalDate(array $opportunities): ?string
    {
        $dates = [];
        foreach ($opportunities as $opportunity) {
            $value = trim((string) ($opportunity['close_date'] ?? ''));
            if ($value === '') {
                continue;
            }
            $dates[] = $value;
        }

        if (count($dates) === 0) {
            return null;
        }

        sort($dates);

        return $dates[0];
    }

    private function deriveNotableDeal(array $opportunities, array $keywords): ?string
    {
        foreach ($opportunities as $opportunity) {
            $haystack = strtolower(implode(' ', [
                (string) ($opportunity['status'] ?? ''),
                (string) ($opportunity['stage'] ?? ''),
                (string) ($opportunity['name'] ?? ''),
            ]));

            foreach ($keywords as $keyword) {
                if (str_contains($haystack, strtolower($keyword))) {
                    return (string) ($opportunity['name'] ?? 'Not available');
                }
            }
        }

        return null;
    }

    private function deriveEngagementSignals(array $result, array $activityStreams, array $activityMeta): array
    {
        $leadIncluded = (int) ($activityMeta['lead_included_count'] ?? 0);
        $oppIncluded = (int) ($activityMeta['opportunity_included_count'] ?? 0);
        $leadScanned = (int) ($activityMeta['lead_scanned_count'] ?? 0);
        $oppScanned = (int) ($activityMeta['opportunity_scanned_count'] ?? 0);
        $totalIncluded = $leadIncluded + $oppIncluded;
        $totalScanned = $leadScanned + $oppScanned;

        $usage = $this->firstNonEmptyString([$result['usage_signal'] ?? null], '');
        $support = $this->firstNonEmptyString([$result['support_signal'] ?? null], '');
        $sentiment = $this->firstNonEmptyString([$result['sentiment_signal'] ?? null], '');
        $engagementPattern = $this->firstNonEmptyString([$result['engagement_pattern'] ?? null], '');

        if ($usage === '') {
            if ($totalIncluded >= 20) {
                $usage = 'High - consistent cross-channel activity is present.';
            } elseif ($totalIncluded >= 8) {
                $usage = 'Medium - active engagement is present with room to improve.';
            } elseif ($totalIncluded > 0) {
                $usage = 'Low-Medium - limited recent activity was detected.';
            } else {
                $usage = 'Low - no recent CRM activity was detected.';
            }
        }

        $support = $this->firstNonEmptyString([$result['support_signal'] ?? null], '');
        if ($support === '') {
            $serialized = strtolower(json_encode($activityStreams, JSON_UNESCAPED_SLASHES) ?: '');
            if ((bool) preg_match('/ticket|support|bug|incident|escalat|error|issue/', $serialized)) {
                $support = 'Issues - support-related activity exists and should be monitored.';
            } else {
                $support = 'Stable - no elevated support friction detected in activity logs.';
            }
        }

        $sentiment = $this->firstNonEmptyString([$result['sentiment_signal'] ?? null], '');
        if ($sentiment === '') {
            $relationshipHealth = strtolower(trim((string) ($result['relationship_health'] ?? '')));
            $sentiment = match ($relationshipHealth) {
                'healthy' => 'Positive - relationship health indicates constructive momentum.',
                'neutral', 'strained' => 'Neutral - relationship quality is mixed and requires monitoring.',
                'damaged' => 'Negative - relationship health suggests elevated commercial risk.',
                default => 'Neutral - direct sentiment signal is limited.',
            };
        }

        $engagementPattern = $this->firstNonEmptyString([$result['engagement_pattern'] ?? null], '');
        if ($engagementPattern === '') {
            if ($totalScanned >= 30) {
                $engagementPattern = 'High responsiveness across channels with sustained follow-through.';
            } elseif ($totalScanned >= 10) {
                $engagementPattern = 'Moderate responsiveness with periodic customer activity.';
            } elseif ($totalScanned > 0) {
                $engagementPattern = 'Light engagement pattern with intermittent activity.';
            } else {
                $engagementPattern = 'Limited engagement pattern due to sparse activity history.';
            }
        }

        return [
            'usage' => $usage,
            'support' => $support,
            'sentiment' => $sentiment,
            'engagement_pattern' => $engagementPattern,
        ];
    }

    private function sanitizeAction(string $action): string
    {
        $normalized = preg_replace('/\s*->\s*(High|Medium|Low)\b/i', '', $action);
        $normalized = preg_replace('/\s*-\s*(High|Medium|Low)\s*priority\.?/i', '', (string) $normalized);
        $normalized = trim((string) $normalized);

        return $normalized !== '' ? $normalized : 'Not available';
    }

    private function extractCleanName(string $raw): string
    {
        $value = trim($raw);
        if ($value === '') {
            return 'Not available';
        }

        // Remove narrative suffixes like " - Primary contact for this account."
        $value = preg_replace('/\s+[-–—]\s+.+$/u', '', $value) ?? $value;
        // Remove bracketed clarifiers like "(Primary contact...)"
        $value = preg_replace('/\s*\(.+\)\s*$/u', '', $value) ?? $value;
        // Remove trailing punctuation artifacts.
        $value = trim((string) preg_replace('/[.,;:\-–—\s]+$/u', '', $value));

        return $value !== '' ? $value : 'Not available';
    }
}
