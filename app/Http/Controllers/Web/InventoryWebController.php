<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class InventoryWebController extends Controller
{
    public function __construct(private readonly InventoryService $service) {}

    public function index(Request $request)
    {
        abort_unless($request->user()->isAdmin(), 403);

        $inventories = Inventory::with(['product', 'movements' => fn ($q) => $q->latest()->limit(5)->with('performer')])
            ->join('products', 'products.id', '=', 'inventories.product_id')
            ->whereNull('products.deleted_at')
            ->orderBy('products.name')
            ->select('inventories.*')
            ->get();

        return Inertia::render('Inventory', [
            'inventories' => $inventories,
            'lowStockCount' => $inventories->filter(fn ($i) => $i->isLowStock())->count(),
        ]);
    }

    public function restock(Request $request, Inventory $inventory)
    {
        abort_unless($request->user()->isAdmin(), 403);

        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
            'notes'    => ['nullable', 'string', 'max:500'],
        ]);

        $this->service->restock($inventory, $data['quantity'], $request->user(), $data['notes'] ?? null);

        return back()->with('success', 'Stock restocked successfully.');
    }

    public function adjust(Request $request, Inventory $inventory)
    {
        abort_unless($request->user()->isAdmin(), 403);

        $data = $request->validate([
            'quantity_on_hand' => ['required', 'integer', 'min:0'],
            'reorder_level'    => ['required', 'integer', 'min:0'],
            'reorder_quantity' => ['required', 'integer', 'min:0'],
            'notes'            => ['nullable', 'string', 'max:500'],
        ]);

        $this->service->adjust($inventory, $data['quantity_on_hand'], $request->user(), $data['notes'] ?? null);
        $this->service->updateLevels($inventory, $data['reorder_level'], $data['reorder_quantity']);

        return back()->with('success', 'Inventory updated.');
    }

    public function movements(Request $request, Inventory $inventory)
    {
        abort_unless($request->user()->isAdmin(), 403);

        $movements = $inventory->movements()
            ->with('performer')
            ->latest()
            ->paginate(30);

        return Inertia::render('InventoryMovements', [
            'inventory' => $inventory->load('product'),
            'movements' => $movements,
        ]);
    }
}
