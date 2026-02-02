<?php

namespace App\Http\Controllers\Modules\Sales\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionController extends Controller
{
    /**
     * Get all roles with their permissions
     */
    public function getRoles($companySlug, $moduleId): JsonResponse
    {
        $user = Auth::user();

        // Only owner/admin can manage roles
        if (!$user->isOwner() && !$user->hasRole('Super Admin')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to view roles',
            ], 403);
        }

        $roles = Role::with('permissions')
            ->where('guard_name', 'web')
            ->get()
            ->map(function ($role) {
                return [
                    'id' => $role->id,
                    'name' => $role->name,
                    'permissions' => $role->permissions->pluck('name'),
                    'users_count' => $role->users()->count(),
                    'created_at' => $role->created_at,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $roles,
        ]);
    }

    /**
     * Get all available permissions grouped by module/category
     */
    public function getPermissions($companySlug, $moduleId): JsonResponse
    {
        $user = Auth::user();

        // Only owner/admin can view permissions
        if (!$user->isOwner() && !$user->hasRole('Super Admin')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to view permissions',
            ], 403);
        }

        $permissions = Permission::where('guard_name', 'web')
            ->get()
            ->groupBy(function ($permission) {
                // Group by the first part before the dot (e.g., "leads", "deals", etc.)
                $parts = explode('.', $permission->name);
                return $parts[0];
            })
            ->map(function ($group, $key) {
                return [
                    'category' => $key,
                    'label' => ucfirst(str_replace('_', ' ', $key)),
                    'permissions' => $group->map(function ($permission) {
                        return [
                            'id' => $permission->id,
                            'name' => $permission->name,
                            'label' => $this->formatPermissionLabel($permission->name),
                        ];
                    })->values(),
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => $permissions,
        ]);
    }

    /**
     * Get specific role with permissions
     */
    public function getRole($companySlug, $moduleId, $roleId): JsonResponse
    {
        $user = Auth::user();

        // Only owner/admin can view roles
        if (!$user->isOwner() && !$user->hasRole('Super Admin')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to view roles',
            ], 403);
        }

        $role = Role::with('permissions')->find($roleId);

        if (!$role) {
            return response()->json([
                'success' => false,
                'message' => 'Role not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name'),
                'users_count' => $role->users()->count(),
                'created_at' => $role->created_at,
            ],
        ]);
    }

    /**
     * Update role permissions
     */
    public function updateRolePermissions($companySlug, $moduleId, Request $request, $roleId): JsonResponse
    {
        $user = Auth::user();

        // Only owner can update permissions
        if (!$user->isOwner() && !$user->hasRole('Super Admin')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to update role permissions',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'permissions' => ['required', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $role = Role::find($roleId);

        if (!$role) {
            return response()->json([
                'success' => false,
                'message' => 'Role not found',
            ], 404);
        }

        // Cannot modify Super Admin role permissions
        if ($role->name === 'Super Admin') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot modify Super Admin role permissions',
            ], 403);
        }

        // Sync permissions
        $role->syncPermissions($request->permissions);

        return response()->json([
            'success' => true,
            'message' => 'Role permissions updated successfully',
            'data' => [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name'),
            ],
        ]);
    }

    /**
     * Get role access details (for the UI like your image)
     */
    public function getRoleAccessDetails($companySlug, $moduleId, $roleName): JsonResponse
    {
        $user = Auth::user();

        // Only owner/admin can view role details
        if (!$user->isOwner() && !$user->hasRole('Super Admin')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to view role details',
            ], 403);
        }

        $role = Role::where('name', $roleName)
            ->where('guard_name', 'web')
            ->with('permissions')
            ->first();

        if (!$role) {
            return response()->json([
                'success' => false,
                'message' => 'Role not found',
            ], 404);
        }

        // Get all permissions grouped by category
        $allPermissions = Permission::where('guard_name', 'web')
            ->get()
            ->groupBy(function ($permission) {
                $parts = explode('.', $permission->name);
                return $parts[0];
            });

        $rolePermissionNames = $role->permissions->pluck('name')->toArray();

        $accessDetails = $allPermissions->map(function ($permissions, $category) use ($rolePermissionNames) {
            $categoryPermissions = $permissions->map(function ($permission) use ($rolePermissionNames) {
                return [
                    'name' => $permission->name,
                    'label' => $this->formatPermissionLabel($permission->name),
                    'granted' => in_array($permission->name, $rolePermissionNames),
                ];
            });

            return [
                'category' => $category,
                'label' => $this->formatCategoryLabel($category),
                'permissions' => $categoryPermissions->values(),
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => [
                'role' => [
                    'id' => $role->id,
                    'name' => $role->name,
                ],
                'access_details' => $accessDetails,
            ],
        ]);
    }

    /**
     * Bulk update multiple roles permissions (like in your UI)
     */
    public function bulkUpdatePermissions($companySlug, $moduleId, Request $request): JsonResponse
    {
        $user = Auth::user();

        // Only owner can update permissions
        if (!$user->isOwner() && !$user->hasRole('Super Admin')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to update permissions',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'updates' => ['required', 'array'],
            'updates.*.role_id' => ['required', 'exists:roles,id'],
            'updates.*.permissions' => ['required', 'array'],
            'updates.*.permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $updated = [];

        foreach ($request->updates as $update) {
            $role = Role::find($update['role_id']);

            if ($role && $role->name !== 'Super Admin') {
                $role->syncPermissions($update['permissions']);
                $updated[] = $role->name;
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Permissions updated successfully',
            'data' => [
                'updated_roles' => $updated,
            ],
        ]);
    }

    /**
     * Toggle a single permission for a role
     */
    public function togglePermission($companySlug, $moduleId, Request $request): JsonResponse
    {
        $user = Auth::user();

        // Only owner can update permissions
        if (!$user->isOwner() && !$user->hasRole('Super Admin')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to update permissions',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'role_id' => ['required', 'exists:roles,id'],
            'permission_name' => ['required', 'string', 'exists:permissions,name'],
            'grant' => ['required', 'boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $role = Role::find($request->role_id);

        if ($role->name === 'Super Admin') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot modify Super Admin role permissions',
            ], 403);
        }

        if ($request->grant) {
            $role->givePermissionTo($request->permission_name);
        } else {
            $role->revokePermissionTo($request->permission_name);
        }

        return response()->json([
            'success' => true,
            'message' => 'Permission updated successfully',
            'data' => [
                'role' => $role->name,
                'permission' => $request->permission_name,
                'granted' => $request->grant,
            ],
        ]);
    }

    /**
     * Format permission label for display
     */
    private function formatPermissionLabel(string $permissionName): string
    {
        $parts = explode('.', $permissionName);
        $action = end($parts);

        return ucfirst(str_replace('_', ' ', $action));
    }

    /**
     * Format category label for display
     */
    private function formatCategoryLabel(string $category): string
    {
        $labels = [
            'leads' => 'Leads Management',
            'deals' => 'Deals Management',
            'proposals' => 'Proposals',
            'invoices' => 'Invoices',
            'reports' => 'Reports',
            'settings' => 'Settings',
            'users' => 'User Management',
            'roles' => 'Role Management',
            'targets' => 'Targets',
            'payments' => 'Payments',
            'purchasing_orders' => 'Purchasing Orders',
            'items_price' => 'Items Price',
        ];

        return $labels[$category] ?? ucfirst(str_replace('_', ' ', $category));
    }
}
