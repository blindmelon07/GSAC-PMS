<?php

use App\Models\Branch;
use App\Models\FormOrder;
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

    $this->from = now()->startOfMonth()->toDateString();
    $this->to   = now()->toDateString();
});

// ─── Access ───────────────────────────────────────────────────────────────────

test('admin can view reports page', function () {
    $this->actingAs($this->admin)
        ->get('/reports')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Reports')
            ->has('results')
            ->has('summary')
            ->has('branches')
        );
});

test('branch user cannot view reports', function () {
    $this->actingAs($this->branchUser)
        ->get('/reports')
        ->assertForbidden();
});

test('guest cannot view reports', function () {
    $this->get('/reports')->assertRedirect('/login');
});

// ─── Report Types ─────────────────────────────────────────────────────────────

test('orders report returns correct structure', function () {
    $this->actingAs($this->admin)
        ->get("/reports?type=orders&from={$this->from}&to={$this->to}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Reports')
            ->where('type', 'orders')
            ->has('summary.count')
            ->has('summary.total_amount')
        );
});

test('invoices report returns correct structure', function () {
    $this->actingAs($this->admin)
        ->get("/reports?type=invoices&from={$this->from}&to={$this->to}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('type', 'invoices')
            ->has('summary.count')
            ->has('summary.total_discount')
        );
});

test('branch summary report returns correct structure', function () {
    $this->actingAs($this->admin)
        ->get("/reports?type=branches&from={$this->from}&to={$this->to}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('type', 'branches')
            ->has('summary.total_orders')
        );
});

test('form types usage report returns correct structure', function () {
    $this->actingAs($this->admin)
        ->get("/reports?type=form-types&from={$this->from}&to={$this->to}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('type', 'form-types')
            ->has('summary.total_quantity')
        );
});

// ─── Filters ──────────────────────────────────────────────────────────────────

test('reports can be filtered by branch', function () {
    $branch = Branch::where('is_main_branch', false)->first();

    $this->actingAs($this->admin)
        ->get("/reports?type=orders&from={$this->from}&to={$this->to}&branch_id={$branch->id}")
        ->assertOk();
});

test('default date range defaults to current month', function () {
    $this->actingAs($this->admin)
        ->get('/reports')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('from', now()->startOfMonth()->toDateString())
        );
});

// ─── PDF Export ───────────────────────────────────────────────────────────────

test('admin can export orders report as pdf', function () {
    $this->actingAs($this->admin)
        ->get("/reports/export?type=orders&from={$this->from}&to={$this->to}")
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

test('admin can export invoices report as pdf', function () {
    $this->actingAs($this->admin)
        ->get("/reports/export?type=invoices&from={$this->from}&to={$this->to}")
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

test('admin can export branch summary as pdf', function () {
    $this->actingAs($this->admin)
        ->get("/reports/export?type=branches&from={$this->from}&to={$this->to}")
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

test('admin can export form types report as pdf', function () {
    $this->actingAs($this->admin)
        ->get("/reports/export?type=form-types&from={$this->from}&to={$this->to}")
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

test('branch user cannot export reports', function () {
    $this->actingAs($this->branchUser)
        ->get("/reports/export?type=orders&from={$this->from}&to={$this->to}")
        ->assertForbidden();
});

test('guest cannot export reports', function () {
    $this->get("/reports/export?type=orders&from={$this->from}&to={$this->to}")
        ->assertRedirect('/login');
});

// ─── Report data accuracy ─────────────────────────────────────────────────────

test('orders report count matches database', function () {
    $ft     = FormType::where('code', 'WS-001')->first();
    $branch = Branch::where('is_main_branch', false)->first();
    $staff  = User::factory()->create(['branch_id' => $branch->id, 'role' => User::ROLE_BRANCH_STAFF]);

    // Create 3 orders today
    foreach (range(1, 3) as $_) {
        $this->actingAs($staff)->postJson('/api/v1/form-orders', [
            'priority' => 'normal',
            'items'    => [['form_type_id' => $ft->id, 'printer_type' => 'consumable', 'quantity' => 100]],
        ]);
    }

    $this->actingAs($this->admin)
        ->get("/reports?type=orders&from={$this->from}&to={$this->to}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('summary.count', FormOrder::whereBetween('created_at', [
                $this->from . ' 00:00:00',
                $this->to   . ' 23:59:59',
            ])->count())
        );
});
