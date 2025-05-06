<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Services\ApiResponseService;
use App\Services\FilterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    protected $filterService;

    // Assuming FilterService is injected via constructor (same as ReportController)
    public function __construct(FilterService $filterService)
    {
        $this->filterService = $filterService;
    }

    /**
     * @operationId Current User Notifications
     */
    public function index(Request $request)
    {
        try {
            // Authenticate the user via Sanctum
            $user = Auth::guard('sanctum')->user();

            if (!$user) {
                return apiResponse(false, 'Unauthorized: You must be logged in to view notifications.', [], null, 401);
            }

            // Validate query parameters
            $request->validate([
                'type' => 'nullable|string|max:255',
                'status' => 'nullable|in:unread,read',
                'notifiable_type' => 'nullable|string|max:255',
                'notifiable_id' => 'nullable|integer|min:1',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
                'per_page' => 'nullable|integer|min:1|max:100',
            ]);

            // Initialize query for the current user's notifications
            $query = Notification::where('user_id', $user->id)
                ->orderBy('created_at', 'desc');

            // Apply filters using the service (assuming FilterService handles these fields)
            $query = $this->filterService->applyFilters($request, $query);

            // Handle pagination
            $perPage = $request->get('per_page', 10);
            $perPage = is_numeric($perPage) && $perPage > 0 ? (int) $perPage : 10;
            $notifications = $query->paginate($perPage);

            if ($notifications->isEmpty()) {
                return apiResponse(true, 'No notifications found.', $notifications, 'notifications', 200);
            }

            return apiResponse(true, 'Notifications retrieved successfully!', $notifications, 'notifications', 200);

        } catch (\Exception $e) {
            return ApiResponseService::handleException($e, $request->all());
        }
    }

    /**
     * @operationId Update notification status
     */

    public function update(Request $request, $id)
    {
        try {
            $user = Auth::guard('sanctum')->user();
            if (!$user) {
                return apiResponse(false, 'Unauthorized: You must be logged in to update a notification.', [], null, 401);
            }

            $notification = Notification::find($id);
            if ($notification->user_id !== $user->id) {
                return apiResponse(false, 'Unauthorized: You can only update your own notifications.', [], null, 403);
            }

            $request->validate([
                'status' => 'nullable|in:read',
            ]);

            $notification->update([
                'status' => 'read',
            ]);

            return apiResponse(true, 'Notification marked as read successfully!', $notification, 'notification', 200);
        } catch (\Exception $e) {
            return ApiResponseService::handleException($e, $request->all());
        }
    }

    /**
     * @operationId Mark all read
     */
    public function markAllAsRead(Request $request)
    {
        try {
            $user = Auth::guard('sanctum')->user();
            if (!$user) {
                return apiResponse(false, 'Unauthorized: You must be logged in to update notifications.', [], null, 401);
            }

            // Update all unread notifications for the current user to 'read'
            $updatedCount = Notification::where('user_id', $user->id)
                ->where('status', 'unread')
                ->update(['status' => 'read']);

            if ($updatedCount === 0) {
                return apiResponse(true, 'No unread notifications found to mark as read.', [], null, 200);
            }

            return apiResponse(true, "All unread notifications ($updatedCount) marked as read successfully!", [], null, 200);
        } catch (\Exception $e) {
            return ApiResponseService::handleException($e, $request->all());
        }
    }
}
