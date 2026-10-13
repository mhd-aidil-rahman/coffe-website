<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\FinancialReporting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class OwnerReportController extends Controller
{
    public function __construct(private FinancialReporting $reports) {}

    public function dashboard(): JsonResponse
    {
        return response()->json(['data' => $this->reports->dashboard()]);
    }

    public function financial(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => ['sometimes', 'required_with:end_date', 'date_format:Y-m-d'],
            'end_date' => ['sometimes', 'required_with:start_date', 'date_format:Y-m-d', 'after_or_equal:start_date'],
        ]);
        $start = isset($validated['start_date']) ? Carbon::createFromFormat('Y-m-d', $validated['start_date'])->startOfDay() : now()->subDays(29)->startOfDay();
        $end = isset($validated['end_date']) ? Carbon::createFromFormat('Y-m-d', $validated['end_date'])->endOfDay() : now()->endOfDay();
        $report = $this->reports->report($start, $end);

        return response()->json(['data' => [
            'period' => ['start_date' => $start->toDateString(), 'end_date' => $end->toDateString()],
            'summary' => ['income' => $report['income'], 'order_income' => $report['order_income'], 'bar_income' => $report['bar_income'], 'expenses' => $report['expenses'], 'net' => $report['profit']],
            'payments_by_method' => $report['payments_by_method'], 'expenses_by_category' => $report['expenses_by_category'],
            'daily_trends' => $report['daily_trends'], 'recent_orders' => $report['recent_orders'], 'recent_bar_transactions' => $report['recent_bar_transactions'], 'cancellations' => $report['cancellations'],
        ]]);
    }
}
