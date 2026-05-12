<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')->insert([
            [
                'key'         => 'printer_consumable_maintenance',
                'value'       => '0',
                'label'       => 'Consumable Printer Maintenance',
                'description' => 'When enabled, the consumable printer is under maintenance and new orders for it are blocked.',
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
            [
                'key'         => 'printer_non_consumable_maintenance',
                'value'       => '0',
                'label'       => 'Non-Consumable Printer Maintenance',
                'description' => 'When enabled, the non-consumable printer is under maintenance and new orders for it are blocked.',
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
        ]);
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', [
            'printer_consumable_maintenance',
            'printer_non_consumable_maintenance',
        ])->delete();
    }
};
