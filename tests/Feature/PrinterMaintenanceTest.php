<?php

use App\Models\Branch;
use App\Models\FormType;
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
    $this->branch = $branch;
    $this->staff  = User::factory()->create([
        'branch_id' => $branch->id,
        'role'      => User::ROLE_BRANCH_STAFF,
    ]);

    $this->ws = FormType::where('code', 'WS-001')->first();
});

// ─── Settings toggle ─────────────────────────────────────────────────────────

test('admin can enable consumable printer maintenance', function () {
    $this->actingAs($this->admin)
        ->post('/settings', [
            'vat_rate'                        => '12.00',
            'discount_rate'                   => '0.00',
            'printer_consumable_maintenance'  => true,
            'printer_non_consumable_maintenance' => false,
        ])
        ->assertRedirect();

    expect(Setting::getValue('printer_consumable_maintenance'))->toBe('1');
    expect(Setting::getValue('printer_non_consumable_maintenance'))->toBe('0');
});

test('admin can enable non-consumable printer maintenance', function () {
    $this->actingAs($this->admin)
        ->post('/settings', [
            'vat_rate'                           => '12.00',
            'discount_rate'                      => '0.00',
            'printer_consumable_maintenance'     => false,
            'printer_non_consumable_maintenance' => true,
        ])
        ->assertRedirect();

    expect(Setting::getValue('printer_non_consumable_maintenance'))->toBe('1');
    expect(Setting::getValue('printer_consumable_maintenance'))->toBe('0');
});

test('admin can enable both printers under maintenance simultaneously', function () {
    $this->actingAs($this->admin)
        ->post('/settings', [
            'vat_rate'                           => '12.00',
            'discount_rate'                      => '0.00',
            'printer_consumable_maintenance'     => true,
            'printer_non_consumable_maintenance' => true,
        ])
        ->assertRedirect();

    expect(Setting::getValue('printer_consumable_maintenance'))->toBe('1');
    expect(Setting::getValue('printer_non_consumable_maintenance'))->toBe('1');
});

test('admin can disable printer maintenance', function () {
    Setting::setValue('printer_consumable_maintenance', '1');

    $this->actingAs($this->admin)
        ->post('/settings', [
            'vat_rate'                           => '12.00',
            'discount_rate'                      => '0.00',
            'printer_consumable_maintenance'     => false,
            'printer_non_consumable_maintenance' => false,
        ])
        ->assertRedirect();

    expect(Setting::getValue('printer_consumable_maintenance'))->toBe('0');
});

test('maintenance flags default to off when not sent', function () {
    $this->actingAs($this->admin)
        ->post('/settings', [
            'vat_rate'      => '12.00',
            'discount_rate' => '0.00',
            // maintenance flags omitted
        ])
        ->assertRedirect();

    expect(Setting::getValue('printer_consumable_maintenance'))->toBe('0');
    expect(Setting::getValue('printer_non_consumable_maintenance'))->toBe('0');
});

// ─── Order blocking ───────────────────────────────────────────────────────────

test('order is blocked when consumable printer is under maintenance', function () {
    Setting::setValue('printer_consumable_maintenance', '1');

    $this->actingAs($this->staff)
        ->postJson('/api/v1/form-orders', [
            'priority' => 'normal',
            'items'    => [[
                'form_type_id' => $this->ws->id,
                'printer_type' => 'consumable',
                'quantity'     => 100,
            ]],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['items.0.printer_type']);
});

test('order is blocked when non-consumable printer is under maintenance', function () {
    Setting::setValue('printer_non_consumable_maintenance', '1');

    $this->actingAs($this->staff)
        ->postJson('/api/v1/form-orders', [
            'priority' => 'normal',
            'items'    => [[
                'form_type_id' => $this->ws->id,
                'printer_type' => 'non_consumable',
                'quantity'     => 100,
            ]],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['items.0.printer_type']);
});

test('consumable order succeeds when only non-consumable is under maintenance', function () {
    Setting::setValue('printer_non_consumable_maintenance', '1');
    Setting::setValue('printer_consumable_maintenance', '0');

    $this->actingAs($this->staff)
        ->postJson('/api/v1/form-orders', [
            'priority' => 'normal',
            'items'    => [[
                'form_type_id' => $this->ws->id,
                'printer_type' => 'consumable',
                'quantity'     => 100,
            ]],
        ])
        ->assertCreated();
});

test('non-consumable order succeeds when only consumable is under maintenance', function () {
    Setting::setValue('printer_consumable_maintenance', '1');
    Setting::setValue('printer_non_consumable_maintenance', '0');

    $this->actingAs($this->staff)
        ->postJson('/api/v1/form-orders', [
            'priority' => 'normal',
            'items'    => [[
                'form_type_id' => $this->ws->id,
                'printer_type' => 'non_consumable',
                'quantity'     => 100,
            ]],
        ])
        ->assertCreated();
});

test('all orders are blocked when both printers are under maintenance', function () {
    Setting::setValue('printer_consumable_maintenance',     '1');
    Setting::setValue('printer_non_consumable_maintenance', '1');

    $this->actingAs($this->staff)
        ->postJson('/api/v1/form-orders', [
            'priority' => 'normal',
            'items'    => [[
                'form_type_id' => $this->ws->id,
                'printer_type' => 'consumable',
                'quantity'     => 100,
            ]],
        ])
        ->assertUnprocessable();

    $this->actingAs($this->staff)
        ->postJson('/api/v1/form-orders', [
            'priority' => 'normal',
            'items'    => [[
                'form_type_id' => $this->ws->id,
                'printer_type' => 'non_consumable',
                'quantity'     => 100,
            ]],
        ])
        ->assertUnprocessable();
});

test('maintenance error message is descriptive', function () {
    Setting::setValue('printer_consumable_maintenance', '1');

    $response = $this->actingAs($this->staff)
        ->postJson('/api/v1/form-orders', [
            'priority' => 'normal',
            'items'    => [[
                'form_type_id' => $this->ws->id,
                'printer_type' => 'consumable',
                'quantity'     => 100,
            ]],
        ])
        ->assertUnprocessable();

    $errors = $response->json('errors')['items.0.printer_type'] ?? [];
    expect($errors)->toBeArray()
        ->and($errors[0])->toContain('maintenance');
});

test('mixed-printer order is partially blocked', function () {
    Setting::setValue('printer_consumable_maintenance', '1');
    Setting::setValue('printer_non_consumable_maintenance', '0');

    $ds = FormType::where('code', 'DS-001')->first();

    $this->actingAs($this->staff)
        ->postJson('/api/v1/form-orders', [
            'priority' => 'normal',
            'items'    => [
                ['form_type_id' => $this->ws->id, 'printer_type' => 'consumable',     'quantity' => 100],
                ['form_type_id' => $ds->id,       'printer_type' => 'non_consumable', 'quantity' => 100],
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['items.0.printer_type'])
        ->assertJsonMissingValidationErrors(['items.1.printer_type']);
});

// ─── Pages pass maintenance status ───────────────────────────────────────────

test('orders page exposes printer maintenance status', function () {
    Setting::setValue('printer_consumable_maintenance', '1');

    $this->actingAs($this->staff)
        ->get('/orders')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Orders')
            ->where('printerMaintenance.consumable', true)
            ->where('printerMaintenance.non_consumable', false)
        );
});

test('form types page exposes printer maintenance status', function () {
    Setting::setValue('printer_non_consumable_maintenance', '1');

    $this->actingAs($this->admin)
        ->get('/form-types')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('FormTypes')
            ->where('printerMaintenance.consumable', false)
            ->where('printerMaintenance.non_consumable', true)
        );
});

test('settings page exposes current printer maintenance status', function () {
    Setting::setValue('printer_consumable_maintenance',     '1');
    Setting::setValue('printer_non_consumable_maintenance', '0');

    $this->actingAs($this->admin)
        ->get('/settings')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Settings')
            ->where('printerMaintenance.consumable', true)
            ->where('printerMaintenance.non_consumable', false)
        );
});
