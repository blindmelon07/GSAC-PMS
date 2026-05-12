<?php

use App\Models\Branch;
use App\Models\FormOrder;
use App\Models\FormType;
use App\Models\User;

beforeEach(function () {
    $this->seed();

    $mainBranch        = Branch::where('is_main_branch', true)->first();
    $this->branch      = Branch::where('code', 'BR-001')->first();

    $this->admin = User::factory()->create([
        'branch_id' => $mainBranch->id,
        'role'      => User::ROLE_ADMIN,
    ]);

    $this->branchManager = User::factory()->create([
        'branch_id' => $this->branch->id,
        'role'      => User::ROLE_BRANCH_MANAGER,
    ]);

    $this->branchStaff = User::factory()->create([
        'branch_id' => $this->branch->id,
        'role'      => User::ROLE_BRANCH_STAFF,
    ]);

    $this->withdrawalSlip = FormType::where('code', 'WS-001')->first();
});

// ─── Order Submission ────────────────────────────────────────────────────────

test('branch staff can submit an order', function () {
    $this->actingAs($this->branchStaff)
        ->postJson('/api/v1/form-orders', [
            'priority' => 'normal',
            'items'    => [['form_type_id' => $this->withdrawalSlip->id, 'printer_type' => 'consumable', 'quantity' => 500]],
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending');
});

test('branch manager can submit an order', function () {
    $this->actingAs($this->branchManager)
        ->postJson('/api/v1/form-orders', [
            'priority' => 'urgent',
            'items'    => [['form_type_id' => $this->withdrawalSlip->id, 'printer_type' => 'consumable', 'quantity' => 200]],
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.priority', 'urgent');
});

test('admin cannot submit orders (no branch assigned by default)', function () {
    $adminWithNoBranch = User::factory()->create(['role' => User::ROLE_ADMIN, 'branch_id' => null]);

    $this->actingAs($adminWithNoBranch)
        ->postJson('/api/v1/form-orders', [
            'priority' => 'normal',
            'items'    => [['form_type_id' => $this->withdrawalSlip->id, 'printer_type' => 'consumable', 'quantity' => 500]],
        ])
        ->assertForbidden();
});

// ─── Validation ──────────────────────────────────────────────────────────────

test('quantity must meet minimum order requirement', function () {
    $this->actingAs($this->branchStaff)
        ->postJson('/api/v1/form-orders', [
            'priority' => 'normal',
            'items'    => [['form_type_id' => $this->withdrawalSlip->id, 'printer_type' => 'consumable', 'quantity' => 1]],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['items.0.quantity']);
});

test('duplicate form types in a single order are rejected', function () {
    $this->actingAs($this->branchStaff)
        ->postJson('/api/v1/form-orders', [
            'priority' => 'normal',
            'items'    => [
                ['form_type_id' => $this->withdrawalSlip->id, 'printer_type' => 'consumable', 'quantity' => 100],
                ['form_type_id' => $this->withdrawalSlip->id, 'printer_type' => 'consumable', 'quantity' => 200],
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['items']);
});

test('priority field is required', function () {
    $this->actingAs($this->branchStaff)
        ->postJson('/api/v1/form-orders', [
            'items' => [['form_type_id' => $this->withdrawalSlip->id, 'quantity' => 500]],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['priority']);
});

test('invalid priority value is rejected', function () {
    $this->actingAs($this->branchStaff)
        ->postJson('/api/v1/form-orders', [
            'priority' => 'extreme',
            'items'    => [['form_type_id' => $this->withdrawalSlip->id, 'printer_type' => 'consumable', 'quantity' => 500]],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['priority']);
});

test('items array must have at least one item', function () {
    $this->actingAs($this->branchStaff)
        ->postJson('/api/v1/form-orders', ['priority' => 'normal', 'items' => [], 'printer_type' => 'consumable'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['items']);
});

// ─── Totals Calculation ───────────────────────────────────────────────────────

test('order totals are calculated correctly with 12% VAT', function () {
    $response = $this->actingAs($this->branchStaff)
        ->postJson('/api/v1/form-orders', [
            'priority' => 'normal',
            'items'    => [['form_type_id' => $this->withdrawalSlip->id, 'printer_type' => 'consumable', 'quantity' => 1000]],
        ])
        ->assertCreated();

    $order = FormOrder::find($response->json('data.id'));

    // 1000 × ₱2.50 = ₱2,500 + 12% VAT = ₱2,800
    expect((float) $order->subtotal)->toBe(2500.00)
        ->and((float) $order->tax_amount)->toBe(300.00)
        ->and((float) $order->total_amount)->toBe(2800.00);
});

// ─── Approval Workflow ────────────────────────────────────────────────────────

test('admin can approve a pending order', function () {
    $order = createPendingOrder($this->branchStaff, $this->withdrawalSlip);

    $this->actingAs($this->admin)
        ->patchJson("/api/v1/form-orders/{$order->id}/approve")
        ->assertOk()
        ->assertJsonPath('data.status', 'approved');

    expect($order->fresh()->approved_at)->not->toBeNull();
});

test('branch staff cannot approve orders', function () {
    $order = createPendingOrder($this->branchStaff, $this->withdrawalSlip);

    $this->actingAs($this->branchStaff)
        ->patchJson("/api/v1/form-orders/{$order->id}/approve")
        ->assertForbidden();
});

test('branch manager cannot approve orders', function () {
    $order = createPendingOrder($this->branchStaff, $this->withdrawalSlip);

    $this->actingAs($this->branchManager)
        ->patchJson("/api/v1/form-orders/{$order->id}/approve")
        ->assertForbidden();
});

test('cannot approve an already-approved order', function () {
    $order = createPendingOrder($this->branchStaff, $this->withdrawalSlip);

    $this->actingAs($this->admin)->patchJson("/api/v1/form-orders/{$order->id}/approve");

    // Policy checks canBeApproved() — returns 403 when order is no longer pending
    $this->actingAs($this->admin)
        ->patchJson("/api/v1/form-orders/{$order->id}/approve")
        ->assertForbidden();
});

// ─── Rejection Workflow ───────────────────────────────────────────────────────

test('admin can reject a pending order with a reason', function () {
    $order = createPendingOrder($this->branchStaff, $this->withdrawalSlip);

    $this->actingAs($this->admin)
        ->patchJson("/api/v1/form-orders/{$order->id}/reject", [
            'reason' => 'Insufficient stock at main branch.',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'rejected');

    expect($order->fresh()->rejection_reason)->toBe('Insufficient stock at main branch.');
});

test('admin can reject a pending order without a reason', function () {
    $order = createPendingOrder($this->branchStaff, $this->withdrawalSlip);

    $this->actingAs($this->admin)
        ->patchJson("/api/v1/form-orders/{$order->id}/reject")
        ->assertOk()
        ->assertJsonPath('data.status', 'rejected');
});

// ─── Delivery Workflow ────────────────────────────────────────────────────────

test('admin can mark an approved order as delivered', function () {
    $order = createPendingOrder($this->branchStaff, $this->withdrawalSlip);

    $this->actingAs($this->admin)->patchJson("/api/v1/form-orders/{$order->id}/approve");

    $this->actingAs($this->admin)
        ->patchJson("/api/v1/form-orders/{$order->id}/deliver")
        ->assertOk()
        ->assertJsonPath('data.status', 'delivered');

    expect($order->fresh()->delivered_at)->not->toBeNull();
});

test('cannot deliver a pending order', function () {
    $order = createPendingOrder($this->branchStaff, $this->withdrawalSlip);

    // Policy checks canBeDelivered() — returns 403 when order is still pending
    $this->actingAs($this->admin)
        ->patchJson("/api/v1/form-orders/{$order->id}/deliver")
        ->assertForbidden();
});

// ─── Cancellation ────────────────────────────────────────────────────────────

test('branch manager can cancel a pending order', function () {
    $order = createPendingOrder($this->branchStaff, $this->withdrawalSlip);

    $this->actingAs($this->branchManager)
        ->deleteJson("/api/v1/form-orders/{$order->id}")
        ->assertOk();

    expect(FormOrder::find($order->id))->toBeNull();
});

test('branch staff cannot cancel orders', function () {
    $order = createPendingOrder($this->branchStaff, $this->withdrawalSlip);

    $this->actingAs($this->branchStaff)
        ->deleteJson("/api/v1/form-orders/{$order->id}")
        ->assertForbidden();
});

// ─── Visibility / Scoping ─────────────────────────────────────────────────────

test('branch user only sees orders from their own branch', function () {
    createPendingOrder($this->branchStaff, $this->withdrawalSlip);

    $otherBranch = Branch::where('code', 'BR-002')->first();
    $otherStaff  = User::factory()->create(['branch_id' => $otherBranch->id, 'role' => User::ROLE_BRANCH_STAFF]);
    createPendingOrder($otherStaff, $this->withdrawalSlip);

    $response = $this->actingAs($this->branchStaff)->getJson('/api/v1/form-orders')->assertOk();

    $branchIds = collect($response->json('data'))->pluck('branch_id')->unique();
    expect($branchIds)->toHaveCount(1)
        ->and($branchIds->first())->toBe($this->branch->id);
});

test('admin sees all orders across branches', function () {
    createPendingOrder($this->branchStaff, $this->withdrawalSlip);

    $otherBranch = Branch::where('code', 'BR-002')->first();
    $otherStaff  = User::factory()->create(['branch_id' => $otherBranch->id, 'role' => User::ROLE_BRANCH_STAFF]);
    createPendingOrder($otherStaff, $this->withdrawalSlip);

    $response = $this->actingAs($this->admin)->getJson('/api/v1/form-orders')->assertOk();

    $branchIds = collect($response->json('data'))->pluck('branch_id')->unique();
    expect($branchIds->count())->toBeGreaterThanOrEqual(2);
});

test('branch staff cannot view orders from another branch', function () {
    $otherBranch = Branch::where('code', 'BR-002')->first();
    $otherStaff  = User::factory()->create(['branch_id' => $otherBranch->id, 'role' => User::ROLE_BRANCH_STAFF]);
    $order       = createPendingOrder($otherStaff, $this->withdrawalSlip);

    $this->actingAs($this->branchStaff)
        ->getJson("/api/v1/form-orders/{$order->id}")
        ->assertForbidden();
});

// ─── Reference Number ────────────────────────────────────────────────────────

test('order reference number is auto-generated with correct format', function () {
    $response = $this->actingAs($this->branchStaff)
        ->postJson('/api/v1/form-orders', [
            'priority' => 'normal',
            'items'    => [['form_type_id' => $this->withdrawalSlip->id, 'printer_type' => 'consumable', 'quantity' => 500]],
        ])
        ->assertCreated();

    $ref = $response->json('data.reference_number');
    expect($ref)->toMatch('/^ORD-\d{4}-\d{2}-\d{5}$/');
});

// ─── Dashboard ───────────────────────────────────────────────────────────────

test('admin receives admin stats from dashboard', function () {
    $this->actingAs($this->admin)
        ->getJson('/api/v1/dashboard/stats')
        ->assertOk()
        ->assertJsonStructure(['status_counts', 'branch_activity', 'recent_orders', 'totals']);
});

test('branch user receives branch-scoped stats from dashboard', function () {
    $this->actingAs($this->branchStaff)
        ->getJson('/api/v1/dashboard/stats')
        ->assertOk()
        ->assertJsonStructure(['status_counts', 'recent_orders', 'totals']);
});

// ─── Form Types ───────────────────────────────────────────────────────────────

test('authenticated user can list active form types', function () {
    $this->actingAs($this->branchStaff)
        ->getJson('/api/v1/form-types')
        ->assertOk()
        ->assertJsonStructure([['id', 'code', 'name', 'unit_price', 'minimum_order']]);
});

// ─── Branches ────────────────────────────────────────────────────────────────

test('authenticated user can list branches', function () {
    $this->actingAs($this->branchStaff)
        ->getJson('/api/v1/branches')
        ->assertOk()
        ->assertJsonStructure([['id', 'code', 'name', 'city']]);
});

// ─── Unauthenticated Access ───────────────────────────────────────────────────

test('unauthenticated requests are rejected', function () {
    $this->getJson('/api/v1/form-orders')->assertUnauthorized();
    $this->getJson('/api/v1/dashboard/stats')->assertUnauthorized();
    $this->getJson('/api/v1/form-types')->assertUnauthorized();
});

// ─── Helpers ─────────────────────────────────────────────────────────────────

function createPendingOrder(User $user, FormType $formType, string $printerType = 'consumable'): FormOrder
{
    $response = test()->actingAs($user)->postJson('/api/v1/form-orders', [
        'priority' => 'normal',
        'items'    => [[
            'form_type_id' => $formType->id,
            'printer_type' => $printerType,
            'quantity'     => 500,
        ]],
    ]);

    return FormOrder::find($response->json('data.id'));
}
