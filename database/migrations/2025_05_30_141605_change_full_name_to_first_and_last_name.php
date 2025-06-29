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
        Schema::table('users', function (Blueprint $table) {
            // Drop the unique index (not constraint) on username
            $table->dropColumn('full_name');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable()->after('first_name');
            $table->enum('gender', ['male', 'female'])->after('last_name')->nullable();
            $table->date('date_of_birth')->nullable()->after('gender');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('full_name')->nullable();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['first_name', 'last_name', 'gender', 'date_of_birth']);
        });
    }
};
