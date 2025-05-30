<?php

namespace App\Http\Controllers;

use App\Models\WishList;
use App\Services\ApiResponseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WishListController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        try {
            // Get the authenticated user
            $user = Auth::guard('sanctum')->user();

            if (!$user) {
                return apiResponse(false, 'Unauthorized: You must be logged in.', [], null);
            }

            // Fetch the user's wishlist with products and their media
            $wishLists = $user->wishLists()->with(['product' => function ($query) {
                $query->with('media');
            }])->get();

            return apiResponse(true, 'Wishlist retrieved successfully!', $wishLists, 'wish_lists');

        } catch (\Exception $e) {
            return ApiResponseService::handleException($e, []);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            // Get the authenticated user
            $user = Auth::guard('sanctum')->user();

            if (!$user) {
                return apiResponse(false, 'Unauthorized: You must be logged in.', [], null);
            }

            // Validate request data
            $validatedData = $request->validate([
                'product_id' => 'required|exists:products,id',
            ]);

            // Check if the wishlist entry already exists
            $exists = WishList::where('user_id', $user->id)
                ->where('product_id', $validatedData['product_id'])
                ->exists();

            if ($exists) {
                return apiResponse(false, 'Product is already in the wishlist.', [], null);
            }

            // Create a new wishlist entry
            $wishList = $user->wishLists()->create([
                'product_id' => $validatedData['product_id'],
            ]);

            return apiResponse(true, 'Product added to wishlist successfully!', $wishList, 'wish_list');

        } catch (\Exception $e) {
            return ApiResponseService::handleException($e, $request->all());
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(WishList $wishList)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, WishList $wishList)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, $product_id): JsonResponse
    {
        try {
            // Get the authenticated user
            $user = Auth::guard('sanctum')->user();

            if (!$user) {
                return apiResponse(false, 'Unauthorized: You must be logged in.', [], null);
            }

            // Find the wishlist entry for the user and product
            $wishList = WishList::where('user_id', $user->id)
                ->where('product_id', $product_id)
                ->first();

            if (!$wishList) {
                return apiResponse(false, 'Wishlist item not found.', [], null);
            }


            // Delete the wishlist entry
            $wishList->delete();

            return apiResponse(true, 'Product removed from wishlist successfully!', [], null);

        } catch (\Exception $e) {
            return ApiResponseService::handleException($e, []);
        }
    }
}
