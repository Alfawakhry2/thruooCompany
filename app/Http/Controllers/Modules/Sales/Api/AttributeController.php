<?php

namespace App\Http\Controllers\Modules\Sales\Api;

use App\Http\Controllers\Controller;
use App\Models\Modules\Module;
use App\Models\Modules\Sales\Attribute;
use App\Models\Modules\Sales\AttributeValue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AttributeController extends Controller
{
    /**
     * Get all attributes
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
        $withValues = $request->query('with_values', false);

        $query = Attribute::with('creator')
            ->forBranch($branchId)
            ->where('module_id', $moduleId);

        if ($search) {
            $query->search($search);
        }

        if ($status && $status !== 'all') {
            $isActive = filter_var($status, FILTER_VALIDATE_BOOLEAN);
            $query->where('is_active', $isActive);
        }

        if ($withValues) {
            $query->with('values');
        }

        $attributes = $query->latest()->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $attributes,
        ]);
    }

    /**
     * Get all attributes (no pagination)
     */
    public function all($companySlug, $moduleId, $branchId): JsonResponse
    {
        $module = Module::find($moduleId);
        if (!$module) {
            return response()->json(['success' => false, 'message' => 'Module not found'], 404);
        }

        $attributes = Attribute::with('activeValues')
            ->forBranch($branchId)
            ->where('module_id', $moduleId)
            ->active()
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $attributes,
        ]);
    }

    /**
     * Create attribute
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
                'message' => 'You do not have permission to create attributes',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
            'values' => ['nullable', 'array'],
            'values.*.value' => ['required', 'string', 'max:255'],
            'values.*.value_ar' => ['nullable', 'string', 'max:255'],
            'values.*.color_code' => ['nullable', 'string', 'regex:/^#[0-9A-F]{6}$/i'],
            'values.*.is_active' => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();
        try {
            $data = $validator->validated();
            $valuesData = $data['values'] ?? [];
            unset($data['values']);

            $data['module_id'] = $moduleId;
            $data['branch_id'] = $branchId;
            $data['created_by'] = Auth::id();

            $attribute = Attribute::create($data);

            // Create values
            foreach ($valuesData as $valueData) {
                $attribute->values()->create($valueData);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Attribute created successfully',
                'data' => $attribute->load(['creator', 'values']),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create attribute: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get single attribute
     */
    public function show($companySlug, $moduleId, $branchId, $attributeId): JsonResponse
    {
        $module = Module::find($moduleId);
        if (!$module) {
            return response()->json(['success' => false, 'message' => 'Module not found'], 404);
        }

        $attribute = Attribute::with(['creator', 'values'])
            ->forBranch($branchId)
            ->where('module_id', $moduleId)
            ->find($attributeId);

        if (!$attribute) {
            return response()->json([
                'success' => false,
                'message' => 'Attribute not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $attribute,
        ]);
    }

    /**
     * Update attribute
     */
    public function update($companySlug, $moduleId, $branchId, Request $request, $attributeId): JsonResponse
    {
        $module = Module::find($moduleId);
        if (!$module) {
            return response()->json(['success' => false, 'message' => 'Module not found'], 404);
        }

        $user = Auth::user();
        if (!$user->isOwner() && !$user->hasRole('Super Admin')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to update attributes',
            ], 403);
        }

        $attribute = Attribute::forBranch($branchId)->where('module_id', $moduleId)->find($attributeId);
        if (!$attribute) {
            return response()->json([
                'success' => false,
                'message' => 'Attribute not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $attribute->update($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Attribute updated successfully',
            'data' => $attribute->fresh(['creator', 'values']),
        ]);
    }

    /**
     * Delete attribute
     */
    public function destroy($companySlug, $moduleId, $branchId, $attributeId): JsonResponse
    {
        $module = Module::find($moduleId);
        if (!$module) {
            return response()->json(['success' => false, 'message' => 'Module not found'], 404);
        }

        $user = Auth::user();
        if (!$user->isOwner() && !$user->hasRole('Super Admin')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to delete attributes',
            ], 403);
        }

        $attribute = Attribute::forBranch($branchId)->where('module_id', $moduleId)->find($attributeId);
        if (!$attribute) {
            return response()->json([
                'success' => false,
                'message' => 'Attribute not found',
            ], 404);
        }

        // Check if used by product variants
        $usedCount = AttributeValue::where('attribute_id', $attributeId)
            ->whereHas('productVariants')
            ->count();

        if ($usedCount > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete attribute that is used by product variants',
            ], 400);
        }

        DB::beginTransaction();
        try {
            // Delete all values first
            $attribute->values()->delete();
            $attribute->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Attribute deleted successfully',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete attribute: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Add value to attribute
     */
    public function addValue($companySlug, $moduleId, $branchId, $attributeId, Request $request): JsonResponse
    {
        $module = Module::find($moduleId);
        if (!$module) {
            return response()->json(['success' => false, 'message' => 'Module not found'], 404);
        }

        $user = Auth::user();
        if (!$user->isOwner() && !$user->hasRole('Super Admin')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to add attribute values',
            ], 403);
        }

        $attribute = Attribute::forBranch($branchId)->where('module_id', $moduleId)->find($attributeId);
        if (!$attribute) {
            return response()->json([
                'success' => false,
                'message' => 'Attribute not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'value' => ['required', 'string', 'max:255'],
            'value_ar' => ['nullable', 'string', 'max:255'],
            'color_code' => ['nullable', 'string', 'regex:/^#[0-9A-F]{6}$/i'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $value = $attribute->values()->create($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Attribute value added successfully',
            'data' => $value,
        ], 201);
    }

    /**
     * Update attribute value
     */
    public function updateValue($companySlug, $moduleId, $branchId, $attributeId, $valueId, Request $request): JsonResponse
    {
        $module = Module::find($moduleId);
        if (!$module) {
            return response()->json(['success' => false, 'message' => 'Module not found'], 404);
        }

        $user = Auth::user();
        if (!$user->isOwner() && !$user->hasRole('Super Admin')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to update attribute values',
            ], 403);
        }

        $attribute = Attribute::forBranch($branchId)->where('module_id', $moduleId)->find($attributeId);
        if (!$attribute) {
            return response()->json([
                'success' => false,
                'message' => 'Attribute not found',
            ], 404);
        }

        $value = $attribute->values()->find($valueId);
        if (!$value) {
            return response()->json([
                'success' => false,
                'message' => 'Attribute value not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'value' => ['sometimes', 'required', 'string', 'max:255'],
            'value_ar' => ['nullable', 'string', 'max:255'],
            'color_code' => ['nullable', 'string', 'regex:/^#[0-9A-F]{6}$/i'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $value->update($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Attribute value updated successfully',
            'data' => $value->fresh(),
        ]);
    }

    /**
     * Delete attribute value
     */
    public function deleteValue($companySlug, $moduleId, $branchId, $attributeId, $valueId): JsonResponse
    {
        $module = Module::find($moduleId);
        if (!$module) {
            return response()->json(['success' => false, 'message' => 'Module not found'], 404);
        }

        $user = Auth::user();
        if (!$user->isOwner() && !$user->hasRole('Super Admin')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to delete attribute values',
            ], 403);
        }

        $attribute = Attribute::forBranch($branchId)->where('module_id', $moduleId)->find($attributeId);
        if (!$attribute) {
            return response()->json([
                'success' => false,
                'message' => 'Attribute not found',
            ], 404);
        }

        $value = $attribute->values()->find($valueId);
        if (!$value) {
            return response()->json([
                'success' => false,
                'message' => 'Attribute value not found',
            ], 404);
        }

        // Check if used by product variants
        if ($value->productVariants()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete attribute value that is used by product variants',
            ], 400);
        }

        $value->delete();

        return response()->json([
            'success' => true,
            'message' => 'Attribute value deleted successfully',
        ]);
    }
}
