<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>CCRS Weekly Report — {{ $report_date }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #333; margin: 20px; }
        h1 { color: #1e40af; font-size: 20px; border-bottom: 2px solid #1e40af; padding-bottom: 8px; }
        h2 { color: #374151; font-size: 16px; margin-top: 24px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th { background-color: #f3f4f6; text-align: left; padding: 8px; border-bottom: 2px solid #d1d5db; }
        td { padding: 8px; border-bottom: 1px solid #e5e7eb; }
        .metric { font-size: 28px; font-weight: bold; color: #1e40af; }
        .metric-label { font-size: 11px; color: #6b7280; text-transform: uppercase; }
        .metric-box { display: inline-block; width: 22%; text-align: center; padding: 12px; border: 1px solid #e5e7eb; border-radius: 8px; margin: 4px; }
        .warning { color: #dc2626; font-weight: bold; }
        .footer { margin-top: 32px; font-size: 10px; color: #9ca3af; text-align: center; }
    </style>
</head>
<body>
    <h1>CCRS Weekly Report</h1>
    <p>Period: {{ $period_start }} &mdash; {{ $period_end }} | Generated: {{ $report_date }}</p>

    <h2>Key Metrics</h2>
    <div>
        <div class="metric-box">
            <div class="metric">{{ $new_contracts }}</div>
            <div class="metric-label">New Contracts</div>
        </div>
        <div class="metric-box">
            <div class="metric {{ $expiring_contracts > 0 ? 'warning' : '' }}">{{ $expiring_contracts }}</div>
            <div class="metric-label">Expiring (30d)</div>
        </div>
        <div class="metric-box">
            <div class="metric {{ $overdue_obligations > 0 ? 'warning' : '' }}">{{ $overdue_obligations }}</div>
            <div class="metric-label">Overdue Obligations</div>
        </div>
        <div class="metric-box">
            <div class="metric {{ $open_escalations > 0 ? 'warning' : '' }}">{{ $open_escalations }}</div>
            <div class="metric-label">Open Escalations</div>
        </div>
    </div>

    @if (! empty($new_contracts_by_type))
        <h2>New Contracts by Type</h2>
        <table>
            <thead><tr><th>Type</th><th>Count</th></tr></thead>
            <tbody>
                @foreach ($new_contracts_by_type as $type => $count)
                    <tr><td>{{ ucwords(str_replace('_', ' ', $type)) }}</td><td>{{ $count }}</td></tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if (! empty($pipeline_summary))
        <h2>Contract Pipeline</h2>
        <table>
            <thead><tr><th>State</th><th>Count</th></tr></thead>
            <tbody>
                @foreach ($pipeline_summary as $state => $count)
                    <tr><td>{{ ucwords(str_replace('_', ' ', $state)) }}</td><td>{{ $count }}</td></tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if ($compliance_issues !== null)
        <h2>Compliance</h2>
        <p>Non-compliant findings across all contracts: <strong class="{{ $compliance_issues > 0 ? 'warning' : '' }}">{{ $compliance_issues }}</strong></p>
    @endif

    <div class="footer">
        CCRS — Contract & Merchant Agreement Repository System | Digittal Group
    </div>
</body>
</html>
