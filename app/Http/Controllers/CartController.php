<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Http\Request;

class CartController extends Controller
{
    protected function getOrCreateCart(?int $userId, string $currency = 'USD')
    {
        return Cart::firstOrCreate(['user_id' => $userId], ['currency' => $currency]);
    }

    public function show(Request $request)
    {
        $cart = $this->getOrCreateCart($request->user()?->id);
        $cart->load('items');
        return response()->json($cart);
    }

    public function addItem(Request $request)
    {
        $data = $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
        ]);

        $cart = $this->getOrCreateCart($request->user()?->id);
        $product = Product::findOrFail($data['product_id']);

        $item = CartItem::firstOrNew([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
        ]);
        $item->quantity = ($item->exists ? $item->quantity : 0) + $data['quantity'];
        $item->unit_price = $product->sale_price ?? $product->price;
        $item->currency = $product->currency ?? $cart->currency;
        $item->save();

        $cart->load('items');
        return response()->json($cart, 201);
    }

    public function updateItem(Request $request, CartItem $cartItem)
    {
        $data = $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);
        $cartItem->update($data);
        return response()->json($cartItem->fresh());
    }

    public function removeItem(CartItem $cartItem)
    {
        $cartItem->delete();
        return response()->json(['message' => 'deleted']);
    }

    public function clear(Request $request)
    {
        $cart = $this->getOrCreateCart($request->user()?->id);
        $cart->items()->delete();
        return response()->json(['message' => 'cleared']);
    }
}


