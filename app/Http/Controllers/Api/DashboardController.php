<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\FormOrder;
use App\Models\Invoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();
        return $user->isAdmin()
            ? $this->adminStats()
            : $this->branchStats($user->branch_id);
    }

    private function adminStats(): JsonResponse
    {
        $statusCounts = FormOrder::select('status', DB::raw('count(*) as total'))
            ->groupBy('status')->pluck('total', 'status');

        $branchActivity = Branch::active()->notMain()
            ->withCount(['formOrders as total_orders'])
            ->withCount(['formOrders as pending_orders' => fn ($q) => $q->pending()])
            ->withSum(['formOrders as total_billed' => fn ($q) => $q->billed()], 'total_amount')
            ->orderByDesc('total_orders')->get();

        $driver = DB::connection()->getDriverName();
        $monthExpr = $driver === 'sqlite'
            ? "strftime('%Y-%m', created_at)"
            : "DATE_FORMAT(created_at, '%Y-%m')";

        $monthlyBilling = Invoice::select(
            DB::raw("{$monthExpr} as month"),
            DB::raw('SUM(total_amount) as total'),
            DB::raw('COUNT(*) as count')
        )->groupBy('month')->orderBy('month')->limit(12)->get();

        return response()->json([
            'status_counts'   => $statusCounts,
            'branch_activity' => $branchActivity,
            'recent_orders'   => FormOrder::with(['branch', 'requester'])->latest()->limit(10)->get(),
            'monthly_billing' => $monthlyBilling,
            'urgent_pending'  => FormOrder::pending()->urgent()->with('branch')->latest()->get(),
            'totals' => [
                'orders_today'     => FormOrder::whereDate('created_at', today())->count(),
                'pending_count'    => $statusCounts['pending'] ?? 0,
                'total_billed_ytd' => Invoice::whereYear('created_at', now()->year)->sum('total_amount'),
                'active_branches'  => Branch::active()->notMain()->count(),
            ],
        ]);
    }

    private function branchStats(int $branchId): JsonResponse
    {
        $statusCounts = FormOrder::forBranch($branchId)
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')->pluck('total', 'status');

        return response()->json([
            'status_counts'  => $statusCounts,
            'recent_orders'  => FormOrder::forBranch($branchId)->with(['items.formType'])->latest()->limit(5)->get(),
            'totals' => [
                'pending_count'     => $statusCounts['pending'] ?? 0,
                'delivered_count'   => ($statusCounts['delivered'] ?? 0) + ($statusCounts['billed'] ?? 0),
                'total_billed'      => FormOrder::forBranch($branchId)->billed()->sum('total_amount'),
                'orders_this_month' => FormOrder::forBranch($branchId)->whereMonth('created_at', now()->month)->count(),
            ],
        ]);
    }
}
