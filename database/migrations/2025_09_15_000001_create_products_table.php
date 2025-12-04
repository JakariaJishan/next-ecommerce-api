<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->decimal('price', 12, 2);
            $table->decimal('sale_price', 12, 2)->nullable();
            $table->string('currency', 3)->default('USD');
            $table->unsignedInteger('stock')->default(0);
            $table->enum('status', ['draft', 'active', 'inactive'])->default('active');
            $table->json('images')->nullable();
            $table->json('attributes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};


