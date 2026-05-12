<?php

use App\Models\Branch;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed();
    $main = Branch::where('is_main_branch', true)->first();

    $this->user = User::factory()->create([
        'branch_id' => $main->id,
        'role'      => User::ROLE_ADMIN,
        'password'  => bcrypt('old-password'),
    ]);
});

test('user can change their password', function () {
    $this->actingAs($this->user)
        ->post('/profile/password', [
            'current_password'      => 'old-password',
            'password'              => 'new-password',
            'password_confirmation' => 'new-password',
        ])
        ->assertRedirect();

    expect(Hash::check('new-password', $this->user->fresh()->password))->toBeTrue();
});

test('wrong current password is rejected', function () {
    $this->actingAs($this->user)
        ->post('/profile/password', [
            'current_password'      => 'wrong-password',
            'password'              => 'new-password',
            'password_confirmation' => 'new-password',
        ])
        ->assertSessionHasErrors('current_password');
});

test('password confirmation must match', function () {
    $this->actingAs($this->user)
        ->post('/profile/password', [
            'current_password'      => 'old-password',
            'password'              => 'new-password',
            'password_confirmation' => 'different-password',
        ])
        ->assertSessionHasErrors('password');
});

test('new password must be at least 8 characters', function () {
    $this->actingAs($this->user)
        ->post('/profile/password', [
            'current_password'      => 'old-password',
            'password'              => 'short',
            'password_confirmation' => 'short',
        ])
        ->assertSessionHasErrors('password');
});

test('guest cannot change password', function () {
    $this->post('/profile/password', [
        'current_password'      => 'old-password',
        'password'              => 'new-password',
        'password_confirmation' => 'new-password',
    ])->assertRedirect('/login');
});
