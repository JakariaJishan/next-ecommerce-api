<?php

namespace App\Http\Controllers;

use App\Models\PaymentMethod;
use App\Services\ApiResponseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PaymentMethodController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // Get the authenticated user
            $user = Auth::guard('sanctum')->user();

            if (!$user) {
                return apiResponse(false, 'Unauthorized: You must be logged in.', [], null);
            }

            // Validate query parameters
            $request->validate([
                'per_page' => 'nullable|integer|min:1|max:100',
            ]);

            // Retrieve payment methods with pagination
            $perPage = $request->get('per_page', 10);
            $perPage = is_numeric($perPage) && $perPage > 0 ? (int)$perPage : 10;
            $paymentMethods = $user->paymentMethods()
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            // Mask sensitive data (e.g., card_number, account_number)
            $paymentMethods->getCollection()->transform(function ($paymentMethod) {
                if ($paymentMethod->payment_type === 'credit_debit_card' && $paymentMethod->card_number) {
                    $paymentMethod->card_number = '****' . substr($paymentMethod->card_number, -4);
                } elseif ($paymentMethod->payment_type === 'bank_account' && $paymentMethod->account_number) {
                    $paymentMethod->account_number = '****' . substr($paymentMethod->account_number, -4);
                }
                return $paymentMethod;
            });

            // Check if there are no payment methods
            if ($paymentMethods->isEmpty()) {
                return apiResponse(true, 'No payment methods found.', ['payment_methods' => $paymentMethods], 'payment_methods', 200);
            }

            return apiResponse(true, 'Payment methods retrieved successfully!', ['payment_methods' => $paymentMethods], 'payment_methods', 200);
        } catch (\Exception $e) {
            return ApiResponseService::handleException($e, []);
        }
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            // Get the authenticated user
            $user = Auth::guard('sanctum')->user();

            if (!$user) {
                return apiResponse(false, 'Unauthorized: You must be logged in.', [], null);
            }

            // Define base validation rules
            $rules = [
                'payment_type' => 'required|in:credit_debit_card,paypal,bank_account',
                'full_name' => 'required|string|max:255',
                'address' => 'required|string|max:255',
                'city' => 'required|string|max:100',
                'zip' => 'required|string|max:20',
                'state' => 'required|string|max:100',
                'set_as_default' => 'boolean',
            ];

            // Add conditional validation rules based on payment_type
            if ($request->payment_type === 'credit_debit_card') {
                $rules = array_merge($rules, [
                    'card_number' => 'required|string|max:20',
                    'expiry_month' => 'required|string|size:2',
                    'expiry_year' => 'required|string|size:4',
                    'card_type' => 'required|in:visa,mastercard,american_express,discover',
                    'card_holder_name' => 'required|string|max:255',
                ]);
            } elseif ($request->payment_type === 'paypal') {
                $rules['paypal_email'] = 'required|email|max:255';
            } elseif ($request->payment_type === 'bank_account') {
                $rules['bank_name'] = 'required|string|max:255';
                $rules['account_number'] = 'required|string|max:50';
            }

            // Validate request data
            $validated = $request->validate($rules);

            if ($request->payment_type === 'credit_debit_card') {
                $existing = $user->paymentMethods()
                    ->where('payment_type', 'credit_debit_card')
                    ->whereNotNull('card_number')
                    ->get()
                    ->firstWhere('card_number', $validated['card_number']);
                if ($existing) {
                    return apiResponse(false, 'This card is already registered.', [], null, 422);
                }
            } elseif ($request->payment_type === 'paypal') {
                $existing = $user->paymentMethods()
                    ->where('payment_type', 'paypal')
                    ->whereNotNull('paypal_email')
                    ->get()
                    ->firstWhere('paypal_email', $validated['paypal_email']);
                if ($existing) {
                    return apiResponse(false, 'This PayPal email is already registered.', [], null, 422);
                }
            } elseif ($request->payment_type === 'bank_account') {
                $existing = $user->paymentMethods()
                    ->where('payment_type', 'bank_account')
                    ->whereNotNull('account_number')
                    ->get()
                    ->firstWhere('account_number', $validated['account_number']);
                if ($existing) {
                    return apiResponse(false, 'This bank account is already registered.', [], null, 422);
                }
            }

            // If set_as_default is true, unset default for other payment methods
            if ($validated['set_as_default']) {
                $user->paymentMethods()->where('set_as_default', true)->update(['set_as_default' => false]);
            }

            // Create the payment method
            $paymentMethod = $user->paymentMethods()->create($validated);

            return apiResponse(true, 'Payment method created successfully!', $paymentMethod, 'payment_method', 201);
        } catch (\Exception $e) {
            return ApiResponseService::handleException($e, $request->all());
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(PaymentMethod $paymentMethod)
    {
        //
    }


    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $payment_method_id): JsonResponse
    {
        try {
            // Get the authenticated user
            $user = Auth::guard('sanctum')->user();

            if (!$user) {
                return apiResponse(false, 'Unauthorized: You must be logged in.', [], null);
            }

            $paymentMethod = PaymentMethod::where('id', $payment_method_id)
                ->first();
            // Check if the payment method belongs to the authenticated user
            if ($paymentMethod->user_id !== $user->id) {
                return apiResponse(false, 'Forbidden: You can only update your own payment methods.', [], null, 403);
            }

            // Define base validation rules
            $rules = [
                'payment_type' => 'required|in:credit_debit_card,paypal,bank_account',
                'full_name' => 'required|string|max:255',
                'address' => 'required|string|max:255',
                'city' => 'required|string|max:100',
                'zip' => 'required|string|max:20',
                'state' => 'required|string|max:100',
                'set_as_default' => 'boolean',
            ];

            // Add conditional validation rules based on payment_type
            if ($request->payment_type === 'credit_debit_card') {
                $rules = array_merge($rules, [
                    'card_number' => 'required|string|max:20',
                    'expiry_month' => 'required|string|size:2',
                    'expiry_year' => 'required|string|size:4',
                    'card_type' => 'required|in:visa,mastercard,american_express,discover',
                    'card_holder_name' => 'required|string|max:255',
                ]);
            } elseif ($request->payment_type === 'paypal') {
                $rules['paypal_email'] = 'required|email|max:255';
            } elseif ($request->payment_type === 'bank_account') {
                $rules['bank_name'] = 'required|string|max:255';
                $rules['account_number'] = 'required|string|max:50';
            }

            // Validate request data
            $validated = $request->validate($rules);

            // Check for duplicate payment methods (excluding the current payment method)
            if ($request->payment_type === 'credit_debit_card') {
                $existing = $user->paymentMethods()
                    ->where('payment_type', 'credit_debit_card')
                    ->whereNotNull('card_number')
                    ->where('id', '!=', $paymentMethod->id)
                    ->get()
                    ->firstWhere('card_number', $validated['card_number']);
                if ($existing) {
                    return apiResponse(false, 'This card is already registered.', [], null, 422);
                }
            } elseif ($request->payment_type === 'paypal') {
                $existing = $user->paymentMethods()
                    ->where('payment_type', 'paypal')
                    ->whereNotNull('paypal_email')
                    ->where('id', '!=', $paymentMethod->id)
                    ->get()
                    ->firstWhere('paypal_email', $validated['paypal_email']);
                if ($existing) {
                    return apiResponse(false, 'This PayPal email is already registered.', [], null, 422);
                }
            } elseif ($request->payment_type === 'bank_account') {
                $existing = $user->paymentMethods()
                    ->where('payment_type', 'bank_account')
                    ->whereNotNull('account_number')
                    ->where('id', '!=', $paymentMethod->id)
                    ->get()
                    ->firstWhere('account_number', $validated['account_number']);
                if ($existing) {
                    return apiResponse(false, 'This bank account is already registered.', [], null, 422);
                }
            }

            // If set_as_default is true, unset default for other payment methods
            if ($validated['set_as_default']) {
                $user->paymentMethods()->where('set_as_default', true)->where('id', '!=', $paymentMethod->id)->update(['set_as_default' => false]);
            }

            // Update the payment method
            $paymentMethod->update($validated);

            return apiResponse(true, 'Payment method updated successfully!', $paymentMethod, 'payment_method', 200);
        } catch (\Exception $e) {
            return ApiResponseService::handleException($e, $request->all());
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, $payment_method_id): JsonResponse
    {
        try {
            // Get the authenticated user
            $user = Auth::guard('sanctum')->user();

            if (!$user) {
                return apiResponse(false, 'Unauthorized: You must be logged in.', [], null);
            }

            // Find the payment method
            $paymentMethod = PaymentMethod::where('id', $payment_method_id)
                ->where('user_id', $user->id)
                ->first();

            if (!$paymentMethod) {
                return apiResponse(false, 'Payment method not found or you do not have permission to delete it.', [], null, 404);
            }

            // Delete the payment method
            $paymentMethod->delete();

            return apiResponse(true, 'Payment method deleted successfully!', [], null, 200);
        } catch (\Exception $e) {
            return ApiResponseService::handleException($e, []);
        }
    }
}
