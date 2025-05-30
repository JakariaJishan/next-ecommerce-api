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
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('payment_type', ['credit_debit_card', 'paypal', 'bank_account'])->default('credit_debit_card');
            $table->string('card_number')->nullable();
            $table->string('expiry_month', 2)->nullable();
            $table->string('expiry_year', 4)->nullable();
            $table->enum('card_type', ['visa', 'mastercard', 'american_express', 'discover'])->nullable();
            $table->string('card_holder_name')->nullable();
            $table->string('full_name');
            $table->string('address');
            $table->string('city');
            $table->string('zip');
            $table->string('state');
            $table->boolean('set_as_default')->default(false);
            $table->string('paypal_email')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('account_number')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['user_id', 'card_number'], 'unique_user_card');
            $table->unique(['user_id', 'paypal_email'], 'unique_user_paypal');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
    }
};
