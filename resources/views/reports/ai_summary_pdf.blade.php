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
        .chart-metrics {
            margin: 0 0 8px 0;
            font-size: 9px;
            color: {{ $styleTokens['text_muted'] ?? '#6b7280' }};
        }
        .chart-metrics span {
            display: inline-block;
            margin-right: 8px;
            padding: 2px 6px;
            border: 1px solid {{ $styleTokens['border'] ?? '#e5e7eb' }};
            border-radius: 4px;
            background: #ffffff;
        }
        .takeaway-list { margin: 0; padding-left: 16px; }
        .takeaway-list li { margin-bottom: 5px; }
        .chart-wrap {
            width: 100%;
            border-collapse: collapse;
            border-bottom: 1px solid {{ $styleTokens['border'] ?? '#e5e7eb' }};
            margin-bottom: 4px;
        }
        .chart-wrap td {
            vertical-align: bottom;
            padding: 0 1px;
            text-align: center;
        }
        .chart-bar {
            background: {{ $styleTokens['brand_color'] ?? '#10b77f' }};
            margin: 0 auto;
            width: 6px;
        }
        .axis-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 3px;
            font-size: 8px;
            color: {{ $styleTokens['text_muted'] ?? '#6b7280' }};
        }
        .axis-table td { text-align: center; padding: 0 1px; }
        .legend {
            margin-top: 5px;
            font-size: 9px;
            color: {{ $styleTokens['text_muted'] ?? '#6b7280' }};
        }
        .legend-dot {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 999px;
            margin-right: 4px;
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
                $graphId = (string) ($graph['id'] ?? '');
                $series = is_array($graph['series'] ?? null) ? array_values($graph['series']) : [];
                $max = max(1, (float) max($series ?: [0]));
                $min = (float) min($series ?: [0]);
                $count = max(1, count($series));
                $points = collect($series)->map(function ($value, $idx) use ($count, $max) {
                    $x = $count > 1 ? (($idx / ($count - 1)) * 560.0) : 0.0;
                    $y = 120.0 - (((float) $value / $max) * 100.0);
                    return number_format($x, 2, '.', '') . ',' . number_format($y, 2, '.', '');
                })->implode(' ');

                $avg = $count > 0 ? (float) (array_sum($series) / $count) : 0.0;
                $latest = $count > 0 ? (float) $series[$count - 1] : 0.0;

                $tickCount = 7;
                $tickLabels = [];
                for ($t = 0; $t < $tickCount; $t++) {
                    $idx = $count > 1 ? (int) round(($t / ($tickCount - 1)) * ($count - 1)) : 0;
                    $tickLabels[] = $dateLabels[$idx] ?? '';
                }

                $yBase = 70;
                $sentimentMin = min(-1.0, $min);
                $sentimentMax = max(1.0, $max);
            @endphp
            <div class="card">
                <div class="card-head">{{ $graph['title'] ?? 'Graph' }}</div>
                <div class="card-body">
                    <div class="chart-metrics">
                        <span>Latest: {{ number_format($latest, 1) }}</span>
                        <span>Avg: {{ number_format($avg, 1) }}</span>
                        <span>Min: {{ number_format($min, 1) }}</span>
                        <span>Max: {{ number_format($max, 1) }}</span>
                    </div>

                    @php
                        $chartHeight = 80;
                        $barColor = $styleTokens['brand_color'] ?? '#10b77f';
                        $warnColor = $styleTokens['warning'] ?? '#f59e0b';
                        $dangerColor = $styleTokens['danger'] ?? '#ef4444';
                        $neutralColor = '#9ca3af';
                        $borderColor = $styleTokens['border'] ?? '#e5e7eb';
                    @endphp

                    @if($graphId === 'interaction_volume' || $graphId === 'inactivity_gap')
                        {{-- Column (bar) chart --}}
                        <table class="chart-wrap" style="height: {{ $chartHeight }}px;">
                            <tr>
                                @foreach($series as $value)
                                    @php
                                        $pct = $max > 0 ? max(1, round(((float)$value / $max) * $chartHeight)) : 1;
                                    @endphp
                                    <td>
                                        <div class="chart-bar" style="height: {{ $pct }}px; background: {{ $barColor }};"></div>
                                    </td>
                                @endforeach
                            </tr>
                        </table>
                        <div class="legend"><span class="legend-dot" style="background: {{ $barColor }};"></span>Column chart (daily values)</div>

                    @elseif($graphId === 'response_time')
                        {{-- Line chart approximated as column chart with warning color --}}
                        <table class="chart-wrap" style="height: {{ $chartHeight }}px;">
                            <tr>
                                @foreach($series as $value)
                                    @php
                                        $pct = $max > 0 ? max(1, round(((float)$value / $max) * $chartHeight)) : 1;
                                    @endphp
                                    <td>
                                        <div class="chart-bar" style="height: {{ $pct }}px; background: {{ $warnColor }};"></div>
                                    </td>
                                @endforeach
                            </tr>
                        </table>
                        <div class="legend"><span class="legend-dot" style="background: {{ $warnColor }};"></span>Column chart (hours)</div>

                    @elseif($graphId === 'relationship_strength')
                        {{-- Ordinal chart: 1=Weak, 2=Medium, 3=Strong mapped to 33%/66%/100% height --}}
                        <table class="chart-wrap" style="height: {{ $chartHeight }}px;">
                            <tr>
                                @foreach($series as $value)
                                    @php
                                        $pct = max(1, round(((float)$value / 3.0) * $chartHeight));
                                    @endphp
                                    <td>
                                        <div class="chart-bar" style="height: {{ $pct }}px; background: {{ $barColor }};"></div>
                                    </td>
                                @endforeach
                            </tr>
                        </table>
                        <div class="legend">
                            <span class="legend-dot" style="background: {{ $barColor }};"></span>Bar height: 1=Weak, 2=Medium, 3=Strong
                        </div>

                    @elseif($graphId === 'sentiment_trend')
                        {{-- Sentiment: positive=green, neutral=grey, negative=red --}}
                        <table class="chart-wrap" style="height: {{ $chartHeight }}px;">
                            <tr>
                                @foreach($series as $value)
                                    @php
                                        $fv = (float)$value;
                                        $sentColor = $fv > 0 ? $barColor : ($fv < 0 ? $dangerColor : $neutralColor);
                                        $scale = max(abs($sentimentMin), abs($sentimentMax));
                                        $pct = $scale > 0 ? max(2, round((abs($fv) / $scale) * $chartHeight)) : 2;
                                    @endphp
                                    <td>
                                        <div class="chart-bar" style="height: {{ $pct }}px; background: {{ $sentColor }};"></div>
                                    </td>
                                @endforeach
                            </tr>
                        </table>
                        <div class="legend">
                            <span class="legend-dot" style="background: {{ $barColor }};"></span>Positive
                            <span class="legend-dot" style="background: {{ $dangerColor }}; margin-left: 10px;"></span>Negative
                            <span class="legend-dot" style="background: {{ $neutralColor }}; margin-left: 10px;"></span>Neutral
                        </div>

                    @else
                        {{-- Generic column chart fallback --}}
                        <table class="chart-wrap" style="height: {{ $chartHeight }}px;">
                            <tr>
                                @foreach($series as $value)
                                    @php
                                        $pct = $max > 0 ? max(1, round(((float)$value / $max) * $chartHeight)) : 1;
                                    @endphp
                                    <td>
                                        <div class="chart-bar" style="height: {{ $pct }}px; background: {{ $barColor }};"></div>
                                    </td>
                                @endforeach
                            </tr>
                        </table>
                    @endif

                    <table class="axis-table"><tr>
                        @foreach($tickLabels as $label)
                            <td>{{ $label }}</td>
                        @endforeach
                    </tr></table>
                    <div class="legend">
                        Weekly date ticks (MMM dd); missing days are zero-filled for continuity.
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
