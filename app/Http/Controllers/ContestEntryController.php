<?php

namespace App\Http\Controllers;

use App\Models\Contest;
use App\Models\ContestEntry;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ContestEntryController extends Controller
{
    /**
     * @operationId All contests entries
     */
    public function index()
    {
        try {
            // Authenticate the user via Sanctum
            $user = Auth::guard('sanctum')->user();

            if (!$user) {
                return apiResponse(false, 'Unauthorized: You must be logged in to view your contest entries.', [], null);
            }

            if (!$user->hasRole('user')) {
                return apiResponse(false, 'Unauthorized: You must have the user role and contest_entry_view permission.', [], null);
            }

            $perPage = request()->get('per_page', 10);
            $perPage = is_numeric($perPage) && $perPage > 0 ? (int) $perPage : 10;

            // Retrieve contest entries with pagination for the authenticated user
            $entries = ContestEntry::with('contest')
                ->where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            if ($entries->isEmpty()) {
                return apiResponse(true, 'No contest entries found for the current user.', $entries, 'entries', 200);
            }

            return apiResponse(true, 'Contest entries retrieved successfully!', $entries, 'entries', 200);

        } catch (\Exception $e) {
            return apiResponse(false, 'An error occurred while retrieving contest entries.',
                $e->getMessage(), 'error');
        }
    }

    /**
     * @operationId Create contests entries
     */
    public function store(Request $request)
    {
        try {
            // Authenticate the user via Sanctum
            $user = Auth::guard('sanctum')->user();

            if (!$user) {
                return apiResponse(false, 'Unauthorized: You must be logged in to submit an entry.', [], null);
            }

            // Check if the user has the 'user' role and 'contest_entry_create' permission
            if (!$user->hasRole('user') ) {
                return apiResponse(false, 'Unauthorized: You must have the user role and contest_entry_create permission.', [], null);
            }

            // Validate request data
            $request->validate([
                'contest_id' => 'required|exists:contests,id',
                'contest_url' => 'required|url',
            ]);

            // Check if the contest exists
            $contest = Contest::find($request->contest_id);
            if (!$contest) {
                return apiResponse(false, 'Contest not found.', [], null);
            }

            // Prevent submission if the contest's end date has passed
            if (Carbon::now()->greaterThan(Carbon::parse($contest->end_date))) {
                return apiResponse(false, 'The contest has ended. You cannot submit an entry.', [], null);
            }

            // Check if the user has already submitted an entry for this contest
            $existingEntry = ContestEntry::where('contest_id', $request->contest_id)
                ->where('user_id', $user->id)
                ->first();

            if ($existingEntry) {
                return apiResponse(false, 'You have already submitted an entry for this contest.', [], null);
            }

            // Create the contest entry
            $entry = ContestEntry::create([
                'contest_id' => $request->contest_id,
                'user_id' => $user->id,
                'contest_url' => $request->contest_url,
                'votes_count' => 0, // Default votes count
            ]);

            return apiResponse(true, 'Contest entry submitted successfully!',
                $entry, 'entry');

        } catch (\Exception $e) {
            return apiResponse(false, 'An error occurred while submitting the contest entry.',
                $e->getMessage(), 'error');
        }
    }

}
