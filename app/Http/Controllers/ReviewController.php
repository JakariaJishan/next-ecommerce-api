<?php

namespace App\Http\Controllers;

use App\Models\Review;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function index(Request $request)
    {
        $query = Review::query();
        if ($request->filled('product_id')) {
            $query->where('product_id', $request->integer('product_id'));
        }
        return response()->json($query->latest()->paginate($request->integer('per_page', 15)));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'product_id' => 'required|exists:products,id',
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string',
        ]);
        $data['user_id'] = $request->user()?->id;
        $review = Review::create($data);
        return response()->json($review, 201);
    }

    public function show(Review $review)
    {
        return response()->json($review);
    }

    public function update(Request $request, Review $review)
    {
        $data = $request->validate([
            'rating' => 'sometimes|integer|min:1|max:5',
            'comment' => 'sometimes|nullable|string',
            'approved' => 'sometimes|boolean',
        ]);
        $review->update($data);
        return response()->json($review);
    }

    public function destroy(Review $review)
    {
        $review->delete();
        return response()->json(['message' => 'deleted']);
    }
}


