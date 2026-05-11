<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\FormOrder;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardWebController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        if ($user->isAdmin()) {
            $statusCounts = FormOrder::select('status', DB::raw('count(*) as total'))
                ->groupBy('status')->pluck('total', 'status');

            $stats = [
                'status_counts'  => $statusCounts,
                'recent_orders'  => FormOrder::with(['branch', 'requester'])->latest()->limit(10)->get(),
                'urgent_pending' => FormOrder::pending()->urgent()->with('branch')->latest()->get(),
                'totals' => [
                    'orders_today'     => FormOrder::whereDate('created_at', today())->count(),
                    'pending_count'    => $statusCounts['pending'] ?? 0,
                    'total_billed_ytd' => Invoice::whereYear('created_at', now()->year)->sum('total_amount'),
                    'active_branches'  => Branch::active()->notMain()->count(),
                ],
            ];
        } else {
            $branchId = $user->branch_id;
            $statusCounts = FormOrder::forBranch($branchId)
                ->select('status', DB::raw('count(*) as total'))
                ->groupBy('status')->pluck('total', 'status');

            $stats = [
                'status_counts' => $statusCounts,
                'recent_orders' => FormOrder::forBranch($branchId)->with(['items.formType'])->latest()->limit(5)->get(),
                'totals' => [
                    'pending_count'     => $statusCounts['pending'] ?? 0,
                    'delivered_count'   => ($statusCounts['delivered'] ?? 0) + ($statusCounts['billed'] ?? 0),
                    'total_billed'      => FormOrder::forBranch($branchId)->billed()->sum('total_amount'),
                    'orders_this_month' => FormOrder::forBranch($branchId)->whereMonth('created_at', now()->month)->count(),
                ],
            ];
        }

        return Inertia::render('Dashboard', [
            'stats'   => $stats,
            'isAdmin' => $user->isAdmin(),
        ]);
    }
}
