<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $mainBranch = Branch::where('is_main_branch', true)->first();

        User::firstOrCreate(['email' => 'admin@formflow.ph'], [
            'name'      => 'System Administrator',
            'password'  => Hash::make('password'),
            'branch_id' => $mainBranch?->id,
            'role'      => User::ROLE_ADMIN,
        ]);

        foreach (Branch::active()->notMain()->get() as $branch) {
            $slug = strtolower(str_replace([' ', '-'], '_', $branch->code));

            User::firstOrCreate(['email' => "manager.{$slug}@formflow.ph"], [
                'name'      => "Manager — {$branch->name}",
                'password'  => Hash::make('password'),
                'branch_id' => $branch->id,
                'role'      => User::ROLE_BRANCH_MANAGER,
            ]);

            User::firstOrCreate(['email' => "staff.{$slug}@formflow.ph"], [
                'name'      => "Staff — {$branch->name}",
                'password'  => Hash::make('password'),
                'branch_id' => $branch->id,
                'role'      => User::ROLE_BRANCH_STAFF,
            ]);
        }
    }
}
