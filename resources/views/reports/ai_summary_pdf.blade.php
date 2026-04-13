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
        $crm = $context['crm'] ?? [];
        $financials = $crm['financials'] ?? [];
        $opportunities = $crm['opportunities'] ?? [];
        $relationships = $result['key_relationships'] ?? ($crm['relationships'] ?? []);
        $roleActions = $result['role_based_actions'] ?? [];
    @endphp

    <h1>Client Account Snapshot</h1>
    <p class="muted">
        Report #{{ $job->id }} | Scope: {{ strtoupper((string) ($job->scope ?? 'overall')) }} | Generated: {{ optional($job->completed_at)->format('M d, Y H:i') ?? now()->format('M d, Y H:i') }}
    </p>

    <div class="section">
        <h2>Account Status</h2>
        <p class="status">{{ $result['account_status'] ?? ($result['summary'] ?? 'Status unavailable.') }}</p>
    </div>

    <div class="section">
        <h2>Financial Snapshot</h2>
        <table class="grid">
            <tr>
                <th>ARR</th>
                <th>MRR</th>
                <th>Active Deals</th>
            </tr>
            <tr>
                <td>${{ number_format((float) ($financials['arr'] ?? 0), 2) }}</td>
                <td>${{ number_format((float) ($financials['mrr'] ?? 0), 2) }}</td>
                <td>{{ (int) ($financials['active_deals_count'] ?? 0) }}</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <h2>Executive Insights</h2>
        <ul class="list">
            @foreach(($result['executive_insights'] ?? $result['key_insights'] ?? []) as $item)
                <li>{{ $item }}</li>
            @endforeach
        </ul>
    </div>

    <div class="section">
        <h2>Deals</h2>
        @if(count($opportunities) === 0)
            <p>No active deals found.</p>
        @else
            <table class="grid">
                <tr>
                    <th>Opportunity</th>
                    <th>Stage</th>
                    <th>Amount</th>
                    <th>Close Date</th>
                </tr>
                @foreach($opportunities as $opportunity)
                    <tr>
                        <td>{{ $opportunity['name'] ?? 'N/A' }}</td>
                        <td>{{ $opportunity['stage'] ?? 'N/A' }}</td>
                        <td>${{ number_format((float) ($opportunity['amount'] ?? 0), 2) }}</td>
                        <td>{{ $opportunity['close_date'] ?? 'N/A' }}</td>
                    </tr>
                @endforeach
            </table>
        @endif
    </div>

    <div class="section">
        <h2>Key Relationships</h2>
        <ul class="list">
            @foreach($relationships as $rel)
                @if(is_array($rel))
                    <li>{{ $rel['name'] ?? 'Unknown' }} - {{ $rel['role'] ?? 'Stakeholder' }}</li>
                @else
                    <li>{{ $rel }}</li>
                @endif
            @endforeach
        </ul>
    </div>

    <div class="section">
        <h2>Risks & Opportunities</h2>
        <ul class="list">
            @foreach(($result['risks_and_opportunities'] ?? []) as $item)
                <li>{{ $item }}</li>
            @endforeach
        </ul>
    </div>

    <div class="section">
        <h2>Role-Based Actions</h2>
        <table class="grid">
            <tr>
                <th>Sales</th>
                <th>CSM</th>
                <th>Support</th>
            </tr>
            <tr>
                <td>
                    <ul class="list">
                        @foreach(($roleActions['sales'] ?? $result['next_actions'] ?? []) as $action)
                            <li>{{ $action }}</li>
                        @endforeach
                    </ul>
                </td>
                <td>
                    <ul class="list">
                        @foreach(($roleActions['csm'] ?? []) as $action)
                            <li>{{ $action }}</li>
                        @endforeach
                    </ul>
                </td>
                <td>
                    <ul class="list">
                        @foreach(($roleActions['support'] ?? []) as $action)
                            <li>{{ $action }}</li>
                        @endforeach
                    </ul>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
