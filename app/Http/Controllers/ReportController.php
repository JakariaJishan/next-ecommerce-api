<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessAdReport;
use App\Models\Report;
use App\Services\ApiResponseService;
use App\Services\FilterService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReportController extends Controller
{
    protected $filterService;
    protected $notificationService;

    public function __construct(FilterService $filterService, NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
        $this->filterService = $filterService;
    }

    /**
     * @operationId All reports
     */
    public function index(Request $request)
    {
        try {
            // Authenticate the user via Sanctum
            $user = Auth::guard('sanctum')->user();

            if (!$user) {
                return apiResponse(false, 'Unauthorized: You must be logged in to view reports.', [], null);
            }

            // Check if the user is an admin and has permission to view reports
            if (!$user->hasRole('admin')) {
                return apiResponse(false, 'Unauthorized: Only admins can view reports.', [], null);
            }

            // Validate query parameters
            $request->validate([
                'user_id' => 'nullable|exists:users,id',
                'ad_id' => 'nullable|exists:ads,id',
                'reason' => 'nullable|string|max:1000',
                'status' => 'nullable|in:pending,resolved,dismissed',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
                'per_page' => 'nullable|integer|min:1|max:100',
            ]);

            // Initialize query
            $query = Report::with(['ad.media', 'user.media'])
                ->orderBy('created_at', 'desc');

            // Apply filters using the service
            $query = $this->filterService->applyFilters($request, $query);

            // Handle pagination
            $perPage = $request->get('per_page', 10);
            $perPage = is_numeric($perPage) && $perPage > 0 ? (int) $perPage : 10;
            $reports = $query->paginate($perPage);

            if ($reports->isEmpty()) {
                return apiResponse(true, 'No reports found.', $reports, 'reports', 200);
            }

            return apiResponse(true, 'Reports retrieved successfully!', $reports, 'reports', 200);

        } catch (\Exception $e) {
            return apiResponse(false, 'An error occurred while retrieving reports.',
                $e->getMessage(), 'error');
        }
    }

    /**
     * @operationId Create report
     */
    public function store(Request $request)
    {
        try {
            $user = Auth::guard('sanctum')->user();

            if (!$user) {
                return apiResponse(false, 'Unauthorized: You must be logged in to report an ad.', [], null);
            }

            if (!$user->hasRole('user')) {
                return apiResponse(false, 'Unauthorized: You must have the user role and report_create permission.', [], null);
            }

            $request->validate([
                'ad_id' => 'required|exists:ads,id',
                'reason' => 'required|string|max:1000',
            ]);

            $existingReport = Report::where('user_id', $user->id)
                ->where('ad_id', $request->ad_id)
                ->first();

            if ($existingReport) {
                return apiResponse(false, 'You have already reported this ad.', [], null);
            }

            $report = Report::create([
                'user_id' => $user->id,
                'ad_id' => $request->ad_id,
                'reason' => $request->reason,
                'status' => 'pending',
            ]);

            // Dispatch a job to process the report
            ProcessAdReport::dispatch($request->ad_id);

            $this->notificationService->storeNotification(
                'message',
                "A new report has been created!",
                Report::class,
                $report->id,
                'admins' // Target only admin users
            );

            return apiResponse(true, 'Report submitted successfully!',
                $report, 'report');

        } catch (\Exception $e) {
            return ApiResponseService::handleException($e, $request->all());
        }
    }

    /**
     * @operationId Show report
     */
    public function show($id)
    {
        try {
            // Authenticate the user via Sanctum
            $user = Auth::guard('sanctum')->user();

            if (!$user) {
                return apiResponse(false, 'Unauthorized: You must be logged in to see report.', [], null);
            }

            // Check if the user is an admin and has permission to view reports
            if (!$user || !$user->hasRole('admin') ) {
                return apiResponse(false, 'Unauthorized: Only admins can view reported ads.', [], null);
            }

            $report = Report::find($id);

            if (!$report) {
                return apiResponse(false, 'Report not found.', [], null);
            }

            // Load the related ad and user who reported it
            $report->load(['ad.media', 'user.media']);

            // Return the report details along with ad and user info
            return apiResponse(true, 'Report details retrieved successfully!',
                $report, 'report');

        } catch (\Exception $e) {
            return apiResponse(false, 'An error occurred while retrieving the report.',
                $e->getMessage(), 'error');
        }
    }

    /**
     * @operationId Update report
     */
    public function update(Request $request, $id)
    {
        try {
            // Authenticate the user via Sanctum
            $user = Auth::guard('sanctum')->user();

            if (!$user) {
                return apiResponse(false, 'Unauthorized: You must be logged in to update a report.', [], null);
            }

            // Check if the user is an admin and has permission to update reports
            if (!$user->hasRole('admin')) {
                return apiResponse(false, 'Unauthorized: Only admins can update report status.', [], null);
            }

            // Check if the report exists
            $report = Report::find($id);
            if (!$report) {
                return apiResponse(false, 'Report not found.', [], null);
            }

            // Validate the request (only status field is allowed)
            $request->validate([
                'status' => 'required|in:pending,resolved,dismissed',
            ]);

            // Update the report status
            $report->status = $request->status;
            $report->save();

            return apiResponse(true, 'Report status updated successfully!',
                $report->load('ad.media', 'user.media'), 'report');

        } catch (\Exception $e) {
            return apiResponse(false, 'An error occurred while updating the report.',
                $e->getMessage(), 'error');
        }
    }

}
