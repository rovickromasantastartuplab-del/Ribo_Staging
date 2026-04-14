<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>AI Strategic Summary Report</title>
    <style>
        @page { margin: 28px; }
        body {
            font-family: DejaVu Sans, sans-serif;
            color: {{ $styleTokens['text_primary'] ?? '#111827' }};
            font-size: 11px;
            line-height: 1.45;
            margin: 0;
        }
        .page { page-break-after: always; }
        .page:last-child { page-break-after: auto; }
        .page-title {
            font-size: 14px;
            font-weight: 700;
            margin-bottom: 10px;
            padding: 7px 10px;
            border-left: 4px solid {{ $styleTokens['brand_color'] ?? '#10b77f' }};
            background: {{ $styleTokens['brand_soft'] ?? '#eaf8f2' }};
        }
        .header {
            border: 1px solid {{ $styleTokens['border'] ?? '#e5e7eb' }};
            background: {{ $styleTokens['surface'] ?? '#f9fafb' }};
            padding: 10px 12px;
            margin-bottom: 10px;
        }
        .header h1 {
            margin: 0;
            font-size: 18px;
            color: {{ $styleTokens['text_primary'] ?? '#111827' }};
        }
        .muted {
            color: {{ $styleTokens['text_muted'] ?? '#6b7280' }};
            font-size: 10px;
            margin: 4px 0 0;
        }
        .card {
            border: 1px solid {{ $styleTokens['border'] ?? '#e5e7eb' }};
            border-radius: 6px;
            margin-bottom: 10px;
            page-break-inside: avoid;
        }
        .card-head {
            background: {{ $styleTokens['surface'] ?? '#f9fafb' }};
            border-bottom: 1px solid {{ $styleTokens['border'] ?? '#e5e7eb' }};
            padding: 7px 10px;
            font-weight: 700;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .card-body { padding: 10px; }
        .takeaway-list { margin: 0; padding-left: 16px; }
        .takeaway-list li { margin-bottom: 5px; }
        .axis {
            margin-top: 5px;
            font-size: 9px;
            color: {{ $styleTokens['text_muted'] ?? '#6b7280' }};
            display: flex;
            justify-content: space-between;
        }
        .axis span { display: inline-block; min-width: 52px; text-align: center; }
        .legend {
            margin-top: 5px;
            font-size: 9px;
            color: {{ $styleTokens['text_muted'] ?? '#6b7280' }};
        }
        .bars {
            width: 100%;
            border-collapse: collapse;
            margin-top: 4px;
        }
        .bars td {
            vertical-align: bottom;
            width: 3%;
            height: 48px;
            padding: 0 1px;
        }
        .bar {
            display: block;
            width: 100%;
            background: {{ $styleTokens['brand_color'] ?? '#10b77f' }};
            min-height: 2px;
        }
        .section { margin-bottom: 10px; page-break-inside: avoid; }
        .section-header {
            border-left: 4px solid {{ $styleTokens['brand_color'] ?? '#10b77f' }};
            background: {{ $styleTokens['brand_soft'] ?? '#eaf8f2' }};
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 6px 10px;
            margin-bottom: 8px;
        }
        .list { margin: 0; padding-left: 15px; }
        .list li { margin-bottom: 5px; }
        .label { font-weight: 700; }
        .grid { width: 100%; border-collapse: collapse; }
        .grid th, .grid td {
            border: 1px solid {{ $styleTokens['border'] ?? '#e5e7eb' }};
            padding: 7px 8px;
            text-align: left;
            vertical-align: top;
            font-size: 10px;
        }
        .grid th {
            background: {{ $styleTokens['surface'] ?? '#f9fafb' }};
            text-transform: uppercase;
            font-size: 9px;
            letter-spacing: 0.4px;
        }
        .pill {
            display: inline-block;
            padding: 1px 6px;
            border-radius: 999px;
            background: {{ $styleTokens['brand_soft'] ?? '#eaf8f2' }};
            color: {{ $styleTokens['brand_border'] ?? '#0f5132' }};
            font-size: 9px;
            font-weight: 700;
        }
        .evidence {
            border-left: 3px solid {{ $styleTokens['brand_color'] ?? '#10b77f' }};
            padding-left: 8px;
            color: {{ $styleTokens['text_muted'] ?? '#6b7280' }};
            font-size: 10px;
        }
    </style>
</head>
<body>
    @php
        $formatted = $formatted ?? [];
        $styleTokens = $styleTokens ?? [];
        $accountName = $context['crm']['account']['name'] ?? ($formatted['client_account_snapshot']['account_name'] ?? 'Unassigned Account');
        $scopeLabel = strtoupper((string) ($job->scope ?? ($formatted['scope'] ?? 'overall')));
        $generatedAt = optional($job->completed_at)->format('M d, Y H:i') ?? now()->format('M d, Y H:i');

        $analytics = is_array($formatted['analytics'] ?? null) ? $formatted['analytics'] : [];
        $graphs = is_array($analytics['graphs'] ?? null) ? $analytics['graphs'] : [];
        $takeaways = is_array($analytics['top_takeaways'] ?? null) ? $analytics['top_takeaways'] : [];
        $dateLabels = is_array($analytics['date_labels'] ?? null) ? $analytics['date_labels'] : [];
        $markers = is_array($analytics['event_markers'] ?? null) ? $analytics['event_markers'] : [];
        $windowDays = (int) ($analytics['window_days'] ?? 30);

        $accountStatus = $formatted['account_status'] ?? [];
        $executiveInsights = $formatted['executive_insights'] ?? [];
        $keyRelationships = $formatted['key_relationships'] ?? [];
        $visibleRelationships = collect($keyRelationships)->filter(fn($r) => ($r['name'] ?? 'Not available') !== 'Not available')->values();
        $relationshipGaps = $formatted['relationship_gaps'] ?? 'Not available';
        $deals = $formatted['deals'] ?? [];
        $healthSignals = $formatted['engagement_health_signals'] ?? [];
        $keyRisks = $formatted['key_risks'] ?? [];
        $growthOpportunities = $formatted['growth_opportunities'] ?? [];
        $recommendedActions = $formatted['recommended_actions'] ?? [];
        $evidenceTrace = $formatted['evidence_trace'] ?? [];
        $additionalContext = $formatted['additional_context'] ?? [];

        $axisStart = $dateLabels[0] ?? '';
        $axisMid = count($dateLabels) > 2 ? $dateLabels[(int) floor((count($dateLabels) - 1) / 2)] : '';
        $axisEnd = count($dateLabels) > 1 ? $dateLabels[count($dateLabels) - 1] : '';
    @endphp

    <div class="page">
        <div class="header">
            <h1>AI Strategic Summary Report</h1>
            <p class="muted">
                Account: {{ $accountName }} | Scope: {{ $scopeLabel }} | Generated: {{ $generatedAt }} | Window: {{ $windowDays }} Days
            </p>
        </div>

        <div class="page-title">Page 1: Relationship Analytics</div>

        <div class="card">
            <div class="card-head">Top 3 Takeaways</div>
            <div class="card-body">
                <ul class="takeaway-list">
                    @forelse($takeaways as $line)
                        <li>{{ $line }}</li>
                    @empty
                        <li>Not available: analytics summary could not be generated for the selected scope.</li>
                    @endforelse
                </ul>
            </div>
        </div>

        @forelse($graphs as $graph)
            @php
                $series = is_array($graph['series'] ?? null) ? array_values($graph['series']) : [];
                $max = max(1, (float) max($series ?: [0]));
                $count = max(1, count($series));
                $points = collect($series)->map(function ($value, $idx) use ($count, $max) {
                    $x = $count > 1 ? (($idx / ($count - 1)) * 560.0) : 0.0;
                    $y = 120.0 - (((float) $value / $max) * 100.0);
                    return number_format($x, 2, '.', '') . ',' . number_format($y, 2, '.', '');
                })->implode(' ');
                $step = max(1, (int) ceil($count / 30));
                $barSeries = [];
                for ($idx = 0; $idx < $count; $idx += $step) {
                    $barSeries[] = (float) ($series[$idx] ?? 0);
                }
            @endphp
            <div class="card">
                <div class="card-head">{{ $graph['title'] ?? 'Graph' }}</div>
                <div class="card-body">
                    <svg viewBox="0 0 560 140" width="560" height="130" xmlns="http://www.w3.org/2000/svg" style="display: block;">
                        <line x1="0" y1="120" x2="560" y2="120" stroke="{{ $styleTokens['border'] ?? '#e5e7eb' }}" stroke-width="1"></line>
                        <line x1="0" y1="20" x2="0" y2="120" stroke="{{ $styleTokens['border'] ?? '#e5e7eb' }}" stroke-width="1"></line>
                        <polyline fill="none" stroke="{{ $styleTokens['brand_color'] ?? '#10b77f' }}" stroke-width="2" points="{{ $points }}"></polyline>
                    </svg>
                    <table class="bars" aria-hidden="true">
                        <tr>
                            @foreach($barSeries as $bar)
                                @php
                                    $barHeight = 2 + (int) round(((float) $bar / $max) * 44);
                                @endphp
                                <td><span class="bar" style="height: {{ $barHeight }}px;"></span></td>
                            @endforeach
                        </tr>
                    </table>
                    <div class="axis">
                        <span>{{ $axisStart }}</span>
                        <span>{{ $axisMid }}</span>
                        <span>{{ $axisEnd }}</span>
                    </div>
                    <div class="legend">
                        Date axis uses explicit labels (MMM dd); missing days are zero-filled for continuity.
                    </div>
                </div>
            </div>
        @empty
            <div class="card">
                <div class="card-head">User Relationship Health Graphs</div>
                <div class="card-body">Not available: no analytics series were generated.</div>
            </div>
        @endforelse

        <div class="card">
            <div class="card-head">Event Markers</div>
            <div class="card-body">
                @if(!empty($markers))
                    <ul class="list">
                        @foreach($markers as $marker)
                            <li><span class="pill">{{ $marker['date'] ?? '' }}</span> {{ $marker['label'] ?? '' }}</li>
                        @endforeach
                    </ul>
                @else
                    <p class="muted">No dated milestone markers were detected in this scoped timeline.</p>
                @endif
            </div>
        </div>
    </div>

    <div class="page">
        <div class="page-title">Page 2: Executive Summary</div>

        <div class="section">
            <div class="section-header">Client Account Snapshot</div>
            <ul class="list">
                <li><span class="label">Account:</span> {{ $accountName }}</li>
                <li><span class="label">Scope:</span> {{ $scopeLabel }}</li>
                <li><span class="label">Generated:</span> {{ $generatedAt }}</li>
            </ul>
        </div>

        <div class="section">
            <div class="section-header">Account Status</div>
            <ul class="list">
                <li><span class="label">Status:</span> {{ $accountStatus['status'] ?? 'Not available' }}</li>
                <li><span class="label">Health Score:</span> {{ $accountStatus['health'] ?? 'Not available' }} - {{ $accountStatus['health_reason'] ?? 'Not available' }}</li>
                <li>
                    <span class="label">ARR:</span> {{ $accountStatus['arr'] ?? 'Not available' }} |
                    <span class="label">MRR:</span> {{ $accountStatus['mrr'] ?? 'Not available' }} |
                    <span class="label">Renewal:</span> {{ $accountStatus['renewal'] ?? 'Not available' }}
                </li>
            </ul>
        </div>

        <div class="section">
            <div class="section-header">Executive Insights</div>
            <ul class="list">
                @forelse(collect($executiveInsights)->filter(fn($i) => $i !== 'Not available') as $item)
                    <li>{{ $item }}</li>
                @empty
                    <li>No specific strategic insights identified for this thread context.</li>
                @endforelse
            </ul>
        </div>

        <div class="section">
            <div class="section-header">Key Relationships</div>
            @if($visibleRelationships->isNotEmpty())
                <table class="grid">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Role</th>
                            <th>Type</th>
                            <th>Strength</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($visibleRelationships as $relationship)
                            <tr>
                                <td>{{ $relationship['name'] ?? 'Not available' }}</td>
                                <td>{{ $relationship['role'] ?? 'Stakeholder' }}</td>
                                <td>{{ $relationship['type'] ?? 'Stakeholder' }}</td>
                                <td>{{ $relationship['strength'] ?? 'Medium' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <ul class="list">
                    <li>No relationship data available for this report context.</li>
                </ul>
            @endif
            <p class="muted"><span class="label">Gaps:</span> {{ $relationshipGaps ?? 'Not available' }}</p>
        </div>

        <div class="section">
            <div class="section-header">Deals &amp; Pipeline Snapshot</div>
            <ul class="list">
                <li><span class="label">Active Deals:</span> {{ $deals['active_deals'] ?? 'Not available' }}</li>
                <li><span class="label">Top Deal:</span> {{ $deals['top_deal'] ?? 'Not available' }}</li>
                <li><span class="label">Expansion Potential:</span> {{ $deals['expansion_potential'] ?? 'Not available' }}</li>
                <li><span class="label">Notable Past Deals:</span>
                    Won: {{ $deals['notable_won'] ?? 'Not available' }} |
                    Lost: {{ $deals['notable_lost'] ?? 'Not available' }} |
                    Stalled: {{ $deals['notable_stalled'] ?? 'Not available' }}
                </li>
            </ul>
        </div>

        <div class="section">
            <div class="section-header">Engagement &amp; Health Signals</div>
            <ul class="list">
                <li><span class="label">Usage:</span> {{ $healthSignals['usage'] ?? 'Not available' }}</li>
                <li><span class="label">Support:</span> {{ $healthSignals['support'] ?? 'Not available' }}</li>
                <li><span class="label">Sentiment:</span> {{ $healthSignals['sentiment'] ?? 'Not available' }}</li>
                <li><span class="label">Engagement Pattern:</span> {{ $healthSignals['engagement_pattern'] ?? 'Not available' }}</li>
            </ul>
        </div>

        <div class="section">
            <div class="section-header">Key Risks</div>
            <ul class="list">
                @forelse(collect($keyRisks)->filter(fn($r) => $r !== 'Not available') as $item)
                    <li>{{ $item }}</li>
                @empty
                    <li>No significant commercial risks detected in recent history.</li>
                @endforelse
            </ul>
        </div>

        <div class="section">
            <div class="section-header">Growth Opportunities</div>
            <ul class="list">
                @forelse(collect($growthOpportunities)->filter(fn($o) => $o !== 'Not available') as $item)
                    <li>{{ $item }}</li>
                @empty
                    <li>No immediate expansion or growth signals identified.</li>
                @endforelse
            </ul>
        </div>

        <div class="section">
            <div class="section-header">Recommended Actions (Next 30-60 Days)</div>
            <ul class="list">
                @forelse(collect($recommendedActions)->filter(fn($a) => !str_contains($a, 'Not available')) as $actionLine)
                    <li>{{ $actionLine }}</li>
                @empty
                    <li>Continue standard account monitoring and relationship maintenance.</li>
                @endforelse
            </ul>
        </div>

        <div class="section">
            <div class="section-header">Evidence Trace</div>
            <div class="evidence">
                <ul class="list">
                    @forelse($evidenceTrace as $line)
                        <li>{{ $line }}</li>
                    @empty
                        <li>Not available: no evidence trace lines were generated.</li>
                    @endforelse
                </ul>
            </div>
        </div>

        @if(!empty($additionalContext))
            <div class="section">
                <div class="section-header">Additional Context</div>
                <ul class="list">
                    @foreach($additionalContext as $line)
                        <li>{{ $line }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
</body>
</html>
