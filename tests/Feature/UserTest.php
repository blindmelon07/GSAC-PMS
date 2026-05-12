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
    $this->branch = $branch;
    $this->branchUser = User::factory()->create([
        'branch_id' => $branch->id,
        'role'      => User::ROLE_BRANCH_STAFF,
    ]);
});

// ─── Listing ──────────────────────────────────────────────────────────────────

test('admin can view users page', function () {
    $this->actingAs($this->admin)
        ->get('/users')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Users')
            ->has('users')
            ->has('branches')
        );
});

test('branch user cannot view users page', function () {
    $this->actingAs($this->branchUser)
        ->get('/users')
        ->assertForbidden();
});

// ─── Filtering ────────────────────────────────────────────────────────────────

test('admin can filter users by role', function () {
    $this->actingAs($this->admin)
        ->get('/users?role=admin')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Users')
            ->where('filters.role', 'admin')
        );
});

test('admin can filter users by branch', function () {
    $this->actingAs($this->admin)
        ->get("/users?branch_id={$this->branch->id}")
        ->assertOk();
});

test('admin can search users by name', function () {
    $this->actingAs($this->admin)
        ->get('/users?search=Administrator')
        ->assertOk();
});

test('admin can filter users by status', function () {
    $this->actingAs($this->admin)
        ->get('/users?status=active')
        ->assertOk();
});

// ─── Create ───────────────────────────────────────────────────────────────────

test('admin can create a branch staff user', function () {
    $this->actingAs($this->admin)
        ->post('/users', [
            'name'                  => 'New Staff',
            'email'                 => 'newstaff@gsac.ph',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
            'role'                  => User::ROLE_BRANCH_STAFF,
            'branch_id'             => $this->branch->id,
            'is_active'             => true,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('users', [
        'email'     => 'newstaff@gsac.ph',
        'role'      => 'branch_staff',
        'branch_id' => $this->branch->id,
    ]);
});

test('admin can create an admin user without a branch', function () {
    $this->actingAs($this->admin)
        ->post('/users', [
            'name'                  => 'New Admin',
            'email'                 => 'newadmin@gsac.ph',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
            'role'                  => User::ROLE_ADMIN,
            'branch_id'             => null,
            'is_active'             => true,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('users', ['email' => 'newadmin@gsac.ph', 'role' => 'admin']);
});

test('email must be unique when creating a user', function () {
    $this->actingAs($this->admin)
        ->post('/users', [
            'name'                  => 'Duplicate',
            'email'                 => $this->admin->email,
            'password'              => 'password123',
            'password_confirmation' => 'password123',
            'role'                  => User::ROLE_BRANCH_STAFF,
        ])
        ->assertSessionHasErrors('email');
});

test('password is required when creating a user', function () {
    $this->actingAs($this->admin)
        ->post('/users', [
            'name'  => 'No Password',
            'email' => 'nopw@gsac.ph',
            'role'  => User::ROLE_BRANCH_STAFF,
        ])
        ->assertSessionHasErrors('password');
});

test('branch user cannot create users', function () {
    $this->actingAs($this->branchUser)
        ->post('/users', [
            'name'                  => 'Hacked',
            'email'                 => 'hacked@gsac.ph',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
            'role'                  => User::ROLE_ADMIN,
        ])
        ->assertForbidden();
});

// ─── Update ───────────────────────────────────────────────────────────────────

test('admin can update a user name and role', function () {
    $user = User::factory()->create([
        'branch_id' => $this->branch->id,
        'role'      => User::ROLE_BRANCH_STAFF,
    ]);

    $this->actingAs($this->admin)
        ->patch("/users/{$user->id}", [
            'name'      => 'Updated Name',
            'email'     => $user->email,
            'role'      => User::ROLE_BRANCH_MANAGER,
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ])
        ->assertRedirect();

    expect($user->fresh()->name)->toBe('Updated Name')
        ->and($user->fresh()->role)->toBe(User::ROLE_BRANCH_MANAGER);
});

test('admin can deactivate a user', function () {
    $user = User::factory()->create([
        'branch_id' => $this->branch->id,
        'role'      => User::ROLE_BRANCH_STAFF,
        'is_active' => true,
    ]);

    $this->actingAs($this->admin)
        ->patch("/users/{$user->id}", [
            'name'      => $user->name,
            'email'     => $user->email,
            'role'      => $user->role,
            'is_active' => false,
        ])
        ->assertRedirect();

    expect($user->fresh()->is_active)->toBeFalse();
});

test('password is not changed when blank on update', function () {
    $user = User::factory()->create([
        'branch_id' => $this->branch->id,
        'role'      => User::ROLE_BRANCH_STAFF,
        'password'  => bcrypt('original-password'),
    ]);

    $this->actingAs($this->admin)
        ->patch("/users/{$user->id}", [
            'name'      => $user->name,
            'email'     => $user->email,
            'role'      => $user->role,
            'password'  => '',
            'is_active' => true,
        ])
        ->assertRedirect();

    expect(\Illuminate\Support\Facades\Hash::check('original-password', $user->fresh()->password))->toBeTrue();
});

test('branch user cannot update users', function () {
    $this->actingAs($this->branchUser)
        ->patch("/users/{$this->admin->id}", ['name' => 'Hacked'])
        ->assertForbidden();
});
