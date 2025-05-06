<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\FilterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SellerInfoController extends Controller
{

    protected $filterService;

    public function __construct(FilterService $filterService)
    {
        $this->filterService = $filterService;
    }


    public function index()
    {
        try {
            $user = Auth::guard('sanctum')->user();

            if (!$user) {
                return apiResponse(false, 'Unauthorized: You must be logged in to create a contest.', [], 401);
            }

            // Check if the user is an admin and has permission to create contests
            if (!$user->hasRole('admin')) {
                return apiResponse(false, 'Unauthorized: Only admins can view seller info.', [], 403);
            }
            $perPage = request()->get('per_page', 10);
            $perPage = is_numeric($perPage) && $perPage > 0 ? (int) $perPage : 10;

            // ✅ Fetch distinct sellers (users) who have posted ads with pagination
            $sellers = User::whereHas('ads')->with('media')->paginate($perPage);

            // ✅ Check if no sellers found
            if ($sellers->isEmpty()) {
                return apiResponse(true, 'No sellers found.', $sellers, 'sellers', 200);
            }

            return apiResponse(true, 'Sellers retrieved successfully.', $sellers, 'sellers', 200);
        } catch (\Exception $e) {
            return apiResponse(false, 'An error occurred while retrieving sellers.', [
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @operationId Seller infos
     */
    public function sellersInfo(Request $request)
    {
        try {
            $user = Auth::guard('sanctum')->user();
            if (!$user) {
                return apiResponse(false, 'Unauthorized: You must be logged in.', [], null);
            }

            if (!$user->hasRole('admin')) {
                return apiResponse(false, 'Unauthorized: You do not have permission to view seller info.', [], null);
            }

            $request->validate([
                'search' => 'nullable|string|max:255',
                'per_page' => 'nullable|integer|min:1|max:100',
            ]);

            $query = User::whereHas('ads')->with('media');

            if ($search = $request->input('search')) {
                $query->where(function ($q) use ($search) {
                    $q->where('username', 'like', "%$search%")
                        ->orWhere('email', 'like', "%$search%");
                });
            }

            $query = $this->filterService->applyFilters($request, $query);

            $perPage = $request->get('per_page', 10);
            $perPage = is_numeric($perPage) && $perPage > 0 ? (int) $perPage : 10;
            $sellers = $query->paginate($perPage);

            if ($sellers->isEmpty()) {
                return apiResponse(true, 'No sellers found.', $sellers, 'sellers', 200);
            }

            return apiResponse(true, 'Sellers retrieved successfully.', $sellers, 'sellers', 200);

        } catch (\Exception $e) {
            return apiResponse(false, 'An error occurred while retrieving sellers.',
                $e->getMessage(), 'error');
        }
    }

    /**
     * @operationId Individual seller info
     */
    public function individualSellerInfo($userId)
    {
        try {
            // Retrieve the authenticated user (this assumes you're using Sanctum for authentication)
            $user = Auth::guard('sanctum')->user();

            if (!$user) {
                return apiResponse(false, 'Unauthorized: You must be logged in.', [], null);
            }

            // Check if the authenticated user has the right permissions
            if (!$user->hasRole('admin')) {
                return apiResponse(false, 'Unauthorized: You do not have permission to view seller info.', [], null);
            }

            $perPage = request()->get('per_page', 10);
            $perPage = is_numeric($perPage) && $perPage > 0 ? (int) $perPage : 10;

            // Retrieve the user's information with paginated ads, sold ads count, and total revenue
            $userWithAds = User::where('id', $userId)
                ->whereHas('ads') // Ensure the user has posted ads
                ->with('media')
                ->withCount(['ads as sold_ads_count' => function ($query) {
                    $query->where('status', 'sold');
                }])
                ->withSum(['ads as total_revenue' => function ($query) {
                    $query->where('status', 'sold');
                }], 'price')
                ->first();

            // If user doesn't exist, return an empty response
            if (!$userWithAds) {
                return apiResponse(false, 'No seller found.', [], 'seller', 200);
            }

            // Fetch paginated ads separately
            $ads = $userWithAds->ads()
                ->with('media')
                ->paginate($perPage);

            // If no ads exist, return an empty response
            if ($ads->isEmpty()) {
                return apiResponse(false, 'No ads found for this user.', [], 'seller', 200);
            }

            // Prepare seller data without ads
            $sellerData = $userWithAds->toArray();
            unset($sellerData['ads']); // Remove ads from seller data
            $sellerData['sold_ads_count'] = $userWithAds->sold_ads_count;
            $sellerData['total_revenue'] = $userWithAds->total_revenue ?? 0;

            // Prepare the response data
            $responseData = [
                'seller' => $sellerData,
                'ads' => $ads, // Pass the LengthAwarePaginator instance directly
            ];

            // Return the response with seller and paginated ads
            return apiResponse(true, 'User ads retrieved successfully.', $responseData, null, 200);
        } catch (\Exception $e) {
            return apiResponse(false, 'An error occurred while retrieving user ads.',
                $e->getMessage(), 'error');
        }
    }
}
