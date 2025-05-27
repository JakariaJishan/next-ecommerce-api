<?php

namespace App\Http\Controllers;

use App\Models\Address;
use App\Services\ApiResponseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AddressController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        try {
            // Step 1: Auth check
            $user = Auth::guard('sanctum')->user();
            if (!$user) {
                return apiResponse(false, 'Unauthorized: You must be logged in to view addresses.', [], null, 401);
            }

            // Step 2: Fetch all billing and shipping addresses
            $billingAddresses = $user->addresses()->where('type', 'billing')->get();
            $shippingAddresses = $user->addresses()->where('type', 'shipping')->get();

            // Step 3: Return the response
            return apiResponse(true, 'Addresses retrieved successfully.', [

                'billing' => $billingAddresses,
                'shipping' => $shippingAddresses,

            ], 'addresses', 200);

        } catch (\Exception $e) {
            return ApiResponseService::handleException($e);
        }
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            // Step 1: Auth check
            $user = Auth::guard('sanctum')->user();
            if (!$user) {
                return apiResponse(false, 'Unauthorized: You must be logged in to store addresses.', [], null, 401);
            }

            // Step 2: Validation
            $validated = $request->validate([
                'billing.address_line1' => 'required|string',
                'billing.address_line2' => 'nullable|string',
                'billing.city' => 'required|string',
                'billing.state' => 'nullable|string',
                'billing.postal_code' => 'required|string',
                'billing.country' => 'required|string',
                'billing.phone' => 'nullable|string',

                'shipping.address_line1' => 'required|string',
                'shipping.address_line2' => 'nullable|string',
                'shipping.city' => 'required|string',
                'shipping.state' => 'nullable|string',
                'shipping.postal_code' => 'required|string',
                'shipping.country' => 'required|string',
                'shipping.phone' => 'nullable|string',
            ]);

            // Step 3: Wrap in DB transaction
            [$billing, $shipping] = DB::transaction(function () use ($validated, $user) {
                $billing = $user->addresses()->create(array_merge(
                    $validated['billing'],
                    ['type' => 'billing']
                ));

                $shipping = $user->addresses()->create(array_merge(
                    $validated['shipping'],
                    ['type' => 'shipping']
                ));

                return [$billing, $shipping];
            });

            $user->load('media');

            // Step 4: Return success response
            return apiResponse(true, 'Addresses saved successfully.', [
                'addresses' => [
                    'billing' => $billing,
                    'shipping' => $shipping,
                ],
                'user' => $user,
            ], null, 201);

        } catch (\Exception $e) {
            return ApiResponseService::handleException($e, $request->all());
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Address $address)
    {
        //
    }


    public function update(Request $request, $id): JsonResponse
    {
        try {
            // Step 1: Auth check
            $user = Auth::guard('sanctum')->user();
            if (!$user) {
                return apiResponse(false, 'Unauthorized: You must be logged in to update addresses.', [], null, 401);
            }

            $address = Address::where('id', $id)->first();

            if (!$address) {
                return apiResponse(false, 'Address not found.', [], null, 404);
            }

            // Step 2: Ownership check
            if ($address->user_id !== $user->id) {
                return apiResponse(false, 'Forbidden: You can only update your own addresses.', [], null, 403);
            }

            // Step 3: Validation
            $validated = $request->validate([
                'address_line1' => 'sometimes|required|string',
                'address_line2' => 'nullable|string',
                'city' => 'sometimes|required|string',
                'state' => 'nullable|string',
                'postal_code' => 'sometimes|required|string',
                'country' => 'sometimes|required|string',
                'phone' => 'nullable|string',
            ]);

            // Step 4: Update the address
            $address->update($validated);

            // Step 5: Return success response
            return apiResponse(true, ucfirst($address->type) . ' address updated successfully.', [
                'addresses' => [
                    $address->type => $address,
                ],
            ]);

        } catch (\Exception $e) {
            return ApiResponseService::handleException($e);
        }
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id): JsonResponse
    {
        try {
            // Step 1: Auth check
            $user = Auth::guard('sanctum')->user();
            if (!$user) {
                return apiResponse(false, 'Unauthorized: You must be logged in to delete addresses.', [], null, 401);
            }

            // Step 2: Ownership check
            $address = Address::where('id', $id)->first();

            if (!$address) {
                return apiResponse(false, 'Address not found.', [], null, 404);
            }

            // Step 2: Ownership check
            if ($address->user_id !== $user->id) {
                return apiResponse(false, 'Forbidden: You can only update your own addresses.', null, 403);
            }

            // Step 3: Delete the address
            $address->delete();

            // Step 4: Return success response
            return apiResponse(true, 'Address deleted successfully!', [], null, 200);

        } catch (\Exception $e) {
            return ApiResponseService::handleException($e);
        }
    }
}
