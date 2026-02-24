<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ComplianceFinding;
use App\Models\Contract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    public function pipeline(Request $request): JsonResponse
    {
        $query = Contract::query();

        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->input('date_to'));
        }
        if ($request->filled('region_id')) {
            $query->where('region_id', $request->input('region_id'));
        }
        if ($request->filled('entity_id')) {
            $query->where('entity_id', $request->input('entity_id'));
        }
        if ($request->filled('contract_type')) {
            $query->where('contract_type', $request->input('contract_type'));
        }

        $pipeline = $query->select('workflow_state', DB::raw('COUNT(*) as count'))
            ->groupBy('workflow_state')
            ->orderByRaw("FIELD(workflow_state, 'draft', 'in_review', 'pending_approval', 'signing', 'executed', 'archived', 'cancelled', 'expired')")
            ->get();

        return response()->json(['data' => $pipeline]);
    }

    public function riskDistribution(): JsonResponse
    {
        $results = DB::table('contracts')
            ->join('regions', 'contracts.region_id', '=', 'regions.id')
            ->leftJoin('ai_analysis_results', function ($join) {
                $join->on('ai_analysis_results.contract_id', '=', 'contracts.id')
                    ->where('ai_analysis_results.analysis_type', '=', 'risk');
            })
            ->select(
                'regions.name as region_name',
                DB::raw("COALESCE(JSON_UNQUOTE(JSON_EXTRACT(ai_analysis_results.result, '$.risk_level')), 'unscored') as risk_level"),
                DB::raw('COUNT(*) as count')
            )
            ->whereNotIn('contracts.workflow_state', ['cancelled'])
            ->groupBy('regions.name', 'risk_level')
            ->orderBy('regions.name')
            ->get();

        return response()->json(['data' => $results]);
    }

    public function complianceOverview(): JsonResponse
    {
        if (! config('features.regulatory_compliance', false)) {
            return response()->json(['error' => 'Regulatory compliance feature is not enabled'], 404);
        }

        $aggregates = ComplianceFinding::select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->get();

        $frameworkStats = DB::table('compliance_findings')
            ->join('regulatory_frameworks', 'compliance_findings.framework_id', '=', 'regulatory_frameworks.id')
            ->select(
                'regulatory_frameworks.framework_name',
                'regulatory_frameworks.id as framework_id',
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN compliance_findings.status = 'non_compliant' THEN 1 ELSE 0 END) as non_compliant_count"),
                DB::raw("SUM(CASE WHEN compliance_findings.status = 'compliant' THEN 1 ELSE 0 END) as compliant_count")
            )
            ->groupBy('regulatory_frameworks.id', 'regulatory_frameworks.framework_name')
            ->orderByRaw("SUM(CASE WHEN compliance_findings.status = 'non_compliant' THEN 1 ELSE 0 END) DESC")
            ->get();

        return response()->json([
            'data' => [
                'aggregates' => $aggregates,
                'framework_stats' => $frameworkStats,
            ],
        ]);
    }

    public function obligationsTimeline(Request $request): JsonResponse
    {
        $query = DB::table('obligations_register')
            ->join('contracts', 'obligations_register.contract_id', '=', 'contracts.id')
            ->select(
                'obligations_register.id',
                'obligations_register.obligation_type',
                'obligations_register.description',
                'obligations_register.due_date',
                'obligations_register.status',
                'contracts.title as contract_title'
            );

        if ($request->filled('obligation_type')) {
            $query->where('obligations_register.obligation_type', $request->input('obligation_type'));
        }
        if ($request->filled('status')) {
            $query->where('obligations_register.status', $request->input('status'));
        }
        if ($request->filled('date_from')) {
            $query->where('obligations_register.due_date', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->where('obligations_register.due_date', '<=', $request->input('date_to'));
        }

        $obligations = $query->orderBy('obligations_register.due_date')->limit(100)->get();

        return response()->json(['data' => $obligations]);
    }

    public function aiCosts(): JsonResponse
    {
        $dailyUsage = DB::table('ai_analysis_results')
            ->select(
                DB::raw('DATE(created_at) as day'),
                DB::raw('SUM(JSON_UNQUOTE(JSON_EXTRACT(result, "$.usage.input_tokens"))) as input_tokens'),
                DB::raw('SUM(JSON_UNQUOTE(JSON_EXTRACT(result, "$.usage.output_tokens"))) as output_tokens'),
                DB::raw('COUNT(*) as analysis_count')
            )
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        $totalInputTokens = $dailyUsage->sum('input_tokens');
        $totalOutputTokens = $dailyUsage->sum('output_tokens');
        $totalAnalyses = $dailyUsage->sum('analysis_count');
        $totalCost = ($totalInputTokens / 1_000_000 * 3.0) + ($totalOutputTokens / 1_000_000 * 15.0);

        return response()->json([
            'data' => [
                'daily' => $dailyUsage,
                'summary' => [
                    'total_input_tokens' => (int) $totalInputTokens,
                    'total_output_tokens' => (int) $totalOutputTokens,
                    'total_analyses' => (int) $totalAnalyses,
                    'total_cost_usd' => round($totalCost, 2),
                    'avg_cost_per_analysis' => $totalAnalyses > 0 ? round($totalCost / $totalAnalyses, 3) : 0,
                ],
            ],
        ]);
    }

    public function workflowPerformance(): JsonResponse
    {
        $metrics = DB::table('workflow_stage_actions')
            ->select(
                'stage_name',
                DB::raw('AVG(TIMESTAMPDIFF(HOUR, created_at, completed_at)) as avg_hours'),
                DB::raw('MAX(TIMESTAMPDIFF(HOUR, created_at, completed_at)) as max_hours'),
                DB::raw('MIN(TIMESTAMPDIFF(HOUR, created_at, completed_at)) as min_hours'),
                DB::raw('COUNT(*) as total_actions'),
                DB::raw('SUM(CASE WHEN completed_at > sla_deadline THEN 1 ELSE 0 END) as sla_breaches')
            )
            ->whereNotNull('completed_at')
            ->where('created_at', '>=', now()->subDays(90))
            ->groupBy('stage_name')
            ->orderByRaw('AVG(TIMESTAMPDIFF(HOUR, created_at, completed_at)) DESC')
            ->get();

        return response()->json(['data' => $metrics]);
    }
}
