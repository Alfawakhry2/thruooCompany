<?php

namespace App\Http\Controllers\Modules\Sales\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Models\Modules\Sales\Role;

class UserController extends Controller
{
    /**
     * Get all users/staff
     */
    public function index($companySlug, $moduleId, $branchId, Request $request): JsonResponse
    {
        $user = Auth::user();

        // Only owner/admin can view all users
        if (!$user->isOwner() && !$user->hasRole('Super Admin') && !$user->hasRole('Admin')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to view users',
            ], 403);
        }

        $perPage = $request->query('per_page', 15);
        $status = $request->query('status');
        $role = $request->query('role');
        $search = $request->query('search');

        $query = User::with(['roles', 'inviter', 'branches']);

        // Filter by status
        if ($status) {
            $query->where('status', $status);
        }

        // Filter by role
        if ($role) {
            $query->whereHas('roles', function ($q) use ($role) {
                $q->where('name', $role);
            });
        }

        // Search by name or email
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->latest()->paginate($perPage);

        // Format response
        $users->getCollection()->transform(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'title' => $user->title,
                'avatar' => $user->avatar_url,
                'status' => $user->status,
                'is_owner' => $user->is_owner,
                'profile_completed' => $user->profile_completed,
                'roles' => $user->roles->pluck('name'),
                'branches' => $user->branches->map(function ($branch) {
                    return [
                        'id' => $branch->id,
                        'name' => $branch->name,
                        'name_ar' => $branch->name_ar,
                    ];
                }),
                'invited_by' => $user->inviter ? $user->inviter->name : null,
                'invited_at' => $user->invited_at,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $users,
        ]);
    }

    /**
     * Get all users without pagination (for dropdowns, etc.)
     */
    public function all($companySlug, $moduleId, $branchId): JsonResponse
    {
        $user = Auth::user();

        // Only active users
        $users = User::where('status', User::STATUS_ACTIVE)
            ->with(['roles', 'branches'])
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatar' => $user->avatar_url,
                    'roles' => $user->roles->pluck('name'),
                    'branches' => $user->branches->pluck('name'),
                    'is_owner' => $user->is_owner,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $users,
        ]);
    }

    /**
     * Create a new staff member (direct creation without invitation)
     */
    public function store($companySlug, $moduleId, $branchId, Request $request): JsonResponse
    {
        $currentUser = Auth::user();

        // Check permission
        if (!$currentUser->isOwner() && !$currentUser->hasRole('Super Admin') && !$currentUser->hasRole('Admin')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to create users',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'english_name' => ['nullable', 'string', 'max:255'],
            'arabic_name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'phone' => ['nullable', 'string', 'max:20'],
            'other_phone' => ['nullable', 'string', 'max:20'],
            'role' => ['required', 'string', 'exists:roles,name'],
            'branch_ids' => ['nullable', 'array'],
            'branch_ids.*' => ['exists:branches,id'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'address' => ['nullable', 'string', 'max:500'],
            'basic_salary' => ['nullable', 'numeric', 'min:0'],
            'insurance' => ['nullable', 'numeric', 'min:0'],
            'title' => ['nullable', 'string', 'max:100'],
            'avatar' => ['nullable', 'string'], // base64 image
            'documents' => ['nullable', 'array'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        // Handle avatar upload
        $avatarPath = null;
        if ($request->avatar) {
            $avatarPath = $this->handleAvatarUpload($request->avatar);
        }

        // Create user
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'phone' => $request->phone,
            'title' => $request->title,
            'avatar' => $avatarPath,
            'status' => User::STATUS_ACTIVE,
            'is_owner' => false,
            'profile_completed' => true,
            'email_verified_at' => now(),
            'invited_by' => $currentUser->id,
            'invited_at' => now(),
        ]);

        // Assign role
        $role = Role::where('name', $request->role)->first();
        if ($role) {
            $user->assignRole($role);
        }

        // **FIX: Sync branches to pivot table**
        if ($request->branch_ids && is_array($request->branch_ids) && count($request->branch_ids) > 0) {
            $user->branches()->sync($request->branch_ids);
        }

        // Handle additional fields (store in preferences)
        $preferences = [
            'english_name' => $request->english_name,
            'arabic_name' => $request->arabic_name,
            'other_phone' => $request->other_phone,
            'department_id' => $request->department_id,
            'address' => $request->address,
            'basic_salary' => $request->basic_salary,
            'insurance' => $request->insurance,
        ];

        $user->update(['preferences' => $preferences]);

        // Load relationships for response
        $user->load(['roles', 'branches']);

        return response()->json([
            'success' => true,
            'message' => 'Staff member created successfully',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->roles->pluck('name')->first(),
                'branches' => $user->branches->map(function ($branch) {
                    return [
                        'id' => $branch->id,
                        'name' => $branch->name,
                        'name_ar' => $branch->name_ar,
                    ];
                }),
                'status' => $user->status,
                'created_at' => $user->created_at,
            ],
        ], 201);
    }

    /**
     * Get a specific user
     */
    public function show($companySlug, $moduleId, $branchId, $userId): JsonResponse
    {
        $currentUser = Auth::user();

        $user = User::with(['roles', 'inviter', 'branches'])->find($userId);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
            ], 404);
        }

        // Users can view their own profile or admins can view anyone
        if ($user->id !== $currentUser->id && !$currentUser->isOwner() && !$currentUser->hasRole('Super Admin') && !$currentUser->hasRole('Admin')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to view this user',
            ], 403);
        }

        $preferences = $user->preferences ?? [];

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'title' => $user->title,
                'avatar' => $user->avatar_url,
                'status' => $user->status,
                'is_owner' => $user->is_owner,
                'profile_completed' => $user->profile_completed,
                'roles' => $user->roles->pluck('name'),
                'branches' => $user->branches->map(function ($branch) {
                    return [
                        'id' => $branch->id,
                        'name' => $branch->name,
                        'name_ar' => $branch->name_ar,
                    ];
                }),
                'invited_by' => $user->inviter ? $user->inviter->name : null,
                'invited_at' => $user->invited_at,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
                // Additional fields from preferences
                'english_name' => $preferences['english_name'] ?? null,
                'arabic_name' => $preferences['arabic_name'] ?? null,
                'other_phone' => $preferences['other_phone'] ?? null,
                'department_id' => $preferences['department_id'] ?? null,
                'address' => $preferences['address'] ?? null,
                'basic_salary' => $preferences['basic_salary'] ?? null,
                'insurance' => $preferences['insurance'] ?? null,
            ],
        ]);
    }



    /**
     * Update a user
     */
    public function update($companySlug, $moduleId,$branchId ,Request $request, $userId): JsonResponse
    {
        $currentUser = Auth::user();

        $user = User::find($userId);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
            ], 404);
        }

        // Users can update their own profile or admins can update anyone (except owner)
        $canUpdate = $user->id === $currentUser->id ||
            (!$user->is_owner && ($currentUser->isOwner() || $currentUser->hasRole('Super Admin') || $currentUser->hasRole('Admin')));

        if (!$canUpdate) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to update this user',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => ['nullable', 'string', 'max:255'],
            'english_name' => ['nullable', 'string', 'max:255'],
            'arabic_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'unique:users,email,' . $userId, 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'other_phone' => ['nullable', 'string', 'max:20'],
            'role' => ['nullable', 'string', 'exists:roles,name'],
            'branch_ids' => ['nullable', 'array'],
            'branch_ids.*' => ['integer', 'exists:branches,id'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'address' => ['nullable', 'string', 'max:500'],
            'basic_salary' => ['nullable', 'numeric', 'min:0'],
            'insurance' => ['nullable', 'numeric', 'min:0'],
            'title' => ['nullable', 'string', 'max:100'],
            'avatar' => ['nullable', 'string'], // base64 image
            'status' => ['nullable', 'in:active,suspended'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = [];

        if ($request->has('name'))
            $data['name'] = $request->name;
        if ($request->has('email'))
            $data['email'] = $request->email;
        if ($request->has('phone'))
            $data['phone'] = $request->phone;
        if ($request->has('title'))
            $data['title'] = $request->title;

        // Only admins can change status
        if ($request->has('status') && ($currentUser->isOwner() || $currentUser->hasRole('Super Admin') || $currentUser->hasRole('Admin'))) {
            $data['status'] = $request->status;
        }

        // Handle avatar upload
        if ($request->has('avatar') && $request->avatar) {
            $data['avatar'] = $this->handleAvatarUpload($request->avatar);
        }

        // Update basic fields
        $user->update($data);

        // Update role (only admins)
        if ($request->has('role') && ($currentUser->isOwner() || $currentUser->hasRole('Super Admin'))) {
            $role = Role::where('name', $request->role)->first();
            if ($role) {
                $user->syncRoles([$role]);
            }
        }

        // **FIX: Sync branches - handle different scenarios**
        if ($request->has('branch_ids')) {
            // Get the actual value
            $branchIds = $request->input('branch_ids');

            if (is_array($branchIds)) {
                // Filter out null/empty values and keep only valid integers
                $validBranchIds = array_filter($branchIds, function ($id) {
                    return !is_null($id) && is_numeric($id);
                });

                if (count($validBranchIds) > 0) {
                    // Sync the valid branch IDs
                    $user->branches()->sync($validBranchIds);
                } else {
                    // Empty array means detach all branches
                    $user->branches()->detach();
                }
            } elseif (is_null($branchIds)) {
                // Null means detach all branches
                $user->branches()->detach();
            }
        }

        // Update preferences
        $currentPreferences = $user->preferences ?? [];
        $newPreferences = array_merge($currentPreferences, array_filter([
            'english_name' => $request->english_name,
            'arabic_name' => $request->arabic_name,
            'other_phone' => $request->other_phone,
            'department_id' => $request->department_id,
            'address' => $request->address,
            'basic_salary' => $request->basic_salary,
            'insurance' => $request->insurance,
        ], function ($value) {
            return !is_null($value);
        }));

        $user->update(['preferences' => $newPreferences]);

        // Load relationships for response
        $user->load(['roles', 'branches']);

        return response()->json([
            'success' => true,
            'message' => 'User updated successfully',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->roles->pluck('name'),
                'branches' => $user->branches->map(function ($branch) {
                    return [
                        'id' => $branch->id,
                        'name' => $branch->name,
                        'name_ar' => $branch->name_ar,
                    ];
                }),
                'status' => $user->status,
            ],
        ]);
    }
    /**
     * Delete a user
     */
    public function destroy($companySlug, $moduleId, $branchId, $userId): JsonResponse
    {
        $currentUser = Auth::user();

        // Only owner/admin can delete
        if (!$currentUser->isOwner() && !$currentUser->hasRole('Super Admin')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to delete users',
            ], 403);
        }

        $user = User::find($userId);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
            ], 404);
        }

        // Cannot delete owner
        if ($user->is_owner) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete the owner account',
            ], 400);
        }

        // Cannot delete self
        if ($user->id === $currentUser->id) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete your own account',
            ], 400);
        }

        // Detach all branches before deleting
        $user->branches()->detach();

        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'User deleted successfully',
        ]);
    }

    /**
     * Toggle user status (activate/suspend)
     */
    public function toggleStatus ($moduleId, $userId): JsonResponse
    {
        $currentUser = Auth::user();

        // Only owner/admin can toggle status
        if (!$currentUser->isOwner() && !$currentUser->hasRole('Super Admin') && !$currentUser->hasRole('Admin')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to change user status',
            ], 403);
        }

        $user = User::find($userId);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
            ], 404);
        }

        // Cannot change owner status
        if ($user->is_owner) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot change owner status',
            ], 400);
        }

        // Toggle status
        $newStatus = $user->status === User::STATUS_ACTIVE ? User::STATUS_SUSPENDED : User::STATUS_ACTIVE;
        $user->update(['status' => $newStatus]);

        return response()->json([
            'success' => true,
            'message' => "User {$newStatus} successfully",
            'data' => [
                'id' => $user->id,
                'status' => $user->status,
            ],
        ]);
    }

    /**
     * Get available roles
     */
    public function getRoles(): JsonResponse
    {
        $roles = Role::where('guard_name', 'web')
            ->get(['id', 'name'])
            ->map(function ($role) {
                return [
                    'id' => $role->id,
                    'name' => $role->name,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $roles,
        ]);
    }

    /**
     * Handle avatar upload from base64
     */
    private function handleAvatarUpload(?string $avatar): ?string
    {
        if (empty($avatar)) {
            return null;
        }

        // If it's already a URL, return as is
        if (filter_var($avatar, FILTER_VALIDATE_URL)) {
            return $avatar;
        }

        // Handle base64 encoded image
        if (preg_match('/^data:image\/(\w+);base64,/', $avatar, $matches)) {
            $extension = $matches[1];
            $data = substr($avatar, strpos($avatar, ',') + 1);
            $data = base64_decode($data);

            if ($data === false) {
                return null;
            }

            $filename = "avatars/" . uniqid() . ".{$extension}";

            try {
                Storage::disk('public')->put($filename, $data);
                return $filename;
            } catch (\Exception $e) {
                return null;
            }
        }

        return null;
    }
}
