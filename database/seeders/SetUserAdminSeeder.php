<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;

class SetUserAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        // Admin details
        $adminEmail = 'jakaria@gmail.com';
        $adminUsername = 'jakaria';
        $adminPhone = '0123456789';
        $adminPassword = '111111';

        // Ensure the admin role exists
        $adminRole = Role::where('name', 'admin')->first();
        if (!$adminRole) {
            $this->command->warn('Admin role not found! Please run the RolePermissionSeeder first.');
            return;
        }

        // Check if the user already exists
        $user = User::where('email', $adminEmail)->first();

        if (!$user) {
            // Create the admin user
            $user = User::create([
                'email' => $adminEmail,
                'username' => $adminUsername,
                'phone' => $adminPhone,
                'password' => Hash::make($adminPassword), // Securely hash the password
                'email_verified_at' => now(), // Mark email as verified
            ]);

            $this->command->info("Admin user with email {$adminEmail} has been created.");
        } else {
            $this->command->warn("User with email {$adminEmail} already exists.");
        }

        // Assign the admin role if not already assigned
        if (!$user->hasRole('admin')) {
            $user->assignRole($adminRole);
            $this->command->info("User with email {$adminEmail} has been assigned the admin role.");
        } else {
            $this->command->warn("User with email {$adminEmail} is already an admin.");
        }
    }
}
