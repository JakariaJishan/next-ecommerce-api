<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class DummyUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Ensure the "user" role exists
        $userRole = Role::firstOrCreate(['name' => 'user']);

        // Define dummy users
        $usersData = [
            ['email' => 'dipto2@gmail.com', 'username' => 'dipto2', 'phone' => '1234567892'],
            ['email' => 'dipto3@gmail.com', 'username' => 'dipto3', 'phone' => '1234567893'],
            ['email' => 'dipto4@gmail.com', 'username' => 'dipto4', 'phone' => '1234567894'],
            ['email' => 'dipto5@gmail.com', 'username' => 'dipto5', 'phone' => '1234567895'],
            ['email' => 'dipto6@gmail.com', 'username' => 'dipto6', 'phone' => '1234567896'],
        ];

        foreach ($usersData as $userData) {
            // Create user if not exists
            $user = User::firstOrCreate(
                ['email' => $userData['email']],
                [
                    'username' => $userData['username'],
                    'phone' => $userData['phone'],
                    'password' => Hash::make('111111'), // Default password
                    'email_verified_at' => Carbon::now(),
                ]
            );

            // Assign "user" role if not already assigned
            if (!$user->hasRole('user')) {
                $user->assignRole('user');
            }
        }

        echo "Dummy users with 'user' role created successfully!\n";
    }
}
