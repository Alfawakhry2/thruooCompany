<?php

namespace App\Http\Controllers\Modules\Sales\Api;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use App\Models\Modules\Sales\Branch;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class BranchController extends Controller
{

    /**
     * Get all lead sources with pagination
     */
    public function index(Request $request): JsonResponse
    {


        $perPage = $request->query('per_page', 15);
        $status = $request->query('status'); // active, inactive, or all

        $query = Branch::query();

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
    public function all($companySlug,Request $request): JsonResponse
    {
        $status = $request->query('status', 'active');

        $query = Branch::query();

        // if ($status !== 'all') {
        //     $query->where('status', $status);
        // }

        $sources = $query->select('name' , 'name_ar')->get();

        return response()->json([
            'success' => true,
            'data' => $sources,
        ]);
    }

    /**
     * Create a new lead source
     */
    public function store($companySlug, Request $request): JsonResponse
    {
        // Check permission
        $user = Auth::user();
        if (!$user->isOwner() && !$user->hasRole('Super Admin')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to create Branch',
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
        $data['created_by'] = $user->id;

        $source = Branch::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Branch created successfully',
            // 'data' => $source->load('creator'),
            'data' => $source,
        ], 201);
    }

    /**
     * Get a specific lead source
     */
    public function show($companySlug, $id): JsonResponse
    {

        $source = Branch::find($id);

        if (!$source) {
            return response()->json([
                'success' => false,
                'message' => 'Branch not found',
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
    public function update($companySlug,Request $request, $id): JsonResponse
    {

        // Check permission
        $user = Auth::user();
        if (!$user->isOwner() && !$user->hasRole('Super Admin')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to update Branch',
            ], 403);
        }

        $source = Branch::find($id);

        if (!$source) {
            return response()->json([
                'success' => false,
                'message' => 'Branch not found',
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
            'message' => 'Branch updated successfully',
            // 'data' => $source->fresh(['creator']),
            'data' => $source,
        ]);
    }

    /**
     * Delete a lead source
     */
    public function destroy($companySlug, $moduleId, $id): JsonResponse
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
                'message' => 'You do not have permission to delete Branch',
            ], 403);
        }

        $source = Branch::find($id);

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
     * Batch delete Branch
     */
    public function batchDelete($companySlug, $moduleId, Request $request): JsonResponse
    {
        // Check permission
        $user = Auth::user();
        if (!$user->isOwner() && !$user->hasRole('Super Admin')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to delete Branch',
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
        $sourcesWithLeads = Branch::whereIn('id', $request->ids)
            ->count();

        if ($sourcesWithLeads > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete lead sources with existing leads',
            ], 400);
        }

        Branch::whereIn('id', $request->ids)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Lead sources deleted successfully',
        ]);
    }

    /**
     * Toggle lead source status
     */
    public function toggleStatus($companySlug, $moduleId, $id): JsonResponse
    {
        // Check permission
        $user = Auth::user();
        if (!$user->isOwner() && !$user->hasRole('Super Admin')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to update Branch status',
            ], 403);
        }

        $source = Branch::find($id);

        if (!$source) {
            return response()->json([
                'success' => false,
                'message' => 'Branch not found',
            ], 404);
        }

        $source->status = $source->status === 'active' ? 'inactive' : 'active';
        $source->save();

        return response()->json([
            'success' => true,
            'message' => 'Branch status updated successfully',
            'data' => $source,
        ]);
    }
}
