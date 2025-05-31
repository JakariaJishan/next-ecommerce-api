<?php

namespace App\Http\Controllers;

use App\Models\LanguagePreference;
use App\Services\ApiResponseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LanguagePreferenceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
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
        try {
            // Get the authenticated user
            $user = Auth::guard('sanctum')->user();

            if (!$user) {
                return apiResponse(false, 'Unauthorized: You must be logged in.', [], null);
            }

            // Validate request data
            $validatedData = $request->validate([
                'language_code' => 'required|string|max:5', // e.g., 'en', 'es', 'fr'
            ]);

            // Check if the language preference already exists
            $exists = LanguagePreference::where('user_id', $user->id)->exists();

            if ($exists) {
                // Update existing language preference
                $user->languagePreferences()->update([
                    'language_code' => $validatedData['language_code'],
                ]);
                $languagePreference = $user->languagePreferences;
                return apiResponse(true, 'Language preference updated successfully!', $languagePreference, 'language_preference');
            }

            // Create a new language preference
            $languagePreference = $user->languagePreferences()->create([
                'language_code' => $validatedData['language_code'],
            ]);

            return apiResponse(true, 'Language preference set successfully!', $languagePreference, 'language_preference');

        } catch (\Exception $e) {
            return ApiResponseService::handleException($e, $request->all());
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(LanguagePreference $languagePreference)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(LanguagePreference $languagePreference)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, LanguagePreference $languagePreference)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(LanguagePreference $languagePreference)
    {
        //
    }
}
