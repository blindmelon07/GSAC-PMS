<?php

use App\Models\Branch;
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

test('admin can view branches page', function () {
    $this->actingAs($this->admin)
        ->get('/branches')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Branches')->has('branches'));
});

test('branch user can view branches page', function () {
    $this->actingAs($this->branchUser)
        ->get('/branches')
        ->assertOk();
});

test('guest cannot view branches', function () {
    $this->get('/branches')->assertRedirect('/login');
});

// ─── Create ───────────────────────────────────────────────────────────────────

test('admin can create a branch', function () {
    $this->actingAs($this->admin)
        ->post('/branches', [
            'code'          => 'TEST-001',
            'name'          => 'Test Branch',
            'city'          => 'Manila',
            'contact_email' => 'test@gsac.ph',
            'contact_phone' => '09171234567',
            'is_active'     => true,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('branches', ['code' => 'TEST-001', 'name' => 'Test Branch']);
});

test('branch code must be unique', function () {
    $existing = Branch::where('is_main_branch', false)->first();

    $this->actingAs($this->admin)
        ->post('/branches', [
            'code' => $existing->code,
            'name' => 'Duplicate Branch',
        ])
        ->assertSessionHasErrors('code');
});

test('branch user cannot create a branch', function () {
    $this->actingAs($this->branchUser)
        ->post('/branches', ['code' => 'NEW-001', 'name' => 'New Branch'])
        ->assertForbidden();
});

// ─── Update ───────────────────────────────────────────────────────────────────

test('admin can update a branch', function () {
    $branch = Branch::where('is_main_branch', false)->first();

    $this->actingAs($this->admin)
        ->patch("/branches/{$branch->id}", [
            'name'      => 'Updated Branch Name',
            'city'      => 'Quezon City',
            'is_active' => true,
        ])
        ->assertRedirect();

    expect($branch->fresh()->name)->toBe('Updated Branch Name');
});

test('branch user cannot update a branch', function () {
    $branch = Branch::where('is_main_branch', false)->first();

    $this->actingAs($this->branchUser)
        ->patch("/branches/{$branch->id}", ['name' => 'Hacked'])
        ->assertForbidden();
});
