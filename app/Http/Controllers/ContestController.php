<?php

namespace App\Http\Controllers;

use App\Models\Contest;
use App\Models\User;
use App\Services\ApiResponseService;
use App\Services\FilterService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ContestController extends Controller
{
    protected $notificationService;
    protected $filterService;

    // Single constructor with both dependencies
    public function __construct(NotificationService $notificationService, FilterService $filterService)
    {
        $this->notificationService = $notificationService;
        $this->filterService = $filterService;
    }

    /**
     * @operationId All contests
     */
    public function index(Request $request)
    {
        try {
            // Authenticate the user via Sanctum
            $user = Auth::guard('sanctum')->user();
            if (!$user) {
                return apiResponse(false, 'Unauthorized: You must be logged in to view contests.', [], null);
            }

            // Validate query parameters
            $request->validate([
                'status' => 'nullable|in:active,closed',
                'title' => 'nullable|string|max:255',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
                'date_field' => 'nullable|in:created_at,start_date,end_date', // Configurable date field
                'per_page' => 'nullable|integer|min:1|max:100',
            ]);

            // Initialize query
            $query = Contest::query();

            // Apply filters using the service
            $query = $this->filterService->applyFilters($request, $query);

            // Apply default sorting (newest created_at first)
            $query->orderBy('created_at', 'desc');

            // Handle pagination
            $perPage = $request->get('per_page', 10);
            $perPage = is_numeric($perPage) && $perPage > 0 ? (int) $perPage : 10;
            $contests = $query->paginate($perPage);

            // Check if there are no contests
            if ($contests->isEmpty()) {
                return apiResponse(true, 'No contests found.', $contests, 'contests', 200);
            }

            return apiResponse(true, 'Contests retrieved successfully!', $contests, 'contests', 200);
        } catch (\Exception $e) {
            return apiResponse(false, 'An error occurred while retrieving contests.',
                $e->getMessage(), 'error');
        }
    }

    /**
     * @operationId Create contest
     */
    public function store(Request $request)
    {
        try {
            // Authenticate the user via Sanctum
            $user = Auth::guard('sanctum')->user();

            if (!$user) {
                return apiResponse(false, 'Unauthorized: You must be logged in to create a contest.', [], null);
            }

            // Check if the user is an admin and has permission to create contests
            if (!$user->hasRole('admin')) {
                return apiResponse(false, 'Unauthorized: Only admins can create contests.', [], null);
            }

            // Validate request data
            $request->validate([
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'start_date' => 'required|date|after_or_equal:today',
                'end_date' => 'required|date|after:start_date',
                'status' => 'required|in:active,closed',
            ]);

            // Create the contest
            $contest = Contest::create([
                'title' => $request->title,
                'description' => $request->description,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'status' => $request->status,
            ]);

            // Notify only non-admins about the new contest
            $this->notificationService->storeNotification(
                'promo',
                "A new contest titled '{$contest->title}' has been created!",
                Contest::class,
                $contest->id,
                'non-admins' // Target only non-admin users
            );

            return apiResponse(true, 'Contest created successfully!',
                $contest, 'contest');

        } catch (\Exception $e) {
            return ApiResponseService::handleException($e, $request->all());
        }
    }


    /**
     * @operationId Show contest
     */
    public function show($id)
    {
        try {
            // Authenticate the user via Sanctum
            $user = Auth::guard('sanctum')->user();

            if (!$user) {
                return apiResponse(false, 'Unauthorized: You must be logged in to view this contest.', [], null);
            }

            // Find the contest by ID
            $contest = Contest::find($id);

            if (!$contest) {
                return apiResponse(false, 'Contest not found.', [], null);
            }

            return apiResponse(true, 'Contest retrieved successfully!',
                $contest, 'contest');

        } catch (\Exception $e) {
            return apiResponse(false, 'An error occurred while retrieving the contest.',
                $e->getMessage(), 'error');
        }
    }

    /**
     * @operationId Update contest
     */
    public function update(Request $request, $id)
    {
        try {
            // Authenticate the user via Sanctum
            $user = Auth::guard('sanctum')->user();

            if (!$user) {
                return apiResponse(false, 'Unauthorized: You must be logged in to update a contest.', [], null);
            }

            // Check if the user is an admin and has permission to update contests
            if (!$user->hasRole('admin')) {
                return apiResponse(false, 'Unauthorized: Only admins can update contests.', [], null);
            }

            // Check if the contest exists
            $contest = Contest::find($id);
            if (!$contest) {
                return apiResponse(false, 'Contest not found.', [], null);
            }

            // Validate request data
            $request->validate([
                'title' => 'sometimes|string|max:255',
                'description' => 'nullable|string',
                'start_date' => 'sometimes|date|after_or_equal:today',
                'end_date' => 'sometimes|date|after:start_date',
                'status' => 'sometimes|in:active,closed',
            ]);

            // Update the contest with validated data
            $contest->update($request->only(['title', 'description', 'start_date', 'end_date', 'status']));

            return apiResponse(true, 'Contest updated successfully!',
                $contest, 'contest');

        } catch (\Exception $e) {
            return ApiResponseService::handleException($e, $request->all());
        }
    }

    /**
     * @operationId Delete contest
     */
    public function destroy($id)
    {
        try {
            // Authenticate the user via Sanctum
            $user = Auth::guard('sanctum')->user();

            if (!$user) {
                return apiResponse(false, 'Unauthorized: You must be logged in to delete a contest.', [], null);
            }

            // Check if the user is an admin and has permission to delete contests
            if (!$user->hasRole('admin')) {
                return apiResponse(false, 'Unauthorized: Only admins can delete contests.', [], null);
            }

            // Find the contest
            $contest = Contest::find($id);
            if (!$contest) {
                return apiResponse(false, 'Contest not found.', [], null);
            }

            // Delete the contest
            $contest->delete();

            return apiResponse(true, 'Contest deleted successfully!', [], null);

        } catch (\Exception $e) {
            return apiResponse(false, 'An error occurred while deleting the contest.',
                $e->getMessage(), 'error');
        }
    }
}
