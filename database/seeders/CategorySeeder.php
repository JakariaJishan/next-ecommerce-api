<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Define top-level categories
        $categories = [
            ['name' => 'Electronics', 'description' => 'Devices, gadgets, and accessories.'],
            ['name' => 'Furniture', 'description' => 'Home and office furniture.'],
            ['name' => 'Clothing', 'description' => 'Men and women clothing.'],
            ['name' => 'Books', 'description' => 'Various kinds of books and reading materials.'],
            ['name' => 'Sports', 'description' => 'Sports equipment and accessories.'],
            ['name' => 'Automobiles', 'description' => 'Cars, bikes, and vehicle accessories.'],
        ];

        foreach ($categories as $categoryData) {
            // Check if the category exists before creating
            Category::firstOrCreate(
                ['name' => $categoryData['name']], // Ensure uniqueness
                ['description' => $categoryData['description']]
            );
        }

        echo "Top-level categories seeded successfully!\n";
    }
}
