<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('category', ['paper', 'writing', 'filing', 'general'])->default('general');
            $table->decimal('unit_price', 10, 2);
            $table->string('unit_label', 50)->default('piece');
            $table->unsignedInteger('minimum_order')->default(1);
            $table->unsignedInteger('maximum_order')->nullable();
            $table->json('customizations')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
