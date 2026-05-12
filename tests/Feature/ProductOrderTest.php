<?php

use App\Models\Branch;
use App\Models\Product;
use App\Models\ProductOrder;
use App\Models\User;

beforeEach(function () {
    $this->seed();
    $main = Branch::where('is_main_branch', true)->first();

    $this->admin = User::factory()->create([
        'branch_id' => $main->id,
        'role'      => User::ROLE_ADMIN,
    ]);

    $this->branch = Branch::where('is_main_branch', false)->first();
    $this->staff  = User::factory()->create([
        'branch_id' => $this->branch->id,
        'role'      => User::ROLE_BRANCH_STAFF,
    ]);

    $this->product = Product::first();

    $this->createPendingOrder = function (): ProductOrder {
        $this->actingAs($this->staff)->post('/product-orders', [
            'items' => [['product_id' => $this->product->id, 'quantity' => 1]],
        ]);

        return ProductOrder::where('requested_by', $this->staff->id)->latest()->firstOrFail();
    };
});

// ─── Listing ──────────────────────────────────────────────────────────────────

test('admin can view all product orders', function () {
    $this->actingAs($this->admin)
        ->get('/product-orders')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('ProductOrders')->has('orders')->has('products'));
});

test('branch staff can view product orders page', function () {
    $this->actingAs($this->staff)
        ->get('/product-orders')
        ->assertOk();
});

// ─── Create ───────────────────────────────────────────────────────────────────

test('branch staff can submit a product order without customizations', function () {
    $this->actingAs($this->staff)
        ->post('/product-orders', [
            'priority' => 'normal',
            'items'    => [
                ['product_id' => $this->product->id, 'quantity' => 5],
            ],
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('product_orders', [
        'branch_id'    => $this->staff->branch_id,
        'requested_by' => $this->staff->id,
        'status'       => 'pending',
    ]);
});

test('branch staff can submit a product order with customizations', function () {
    $product = Product::factory()->withCustomizations()->create();

    $this->actingAs($this->staff)
        ->post('/product-orders', [
            'priority' => 'urgent',
            'items'    => [
                [
                    'product_id'     => $product->id,
                    'quantity'       => 3,
                    'customizations' => ['color' => 'blue'],
                ],
            ],
        ])
        ->assertRedirect();

    $order = ProductOrder::where('requested_by', $this->staff->id)->latest()->first();
    $item  = $order->items->first();

    expect($item->customizations)->toBe(['color' => 'blue']);
});

test('order reference number is auto-generated', function () {
    $this->actingAs($this->staff)
        ->post('/product-orders', [
            'items' => [['product_id' => $this->product->id, 'quantity' => 1]],
        ])
        ->assertRedirect();

    $order = ProductOrder::where('requested_by', $this->staff->id)->latest()->first();

    expect($order->reference_number)->toStartWith('PO-');
});

test('order line total is calculated from product unit price', function () {
    $this->actingAs($this->staff)
        ->post('/product-orders', [
            'items' => [['product_id' => $this->product->id, 'quantity' => 4]],
        ])
        ->assertRedirect();

    $order = ProductOrder::where('requested_by', $this->staff->id)->latest()->first();
    $item  = $order->items->first();

    expect((float) $item->line_total)->toBe((float) $this->product->unit_price * 4);
});

test('order total is sum of line totals', function () {
    $this->actingAs($this->staff)
        ->post('/product-orders', [
            'items' => [['product_id' => $this->product->id, 'quantity' => 2]],
        ])
        ->assertRedirect();

    $order = ProductOrder::where('requested_by', $this->staff->id)->latest()->first();

    expect((float) $order->total_amount)->toBe((float) $this->product->unit_price * 2);
});

test('items array is required', function () {
    $this->actingAs($this->staff)
        ->post('/product-orders', ['priority' => 'normal'])
        ->assertSessionHasErrors('items');
});

test('product must exist when ordering', function () {
    $this->actingAs($this->staff)
        ->post('/product-orders', [
            'items' => [['product_id' => 99999, 'quantity' => 1]],
        ])
        ->assertSessionHasErrors('items.0.product_id');
});

// ─── Approve ──────────────────────────────────────────────────────────────────

test('admin can approve a pending product order', function () {
    $order = ($this->createPendingOrder)();

    $this->actingAs($this->admin)
        ->patch("/product-orders/{$order->id}/approve")
        ->assertRedirect();

    expect($order->fresh()->status)->toBe('approved');
});

test('branch staff cannot approve an order', function () {
    $order = ($this->createPendingOrder)();

    $this->actingAs($this->staff)
        ->patch("/product-orders/{$order->id}/approve")
        ->assertForbidden();
});

test('cannot approve an already approved order', function () {
    $order = ($this->createPendingOrder)();
    $order->update(['status' => ProductOrder::STATUS_APPROVED]);

    $this->actingAs($this->admin)
        ->patch("/product-orders/{$order->id}/approve")
        ->assertStatus(422);
});

// ─── Reject ───────────────────────────────────────────────────────────────────

test('admin can reject a pending product order', function () {
    $order = ($this->createPendingOrder)();

    $this->actingAs($this->admin)
        ->patch("/product-orders/{$order->id}/reject", ['rejection_reason' => 'Out of budget.'])
        ->assertRedirect();

    $order->refresh();
    expect($order->status)->toBe('rejected')
        ->and($order->rejection_reason)->toBe('Out of budget.');
});

test('branch staff cannot reject an order', function () {
    $order = ($this->createPendingOrder)();

    $this->actingAs($this->staff)
        ->patch("/product-orders/{$order->id}/reject")
        ->assertForbidden();
});

// ─── Deliver ──────────────────────────────────────────────────────────────────

test('admin can mark an approved product order as delivered', function () {
    $order = ($this->createPendingOrder)();
    $order->update(['status' => ProductOrder::STATUS_APPROVED]);

    $this->actingAs($this->admin)
        ->patch("/product-orders/{$order->id}/deliver")
        ->assertRedirect();

    expect($order->fresh()->status)->toBe('delivered');
});

test('cannot deliver a pending order', function () {
    $order = ($this->createPendingOrder)();

    $this->actingAs($this->admin)
        ->patch("/product-orders/{$order->id}/deliver")
        ->assertStatus(422);
});

// ─── Branch scoping ───────────────────────────────────────────────────────────

test('branch staff only see their own branch orders', function () {
    $otherBranch = Branch::where('is_main_branch', true)->first();
    $otherStaff  = User::factory()->create(['branch_id' => $otherBranch->id, 'role' => User::ROLE_BRANCH_STAFF]);

    $this->actingAs($otherStaff)->post('/product-orders', [
        'items' => [['product_id' => $this->product->id, 'quantity' => 1]],
    ]);

    $response = $this->actingAs($this->staff)
        ->get('/product-orders')
        ->assertOk();

    $orders = $response->original->getData()['page']['props']['orders']['data'];

    foreach ($orders as $order) {
        expect($order['branch_id'])->toBe($this->staff->branch_id);
    }
});
