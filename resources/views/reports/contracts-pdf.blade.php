<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8" />
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; }
        h1 { font-size: 14px; margin-bottom: 4px; }
        p.meta { color: #666; font-size: 8px; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #1e3a5f; color: white; padding: 4px 6px; text-align: left; font-size: 8px; }
        td { padding: 3px 6px; border-bottom: 1px solid #e5e5e5; }
        tr:nth-child(even) { background: #f8f8f8; }
    </style>
</head>
<body>
    <h1>CCRS — Contracts Report</h1>
    <p class="meta">Generated: {{ $generatedAt }} &bull; Total: {{ $contracts->count() }}</p>
    <table>
        <thead>
            <tr><th>Title</th><th>Type</th><th>State</th><th>Counterparty</th><th>Region</th><th>Entity</th><th>Created</th></tr>
        </thead>
        <tbody>
            @foreach ($contracts as $c)
                <tr>
                    <td>{{ Str::limit($c->title, 40) }}</td>
                    <td>{{ $c->contract_type }}</td>
                    <td>{{ $c->workflow_state }}</td>
                    <td>{{ Str::limit($c->counterparty?->legal_name ?? '—', 25) }}</td>
                    <td>{{ $c->region?->name ?? '—' }}</td>
                    <td>{{ $c->entity?->name ?? '—' }}</td>
                    <td>{{ $c->created_at?->format('Y-m-d') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
