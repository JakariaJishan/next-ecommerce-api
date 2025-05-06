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
        Schema::table('permissions', function (Blueprint $table) {
            // Drop the existing unique constraint
            $table->dropUnique('permissions_name_guard_name_unique');

            // Rename the 'name' column to 'resource'
            $table->renameColumn('name', 'resource');

            // Add the new 'action' column as JSON
            $table->json('action')->nullable()->after('guard_name');

            // Add a new unique constraint with 'resource' and 'guard_name'
            $table->unique(['resource', 'guard_name'], 'permissions_resource_guard_name_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            // Drop the new unique constraint
            $table->dropUnique('permissions_resource_guard_name_unique');

            // Rename 'resource' back to 'name'
            $table->renameColumn('resource', 'name');

            // Drop the 'action' column
            $table->dropColumn('action');

            // Restore the original unique constraint
            $table->unique(['name', 'guard_name'], 'permissions_name_guard_name_unique');
        });
    }
};
