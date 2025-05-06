<?php

namespace App\Http\Controllers;

use App\Models\ContestEntry;
use App\Models\ContestEntryVote;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ContestEntryVoteController extends Controller
{

    /**
     * @operationId Create contests entry vote
     */
    public function store(Request $request)
    {
        try {
            // Authenticate the user via Sanctum
            $user = Auth::guard('sanctum')->user();

            if (!$user) {
                return apiResponse(false, 'Unauthorized: You must be logged in to vote for a contest entry.', [], 401);
            }

            if (!$user->hasRole('user') || !$user->can('contest_entry_vote_create')) {
                return apiResponse(false, 'Unauthorized: You must have the user role and contest_entry_vote_create permission.', [], 403);
            }

            // Validate request data
            $request->validate([
                'entry_id' => 'required|exists:contest_entries,id',
            ]);

            // Find the contest entry
            $entry = ContestEntry::find($request->entry_id);
            if (!$entry) {
                return apiResponse(false, 'Contest entry not found.', [], 404);
            }

            // Prevent users from voting for their own entry
            if ($entry->user_id == $user->id) {
                return apiResponse(false, 'You cannot vote for your own contest entry.', [], 403);
            }

            // Check if the user has already voted for this entry
            $existingVote = ContestEntryVote::where('entry_id', $request->entry_id)
                ->where('user_id', $user->id)
                ->exists();

            if ($existingVote) {
                return apiResponse(false, 'You have already voted for this contest entry.', [], 409);
            }

            // Store the vote
            ContestEntryVote::create([
                'entry_id' => $request->entry_id,
                'user_id' => $user->id,
            ]);

            // Increment the votes count on the contest entry
            $entry->increment('votes_count');

            return apiResponse(true, 'Vote submitted successfully!', [], 201);

        } catch (\Exception $e) {
            return apiResponse(false, 'An error occurred while submitting the vote.', [
                'error' => $e->getMessage(),
            ], 500);
        }
    }

}
