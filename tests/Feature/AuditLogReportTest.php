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

    $this->product   = Product::first();
    $this->inventory = Inventory::firstOrCreate(['product_id' => $this->product->id]);
    $this->service   = app(InventoryService::class);
});

// ─── Access control ──────────────────────────────────────────────────────────

test('admin can view audit log report', function () {
    $this->actingAs($this->admin)
        ->get('/reports?type=audit-logs')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Reports')
            ->where('type', 'audit-logs')
            ->has('results')
            ->has('summary')
        );
});

test('branch staff cannot view reports', function () {
    $this->actingAs($this->staff)
        ->get('/reports?type=audit-logs')
        ->assertForbidden();
});

// ─── Data presence ───────────────────────────────────────────────────────────

test('audit log report includes restock movements', function () {
    $this->service->restock($this->inventory, 50, $this->admin, 'Test restock');

    $response = $this->actingAs($this->admin)
        ->get('/reports?type=audit-logs&from=' . now()->toDateString() . '&to=' . now()->toDateString())
        ->assertOk();

    $results = $response->original->getData()['page']['props']['results'];
    $entry   = collect($results)->firstWhere('type', 'restock');

    expect($entry)->not->toBeNull()
        ->and($entry['quantity_change'])->toBe(50)
        ->and($entry['notes'])->toBe('Test restock')
        ->and($entry['performed_by'])->toBe($this->admin->name);
});

test('audit log report includes adjustment movements', function () {
    $this->inventory->update(['quantity_on_hand' => 20]);
    $this->service->adjust($this->inventory, 10, $this->admin, 'Physical count');

    $response = $this->actingAs($this->admin)
        ->get('/reports?type=audit-logs&from=' . now()->toDateString() . '&to=' . now()->toDateString())
        ->assertOk();

    $results = $response->original->getData()['page']['props']['results'];
    $entry   = collect($results)->firstWhere('type', 'adjustment');

    expect($entry)->not->toBeNull()
        ->and($entry['quantity_change'])->toBe(-10)
        ->and($entry['quantity_before'])->toBe(20)
        ->and($entry['quantity_after'])->toBe(10);
});

test('audit log report includes order fulfillment movements', function () {
    $this->inventory->update(['quantity_on_hand' => 100]);

    $this->actingAs($this->staff)->post('/product-orders', [
        'items' => [['product_id' => $this->product->id, 'quantity' => 7]],
    ]);
    $order = ProductOrder::where('requested_by', $this->staff->id)->latest()->firstOrFail();
    $order->update(['status' => ProductOrder::STATUS_APPROVED]);
    $this->actingAs($this->admin)->patch("/product-orders/{$order->id}/deliver");

    $response = $this->actingAs($this->admin)
        ->get('/reports?type=audit-logs&from=' . now()->toDateString() . '&to=' . now()->toDateString())
        ->assertOk();

    $results = $response->original->getData()['page']['props']['results'];
    $entry   = collect($results)->firstWhere('type', 'order_fulfillment');

    expect($entry)->not->toBeNull()
        ->and($entry['quantity_change'])->toBe(-7)
        ->and($entry['reference'])->toBe($order->reference_number);
});

// ─── Summary block ───────────────────────────────────────────────────────────

test('summary counts each movement type correctly', function () {
    $this->inventory->update(['quantity_on_hand' => 200]);

    $this->service->restock($this->inventory, 10, $this->admin);
    $this->service->restock($this->inventory, 20, $this->admin);
    $this->service->adjust($this->inventory, 100, $this->admin);

    $this->actingAs($this->staff)->post('/product-orders', [
        'items' => [['product_id' => $this->product->id, 'quantity' => 5]],
    ]);
    $order = ProductOrder::where('requested_by', $this->staff->id)->latest()->firstOrFail();
    $order->update(['status' => ProductOrder::STATUS_APPROVED]);
    $this->actingAs($this->admin)->patch("/product-orders/{$order->id}/deliver");

    $response = $this->actingAs($this->admin)
        ->get('/reports?type=audit-logs&from=' . now()->toDateString() . '&to=' . now()->toDateString())
        ->assertOk();

    $summary = $response->original->getData()['page']['props']['summary'];

    expect($summary['restocks'])->toBe(2)
        ->and($summary['adjustments'])->toBe(1)
        ->and($summary['fulfillments'])->toBe(1)
        ->and($summary['total'])->toBe(4)
        ->and($summary['total_restocked'])->toBe(30)
        ->and($summary['total_fulfilled'])->toBe(5);
});

// ─── Date filtering ───────────────────────────────────────────────────────────

test('audit log report only returns movements in the requested date range', function () {
    $this->service->restock($this->inventory, 10, $this->admin, 'Today restock');

    $yesterday = now()->subDay()->toDateString();

    $response = $this->actingAs($this->admin)
        ->get("/reports?type=audit-logs&from={$yesterday}&to={$yesterday}")
        ->assertOk();

    $results = $response->original->getData()['page']['props']['results'];

    expect(collect($results)->where('notes', 'Today restock')->count())->toBe(0);
});

test('audit log report returns movements within date range', function () {
    $this->service->restock($this->inventory, 15, $this->admin, 'Range test');

    $today = now()->toDateString();

    $response = $this->actingAs($this->admin)
        ->get("/reports?type=audit-logs&from={$today}&to={$today}")
        ->assertOk();

    $results = $response->original->getData()['page']['props']['results'];

    expect(collect($results)->where('notes', 'Range test')->count())->toBe(1);
});

// ─── Product info ─────────────────────────────────────────────────────────────

test('each audit log entry includes product name and code', function () {
    $this->service->restock($this->inventory, 5, $this->admin);

    $response = $this->actingAs($this->admin)
        ->get('/reports?type=audit-logs&from=' . now()->toDateString() . '&to=' . now()->toDateString())
        ->assertOk();

    $results = $response->original->getData()['page']['props']['results'];
    $entry   = collect($results)->first();

    expect($entry['product'])->toBe($this->product->name)
        ->and($entry['product_code'])->toBe($this->product->code);
});

// ─── PDF export ───────────────────────────────────────────────────────────────

test('admin can export audit log report as PDF', function () {
    $this->actingAs($this->admin)
        ->get('/reports/export?type=audit-logs&from=' . now()->toDateString() . '&to=' . now()->toDateString())
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
});
