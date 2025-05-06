<?php

namespace App\Http\Controllers;

use App\Helpers\TranslationHelper;
use App\Models\Ads;
use App\Models\AdTag;
use App\Models\AdTagMapping;
use App\Services\ApiResponseService;
use App\Services\FilterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AdsController extends Controller
{
    protected $filterService;

    public function __construct(FilterService $filterService)
    {
        $this->filterService = $filterService;
    }

    /**
     * @operationId All Ads
     */
    public function index(Request $request)
    {
        try {
            // Validate query parameters
            $request->validate([
                'status' => 'nullable|in:active,pending,sold,expired',
                'category_id' => 'nullable|exists:categories,id',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
                'per_page' => 'nullable|integer|min:1|max:100',
            ]);

            // Initialize query with eager loading
            $query = Ads::with(['user.media', 'media']);

            // Apply filters using the service
            $query = $this->filterService->applyFilters($request, $query);

            // Handle pagination
            $perPage = $request->get('per_page', 10);
            $perPage = is_numeric($perPage) && $perPage > 0 ? (int) $perPage : 10;
            $ads = $query->paginate($perPage);

            // Check if there are no ads
            if ($ads->isEmpty()) {
                return apiResponse(false, 'No ads found.', ['ads' => []], null);
            }

            return apiResponse(true, 'Ads retrieved successfully!', $ads, 'ads', 200);
        } catch (\Exception $e) {
            return apiResponse(false, 'An error occurred while retrieving ads.',
                $e->getMessage(), 'error');
        }
    }


    /**
     * @operationId Create Ads
     */
    public function store(Request $request)
    {
        try {
            // Check if the user is authenticated
            $user = Auth::guard('sanctum')->user();

            if (!$user) {
                return apiResponse(false, 'Unauthorized: You must be logged in to create ads.', [], null);
            }

            if (!$user->hasRole('user')) {
                return apiResponse(false, 'Unauthorized: You must have the user role and ads_create permission.', [], null);
            }

            // ✅ Validate the incoming data
            $request->validate([
                'category_id' => 'required|exists:categories,id',
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'price' => 'required|numeric|min:0',
                'currency' => 'required|in:usd,eur',
                'expiration_date' => 'nullable|date|after:today',
                'media' => 'nullable|array',
                'media.*' => 'file|mimes:jpg,jpeg,png,mp4,mov,avi|max:5120',
                'tags.*' => 'required',
            ]);

            $tagsInput = $request->input('tags');
            if (is_string($tagsInput)) {
                // Check if it’s a JSON-encoded array (e.g., '["he", "she", "i"]')
                if (json_decode($tagsInput, true) !== null) {
                    $tags = array_filter(array_map('trim', json_decode($tagsInput, true)));
                } else {
                    // Fallback to comma-separated string (e.g., 'he,she,i')
                    $tags = array_filter(array_map('trim', explode(',', $tagsInput)));
                }
            } else {
                $tags = $tagsInput; // Use as-is if already an array (e.g., from Postman or JSON)
            }

            // Step 3: Validate tags as an array
            $request->merge(['tags' => $tags]);
            $request->validate([
                'tags' => 'required|array',
                'tags.*' => 'required|string|max:255',
            ]);

            // ✅ Create the ad and associate it with the current user and category
            $ad = Ads::create([
                'user_id' => $user->id,
                'category_id' => $request->input('category_id'),
                'title' => $request->input('title'),
                'description' => $request->input('description'),
                'price' => $request->input('price'),
                'currency' => $request->input('currency'),
                'expiration_date' => $request->input('expiration_date'),
            ]);

            // ✅ Handle media uploads
            if ($request->hasFile('media')) {
                foreach ($request->file('media') as $file) {
                    $ad->addMedia($file)->toMediaCollection('ads');
                }
            }

            // ✅ Handle Tags: Create new tags if they don't exist, then attach them to the ad
            if (!empty($tags)) {
                foreach ($tags as $tagName) {
                    $tag = AdTag::firstOrCreate(['tag_name' => $tagName]);
                    AdTagMapping::create([
                        'ad_id' => $ad->id,
                        'tag_id' => $tag->id,
                    ]);
                }
            }

            // ✅ Return success response with ad, media, and tags
            return apiResponse(true, 'Ad created successfully!',
                $ad->load('user.media', 'media', 'tags'), 'ads');

        } catch (\Exception $e) {
            return ApiResponseService::handleException($e, $request->all());
        }
    }

    /**
     * @operationId Show Ads
     */
    public function show($id)
    {
        try {
            // Fetch the ad with user and media
            $ad = Ads::with(['user.media', 'media'])->where('id', $id)->first();

            if (!$ad) {
                return apiResponse(false, 'Ad not found.', [], 'ad');
            }

            return apiResponse(true, 'Ad retrieved successfully.', $ad, 'ad');
        } catch (\Exception $e) {
            return apiResponse(false, 'An error occurred while retrieving the ad.',
                $e->getMessage(), 'error');
        }
    }

    /**
     * @operationId Update Ads
     */
    public function update(Request $request, $id)
    {
        try {
            // Check if the user is authenticated
            $user = Auth::guard('sanctum')->user();

            if (!$user) {
                return apiResponse(false, 'Unauthorized: You must be logged in to update ads.', [], null);
            }

            if (!$user || !$user->hasRole('user')) {
                // Return an unauthorized response if the user doesn't have the required permissions
                return apiResponse(false, 'Unauthorized: You must have the user role and ads_update permission.', [], null);
            }

            $ads = Ads::where('id', $id)->first();

            if (!$ads) {
                return apiResponse(false, 'Ad not found.', [], null);
            }


            // Check if the user owns the ad
            if ($user->id !== $ads->user_id) {
                return apiResponse(false, 'You are not the owner of this ad.', [], null);
            }

            // Validate the incoming data
            $validatedData = $request->validate([
                'category_id' => 'sometimes|required|exists:categories,id',
                'title' => 'sometimes|required|string|max:255',
                'description' => 'nullable|string',
                'price' => 'sometimes|required|numeric|min:0',
                'currency' => 'sometimes|required|in:usd,eur',
                'status' => 'sometimes|required|in:pending,active,sold,expired',
                'moderation_status' => 'sometimes|required|in:approved,rejected,flagged,pending',
                'expiration_date' => 'nullable|date|after:today',
                'media' => 'nullable|array',
                'media.*' => 'file|mimes:jpg,jpeg,png,mp4,mov,avi|max:5120',
                'delete_media' => 'nullable|array',
            ]);

            // Update ad fields
            $ads->update($validatedData);

            // Handle deleting selected media files
            if ($request->has('delete_media')) {
                foreach ($request->delete_media as $mediaId) {
                    $media = $ads->media()->where('id', $mediaId)->first();
                    if ($media) {
                        $media->delete(); // Delete media from storage and database
                    }
                }
            }

            // Handle new media uploads
            if ($request->hasFile('media')) {
                foreach ($request->file('media') as $file) {
                    $ads->addMedia($file)->toMediaCollection('ads');
                }
            }

            return apiResponse(true, 'Ad updated successfully.',
                $ads->load('user.media' ,'media'), 'ad');

        } catch (\Exception $e) {
            return ApiResponseService::handleException($e, $request->all());
        }
    }

    /**
     * @operationId Delete Ads
     */
    public function destroy(Request $request, $id)
    {
        try {
            // Check if the user is authenticated
            $user = Auth::guard('sanctum')->user();

            if (!$user) {
                return apiResponse(false, 'Unauthorized: You must be logged in to update ads.', [], null);
            }

            if (!$user || !$user->hasRole('user')) {
                // Return an unauthorized response if the user doesn't have the required permissions
                return apiResponse(false, 'Unauthorized: You must have the user role and ads_destroy permission.', [], null);
            }


            // Manually fetch the ad with user check
            $ad = Ads::where('id', $id)->first();
            if (!$ad) {
                return apiResponse(false, 'Ad not found.', [], null);
            }

            // Check ownership
            if ($user->id !== $ad->user_id) {
                return apiResponse(false, 'You are not the owner of this ad.', [], null);
            }

            // Delete the ad
            $ad->delete();

            return apiResponse(true, 'Ad deleted successfully.', [], null);

        } catch (\Exception $e) {
            return apiResponse(false, 'An error occurred while deleting the ad.',
                $e->getMessage(), 'error');
        }
    }
}
