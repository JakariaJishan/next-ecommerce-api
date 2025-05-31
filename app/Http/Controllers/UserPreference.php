<?php

namespace App\Http\Controllers;

use App\Models\CurrencyPreference;
use App\Models\LanguagePreference;
use App\Models\TimezonePreference;
use App\Services\ApiResponseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserPreference extends Controller
{
    public function storeAndUpdateLanguagePreference(Request $request)
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
                return apiResponse(true, 'Language preference updated successfully!',
                    $languagePreference, 'language_preference');
            }

            // Create a new language preference
            $languagePreference = $user->languagePreferences()->create([
                'language_code' => $validatedData['language_code'],
            ]);

            return apiResponse(true, 'Language preference set successfully!', $languagePreference,
                'language_preference');

        } catch (\Exception $e) {
            return ApiResponseService::handleException($e, $request->all());
        }
    }

    public function storeAndUpdateCurrencyPreference(Request $request)
    {
        try {
            // Get the authenticated user
            $user = Auth::guard('sanctum')->user();

            if (!$user) {
                return apiResponse(false, 'Unauthorized: You must be logged in.', [], null);
            }

            // Validate request data
            $validatedData = $request->validate([
                'currency_code' => 'required|string|size:3', // e.g., 'USD', 'EUR', 'GBP'
            ]);

            // Check if the currency preference already exists
            $exists = CurrencyPreference::where('user_id', $user->id)->exists();

            if ($exists) {
                // Update existing currency preference
                $user->currencyPreferences()->update([
                    'currency_code' => $validatedData['currency_code'],
                ]);
                $currencyPreference = $user->currencyPreferences;
                return apiResponse(true, 'Currency preference updated successfully!', $currencyPreference, 'currency_preference');
            }

            // Create a new currency preference
            $currencyPreference = $user->currencyPreferences()->create([
                'currency_code' => $validatedData['currency_code'],
            ]);

            return apiResponse(true, 'Currency preference set successfully!', $currencyPreference, 'currency_preference');

        } catch (\Exception $e) {
            return ApiResponseService::handleException($e, $request->all());
        }
    }

    public function storeAndUpdateTimezonePreference(Request $request)
    {
        try {
            // Get the authenticated user
            $user = Auth::guard('sanctum')->user();

            if (!$user) {
                return apiResponse(false, 'Unauthorized: You must be logged in.', [], null);
            }

            // Validate request data
            $validatedData = $request->validate([
                'timezone' => 'required|string|max:50|timezone', // e.g., 'America/New_York', 'Europe/London'
            ]);

            // Check if the timezone preference already exists
            $exists = TimezonePreference::where('user_id', $user->id)->exists();

            if ($exists) {
                // Update existing timezone preference
                $user->timezonePreferences()->update([
                    'timezone' => $validatedData['timezone'],
                ]);
                $timezonePreference = $user->timezonePreferences;
                return apiResponse(true, 'Timezone preference updated successfully!', $timezonePreference, 'timezone_preference');
            }

            // Create a new timezone preference
            $timezonePreference = $user->timezonePreferences()->create([
                'timezone' => $validatedData['timezone'],
            ]);

            return apiResponse(true, 'Timezone preference set successfully!', $timezonePreference, 'timezone_preference');

        } catch (\Exception $e) {
            return ApiResponseService::handleException($e, $request->all());
        }
    }
}
