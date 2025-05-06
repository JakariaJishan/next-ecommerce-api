<?php

namespace Database\Seeders;

use App\Models\Ads;
use App\Models\AdTag;
use App\Models\AdTagMapping;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Category;
use Illuminate\Support\Facades\Http;
use Faker\Factory as Faker;

class AdsSeeder extends Seeder
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
            echo "No eligible users or categories found. Ads seeding skipped.\n";
            return;
        }

        $totalAdsToCreate = 25;
        $adsCreated = 0;

        while ($adsCreated < $totalAdsToCreate) {
            $user = $users->random();

            $title = $faker->unique()->catchPhrase;
            $description = $faker->sentence(rand(8, 15));

            $existingAd = Ads::where('user_id', $user->id)
                ->where('title', $title)
                ->first();

            if ($existingAd) {
                echo "Skipping duplicate ad: '{$title}' for user ID: {$user->id}\n";
                continue;
            }

            $createdAt = $faker->dateTimeBetween('-6 months', 'now');
            $updatedAt = $faker->dateTimeBetween($createdAt, 'now');

            // Generate random tags (at least 1, up to 5)
            $tags = $faker->words($nb = rand(1, 5), $asText = false); // e.g., ['tech', 'sale', 'new']

            $ad = Ads::create([
                'user_id' => $user->id,
                'category_id' => $categories[array_rand($categories)],
                'title' => $title,
                'description' => $description,
                'price' => $faker->randomFloat(2, 50, 5000),
                'currency' => $faker->randomElement(['usd', 'eur']),
                'status' => $faker->randomElement(['pending', 'active', 'sold', 'expired']),
                'moderation_status' => $faker->randomElement(['approved', 'rejected', 'flagged', 'pending']),
                'expiration_date' => $faker->dateTimeBetween('now', '+30 days'),
                'created_at' => $createdAt,
                'updated_at' => $updatedAt,
            ]);

            // Attach media
            for ($j = 1; $j <= 3; $j++) {
                $imageUrl = "https://picsum.photos/300/300?random=" . rand(1, 1000);
                $ad->addMediaFromUrl($imageUrl)->toMediaCollection('ads');
            }

            // Attach tags (mimicking controller logic)
            foreach ($tags as $tagName) {
                $tag = AdTag::firstOrCreate(['tag_name' => $tagName]);
                AdTagMapping::create([
                    'ad_id' => $ad->id,
                    'tag_id' => $tag->id,
                ]);
            }

            echo "Created ad: '{$title}' for user ID: {$user->id} with tags: " . implode(', ', $tags) . " (Created: {$createdAt->format('Y-m-d H:i:s')}, Updated: {$updatedAt->format('Y-m-d H:i:s')})\n";
            $adsCreated++;
        }

        echo "Successfully created 25 dummy ads with images and tags for users with the 'user' role!\n";
    }
}
