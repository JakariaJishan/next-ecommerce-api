<?php

namespace App\Http\Controllers;

use App\Models\SearchHistory;
use Illuminate\Http\Request;

class SearchHistoryController extends Controller
{
    /**
     * @operationId All search histories
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user || !$user->hasRole('admin')) {
                // Return an unauthorized response if the user doesn't have the required permissions
                return apiResponse(false, 'Unauthorized: You must have the admin role and search_histories_view permission.', [], 403);
            }
            $perPage = $request->get('per_page', 10);
            $perPage = is_numeric($perPage) && $perPage > 0 ? (int) $perPage : 10;

            // Fetch search history records with pagination, ordered by latest searches first
            $searchHistories = SearchHistory::with('user')
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            if ($searchHistories->isEmpty()) {
                return apiResponse(true, 'No search history found.', $searchHistories, 'search_histories', 200);
            }

            return apiResponse(true, 'Search history retrieved successfully!', $searchHistories, 'search_histories', 200);

        } catch (\Exception $e) {
            return apiResponse(false, 'An error occurred while fetching search history.', [
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(SearchHistory $searchHistory)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(SearchHistory $searchHistory)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, SearchHistory $searchHistory)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(SearchHistory $searchHistory)
    {
        //
    }
}
