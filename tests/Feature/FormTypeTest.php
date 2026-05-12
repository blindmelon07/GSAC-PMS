<?php

use App\Models\Branch;
use App\Models\FormType;
use App\Models\User;

beforeEach(function () {
    $this->seed();
    $main = Branch::where('is_main_branch', true)->first();

    $this->admin = User::factory()->create([
        'branch_id' => $main->id,
        'role'      => User::ROLE_ADMIN,
    ]);

    $branch = Branch::where('is_main_branch', false)->first();
    $this->branchUser = User::factory()->create([
        'branch_id' => $branch->id,
        'role'      => User::ROLE_BRANCH_STAFF,
    ]);
});

// ─── Listing ──────────────────────────────────────────────────────────────────

test('admin can view form types page', function () {
    $this->actingAs($this->admin)
        ->get('/form-types')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('FormTypes')->has('formTypes'));
});

test('branch user cannot view form types management page', function () {
    $this->actingAs($this->branchUser)
        ->get('/form-types')
        ->assertForbidden();
});

// ─── Create ───────────────────────────────────────────────────────────────────

test('admin can create a form type with dual pricing', function () {
    $this->actingAs($this->admin)
        ->post('/form-types', [
            'code'                 => 'TEST-001',
            'name'                 => 'Test Form',
            'price_consumable'     => 10.00,
            'price_non_consumable' => 15.00,
            'unit_label'           => 'piece',
            'minimum_order'        => 100,
            'is_active'            => true,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('form_types', [
        'code'                 => 'TEST-001',
        'price_consumable'     => '10.00',
        'price_non_consumable' => '15.00',
        'unit_price'           => '10.00', // synced from consumable
    ]);
});

test('branch user cannot create a form type', function () {
    $this->actingAs($this->branchUser)
        ->post('/form-types', [
            'code'                 => 'X-001',
            'name'                 => 'Unauthorized',
            'price_consumable'     => 5.00,
            'price_non_consumable' => 7.00,
            'unit_label'           => 'piece',
            'minimum_order'        => 10,
        ])
        ->assertForbidden();
});

test('form type code must be unique', function () {
    $existing = FormType::first();

    $this->actingAs($this->admin)
        ->post('/form-types', [
            'code'                 => $existing->code,
            'name'                 => 'Duplicate',
            'price_consumable'     => 5.00,
            'price_non_consumable' => 7.00,
            'unit_label'           => 'piece',
            'minimum_order'        => 10,
        ])
        ->assertSessionHasErrors('code');
});

test('prices are required', function () {
    $this->actingAs($this->admin)
        ->post('/form-types', [
            'code'          => 'NEW-001',
            'name'          => 'Missing Prices',
            'unit_label'    => 'piece',
            'minimum_order' => 10,
        ])
        ->assertSessionHasErrors(['price_consumable', 'price_non_consumable']);
});

// ─── Update ───────────────────────────────────────────────────────────────────

test('admin can update both prices independently', function () {
    $ft = FormType::first();

    $this->actingAs($this->admin)
        ->patch("/form-types/{$ft->id}", [
            'name'                 => $ft->name,
            'price_consumable'     => 99.00,
            'price_non_consumable' => 120.00,
            'unit_label'           => $ft->unit_label,
            'minimum_order'        => $ft->minimum_order,
            'is_active'            => true,
        ])
        ->assertRedirect();

    $ft->refresh();
    expect((float) $ft->price_consumable)->toBe(99.0)
        ->and((float) $ft->price_non_consumable)->toBe(120.0)
        ->and((float) $ft->unit_price)->toBe(99.0); // synced
});

test('branch user cannot update a form type', function () {
    $ft = FormType::first();

    $this->actingAs($this->branchUser)
        ->patch("/form-types/{$ft->id}", ['price_consumable' => 999])
        ->assertForbidden();
});

// ─── Printer Pricing on Orders ────────────────────────────────────────────────

test('order uses consumable price when printer type is consumable', function () {
    $ft = FormType::where('code', 'WS-001')->first();
    $branch = Branch::where('is_main_branch', false)->first();
    $staff = User::factory()->create(['branch_id' => $branch->id, 'role' => User::ROLE_BRANCH_STAFF]);

    $response = $this->actingAs($staff)->postJson('/api/v1/form-orders', [
        'priority' => 'normal',
        'items'    => [['form_type_id' => $ft->id, 'printer_type' => 'consumable', 'quantity' => 100]],
    ])->assertCreated();

    $orderId = $response->json('data.id');
    $item = \App\Models\FormOrderItem::where('form_order_id', $orderId)->first();

    expect((float) $item->unit_price)->toBe((float) $ft->price_consumable);
});

test('order uses non-consumable price when printer type is non_consumable', function () {
    $ft = FormType::where('code', 'WS-001')->first();
    $branch = Branch::where('is_main_branch', false)->first();
    $staff = User::factory()->create(['branch_id' => $branch->id, 'role' => User::ROLE_BRANCH_STAFF]);

    $response = $this->actingAs($staff)->postJson('/api/v1/form-orders', [
        'priority' => 'normal',
        'items'    => [['form_type_id' => $ft->id, 'printer_type' => 'non_consumable', 'quantity' => 100]],
    ])->assertCreated();

    $orderId = $response->json('data.id');
    $item = \App\Models\FormOrderItem::where('form_order_id', $orderId)->first();

    expect((float) $item->unit_price)->toBe((float) $ft->price_non_consumable);
});

test('invalid printer type is rejected', function () {
    $ft = FormType::first();
    $branch = Branch::where('is_main_branch', false)->first();
    $staff = User::factory()->create(['branch_id' => $branch->id, 'role' => User::ROLE_BRANCH_STAFF]);

    $this->actingAs($staff)->postJson('/api/v1/form-orders', [
        'priority' => 'normal',
        'items'    => [['form_type_id' => $ft->id, 'printer_type' => 'laser', 'quantity' => 100]],
    ])->assertUnprocessable()->assertJsonValidationErrors(['items.0.printer_type']);
});
