<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::query();

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->integer('category_id'));
        }

        if ($request->filled('q')) {
            $q = $request->string('q');
            $query->where(function ($w) use ($q) {
                $w->where('name', 'like', "%{$q}%")
                  ->orWhere('description', 'like', "%{$q}%");
            });
        }

        return response()->json($query->paginate($request->integer('per_page', 15)));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:products,slug',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'sale_price' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'stock' => 'nullable|integer|min:0',
            'status' => 'nullable|in:draft,active,inactive',
            'images' => 'nullable|array',
            'attributes' => 'nullable|array',
            'category_id' => 'nullable|exists:categories,id',
        ]);

        $data['user_id'] = $request->user()?->id ?? null;
        $data['slug'] = $data['slug'] ?? Str::slug($data['name']) . '-' . Str::random(6);

        $product = Product::create($data);

        return response()->json($product, 201);
    }

    public function show(Product $product)
    {
        return response()->json($product);
    }

    public function update(Request $request, Product $product)
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'slug' => 'sometimes|string|max:255|unique:products,slug,' . $product->id,
            'description' => 'sometimes|nullable|string',
            'price' => 'sometimes|numeric|min:0',
            'sale_price' => 'sometimes|nullable|numeric|min:0',
            'currency' => 'sometimes|string|size:3',
            'stock' => 'sometimes|integer|min:0',
            'status' => 'sometimes|in:draft,active,inactive',
            'images' => 'sometimes|array',
            'attributes' => 'sometimes|array',
            'category_id' => 'sometimes|nullable|exists:categories,id',
        ]);

        $product->update($data);

        return response()->json($product);
    }

    public function destroy(Product $product)
    {
        $product->delete();
        return response()->json(['message' => 'deleted']);
    }
}


