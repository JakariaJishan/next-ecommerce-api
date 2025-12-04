<?php

namespace App\Http\Controllers;

use App\Models\Inventory;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function show(int $productId)
    {
        $inventory = Inventory::firstOrCreate(['product_id' => $productId]);
        return response()->json($inventory);
    }

    public function set(Request $request, int $productId)
    {
        $data = $request->validate(['quantity' => 'required|integer|min:0']);
        $inventory = Inventory::firstOrCreate(['product_id' => $productId]);
        $inventory->update(['quantity' => $data['quantity']]);
        return response()->json($inventory);
    }

    public function adjust(Request $request, int $productId)
    {
        $data = $request->validate(['delta' => 'required|integer']);
        $inventory = Inventory::firstOrCreate(['product_id' => $productId]);
        $inventory->quantity += $data['delta'];
        if ($inventory->quantity < 0) {
            $inventory->quantity = 0;
        }
        $inventory->save();
        return response()->json($inventory);
    }
}


