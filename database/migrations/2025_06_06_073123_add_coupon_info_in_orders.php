<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('coupon_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('subtotal', 10, 2)->after('total_amount'); // Pre-discount amount
            // Rename total_amount to final_amount for clarity
            $table->renameColumn('total_amount', 'final_amount');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['coupon_id']);
            $table->dropColumn(['coupon_id', 'discount_amount', 'subtotal']);
            $table->renameColumn('final_amount', 'total_amount');
        });
    }
};
