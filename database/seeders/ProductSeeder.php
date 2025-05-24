<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Seeder;
use Faker\Factory as Faker;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create();

        // Fetch users with 'user' role
        $users = User::whereHas('roles', function ($query) {
            $query->where('name', 'user');
        })->get();

        // Fetch all categories
        $categories = Category::pluck('id')->toArray();

        if ($users->isEmpty() || empty($categories)) {
            echo "No eligible users or categories found. Product seeding skipped.\n";
            return;
        }

        $totalProductsToCreate = 25;
        $productsCreated = 0;

        while ($productsCreated < $totalProductsToCreate) {
            $user = $users->random();

            $name = $faker->unique()->catchPhrase;
            $description = $faker->sentence(rand(8, 15));

            // Check for duplicate product name for the user
            $existingProduct = Product::where('user_id', $user->id)
                ->where('name', $name)
                ->first();

            if ($existingProduct) {
                echo "Skipping duplicate product: '{$name}' for user ID: {$user->id}\n";
                continue;
            }

            $createdAt = $faker->dateTimeBetween('-6 months', 'now');
            $updatedAt = $faker->dateTimeBetween($createdAt, 'now');

            // Generate random tags (at least 1, up to 5)
            $tags = $faker->words($nb = rand(1, 5), $asText = false);

            // Generate random custom attributes
            $customAttributes = [
                'color' => $faker->colorName,
                'brand' => $faker->company,
                'condition' => $faker->randomElement(['new', 'used', 'refurbished']),
            ];

            // Create the product
            $product = Product::create([
                'user_id' => $user->id,
                'category_id' => $categories[array_rand($categories)],
                'name' => $name,
                'description' => $description,
                'sku' => $faker->unique()->bothify('SKU-#####'),
                'price' => $faker->randomFloat(2, 50, 5000),
                'weight' => $faker->optional(0.8)->randomFloat(2, 0.5, 10),
                'length' => $faker->optional(0.8)->randomFloat(2, 10, 100),
                'width' => $faker->optional(0.8)->randomFloat(2, 10, 100),
                'height' => $faker->optional(0.8)->randomFloat(2, 1, 50),
                'custom_attributes' => $customAttributes,
                'created_at' => $createdAt,
                'updated_at' => $updatedAt,
            ]);

            // Attach media
            for ($j = 1; $j <= 3; $j++) {
                $imageUrl = "https://picsum.photos/300/300?random=" . rand(1, 1000);
                $product->addMediaFromUrl($imageUrl)->toMediaCollection('product');
            }

            // Attach tags using the polymorphic relationship
            $tagIds = [];
            foreach ($tags as $tagName) {
                $tag = Tag::firstOrCreate(['tag_name' => $tagName]);
                $tagIds[] = $tag->id;
            }
            $product->tags()->sync($tagIds);

            echo "Created product: '{$name}' for user ID: {$user->id} with tags: " . implode(', ', $tags) . " (Created: {$createdAt->format('Y-m-d H:i:s')}, Updated: {$updatedAt->format('Y-m-d H:i:s')})\n";
            $productsCreated++;
        }

        echo "Successfully created 25 dummy products with images and tags for users with the 'user' role!\n";
    }
}
