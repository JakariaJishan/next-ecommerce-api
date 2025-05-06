<?php

namespace App\Http\Controllers;

use App\Helpers\TranslationHelper;
use App\Models\Category;
use App\Services\ApiResponseService;
use App\Services\FilterService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CategoryController extends Controller
{

    protected $filterService;

    public function __construct(FilterService $filterService)
    {
        $this->filterService = $filterService;
    }

    /**
     * @operationId All categories
     */
    public function index(Request $request)
    {
        try {
            // Validate query parameters
            $request->validate([
                'name' => 'nullable|string|max:255',
                'parent_category_id' => 'nullable|exists:categories,id',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
                'per_page' => 'nullable|integer|min:1|max:100',
            ]);

            // Initialize query
            $query = Category::query();

            // Apply filters using the service
            $query = $this->filterService->applyFilters($request, $query);

            // Handle pagination
            $perPage = $request->get('per_page', 10);
            $perPage = is_numeric($perPage) && $perPage > 0 ? (int) $perPage : 10;
            $categories = $query->paginate($perPage);

            // Check if there are no categories
            if ($categories->isEmpty()) {
                return apiResponse(true, 'No categories found.', $categories, 'categories', 200);
            }

            return apiResponse(true, 'Categories retrieved successfully!', $categories, 'categories', 200);
        } catch (\Exception $e) {
            return apiResponse(false, 'An error occurred while retrieving categories.',
                $e->getMessage(), 'error');
        }
    }

    /**
     * @operationId Create category
     */
    public function store(Request $request)
    {
        try {
            // Check if the user is authenticated
            $user = Auth::guard('sanctum')->user();

            if (!$user) {
                return apiResponse(false, 'Unauthorized: You must be logged in as an admin.', [], null);
            }

            // Check if the user has the 'admin' role and the 'category_create' permission
            if (!$user || !$user->hasRole('admin')) {
                // Return an unauthorized response if the user doesn't have the required permissions
                return apiResponse(false, 'Unauthorized: You must have the admin role and category_create permission.', [], null);
            }

            // Validate the incoming data
            $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
            ]);

            // Create a new category and save it to the database
            $category = Category::create([
                'name' => $request->input('name'),
                'description' => $request->input('description'),
            ]);

            // Return a success response
            return apiResponse(true, 'Category created successfully!',
                $category, 'category');

        } catch (\Exception $e) {
            return ApiResponseService::handleException($e, $request->all());
        }
    }

    /**
     * @operationId Show category
     */
    public function show($id)
    {
        try {
            // Attempt to find the category
            $category = Category::findOrFail($id);

            // Return the category details using the apiResponse helper
            return apiResponse(true, 'Category fetched successfully', $category, 'category');
        } catch (ModelNotFoundException $e) {
            // Handle the case where the category is not found
            return apiResponse(false, 'Category not found', [], null);
        }
    }


    /**
     * @operationId Update category
     */
    public function update(Request $request, $id)
    {
        $user = Auth::guard('sanctum')->user();

        if (!$user) {
            return apiResponse(false, 'Unauthorized: You must be logged in as an admin.', [], null);
        }

        try {
            // Attempt to find the category
            $category = Category::findOrFail($id);

            // Validate the request data
            $validatedData = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
            ]);

            // Check if the user has the 'admin' role and the 'category_update' permission
            if (!$user || !$user->hasRole('admin')) {
                // Return an unauthorized response if the user doesn't have the required permissions
                return apiResponse(false, 'Unauthorized: You must have the admin role and category_update permission.', [], null);
            }

            // Update the category with the validated data
            $category->update($validatedData);

            // Return a success response with the updated category
            return apiResponse(true, 'Category updated successfully',  $category, 'category');
        } catch (\Exception $e) {
            return ApiResponseService::handleException($e, $request->all());
        }
    }

    /**
     * @operationId Delete category
     */
    public function destroy(Request $request, $id)
    {
        $user = Auth::guard('sanctum')->user();

        if (!$user) {
            return apiResponse(false, 'Unauthorized: You must be logged in as an admin.', [], null);
        }

        try {
            // Attempt to find the category
            $category = Category::findOrFail($id);

            // Check if the user has the 'admin' role and the 'category_update' permission
            if (!$user || !$user->hasRole('admin')) {
                // Return an unauthorized response if the user doesn't have the required permissions
                return apiResponse(false, 'Unauthorized: You must have the admin role and category_destroy permission.', [], null);
            }

            $category->delete();

            // Return a success response with the updated category
            return apiResponse(true, 'Category deleted successfully', [], null);
        } catch (\Exception $e) {
            return ApiResponseService::handleException($e, $request->all());
        }
    }
}
