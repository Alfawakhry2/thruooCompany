<?php

namespace App\Http\Controllers\Modules\Sales\Api;

use App\Http\Controllers\Controller;
use App\Models\Modules\Sales\LeadStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class LeadStatusController extends Controller
{
    /**
     * Get all lead statuses with pagination
     */
    public function index($companySlug, $moduleId, $branchId, Request $request): JsonResponse
    {
        $module = \App\Models\Modules\Module::find($moduleId);
        if (!$module) {
            return response()->json(['success' => false, 'message' => 'Module not found'], 404);
        }
        $perPage = $request->query('per_page', 15);
        $status = $request->query('status'); // active, inactive, or all

        $query = LeadStatus::with('creator')->forBranch($branchId);

        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        $statuses = $query->ordered()->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $statuses,
        ]);
    }

    /**
     * Get all lead statuses without pagination (ordered)
     */
    public function all($companySlug, $moduleId, $branchId, Request $request): JsonResponse
    {
        $module = \App\Models\Modules\Module::find($moduleId);
        if (!$module) {
            return response()->json(['success' => false, 'message' => 'Module not found'], 404);
        }
        $status = $request->query('status', 'active');

        $query = LeadStatus::forBranch($branchId);

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $statuses = $query->ordered()->get();

        return response()->json([
            'success' => true,
            'data' => $statuses,
        ]);
    }

    /**
     * Create a new lead status
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
                'message' => 'You do not have permission to create lead statuses',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'order' => ['nullable', 'integer', 'min:0'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9A-F]{6}$/i'],
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

        // Auto-assign order if not provided
        if (!isset($data['order'])) {
            $maxOrder = LeadStatus::max('order') ?? 0;
            $data['order'] = $maxOrder + 1;
        }

        // Default color if not provided
        if (!isset($data['color'])) {
            $data['color'] = '#3B82F6';
        }

        $leadStatus = LeadStatus::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Lead status created successfully',
            'data' => $leadStatus,
        ], 201);
    }

    /**
     * Get a specific lead status
     */
    public function show($companySlug, $moduleId, $branchId, $id): JsonResponse
    {
        $module = \App\Models\Modules\Module::find($moduleId);
        if (!$module) {
            return response()->json(['success' => false, 'message' => 'Module not found'], 404);
        }
        $leadStatus = LeadStatus::forBranch($branchId)->find($id);

        if (!$leadStatus) {
            return response()->json([
                'success' => false,
                'message' => 'Lead status not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $leadStatus,
        ]);
    }

    /**
     * Update a lead status
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
                'message' => 'You do not have permission to update lead statuses',
            ], 403);
        }

        $leadStatus = LeadStatus::forBranch($branchId)->find($id);

        if (!$leadStatus) {
            return response()->json([
                'success' => false,
                'message' => 'Lead status not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'order' => ['nullable', 'integer', 'min:0'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9A-F]{6}$/i'],
            'status' => ['sometimes', 'required', 'in:active,inactive'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $leadStatus->update($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Lead status updated successfully',
            'data' => $leadStatus,
        ]);
    }

    /**
     * Delete a lead status
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
                'message' => 'You do not have permission to delete lead statuses',
            ], 403);
        }

        $leadStatus = LeadStatus::forBranch($branchId)->find($id);

        if (!$leadStatus) {
            return response()->json([
                'success' => false,
                'message' => 'Lead status not found',
            ], 404);
        }

        // Check if status has leads
        if ($leadStatus->leads()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete lead status with existing leads',
            ], 400);
        }

        $leadStatus->delete();

        return response()->json([
            'success' => true,
            'message' => 'Lead status deleted successfully',
        ]);
    }

    /**
     * Reorder lead statuses
     */
    public function reorder($companySlug, $moduleId, $branchId, Request $request): JsonResponse
    {
        // Check permission
        $user = Auth::user();
        if (!$user->isOwner() && !$user->hasRole('Super Admin')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to reorder lead statuses',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'statuses' => ['required', 'array', 'min:1'],
            'statuses.*.id' => ['required', 'integer', 'exists:lead_statuses,id'],
            'statuses.*.order' => ['required', 'integer', 'min:0'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        foreach ($request->statuses as $statusData) {
            LeadStatus::where('id', $statusData['id'])
                ->update(['order' => $statusData['order']]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Lead statuses reordered successfully',
        ]);
    }

    /**
     * Batch delete lead statuses
     */
    public function batchDelete($companySlug, $moduleId, $branchId, Request $request): JsonResponse
    {
        // Check permission
        $user = Auth::user();
        if (!$user->isOwner() && !$user->hasRole('Super Admin')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to delete lead statuses',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer', 'exists:lead_statuses,id'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        // Check if any status has leads
        $statusesWithLeads = LeadStatus::whereIn('id', $request->ids)
            ->has('leads')
            ->count();

        if ($statusesWithLeads > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete lead statuses with existing leads',
            ], 400);
        }

        LeadStatus::whereIn('id', $request->ids)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Lead statuses deleted successfully',
        ]);
    }

    /**
     * Toggle lead status status (active/inactive)
     */
    public function toggleStatus($companySlug, $moduleId, $branchId, $id): JsonResponse
    {
        // Check permission
        $user = Auth::user();
        if (!$user->isOwner() && !$user->hasRole('Super Admin')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to update lead status',
            ], 403);
        }

        $leadStatus = LeadStatus::find($id);

        if (!$leadStatus) {
            return response()->json([
                'success' => false,
                'message' => 'Lead status not found',
            ], 404);
        }

        $leadStatus->status = $leadStatus->status === 'active' ? 'inactive' : 'active';
        $leadStatus->save();

        return response()->json([
            'success' => true,
            'message' => 'Lead status updated successfully',
            'data' => $leadStatus,
        ]);
    }
}
