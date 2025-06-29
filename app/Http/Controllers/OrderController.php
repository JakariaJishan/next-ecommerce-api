<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\DiscountService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    protected $discountService;

    public function __construct(DiscountService $discountService)
    {
        $this->discountService = $discountService;
    }

    public function index(Request $request)
    {
        $user = Auth::guard('sanctum')->user();

        if (!$user) {
            return apiResponse(false, 'Unauthorized', [], null, 401);
        }

        try {
            // Validate query parameters
            $request->validate([
                'status' => 'nullable|in:pending,completed,cancelled,shipped,unpaid', // adjust based on your app
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
                'per_page' => 'nullable|integer|min:1|max:100',
            ]);

            // Base query
            $query = $user->orders()->with(['items.product', 'shippingAddress', 'billingAddress'])->latest();

            // Apply filters
            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            if ($request->filled('start_date')) {
                $query->whereDate('created_at', '>=', $request->start_date);
            }

            if ($request->filled('end_date')) {
                $query->whereDate('created_at', '<=', $request->end_date);
            }

            // Pagination
            $perPage = $request->get('per_page', 10);
            $perPage = is_numeric($perPage) && $perPage > 0 ? (int) $perPage : 10;

            $orders = $query->paginate($perPage);

            // Check for empty result
            if ($orders->isEmpty()) {
                return apiResponse(false, 'No orders found.', ['orders' => []]);
            }

            return apiResponse(true, 'User orders fetched successfully.', $orders, 'orders', 200);

        } catch (\Exception $e) {
            return apiResponse(false, 'An error occurred while retrieving orders.', $e->getMessage(), 'error');
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $user = Auth::guard('sanctum')->user();

        if (!$user) {
            return apiResponse(false, 'Unauthorized: You must be logged in to place an order.', [], null, 401);
        }

        $validated = $request->validate([
            'shipping_address_id' => 'required|exists:addresses,id',
            'billing_address_id' => 'nullable|exists:addresses,id',
            'payment_method' => 'required|in:online,offline',
            'coupon_code' => 'nullable|string|exists:coupons,code',
        ]);

        $cart = $user->cart;

        if (!$cart) {
            return apiResponse(false, 'Cart not found for this user.', [], null, 404);
        }

        $cartItems = $cart->cartItems()->with('product')->get();

        if ($cartItems->isEmpty()) {
            return apiResponse(false, 'Cart is empty. Add products before placing an order.', [], null, 400);
        }

        // Calculate subtotal
        $subtotal = $cartItems->sum(function ($item) {
            return $item->product->price * $item->quantity;
        });

        // Initialize discount variables
        $discountAmount = 0;
        $couponId = null;
        $finalAmount = $subtotal;

        // Apply coupon if provided
        if (isset($validated['coupon_code'])) {
            $coupon = Coupon::where('code', $validated['coupon_code'])->first();

            // Validate coupon
            $validation = $this->discountService->validateCouponForUser($coupon, $user->id, $subtotal);

            if (!$validation['valid']) {
                return apiResponse(false, $validation['message'], [], null, 422);
            }

            // Calculate discount
            $discountResult = $this->discountService->calculateDiscount($cart, $coupon);

            if ($discountResult['success']) {
                $discountAmount = $discountResult['discount'];
                $finalAmount = $discountResult['final_amount'];
                $couponId = $coupon->id;
            }
        }

        // Start DB transaction
        DB::beginTransaction();

        try {
            // Create Order
            $order = Order::create([
                'user_id' => $user->id,
                'shipping_address_id' => $validated['shipping_address_id'],
                'billing_address_id' => $validated['billing_address_id'] ?? null,
                'payment_method' => $validated['payment_method'],
                'payment_status' => 'unpaid',
                'status' => 'pending',
                'subtotal' => $subtotal,
                'discount_amount' => $discountAmount,
                'final_amount' => $finalAmount,
                'coupon_id' => $couponId,
            ]);

            // Create OrderItems
            foreach ($cartItems as $item) {
                $order->items()->create([
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                    'price' => $item->product->price,
                ]);
            }

            // Record coupon usage if applied
            if ($couponId) {
                $user->coupons()->attach($couponId, [
                    'order_id' => $order->id,
                    'discount_amount' => $discountAmount,
                ]);

                // Increment used_count
                $coupon->increment('used_count');
            }

            // Clear user's cart
            $cart->cartItems()->delete();

            DB::commit();

            return apiResponse(true, 'Order placed successfully!',
                $order->load('items', 'coupon'), 'order', 200);
        } catch (\Exception $e) {
            DB::rollBack();

            return apiResponse(false, 'Failed to place order.', [], $e->getMessage(), 500);
        }
    }


    public function show($order_id)
    {
        $user = Auth::guard('sanctum')->user();

        if (!$user) {
            return apiResponse(false, 'Unauthorized', [], null, 401);
        }

        // Fetch the order only if it belongs to the authenticated user
        $order = $user->orders()
            ->with([
                'items.product.media', // Eager load product and its media
                'shippingAddress',
                'billingAddress'
            ])
            ->find($order_id);

        if (!$order) {
            return apiResponse(false, 'Order not found or access denied.', [], null, 404);
        }

        return apiResponse(true, 'Order fetched successfully.', $order, 'order', 200);
    }
    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Order $order)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Order $order)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Order $order)
    {
        //
    }
}
