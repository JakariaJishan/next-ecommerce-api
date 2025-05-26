<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CartController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $user = Auth::guard('sanctum')->user();
        if (!$user) {
            return apiResponse(false, 'Unauthorized: You must be logged in.', [], null, 401);
        }

        // Get the user's cart with relationships
        $cart = $user->cart()
            ->with([
                'cartItems.product.media', // Load cart items, their products, and product media
                'user.media' // Load user media
            ])
            ->first();

        if (!$cart) {
            return apiResponse(true, 'No cart found for this user.', ['cart' => null], null, 200);
        }

        return apiResponse(true, 'Cart retrieved successfully.', ['cart' => $cart], null, 200);
    }

    /**
     * Show the form for creating a new resource.
     */

    public function addToCart(Request $request, $product_id)
    {
        // Validate product_id from URL and optional quantity from request body
        $request->validate([
            'quantity' => 'nullable|integer|min:1', // Quantity is optional, defaults to 1
        ]);

        $user = Auth::guard('sanctum')->user();
        if (!$user) {
            return apiResponse(false, 'Unauthorized: You must be logged in.', [], null, 401);
        }

        // Validate product exists
        $product = Product::where('id', $product_id)->first();

        if (!$product) {
            return apiResponse(false, 'Product not found.', [], null, 404);
        }

        // Optional: Check product stock (assuming products table has a stock column)
        $quantity = $request->input('quantity', 1); // Default to 1 if quantity not provided
        if (!$product->inventory || $product->inventory->stock_quantity < $quantity) {
            return apiResponse(false, 'Insufficient stock for this product.', [], null, 400);
        }

        // Get or create the user's cart
        $cart = $user->cart()->firstOrCreate(['user_id' => $user->id]);

        // Check if product is already in cart (prevent duplicate for initial add)
        if ($cart->cartItems()->where('product_id', $product->id)->exists()) {
            return apiResponse(false, 'Product already in cart.', [], null, 400);
        }

        // Create new cart item (no update or increment)
        $cartItem = $cart->cartItems()->create([
            'product_id' => $product->id,
            'quantity' => $quantity,
            'price' => $product->price,
        ]);

        // Reload cart with relationships
        $cart->load('cartItems.product');

        return apiResponse(true, 'Product added to cart.', ['cart' => $cart], null, 200);
    }

    public function removeFromCart(Request $request, $cart_id, $product_id)
    {
        $user = Auth::guard('sanctum')->user();
        if (!$user) {
            return apiResponse(false, 'Unauthorized: You must be logged in.', [], null, 401);
        }

        // Validate cart exists and belongs to the user
        $cart = Cart::where('id', $cart_id)->where('user_id', $user->id)->first();
        if (!$cart) {
            return apiResponse(false, 'Cart not found or does not belong to you.', [], null, 404);
        }

        // Validate product exists
        $product = Product::with('inventory')->where('id', $product_id)->first();
        if (!$product) {
            return apiResponse(false, 'Product not found.', [], null, 404);
        }

        // Find the cart item
        $cartItem = $cart->cartItems()->where('product_id', $product_id)->first();
        if (!$cartItem) {
            return apiResponse(false, 'Product not found in cart.', [], null, 404);
        }

        // Delete the cart item
        $cartItem->delete();
        if ($cart->cartItems()->count() === 0) {
            $cart->delete();
            return apiResponse(true, 'Product removed and cart deleted as it was empty.', ['cart' => null], null, 200);
        }

        // Reload cart with relationships
        $cart->load('cartItems.product');

        return apiResponse(true, 'Product removed from cart.', ['cart' => $cart], null, 200);
    }

    public function increaseQuantity(Request $request, $cart_id, $cart_item_id)
    {
        $user = Auth::guard('sanctum')->user();
        if (!$user) {
            return apiResponse(false, 'Unauthorized: You must be logged in.', [], null, 401);
        }

        // Validate cart exists and belongs to the user
        $cart = Cart::where('id', $cart_id)->where('user_id', $user->id)->first();
        if (!$cart) {
            return apiResponse(false, 'Cart not found or does not belong to you.', [], null, 404);
        }

        // Validate cart item exists
        $cartItem = $cart->cartItems()->where('id', $cart_item_id)->first();
        if (!$cartItem) {
            return apiResponse(false, 'Cart item not found.', [], null, 404);
        }

        // Check stock availability
        $product = Product::with('inventory')->findOrFail($cartItem->product_id);
        if (!$product->inventory || $product->inventory->stock_quantity < ($cartItem->quantity + 1)) {
            return apiResponse(false, 'Insufficient stock for this product.', [], null, 400);
        }


        // Increment quantity
        $cartItem->increment('quantity');

        // Reload cart with relationships
        $cart->load('cartItems.product');

        return apiResponse(true, 'Cart item quantity increased.', ['cart' => $cart], null, 200);
    }

    public function decreaseQuantity(Request $request, $cart_id, $cart_item_id)
    {
        $user = Auth::guard('sanctum')->user();
        if (!$user) {
            return apiResponse(false, 'Unauthorized: You must be logged in.', [], null, 401);
        }

        // Validate cart exists and belongs to the user
        $cart = Cart::where('id', $cart_id)->where('user_id', $user->id)->first();
        if (!$cart) {
            return apiResponse(false, 'Cart not found or does not belong to you.', [], null, 404);
        }

        // Validate cart item exists
        $cartItem = $cart->cartItems()->where('id', $cart_item_id)->first();
        if (!$cartItem) {
            return apiResponse(false, 'Cart item not found.', [], null, 404);
        }


        // Decrement quantity or delete if quantity becomes 0
        if ($cartItem->quantity <= 1) {
            $cartItem->delete();
            // Check if cart is empty and delete if so
            if ($cart->cartItems()->count() === 0) {
                $cart->delete();
                return apiResponse(true, 'Cart item removed and cart deleted as it was empty.', ['cart' => null], null, 200);
            }
        } else {
            $cartItem->decrement('quantity');
        }

        // Reload cart with relationships
        $cart->load('cartItems.product');

        return apiResponse(true, 'Cart item quantity decreased.', ['cart' => $cart], null, 200);
    }
}
