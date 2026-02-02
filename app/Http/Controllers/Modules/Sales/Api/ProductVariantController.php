<?php

namespace App\Http\Controllers\Modules\Sales\Api;

use App\Http\Controllers\Controller;
use App\Models\Modules\Module;
use App\Models\Modules\Sales\Product;
use App\Models\Modules\Sales\ProductVariant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ProductVariantController extends Controller
{
    /**
     * Get all variants for a product
     */
    public function index($companySlug, $moduleId, $branchId, $productId, Request $request): JsonResponse
    {
        $module = Module::find($moduleId);
        if (!$module) {
            return response()->json(['success' => false, 'message' => 'Module not found'], 404);
        }

        $product = Product::forBranch($branchId)->where('module_id', $moduleId)->find($productId);
        if (!$product) {
            return response()->json(['success' => false, 'message' => 'Product not found'], 404);
        }

        $status = $request->query('status');

        $query = ProductVariant::with(['attributeValues.attribute'])
            ->where('product_id', $productId);

        if ($status && $status !== 'all') {
            $isActive = filter_var($status, FILTER_VALIDATE_BOOLEAN);
            $query->where('status', $isActive);
        }

        $variants = $query->get();

        return response()->json([
            'success' => true,
            'data' => $variants,
        ]);
    }

    /**
     * Create variant
     */
    public function store($companySlug, $moduleId, $branchId, $productId, Request $request): JsonResponse
    {
        $module = Module::find($moduleId);
        if (!$module) {
            return response()->json(['success' => false, 'message' => 'Module not found'], 404);
        }

        $user = Auth::user();
        if (!$user->isOwner() && !$user->hasRole('Super Admin')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to create variants',
            ], 403);
        }

        $product = Product::forBranch($branchId)->where('module_id', $moduleId)->find($productId);
        if (!$product) {
            return response()->json(['success' => false, 'message' => 'Product not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => ['nullable', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:100', 'unique:product_variants,sku'],
            'price' => ['required', 'numeric', 'min:0'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'stock' => ['nullable', 'integer', 'min:0'],
            'image' => ['nullable', 'image', 'max:2048'],
            'status' => ['nullable', 'boolean'],
            'attribute_value_ids' => ['nullable', 'array'],
            'attribute_value_ids.*' => ['exists:attribute_values,id'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $attributeValueIds = $data['attribute_value_ids'] ?? [];
        unset($data['attribute_value_ids']);

        // Handle image
        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('variants', 'public');
        }

        $variant = $product->variants()->create($data);

        // Attach attribute values
        if (!empty($attributeValueIds)) {
            $variant->attributeValues()->sync($attributeValueIds);
        }

        return response()->json([
            'success' => true,
            'message' => 'Variant created successfully',
            'data' => $variant->load('attributeValues.attribute'),
        ], 201);
    }

    /**
     * Get single variant
     */
    public function show($companySlug, $moduleId, $branchId, $productId, $variantId): JsonResponse
    {
        $module = Module::find($moduleId);
        if (!$module) {
            return response()->json(['success' => false, 'message' => 'Module not found'], 404);
        }

        $product = Product::forBranch($branchId)->where('module_id', $moduleId)->find($productId);
        if (!$product) {
            return response()->json(['success' => false, 'message' => 'Product not found'], 404);
        }

        $variant = ProductVariant::with(['attributeValues.attribute', 'product'])
            ->where('product_id', $productId)
            ->find($variantId);

        if (!$variant) {
            return response()->json([
                'success' => false,
                'message' => 'Variant not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $variant,
        ]);
    }

    /**
     * Update variant
     */
    public function update($companySlug, $moduleId, $branchId, $productId, Request $request, $variantId): JsonResponse
    {
        $module = Module::find($moduleId);
        if (!$module) {
            return response()->json(['success' => false, 'message' => 'Module not found'], 404);
        }

        $user = Auth::user();
        if (!$user->isOwner() && !$user->hasRole('Super Admin')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to update variants',
            ], 403);
        }

        $product = Product::forBranch($branchId)->where('module_id', $moduleId)->find($productId);
        if (!$product) {
            return response()->json(['success' => false, 'message' => 'Product not found'], 404);
        }

        $variant = ProductVariant::where('product_id', $productId)->find($variantId);
        if (!$variant) {
            return response()->json([
                'success' => false,
                'message' => 'Variant not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:100', 'unique:product_variants,sku,' . $variantId],
            'price' => ['sometimes', 'required', 'numeric', 'min:0'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'stock' => ['nullable', 'integer', 'min:0'],
            'image' => ['nullable', 'image', 'max:2048'],
            'status' => ['nullable', 'boolean'],
            'attribute_value_ids' => ['nullable', 'array'],
            'attribute_value_ids.*' => ['exists:attribute_values,id'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $attributeValueIds = $data['attribute_value_ids'] ?? null;
        unset($data['attribute_value_ids']);

        // Handle image
        if ($request->hasFile('image')) {
            // Delete old image
            if ($variant->image && Storage::disk('public')->exists($variant->image)) {
                Storage::disk('public')->delete($variant->image);
            }
            $data['image'] = $request->file('image')->store('variants', 'public');
        }

        $variant->update($data);

        // Sync attribute values if provided
        if ($attributeValueIds !== null) {
            $variant->attributeValues()->sync($attributeValueIds);
        }

        return response()->json([
            'success' => true,
            'message' => 'Variant updated successfully',
            'data' => $variant->fresh(['attributeValues.attribute']),
        ]);
    }

    /**
     * Delete variant
     */
    public function destroy($companySlug, $moduleId, $branchId, $productId, $variantId): JsonResponse
    {
        $module = Module::find($moduleId);
        if (!$module) {
            return response()->json(['success' => false, 'message' => 'Module not found'], 404);
        }

        $user = Auth::user();
        if (!$user->isOwner() && !$user->hasRole('Super Admin')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to delete variants',
            ], 403);
        }

        $product = Product::forBranch($branchId)->where('module_id', $moduleId)->find($productId);
        if (!$product) {
            return response()->json(['success' => false, 'message' => 'Product not found'], 404);
        }

        $variant = ProductVariant::where('product_id', $productId)->find($variantId);
        if (!$variant) {
            return response()->json([
                'success' => false,
                'message' => 'Variant not found',
            ], 404);
        }

        // Delete image
        if ($variant->image && Storage::disk('public')->exists($variant->image)) {
            Storage::disk('public')->delete($variant->image);
        }

        $variant->delete();

        return response()->json([
            'success' => true,
            'message' => 'Variant deleted successfully',
        ]);
    }

    /**
     * Toggle variant status
     */
    public function toggleStatus($companySlug, $moduleId, $branchId, $productId, $variantId): JsonResponse
    {
        $module = Module::find($moduleId);
        if (!$module) {
            return response()->json(['success' => false, 'message' => 'Module not found'], 404);
        }

        $user = Auth::user();
        if (!$user->isOwner() && !$user->hasRole('Super Admin')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to update variants',
            ], 403);
        }

        $product = Product::forBranch($branchId)->where('module_id', $moduleId)->find($productId);
        if (!$product) {
            return response()->json(['success' => false, 'message' => 'Product not found'], 404);
        }

        $variant = ProductVariant::where('product_id', $productId)->find($variantId);
        if (!$variant) {
            return response()->json([
                'success' => false,
                'message' => 'Variant not found',
            ], 404);
        }

        $variant->update(['status' => !$variant->status]);

        return response()->json([
            'success' => true,
            'message' => 'Variant status updated successfully',
            'data' => $variant,
        ]);
    }
}
