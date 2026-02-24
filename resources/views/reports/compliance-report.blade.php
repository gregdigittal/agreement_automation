<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Compliance Report — {{ $contract->title }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #333; margin: 20px; }
        h1 { color: #1e40af; font-size: 18px; border-bottom: 2px solid #1e40af; padding-bottom: 8px; }
        h2 { color: #374151; font-size: 14px; margin-top: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th { background-color: #f3f4f6; text-align: left; padding: 6px; border-bottom: 2px solid #d1d5db; font-size: 10px; }
        td { padding: 6px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        .compliant { color: #16a34a; font-weight: bold; }
        .non_compliant { color: #dc2626; font-weight: bold; }
        .unclear { color: #d97706; font-weight: bold; }
        .not_applicable { color: #6b7280; }
        .score { font-size: 24px; font-weight: bold; }
        .footer { margin-top: 32px; font-size: 10px; color: #9ca3af; text-align: center; }
    </style>
</head>
<body>
    <h1>Compliance Report</h1>
    <p><strong>Contract:</strong> {{ $contract->title }}</p>
    <p><strong>Counterparty:</strong> {{ $contract->counterparty?->name ?? 'N/A' }}</p>
    <p><strong>Entity:</strong> {{ $contract->entity?->name ?? 'N/A' }} | <strong>Region:</strong> {{ $contract->region?->name ?? 'N/A' }}</p>
    <p><strong>Generated:</strong> {{ $generated_at }}</p>

    @foreach ($findings as $frameworkId => $frameworkFindings)
        @php
            $framework = $frameworkFindings->first()?->framework;
            $score = $scores[$frameworkId] ?? null;
        @endphp

        <h2>{{ $framework?->framework_name ?? 'Unknown Framework' }} ({{ $framework?->jurisdiction_code ?? '' }})</h2>

        @if ($score)
            <p>
                Compliance Score: <span class="score {{ $score['score'] >= 80 ? 'compliant' : ($score['score'] >= 50 ? 'unclear' : 'non_compliant') }}">{{ $score['score'] }}%</span>
                &mdash; {{ $score['compliant'] }} compliant, {{ $score['non_compliant'] }} non-compliant, {{ $score['unclear'] }} unclear, {{ $score['not_applicable'] }} N/A out of {{ $score['total'] }} requirements
            </p>
        @endif

        <table>
            <thead>
                <tr>
                    <th style="width:8%">ID</th>
                    <th style="width:25%">Requirement</th>
                    <th style="width:10%">Status</th>
                    <th style="width:25%">Evidence</th>
                    <th style="width:25%">Rationale</th>
                    <th style="width:7%">Conf.</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($frameworkFindings as $finding)
                    <tr>
                        <td>{{ $finding->requirement_id }}</td>
                        <td>{{ $finding->requirement_text }}</td>
                        <td class="{{ $finding->status }}">{{ ucwords(str_replace('_', ' ', $finding->status)) }}</td>
                        <td>{{ $finding->evidence_clause ?? '—' }}</td>
                        <td>{{ $finding->ai_rationale ?? '—' }}</td>
                        <td>{{ $finding->confidence !== null ? round($finding->confidence * 100) . '%' : '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endforeach

    @if ($findings->isEmpty())
        <p>No compliance findings available. Run a compliance check to generate findings.</p>
    @endif

    <div class="footer">
        CCRS — Contract & Merchant Agreement Repository System | Digittal Group<br>
        This report is for informational purposes only and does not constitute legal advice.
    </div>
</body>
</html>
