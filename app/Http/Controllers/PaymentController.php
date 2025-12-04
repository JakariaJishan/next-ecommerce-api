<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Transaction;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $query = Payment::query()->with('order');
        if ($request->filled('order_id')) {
            $query->where('order_id', $request->integer('order_id'));
        }
        return response()->json($query->paginate($request->integer('per_page', 15)));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'order_id' => 'required|exists:orders,id',
            'provider' => 'nullable|string',
            'amount' => 'required|numeric|min:0',
            'currency' => 'nullable|string|size:3',
        ]);

        $order = Order::findOrFail($data['order_id']);
        $payment = Payment::create([
            'order_id' => $order->id,
            'provider' => $data['provider'] ?? 'manual',
            'status' => 'pending',
            'amount' => $data['amount'],
            'currency' => $data['currency'] ?? $order->currency,
            'payload' => null,
        ]);

        return response()->json($payment, 201);
    }

    public function show(Payment $payment)
    {
        return response()->json($payment->load('transactions'));
    }

    public function webhook(Request $request)
    {
        // Placeholder for gateway webhook integration
        return response()->json(['message' => 'ok']);
    }

    public function capture(Payment $payment)
    {
        $payment->update(['status' => 'paid']);
        $payment->order->update(['payment_status' => 'paid', 'status' => 'processing']);
        Transaction::create([
            'payment_id' => $payment->id,
            'type' => 'capture',
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'payload' => null,
        ]);
        return response()->json($payment->fresh());
    }
}


