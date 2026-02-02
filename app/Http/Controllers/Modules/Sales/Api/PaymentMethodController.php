<?php

namespace App\Http\Controllers\Modules\Sales\Api;

use App\Http\Controllers\Controller;
use App\Models\Modules\Module;
use App\Models\Modules\Sales\PaymentMethod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class PaymentMethodController extends Controller
{
    /**
     * Get all payment methods
     */
    public function index($companySlug, $moduleId, $branchId, Request $request): JsonResponse
    {
        $module = Module::find($moduleId);
        if (!$module) {
            return response()->json(['success' => false, 'message' => 'Module not found'], 404);
        }

        $perPage = $request->query('per_page', 15);
        $status = $request->query('status');
        $type = $request->query('type');

        $query = PaymentMethod::with('creator')
            ->forBranch($branchId)
            ->where('module_id', $moduleId);

        if ($status && $status !== 'all') {
            $isActive = filter_var($status, FILTER_VALIDATE_BOOLEAN);
            $query->where('is_active', $isActive);
        }

        if ($type) {
            $query->where('type', $type);
        }

        $paymentMethods = $query->latest()->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $paymentMethods,
        ]);
    }

    /**
     * Get all payment methods (no pagination)
     */
    public function all($companySlug, $moduleId, $branchId): JsonResponse
    {
        $module = Module::find($moduleId);
        if (!$module) {
            return response()->json(['success' => false, 'message' => 'Module not found'], 404);
        }

        $paymentMethods = PaymentMethod::forBranch($branchId)
            ->where('module_id', $moduleId)
            ->active()
            ->orderBy('is_default', 'desc')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $paymentMethods,
        ]);
    }

    /**
     * Create payment method
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
                'message' => 'You do not have permission to create payment methods',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'type' => ['nullable', 'in:bank_transfer,cash,credit_card,paypal,other'],
            'name' => ['required', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'bank_name' => ['required', 'string', 'max:255'],
            'account_number' => ['required', 'string', 'max:100'],
            'account_holder' => ['nullable', 'string', 'max:255'],
            'iban' => ['nullable', 'string', 'max:34'],
            'swift_code' => ['nullable', 'string', 'max:11'],
            'is_active' => ['nullable', 'boolean'],
            'is_default' => ['nullable', 'boolean'],
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

        $paymentMethod = PaymentMethod::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Payment method created successfully',
            'data' => $paymentMethod->load('creator'),
        ], 201);
    }

    /**
     * Get single payment method
     */
    public function show($companySlug, $moduleId, $branchId, $paymentMethodId): JsonResponse
    {
        $module = Module::find($moduleId);
        if (!$module) {
            return response()->json(['success' => false, 'message' => 'Module not found'], 404);
        }

        $paymentMethod = PaymentMethod::with('creator')
            ->forBranch($branchId)
            ->where('module_id', $moduleId)
            ->find($paymentMethodId);

        if (!$paymentMethod) {
            return response()->json([
                'success' => false,
                'message' => 'Payment method not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $paymentMethod,
        ]);
    }

    /**
     * Update payment method
     */
    public function update($companySlug, $moduleId, $branchId, Request $request, $paymentMethodId): JsonResponse
    {
        $module = Module::find($moduleId);
        if (!$module) {
            return response()->json(['success' => false, 'message' => 'Module not found'], 404);
        }

        $user = Auth::user();
        if (!$user->isOwner() && !$user->hasRole('Super Admin')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to update payment methods',
            ], 403);
        }

        $paymentMethod = PaymentMethod::forBranch($branchId)->where('module_id', $moduleId)->find($paymentMethodId);
        if (!$paymentMethod) {
            return response()->json([
                'success' => false,
                'message' => 'Payment method not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'type' => ['sometimes', 'required', 'in:bank_transfer,cash,credit_card,paypal,other'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'account_number' => ['nullable', 'string', 'max:100'],
            'account_holder' => ['nullable', 'string', 'max:255'],
            'iban' => ['nullable', 'string', 'max:34'],
            'swift_code' => ['nullable', 'string', 'max:11'],
            'is_active' => ['nullable', 'boolean'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $paymentMethod->update($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Payment method updated successfully',
            'data' => $paymentMethod->fresh('creator'),
        ]);
    }

    /**
     * Delete payment method
     */
    public function destroy($companySlug, $moduleId, $branchId, $paymentMethodId): JsonResponse
    {
        $module = Module::find($moduleId);
        if (!$module) {
            return response()->json(['success' => false, 'message' => 'Module not found'], 404);
        }

        $user = Auth::user();
        if (!$user->isOwner() && !$user->hasRole('Super Admin')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to delete payment methods',
            ], 403);
        }

        $paymentMethod = PaymentMethod::forBranch($branchId)->where('module_id', $moduleId)->find($paymentMethodId);
        if (!$paymentMethod) {
            return response()->json([
                'success' => false,
                'message' => 'Payment method not found',
            ], 404);
        }

        $paymentMethod->delete();

        return response()->json([
            'success' => true,
            'message' => 'Payment method deleted successfully',
        ]);
    }
}
