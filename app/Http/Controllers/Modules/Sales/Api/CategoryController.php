<?php

namespace App\Http\Controllers\Modules\Sales\Api;

use Illuminate\Http\Request;
use App\Models\Modules\Module;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Models\Modules\Sales\Category;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class CategoryController extends Controller
{
    /**
     * Get all categories
     */
    public function index($companySlug, $moduleId, $branchId, Request $request): JsonResponse
    {
        // Verify module
        $module = Module::find($moduleId);
        if (!$module) {
            return response()->json(['success' => false, 'message' => 'Module not found'], 404);
        }

        $perPage = $request->query('per_page', 15);
        $status = $request->query('status');
        $type = $request->query('type');
        $parentId = $request->query('parent_id');
        $withCounts = $request->query('with_counts', false);

        $query = Category::with(['creator', 'teams'])
            ->forBranch($branchId)
            ->where('module_id', $moduleId);

        // Filter by status
        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        if ($type) {
            $query->where('type', $type);
        }
        // Filter by parent (root categories or subcategories)
        if ($parentId === 'null' || $parentId === null) {
            $query->whereNull('parent_id'); // Root categories only
        } elseif ($parentId) {
            $query->where('parent_id', $parentId);
        }

        // Add counts
        if ($withCounts) {
            $query->withCount(['products', 'children']);
        }

        $categories = $query->ordered()->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $categories,
        ]);
    }

    /**
     * Get all categories (no pagination)
     */
    public function all($companySlug, $moduleId, $branchId, Request $request): JsonResponse
    {
        $module = Module::find($moduleId);
        if (!$module) {
            return response()->json(['success' => false, 'message' => 'Module not found'], 404);
        }

        $status = $request->query('status', 'active');
        $rootOnly = $request->query('root_only', false);
        $type = $request->query('type');
        $query = Category::forBranch($branchId)->where('module_id', $moduleId);

        if ($status !== 'all') {
            $query->where('status', $status);
        }
        if ($type) {
            $query->where('type', $type);
        }
        if ($rootOnly) {
            $query->whereNull('parent_id');
        }

        $categories = $query->ordered()->get();

        return response()->json([
            'success' => true,
            'data' => $categories,
        ]);
    }

    /**
     * Create category
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
                'message' => 'You do not have permission to create categories',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'type' => ['required', 'string', 'in:service,product,unit'],
            'name' => ['required', 'string', 'max:255'],
            'name_ar' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'description_ar' => ['nullable', 'string'],
            'parent_id' => ['nullable', 'exists:categories,id'],
            'status' => ['nullable', 'in:active,inactive'],
            'order' => ['nullable', 'integer', 'min:0'],
            'team_ids' => ['nullable', 'array'],
            'team_ids.*' => ['exists:teams,id'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,svg', 'max:2048'],
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

        // Handle image upload
        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $imageName = time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
            $imagePath = $image->storeAs('categories', $imageName, 'public');
            $data['image'] = $imagePath;
        }
        $teamIds = $data['team_ids'] ?? [];
        unset($data['team_ids']);

        $category = Category::create($data);

        // Attach teams
        if (!empty($teamIds)) {
            $category->teams()->sync($teamIds);
        }

        return response()->json([
            'success' => true,
            'message' => 'Category created successfully',
            'data' => $category->load(['creator', 'teams', 'parent']),
        ], 201);
    }

    /**
     * Get single category
     */
    public function show($companySlug, $moduleId, $branchId, $categoryId): JsonResponse
    {
        $module = Module::find($moduleId);
        if (!$module) {
            return response()->json(['success' => false, 'message' => 'Module not found'], 404);
        }

        $category = Category::with([
            'creator',
            'teams',
            'parent',
            'children' => function ($query) {
                $query->withCount(['products', 'children']);
            }
        ])
            ->withCount(['products', 'children'])
            ->forBranch($branchId)
            ->where('module_id', $moduleId)
            ->find($categoryId);

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Category not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $category,
        ]);
    }

    /**
     * Update category
     */
    public function update($companySlug, $moduleId, $branchId, Request $request, $categoryId): JsonResponse
    {
        $module = Module::find($moduleId);
        if (!$module) {
            return response()->json(['success' => false, 'message' => 'Module not found'], 404);
        }

        $user = Auth::user();
        if (!$user->isOwner() && !$user->hasRole('Super Admin')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to update categories',
            ], 403);
        }

        $category = Category::forBranch($branchId)->where('module_id', $moduleId)->find($categoryId);
        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Category not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'type' => ['sometimes', 'string', 'in:service,product,unit'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'name_ar' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'description_ar' => ['nullable', 'string'],
            'parent_id' => ['nullable', 'exists:categories,id'],
            'status' => ['sometimes', 'required', 'in:active,inactive'],
            'order' => ['nullable', 'integer', 'min:0'],
            'team_ids' => ['nullable', 'array'],
            'team_ids.*' => ['exists:teams,id'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,svg', 'max:2048'],

        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        // Prevent circular reference
        if (isset($data['parent_id']) && $data['parent_id'] == $categoryId) {
            return response()->json([
                'success' => false,
                'message' => 'Category cannot be its own parent',
            ], 422);
        }

        // Handle image upload
        if ($request->hasFile('image')) {
            // Delete old image if exists
            if ($category->image) {
                Storage::disk('public')->delete($category->image);
            }

            $image = $request->file('image');
            $imageName = time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
            $imagePath = $image->storeAs('categories', $imageName, 'public');
            $data['image'] = $imagePath;
        }
        $teamIds = $data['team_ids'] ?? null;
        unset($data['team_ids']);

        $category->update($data);

        // Sync teams if provided
        if ($teamIds !== null) {
            $category->teams()->sync($teamIds);
        }

        return response()->json([
            'success' => true,
            'message' => 'Category updated successfully',
            'data' => $category->fresh(['creator', 'teams', 'parent']),
        ]);
    }

    /**
     * Delete category
     */
    public function destroy($companySlug, $moduleId, $branchId, $categoryId): JsonResponse
    {
        $module = Module::find($moduleId);
        if (!$module) {
            return response()->json(['success' => false, 'message' => 'Module not found'], 404);
        }

        $user = Auth::user();
        if (!$user->isOwner() && !$user->hasRole('Super Admin')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to delete categories',
            ], 403);
        }

        $category = Category::forBranch($branchId)->where('module_id', $moduleId)->find($categoryId);
        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Category not found',
            ], 404);
        }

        // Check if has products
        if ($category->products()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete category with existing products',
            ], 400);
        }

        // Check if has subcategories
        if ($category->children()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete category with subcategories',
            ], 400);
        }
        // Before $category->delete();
        if ($category->image) {
            Storage::disk('public')->delete($category->image);
        }
        $category->teams()->detach();
        $category->delete();

        return response()->json([
            'success' => true,
            'message' => 'Category deleted successfully',
        ]);
    }

    /**
     * Batch delete categories
     */
    public function batchDelete($companySlug, $moduleId, $branchId, Request $request): JsonResponse
    {
        $module = Module::find($moduleId);
        if (!$module) {
            return response()->json(['success' => false, 'message' => 'Module not found'], 404);
        }

        $user = Auth::user();
        if (!$user->isOwner() && !$user->hasRole('Super Admin')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to delete categories',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer', 'exists:categories,id'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $categories = Category::forBranch($branchId)
            ->where('module_id', $moduleId)
            ->whereIn('id', $request->ids)
            ->get();

        foreach ($categories as $category) {
            // Skip if has products or children
            if ($category->products()->count() > 0 || $category->children()->count() > 0) {
                continue;
            }

            $category->teams()->detach();
            $category->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Categories deleted successfully',
        ]);
    }

    /**
     * Assign teams to category
     */
    public function assignTeams($companySlug, $moduleId, $branchId, $categoryId, Request $request): JsonResponse
    {
        $module = Module::find($moduleId);
        if (!$module) {
            return response()->json(['success' => false, 'message' => 'Module not found'], 404);
        }

        $user = Auth::user();
        if (!$user->isOwner() && !$user->hasRole('Super Admin')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to assign teams',
            ], 403);
        }

        $category = Category::forBranch($branchId)->where('module_id', $moduleId)->find($categoryId);
        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Category not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'team_ids' => ['required', 'array'],
            'team_ids.*' => ['exists:teams,id'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $category->teams()->sync($request->team_ids);

        return response()->json([
            'success' => true,
            'message' => 'Teams assigned successfully',
            'data' => $category->load('teams'),
        ]);
    }

    public function toggleStatus($companySlug, $moduleId, $branchId, $id): JsonResponse
    {
        // Verify module
        $module = Module::find($moduleId);
        if (!$module) {
            return response()->json([
                'success' => false,
                'message' => 'Module not found',
            ], 404);
        }
        // Check permission
        $user = Auth::user();
        if (!$user->isOwner() && !$user->hasRole('Super Admin')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to update category status',
            ], 403);
        }

        $category = Category::find($id);

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'Category not found',
            ], 404);
        }

        $category->status = $category->status === 'active' ? 'inactive' : 'active';
        $category->save();

        return response()->json([
            'success' => true,
            'message' => 'Category status ' . $category->status,
            // 'data' => $category,
        ]);
    }
}
