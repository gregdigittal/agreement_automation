<?php

namespace App\Jobs;

use App\Models\ComplianceFinding;
use App\Models\Contract;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class GenerateWeeklyReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 120;

    public function handle(): void
    {
        if (! config('features.advanced_analytics', false)) {
            Log::info('Weekly report skipped: advanced_analytics feature is disabled.');

            return;
        }

        $reportData = $this->compileReportData();

        $pdf = Pdf::loadView('reports.weekly-summary', $reportData);
        $pdfContent = $pdf->output();

        $filename = 'reports/weekly/ccrs-weekly-report-'.now()->format('Y-m-d').'.pdf';
        Storage::disk('s3')->put($filename, $pdfContent);

        $recipients = User::whereHas('roles', function ($q) {
            $q->whereIn('name', ['system_admin', 'legal']);
        })->get();

        foreach ($recipients as $user) {
            $prefs = $user->notification_preferences ?? [];
            $emailEnabled = $prefs['email'] ?? true;

            if ($emailEnabled) {
                Mail::send('emails.weekly-report', $reportData, function ($message) use ($user, $pdfContent, $filename) {
                    $message->to($user->email)
                        ->subject('CCRS Weekly Report — '.now()->format('d M Y'))
                        ->attachData($pdfContent, basename($filename), [
                            'mime' => 'application/pdf',
                        ]);
                });
            }
        }

        Log::info("Weekly report generated and sent to {$recipients->count()} recipients.", [
            'storage_path' => $filename,
        ]);
    }

    private function compileReportData(): array
    {
        $now = now();
        $weekStart = $now->copy()->subWeek()->startOfWeek();
        $weekEnd = $now->copy()->subWeek()->endOfWeek();

        return [
            'report_date' => $now->format('d M Y'),
            'period_start' => $weekStart->format('d M Y'),
            'period_end' => $weekEnd->format('d M Y'),

            'new_contracts' => Contract::whereBetween('created_at', [$weekStart, $weekEnd])->count(),
            'new_contracts_by_type' => Contract::whereBetween('created_at', [$weekStart, $weekEnd])
                ->select('contract_type', DB::raw('COUNT(*) as count'))
                ->groupBy('contract_type')
                ->pluck('count', 'contract_type')
                ->toArray(),

            'expiring_contracts' => Contract::where('end_date', '>=', $now)
                ->where('end_date', '<=', $now->copy()->addDays(30))
                ->whereNotIn('workflow_state', ['cancelled', 'expired'])
                ->count(),

            'overdue_obligations' => DB::table('obligations_register')
                ->where('due_date', '<', $now)
                ->where('status', '!=', 'completed')
                ->count(),

            'open_escalations' => DB::table('escalations')
                ->where('status', 'open')
                ->count(),

            'compliance_issues' => config('features.regulatory_compliance', false)
                ? ComplianceFinding::where('status', 'non_compliant')->count()
                : null,

            'pipeline_summary' => Contract::select('workflow_state', DB::raw('COUNT(*) as count'))
                ->groupBy('workflow_state')
                ->pluck('count', 'workflow_state')
                ->toArray(),
        ];
    }
}
