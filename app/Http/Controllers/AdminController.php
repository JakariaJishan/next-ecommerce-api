<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\FilterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class AdminController extends Controller
{

    protected $filterService;

    public function __construct(FilterService $filterService)
    {
        $this->filterService = $filterService;
    }

    /**
     * @operationId All Admins
     */
    public function index(Request $request)
    {
        try {
            // Authenticate the requesting admin
            $admin = Auth::guard('sanctum')->user();

            if (!$admin) {
                return apiResponse(false, 'Unauthorized: You must be logged in as an admin.', [], null);
            }

            // Check if the user has permission to view admins
            if (!$admin->hasRole('admin')) {
                return apiResponse(false, 'Unauthorized: Only admins can view this list.', [], null);
            }

            // Validate query parameters
            $request->validate([
                'name' => 'nullable|string|max:255',
                'email' => 'nullable|string|email|max:255',
                'role' => 'nullable|string|max:255',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
                'per_page' => 'nullable|integer|min:1|max:100',
                'search' => 'nullable|string|max:255', // Added for specific username/email search
            ]);

            // Initialize query for users who are not regular 'user' role
            $query = User::whereDoesntHave('roles', function ($query) {
                $query->where('name', 'user');
            })->with(['roles.permissions', 'media']);

            // Apply specific search by username or email
            if ($search = $request->input('search')) {
                $query->where(function ($q) use ($search) {
                    $q->where('username', 'like', "%$search%") // Assuming 'username' column exists
                    ->orWhere('email', 'like', "%$search%");
                });
            }

            // Apply additional filters using the service
            $query = $this->filterService->applyFilters($request, $query);

            // Handle pagination
            $perPage = $request->get('per_page', 10);
            $perPage = is_numeric($perPage) && $perPage > 0 ? (int) $perPage : 10;
            $users = $query->paginate($perPage);

            if ($users->isEmpty()) {
                return apiResponse(true, 'No admins found.', $users, 'users', 200);
            }

            return apiResponse(true, 'Users retrieved successfully!', $users, 'users', 200);

        } catch (\Exception $e) {
            return apiResponse(false, 'An error occurred while retrieving users.',
                $e->getMessage(), 'error');
        }
    }


    /**
     * @operationId Create Admin
     */
    public function store(Request $request)
    {
        try {
            // Authenticate the user
            $admin = Auth::guard('sanctum')->user();

            if (!$admin) {
                return apiResponse(false, 'Unauthorized: You must be logged in as an admin.', [], null);
            }

            // Check if the user has permission to create admins
            if (!$admin->hasRole('admin')) {
                return apiResponse(false, 'Unauthorized: Only admins can create new admins.', [], null);
            }

            // ✅ Validate the request data
            $request->validate([
                'username' => 'required|string|max:255|unique:users,username',
                'email' => 'required|string|email|max:255|unique:users,email',
                'role_id' => 'required|exists:roles,id', // Ensure role ID exists
                'password' => 'required|string|min:6|confirmed', // 'confirmed' checks password_confirmation
            ]);

            // ✅ Create the new admin user
            $user = User::create([
                'username' => $request->input('username'),
                'email' => $request->input('email'),
                'password' => Hash::make($request->input('password')), // Hash the password
            ]);

            // ✅ Assign the role using role ID
            $role = Role::find($request->input('role_id'));
            $user->assignRole($role->name); // Assign role by name

            return apiResponse(true, 'Admin created successfully!', $user->load('roles', 'media'), 'admin', 201);

        } catch (\Exception $e) {
            return apiResponse(false, 'An error occurred while creating the admin.',
                $e->getMessage(), 'error');
        }
    }


    /**
     * @operationId Show Admin
     */
    public function show($id)
    {
        try {
            // Authenticate the requesting admin
            $admin = Auth::guard('sanctum')->user();

            if (!$admin) {
                return apiResponse(false, 'Unauthorized: You must be logged in as an admin.', [], null);
            }

            // Check if the user has permission to view admins
            if (!$admin->hasRole('admin')) {
                return apiResponse(false, 'Unauthorized: Only admins can view admin details.', [], null);
            }

            // Find the user by ID and load related roles, permissions, and media
            $user = User::with(['roles.permissions', 'media'])->find($id);

            if (!$user) {
                return apiResponse(false, 'User not found.', [], null);
            }

            return apiResponse(true, 'Admin details retrieved successfully!', $user, 'user', 200);

        } catch (\Exception $e) {
            return apiResponse(false, 'An error occurred while retrieving admin details.',
                $e->getMessage(), 'error');
        }
    }

    /**
     * @operationId Update Admin
     */
    public function update(Request $request, $id)
    {
        try {
            // Authenticate the requesting admin
            $admin = Auth::guard('sanctum')->user();

            if (!$admin) {
                return apiResponse(false, 'Unauthorized: You must be logged in as an admin.', [], null);
            }

            // Check if the user has permission to update admins
            if (!$admin->hasRole('admin')) {
                return apiResponse(false, 'Unauthorized: Only admins can update admin roles.', [], null);
            }

            // Find the user by ID
            $user = User::find($id);

            if (!$user) {
                return apiResponse(false, 'User not found.', [], null);
            }

            // ✅ Validate the role_id
            $request->validate([
                'role_id' => 'required|exists:roles,id', // Ensure role ID exists
            ]);

            // ✅ Update role
            $role = Role::find($request->input('role_id'));
            $user->syncRoles([$role->name]); // Sync new role

            return apiResponse(true, 'User role updated successfully!', $user->load('roles', 'media'), 'user', 200);

        } catch (\Exception $e) {
            return apiResponse(false, 'An error occurred while updating the user role.',
                $e->getMessage(), 'error');
        }
    }


    /**
     * @operationId Delete Admin
     */
    public function destroy($id)
    {
        try {
            // Authenticate the requesting admin
            $admin = Auth::guard('sanctum')->user();

            if (!$admin) {
                return apiResponse(false, 'Unauthorized: You must be logged in as an admin.', [], null);
            }

            // Check if the user has permission to delete admins
            if (!$admin->hasRole('admin')) {
                return apiResponse(false, 'Unauthorized: Only admins can delete other admins.', [], null);
            }

            // Find the user by ID
            $user = User::find($id);

            if (!$user) {
                return apiResponse(false, 'User not found.', [], null);
            }

            // Prevent an admin from deleting themselves
            if ($admin->id === $user->id) {
                return apiResponse(false, 'You cannot delete your own account.', [], null);
            }

            // Delete the user
            $user->delete();

            return apiResponse(true, 'Admin deleted successfully!', [], null,200);

        } catch (\Exception $e) {
            return apiResponse(false, 'An error occurred while deleting the admin.',
                $e->getMessage(), 'error');
        }
    }

}
