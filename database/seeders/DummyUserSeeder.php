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
            ['email' => 'dipto2@gmail.com', 'first_name' => 'dipto2', 'last_name' => 'dipto2', 'phone' => '1234567892'],
            ['email' => 'dipto3@gmail.com', 'first_name' => 'dipto3', 'last_name' => 'dipto2', 'phone' => '1234567893'],
            ['email' => 'dipto4@gmail.com', 'first_name' => 'dipto4', 'last_name' => 'dipto2', 'phone' => '1234567894'],
            ['email' => 'dipto5@gmail.com', 'first_name' => 'dipto5', 'last_name' => 'dipto2', 'phone' => '1234567895'],
            ['email' => 'dipto6@gmail.com', 'first_name' => 'dipto6', 'last_name' => 'dipto2', 'phone' => '1234567896'],
        ];

        foreach ($usersData as $userData) {
            // Create user if not exists
            $user = User::firstOrCreate(
                ['email' => $userData['email']],
                [
                    'first_name' => $userData['first_name'],
                    'last_name' => $userData['last_name'],
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
