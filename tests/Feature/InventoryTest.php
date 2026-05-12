<?php

use App\Models\Branch;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductOrder;
use App\Models\User;
use App\Services\InventoryService;

beforeEach(function () {
    $this->seed();

    $main = Branch::where('is_main_branch', true)->first();
    $this->admin = User::factory()->create([
        'branch_id' => $main->id,
        'role'      => User::ROLE_ADMIN,
    ]);

    $branch = Branch::where('is_main_branch', false)->first();
    $this->staff = User::factory()->create([
        'branch_id' => $branch->id,
        'role'      => User::ROLE_BRANCH_STAFF,
    ]);

    $this->product = Product::first();
    $this->inventory = Inventory::where('product_id', $this->product->id)->firstOrCreate([
        'product_id' => $this->product->id,
    ]);
});

// ─── Auto-create ──────────────────────────────────────────────────────────────

test('inventory record is auto-created when a product is created', function () {
    $product = Product::factory()->create();

    $this->assertDatabaseHas('inventories', ['product_id' => $product->id]);
});

// ─── Listing ──────────────────────────────────────────────────────────────────

test('admin can view the inventory page', function () {
    $this->actingAs($this->admin)
        ->get('/inventory')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Inventory')->has('inventories')->has('lowStockCount'));
});

test('branch staff cannot view inventory page', function () {
    $this->actingAs($this->staff)
        ->get('/inventory')
        ->assertForbidden();
});

// ─── Restock ──────────────────────────────────────────────────────────────────

test('admin can restock a product', function () {
    $this->inventory->update(['quantity_on_hand' => 10]);

    $this->actingAs($this->admin)
        ->post("/inventory/{$this->inventory->id}/restock", [
            'quantity' => 50,
            'notes'    => 'Received from supplier',
        ])
        ->assertRedirect();

    expect($this->inventory->fresh()->quantity_on_hand)->toBe(60);
});

test('restock creates a movement record', function () {
    $this->inventory->update(['quantity_on_hand' => 5]);

    $this->actingAs($this->admin)
        ->post("/inventory/{$this->inventory->id}/restock", ['quantity' => 20])
        ->assertRedirect();

    $movement = InventoryMovement::where('inventory_id', $this->inventory->id)->latest()->first();

    expect($movement->type)->toBe(InventoryMovement::TYPE_RESTOCK)
        ->and($movement->quantity_change)->toBe(20)
        ->and($movement->quantity_before)->toBe(5)
        ->and($movement->quantity_after)->toBe(25)
        ->and($movement->performed_by)->toBe($this->admin->id);
});

test('restock quantity must be at least 1', function () {
    $this->actingAs($this->admin)
        ->post("/inventory/{$this->inventory->id}/restock", ['quantity' => 0])
        ->assertSessionHasErrors('quantity');
});

test('branch staff cannot restock', function () {
    $this->actingAs($this->staff)
        ->post("/inventory/{$this->inventory->id}/restock", ['quantity' => 10])
        ->assertForbidden();
});

// ─── Adjust ───────────────────────────────────────────────────────────────────

test('admin can adjust stock quantity and reorder levels', function () {
    $this->actingAs($this->admin)
        ->patch("/inventory/{$this->inventory->id}/adjust", [
            'quantity_on_hand' => 100,
            'reorder_level'    => 20,
            'reorder_quantity' => 50,
            'notes'            => 'Physical count correction',
        ])
        ->assertRedirect();

    $inv = $this->inventory->fresh();
    expect($inv->quantity_on_hand)->toBe(100)
        ->and($inv->reorder_level)->toBe(20)
        ->and($inv->reorder_quantity)->toBe(50);
});

test('adjust creates an adjustment movement record', function () {
    $this->inventory->update(['quantity_on_hand' => 30]);

    $this->actingAs($this->admin)
        ->patch("/inventory/{$this->inventory->id}/adjust", [
            'quantity_on_hand' => 25,
            'reorder_level'    => 10,
            'reorder_quantity' => 30,
        ])
        ->assertRedirect();

    $movement = InventoryMovement::where('inventory_id', $this->inventory->id)
        ->where('type', InventoryMovement::TYPE_ADJUSTMENT)
        ->latest()->first();

    expect($movement->quantity_change)->toBe(-5)
        ->and($movement->quantity_before)->toBe(30)
        ->and($movement->quantity_after)->toBe(25);
});

test('adjusted quantity cannot be negative', function () {
    $this->actingAs($this->admin)
        ->patch("/inventory/{$this->inventory->id}/adjust", [
            'quantity_on_hand' => -1,
            'reorder_level'    => 0,
            'reorder_quantity' => 0,
        ])
        ->assertSessionHasErrors('quantity_on_hand');
});

// ─── Low stock detection ───────────────────────────────────────────────────────

test('isLowStock returns true when quantity is at or below reorder level', function () {
    $this->inventory->update(['quantity_on_hand' => 5, 'reorder_level' => 10]);

    expect($this->inventory->fresh()->isLowStock())->toBeTrue();
});

test('isLowStock returns false when quantity is above reorder level', function () {
    $this->inventory->update(['quantity_on_hand' => 50, 'reorder_level' => 10]);

    expect($this->inventory->fresh()->isLowStock())->toBeFalse();
});

test('isLowStock returns false when reorder level is zero', function () {
    $this->inventory->update(['quantity_on_hand' => 0, 'reorder_level' => 0]);

    expect($this->inventory->fresh()->isLowStock())->toBeFalse();
});

test('low stock count reflects correctly on inventory page', function () {
    $this->inventory->update(['quantity_on_hand' => 2, 'reorder_level' => 10]);

    $response = $this->actingAs($this->admin)->get('/inventory')->assertOk();

    expect($response->original->getData()['page']['props']['lowStockCount'])->toBeGreaterThanOrEqual(1);
});

// ─── Delivery deducts stock ───────────────────────────────────────────────────

test('delivering a product order deducts stock from inventory', function () {
    $this->inventory->update(['quantity_on_hand' => 100]);

    $this->actingAs($this->staff)->post('/product-orders', [
        'items' => [['product_id' => $this->product->id, 'quantity' => 10]],
    ]);

    $order = ProductOrder::where('requested_by', $this->staff->id)->latest()->firstOrFail();
    $order->update(['status' => ProductOrder::STATUS_APPROVED]);

    $this->actingAs($this->admin)
        ->patch("/product-orders/{$order->id}/deliver")
        ->assertRedirect();

    expect($this->inventory->fresh()->quantity_on_hand)->toBe(90);
});

test('delivery creates an order_fulfillment movement with the order reference', function () {
    $this->inventory->update(['quantity_on_hand' => 50]);

    $this->actingAs($this->staff)->post('/product-orders', [
        'items' => [['product_id' => $this->product->id, 'quantity' => 5]],
    ]);

    $order = ProductOrder::where('requested_by', $this->staff->id)->latest()->firstOrFail();
    $order->update(['status' => ProductOrder::STATUS_APPROVED]);

    $this->actingAs($this->admin)->patch("/product-orders/{$order->id}/deliver");

    $movement = InventoryMovement::where('inventory_id', $this->inventory->id)
        ->where('type', InventoryMovement::TYPE_ORDER_FULFILLMENT)
        ->latest()->first();

    expect($movement)->not->toBeNull()
        ->and($movement->quantity_change)->toBe(-5)
        ->and($movement->reference)->toBe($order->reference_number);
});

// ─── Movement log ─────────────────────────────────────────────────────────────

test('admin can view movement log for an inventory item', function () {
    $this->actingAs($this->admin)
        ->get("/inventory/{$this->inventory->id}/movements")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('InventoryMovements')->has('inventory')->has('movements'));
});

test('branch staff cannot view movement log', function () {
    $this->actingAs($this->staff)
        ->get("/inventory/{$this->inventory->id}/movements")
        ->assertForbidden();
});

// ─── InventoryService unit tests ───────────────────────────────────────────────

test('service restock adds quantity correctly', function () {
    $this->inventory->update(['quantity_on_hand' => 10]);

    app(InventoryService::class)->restock($this->inventory, 40, $this->admin, 'Batch delivery');

    expect($this->inventory->fresh()->quantity_on_hand)->toBe(50);
});

test('service adjust sets exact quantity', function () {
    $this->inventory->update(['quantity_on_hand' => 99]);

    app(InventoryService::class)->adjust($this->inventory, 30, $this->admin);

    expect($this->inventory->fresh()->quantity_on_hand)->toBe(30);
});

test('service restock with zero quantity aborts', function () {
    expect(fn () => app(InventoryService::class)->restock($this->inventory, 0, $this->admin))
        ->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
});

test('service adjust with negative quantity aborts', function () {
    expect(fn () => app(InventoryService::class)->adjust($this->inventory, -1, $this->admin))
        ->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
});
