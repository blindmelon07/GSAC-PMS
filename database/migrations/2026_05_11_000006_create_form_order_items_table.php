<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_order_id')->constrained('form_orders')->cascadeOnDelete();
            $table->foreignId('form_type_id')->constrained('form_types')->restrictOnDelete();
            $table->integer('quantity');
            $table->decimal('unit_price', 10, 2);
            $table->decimal('line_total', 12, 2);
            $table->string('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_order_items');
    }
};
