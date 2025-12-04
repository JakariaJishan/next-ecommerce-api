<?php

namespace App\Http\Controllers;

use App\Models\Coupon;
use Illuminate\Http\Request;

class CouponController extends Controller
{
    public function index()
    {
        return response()->json(Coupon::query()->latest()->paginate(15));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'code' => 'required|string|unique:coupons,code',
            'type' => 'required|in:percent,fixed',
            'value' => 'required|numeric|min:0',
            'max_uses' => 'nullable|integer|min:1',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after:starts_at',
            'active' => 'nullable|boolean',
            'meta' => 'nullable|array',
        ]);
        $coupon = Coupon::create($data);
        return response()->json($coupon, 201);
    }

    public function show(Coupon $coupon)
    {
        return response()->json($coupon);
    }

    public function update(Request $request, Coupon $coupon)
    {
        $data = $request->validate([
            'type' => 'sometimes|in:percent,fixed',
            'value' => 'sometimes|numeric|min:0',
            'max_uses' => 'sometimes|nullable|integer|min:1',
            'starts_at' => 'sometimes|nullable|date',
            'ends_at' => 'sometimes|nullable|date|after:starts_at',
            'active' => 'sometimes|boolean',
            'meta' => 'sometimes|nullable|array',
        ]);
        $coupon->update($data);
        return response()->json($coupon);
    }

    public function destroy(Coupon $coupon)
    {
        $coupon->delete();
        return response()->json(['message' => 'deleted']);
    }
}


