<?php

namespace App\Http\Controllers\Modules\Sales\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Modules\Sales\Department;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class DepartmentController extends Controller
{
    /**
     * Get all Department with pagination
     */
    public function index($companySlug, $moduleId, $branchId, Request $request): JsonResponse
    {
        $module = \App\Models\Modules\Module::find($moduleId);
        if (!$module) {
            return response()->json(['success' => false, 'message' => 'Module not found'], 404);
        }

        $perPage = $request->query('per_page', 15);
        $status = $request->query('status'); // active, inactive, or all

        $query = Department::forBranch($branchId);

        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        $sources = $query->latest()->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $sources,
        ]);
    }

    /**
     * Get all Department without pagination
     */
    public function all($companySlug, $moduleId, $branchId, Request $request): JsonResponse
    {
        $module = \App\Models\Modules\Module::find($moduleId);
        if (!$module) {
            return response()->json(['success' => false, 'message' => 'Module not found'], 404);
        }
        $status = $request->query('status', 'active');

        $query = Department::forBranch($branchId);

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $sources = $query->orderBy('name')->get();

        return response()->json([
            'success' => true,
            'data' => $sources,
        ]);
    }

    /**
     * Create a new Department
     */
    public function store($companySlug, $moduleId, $branchId, Request $request): JsonResponse
    {
        $module = \App\Models\Modules\Module::find($moduleId);
        if (!$module) {
            return response()->json(['success' => false, 'message' => 'Module not found'], 404);
        }
        // Check permission
        $user = Auth::user();
        if (!$user->isOwner() && !$user->hasRole('Super Admin')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to create Department',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'name_ar' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'in:active,inactive'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $data['branch_id'] = $branchId;
        $data['created_by'] = $user->id;

        $source = Department::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Department created successfully',
            // 'data' => $source->load('creator'),
            'data' => $source,
        ], 201);
    }

    /**
     * Get a specific Department
     */
    public function show($companySlug, $moduleId, $branchId, $id): JsonResponse
    {
        $module = \App\Models\Modules\Module::find($moduleId);
        if (!$module) {
            return response()->json(['success' => false, 'message' => 'Module not found'], 404);
        }
        $source = Department::forBranch($branchId)->find($id);

        if (!$source) {
            return response()->json([
                'success' => false,
                'message' => 'Department not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $source,
        ]);
    }

    /**
     * Update a Department
     */
    public function update($companySlug, $moduleId, $branchId, Request $request, $id): JsonResponse
    {
        $module = \App\Models\Modules\Module::find($moduleId);
        if (!$module) {
            return response()->json(['success' => false, 'message' => 'Module not found'], 404);
        }
        // Check permission
        $user = Auth::user();
        if (!$user->isOwner() && !$user->hasRole('Super Admin')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to update Department',
            ], 403);
        }

        $source = Department::forBranch($branchId)->find($id);

        if (!$source) {
            return response()->json([
                'success' => false,
                'message' => 'Department not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['sometimes', 'required', 'in:active,inactive'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $source->update($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Department updated successfully',
            // 'data' => $source->fresh(['creator']),
            'data' => $source,
        ]);
    }

    /**
     * Delete a Department
     */
    public function destroy($companySlug, $moduleId, $branchId, $id): JsonResponse
    {
        $module = \App\Models\Modules\Module::find($moduleId);
        if (!$module) {
            return response()->json(['success' => false, 'message' => 'Module not found'], 404);
        }
        // Check permission
        $user = Auth::user();
        if (!$user->isOwner() && !$user->hasRole('Super Admin')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to delete Department',
            ], 403);
        }

        $source = Department::forBranch($branchId)->find($id);

        if (!$source) {
            return response()->json([
                'success' => false,
                'message' => 'Department not found',
            ], 404);
        }

        // Check if source has leads
        // if ($source->leads()->count() > 0) {
        //     return response()->json([
        //         'success' => false,
        //         'message' => 'Cannot delete Department with existing leads',
        //     ], 400);
        // }

        $source->delete();

        return response()->json([
            'success' => true,
            'message' => 'Department deleted successfully',
        ]);
    }

    /**
     * Batch delete Department
     */
    public function batchDelete($companySlug, $moduleId, $branchId, Request $request): JsonResponse
    {
        // Check permission
        $user = Auth::user();
        if (!$user->isOwner() && !$user->hasRole('Super Admin')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to delete Department',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer', 'exists:lead_sources,id'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        // Check if any source has leads
        $sourcesWithLeads = Department::whereIn('id', $request->ids)
            ->count();

        if ($sourcesWithLeads > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete Departments with existing leads',
            ], 400);
        }

        Department::whereIn('id', $request->ids)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Department deleted successfully',
        ]);
    }

    /**
     * Toggle Department status
     */

    public function toggleStatus($companySlug, $moduleId, $branchId, $id): JsonResponse
    {
        // Check permission
        $user = Auth::user();
        if (!$user->isOwner() && !$user->hasRole('Super Admin')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to update Department status',
            ], 403);
        }

        $source = Department::find($id);

        if (!$source) {
            return response()->json([
                'success' => false,
                'message' => 'Department not found',
            ], 404);
        }

        $source->status = $source->status === 'active' ? 'inactive' : 'active';
        $source->save();

        return response()->json([
            'success' => true,
            'message' => 'Department status updated successfully',
            'data' => $source,
        ]);
    }
}
