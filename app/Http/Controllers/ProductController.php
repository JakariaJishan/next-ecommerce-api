<?php

namespace App\Http\Controllers;

use App\Models\Inventory;
use App\Models\Tag;
use App\Models\TagMapping;
use App\Models\Product;
use App\Notifications\LowStockNotification;
use App\Services\ApiResponseService;
use App\Services\FilterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

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
            $perPage = is_numeric($perPage) && $perPage > 0 ? (int)$perPage : 10;
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
                return apiResponse(false, 'Unauthorized: You must be logged in to create products.',
                    [], null, 401);
            }

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
                'stock_quantity' => 'nullable|integer|min:0',
                'low_stock_threshold' => 'nullable|integer|min:0',
            ]);

            // Wrap all database operations in a transaction
            $product = DB::transaction(function () use ($request, $validated, $user) {
                // Handle tags input
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
                ]);

                // Create inventory record
                $inventory = Inventory::create([
                    'product_id' => $product->id,
                    'stock_quantity' => $validated['stock_quantity'] ?? 0,
                    'low_stock_threshold' => $validated['low_stock_threshold'] ?? 10,
                ]);


                // Handle media uploads
                $mediaPaths = [];
                if ($request->hasFile('media')) {
                    foreach ($request->file('media') as $file) {
                        $media = $product->addMedia($file)->toMediaCollection('product');
                        $mediaPaths[] = $media->getPath();
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

                return [$product, $mediaPaths];
            });

            // Extract product and media paths from transaction result
            [$product, $mediaPaths] = $product;

            // Return success response with product, inventory, media, and tags
            return apiResponse(true, 'Product created successfully!',
                $product->load('user.media', 'media', 'tags', 'inventory'), 'products', 201);
        } catch (\Exception $e) {
            // Clean up any uploaded media files if transaction fails
            if (isset($mediaPaths)) {
                foreach ($mediaPaths as $path) {
                    Storage::delete($path);
                }
            }

            return ApiResponseService::handleException($e, $request->all());
        }
    }

    /**
     * @operationId Show Product
     */

    public function show($id)
    {
        try {
            // Fetch the product with user, media, and inventory
            $product = Product::with(['user.media', 'media', 'inventory'])->where('id', $id)->first();

            if (!$product) {
                return apiResponse(false, 'Product not found.', [], 'product', 404);
            }

            return apiResponse(true, 'Product retrieved successfully.', $product, 'product', 200);
        } catch (\Exception $e) {
            return apiResponse(false, 'An error occurred while retrieving the product.',
                $e->getMessage(), 'error', 500);
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
                return apiResponse(false, 'Unauthorized: You must be logged in to update products.', [], null, 401);
            }

            // Fetch the product
            $product = Product::where('id', $id)->first();

            if (!$product) {
                return apiResponse(false, 'Product not found.', [], null, 404);
            }

            // Check if the user owns the product
            if ($user->id !== $product->user_id) {
                return apiResponse(false, 'You are not the owner of this product.', [], null, 403);
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
                'stock_quantity' => 'sometimes|required|integer|min:0',
                'low_stock_threshold' => 'sometimes|required|integer|min:0',
            ]);

            // Wrap all database operations in a transaction
            $product = DB::transaction(function () use ($request, $validatedData, $product, $user) {
                // Update product fields
                $product->update($validatedData);

                // Handle inventory update
                $inventory = $product->inventory;
                if ($request->hasAny(['stock_quantity', 'low_stock_threshold'])) {
                    if (!$inventory) {
                        // Create inventory if it doesn't exist (unlikely due to store method)
                        $inventory = Inventory::create([
                            'product_id' => $product->id,
                            'stock_quantity' => $validatedData['stock_quantity'] ?? 0,
                            'low_stock_threshold' => $validatedData['low_stock_threshold'] ?? 10,
                        ]);
                    } else {
                        // Check for low stock before updating
                        $wasLowStock = $inventory->stock_quantity <= $inventory->low_stock_threshold;
                        $newStock = $request->input('stock_quantity', $inventory->stock_quantity);
                        $newThreshold = $request->input('low_stock_threshold', $inventory->low_stock_threshold);

                        // Update inventory
                        $inventory->update([
                            'stock_quantity' => $newStock,
                            'low_stock_threshold' => $newThreshold,
                        ]);

                        // Trigger low-stock alert if necessary
                        if (!$wasLowStock && $newStock <= $newThreshold) {
                            $product->user->notify(new LowStockNotification($inventory));
                        }
                    }
                }

                // Handle deleting selected media files
                if ($request->has('delete_media')) {
                    foreach ($request->delete_media as $mediaId) {
                        $media = $product->media()->where('id', $mediaId)->first();
                        if ($media) {
                            $media->delete();
                        }
                    }
                }

                // Handle new media uploads
                $mediaPaths = [];
                if ($request->hasFile('media')) {
                    foreach ($request->file('media') as $file) {
                        $media = $product->addMedia($file)->toMediaCollection('product');
                        $mediaPaths[] = $media->getPath();
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

                return [$product, $mediaPaths];
            });

            // Extract product and media paths from transaction result
            [$product, $mediaPaths] = $product;

            // Return success response with product, inventory, media, and tags
            return apiResponse(true, 'Product updated successfully.',
                $product->load('user.media', 'media', 'tags', 'inventory'), 'products', 200);
        } catch (\Exception $e) {
            // Clean up any uploaded media files if transaction fails
            if (isset($mediaPaths)) {
                foreach ($mediaPaths as $path) {
                    Storage::delete($path);
                }
            }
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
