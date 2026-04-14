<?php

namespace App\Services\AI\Reports;

class ReportTemplateFormatter
{
    public function __construct(
        private readonly ReportAnalyticsFormatter $analyticsFormatter
    ) {
    }

    public function format(array $result, array $context, string $scope): array
    {
        $crm = is_array($context['crm'] ?? null) ? $context['crm'] : [];
        $financials = is_array($crm['financials'] ?? null) ? $crm['financials'] : [];
        $crmOpportunities = is_array($crm['opportunities'] ?? null) ? $crm['opportunities'] : [];
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
            (array) ($result['executive_insights'] ?? []),
            (array) ($result['key_insights'] ?? [])
        );

        $aiRisks = $this->normalizeExecutiveInsights($result['key_risks'] ?? []);
        if (count($aiRisks) === 0) {
            $aiRisks = $this->extractRiskBullets($scavengePool);
        }

        $aiOpportunities = $this->normalizeExecutiveInsights($result['growth_opportunities'] ?? []);
        if (count($aiOpportunities) === 0) {
            $aiOpportunities = $this->extractOpportunityBullets($scavengePool);
        }

        $analytics = $this->analyticsFormatter->build([
            'timeline' => $this->extractTimelineRows($activityStreams),
            'events' => $this->extractEventMarkers($result, $context),
        ]);

        return [
            'sections' => [
                ['title' => 'Client Account Snapshot'],
                ['title' => 'Account Status'],
                ['title' => 'Executive Insights'],
                ['title' => 'Key Relationships'],
                ['title' => 'Deals & Pipeline Snapshot'],
                ['title' => 'Engagement & Health Signals'],
                ['title' => 'Key Risks'],
                ['title' => 'Growth Opportunities'],
                ['title' => 'Recommended Actions (Next 30-60 Days)'],
            ],
            'client_account_snapshot' => [
                'account_name' => (string) ($crm['account']['name'] ?? 'Unassigned Account'),
                'scope' => $scope,
            ],
            'analytics' => $analytics,
            'account_status' => [
                'status' => $this->enum(
                    $result['status_value'] ?? null,
                    ['Strategic', 'Growth', 'At Risk', 'Stable'],
                    $this->enum($result['normalized_status'] ?? null, ['Strategic', 'Growth', 'At Risk', 'Stable'], 'Stable')
                ),
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
                    $this->deriveRenewalDate($crmOpportunities),
                ], 'Not available'),
            ],
            'deals' => [
                'active_deals' => $this->firstNonEmptyString([
                    isset($financials['active_deals_count']) ? (string) $financials['active_deals_count'] : null,
                    count($crmOpportunities) > 0 ? (string) count($crmOpportunities) : null,
                ], 'Not available'),
                'top_deal' => $this->firstNonEmptyString([$crmOpportunities[0]['name'] ?? null], 'Not available'),
                'expansion_potential' => $this->deriveExpansionPotential($financials, $crmOpportunities),
                'notable_won' => $this->firstNonEmptyString([
                    $result['notable_deals']['won'] ?? null,
                    $this->deriveNotableDeal($crmOpportunities, ['won', 'closed won', 'success']),
                ], 'Not available'),
                'notable_lost' => $this->firstNonEmptyString([
                    $result['notable_deals']['lost'] ?? null,
                    $this->deriveNotableDeal($crmOpportunities, ['lost', 'closed lost']),
                ], 'Not available'),
                'notable_stalled' => $this->firstNonEmptyString([
                    $result['notable_deals']['stalled'] ?? null,
                    $this->deriveNotableDeal($crmOpportunities, ['stalled', 'stuck', 'hold', 'blocked']),
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
            'key_risks' => $aiRisks,
            'growth_opportunities' => $aiOpportunities,
            'additional_context' => $this->normalizeStringArray($result['additional_context'] ?? []),
            'evidence_trace' => $this->buildEvidenceTrace($activityStreams, $result),
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
                    'type' => $this->inferRelationshipType((string) ($item['type'] ?? ''), $role),
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
                'type' => 'Stakeholder',
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

    private function inferRelationshipType(string $rawType, string $role): string
    {
        $rawType = trim($rawType);
        if ($rawType !== '') {
            return $rawType;
        }

        $role = strtolower($role);
        if ((bool) preg_match('/ceo|cto|cfo|chief|director|head|vp|president|owner|founder/', $role)) {
            return 'Decision-maker';
        }
        if ((bool) preg_match('/support|ops|admin|legal|procurement|finance/', $role)) {
            return 'Influencer';
        }

        return 'Stakeholder';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function extractTimelineRows(array $activityStreams): array
    {
        $lead = is_array($activityStreams['lead'] ?? null) ? $activityStreams['lead'] : [];
        $opportunity = is_array($activityStreams['opportunity'] ?? null) ? $activityStreams['opportunity'] : [];
        $items = array_merge($lead, $opportunity);

        if (count($items) === 0) {
            return [];
        }

        $daily = [];
        $timestamps = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $createdAtRaw = trim((string) ($item['created_at'] ?? ''));
            if ($createdAtRaw === '') {
                continue;
            }

            $ts = strtotime($createdAtRaw);
            if ($ts === false) {
                continue;
            }

            $date = date('Y-m-d', $ts);
            if (!isset($daily[$date])) {
                $daily[$date] = [
                    'date' => $date,
                    'emails' => 0,
                    'replies' => 0,
                    'meetings' => 0,
                    'response_sum' => 0.0,
                    'response_count' => 0,
                    'negative' => 0,
                    'positive' => 0,
                ];
            }

            $type = strtolower(trim((string) ($item['activity_type'] ?? '')));
            $title = strtolower(trim((string) ($item['title'] ?? '')));
            $description = strtolower(trim((string) ($item['description'] ?? '')));
            $text = trim($title . ' ' . $description);

            if ($type === 'email' || $type === 'message' || str_contains($title, 'email')) {
                $daily[$date]['emails']++;
            }
            if (str_contains($title, 'email from') || str_contains($title, 'received message')) {
                $daily[$date]['replies']++;
            }
            if ((bool) preg_match('/meeting|demo|call|sync|review|workshop/', $text)) {
                $daily[$date]['meetings']++;
            }
            if ((bool) preg_match('/risk|block|delay|stalled|churn|issue|escalat|friction|cancel/', $text)) {
                $daily[$date]['negative']++;
            }
            if ((bool) preg_match('/win|progress|momentum|success|expansion|upsell|alignment|positive/', $text)) {
                $daily[$date]['positive']++;
            }

            $timestamps[] = ['date' => $date, 'timestamp' => $ts];
        }

        usort($timestamps, static fn (array $a, array $b): int => $a['timestamp'] <=> $b['timestamp']);
        for ($i = 1; $i < count($timestamps); $i++) {
            $deltaHours = max(0.0, min(72.0, ($timestamps[$i]['timestamp'] - $timestamps[$i - 1]['timestamp']) / 3600));
            $date = $timestamps[$i]['date'];
            $daily[$date]['response_sum'] += $deltaHours;
            $daily[$date]['response_count']++;
        }

        ksort($daily);
        $lastActivityDate = null;
        $rows = [];
        foreach ($daily as $date => $row) {
            $volume = (int) $row['emails'] + (int) $row['replies'] + (int) $row['meetings'];
            $strength = $volume >= 4 ? 'Strong' : ($volume >= 2 ? 'Medium' : 'Weak');
            $sentiment = $row['positive'] > $row['negative']
                ? 'Positive'
                : ($row['negative'] > $row['positive'] ? 'Negative' : 'Neutral');

            $inactivityDays = 0;
            if ($lastActivityDate !== null) {
                $inactivityDays = max(0, (int) floor((strtotime($date) - strtotime($lastActivityDate)) / 86400));
            }
            $lastActivityDate = $date;

            $rows[] = [
                'date' => $date,
                'emails' => (int) $row['emails'],
                'replies' => (int) $row['replies'],
                'meetings' => (int) $row['meetings'],
                'response_hours' => $row['response_count'] > 0
                    ? round((float) $row['response_sum'] / (int) $row['response_count'], 2)
                    : 0.0,
                'strength' => $strength,
                'inactivity_days' => $inactivityDays,
                'sentiment' => $sentiment,
            ];
        }

        return $rows;
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function extractEventMarkers(array $result, array $context): array
    {
        $events = [];

        $renewal = trim((string) ($context['crm']['financials']['renewal_date'] ?? ''));
        if ($renewal !== '') {
            $events[] = ['label' => 'Renewal Milestone', 'date' => $renewal];
        }

        $opps = is_array($context['crm']['opportunities'] ?? null) ? $context['crm']['opportunities'] : [];
        if (isset($opps[0]['close_date']) && trim((string) $opps[0]['close_date']) !== '') {
            $events[] = ['label' => 'Top Opportunity Close Date', 'date' => (string) $opps[0]['close_date']];
        }

        $riskSignalDate = trim((string) ($result['risk_signal_date'] ?? ''));
        if ($riskSignalDate !== '') {
            $events[] = ['label' => 'Risk Signal', 'date' => $riskSignalDate];
        }

        return $events;
    }

    /**
     * @return array<int, string>
     */
    private function buildEvidenceTrace(array $activityStreams, array $result): array
    {
        $historical = is_array($activityStreams['historical_summary']['combined'] ?? null)
            ? $activityStreams['historical_summary']['combined']
            : [];

        $riskEvents = is_array($historical['significant_risk_events'] ?? null)
            ? $historical['significant_risk_events']
            : [];
        $positiveEvents = is_array($historical['significant_positive_events'] ?? null)
            ? $historical['significant_positive_events']
            : [];

        $lines = [];

        foreach (array_slice($riskEvents, 0, 2) as $line) {
            $value = trim((string) $line);
            if ($value !== '') {
                $lines[] = "Risk signal: {$value}";
            }
        }
        foreach (array_slice($positiveEvents, 0, 2) as $line) {
            $value = trim((string) $line);
            if ($value !== '') {
                $lines[] = "Positive signal: {$value}";
            }
        }

        if (count($lines) === 0) {
            $summary = trim((string) ($result['summary'] ?? ''));
            if ($summary !== '') {
                $lines[] = "Primary summary signal: {$summary}";
            }
        }

        if (count($lines) === 0) {
            $lines[] = 'Not available: no evidence trace signals were detected in current scoped history.';
        }

        return array_slice($lines, 0, 4);
    }
}
