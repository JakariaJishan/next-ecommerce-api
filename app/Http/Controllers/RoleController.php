<?php

namespace App\Http\Controllers;

use App\Services\ApiResponseService;
use App\Services\FilterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    protected $filterService;

    public function __construct(FilterService $filterService)
    {
        $this->filterService = $filterService;
    }

    /**
     * @operationId All roles
     */
    public function index(Request $request)
    {
        try {
            // Authenticate the user via Sanctum
            $user = Auth::guard('sanctum')->user();

            if (!$user) {
                return apiResponse(false, 'Unauthorized: You must be logged in to view roles.', [], null);
            }

            // Check if the user has permission to view roles
            if (!$user->hasRole('admin')) {
                return apiResponse(false, 'Unauthorized: Only admins can view roles.', [], null);
            }

            // Validate query parameters
            $request->validate([
                'search' => 'nullable|string|max:255', // Specific filter for role name
                'per_page' => 'nullable|integer|min:1|max:100',
            ]);

            // Initialize query for roles with permissions
            $query = Role::with('permissions');

            // Apply specific filter by role name
            if ($name = $request->input('search')) {
                $query->where('name', 'like', "%$name%");
            }

            // Apply additional filters using the service (optional)
            $query = $this->filterService->applyFilters($request, $query);

            // Handle pagination
            $perPage = $request->get('per_page', 10);
            $perPage = is_numeric($perPage) && $perPage > 0 ? (int) $perPage : 10;
            $roles = $query->paginate($perPage)->through(function ($role) {
                $role->permissions->each(function ($permission) {
                    if (is_string($permission->action)) {
                        $permission->action = json_decode($permission->action, true);
                    }
                });
                return $role;
            });

            if ($roles->isEmpty()) {
                return apiResponse(true, 'No roles found.', $roles, 'roles', 200);
            }

            return apiResponse(true, 'Roles and their permissions fetched successfully!', $roles, 'roles', 200);

        } catch (\Exception $e) {
            return apiResponse(false, 'An error occurred while fetching roles.',
                $e->getMessage(), 'error');
        }
    }

    /**
     * @operationId Create role
     */
    public function store(Request $request)
    {
        try {
            // Authenticate the user via Sanctum
            $user = Auth::guard('sanctum')->user();
            if (!$user) {
                return apiResponse(false, 'Unauthorized: You must be logged in to create a role.', [], null);
            }

            // Check if the user is an admin and has permission to create roles
            if (!$user->hasRole('admin')) {
                return apiResponse(false, 'Unauthorized: Only admins can create roles.', [], null);
            }

            // Parse permission_ids if it comes as a JSON string
            $permissionIds = $request->input('permission_ids');
            if (is_string($permissionIds)) {
                $permissionIds = json_decode($permissionIds, true); // Convert JSON string to array
                if (json_last_error() !== JSON_ERROR_NONE) {
                    return apiResponse(false, 'Invalid permission_ids format. Must be a valid JSON array.', [], null);
                }
            }

            // Validate request data
            $validated = $request->validate([
                'name' => 'required|string|max:255|unique:roles,name',
                'description' => 'nullable|string|max:1000',
                // 'permission_ids' => 'nullable|array', // Optional array of permission IDs
                'permission_ids.*' => 'exists:permissions,id', // Each ID must exist in the permissions table
            ]);

            // If permission_ids was parsed, manually merge it into validated data
            if (isset($permissionIds)) {
                $validated['permission_ids'] = $permissionIds;
            }

            // Create the role with the default guard
            $role = Role::create([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'guard_name' => 'web', // Default guard, consistent with permissions
            ]);

            // Assign permissions to the role if permission_ids are provided
            if (!empty($validated['permission_ids'])) {
                $permissions = Permission::whereIn('id', $validated['permission_ids'])->get();
                $role->givePermissionTo($permissions);
            }

            // Load the permissions relationship after assigning
            $role->load('permissions');

            return apiResponse(true, 'Role created and permissions assigned successfully!', $role, 'role', 201);
        } catch (\Exception $e) {
            return ApiResponseService::handleException($e, $request->all());
        }
    }

    /**
     * @operationId Show role
     */
    public function show($id)
    {
        try {
            // Authenticate the user via Sanctum
            $user = Auth::guard('sanctum')->user();

            if (!$user) {
                return apiResponse(false, 'Unauthorized: You must be logged in to view this role.', [], null);
            }

            // Check if the user has permission to view roles
            if (!$user->hasRole('admin')) {
                return apiResponse(false, 'Unauthorized: Only admins can view roles.', [], null);
            }

            // Find the role by ID
            $role = Role::with('permissions')->find($id);

            if (!$role) {
                return apiResponse(false, 'Role not found.', [], 'role');
            }

            // Convert the action field from JSON string to array for each permission
            foreach ($role->permissions as $permission) {
                $permission->action = json_decode($permission->action); // Decode the JSON string to an array
            }

            return apiResponse(true, 'Role retrieved successfully!', $role, 'role');

        } catch (\Exception $e) {
            return ApiResponseService::handleException($e);
        }
    }

    /**
     * @operationId Update role
     */
    public function update(Request $request, $id)
    {
        try {
            $user = Auth::guard('sanctum')->user();
            if (!$user) {
                return apiResponse(false, 'Unauthorized: You must be logged in to update a role.', [], null);
            }

            if (!$user->hasRole('admin')) {
                return apiResponse(false, 'Unauthorized: Only admins can update roles.', [], null);
            }

            $role = Role::find($id);
            if (!$role) {
                return apiResponse(false, 'Role not found.', [], null);
            }

            // Parse permission_ids if it comes as a JSON string
            $permissionIds = $request->input('permission_ids');
            if (is_string($permissionIds)) {
                $permissionIds = json_decode($permissionIds, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    return apiResponse(false, 'Invalid permission_ids format. Must be a valid JSON array.', [], null);
                }
            }

            // Validate request data
            $validated = $request->validate([
                'name' => 'required|string|max:255|unique:roles,name,' . $role->id,
                'description' => 'nullable|string|max:1000',
                // 'permission_ids' => 'nullable|array', // Still commented out
                'permission_ids.*' => 'exists:permissions,id',
            ]);

            // If permission_ids was parsed, manually merge it into validated data
            if (isset($permissionIds)) {
                $validated['permission_ids'] = $permissionIds;
            }

            // Update the role details
            $role->update([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? $role->description,
            ]);

            // Sync permissions if permission_ids are provided
            if (isset($validated['permission_ids'])) {
                $permissions = Permission::whereIn('id', $validated['permission_ids'])->pluck('id')->toArray();
                $role->syncPermissions($permissions);
            }

            // Load the permissions relationship after syncing
            $role->load('permissions');

            return apiResponse(true, 'Role updated and permissions assigned successfully!', $role, 'role', 200);
        } catch (\Exception $e) {
            return ApiResponseService::handleException($e, $request->all());
        }
    }

    /**
     * @operationId Delete role
     */
    public function destroy($id)
    {
        try {
            // Authenticate the user via Sanctum
            $user = Auth::guard('sanctum')->user();

            if (!$user) {
                return apiResponse(false, 'Unauthorized: You must be logged in to delete a role.', [], null);
            }

            // Check if the user has permission to delete roles
            if (!$user->hasRole('admin')) {
                return apiResponse(false, 'Unauthorized: Only admins can delete roles.', [], null);
            }

            // Find the role by ID
            $role = Role::find($id);

            if (!$role) {
                return apiResponse(false, 'Role not found.', [], null);
            }

            // Prevent deleting essential roles (e.g., super admin)
//            if (in_array($role->name, ['super-admin', 'admin'])) {
//                return apiResponse(false, 'This role cannot be deleted.', [], 403);
//            }

            // Delete the role
            $role->delete();

            return apiResponse(true, 'Role deleted successfully!', [], null);

        } catch (\Exception $e) {
            return apiResponse(false, 'An error occurred while deleting the role.',
                $e->getMessage(), 'error');
        }
    }

}
