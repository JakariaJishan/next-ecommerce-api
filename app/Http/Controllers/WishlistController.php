<?php

namespace App\Http\Controllers;

use App\Models\Wishlist;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    public function index(Request $request)
    {
        $query = Wishlist::query();
        if ($request->user()) {
            $query->where('user_id', $request->user()->id);
        }
        return response()->json($query->with('product')->paginate($request->integer('per_page', 15)));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'product_id' => 'required|exists:products,id',
        ]);
        $wishlist = Wishlist::firstOrCreate([
            'user_id' => $request->user()?->id,
            'product_id' => $data['product_id'],
        ]);
        return response()->json($wishlist, 201);
    }

    public function destroy(Wishlist $wishlist)
    {
        $wishlist->delete();
        return response()->json(['message' => 'deleted']);
    }
}


