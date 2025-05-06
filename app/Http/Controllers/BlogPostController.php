<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use App\Services\ApiResponseService;
use App\Services\FilterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BlogPostController extends Controller
{
    protected $filterService;

    public function __construct(FilterService $filterService)
    {
        $this->filterService = $filterService;
    }

    /**
     * @operationId All blog posts
     */
    public function index(Request $request)
    {
        try {
            // Validate query parameters
            $request->validate([
                'status' => 'nullable|in:draft,published,archived',
                'author_id' => 'nullable|exists:users,id',
                'title' => 'nullable|string|max:255',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
                'date_field' => 'nullable|in:created_at,published_date', // Optional: specify date field
                'per_page' => 'nullable|integer|min:1|max:100',
            ]);

            // Initialize query with eager loading
            $query = BlogPost::with(['user', 'user.media']);

            // Apply filters using the service
            $query = $this->filterService->applyFilters($request, $query);

            // Apply default sorting (newest published_date first)
            $query->orderBy('published_date', 'desc');

            // Handle pagination
            $perPage = $request->get('per_page', 10);
            $perPage = is_numeric($perPage) && $perPage > 0 ? (int) $perPage : 10;
            $blogPosts = $query->paginate($perPage);

            // Check if there are no blog posts
            if ($blogPosts->isEmpty()) {
                return apiResponse(true, 'No blog posts found.', $blogPosts, 'blog_posts', 200);
            }

            return apiResponse(true, 'Blog posts retrieved successfully!', $blogPosts, 'blog_posts', 200);
        } catch (\Exception $e) {
            return apiResponse(false, 'An error occurred while retrieving blog posts.',
                $e->getMessage(), 'error');
        }
    }

    /**
     * @operationId Create blog posts
     */
    public function store(Request $request)
    {
        try {
            // Get the authenticated user
            $user = Auth::guard('sanctum')->user();

            if (!$user) {
                return apiResponse(false, 'Unauthorized: You must be logged in as an admin.', [], null);
            }

            if (!$user || !$user->hasRole('admin')) {
                return apiResponse(false, 'Unauthorized: You must have the admin role and blog_create permission.', [], null);
            }


            // Validate request data
            $validatedData = $request->validate([
                'title' => 'required|string|max:255',
                'content' => 'required|string',
                'published_date' => 'nullable|date',
                'status' => 'required|in:draft,published,archived',
            ]);

            // Create a new blog post with the authenticated user as the author
            $blogPost = $user->blogPosts()->create([
                'title' => $validatedData['title'],
                'content' => $validatedData['content'],
                'published_date' => $validatedData['published_date'] ?? now(),
                'status' => $validatedData['status'],
            ]);

            return apiResponse(true, 'Blog post created successfully!',
                $blogPost, 'blog_post');

        } catch (\Exception $e) {
            return ApiResponseService::handleException($e, $request->all());
        }
    }

    /**
     * @operationId Show blog posts
     */
    public function show($id)
    {
        try {
            // Find the blog post with author details and media
            $blogPost = BlogPost::with(['user', 'user.media'])->find($id);

            if (!$blogPost) {
                return apiResponse(false, 'Blog not found.', [], null);
            }

            return apiResponse(true, 'Blog post retrieved successfully!',
                $blogPost, 'blog_post');

        } catch (\Exception $e) {
            return apiResponse(false, 'An error occurred while retrieving the blog post.',
                $e->getMessage(), 'error');
        }
    }

    /**
     * @operationId Update blog posts
     */
    public function update(Request $request, $id)
    {
        try {
            // Get the authenticated user
            $user = Auth::guard('sanctum')->user();

            if (!$user) {
                return apiResponse(false, 'Unauthorized: You must be logged in as an admin.', [], null);
            }

            $blogPost = BlogPost::where('id', $id)->first();

            if (!$blogPost) {
                return apiResponse(false, 'Blog not found.', [], null);
            }

            // Check if the user is authorized to update the blog post
            if (!$user || !$user->hasRole('admin')) {
                return apiResponse(false, 'Unauthorized: You must have the admin role and blog_update permission.', [], null);
            }

            // Validate request data
            $validatedData = $request->validate([
                'title' => 'sometimes|required|string|max:255',
                'content' => 'sometimes|required|string',
                'published_date' => 'nullable|date',
                'status' => 'sometimes|required|in:draft,published,archived',
            ]);

            // Update the blog post
            $blogPost->update($validatedData);

            return apiResponse(true, 'Blog post updated successfully!',
                $blogPost, 'blog_post');

        } catch (\Exception $e) {
            return ApiResponseService::handleException($e, $request->all());
        }
    }

    /**
     * @operationId Delete blog posts
     */
    public function destroy(Request $request, $id)
    {
        try {
            // Get the authenticated user
            $user = Auth::guard('sanctum')->user();

            if (!$user) {
                return apiResponse(false, 'Unauthorized: You must be logged in as an admin.', [], null);
            }

            // Find the blog post
            $blogPost = BlogPost::find($id);

            if (!$blogPost) {
                return apiResponse(false, 'Blog not found.', [], null);
            }

            // Check if the user is authorized to delete the blog post
            if (!$user || !$user->hasRole('admin')) {
                return apiResponse(false, 'Unauthorized: You must have the admin role and blog_delete permission.', [], null);
            }

            // Delete the blog post
            $blogPost->delete();

            return apiResponse(true, 'Blog post deleted successfully!', [], null);

        } catch (\Exception $e) {
            return apiResponse(false, 'An error occurred while deleting the blog post.',
                $e->getMessage(), 'error');
        }
    }

}
