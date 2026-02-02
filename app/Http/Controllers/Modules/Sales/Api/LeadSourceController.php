<?php

namespace App\Http\Controllers\Modules\Sales\Api;

use App\Http\Controllers\Controller;
use App\Models\Modules\Sales\LeadSource;
use App\Models\Modules\Module;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class LeadSourceController extends Controller
{

    /**
     * Get all lead sources with pagination
     */
    public function index($companySlug, $moduleId, $branchId, Request $request): JsonResponse
    {   
        $module = Module::find($moduleId);
        if (!$module) {
            return response()->json(['success' => false, 'message' => 'Module not found'], 404);
        }

        $perPage = $request->query('per_page', 15);
        $status = $request->query('status'); // active, inactive, or all

        $query = LeadSource::forBranch($branchId);

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
     * Get all lead sources without pagination
     */
    public function all($companySlug, $moduleId, $branchId, Request $request): JsonResponse
    {
        $module = Module::find($moduleId);
        if (!$module) {
            return response()->json(['success' => false, 'message' => 'Module not found'], 404);
        }
        $status = $request->query('status', 'active');

        $query = LeadSource::forBranch($branchId);

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
     * Create a new lead source
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
                'message' => 'You do not have permission to create lead sources',
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

        $source = LeadSource::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Lead source created successfully',
            // 'data' => $source->load('creator'),
            'data' => $source,
        ], 201);
    }

    /**
     * Get a specific lead source
     */
    public function show($companySlug, $moduleId, $branchId, $id): JsonResponse
    {
        $module = \App\Models\Modules\Module::find($moduleId);
        if (!$module) {
            return response()->json(['success' => false, 'message' => 'Module not found'], 404);
        }
        $source = LeadSource::forBranch($branchId)->with(['creator', 'leads'])->find($id);

        if (!$source) {
            return response()->json([
                'success' => false,
                'message' => 'Lead source not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $source,
        ]);
    }

    /**
     * Update a lead source
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
                'message' => 'You do not have permission to update lead sources',
            ], 403);
        }

        $source = LeadSource::forBranch($branchId)->find($id);

        if (!$source) {
            return response()->json([
                'success' => false,
                'message' => 'Lead source not found',
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
            'message' => 'Lead source updated successfully',
            // 'data' => $source->fresh(['creator']),
            'data' => $source,
        ]);
    }

    /**
     * Delete a lead source
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
                'message' => 'You do not have permission to delete lead sources',
            ], 403);
        }

        $source = LeadSource::forBranch($branchId)->find($id);

        if (!$source) {
            return response()->json([
                'success' => false,
                'message' => 'Lead source not found',
            ], 404);
        }

        // Check if source has leads
        if ($source->leads()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete lead source with existing leads',
            ], 400);
        }

        $source->delete();

        return response()->json([
            'success' => true,
            'message' => 'Lead source deleted successfully',
        ]);
    }

    /**
     * Batch delete lead sources
     */
    public function batchDelete(Request $request): JsonResponse
    {
        // Check permission
        $user = Auth::user();
        if (!$user->isOwner() && !$user->hasRole('Super Admin')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to delete lead sources',
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
        $sourcesWithLeads = LeadSource::whereIn('id', $request->ids)
            ->has('leads')
            ->count();

        if ($sourcesWithLeads > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete lead sources with existing leads',
            ], 400);
        }

        LeadSource::whereIn('id', $request->ids)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Lead sources deleted successfully',
        ]);
    }

    /**
     * Toggle lead source status
     */
    public function toggleStatus($companySlug, $moduleId, $branchId, $id): JsonResponse
    {
        // Check permission
        $user = Auth::user();
        if (!$user->isOwner() && !$user->hasRole('Super Admin')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to update lead source status',
            ], 403);
        }

        $source = LeadSource::find($id);

        if (!$source) {
            return response()->json([
                'success' => false,
                'message' => 'Lead source not found',
            ], 404);
        }

        $source->status = $source->status === 'active' ? 'inactive' : 'active';
        $source->save();

        return response()->json([
            'success' => true,
            'message' => 'Lead source status updated successfully',
            'data' => $source,
        ]);
    }
}
