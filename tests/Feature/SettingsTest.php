<?php

use App\Models\Branch;
use App\Models\Setting;
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

// ─── Access ───────────────────────────────────────────────────────────────────

test('admin can view settings page', function () {
    $this->actingAs($this->admin)
        ->get('/settings')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Settings')->has('settings'));
});

test('branch user cannot view settings', function () {
    $this->actingAs($this->branchUser)
        ->get('/settings')
        ->assertForbidden();
});

test('guest cannot view settings', function () {
    $this->get('/settings')->assertRedirect('/login');
});

// ─── Update ───────────────────────────────────────────────────────────────────

test('admin can update vat rate', function () {
    $this->actingAs($this->admin)
        ->post('/settings', [
            'vat_rate'      => '15.00',
            'discount_rate' => '0.00',
        ])
        ->assertRedirect();

    expect(Setting::getValue('vat_rate'))->toBe('15.00');
});

test('admin can update discount rate', function () {
    $this->actingAs($this->admin)
        ->post('/settings', [
            'vat_rate'      => '12.00',
            'discount_rate' => '5.00',
        ])
        ->assertRedirect();

    expect(Setting::getValue('discount_rate'))->toBe('5.00');
});

test('vat rate must be between 0 and 100', function () {
    $this->actingAs($this->admin)
        ->post('/settings', [
            'vat_rate'      => '150',
            'discount_rate' => '0',
        ])
        ->assertSessionHasErrors('vat_rate');
});

test('discount rate cannot be negative', function () {
    $this->actingAs($this->admin)
        ->post('/settings', [
            'vat_rate'      => '12',
            'discount_rate' => '-5',
        ])
        ->assertSessionHasErrors('discount_rate');
});

test('both fields are required', function () {
    $this->actingAs($this->admin)
        ->post('/settings', [])
        ->assertSessionHasErrors(['vat_rate', 'discount_rate']);
});

test('branch user cannot update settings', function () {
    $this->actingAs($this->branchUser)
        ->post('/settings', ['vat_rate' => '0', 'discount_rate' => '0'])
        ->assertForbidden();
});

// ─── Settings affect order calculation ───────────────────────────────────────

test('vat rate from settings is applied to new orders', function () {
    Setting::setValue('vat_rate', '10.00');

    $ft = \App\Models\FormType::where('code', 'WS-001')->first();
    $branch = Branch::where('is_main_branch', false)->first();
    $staff = User::factory()->create(['branch_id' => $branch->id, 'role' => User::ROLE_BRANCH_STAFF]);

    $response = $this->actingAs($staff)->postJson('/api/v1/form-orders', [
        'priority' => 'normal',
        'items'    => [['form_type_id' => $ft->id, 'printer_type' => 'consumable', 'quantity' => 1000]],
    ])->assertCreated();

    $order = \App\Models\FormOrder::find($response->json('data.id'));
    // 1000 × ₱2.50 = ₱2,500 × 10% = ₱250 tax, ₱2,750 total
    expect((float) $order->tax_amount)->toBe(250.0)
        ->and((float) $order->total_amount)->toBe(2750.0);
});
