<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Run the seeders in the correct order
        $this->call([
            CategorySeeder::class,
            RolePermissionSeeder::class, // First, create roles and permissions
            SetUserAdminSeeder::class,   // Then, create the admin user and assign the role
            DummyUserSeeder::class,
            AdsSeeder::class,
        ]);
    }
}
