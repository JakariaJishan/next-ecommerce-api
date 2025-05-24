<?php

namespace App\Http\Controllers;

use App\Models\Tag;
use App\Models\TagMapping;
use App\Models\Product;
use App\Services\ApiResponseService;
use App\Services\FilterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;

class ProductController extends Controller
{
    protected $filterService;

    public function __construct(FilterService $filterService)
    {
        $this->filterService = $filterService;
    }

    /**
     * @operationId List Products
     */
    public function index(Request $request)
    {
        try {
            // Validate query parameters
            $request->validate([
                'category_id' => 'nullable|exists:categories,id',
                'status' => 'nullable|in:active,inactive,draft', // Adjust statuses based on your needs
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
                'per_page' => 'nullable|integer|min:1|max:100',
            ]);

            // Initialize query with eager loading
            $query = Product::with(['user.media', 'media', 'tags']);

            // Apply filters using the service
            $query = $this->filterService->applyFilters($request, $query);

            // Handle pagination
            $perPage = $request->get('per_page', 10);
            $perPage = is_numeric($perPage) && $perPage > 0 ? (int) $perPage : 10;
            $products = $query->paginate($perPage);

            // Check if there are no products
            if ($products->isEmpty()) {
                return apiResponse(false, 'No products found.', ['products' => []], null);
            }

            return apiResponse(true, 'Products retrieved successfully!', $products, 'products', 200);
        } catch (\Exception $e) {
            return apiResponse(false, 'An error occurred while retrieving products.',
                $e->getMessage(), 'error');
        }
    }

    /**
     * @operationId Store/Create Products
     */

    public function store(Request $request): JsonResponse
    {
        try {
            // Check if the user is authenticated
            $user = Auth::guard('sanctum')->user();

            if (!$user) {
                return apiResponse(false, 'Unauthorized: You must be logged in to create products.', [], null);
            }
//            if (!$user->hasRole('user')) {
//                return apiResponse(false, 'Unauthorized: You must have the user role and ads_create permission.', [], null);
//            }

            // Validate the incoming data
            $validated = $request->validate([
                'category_id' => 'required|exists:categories,id',
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'sku' => 'nullable|string|max:50|unique:products,sku',
                'price' => 'required|numeric|min:0',
                'weight' => 'nullable|numeric|min:0',
                'length' => 'nullable|numeric|min:0',
                'width' => 'nullable|numeric|min:0',
                'height' => 'nullable|numeric|min:0',
                'custom_attributes' => 'nullable|array',
                'media' => 'required|array',
                'media.*' => 'file|mimes:jpg,jpeg,png,mp4,mov,avi|max:5120',
                'tags' => 'required|array',
                'tags.*' => 'required|string|max:255',
            ]);

            // Handle tags input (already validated as an array)
            $tags = Arr::wrap($request->input('tags'));

            // Create the product
            $product = Product::create([
                'user_id' => $user->id,
                'category_id' => $validated['category_id'],
                'name' => $validated['name'],
                'description' => $validated['description'],
                'sku' => $validated['sku'],
                'price' => $validated['price'],
                'weight' => $validated['weight'],
                'length' => $validated['length'],
                'width' => $validated['width'],
                'height' => $validated['height'],
//                'custom_attributes' => $validated['custom_attributes'],
            ]);

            // Handle media uploads
            if ($request->hasFile('media')) {
                foreach ($request->file('media') as $file) {
                    $product->addMedia($file)->toMediaCollection('product');
                }
            }

            // Handle tags: Create or find tags and attach them to the product
            if (!empty($tags)) {
                $tagIds = [];
                foreach ($tags as $tagName) {
                    $tag = Tag::firstOrCreate(
                        ['tag_name' => $tagName],
                        ['tag_description' => 'Tag description']
                    );
                    $tagIds[$tag->id] = [
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
                $product->tags()->sync($tagIds);
            }

            // Return success response with product, media, and tags
            return apiResponse(true, 'Product created successfully!',
                $product->load('user.media', 'media', 'tags'), 'products');

        } catch (\Exception $e) {
            return ApiResponseService::handleException($e, $request->all());
        }
    }

    /**
     * @operationId Show Product
     */

    public function show($id)
    {
        try {
            // Fetch the ad with user and media
            $ad = Product::with(['user.media', 'media'])->where('id', $id)->first();

            if (!$ad) {
                return apiResponse(false, 'Product not found.', [], 'product');
            }

            return apiResponse(true, 'Product retrieved successfully.', $ad, 'product');
        } catch (\Exception $e) {
            return apiResponse(false, 'An error occurred while retrieving the product.',
                $e->getMessage(), 'error');
        }
    }



    /**
     * @operationId Update Product
     */
    public function update(Request $request, $id)
    {
        try {
            // Check if the user is authenticated
            $user = Auth::guard('sanctum')->user();

            if (!$user) {
                return apiResponse(false, 'Unauthorized: You must be logged in to update products.', [], null);
            }

            $product = Product::where('id', $id)->first();

            if (!$product) {
                return apiResponse(false, 'Product not found.', [], null);
            }

            // Check if the user owns the product
            if ($user->id !== $product->user_id) {
                return apiResponse(false, 'You are not the owner of this product.', [], null);
            }

            // Validate the incoming data
            $validatedData = $request->validate([
                'category_id' => 'sometimes|required|exists:categories,id',
                'name' => 'sometimes|required|string|max:255',
                'description' => 'nullable|string',
                'sku' => [
                    'sometimes',
                    'required',
                    'string',
                    'max:50',
                    // Custom rule to check uniqueness only if SKU is different from current
                    function ($attribute, $value, $fail) use ($product) {
                        if ($value !== $product->sku && Product::where('sku', $value)->where('id', '!=', $product->id)->exists()) {
                            $fail('The SKU has already been taken.');
                        }
                    },
                ],
                'price' => 'sometimes|required|numeric|min:0',
                'weight' => 'nullable|numeric|min:0',
                'length' => 'nullable|numeric|min:0',
                'width' => 'nullable|numeric|min:0',
                'height' => 'nullable|numeric|min:0',
                'custom_attributes' => 'nullable|array',
                'media' => 'nullable|array',
                'media.*' => 'file|mimes:jpg,jpeg,png,mp4,mov,avi|max:5120',
                'delete_media' => 'nullable|array',
                'tags' => 'nullable|array',
                'tags.*' => 'string|max:255',
            ]);

            // Update product fields
            $product->update($validatedData);

            // Handle deleting selected media files
            if ($request->has('delete_media')) {
                foreach ($request->delete_media as $mediaId) {
                    $media = $product->media()->where('id', $mediaId)->first();
                    if ($media) {
                        $media->delete(); // Delete media from storage and database
                    }
                }
            }

            // Handle new media uploads
            if ($request->hasFile('media')) {
                foreach ($request->file('media') as $file) {
                    $product->addMedia($file)->toMediaCollection('product');
                }
            }

            // Handle tags: Sync new tags if provided
            if ($request->has('tags')) {
                $tags = Arr::wrap($request->input('tags'));
                $tagIds = [];
                foreach ($tags as $tagName) {
                    $tag = Tag::firstOrCreate(
                        ['tag_name' => $tagName],
                        ['tag_description' => 'Tag description']
                    );
                    $tagIds[$tag->id] = [
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
                $product->tags()->sync($tagIds);
            }

            return apiResponse(true, 'Product updated successfully.',
                $product->load('user.media', 'media', 'tags'), 'products');

        } catch (\Exception $e) {
            return ApiResponseService::handleException($e, $request->all());
        }
    }

    /**
     * @operationId Delete Product
     */
    public function destroy(Request $request, $id)
    {
        try {
            // Check if the user is authenticated
            $user = Auth::guard('sanctum')->user();

            if (!$user) {
                return apiResponse(false, 'Unauthorized: You must be logged in to delete products.', [], null, 401);
            }

            $product = Product::where('id', $id)->first();
            if (!$product) {
                return apiResponse(false, 'Product not found.', [], null);
            }

            // Check if the user owns the product
            if ($user->id !== $product->user_id) {
                return apiResponse(false, 'Unauthorized: You do not have permission to delete this product.', [], null, 403);
            }

            // Delete associated media
            $product->media()->delete();

            // Detach associated tags
            $product->tags()->detach();

            // Delete the product
            $product->delete();

            return apiResponse(true, 'Product deleted successfully!', [], null, 200);
        } catch (\Exception $e) {
            return apiResponse(false, 'An error occurred while deleting the product.',
                $e->getMessage(), 'error', 500);
        }
    }
}
