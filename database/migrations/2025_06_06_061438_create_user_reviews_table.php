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
        Schema::create('user_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reviewer_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('reviewed_user_id')->constrained('users')->onDelete('cascade');
            $table->integer('rating')->unsigned()->comment('Rating from 1-5');
            $table->text('comment')->nullable();
            $table->boolean('is_approved')->default(true);
            $table->timestamps();

            // Ensure a user can only review another user once
            $table->unique(['reviewer_id', 'reviewed_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_reviews');
    }
};
