<?php

namespace App\Http\Controllers;

use App\Models\Coupon;
use App\Services\DiscountService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class CouponController extends Controller
{
    protected $discountService;

    public function __construct(DiscountService $discountService)
    {
        $this->discountService = $discountService;
    }

    /**
     * Display a listing of coupons (admin only)
     */
    public function index(Request $request)
    {
        // Check admin permission
        if (!Auth::user()->can('manage coupons')) {
            return apiResponse(false, 'Unauthorized', [], null, 403);
        }

        $query = Coupon::query();

        // Apply filters
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Pagination
        $perPage = $request->get('per_page', 15);
        $coupons = $query->paginate($perPage);

        return apiResponse(true, 'Coupons retrieved successfully', $coupons, 'coupons');
    }

    /**
     * Store a newly created coupon (admin only)
     */
    public function store(Request $request)
    {
        // Check admin permission
        if (!Auth::user()->can('manage coupons')) {
            return apiResponse(false, 'Unauthorized', [], null, 403);
        }

        $validator = Validator::make($request->all(), [
            'code' => 'required|string|unique:coupons,code|max:50',
            'description' => 'nullable|string|max:255',
            'type' => 'required|in:percentage,fixed,bundle,bogo',
            'value' => 'required_unless:type,bundle,bogo|nullable|numeric|min:0',
            'min_order_amount' => 'nullable|numeric|min:0',
            'max_uses' => 'nullable|integer|min:1',
            'is_active' => 'boolean',
            'starts_at' => 'nullable|date',
            'expires_at' => 'nullable|date|after_or_equal:starts_at',
            'conditions' => 'nullable|json',
        ]);

        if ($validator->fails()) {
            return apiResponse(false, 'Validation failed', $validator->errors(), 'errors', 422);
        }

        // Additional validation based on coupon type
        if ($request->type === 'percentage' && $request->value > 100) {
            return apiResponse(false, 'Percentage discount cannot exceed 100%', [], null, 422);
        }

        // Validate conditions based on type
        if (in_array($request->type, ['bundle', 'bogo'])) {
            $conditions = json_decode($request->conditions, true);

            if ($request->type === 'bundle' && (!isset($conditions['category_id']) || !isset($conditions['min_quantity']) || !isset($conditions['discount_percentage']))) {
                return apiResponse(false, 'Bundle conditions must include category_id, min_quantity, and discount_percentage', [], null, 422);
            }

            if ($request->type === 'bogo' && (!isset($conditions['buy_quantity']) || !isset($conditions['get_quantity']) || !isset($conditions['discount_percentage']))) {
                return apiResponse(false, 'BOGO conditions must include buy_quantity, get_quantity, and discount_percentage', [], null, 422);
            }
        }

        $coupon = Coupon::create($validator->validated());

        return apiResponse(true, 'Coupon created successfully', $coupon, 'coupon', 201);
    }

    /**
     * Display the specified coupon (admin only)
     */
    public function show($id)
    {
        // Check admin permission
        if (!Auth::user()->can('manage coupons')) {
            return apiResponse(false, 'Unauthorized', [], null, 403);
        }

        $coupon = Coupon::findOrFail($id);

        return apiResponse(true, 'Coupon retrieved successfully', $coupon, 'coupon');
    }

    /**
     * Update the specified coupon (admin only)
     */
    public function update(Request $request, $id)
    {
        // Check admin permission
        if (!Auth::user()->can('manage coupons')) {
            return apiResponse(false, 'Unauthorized', [], null, 403);
        }

        $coupon = Coupon::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'code' => 'string|unique:coupons,code,' . $id . '|max:50',
            'description' => 'nullable|string|max:255',
            'type' => 'in:percentage,fixed,bundle,bogo',
            'value' => 'nullable|numeric|min:0',
            'min_order_amount' => 'nullable|numeric|min:0',
            'max_uses' => 'nullable|integer|min:1',
            'is_active' => 'boolean',
            'starts_at' => 'nullable|date',
            'expires_at' => 'nullable|date|after_or_equal:starts_at',
            'conditions' => 'nullable|json',
        ]);

        if ($validator->fails()) {
            return apiResponse(false, 'Validation failed', $validator->errors(), 'errors', 422);
        }

        // Additional validation based on coupon type
        if ($request->has('type') && $request->type === 'percentage' && $request->has('value') && $request->value > 100) {
            return apiResponse(false, 'Percentage discount cannot exceed 100%', [], null, 422);
        }

        $coupon->update($validator->validated());

        return apiResponse(true, 'Coupon updated successfully', $coupon, 'coupon');
    }

    /**
     * Remove the specified coupon (admin only)
     */
    public function destroy($id)
    {
        // Check admin permission
        if (!Auth::user()->can('manage coupons')) {
            return apiResponse(false, 'Unauthorized', [], null, 403);
        }

        $coupon = Coupon::findOrFail($id);
        $coupon->delete();

        return apiResponse(true, 'Coupon deleted successfully', [], null);
    }

    /**
     * Validate a coupon code (for customers)
     */
    public function validate(Request $request)
    {
        $user = Auth::guard('sanctum')->user();

        if (!$user) {
            return apiResponse(false, 'Unauthorized', [], null, 401);
        }

        $request->validate([
            'code' => 'required|string|exists:coupons,code',
        ]);

        $coupon = Coupon::where('code', $request->code)->first();

        // Get cart total if available
        $cart = $user->cart;
        $cartTotal = null;

        if ($cart) {
            $cartItems = $cart->cartItems()->with('product')->get();
            $cartTotal = $cartItems->sum(function ($item) {
                return $item->product->price * $item->quantity;
            });
        }

        $validation = $this->discountService->validateCouponForUser($coupon, $user->id, $cartTotal);

        if (!$validation['valid']) {
            return apiResponse(false, $validation['message'], [], null, 422);
        }

        // If cart exists, calculate potential discount
        $discountInfo = [];

        if ($cart && $cartItems && $cartItems->count() > 0) {
            $discountInfo = $this->discountService->calculateDiscount($cart, $coupon);
        }

        return apiResponse(true, 'Coupon is valid', [
            'coupon' => $coupon,
            'discount_info' => $discountInfo
        ], 'data');
    }
}
