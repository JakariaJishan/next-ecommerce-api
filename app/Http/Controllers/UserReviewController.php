<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserReview;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserReviewController extends Controller
{
    public function index(Request $request)
    {
        try {
            $request->validate([
                'user_id' => 'nullable|exists:users,id',
                'rating' => 'nullable|integer|min:1|max:5',
                'per_page' => 'nullable|integer|min:1|max:100',
            ]);

            $query = UserReview::with(['reviewer'])
                ->where('is_approved', true);

            if ($request->has('user_id')) {
                $query->where('reviewed_user_id', $request->user_id);
            }

            if ($request->has('rating')) {
                $query->where('rating', $request->rating);
            }

            $perPage = $request->get('per_page', 10);
            $reviews = $query->paginate($perPage);

            return apiResponse(true, 'User reviews retrieved successfully!', $reviews, 'reviews');
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
                'user_id' => 'required|exists:users,id',
                'rating' => 'required|integer|min:1|max:5',
                'comment' => 'nullable|string|max:1000',
            ]);

            // Prevent self-review
            if ($user->id == $validated['user_id']) {
                return apiResponse(false, 'You cannot review yourself.', [], null, 400);
            }

            // Check if user has already reviewed this seller
            $existingReview = UserReview::where('reviewer_id', $user->id)
                ->where('reviewed_user_id', $validated['user_id'])
                ->first();

            if ($existingReview) {
                return apiResponse(false, 'You have already reviewed this user.', [], null, 400);
            }

            // Check if the user has purchased from this seller
            $hasPurchased = Order::whereHas('orderItems.product', function ($query) use ($validated) {
                $query->where('user_id', $validated['user_id']);
            })->where('user_id', $user->id)->exists();

            if (!$hasPurchased) {
                return apiResponse(false, 'You can only review sellers you have purchased from.',
                    [], null, 400);
            }

            $review = UserReview::create([
                'reviewer_id' => $user->id,
                'reviewed_user_id' => $validated['user_id'],
                'rating' => $validated['rating'],
                'comment' => $validated['comment'] ?? null,
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
            $review = UserReview::with(['reviewer'])
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

            $review = UserReview::where('id', $id)
                ->where('reviewer_id', $user->id)
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

            $review = UserReview::where('id', $id)
                ->where('reviewer_id', $user->id)
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

    // Endpoint to get aggregated ratings for a user/seller
    public function userRatingSummary($userId)
    {
        try {
            $user = User::findOrFail($userId);

            $summary = [
                'average_rating' => $user->average_rating,
                'rating_count' => $user->rating_count,
                'rating_distribution' => [
                    5 => UserReview::where('reviewed_user_id', $userId)
                        ->where('is_approved', true)
                        ->where('rating', 5)
                        ->count(),
                    4 => UserReview::where('reviewed_user_id', $userId)
                        ->where('is_approved', true)
                        ->where('rating', 4)
                        ->count(),
                    3 => UserReview::where('reviewed_user_id', $userId)
                        ->where('is_approved', true)
                        ->where('rating', 3)
                        ->count(),
                    2 => UserReview::where('reviewed_user_id', $userId)
                        ->where('is_approved', true)
                        ->where('rating', 2)
                        ->count(),
                    1 => UserReview::where('reviewed_user_id', $userId)
                        ->where('is_approved', true)
                        ->where('rating', 1)
                        ->count(),
                ],
            ];

            return apiResponse(true, 'User rating summary retrieved successfully!',
                $summary, 'rating_summary');
        } catch (\Exception $e) {
            return apiResponse(false, 'An error occurred while retrieving the rating summary.',
                $e->getMessage(), 'error');
        }
    }
}
