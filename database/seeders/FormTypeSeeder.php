<?php

namespace Database\Seeders;

use App\Models\FormType;
use Illuminate\Database\Seeder;

class FormTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['code' => 'WS-001',  'name' => 'Withdrawal Slip',        'unit_price' => 2.50,  'minimum_order' => 100],
            ['code' => 'DS-001',  'name' => 'Deposit Slip',            'unit_price' => 2.50,  'minimum_order' => 100],
            ['code' => 'AO-001',  'name' => 'Account Opening Form',    'unit_price' => 12.00, 'minimum_order' => 10],
            ['code' => 'LA-001',  'name' => 'Loan Application Form',   'unit_price' => 25.00, 'minimum_order' => 10],
            ['code' => 'FT-001',  'name' => 'Fund Transfer Form',      'unit_price' => 5.00,  'minimum_order' => 50],
            ['code' => 'CR-001',  'name' => 'Cheque Requisition Form', 'unit_price' => 8.00,  'minimum_order' => 25],
            ['code' => 'ATM-001', 'name' => 'ATM Application Form',    'unit_price' => 15.00, 'minimum_order' => 10],
            ['code' => 'AC-001',  'name' => 'Account Closure Form',    'unit_price' => 10.00, 'minimum_order' => 10],
            ['code' => 'SC-001',  'name' => 'Signature Card',          'unit_price' => 5.00,  'minimum_order' => 50],
            ['code' => 'KYC-001', 'name' => 'KYC Update Form',         'unit_price' => 8.00,  'minimum_order' => 25],
        ];

        foreach ($types as $data) {
            FormType::firstOrCreate(['code' => $data['code']], $data);
        }
    }
}
