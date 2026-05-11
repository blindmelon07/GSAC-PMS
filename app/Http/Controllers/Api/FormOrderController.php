<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFormOrderRequest;
use App\Models\FormOrder;
use App\Services\FormOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FormOrderController extends Controller
{
    public function __construct(private readonly FormOrderService $service) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', FormOrder::class);

        $user  = $request->user();
        $query = FormOrder::with(['branch', 'requester', 'items.formType'])->latest();

        if ($user->isBranchUser()) {
            $query->forBranch($user->branch_id);
        }

        $request->whenFilled('status',   fn ($v) => $query->where('status', $v));
        $request->whenFilled('priority', fn ($v) => $query->where('priority', $v));
        $request->whenFilled('from',     fn ($v) => $query->whereDate('created_at', '>=', $v));
        $request->whenFilled('to',       fn ($v) => $query->whereDate('created_at', '<=', $v));
        $request->whenFilled('search',   fn ($v) => $query->where('reference_number', 'like', "%{$v}%"));

        if ($request->filled('branch_id') && $user->isAdmin()) {
            $query->forBranch($request->branch_id);
        }

        return response()->json($query->paginate($request->integer('per_page', 20)));
    }

    public function store(StoreFormOrderRequest $request): JsonResponse
    {
        $order = $this->service->create(
            array_merge($request->validated(), ['branch_id' => $request->user()->branch_id]),
            $request->user()
        );

        return response()->json(['message' => 'Form order submitted successfully.', 'data' => $order], 201);
    }

    public function show(FormOrder $formOrder): JsonResponse
    {
        $this->authorize('view', $formOrder);

        return response()->json(
            $formOrder->load(['branch', 'requester', 'approver', 'deliverer', 'biller', 'items.formType', 'invoice'])
        );
    }

    public function destroy(FormOrder $formOrder): JsonResponse
    {
        $this->authorize('delete', $formOrder);
        $formOrder->delete();
        return response()->json(['message' => 'Order cancelled successfully.']);
    }

    public function approve(Request $request, FormOrder $formOrder): JsonResponse
    {
        $this->authorize('approve', $formOrder);
        $order = $this->service->approve($formOrder, $request->user());
        return response()->json(['message' => 'Order approved.', 'data' => $order]);
    }

    public function reject(Request $request, FormOrder $formOrder): JsonResponse
    {
        $this->authorize('reject', $formOrder);
        $request->validate(['reason' => ['nullable', 'string', 'max:500']]);
        $order = $this->service->reject($formOrder, $request->user(), $request->reason ?? '');
        return response()->json(['message' => 'Order rejected.', 'data' => $order]);
    }

    public function deliver(Request $request, FormOrder $formOrder): JsonResponse
    {
        $this->authorize('deliver', $formOrder);
        $order = $this->service->deliver($formOrder, $request->user());
        return response()->json(['message' => 'Order marked as delivered.', 'data' => $order]);
    }
}
