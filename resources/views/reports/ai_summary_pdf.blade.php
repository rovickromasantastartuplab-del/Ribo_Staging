<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>AI Strategic Summary Report</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #1f2937; font-size: 11px; line-height: 1.5; margin: 40px; }
        h1 { font-size: 28px; margin: 0 0 4px 0; color: #111827; letter-spacing: -0.5px; }
        h2 { font-size: 10px; margin: 0; text-transform: uppercase; color: #4b5563; letter-spacing: 1.5px; font-weight: 700; }
        .muted { color: #6b7280; font-size: 10px; margin: 0; }
        .header { border-bottom: 2px solid #f3f4f6; padding-bottom: 20px; margin-bottom: 20px; }
        .section { margin-top: 24px; page-break-inside: avoid; }
        .section-header { background: #f9fafb; border-left: 4px solid #3b82f6; padding: 6px 12px; margin-bottom: 12px; }
        .grid { width: 100%; border-collapse: collapse; margin-top: 8px; }
        .grid th, .grid td { border: 1px solid #e5e7eb; padding: 10px 12px; text-align: left; vertical-align: middle; }
        .grid th { background: #f9fafb; font-size: 9px; font-weight: 700; color: #374151; text-transform: uppercase; letter-spacing: 0.5px; }
        .list { margin: 0; padding-left: 14px; list-style-type: none; }
        .list li { margin-bottom: 8px; position: relative; padding-left: 12px; }
        .list li::before { content: "•"; color: #3b82f6; position: absolute; left: 0; font-weight: bold; }
        .gaps { margin-top: 12px; font-style: italic; color: #4b5563; border-left: 2px solid #e5e7eb; padding-left: 10px; }
        .status-pill { display: inline-block; padding: 2px 8px; background: #eff6ff; color: #1e40af; border-radius: 4px; font-weight: 700; text-transform: uppercase; font-size: 9px; }
        .label { font-weight: 700; color: #374151; }
    </style>
</head>
<body>
    @php
        $formatted = $formatted ?? [];
        $accountName = $context['crm']['account']['name'] ?? 'Unassigned Account';

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
        $additionalContext = $formatted['additional_context'] ?? [];
    @endphp

    <div class="header">
        <h1>Account Summary</h1>
        <p class="muted meta">
            Account: {{ $accountName }} | Scope: {{ strtoupper((string) ($job->scope ?? 'overall')) }} | Generated: {{ optional($job->completed_at)->format('M d, Y H:i') ?? now()->format('M d, Y H:i') }}
        </p>
    </div>

    <div class="section">
        <div class="section-header"><h2>Account Status</h2></div>
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
        <div class="section-header"><h2>Executive Insights</h2></div>
        <ul class="list">
            @forelse(collect($executiveInsights)->filter(fn($i) => $i !== 'Not available') as $item)
                <li>{{ $item }}</li>
            @empty
                <li>No specific strategic insights identified for this thread context.</li>
            @endforelse
        </ul>
    </div>

    <div class="section">
        <div class="section-header"><h2>Key Relationships</h2></div>
        @if($visibleRelationships->isNotEmpty())
            <table class="grid">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Role</th>
                        <th>Strength</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($visibleRelationships as $relationship)
                        <tr>
                            <td><span class="label">{{ $relationship['name'] }}</span></td>
                            <td>{{ $relationship['role'] ?? 'Stakeholder' }}</td>
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
        <p class="gaps"><span class="label">Gaps:</span> {{ $relationshipGaps ?? 'Not available' }}</p>
    </div>

    <div class="section">
        <div class="section-header"><h2>Deals &amp; Pipeline Snapshot</h2></div>
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
        <div class="section-header"><h2>Engagement &amp; Health Signals</h2></div>
        <ul class="list">
            <li><span class="label">Usage:</span> {{ $healthSignals['usage'] ?? 'Not available' }}</li>
            <li><span class="label">Support:</span> {{ $healthSignals['support'] ?? 'Not available' }}</li>
            <li><span class="label">Sentiment:</span> {{ $healthSignals['sentiment'] ?? 'Not available' }}</li>
            <li><span class="label">Engagement Pattern:</span> {{ $healthSignals['engagement_pattern'] ?? 'Not available' }}</li>
        </ul>
    </div>

    <div class="section">
        <div class="section-header"><h2>Key Risks</h2></div>
        <ul class="list">
            @forelse(collect($keyRisks)->filter(fn($r) => $r !== 'Not available') as $item)
                <li>{{ $item }}</li>
            @empty
                <li>No significant commercial risks detected in recent history.</li>
            @endforelse
        </ul>
    </div>

    <div class="section">
        <div class="section-header"><h2>Growth Opportunities</h2></div>
        <ul class="list">
            @forelse(collect($growthOpportunities)->filter(fn($o) => $o !== 'Not available') as $item)
                <li>{{ $item }}</li>
            @empty
                <li>No immediate expansion or growth signals identified.</li>
            @endforelse
        </ul>
    </div>

    <div class="section">
        <div class="section-header"><h2>Recommended Actions (Next 30-60 Days)</h2></div>
        <ul class="list">
            @forelse(collect($recommendedActions)->filter(fn($a) => !str_contains($a, 'Not available')) as $actionLine)
                <li>{{ $actionLine }}</li>
            @empty
                <li>Continue standard account monitoring and relationship maintenance.</li>
            @endforelse
        </ul>
    </div>

    @if(!empty($additionalContext))
        <div class="section">
            <div class="section-header"><h2>Additional Context</h2></div>
            <ul class="list">
                @foreach($additionalContext as $line)
                    <li>{{ $line }}</li>
                @endforeach
            </ul>
        </div>
    @endif
</body>
</html>


