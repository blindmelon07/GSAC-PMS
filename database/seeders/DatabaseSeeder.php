<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            BranchSeeder::class,
            FormTypeSeeder::class,
            ProductSeeder::class,
            UserSeeder::class,
        ]);
    }
}
