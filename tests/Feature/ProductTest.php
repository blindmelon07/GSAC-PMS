<?php

use App\Models\Branch;
use App\Models\Product;
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

test('admin can view products page', function () {
    $this->actingAs($this->admin)
        ->get('/products')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Products')->has('products'));
});

test('branch user cannot view products management page', function () {
    $this->actingAs($this->branchUser)
        ->get('/products')
        ->assertForbidden();
});

// ─── Create ───────────────────────────────────────────────────────────────────

test('admin can create a product without customizations', function () {
    $this->actingAs($this->admin)
        ->post('/products', [
            'code'          => 'TST-999',
            'name'          => 'Test Supply',
            'category'      => 'general',
            'unit_price'    => 50.00,
            'unit_label'    => 'piece',
            'minimum_order' => 1,
            'is_active'     => true,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('products', [
        'code'       => 'TST-999',
        'name'       => 'Test Supply',
        'unit_price' => '50.00',
    ]);
});

test('admin can create a product with customizations', function () {
    $this->actingAs($this->admin)
        ->post('/products', [
            'code'          => 'PAP-999',
            'name'          => 'Custom Paper',
            'category'      => 'paper',
            'unit_price'    => 200.00,
            'unit_label'    => 'ream',
            'minimum_order' => 1,
            'customizations' => [
                ['key' => 'size', 'label' => 'Size', 'type' => 'select', 'options' => ['A4', 'Legal']],
            ],
            'is_active' => true,
        ])
        ->assertRedirect();

    $product = Product::where('code', 'PAP-999')->first();

    expect($product)->not->toBeNull()
        ->and($product->hasCustomizations())->toBeTrue()
        ->and($product->customizations[0]['key'])->toBe('size');
});

test('branch user cannot create a product', function () {
    $this->actingAs($this->branchUser)
        ->post('/products', [
            'code'          => 'X-001',
            'name'          => 'Unauthorized',
            'category'      => 'general',
            'unit_price'    => 10.00,
            'unit_label'    => 'piece',
            'minimum_order' => 1,
        ])
        ->assertForbidden();
});

test('product code must be unique', function () {
    $existing = Product::first();

    $this->actingAs($this->admin)
        ->post('/products', [
            'code'          => $existing->code,
            'name'          => 'Duplicate',
            'category'      => 'general',
            'unit_price'    => 10.00,
            'unit_label'    => 'piece',
            'minimum_order' => 1,
        ])
        ->assertSessionHasErrors('code');
});

test('product category must be valid', function () {
    $this->actingAs($this->admin)
        ->post('/products', [
            'code'          => 'CAT-001',
            'name'          => 'Bad Category',
            'category'      => 'invalid_category',
            'unit_price'    => 10.00,
            'unit_label'    => 'piece',
            'minimum_order' => 1,
        ])
        ->assertSessionHasErrors('category');
});

test('unit price is required', function () {
    $this->actingAs($this->admin)
        ->post('/products', [
            'code'          => 'REQ-001',
            'name'          => 'No Price',
            'category'      => 'general',
            'unit_label'    => 'piece',
            'minimum_order' => 1,
        ])
        ->assertSessionHasErrors('unit_price');
});

// ─── Update ───────────────────────────────────────────────────────────────────

test('admin can update a product', function () {
    $product = Product::first();

    $this->actingAs($this->admin)
        ->patch("/products/{$product->id}", [
            'name'          => 'Updated Name',
            'category'      => $product->category,
            'unit_price'    => 999.00,
            'unit_label'    => $product->unit_label,
            'minimum_order' => $product->minimum_order,
            'is_active'     => true,
        ])
        ->assertRedirect();

    $product->refresh();
    expect($product->name)->toBe('Updated Name')
        ->and((float) $product->unit_price)->toBe(999.0);
});

test('admin can update customizations on a product', function () {
    $product = Product::first();

    $this->actingAs($this->admin)
        ->patch("/products/{$product->id}", [
            'name'          => $product->name,
            'category'      => $product->category,
            'unit_price'    => $product->unit_price,
            'unit_label'    => $product->unit_label,
            'minimum_order' => $product->minimum_order,
            'customizations' => [
                ['key' => 'color', 'label' => 'Color', 'type' => 'select', 'options' => ['red', 'blue']],
            ],
            'is_active' => true,
        ])
        ->assertRedirect();

    $product->refresh();
    expect($product->customizations[0]['key'])->toBe('color');
});

test('branch user cannot update a product', function () {
    $product = Product::first();

    $this->actingAs($this->branchUser)
        ->patch("/products/{$product->id}", ['unit_price' => 999])
        ->assertForbidden();
});
