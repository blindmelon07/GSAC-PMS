<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->decimal('discount_rate', 5, 2)->default(0.00)->after('tax_rate');
            $table->decimal('discount_amount', 14, 2)->default(0.00)->after('discount_rate');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['discount_rate', 'discount_amount']);
        });
    }
};
