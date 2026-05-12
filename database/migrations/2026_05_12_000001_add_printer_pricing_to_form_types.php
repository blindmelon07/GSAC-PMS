<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('form_types', function (Blueprint $table) {
            $table->decimal('price_consumable', 10, 2)->default(0.00)->after('unit_price');
            $table->decimal('price_non_consumable', 10, 2)->default(0.00)->after('price_consumable');
        });

        // Seed both prices from the existing unit_price
        DB::table('form_types')->update([
            'price_consumable'     => DB::raw('unit_price'),
            'price_non_consumable' => DB::raw('unit_price'),
        ]);

        Schema::table('form_order_items', function (Blueprint $table) {
            $table->enum('printer_type', ['consumable', 'non_consumable'])
                ->default('consumable')
                ->after('form_type_id');
        });
    }

    public function down(): void
    {
        Schema::table('form_types', function (Blueprint $table) {
            $table->dropColumn(['price_consumable', 'price_non_consumable']);
        });

        Schema::table('form_order_items', function (Blueprint $table) {
            $table->dropColumn('printer_type');
        });
    }
};
