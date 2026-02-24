<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>CCRS Analytics Snapshot</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #333; margin: 20px; }
        h1 { color: #1e40af; font-size: 20px; border-bottom: 2px solid #1e40af; padding-bottom: 8px; }
        h2 { color: #374151; font-size: 16px; margin-top: 24px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th { background-color: #f3f4f6; text-align: left; padding: 8px; border-bottom: 2px solid #d1d5db; }
        td { padding: 8px; border-bottom: 1px solid #e5e7eb; }
        .footer { margin-top: 32px; font-size: 10px; color: #9ca3af; text-align: center; }
    </style>
</head>
<body>
    <h1>CCRS Analytics Snapshot</h1>
    <p>Generated: {{ $generated_at }}</p>

    <h2>Contract Pipeline</h2>
    <table>
        <thead><tr><th>State</th><th>Count</th></tr></thead>
        <tbody>
            @foreach ($pipeline as $state => $count)
                <tr><td>{{ ucwords(str_replace('_', ' ', $state)) }}</td><td>{{ $count }}</td></tr>
            @endforeach
        </tbody>
    </table>

    <h2>Risk Distribution by Region</h2>
    <table>
        <thead><tr><th>Region</th><th>Risk Level</th><th>Count</th></tr></thead>
        <tbody>
            @foreach ($risk_distribution as $row)
                <tr>
                    <td>{{ $row->region_name }}</td>
                    <td>{{ ucfirst($row->risk_level) }}</td>
                    <td>{{ $row->count }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">CCRS — Contract & Merchant Agreement Repository System | Digittal Group</div>
</body>
</html>
