<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>AI Strategic Summary Report</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #111827; font-size: 12px; line-height: 1.45; margin: 22px; }
        h1 { font-size: 20px; margin: 0 0 6px 0; letter-spacing: 0.5px; }
        h2 { font-size: 12px; margin: 0 0 8px 0; text-transform: uppercase; color: #1f2937; letter-spacing: 0.8px; }
        .muted { color: #6b7280; font-size: 10px; }
        .section { margin-top: 16px; border-top: 1px solid #d1d5db; padding-top: 10px; }
        .grid { width: 100%; border-collapse: collapse; }
        .grid th, .grid td { border: 1px solid #d1d5db; padding: 6px 8px; text-align: left; vertical-align: top; }
        .grid th { background: #f3f4f6; font-size: 11px; }
        .list { margin: 0; padding-left: 16px; }
        .status { font-weight: 700; }
    </style>
</head>
<body>
    @php
        $formatted = $formatted ?? [];

        $accountStatus = $formatted['account_status'] ?? [];
        $executiveInsights = $formatted['executive_insights'] ?? [];
        $keyRelationships = $formatted['key_relationships'] ?? [];
        $relationshipGaps = $formatted['relationship_gaps'] ?? 'Not available';
        $deals = $formatted['deals'] ?? [];
        $healthSignals = $formatted['engagement_health_signals'] ?? [];
        $keyRisks = $formatted['key_risks'] ?? [];
        $growthOpportunities = $formatted['growth_opportunities'] ?? [];
        $recommendedActions = $formatted['recommended_actions'] ?? [];
        $additionalContext = $formatted['additional_context'] ?? [];
    @endphp

    <h1>Client Account Snapshot</h1>
    <p class="muted">
        Report #{{ $job->id }} | Scope: {{ strtoupper((string) ($job->scope ?? 'overall')) }} | Generated: {{ optional($job->completed_at)->format('M d, Y H:i') ?? now()->format('M d, Y H:i') }}
    </p>

    <div class="section">
        <h2>Account Status</h2>
        <ul class="list">
            <li>Status: {{ $accountStatus['status'] ?? 'Not available' }}</li>
            <li>Health Score: {{ $accountStatus['health'] ?? 'Not available' }} - {{ $accountStatus['health_reason'] ?? 'Not available' }}</li>
            <li>ARR: {{ $accountStatus['arr'] ?? 'Not available' }} | MRR: {{ $accountStatus['mrr'] ?? 'Not available' }} | Renewal: {{ $accountStatus['renewal'] ?? 'Not available' }}</li>
        </ul>
    </div>

    <div class="section">
        <h2>Executive Insights</h2>
        <ul class="list">
            @forelse($executiveInsights as $item)
                <li>{{ $item }}</li>
            @empty
                <li>Not available</li>
            @endforelse
        </ul>
    </div>

    <div class="section">
        <h2>Key Relationships</h2>
        <table class="grid">
            <tr>
                <th>Name</th>
                <th>Role</th>
                <th>Strength</th>
            </tr>
            @forelse($keyRelationships as $relationship)
                <tr>
                    <td>{{ $relationship['name'] ?? 'Not available' }}</td>
                    <td>{{ $relationship['role'] ?? 'Not available' }}</td>
                    <td>{{ $relationship['strength'] ?? 'Not available' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="3">Not available</td>
                </tr>
            @endforelse
        </table>
        <p>Gaps: {{ $relationshipGaps }}</p>
    </div>

    <div class="section">
        <h2>Deals &amp; Pipeline Snapshot</h2>
        <ul class="list">
            <li>Active Deals: {{ $deals['active_deals'] ?? 'Not available' }}</li>
            <li>Top Deal: {{ $deals['top_deal'] ?? 'Not available' }}</li>
            <li>Expansion Potential: {{ $deals['expansion_potential'] ?? 'Not available' }}</li>
            <li>Notable Past Deals:</li>
            <li>Won: {{ $deals['notable_won'] ?? 'Not available' }}</li>
            <li>Lost: {{ $deals['notable_lost'] ?? 'Not available' }}</li>
            <li>Stalled: {{ $deals['notable_stalled'] ?? 'Not available' }}</li>
        </ul>
    </div>

    <div class="section">
        <h2>Engagement &amp; Health Signals</h2>
        <ul class="list">
            <li>Usage: {{ $healthSignals['usage'] ?? 'Not available' }}</li>
            <li>Support: {{ $healthSignals['support'] ?? 'Not available' }}</li>
            <li>Sentiment: {{ $healthSignals['sentiment'] ?? 'Not available' }}</li>
            <li>Engagement Pattern: {{ $healthSignals['engagement_pattern'] ?? 'Not available' }}</li>
        </ul>
    </div>

    <div class="section">
        <h2>Key Risks</h2>
        <ul class="list">
            @forelse($keyRisks as $item)
                <li>{{ $item }}</li>
            @empty
                <li>Not available</li>
            @endforelse
        </ul>
    </div>

    <div class="section">
        <h2>Growth Opportunities</h2>
        <ul class="list">
            @forelse($growthOpportunities as $item)
                <li>{{ $item }}</li>
            @empty
                <li>Not available</li>
            @endforelse
        </ul>
    </div>

    <div class="section">
        <h2>Recommended Actions (Next 30–60 Days)</h2>
        <ul class="list">
            @forelse($recommendedActions as $actionLine)
                <li>{{ $actionLine }}</li>
            @empty
                <li>Sales -> Not available -> High</li>
                <li>CSM -> Not available -> High</li>
                <li>Support/Product -> Not available -> Medium</li>
                <li>Exec Sponsor -> Not available -> Medium</li>
            @endforelse
        </ul>
    </div>

    @if(!empty($additionalContext))
        <div class="section">
            <h2>Additional Context</h2>
            <ul class="list">
                @foreach($additionalContext as $line)
                    <li>{{ $line }}</li>
                @endforeach
            </ul>
        </div>
    @endif
</body>
</html>
