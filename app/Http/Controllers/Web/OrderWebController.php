<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFormOrderRequest;
use App\Models\FormOrder;
use App\Models\FormType;
use App\Services\FormOrderService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrderWebController extends Controller
{
    public function __construct(private readonly FormOrderService $service) {}

    public function index(Request $request): Response
    {
        $user  = $request->user();
        $query = FormOrder::with(['branch', 'requester', 'items.formType'])->latest();

        if ($user->isBranchUser()) {
            $query->forBranch($user->branch_id);
        }

        if ($request->filled('status'))   $query->where('status', $request->status);
        if ($request->filled('priority')) $query->where('priority', $request->priority);
        if ($request->filled('search'))   $query->where('reference_number', 'like', "%{$request->search}%");

        return Inertia::render('Orders', [
            'orders'    => $query->paginate(20)->withQueryString(),
            'filters'   => $request->only('status', 'priority', 'search'),
            'isAdmin'   => $user->isAdmin(),
            'formTypes' => FormType::active()->orderBy('name')->get(),
        ]);
    }

    public function store(StoreFormOrderRequest $request)
    {
        $this->service->create(
            array_merge($request->validated(), ['branch_id' => $request->user()->branch_id]),
            $request->user()
        );

        return redirect('/orders')->with('success', 'Order submitted successfully.');
    }

    public function approve(Request $request, FormOrder $formOrder)
    {
        $this->authorize('approve', $formOrder);
        $this->service->approve($formOrder, $request->user());
        return back()->with('success', 'Order approved.');
    }

    public function reject(Request $request, FormOrder $formOrder)
    {
        $this->authorize('reject', $formOrder);
        $request->validate(['reason' => ['nullable', 'string', 'max:500']]);
        $this->service->reject($formOrder, $request->user(), $request->reason ?? '');
        return back()->with('success', 'Order rejected.');
    }

    public function deliver(Request $request, FormOrder $formOrder)
    {
        $this->authorize('deliver', $formOrder);
        $this->service->deliver($formOrder, $request->user());
        return back()->with('success', 'Order marked as delivered.');
    }
}
