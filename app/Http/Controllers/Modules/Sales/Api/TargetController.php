<?php

namespace App\Http\Controllers\Modules\Sales\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Sales\StoreTargetRequest;
use App\Http\Requests\Api\Sales\UpdateTargetRequest;
use App\Models\Modules\Module;
use App\Models\Modules\Sales\Target;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TargetController extends Controller
{
    /**
     * Get all targets for a module
     */
    // public function index($companySlug, $moduleId, $branchId, Request $request): JsonResponse
    // {
    //     // Verify module exists
    //     $module = Module::find($moduleId);
    //     if (!$module) {
    //         return response()->json(['success' => false, 'message' => 'Module not found'], 404);
    //     }

    //     $perPage = $request->query('per_page', 15);
    //     $status = $request->query('status');
    //     $userId = $request->query('user_id');
    //     $roleName = $request->query('role_name');

    //     $query = Target::with(['user', 'creator', 'module'])
    //         ->forBranch($branchId)
    //         ->where('module_id', $moduleId);

    //     // Filter by status
    //     if ($status) {
    //         $query->where('status', $status);
    //     }

    //     // Filter by user
    //     if ($userId) {
    //         $query->where('user_id', $userId);
    //     }

    //     // Filter by role
    //     if ($roleName) {
    //         $query->where('role_name', $roleName);
    //     }

    //     $targets = $query->latest()->paginate($perPage);

    //     // Update progress for all targets
    //     foreach ($targets as $target) {
    //         $target->updateProgress();
    //     }

    //     return response()->json([
    //         'success' => true,
    //         'data' => $targets->fresh(),
    //     ]);
    // }
    public function index($companySlug, $moduleId, $branchId, Request $request): JsonResponse
{
    // Verify module exists
    $module = Module::find($moduleId);
    if (!$module) {
        return response()->json(['success' => false, 'message' => 'Module not found'], 404);
    }

    $perPage = (int) $request->query('per_page', 15);
    $status = $request->query('status');
    $userId = $request->query('user_id');
    $roleName = $request->query('role_name');

    $query = Target::with(['user', 'creator', 'module'])
        ->forBranch($branchId)
        ->where('module_id', $moduleId);

    // Filter by status
    if (!empty($status)) {
        $query->where('status', $status);
    }

    // Filter by user
    if (!empty($userId)) {
        $query->where('user_id', $userId);
    }

    // Filter by role
    if (!empty($roleName)) {
        $query->where('role_name', $roleName);
    }

    $targets = $query->latest()->paginate($perPage);

    // Update progress for all targets
    foreach ($targets->items() as $target) {
        $target->updateProgress();
    }

    // Reload targets after progress update
    $targetIds = collect($targets->items())->pluck('id')->toArray();

    $freshTargets = Target::with(['user', 'creator', 'module'])
        ->whereIn('id', $targetIds)
        ->latest()
        ->get()
        ->keyBy('id');

    // Replace paginator items with fresh updated records
    $targets->setCollection(
        collect($targets->items())->map(function ($target) use ($freshTargets) {
            return $freshTargets[$target->id] ?? $target;
        })
    );

    return response()->json([
        'success' => true,
        'data' => $targets,
    ]);
}


    /**
     * Create a new target
     */
    public function store($companySlug, $moduleId, $branchId, StoreTargetRequest $request): JsonResponse
    {
        // Verify module exists
        $module = Module::find($moduleId);
        if (!$module) {
            return response()->json(['success' => false, 'message' => 'Module not found'], 404);
        }

        $data = $request->validated();
        $data['module_id'] = $moduleId;
        $data['branch_id'] = $branchId;
        $data['created_by'] = Auth::id();
        $data['status'] = 'active';

        // Validate user belongs to tenant (if user_id specified)
        if (!empty($data['user_id'])) {
            $user = User::find($data['user_id']);
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found',
                ], 404);
            }
            // Clear role_name if user_id is set
            $data['role_name'] = null;
        }

        $target = Target::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Target created successfully',
            'data' => $target->load(['user', 'creator', 'module']),
        ], 201);
    }

    /**
     * Get a specific target
     */
    public function show($companySlug, $moduleId, $branchId, $targetId): JsonResponse
    {
        // Verify module exists
        $module = Module::find($moduleId);
        if (!$module) {
            return response()->json(['success' => false, 'message' => 'Module not found'], 404);
        }

        $target = Target::with(['user', 'creator', 'module'])
            ->forBranch($branchId)
            ->where('module_id', $moduleId)
            ->find($targetId);

        if (!$target) {
            return response()->json([
                'success' => false,
                'message' => 'Target not found',
            ], 404);
        }

        // Update progress
        $target->updateProgress();

        return response()->json([
            'success' => true,
            'data' => $target->fresh(['user', 'creator', 'module']),
        ]);
    }

    /**
     * Update a target
     */
    public function update($companySlug, $moduleId, $branchId, UpdateTargetRequest $request, $targetId): JsonResponse
    {
        // Verify module exists
        $module = Module::find($moduleId);
        if (!$module) {
            return response()->json(['success' => false, 'message' => 'Module not found'], 404);
        }

        $target = Target::forBranch($branchId)->where('module_id', $moduleId)->find($targetId);

        if (!$target) {
            return response()->json([
                'success' => false,
                'message' => 'Target not found',
            ], 404);
        }

        $data = $request->validated();

        // If updating user_id, clear role_name
        if (isset($data['user_id'])) {
            $data['role_name'] = null;
        }
        // If updating role_name, clear user_id
        if (isset($data['role_name'])) {
            $data['user_id'] = null;
        }

        $target->update($data);

        // Update progress after changes
        $target->updateProgress();

        return response()->json([
            'success' => true,
            'message' => 'Target updated successfully',
            'data' => $target->fresh(['user', 'creator', 'module']),
        ]);
    }

    /**
     * Delete a target
     */
    public function destroy($companySlug, $moduleId, $branchId, $targetId): JsonResponse
    {
        // Verify module exists
        $module = Module::find($moduleId);
        if (!$module) {
            return response()->json(['success' => false, 'message' => 'Module not found'], 404);
        }

        $user = Auth::user();

        // Only owner/admin can delete
        if (!$user->isOwner() && !$user->hasRole('Super Admin')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to delete targets',
            ], 403);
        }

        $target = Target::forBranch($branchId)->where('module_id', $moduleId)->find($targetId);

        if (!$target) {
            return response()->json([
                'success' => false,
                'message' => 'Target not found',
            ], 404);
        }

        $target->delete();

        return response()->json([
            'success' => true,
            'message' => 'Target deleted successfully',
        ]);
    }

    /**
     * Get targets for current user
     */
    public function myTargets($companySlug, $moduleId, $branchId): JsonResponse
    {
        // Verify module exists
        $module = Module::find($moduleId);
        if (!$module) {
            return response()->json(['success' => false, 'message' => 'Module not found'], 404);
        }

        $user = Auth::user();
        $userRoles = $user->getRoleNames()->toArray();

        // Get user-specific targets OR role-based targets
        $targets = Target::with(['user', 'creator', 'module'])
            ->forBranch($branchId)
            ->where('module_id', $moduleId)
            ->where(function ($query) use ($user, $userRoles) {
                $query->where('user_id', $user->id)
                    ->orWhereIn('role_name', $userRoles);
            })
            ->active()
            ->get();

        // Update progress for all targets
        foreach ($targets as $target) {
            $target->updateProgress();
        }

        return response()->json([
            'success' => true,
            'data' => $targets->fresh(),
        ]);
    }

    /**
     * Refresh target progress (manual recalculation)
     */
    public function refreshProgress($companySlug, $moduleId, $branchId, $targetId): JsonResponse
    {
        // Verify module exists
        $module = Module::find($moduleId);
        if (!$module) {
            return response()->json(['success' => false, 'message' => 'Module not found'], 404);
        }

        $target = Target::forBranch($branchId)->where('module_id', $moduleId)->find($targetId);

        if (!$target) {
            return response()->json([
                'success' => false,
                'message' => 'Target not found',
            ], 404);
        }

        $target->updateProgress();

        return response()->json([
            'success' => true,
            'message' => 'Target progress refreshed',
            'data' => $target->fresh(['user', 'creator', 'module']),
        ]);
    }

    /**
     * Get target statistics
     */
    public function stats($companySlug, $moduleId, $branchId): JsonResponse
    {
        // Verify module exists
        $module = Module::find($moduleId);
        if (!$module) {
            return response()->json(['success' => false, 'message' => 'Module not found'], 404);
        }

        $totalTargets = Target::forBranch($branchId)->where('module_id', $moduleId)->count();
        $activeTargets = Target::forBranch($branchId)->where('module_id', $moduleId)->active()->count();
        $completedTargets = Target::forBranch($branchId)->where('module_id', $moduleId)->where('status', 'completed')->count();
        $expiredTargets = Target::forBranch($branchId)->where('module_id', $moduleId)->where('status', 'expired')->count();

        $averageProgress = Target::forBranch($branchId)->where('module_id', $moduleId)
            ->active()
            ->avg('progress_percentage') ?? 0;

        return response()->json([
            'success' => true,
            'data' => [
                'total_targets' => $totalTargets,
                'active_targets' => $activeTargets,
                'completed_targets' => $completedTargets,
                'expired_targets' => $expiredTargets,
                'average_progress' => round($averageProgress, 2),
            ],
        ]);
    }
}
