<?php

namespace App\Http\Controllers\Modules\Sales\Api;

use App\Http\Controllers\Controller;
use App\Models\Modules\Module;
use App\Models\Modules\Sales\Vendor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class VendorController extends Controller
{
    /**
     * Get all vendors
     */
    public function index($companySlug, $moduleId, $branchId, Request $request): JsonResponse
    {
        $module = Module::find($moduleId);
        if (!$module) {
            return response()->json(['success' => false, 'message' => 'Module not found'], 404);
        }

        $perPage = $request->query('per_page', 15);
        $search = $request->query('search');
        $status = $request->query('status');

        $query = Vendor::with('creator')
            ->forBranch($branchId)
            ->where('module_id', $moduleId);

        if ($search) {
            $query->search($search);
        }

        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        $vendors = $query->latest()->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $vendors,
        ]);
    }

    /**
     * Get all vendors (no pagination)
     */
    public function all($companySlug, $moduleId, $branchId): JsonResponse
    {
        $module = Module::find($moduleId);
        if (!$module) {
            return response()->json(['success' => false, 'message' => 'Module not found'], 404);
        }

        $vendors = Vendor::forBranch($branchId)
            ->where('module_id', $moduleId)
            ->active()
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $vendors,
        ]);
    }

    /**
     * Create vendor
     */
    public function store($companySlug, $moduleId, $branchId, Request $request): JsonResponse
    {
        $module = Module::find($moduleId);
        if (!$module) {
            return response()->json(['success' => false, 'message' => 'Module not found'], 404);
        }

        $user = Auth::user();
        if (!$user->isOwner() && !$user->hasRole('Super Admin')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to create vendors',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'tax_number' => ['nullable', 'string', 'max:100'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:20'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'status' => ['nullable', 'in:active,inactive'],
            'notes' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $data['module_id'] = $moduleId;
        $data['branch_id'] = $branchId;
        $data['created_by'] = Auth::id();
        $data['status'] = $data['status'] ?? 'active';

        $vendor = Vendor::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Vendor created successfully',
            'data' => $vendor->load('creator'),
        ], 201);
    }

    /**
     * Get single vendor
     */
    public function show($companySlug, $moduleId, $branchId, $vendorId): JsonResponse
    {
        $module = Module::find($moduleId);
        if (!$module) {
            return response()->json(['success' => false, 'message' => 'Module not found'], 404);
        }

        $vendor = Vendor::with(['creator', 'products'])
            ->forBranch($branchId)
            ->where('module_id', $moduleId)
            ->find($vendorId);

        if (!$vendor) {
            return response()->json([
                'success' => false,
                'message' => 'Vendor not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $vendor,
        ]);
    }

    /**
     * Update vendor
     */
    public function update($companySlug, $moduleId, $branchId, Request $request, $vendorId): JsonResponse
    {
        $module = Module::find($moduleId);
        if (!$module) {
            return response()->json(['success' => false, 'message' => 'Module not found'], 404);
        }

        $user = Auth::user();
        if (!$user->isOwner() && !$user->hasRole('Super Admin')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to update vendors',
            ], 403);
        }

        $vendor = Vendor::forBranch($branchId)->where('module_id', $moduleId)->find($vendorId);
        if (!$vendor) {
            return response()->json([
                'success' => false,
                'message' => 'Vendor not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'tax_number' => ['nullable', 'string', 'max:100'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:20'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'status' => ['sometimes', 'required', 'in:active,inactive'],
            'notes' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $vendor->update($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Vendor updated successfully',
            'data' => $vendor->fresh('creator'),
        ]);
    }

    /**
     * Delete vendor
     */
    public function destroy($companySlug, $moduleId, $branchId, $vendorId): JsonResponse
    {
        $module = Module::find($moduleId);
        if (!$module) {
            return response()->json(['success' => false, 'message' => 'Module not found'], 404);
        }

        $user = Auth::user();
        if (!$user->isOwner() && !$user->hasRole('Super Admin')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to delete vendors',
            ], 403);
        }

        $vendor = Vendor::forBranch($branchId)->where('module_id', $moduleId)->find($vendorId);
        if (!$vendor) {
            return response()->json([
                'success' => false,
                'message' => 'Vendor not found',
            ], 404);
        }

        // Check if has products
        if ($vendor->products()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete vendor with existing products',
            ], 400);
        }

        $vendor->delete();

        return response()->json([
            'success' => true,
            'message' => 'Vendor deleted successfully',
        ]);
    }
}
