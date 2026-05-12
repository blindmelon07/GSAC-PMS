<?php

use App\Models\Branch;
use App\Models\User;

beforeEach(function () {
    $this->seed();
    $main = Branch::where('is_main_branch', true)->first();

    $this->admin = User::factory()->create([
        'branch_id' => $main->id,
        'role'      => User::ROLE_ADMIN,
        'password'  => bcrypt('password'),
    ]);
});

// ─── Login ────────────────────────────────────────────────────────────────────

test('guest is redirected to login from protected routes', function () {
    $this->get('/dashboard')->assertRedirect('/login');
    $this->get('/orders')->assertRedirect('/login');
    $this->get('/invoices')->assertRedirect('/login');
});

test('login page is accessible to guests', function () {
    $this->get('/login')->assertOk();
});

test('user can log in with valid credentials', function () {
    $this->post('/login', [
        'email'    => $this->admin->email,
        'password' => 'password',
    ])->assertRedirect('/dashboard');
});

test('login fails with wrong password', function () {
    $this->post('/login', [
        'email'    => $this->admin->email,
        'password' => 'wrong-password',
    ])->assertSessionHasErrors('email');
});

test('login fails with unknown email', function () {
    $this->post('/login', [
        'email'    => 'nobody@example.com',
        'password' => 'password',
    ])->assertSessionHasErrors('email');
});

// ─── Logout ───────────────────────────────────────────────────────────────────

test('authenticated user can log out', function () {
    $this->actingAs($this->admin)
        ->post('/logout')
        ->assertRedirect('/login');

    $this->get('/dashboard')->assertRedirect('/login');
});
