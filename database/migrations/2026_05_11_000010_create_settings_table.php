<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->string('value', 255)->default('');
            $table->string('label', 255);
            $table->string('description', 500)->nullable();
            $table->timestamps();
        });

        DB::table('settings')->insert([
            [
                'key'         => 'vat_rate',
                'value'       => '12.00',
                'label'       => 'VAT Rate (%)',
                'description' => 'Value-added tax percentage applied to orders and invoices.',
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
            [
                'key'         => 'discount_rate',
                'value'       => '0.00',
                'label'       => 'Discount Rate (%)',
                'description' => 'Global discount percentage applied to invoice subtotals before VAT is computed.',
                'created_at'  => now(),
                'updated_at'  => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
