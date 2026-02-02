<?php

namespace App\Http\Controllers\Modules\Sales\Api;

use App\Http\Controllers\Controller;
use App\Models\Modules\Module;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ModuleController extends Controller
{
    /**
     * Get all modules with pagination
     */
    public function index($companySlug, Request $request): JsonResponse
    {
        $perPage = $request->query('per_page', 15);
        $status = $request->query('status'); // active, inactive, or all

        $query = Module::query();

        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        $modules = $query->latest()->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $modules,
        ]);
    }

    /**
     * Get all modules without pagination
     */
    public function all($companySlug, Request $request): JsonResponse
    {
        $status = $request->query('status', 'active');

        $query = Module::query();

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $modules = $query->orderBy('name')->get();

        return response()->json([
            'success' => true,
            'data' => $modules,
        ]);
    }

    /**
     * Create a new module
     */
    public function store($companySlug, Request $request): JsonResponse
    {
        // Check permission
        $user = Auth::user();
        if (!$user->isOwner() && !$user->hasRole('Super Admin')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to create modules',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'in:active,inactive'],
            'subscription_start' => ['nullable', 'date'],
            'trial_end' => ['nullable', 'date', 'after_or_equal:subscription_start'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $module = Module::create($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Module created successfully',
            'data' => $module,
        ], 201);
    }

    /**
     * Get a specific module
     */
    public function show($companySlug, $id): JsonResponse
    {
        $module = Module::with(['leads', 'leadSources', 'leadStatuses'])->find($id);

        if (!$module) {
            return response()->json([
                'success' => false,
                'message' => 'Module not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $module,
        ]);
    }

    /**
     * Update a module
     */
    public function update($companySlug, Request $request, $id): JsonResponse
    {
        // Check permission
        $user = Auth::user();
        if (!$user->isOwner() && !$user->hasRole('Super Admin')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to update modules',
            ], 403);
        }

        $module = Module::find($id);

        if (!$module) {
            return response()->json([
                'success' => false,
                'message' => 'Module not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['sometimes', 'required', 'in:active,inactive'],
            'subscription_start' => ['nullable', 'date'],
            'trial_end' => ['nullable', 'date', 'after_or_equal:subscription_start'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $module->update($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Module updated successfully',
            'data' => $module->fresh(),
        ]);
    }

    /**
     * Delete a module
     */
    public function destroy($companySlug, $id): JsonResponse
    {
        // Check permission
        $user = Auth::user();
        if (!$user->isOwner() && !$user->hasRole('Super Admin')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to delete modules',
            ], 403);
        }

        $module = Module::find($id);

        if (!$module) {
            return response()->json([
                'success' => false,
                'message' => 'Module not found',
            ], 404);
        }

        // Check if module has leads
        if ($module->leads()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete module with existing leads',
            ], 400);
        }

        $module->delete();

        return response()->json([
            'success' => true,
            'message' => 'Module deleted successfully',
        ]);
    }

    /**
     * Toggle module status
     */
    public function toggleStatus($companySlug, $id): JsonResponse
    {
        // Check permission
        $user = Auth::user();
        if (!$user->isOwner() && !$user->hasRole('Super Admin')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to update module status',
            ], 403);
        }

        $module = Module::find($id);

        if (!$module) {
            return response()->json([
                'success' => false,
                'message' => 'Module not found',
            ], 404);
        }

        $module->status = $module->status === 'active' ? 'inactive' : 'active';
        $module->save();

        return response()->json([
            'success' => true,
            'message' => 'Module status updated successfully',
            'data' => $module,
        ]);
    }
}
