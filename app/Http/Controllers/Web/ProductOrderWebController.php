<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductOrder;
use App\Services\ProductOrderService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProductOrderWebController extends Controller
{
    public function __construct(private readonly ProductOrderService $service) {}

    public function index(Request $request)
    {
        $user  = $request->user();
        $query = ProductOrder::with(['branch', 'requester', 'items.product'])->latest();

        if ($user->isBranchUser()) {
            $query->forBranch($user->branch_id);
        }

        return Inertia::render('ProductOrders', [
            'orders'   => $query->paginate(20)->withQueryString(),
            'products' => Product::active()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'priority'              => ['sometimes', 'string', 'in:low,normal,urgent'],
            'notes'                 => ['nullable', 'string'],
            'needed_by'             => ['nullable', 'date'],
            'items'                 => ['required', 'array', 'min:1'],
            'items.*.product_id'    => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity'      => ['required', 'integer', 'min:1'],
            'items.*.customizations' => ['nullable', 'array'],
            'items.*.notes'         => ['nullable', 'string'],
        ]);

        $order = $this->service->create($data, $request->user());

        return back()->with('success', "Product order {$order->reference_number} submitted.");
    }

    public function approve(Request $request, ProductOrder $productOrder)
    {
        abort_unless($request->user()->isAdmin(), 403);

        $this->service->approve($productOrder, $request->user());

        return back()->with('success', 'Order approved.');
    }

    public function reject(Request $request, ProductOrder $productOrder)
    {
        abort_unless($request->user()->isAdmin(), 403);

        $data = $request->validate([
            'rejection_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->service->reject($productOrder, $request->user(), $data['rejection_reason'] ?? '');

        return back()->with('success', 'Order rejected.');
    }

    public function deliver(Request $request, ProductOrder $productOrder)
    {
        abort_unless($request->user()->isAdmin(), 403);

        $this->service->deliver($productOrder, $request->user());

        return back()->with('success', 'Order marked as delivered.');
    }
}
