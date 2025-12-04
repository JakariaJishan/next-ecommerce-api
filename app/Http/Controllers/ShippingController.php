<?php

namespace App\Http\Controllers;

use App\Models\Shipment;
use App\Models\ShippingMethod;
use Illuminate\Http\Request;

class ShippingController extends Controller
{
    public function methodsIndex()
    {
        return response()->json(ShippingMethod::query()->where('active', true)->get());
    }

    public function methodsStore(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string',
            'rate' => 'required|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'meta' => 'nullable|array',
            'active' => 'nullable|boolean',
        ]);
        $method = ShippingMethod::create($data);
        return response()->json($method, 201);
    }

    public function shipmentsIndex(Request $request)
    {
        $query = Shipment::query();
        if ($request->filled('order_id')) {
            $query->where('order_id', $request->integer('order_id'));
        }
        return response()->json($query->paginate($request->integer('per_page', 15)));
    }

    public function shipmentsStore(Request $request)
    {
        $data = $request->validate([
            'order_id' => 'required|exists:orders,id',
            'shipping_method_id' => 'nullable|exists:shipping_methods,id',
            'tracking_number' => 'nullable|string',
        ]);
        $shipment = Shipment::create($data + ['status' => 'pending']);
        return response()->json($shipment, 201);
    }

    public function shipmentsUpdate(Request $request, Shipment $shipment)
    {
        $data = $request->validate([
            'status' => 'sometimes|string',
            'tracking_number' => 'sometimes|nullable|string',
            'shipping_method_id' => 'sometimes|nullable|exists:shipping_methods,id',
        ]);
        $shipment->update($data);
        return response()->json($shipment);
    }
}


