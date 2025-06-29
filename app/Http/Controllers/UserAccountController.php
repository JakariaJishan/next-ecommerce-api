<?php

namespace App\Http\Controllers;

use App\Models\UserNotification;
use App\Services\ApiResponseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class UserAccountController extends Controller
{
    public function accountOverView(): JsonResponse
    {
        try {
            // Get the authenticated user
            $user = Auth::guard('sanctum')->user();

            if (!$user) {
                return apiResponse(false, 'Unauthorized: You must be logged in.', [], null);
            }

            // Retrieve counts for the user's account
            $overview = [
                'total_orders' => $user->orders()->count(),
                'total_wishlist_items' => $user->wishLists()->count(),
                'total_addresses' => $user->addresses()->count(),
                'total_payment_methods' => $user->paymentMethods()->count(),
                'recent_orders' => $user->orders()
                    ->select(['id', 'created_at', 'total_amount', 'status'])
                    ->withCount('items')
                    ->orderBy('created_at', 'desc')
                    ->take(5)
                    ->get(),
            ];

            return apiResponse(true, 'Account overview retrieved successfully!', $overview, 'overview');
        } catch (\Exception $e) {
            return ApiResponseService::handleException($e, []);
        }
    }

    public function updateUserInfo(Request $request): JsonResponse
    {
        try {
            // Check if the user is authenticated
            $user = Auth::guard('sanctum')->user();
            if (!$user) {
                return apiResponse(false, 'Unauthenticated.', [], null, 401);
            }

            // Validate the request
            $validated = $request->validate([
                'first_name' => 'sometimes|required|string|max:255',
                'last_name' => 'sometimes|required|string|max:255',
                'gender' => 'sometimes|required|in:male,female',
                'bio' => 'sometimes|required|string|max:1000',
                'date_of_birth' => 'sometimes|required|date|before:today',
                'phone' => 'sometimes|required|regex:/^(\+\d{1,3}[-.\s]?)?\(?\d{3}\)?[-.\s]?\d{3}[-.\s]?\d{4}$/|max:20',
                'avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120', // Max 5MB
            ]);

            // Update user fields
            if ($request->has('first_name')) {
                $user->first_name = $validated['first_name'];
            }
            if ($request->has('last_name')) {
                $user->last_name = $validated['last_name'];
            }
            if ($request->has('gender')) {
                $user->gender = $validated['gender'];
            }
            if ($request->has('bio')) {
                $user->bio = $validated['bio'];
            }
            if ($request->has('date_of_birth')) {
                $user->date_of_birth = $validated['date_of_birth'];
            }
            if ($request->has('phone')) {
                $user->phone = $validated['phone'];
            }

            // Handle avatar upload with Spatie Media Library
            if ($request->hasFile('avatar')) {
                // Remove old avatar if exists
                $user->clearMediaCollection('avatars');

                // Upload new avatar and store it in the 'avatars' collection
                $user->addMedia($request->file('avatar'))
                    ->toMediaCollection('avatars');
            }

            // Save the user changes
            $user->save();

            // Return success response with updated user info, including media
            return apiResponse(
                true,
                'User information updated successfully.',
                $user->load('media'), // Load the media relationship
                'user',
                200
            );
        } catch (\Exception $e) {
            \Log::error("Error updating user information: {$e->getMessage()}");
            return ApiResponseService::handleException($e, $request->all());
        }
    }

    public function updateUserNotification(Request $request): JsonResponse
    {
        try {
            // Check if the user is authenticated
            $user = Auth::guard('sanctum')->user();
            if (!$user) {
                return apiResponse(false, 'Unauthenticated.', [], null, 401);
            }

            // Validate the request
            $validated = $request->validate([
                'email' => 'sometimes|required|boolean',
                'sms' => 'sometimes|required|boolean',
                'marketing' => 'sometimes|required|boolean',
                'order_updates' => 'sometimes|required|boolean',
            ]);

            // Get or create the user's notification preferences
            $notification = $user->userNotifications ?? new UserNotification(['user_id' => $user->id]);

            // Update notification preferences
            if ($request->has('email')) {
                $notification->email = $validated['email'];
            }
            if ($request->has('sms')) {
                $notification->sms = $validated['sms'];
            }
            if ($request->has('marketing')) {
                $notification->marketing = $validated['marketing'];
            }
            if ($request->has('order_updates')) {
                $notification->order_updates = $validated['order_updates'];
            }

            // Save the notification preferences
            $user->userNotifications()->save($notification);

            // Return success response with updated notification preferences
            return apiResponse(
                true,
                'Notification preferences updated successfully.',
                 $notification,
                'notifications',
                200
            );
        } catch (\Exception $e) {
            return ApiResponseService::handleException($e, $request->all());
        }
    }
}
