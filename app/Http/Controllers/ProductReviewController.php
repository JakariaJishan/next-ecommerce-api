<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductReview;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProductReviewController extends Controller
{
    public function index(Request $request)
    {
        try {
            $request->validate([
                'product_id' => 'nullable|exists:products,id',
                'rating' => 'nullable|integer|min:1|max:5',
                'per_page' => 'nullable|integer|min:1|max:100',
            ]);

            $query = ProductReview::with(['user'])
                ->where('is_approved', true);

            if ($request->has('product_id')) {
                $query->where('product_id', $request->product_id);
            }

            if ($request->has('rating')) {
                $query->where('rating', $request->rating);
            }

            $perPage = $request->get('per_page', 10);
            $reviews = $query->paginate($perPage);

            return apiResponse(true, 'Product reviews retrieved successfully!', $reviews, 'reviews');
        } catch (\Exception $e) {
            return apiResponse(false, 'An error occurred while retrieving reviews.',
                $e->getMessage(), 'error');
        }
    }

    public function store(Request $request)
    {
        try {
            $user = Auth::guard('sanctum')->user();

            if (!$user) {
                return apiResponse(false, 'Unauthorized: You must be logged in to submit a review.',
                    [], null, 401);
            }

            $validated = $request->validate([
                'product_id' => 'required|exists:products,id',
                'rating' => 'required|integer|min:1|max:5',
                'comment' => 'nullable|string|max:1000',
            ]);

            // Check if user has already reviewed this product
            $existingReview = ProductReview::where('user_id', $user->id)
                ->where('product_id', $validated['product_id'])
                ->first();

            if ($existingReview) {
                return apiResponse(false, 'You have already reviewed this product.',
                    [], null, 400);
            }

            // Check if this is a verified purchase
            $isVerifiedPurchase = Order::whereHas('items', function ($query) use ($validated) {
                $query->where('product_id', $validated['product_id']);
            })->where('user_id', $user->id)->exists();

            $review = ProductReview::create([
                'user_id' => $user->id,
                'product_id' => $validated['product_id'],
                'rating' => $validated['rating'],
                'comment' => $validated['comment'] ?? null,
                'is_verified_purchase' => $isVerifiedPurchase,
            ]);

            return apiResponse(true, 'Review submitted successfully!', $review, 'review', 201);
        } catch (\Exception $e) {
            return apiResponse(false, 'An error occurred while submitting the review.',
                $e->getMessage(), 'error');
        }
    }

    public function show($id)
    {
        try {
            $review = ProductReview::with(['user'])
                ->where('id', $id)
                ->where('is_approved', true)
                ->first();

            if (!$review) {
                return apiResponse(false, 'Review not found.', [], null, 404);
            }

            return apiResponse(true, 'Review retrieved successfully!', $review, 'review');
        } catch (\Exception $e) {
            return apiResponse(false, 'An error occurred while retrieving the review.',
                $e->getMessage(), 'error');
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $user = Auth::guard('sanctum')->user();

            if (!$user) {
                return apiResponse(false, 'Unauthorized: You must be logged in to update a review.',
                    [], null, 401);
            }

            $review = ProductReview::where('id', $id)
                ->where('user_id', $user->id)
                ->first();

            if (!$review) {
                return apiResponse(false, 'Review not found or you are not authorized to update it.',
                    [], null, 404);
            }

            $validated = $request->validate([
                'rating' => 'required|integer|min:1|max:5',
                'comment' => 'nullable|string|max:1000',
            ]);

            $review->update([
                'rating' => $validated['rating'],
                'comment' => $validated['comment'] ?? $review->comment,
            ]);

            return apiResponse(true, 'Review updated successfully!', $review, 'review');
        } catch (\Exception $e) {
            return apiResponse(false, 'An error occurred while updating the review.',
                $e->getMessage(), 'error');
        }
    }

    public function destroy($id)
    {
        try {
            $user = Auth::guard('sanctum')->user();

            if (!$user) {
                return apiResponse(false, 'Unauthorized: You must be logged in to delete a review.',
                    [], null, 401);
            }

            $review = ProductReview::where('id', $id)
                ->where('user_id', $user->id)
                ->first();

            if (!$review) {
                return apiResponse(false, 'Review not found or you are not authorized to delete it.',
                    [], null, 404);
            }

            $review->delete();

            return apiResponse(true, 'Review deleted successfully!', [], null);
        } catch (\Exception $e) {
            return apiResponse(false, 'An error occurred while deleting the review.',
                $e->getMessage(), 'error');
        }
    }

    // Endpoint to get aggregated ratings for a product
    public function productRatingSummary($productId)
    {
        try {
            $product = Product::where('id', $productId)->first();

            if (!$product) {
                return apiResponse(false, 'Product not found.',
                    [], null, 404);
            }

            $summary = [
                'average_rating' => $product->average_rating,
                'rating_count' => $product->rating_count,
                'rating_distribution' => [
                    5 => ProductReview::where('product_id', $productId)
                        ->where('is_approved', true)
                        ->where('rating', 5)
                        ->count(),
                    4 => ProductReview::where('product_id', $productId)
                        ->where('is_approved', true)
                        ->where('rating', 4)
                        ->count(),
                    3 => ProductReview::where('product_id', $productId)
                        ->where('is_approved', true)
                        ->where('rating', 3)
                        ->count(),
                    2 => ProductReview::where('product_id', $productId)
                        ->where('is_approved', true)
                        ->where('rating', 2)
                        ->count(),
                    1 => ProductReview::where('product_id', $productId)
                        ->where('is_approved', true)
                        ->where('rating', 1)
                        ->count(),
                ],
            ];

            return apiResponse(true, 'Product rating summary retrieved successfully!',
                $summary, 'rating_summary');
        } catch (\Exception $e) {
            return apiResponse(false, 'An error occurred while retrieving the rating summary.',
                $e->getMessage(), 'error');
        }
    }
}
