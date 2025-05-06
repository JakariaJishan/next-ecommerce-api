<?php

namespace App\Http\Controllers;

use App\Models\Ads;
use App\Models\SearchHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SearchController extends Controller
{

    /**
     * @operationId Search ads
     */
    public function adsSearch(Request $request)
    {
        try {
            $query = trim($request->input('q'));

            if (!$query) {
                return apiResponse(false, 'Search query is required.', [], 400);
            }

            // ✅ 1️⃣ Search using Laravel Scout (for title & description)
            $scoutResults = Ads::search($query)->get();

            // ✅ 2️⃣ Search for ads that have matching tags using `whereHas()`
            $tagResults = Ads::whereHas('tags', function ($tagQuery) use ($query) {
                $tagQuery->where('tag_name', 'LIKE', "%{$query}%");
            })->get();

            // ✅ 3️⃣ Merge both results and remove duplicates
            $allAds = $scoutResults->merge($tagResults)->unique('id');

            if ($allAds->isEmpty()) {
                return apiResponse(false, 'No ads found.', [], 404);
            }

            // ✅ Get authenticated user
            $user = Auth::guard('sanctum')->user();

            // ✅ If user is NOT an admin, log search history
            if (!$user || !$user->hasRole('admin')) {
                SearchHistory::create([
                    'user_id' => $user ? $user->id : null,
                    'search_key_words' => $query,
                    'device_info' => $request->header('User-Agent'),
                    'ip_address' => $request->ip(),
                ]);
            }

            return apiResponse(true, 'Ads retrieved successfully!', [
                'ads' => $allAds,
            ], 200);

        } catch (\Exception $e) {
            return apiResponse(false, 'An error occurred while searching ads.', [
                'error' => $e->getMessage(),
            ], 500);
        }
    }


}
