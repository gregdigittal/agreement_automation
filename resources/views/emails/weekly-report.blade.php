@component('mail::message')
# CCRS Weekly Report

**Period:** {{ $period_start }} -- {{ $period_end }}

## Summary

- **New Contracts:** {{ $new_contracts }}
- **Expiring Contracts (next 30 days):** {{ $expiring_contracts }}
- **Overdue Obligations:** {{ $overdue_obligations }}
- **Open Escalations:** {{ $open_escalations }}
@if ($compliance_issues !== null)
- **Non-Compliant Findings:** {{ $compliance_issues }}
@endif

The full PDF report is attached to this email.

@component('mail::button', ['url' => config('app.url') . '/admin/analytics-dashboard'])
View Dashboard
@endcomponent

Regards,
CCRS System
@endcomponent
