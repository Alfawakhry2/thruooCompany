# 🎮 CONTROLLER UPDATE EXAMPLES - COMPLETE PATTERNS

## ✅ Example 1: ProductController (Full CRUD)

**File**: `app/Http/Controllers/Modules/Sales/Api/ProductController.php`

```php
<?php

namespace App\Http\Controllers\Modules\Sales\Api;

use App\Http\Controllers\Controller;
use App\Models\Modules\Sales\Product;
use App\Models\Modules\Module;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProductController extends Controller
{
    /**
     * Get all products for a branch
     */
    public function index($moduleId, $branchId, Request $request): JsonResponse
    {
        // Verify module exists
        $module = Module::find($moduleId);
        if (!$module) {
            return response()->json(['success' => false, 'message' => 'Module not found'], 404);
        }

        $perPage = $request->query('per_page', 15);
        $search = $request->query('search');
        $isActive = $request->query('is_active');

        // Filter by branch using scope
        $query = Product::with(['unit', 'categories', 'branch'])
            ->forBranch($branchId); // ← CRITICAL: Filter by branch

        // Apply search
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('name_ar', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        // Filter by active status
        if ($isActive !== null) {
            $query->where('is_active', filter_var($isActive, FILTER_VALIDATE_BOOLEAN));
        }

        $products = $query->latest()->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $products,
        ]);
    }

    /**
     * Create a new product
     */
    public function store($moduleId, $branchId, Request $request): JsonResponse
    {
        // Verify module exists
        $module = Module::find($moduleId);
        if (!$module) {
            return response()->json(['success' => false, 'message' => 'Module not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'sku' => ['nullable', 'string', 'max:100', 'unique:products,sku'],
            'price' => ['required', 'numeric', 'min:0'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'stock_quantity' => ['nullable', 'integer', 'min:0'],
            'unit_id' => ['nullable', 'exists:units,id'],
            'is_active' => ['boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Create product - branch_id is auto-set by HasBranchContext trait
        // But we explicitly set it for clarity
        $data = $validator->validated();
        $data['branch_id'] = $branchId; // ← Explicitly set branch
        $data['created_by'] = auth()->id();

        $product = Product::create($data);

        // Load relationships
        $product->load(['unit', 'categories', 'branch']);

        return response()->json([
            'success' => true,
            'message' => 'Product created successfully',
            'data' => $product,
        ], 201);
    }

    /**
     * Get a single product
     */
    public function show($moduleId, $branchId, $productId): JsonResponse
    {
        // Find product and ensure it belongs to this branch
        $product = Product::with(['unit', 'categories', 'vendors', 'variants', 'branch'])
            ->forBranch($branchId) // ← Filter by branch
            ->find($productId);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found in this branch',
            ], 404);
        }

        // Double-check user access (extra security layer)
        if (!$product->isAccessibleByUser()) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied to this product',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $product,
        ]);
    }

    /**
     * Update a product
     */
    public function update($moduleId, $branchId, $productId, Request $request): JsonResponse
    {
        // Find product and ensure it belongs to this branch
        $product = Product::forBranch($branchId)->find($productId);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found in this branch',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => ['sometimes', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'sku' => ['sometimes', 'string', 'max:100', 'unique:products,sku,' . $product->id],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'stock_quantity' => ['nullable', 'integer', 'min:0'],
            'unit_id' => ['nullable', 'exists:units,id'],
            'is_active' => ['boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $product->update($validator->validated());
        $product->load(['unit', 'categories', 'branch']);

        return response()->json([
            'success' => true,
            'message' => 'Product updated successfully',
            'data' => $product,
        ]);
    }

    /**
     * Delete a product
     */
    public function destroy($moduleId, $branchId, $productId): JsonResponse
    {
        // Find product and ensure it belongs to this branch
        $product = Product::forBranch($branchId)->find($productId);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found in this branch',
            ], 404);
        }

        $product->delete();

        return response()->json([
            'success' => true,
            'message' => 'Product deleted successfully',
        ]);
    }

    /**
     * Toggle product active status
     */
    public function toggleStatus($moduleId, $branchId, $productId): JsonResponse
    {
        $product = Product::forBranch($branchId)->find($productId);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found in this branch',
            ], 404);
        }

        $product->update(['is_active' => !$product->is_active]);

        return response()->json([
            'success' => true,
            'message' => 'Product status updated successfully',
            'data' => $product,
        ]);
    }

    /**
     * Batch delete products
     */
    public function batchDelete($moduleId, $branchId, Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'ids' => ['required', 'array'],
            'ids.*' => ['required', 'integer', 'exists:products,id'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $ids = $request->input('ids');

        // Only delete products from this branch
        $deletedCount = Product::forBranch($branchId)
            ->whereIn('id', $ids)
            ->delete();

        return response()->json([
            'success' => true,
            'message' => "{$deletedCount} products deleted successfully",
            'deleted_count' => $deletedCount,
        ]);
    }
}
```

---

## ✅ Example 2: TaxController (Settings)

**File**: `app/Http/Controllers/Modules/Sales/Api/TaxController.php`

```php
<?php

namespace App\Http\Controllers\Modules\Sales\Api;

use App\Http\Controllers\Controller;
use App\Models\Modules\Sales\Tax;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TaxController extends Controller
{
    /**
     * Get all taxes for a branch
     */
    public function index($moduleId, $branchId, Request $request): JsonResponse
    {
        $perPage = $request->query('per_page', 15);

        // Get taxes for this branch
        $taxes = Tax::forBranch($branchId) // ← Filter by branch
            ->latest()
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $taxes,
        ]);
    }

    /**
     * Get all active taxes (for dropdowns)
     */
    public function all($moduleId, $branchId): JsonResponse
    {
        $taxes = Tax::forBranch($branchId) // ← Filter by branch
            ->active()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $taxes,
        ]);
    }

    /**
     * Create a new tax
     */
    public function store($moduleId, $branchId, Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'type' => ['required', 'in:percentage,fixed'],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        // Create tax - branch_id auto-set
        $data = $validator->validated();
        $data['branch_id'] = $branchId; // ← Explicitly set branch

        $tax = Tax::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Tax created successfully',
            'data' => $tax,
        ], 201);
    }

    /**
     * Get a single tax
     */
    public function show($moduleId, $branchId, $taxId): JsonResponse
    {
        $tax = Tax::forBranch($branchId)->find($taxId);

        if (!$tax) {
            return response()->json([
                'success' => false,
                'message' => 'Tax not found in this branch',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $tax,
        ]);
    }

    /**
     * Update a tax
     */
    public function update($moduleId, $branchId, $taxId, Request $request): JsonResponse
    {
        $tax = Tax::forBranch($branchId)->find($taxId);

        if (!$tax) {
            return response()->json([
                'success' => false,
                'message' => 'Tax not found in this branch',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => ['sometimes', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'rate' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'type' => ['sometimes', 'in:percentage,fixed'],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $tax->update($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Tax updated successfully',
            'data' => $tax,
        ]);
    }

    /**
     * Delete a tax
     */
    public function destroy($moduleId, $branchId, $taxId): JsonResponse
    {
        $tax = Tax::forBranch($branchId)->find($taxId);

        if (!$tax) {
            return response()->json([
                'success' => false,
                'message' => 'Tax not found in this branch',
            ], 404);
        }

        $tax->delete();

        return response()->json([
            'success' => true,
            'message' => 'Tax deleted successfully',
        ]);
    }
}
```

---

## ✅ Example 3: ServiceController

**File**: `app/Http/Controllers/Modules/Sales/Api/ServiceController.php`

```php
<?php

namespace App\Http\Controllers\Modules\Sales\Api;

use App\Http\Controllers\Controller;
use App\Models\Modules\Sales\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ServiceController extends Controller
{
    public function index($moduleId, $branchId, Request $request): JsonResponse
    {
        $query = Service::with(['creator', 'branch'])
            ->forBranch($branchId); // ← Filter by branch

        if ($search = $request->query('search')) {
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('name_ar', 'like', "%{$search}%");
            });
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $services = $query->latest()->paginate($request->query('per_page', 15));

        return response()->json(['success' => true, 'data' => $services]);
    }

    public function store($moduleId, $branchId, Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'duration_minutes' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $data = $validator->validated();
        $data['branch_id'] = $branchId; // ← Set branch
        $data['created_by'] = auth()->id();

        $service = Service::create($data);
        $service->load(['creator', 'branch']);

        return response()->json([
            'success' => true,
            'message' => 'Service created successfully',
            'data' => $service,
        ], 201);
    }

    public function show($moduleId, $branchId, $serviceId): JsonResponse
    {
        $service = Service::with(['creator', 'branch'])
            ->forBranch($branchId)
            ->find($serviceId);

        if (!$service) {
            return response()->json(['success' => false, 'message' => 'Service not found'], 404);
        }

        return response()->json(['success' => true, 'data' => $service]);
    }

    public function update($moduleId, $branchId, $serviceId, Request $request): JsonResponse
    {
        $service = Service::forBranch($branchId)->find($serviceId);

        if (!$service) {
            return response()->json(['success' => false, 'message' => 'Service not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => ['sometimes', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'duration_minutes' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $service->update($validator->validated());
        $service->load(['creator', 'branch']);

        return response()->json([
            'success' => true,
            'message' => 'Service updated successfully',
            'data' => $service,
        ]);
    }

    public function destroy($moduleId, $branchId, $serviceId): JsonResponse
    {
        $service = Service::forBranch($branchId)->find($serviceId);

        if (!$service) {
            return response()->json(['success' => false, 'message' => 'Service not found'], 404);
        }

        $service->delete();

        return response()->json(['success' => true, 'message' => 'Service deleted successfully']);
    }

    public function toggleStatus($moduleId, $branchId, $serviceId): JsonResponse
    {
        $service = Service::forBranch($branchId)->find($serviceId);

        if (!$service) {
            return response()->json(['success' => false, 'message' => 'Service not found'], 404);
        }

        $service->update(['is_active' => !$service->is_active]);

        return response()->json([
            'success' => true,
            'message' => 'Service status updated',
            'data' => $service,
        ]);
    }
}
```

---

## 🎯 CONTROLLER UPDATE PATTERN (Template)

```php
// INDEX - List items
public function index($moduleId, $branchId, Request $request)
{
    $items = Model::forBranch($branchId) // ← CRITICAL
        ->latest()
        ->paginate(15);
    
    return response()->json(['success' => true, 'data' => $items]);
}

// STORE - Create item
public function store($moduleId, $branchId, Request $request)
{
    $validated = $request->validate([...]);
    
    $validated['branch_id'] = $branchId; // ← Set branch
    $validated['created_by'] = auth()->id();
    
    $item = Model::create($validated);
    
    return response()->json(['success' => true, 'data' => $item], 201);
}

// SHOW - Get single item
public function show($moduleId, $branchId, $id)
{
    $item = Model::forBranch($branchId)->findOrFail($id); // ← CRITICAL
    
    return response()->json(['success' => true, 'data' => $item]);
}

// UPDATE - Update item
public function update($moduleId, $branchId, $id, Request $request)
{
    $item = Model::forBranch($branchId)->findOrFail($id); // ← CRITICAL
    
    $validated = $request->validate([...]);
    $item->update($validated);
    
    return response()->json(['success' => true, 'data' => $item]);
}

// DESTROY - Delete item
public function destroy($moduleId, $branchId, $id)
{
    $item = Model::forBranch($branchId)->findOrFail($id); // ← CRITICAL
    $item->delete();
    
    return response()->json(['success' => true, 'message' => 'Deleted']);
}
```

---

## ✅ CRITICAL POINTS

### 1. ALWAYS Filter by Branch
```php
// ✅ CORRECT
$items = Model::forBranch($branchId)->get();

// ❌ WRONG - Data leakage!
$items = Model::all();
```

### 2. ALWAYS Set branch_id on Create
```php
// ✅ CORRECT
$data['branch_id'] = $branchId;
$item = Model::create($data);

// ⚠️ OK but relies on trait
$item = Model::create($data); // Trait auto-sets if currentBranchId() available
```

### 3. ALWAYS Use findOrFail with Branch Filter
```php
// ✅ CORRECT
$item = Model::forBranch($branchId)->findOrFail($id);

// ❌ WRONG - User could access other branch data!
$item = Model::findOrFail($id);
```

---

**Ready to update controllers? Follow these patterns!** 🚀
