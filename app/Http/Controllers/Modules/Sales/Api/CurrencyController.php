<?php

namespace App\Http\Controllers\Modules\Sales\Api;

use App\Http\Controllers\Controller;
use App\Models\Modules\Module;
use App\Models\Modules\Sales\Currency;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class CurrencyController extends Controller
{
    /**
     * Get all currencies
     */
    public function index($companySlug, $moduleId, $branchId, Request $request): JsonResponse
    {
        $module = Module::find($moduleId);
        if (!$module) {
            return response()->json(['success' => false, 'message' => 'Module not found'], 404);
        }

        $perPage = $request->query('per_page', 15);
        $status = $request->query('status');

        $query = Currency::with('creator')
            ->forBranch($branchId)
            ->where('module_id', $moduleId);

        if ($status && $status !== 'all') {
            $isActive = filter_var($status, FILTER_VALIDATE_BOOLEAN);
            $query->where('is_active', $isActive);
        }

        $currencies = $query->latest()->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $currencies,
        ]);
    }

    /**
     * Get all currencies (no pagination)
     */
    public function all($companySlug, $moduleId, $branchId): JsonResponse
    {
        $module = Module::find($moduleId);
        if (!$module) {
            return response()->json(['success' => false, 'message' => 'Module not found'], 404);
        }

        $currencies = Currency::forBranch($branchId)
            ->where('module_id', $moduleId)
            ->active()
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $currencies,
        ]);
    }

    /**
     * Create currency
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
                'message' => 'You do not have permission to create currencies',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'code' => ['required', 'string', 'size:3', 'uppercase', 'unique:currencies,code'],
            'symbol' => ['required', 'string', 'max:10'],
            'exchange_rate' => ['required', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'is_base' => ['nullable', 'boolean'],
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

        $currency = Currency::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Currency created successfully',
            'data' => $currency->load('creator'),
        ], 201);
    }

    /**
     * Get single currency
     */
    public function show($companySlug, $moduleId, $branchId, $currencyId): JsonResponse
    {
        $module = Module::find($moduleId);
        if (!$module) {
            return response()->json(['success' => false, 'message' => 'Module not found'], 404);
        }

        $currency = Currency::with('creator')
            ->forBranch($branchId)
            ->where('module_id', $moduleId)
            ->find($currencyId);

        if (!$currency) {
            return response()->json([
                'success' => false,
                'message' => 'Currency not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $currency,
        ]);
    }

    /**
     * Update currency
     */
    public function update($companySlug, $moduleId, $branchId, Request $request, $currencyId): JsonResponse
    {
        $module = Module::find($moduleId);
        if (!$module) {
            return response()->json(['success' => false, 'message' => 'Module not found'], 404);
        }

        $user = Auth::user();
        if (!$user->isOwner() && !$user->hasRole('Super Admin')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to update currencies',
            ], 403);
        }

        $currency = Currency::forBranch($branchId)->where('module_id', $moduleId)->find($currencyId);
        if (!$currency) {
            return response()->json([
                'success' => false,
                'message' => 'Currency not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'code' => ['sometimes', 'required', 'string', 'size:3', 'uppercase', 'unique:currencies,code,' . $currencyId],
            'symbol' => ['sometimes', 'required', 'string', 'max:10'],
            'exchange_rate' => ['sometimes', 'required', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'is_base' => ['nullable', 'boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $currency->update($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Currency updated successfully',
            'data' => $currency->fresh('creator'),
        ]);
    }

    /**
     * Delete currency
     */
    public function destroy($companySlug, $moduleId, $branchId, $currencyId): JsonResponse
    {
        $module = Module::find($moduleId);
        if (!$module) {
            return response()->json(['success' => false, 'message' => 'Module not found'], 404);
        }

        $user = Auth::user();
        if (!$user->isOwner() && !$user->hasRole('Super Admin')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to delete currencies',
            ], 403);
        }

        $currency = Currency::forBranch($branchId)->where('module_id', $moduleId)->find($currencyId);
        if (!$currency) {
            return response()->json([
                'success' => false,
                'message' => 'Currency not found',
            ], 404);
        }

        // Don't delete base currency
        if ($currency->is_base) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete base currency',
            ], 400);
        }

        // Check if used by products
        if ($currency->products()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete currency that is used by products',
            ], 400);
        }

        $currency->delete();

        return response()->json([
            'success' => true,
            'message' => 'Currency deleted successfully',
        ]);
    }

    /**
     * Convert between currencies
     */
    public function convert($companySlug, $moduleId, $branchId, Request $request): JsonResponse
    {
        $module = Module::find($moduleId);
        if (!$module) {
            return response()->json(['success' => false, 'message' => 'Module not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'amount' => ['required', 'numeric', 'min:0'],
            'from_currency_id' => ['required', 'exists:currencies,id'],
            'to_currency_id' => ['required', 'exists:currencies,id'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $fromCurrency = Currency::find($request->from_currency_id);
        $toCurrency = Currency::find($request->to_currency_id);

        $convertedAmount = Currency::convert($request->amount, $fromCurrency, $toCurrency);

        return response()->json([
            'success' => true,
            'data' => [
                'original_amount' => $request->amount,
                'converted_amount' => round($convertedAmount, 2),
                'from_currency' => $fromCurrency,
                'to_currency' => $toCurrency,
            ],
        ]);
    }
}
