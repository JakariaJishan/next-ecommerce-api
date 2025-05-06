<?php

// database/seeders/BlogPostSeeder.php
namespace Database\Seeders;

use App\Models\BlogPost;
use App\Models\User; // Assuming your User model is in this namespace
use Illuminate\Database\Seeder;
use Faker\Factory as Faker;

class BlogPostSeeder extends Seeder
{
    public function run()
    {
        $faker = Faker::create();

        // Ensure there are admin users in the database
        $adminUsers = User::role('admin')->pluck('id')->toArray();
        if (empty($adminUsers)) {
            // Seed some admin users if none exist
            $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
            $adminUsers = \App\Models\User::factory()->count(5)->create()->each(function ($user) use ($role) {
                $user->assignRole($role);
            })->pluck('id')->toArray();
        }

        // Sample rich text content templates
        $richTextTemplates = [
            "<h2>Welcome to Our Tech Blog</h2><p>This is a <strong>detailed review</strong> of the latest gadgets. Stay tuned for more updates!</p><ul><li>High performance</li><li>Affordable price</li></ul><p>Lorem ipsum dolor sit amet, consectetur adipiscing elit.</p>",
            "<h1>Travel Adventures</h1><p>Exploring the world one city at a time. Here's what we found:</p><blockquote>Life is short, travel often.</blockquote><p>Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.</p>",
            "<h3>Cooking Tips</h3><p>Learn how to make the perfect dish with these steps:</p><ol><li>Preheat the oven</li><li>Mix ingredients</li><li>Bake for 30 minutes</li></ol><p>Ut enim ad minim veniam, quis nostrud exercitation ullamco.</p>",
        ];

        // Seed 10 blog posts
        foreach (range(1, 10) as $index) {
            $status = $faker->randomElement(['draft', 'published', 'archived']);
            $publishedDate = $status === 'published' ? $faker->dateTimeBetween('-1 year', 'now') : null;

            BlogPost::create([
                'title' => $faker->sentence(4), // e.g., "A Guide to Modern Tech"
                'author_id' => $faker->randomElement($adminUsers), // Random admin user ID
                'content' => $faker->randomElement($richTextTemplates) . '<p>' . $faker->paragraph(3) . '</p>',
                'published_date' => $publishedDate,
                'status' => $status,
                'created_at' => $faker->dateTimeBetween('-2 years', 'now'),
                'updated_at' => $faker->dateTimeBetween('-2 years', 'now'),
            ]);
        }
    }
}
