<?php

namespace App\Http\Controllers;

use App\Services\ApiResponseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
}
