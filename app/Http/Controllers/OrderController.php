<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $query = Order::query()->with('items');
        if ($request->user()) {
            $query->where('user_id', $request->user()->id);
        }
        return response()->json($query->paginate($request->integer('per_page', 15)));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'billing_address' => 'required|array',
            'shipping_address' => 'required|array',
            'notes' => 'nullable|string',
        ]);

        $userId = $request->user()?->id;
        $cart = Cart::with('items')->firstOrCreate(['user_id' => $userId]);
        if ($cart->items->isEmpty()) {
            return response()->json(['message' => 'Cart is empty'], 422);
        }

        return DB::transaction(function () use ($cart, $data, $userId) {
            $subtotal = $cart->items->sum(function ($item) { return $item->unit_price * $item->quantity; });
            $discount = 0;
            $shipping = 0;
            $tax = 0;
            $grand = $subtotal - $discount + $shipping + $tax;

            $order = Order::create([
                'user_id' => $userId,
                'status' => 'pending',
                'payment_status' => 'unpaid',
                'currency' => $cart->currency,
                'subtotal' => $subtotal,
                'discount_total' => $discount,
                'shipping_total' => $shipping,
                'tax_total' => $tax,
                'grand_total' => $grand,
                'billing_address' => $data['billing_address'],
                'shipping_address' => $data['shipping_address'],
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($cart->items as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item->product_id,
                    'product_name' => $item->product->name ?? 'Product',
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'currency' => $item->currency,
                    'meta' => null,
                ]);
            }

            $cart->items()->delete();

            return response()->json($order->load('items'), 201);
        });
    }

    public function show(Order $order)
    {
        return response()->json($order->load('items'));
    }

    public function updateStatus(Request $request, Order $order)
    {
        $data = $request->validate([
            'status' => 'required|string',
        ]);
        $order->update(['status' => $data['status']]);
        return response()->json($order);
    }

    public function cancel(Order $order)
    {
        if ($order->status === 'pending') {
            $order->update(['status' => 'cancelled']);
        }
        return response()->json($order);
    }
}


