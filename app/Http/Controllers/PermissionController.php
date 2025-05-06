<?php

namespace App\Http\Controllers;

use App\Services\ApiResponseService;
use App\Services\FilterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionController extends Controller
{

    protected $filterService;

    public function __construct(FilterService $filterService)
    {
        $this->filterService = $filterService;
    }

    /**
     * @operationId All permissions
     */
    public function index(Request $request)
    {
        try {
            $user = Auth::guard('sanctum')->user();

            if (!$user) {
                return apiResponse(false, 'Unauthorized: You must be logged in to view permissions.', [], null);
            }

            if (!$user->hasRole('admin')) {
                return apiResponse(false, 'Unauthorized: Only admins can view permissions.', [], null);
            }

            $request->validate([
                'resource' => 'nullable|string|max:255',
                'per_page' => 'nullable|integer|min:1|max:100',
            ]);

            $query = Permission::with('roles');

            if ($resource = $request->input('resource')) {
                $query->where('resource', 'like', "%$resource%");
            }

            $query = $this->filterService->applyFilters($request, $query);

            $perPage = $request->get('per_page', 10);
            $perPage = is_numeric($perPage) && $perPage > 0 ? (int) $perPage : 10;
            $permissions = $query->paginate($perPage);

            $permissions->getCollection()->transform(function ($permission) {
                if (is_string($permission->action)) {
                    $permission->action = json_decode($permission->action, true);
                }
                return $permission;
            });

            if ($permissions->isEmpty()) {
                return apiResponse(true, 'No permissions found.', $permissions, 'permissions', 200);
            }

            return apiResponse(true, 'Permissions fetched successfully!', $permissions, 'permissions', 200);

        } catch (\Exception $e) {
            return apiResponse(false, 'An error occurred while fetching permissions.',
                $e->getMessage(), 'error');
        }
    }

    /**
     * @operationId Create permission
     */
    public function store(Request $request)
    {
        try {
            // Authenticate the user via Sanctum
            $user = Auth::guard('sanctum')->user();
            if (!$user) {
                return apiResponse(false, 'Unauthorized: You must be logged in to create a permission.', [], null);
            }

            // Check if the user has permission to create permissions
            if (!$user->hasRole('admin')) {
                return apiResponse(false, 'Unauthorized: Only admins can create permissions.', [], null);
            }

            // Validate request data
            $validated = $request->validate([
                'resource'    => 'required|string|max:255|unique:permissions,resource',
                // 'action'      => 'required|array|min:1', // Still commented out for now
                'description' => 'nullable|string|max:1000',
            ]);

            // Get and parse the action input
            $rawAction = $request->input('action');
            $parsedAction = is_string($rawAction) ? json_decode($rawAction, true) : $rawAction;

            // Ensure parsedAction is an array (default to empty array if invalid)
            if (!is_array($parsedAction)) {
                $parsedAction = [];
            }

            // Prepare data for Spatie's Permission::create()
            $permissionData = [
                'name'        => $validated['resource'],
                'resource'    => $validated['resource'],
                'action'      => json_encode($parsedAction), // Single-encoded JSON string
                'description' => $validated['description'] ?? null,
                'guard_name'  => 'web',
            ];

            // Create the permission
            $permission = Permission::create($permissionData);

            // Decode action for the response
            $permission->action = json_decode($permission->action, true);

            return apiResponse(true, 'Permission created successfully!', $permission, 'permission', 201);
        } catch (\Exception $e) {
            return ApiResponseService::handleException($e, $request->all());
        }
    }

    /**
     * @operationId Show permission
     */
    public function show($id)
    {
        try {
            // Authenticate the user via Sanctum
            $user = Auth::guard('sanctum')->user();

            if (!$user) {
                return apiResponse(false, 'Unauthorized: You must be logged in to view this permission.', [], null);
            }

            // Check if the user has permission to view permissions
            if (!$user->hasRole('admin')) {
                return apiResponse(false, 'Unauthorized: Only admins can view permissions.', [], null);
            }

            // Find the permission by ID and load its associated roles
            $permission = Permission::with('roles')->find($id);

            if (!$permission) {
                return apiResponse(false, 'Permission not found.', [], null);
            }

            // Decode action field before returning response
            $permission->action = json_decode($permission->action, true);

            return apiResponse(true, 'Permission retrieved successfully!', $permission, 'permissions', 200);

        } catch (\Exception $e) {
            return apiResponse(false, 'An error occurred while retrieving the permission.',
                $e->getMessage(), 'error');
        }
    }


    /**
     * @operationId Update permission
     */
    public function update(Request $request, $id)
    {
        try {
            // Authenticate the user via Sanctum
            $user = Auth::guard('sanctum')->user();
            if (!$user) {
                return apiResponse(false, 'Unauthorized: You must be logged in to update a permission.', [], null);
            }

            // Check if the user has permission to update permissions
            if (!$user->hasRole('admin')) {
                return apiResponse(false, 'Unauthorized: Only admins can update permissions.', [], null);
            }

            // Find the permission by ID
            $permission = Permission::find($id);
            if (!$permission) {
                return apiResponse(false, 'Permission not found.', [], null);
            }

            // Validate request data
            $validated = $request->validate([
                'resource' => 'required|string|max:255|unique:permissions,resource,' . $permission->id,
                // 'action' => 'required|array|min:1', // Commented out to match store
                'description' => 'nullable|string|max:1000',
            ]);

            // Get and parse the action input
            $rawAction = $request->input('action');
            $parsedAction = is_string($rawAction) ? json_decode($rawAction, true) : $rawAction;

            // Ensure parsedAction is an array (default to empty array if invalid)
            if (!is_array($parsedAction)) {
                $parsedAction = [];
            }

            // Prepare data for update, including name for Spatie
            $permissionData = [
                'name' => $validated['resource'], // Spatie uses 'name' for permission identifier
                'resource' => $validated['resource'],
                'action' => json_encode($parsedAction), // Single-encoded JSON string
                'description' => $validated['description'] ?? $permission->description,
                'guard_name' => 'web',
            ];

            // Update the permission
            $permission->update($permissionData);

            // Decode action for the response
            $permission->action = json_decode($permission->action, true);

            return apiResponse(true, 'Permission updated successfully!', $permission, 'permission', 200);
        } catch (\Exception $e) {
            return ApiResponseService::handleException($e, $request->all());
        }
    }

    /**
     * @operationId Delete permission
     */
    public function destroy($id)
    {
        try {
            // Authenticate the user via Sanctum
            $user = Auth::guard('sanctum')->user();

            if (!$user) {
                return apiResponse(false, 'Unauthorized: You must be logged in to delete a permission.', [], null);
            }

            // Check if the user has permission to delete permissions
            if (!$user->hasRole('admin')) {
                return apiResponse(false, 'Unauthorized: Only admins can delete permissions.', [], null);
            }

            // Find the permission by ID
            $permission = Permission::find($id);

            if (!$permission) {
                return apiResponse(false, 'Permission not found.', [], null);
            }

            // Detach the permission from all roles before deletion
            $permission->roles()->detach();

            // Delete the permission
            $permission->delete();

            return apiResponse(true, 'Permission deleted successfully!', [], null);

        } catch (\Exception $e) {
            return apiResponse(false, 'An error occurred while deleting the permission.',
                $e->getMessage(), 'error');
        }
    }

}
