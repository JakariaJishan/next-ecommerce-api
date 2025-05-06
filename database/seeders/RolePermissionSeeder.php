<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission; // Make sure your Permission model now uses "resource" and "action"

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        // Define roles
        $roles = ['admin', 'user'];

        // Create roles
        foreach ($roles as $roleName) {
            Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        }

        // Define permissions for each role using the new structure
        $rolePermissions = [
            'admin' => [
                'category'      => ['create', 'update', 'destroy'],
                'blog'          => ['create', 'update', 'destroy'],
                'search_histories' => ['view'],
                'report'        => ['view', 'update'],
                'contest'       => ['create', 'update', 'destroy'],
                'seller_info'   => ['view'],
                'role'          => ['create', 'view', 'edit', 'delete'],
                'permission'    => ['create', 'view', 'edit', 'delete'],
                'admin'         => ['create', 'view', 'update', 'delete'],
            ],
            'user' => [
                'ads'           => ['create', 'update', 'destroy'],
                'report'        => ['create'],
                'contest_entry' => ['create', 'view', 'vote_create'],
            ],
        ];

        // Create permissions (grouped by resource) and assign them to roles
        foreach ($rolePermissions as $roleName => $resources) {
            $role = Role::where('name', $roleName)->first();
            if ($role) {
                foreach ($resources as $resource => $actions) {
                    // Create (or retrieve) the permission using resource and guard_name as the unique key.
                    $permission = Permission::firstOrCreate(
                        [
                            'resource'   => $resource,
                            'guard_name' => 'web'
                        ],
                        [
                            'action' => json_encode($actions)
                        ]
                    );

                    // If the permission exists but the actions might have changed,
                    // you could update it here if desired:
                    // $permission->update(['action' => json_encode($actions)]);

                    // Assign the permission to the role if not already assigned
                    if (!$role->hasPermissionTo($permission)) {
                        $role->givePermissionTo($permission);
                    }
                }
            }
        }
    }
}
