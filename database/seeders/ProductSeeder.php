<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            [
                'code'        => 'PAP-001',
                'name'        => 'Bond Paper',
                'description' => 'Standard bond paper for office use.',
                'category'    => 'paper',
                'unit_price'  => 250.00,
                'unit_label'  => 'ream',
                'minimum_order' => 1,
                'customizations' => [
                    ['key' => 'size',   'label' => 'Size',   'type' => 'select', 'options' => ['A4', 'A3', 'Legal', 'Letter']],
                    ['key' => 'weight', 'label' => 'Weight', 'type' => 'select', 'options' => ['70gsm', '80gsm', '90gsm']],
                ],
            ],
            [
                'code'        => 'PEN-001',
                'name'        => 'Ballpen',
                'description' => 'Standard ballpoint pen.',
                'category'    => 'writing',
                'unit_price'  => 12.00,
                'unit_label'  => 'piece',
                'minimum_order' => 12,
                'customizations' => [
                    ['key' => 'color', 'label' => 'Ink Color', 'type' => 'select', 'options' => ['blue', 'red', 'black']],
                ],
            ],
            [
                'code'        => 'FLD-001',
                'name'        => 'Folder',
                'description' => 'Expandable document folder.',
                'category'    => 'filing',
                'unit_price'  => 35.00,
                'unit_label'  => 'piece',
                'minimum_order' => 1,
                'customizations' => [
                    ['key' => 'color', 'label' => 'Color', 'type' => 'select', 'options' => ['blue', 'red', 'green', 'yellow']],
                    ['key' => 'size',  'label' => 'Size',  'type' => 'select', 'options' => ['A4', 'Legal']],
                ],
            ],
            [
                'code'        => 'STP-001',
                'name'        => 'Stapler',
                'description' => 'Heavy-duty stapler.',
                'category'    => 'general',
                'unit_price'  => 180.00,
                'unit_label'  => 'piece',
                'minimum_order' => 1,
                'customizations' => null,
            ],
            [
                'code'        => 'SCI-001',
                'name'        => 'Scissors',
                'description' => 'Office scissors, stainless blade.',
                'category'    => 'general',
                'unit_price'  => 65.00,
                'unit_label'  => 'piece',
                'minimum_order' => 1,
                'customizations' => null,
            ],
            [
                'code'        => 'NB-001',
                'name'        => 'Notebook',
                'description' => 'Spiral-bound ruled notebook.',
                'category'    => 'writing',
                'unit_price'  => 55.00,
                'unit_label'  => 'piece',
                'minimum_order' => 1,
                'customizations' => [
                    ['key' => 'size', 'label' => 'Size', 'type' => 'select', 'options' => ['A5', 'A4']],
                ],
            ],
        ];

        foreach ($products as $data) {
            Product::firstOrCreate(['code' => $data['code']], $data);
        }
    }
}
