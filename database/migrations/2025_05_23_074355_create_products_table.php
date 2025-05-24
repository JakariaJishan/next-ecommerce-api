<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('category_id')->constrained('categories')->onDelete('cascade');
            $table->string('name', 255);
            $table->longText('description');
            $table->string('sku', 50)->unique();
            $table->decimal('price', 8, 2);
            $table->decimal('weight', 8, 2)->nullable(); // Weight in kilograms
            $table->decimal('length', 8, 2)->nullable(); // Dimensions in centimeters
            $table->decimal('width', 8, 2)->nullable();
            $table->decimal('height', 8, 2)->nullable();
            $table->json('custom_attributes')->nullable(); // JSON for flexible metadata
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
