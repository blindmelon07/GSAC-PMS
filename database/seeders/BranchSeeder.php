<?php

namespace Database\Seeders;

use App\Models\Branch;
use Illuminate\Database\Seeder;

class BranchSeeder extends Seeder
{
    public function run(): void
    {
        $branches = [
            ['code' => 'BR-000', 'name' => 'Main Branch — FormFlow HQ',  'city' => 'Manila',       'is_main_branch' => true],
            ['code' => 'BR-001', 'name' => 'North Makati Branch',          'city' => 'Makati'],
            ['code' => 'BR-002', 'name' => 'South Makati Branch',          'city' => 'Makati'],
            ['code' => 'BR-003', 'name' => 'Quezon City Central Branch',   'city' => 'Quezon City'],
            ['code' => 'BR-004', 'name' => 'Mandaluyong Branch',           'city' => 'Mandaluyong'],
            ['code' => 'BR-005', 'name' => 'Pasig Branch',                 'city' => 'Pasig'],
            ['code' => 'BR-006', 'name' => 'Taguig Branch',                'city' => 'Taguig'],
            ['code' => 'BR-007', 'name' => 'Marikina Branch',              'city' => 'Marikina'],
            ['code' => 'BR-008', 'name' => 'Parañaque Branch',             'city' => 'Parañaque'],
            ['code' => 'BR-009', 'name' => 'Las Piñas Branch',             'city' => 'Las Piñas'],
            ['code' => 'BR-010', 'name' => 'Muntinlupa Branch',            'city' => 'Muntinlupa'],
            ['code' => 'BR-011', 'name' => 'Caloocan Branch',              'city' => 'Caloocan'],
            ['code' => 'BR-012', 'name' => 'Malabon Branch',               'city' => 'Malabon'],
            ['code' => 'BR-013', 'name' => 'Valenzuela Branch',            'city' => 'Valenzuela'],
            ['code' => 'BR-014', 'name' => 'Navotas Branch',               'city' => 'Navotas'],
            ['code' => 'BR-015', 'name' => 'Pasay Branch',                 'city' => 'Pasay'],
            ['code' => 'BR-016', 'name' => 'Pateros Branch',               'city' => 'Pateros'],
            ['code' => 'BR-017', 'name' => 'San Juan Branch',              'city' => 'San Juan'],
            ['code' => 'BR-018', 'name' => 'Manila Central Branch',        'city' => 'Manila'],
        ];

        foreach ($branches as $data) {
            Branch::firstOrCreate(['code' => $data['code']], $data);
        }
    }
}
