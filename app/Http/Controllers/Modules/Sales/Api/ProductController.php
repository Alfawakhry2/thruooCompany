<?php

namespace App\Http\Controllers\Modules\Sales\Api;

use App\Http\Controllers\Controller;
use App\Models\Modules\Module;
use App\Models\Modules\Sales\Product;
use App\Models\Modules\Sales\Category;
use App\Models\Modules\Sales\Branch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    /**
     * Get all products
     */
    public function index($companySlug, $moduleId, $branchId, Request $request): JsonResponse
    {
        // Verify module
        $module = Module::find($moduleId);
        if (!$module) {
            return response()->json(['success' => false, 'message' => 'Module not found'], 404);
        }

        $perPage = $request->query('per_page', 15);
        $search = $request->query('search');
        $categoryId = $request->query('category_id');
        $status = $request->query('status');
        $isFeatured = $request->query('is_featured');
        $lowStock = $request->query('low_stock');
        $trackByBranch = $request->query('track_by_branch');

        $query = Product::with([
            'categories:id,name,name_ar',
            'tax',
            'currency',
            'branch:id,name,name_ar' // Load default branch
        ])->forBranch($branchId)->where('module_id', $moduleId);

        // Search
        if ($search) {
            $query->search($search);
        }

        // Filter by category (including subcategories)
        if ($categoryId) {
            $categoryIds = Category::getAllCategoryIds($categoryId);
            $query->whereHas('categories', function ($q) use ($categoryIds) {
                $q->whereIn('categories.id', $categoryIds);
            });
        }



        // Filter by status
        if ($status !== null) {
            $query->where('status', filter_var($status, FILTER_VALIDATE_BOOLEAN));
        }

        // Filter featured
        if ($isFeatured !== null) {
            $query->where('is_featured', filter_var($isFeatured, FILTER_VALIDATE_BOOLEAN));
        }

        // Filter by track_by_branch
        if ($trackByBranch !== null) {
            $query->where('track_by_branch', filter_var($trackByBranch, FILTER_VALIDATE_BOOLEAN));
        }

        // Filter low stock
        if ($lowStock) {
            $query->lowStock();
        }

        $products = $query->latest()->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $products,
        ]);
    }

    /**
     * Create product
     */
    // public function store($companySlug, $moduleId, $branchId, Request $request): JsonResponse
    // {
    //     // Verify module
    //     $module = Module::find($moduleId);
    //     if (!$module) {
    //         return response()->json(['success' => false, 'message' => 'Module not found'], 404);
    //     }

    //     $validator = Validator::make($request->all(), [
    //         'title' => ['required', 'string', 'max:255'],
    //         'title_ar' => ['required', 'string', 'max:255'],
    //         'description' => ['nullable', 'string'],
    //         'description_ar' => ['nullable', 'string'],
    //         'sku' => ['nullable', 'string', 'max:100', 'unique:products,sku'],
    //         'barcode' => ['nullable', 'string', 'max:100'],
    //         'base_price' => ['required', 'numeric', 'min:0'],
    //         'discount_price' => ['nullable', 'numeric', 'min:0', 'lt:base_price'],
    //         'cost_price' => ['nullable', 'numeric', 'min:0'],
    //         'base_stock' => ['nullable', 'integer', 'min:0'],
    //         'min_stock' => ['nullable', 'integer', 'min:0'],
    //         'tax_id' => ['nullable', 'exists:taxes,id'],
    //         'currency_id' => ['nullable', 'exists:currencies,id'],
    //         'track_by_branch' => ['nullable', 'boolean'],
    //         'image' => ['nullable', 'image', 'max:2048'],
    //         'images.*' => ['nullable', 'image', 'max:2048'],
    //         'status' => ['nullable', 'boolean'],
    //         'is_featured' => ['nullable', 'boolean'],
    //         'track_stock' => ['nullable', 'boolean'],
    //         'category_ids' => ['required', 'array', 'min:1'],
    //         'category_ids.*' => ['exists:categories,id'],
    //         'vendor_ids' => ['nullable', 'array'],
    //         'vendor_ids.*' => ['exists:vendors,id'],

    //         // Branch stock management
    //         'branch_stocks' => ['nullable', 'array'],
    //         'branch_stocks.*.branch_id' => ['required', 'exists:branches,id'],
    //         'branch_stocks.*.stock' => ['required', 'integer', 'min:0'],
    //         'branch_stocks.*.min_stock' => ['nullable', 'integer', 'min:0'],
    //         'branch_stocks.*.price' => ['nullable', 'numeric', 'min:0'],
    //         'branch_stocks.*.is_active' => ['nullable', 'boolean'],

    //         // Variants
    //         'variants' => ['nullable', 'array'],
    //         'variants.*.name' => ['nullable', 'string'],
    //         'variants.*.name_ar' => ['nullable', 'string'],
    //         'variants.*.sku' => ['nullable', 'string', 'unique:product_variants,sku'],
    //         'variants.*.price' => ['required', 'numeric', 'min:0'],
    //         'variants.*.cost_price' => ['nullable', 'numeric', 'min:0'],
    //         'variants.*.stock' => ['nullable', 'integer', 'min:0'],
    //         'variants.*.image' => ['nullable', 'image', 'max:2048'],
    //         'variants.*.status' => ['nullable', 'boolean'],
    //         'variants.*.attribute_value_ids' => ['nullable', 'array'],
    //         'variants.*.attribute_value_ids.*' => ['exists:attribute_values,id'],
    //     ]);

    //     if ($validator->fails()) {
    //         return response()->json([
    //             'success' => false,
    //             'errors' => $validator->errors(),
    //         ], 422);
    //     }

    //     DB::beginTransaction();
    //     try {
    //         $data = $validator->validated();

    //         // Generate SKU if not provided
    //         if (empty($data['sku'])) {
    //             $data['sku'] = 'PRD-' . strtoupper(Str::random(8));
    //         }

    //         // Generate slug
    //         $data['slug'] = Str::slug($data['title']) . '-' . time();

    //         $data['module_id'] = $moduleId;
    //         $data['branch_id'] = $branchId;
    //         $data['created_by'] = Auth::id();

    //         // Handle main image
    //         if ($request->hasFile('image')) {
    //             $data['image'] = $request->file('image')->store('products', 'public');
    //         }

    //         // Handle additional images
    //         if ($request->hasFile('images')) {
    //             $images = [];
    //             foreach ($request->file('images') as $image) {
    //                 $images[] = $image->store('products', 'public');
    //             }
    //             $data['images'] = $images;
    //         }

    //         // Extract related data
    //         $categoryIds = $data['category_ids'] ?? [];
    //         $vendorIds = $data['vendor_ids'] ?? [];
    //         $variantsData = $data['variants'] ?? [];
    //         $branchStocks = $data['branch_stocks'] ?? [];

    //         unset($data['category_ids'], $data['vendor_ids'], $data['variants'], $data['branch_stocks']);

    //         // Create product
    //         $product = Product::create($data);

    //         // Attach categories
    //         $product->categories()->sync($categoryIds);

    //         // Attach vendors
    //         if (!empty($vendorIds)) {
    //             $product->vendors()->sync($vendorIds);
    //         }

    //         // Handle branch-specific stock (if track_by_branch is enabled)
    //         if (!empty($branchStocks) && ($data['track_by_branch'] ?? false)) {
    //             foreach ($branchStocks as $branchStock) {
    //                 $product->branches()->attach($branchStock['branch_id'], [
    //                     'stock' => $branchStock['stock'] ?? 0,
    //                     'reserved' => 0,
    //                     'min_stock' => $branchStock['min_stock'] ?? 0,
    //                     'price' => $branchStock['price'] ?? null,
    //                     'is_active' => $branchStock['is_active'] ?? true,
    //                 ]);
    //             }
    //         }

    //         // Create variants
    //         foreach ($variantsData as $variantData) {
    //             $attributeValueIds = $variantData['attribute_value_ids'] ?? [];
    //             unset($variantData['attribute_value_ids']);

    //             // Handle variant image
    //             if (isset($variantData['image']) && $variantData['image'] instanceof \Illuminate\Http\UploadedFile) {
    //                 $variantData['image'] = $variantData['image']->store('variants', 'public');
    //             }

    //             $variant = $product->variants()->create($variantData);

    //             // Attach attribute values
    //             if (!empty($attributeValueIds)) {
    //                 $variant->attributeValues()->sync($attributeValueIds);
    //             }
    //         }

    //         // Update last restocked timestamp and lifetime stock
    //         if (isset($data['base_stock']) && $data['base_stock'] > 0) {
    //             $product->updateLastRestocked();
    //             $product->addToLifetimeStock($data['base_stock']);
    //         }

    //         DB::commit();

    //         return response()->json([
    //             'success' => true,
    //             'message' => 'Product created successfully',
    //             'data' => $product->load([
    //                 'categories',
    //                 'vendors',
    //                 'branch',
    //                 'branches',
    //                 'variants.attributeValues'
    //             ]),
    //         ], 201);
    //     } catch (\Exception $e) {
    //         DB::rollBack();
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Failed to create product: ' . $e->getMessage(),
    //         ], 500);
    //     }
    // }
    public function store($companySlug, $moduleId, $branchId, Request $request): JsonResponse
{
    // Verify module
    $module = Module::find($moduleId);
    if (!$module) {
        return response()->json(['success' => false, 'message' => 'Module not found'], 404);
    }

    $validator = Validator::make($request->all(), [
        'title' => ['required', 'string', 'max:255'],
        'title_ar' => ['required', 'string', 'max:255'],
        'description' => ['nullable', 'string'],
        'description_ar' => ['nullable', 'string'],
        'sku' => ['nullable', 'string', 'max:100', 'unique:products,sku'],
        'barcode' => ['nullable', 'string', 'max:100'],
        'base_price' => ['required', 'numeric', 'min:0'],
        'discount_price' => ['nullable', 'numeric', 'min:0', 'lt:base_price'],
        'cost_price' => ['nullable', 'numeric', 'min:0'],
        'base_stock' => ['nullable', 'integer', 'min:0'],
        'min_stock' => ['nullable', 'integer', 'min:0'],
        'tax_id' => ['nullable', 'exists:taxes,id'],
        'currency_id' => ['nullable', 'exists:currencies,id'],
        'track_by_branch' => ['nullable', 'boolean'],
        'status' => ['nullable', 'boolean'],
        'is_featured' => ['nullable', 'boolean'],
        'track_stock' => ['nullable', 'boolean'],

        'category_ids' => ['required', 'array', 'min:1'],
        'category_ids.*' => ['exists:categories,id'],

        'vendor_ids' => ['nullable', 'array'],
        'vendor_ids.*' => ['exists:vendors,id'],

        // Variants
        'variants' => ['nullable', 'array'],
        'variants.*.name' => ['nullable', 'string'],
        'variants.*.name_ar' => ['nullable', 'string'],
        'variants.*.sku' => ['nullable', 'string', 'unique:product_variants,sku'],
        'variants.*.price' => ['required', 'numeric', 'min:0'],
        'variants.*.cost_price' => ['nullable', 'numeric', 'min:0'],
        'variants.*.stock' => ['nullable', 'integer', 'min:0'],
        'variants.*.status' => ['nullable', 'boolean'],
        'variants.*.attribute_value_ids' => ['nullable', 'array'],
        'variants.*.attribute_value_ids.*' => ['exists:attribute_values,id'],
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

        // Generate SKU if not provided
        if (empty($data['sku'])) {
            $data['sku'] = 'PRD-' . strtoupper(Str::random(8));
        }

        // Generate slug
        $data['slug'] = Str::slug($data['title']) . '-' . time();

        // ✅ REQUIRED FIELDS (from route/auth)
        $data['module_id']  = $moduleId;
        $data['branch_id']  = $branchId;      // default branch
        $data['created_by'] = Auth::id();      // must not be null

        // Extract related data
        $categoryIds  = $data['category_ids'] ?? [];
        $vendorIds    = $data['vendor_ids'] ?? [];
        $variantsData = $data['variants'] ?? [];

        unset($data['category_ids'], $data['vendor_ids'], $data['variants']);

        // Create product
        $product = Product::create($data);

        // Attach categories
        $product->categories()->sync($categoryIds);

        // Attach vendors
        if (!empty($vendorIds)) {
            $product->vendors()->sync($vendorIds);
        }

        // Create variants
        foreach ($variantsData as $variantData) {
            $attributeValueIds = $variantData['attribute_value_ids'] ?? [];
            unset($variantData['attribute_value_ids']);

            $variant = $product->variants()->create($variantData);

            if (!empty($attributeValueIds)) {
                $variant->attributeValues()->sync($attributeValueIds);
            }
        }

        DB::commit();

        return response()->json([
            'success' => true,
            'message' => 'Product created successfully',
            // ✅ DO NOT load 'branches' to avoid product_branches table
            'data' => $product->load([
                'categories',
                'vendors',
                'branch',
                'variants.attributeValues'
            ]),
        ], 201);

    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json([
            'success' => false,
            'message' => 'Failed to create product: ' . $e->getMessage(),
        ], 500);
    }
}


    /**
     * Get single product
     */
    public function show($companySlug, $moduleId, $branchId, $productId, Request $request): JsonResponse
    {
        $module = Module::find($moduleId);
        if (!$module) {
            return response()->json(['success' => false, 'message' => 'Module not found'], 404);
        }

        $isMobile = $request->query('platform') === 'mobile';

        $query = Product::with([
            'categories',
            'vendors',
            'tax',
            'currency',
            'branch', // Default branch
            'vendors',
        ])->forBranch($branchId)->where('module_id', $moduleId);

        if ($isMobile) {
            $query->where('status', true);
        }

        $product = $query->find($productId);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $product,
        ]);
    }

    /**
     * Update product
     */
    public function update($companySlug, $moduleId, $branchId, Request $request, $productId): JsonResponse
    {
        // Verify module
        $module = Module::find($moduleId);
        if (!$module) {
            return response()->json(['success' => false, 'message' => 'Module not found'], 404);
        }

        $user = Auth::user();
        if (!$user->isOwner() && !$user->hasRole('Super Admin')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to update products',
            ], 403);
        }

        $product = Product::forBranch($branchId)->where('module_id', $moduleId)->find($productId);
        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'title_ar' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'description_ar' => ['nullable', 'string'],
            'sku' => ['nullable', 'string', 'max:100', 'unique:products,sku,' . $productId],
            'barcode' => ['nullable', 'string', 'max:100'],
            'base_price' => ['sometimes', 'required', 'numeric', 'min:0'],
            'discount_price' => ['nullable', 'numeric', 'min:0', 'lt:base_price'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'base_stock' => ['nullable', 'integer', 'min:0'],
            'min_stock' => ['nullable', 'integer', 'min:0'],
            'tax_id' => ['nullable', 'exists:taxes,id'],
            'currency_id' => ['nullable', 'exists:currencies,id'],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'track_by_branch' => ['nullable', 'boolean'],
            'image' => ['nullable', 'image', 'max:2048'],
            'images.*' => ['nullable', 'image', 'max:2048'],
            'status' => ['nullable', 'boolean'],
            'is_featured' => ['nullable', 'boolean'],
            'track_stock' => ['nullable', 'boolean'],
            'category_ids' => ['sometimes', 'required', 'array', 'min:1'],
            'category_ids.*' => ['exists:categories,id'],
            'vendor_ids' => ['nullable', 'array'],
            'vendor_ids.*' => ['exists:vendors,id'],

            // Branch stock management
            'branch_stocks' => ['nullable', 'array'],
            'branch_stocks.*.branch_id' => ['required', 'exists:branches,id'],
            'branch_stocks.*.stock' => ['required', 'integer', 'min:0'],
            'branch_stocks.*.min_stock' => ['nullable', 'integer', 'min:0'],
            'branch_stocks.*.price' => ['nullable', 'numeric', 'min:0'],
            'branch_stocks.*.is_active' => ['nullable', 'boolean'],

            // Variants
            'variants' => ['nullable', 'array'],
            'variants.*.id' => ['nullable', 'exists:product_variants,id'],
            'variants.*.name' => ['nullable', 'string'],
            'variants.*.name_ar' => ['nullable', 'string'],
            'variants.*.sku' => ['nullable', 'string', 'unique:product_variants,sku'],
            'variants.*.price' => ['required', 'numeric', 'min:0'],
            'variants.*.cost_price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.stock' => ['nullable', 'integer', 'min:0'],
            'variants.*.image' => ['nullable', 'image', 'max:2048'],
            'variants.*.status' => ['nullable', 'boolean'],
            'variants.*.attribute_value_ids' => ['nullable', 'array'],
            'variants.*.attribute_value_ids.*' => ['exists:attribute_values,id'],
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
            $oldStock = $product->base_stock;

            // Handle main image upload
            if ($request->hasFile('image')) {
                // Delete old image
                if ($product->image && Storage::disk('public')->exists($product->image)) {
                    Storage::disk('public')->delete($product->image);
                }
                $data['image'] = $request->file('image')->store('products', 'public');
            }

            // Handle additional images
            if ($request->hasFile('images')) {
                // Delete old images
                if ($product->images) {
                    foreach ($product->images as $oldImage) {
                        if (Storage::disk('public')->exists($oldImage)) {
                            Storage::disk('public')->delete($oldImage);
                        }
                    }
                }

                $images = [];
                foreach ($request->file('images') as $image) {
                    $images[] = $image->store('products', 'public');
                }
                $data['images'] = $images;
            }

            // Extract related data
            $categoryIds = $data['category_ids'] ?? null;
            $vendorIds = $data['vendor_ids'] ?? null;
            $variantsData = $data['variants'] ?? [];
            $branchStocks = $data['branch_stocks'] ?? null;

            unset($data['category_ids'], $data['vendor_ids'], $data['variants'], $data['branch_stocks']);

            // Update product
            $product->update($data);

            // Sync categories
            if ($categoryIds !== null) {
                $product->categories()->sync($categoryIds);
            }

            // Sync vendors
            if ($vendorIds !== null) {
                $product->vendors()->sync($vendorIds);
            }

            // Handle branch stocks update
            if ($branchStocks !== null && $product->track_by_branch) {
                // Sync branch stocks
                $syncData = [];
                foreach ($branchStocks as $branchStock) {
                    $syncData[$branchStock['branch_id']] = [
                        'stock' => $branchStock['stock'] ?? 0,
                        'min_stock' => $branchStock['min_stock'] ?? 0,
                        'price' => $branchStock['price'] ?? null,
                        'is_active' => $branchStock['is_active'] ?? true,
                    ];
                }

                // This will add new, update existing, and remove unlisted branches
                $product->branches()->sync($syncData);
            }

            // Handle variants
            $existingVariantIds = [];

            foreach ($variantsData as $variantData) {
                $attributeValueIds = $variantData['attribute_value_ids'] ?? [];
                unset($variantData['attribute_value_ids']);

                // Handle variant image
                if (isset($variantData['image']) && $variantData['image'] instanceof \Illuminate\Http\UploadedFile) {
                    // Delete old variant image if updating
                    if (isset($variantData['id'])) {
                        $existingVariant = $product->variants()->find($variantData['id']);
                        if ($existingVariant && $existingVariant->image && Storage::disk('public')->exists($existingVariant->image)) {
                            Storage::disk('public')->delete($existingVariant->image);
                        }
                    }
                    $variantData['image'] = $variantData['image']->store('variants', 'public');
                }

                if (isset($variantData['id'])) {
                    // Update existing variant
                    $variant = $product->variants()->find($variantData['id']);
                    if ($variant) {
                        $variant->update($variantData);
                        $variant->attributeValues()->sync($attributeValueIds);
                        $existingVariantIds[] = $variant->id;
                    }
                } else {
                    // Create new variant
                    $newVariant = $product->variants()->create($variantData);
                    $newVariant->attributeValues()->sync($attributeValueIds);
                    $existingVariantIds[] = $newVariant->id;
                }
            }

            // Delete removed variants
            $removedVariants = $product->variants()->whereNotIn('id', $existingVariantIds)->get();
            foreach ($removedVariants as $removedVariant) {
                // Delete variant image
                if ($removedVariant->image && Storage::disk('public')->exists($removedVariant->image)) {
                    Storage::disk('public')->delete($removedVariant->image);
                }
                $removedVariant->delete();
            }

            // Update stock tracking if stock increased
            if (isset($data['base_stock'])) {
                $newStock = $data['base_stock'];
                if ($newStock > $oldStock) {
                    $addedStock = $newStock - $oldStock;
                    $product->updateLastRestocked();
                    $product->addToLifetimeStock($addedStock);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Product updated successfully',
                'data' => $product->fresh([
                    'categories',
                    'vendors',
                    'branch',
                    'branches',
                    'variants.attributeValues.attribute',
                    'tax',
                    'currency'
                ]),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update product: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Restock product - NEW METHOD
     */
    public function restock($companySlug, $moduleId, $branchId, $productId, Request $request): JsonResponse
    {
        $module = Module::find($moduleId);
        if (!$module) {
            return response()->json(['success' => false, 'message' => 'Module not found'], 404);
        }

        $user = Auth::user();
        if (!$user->isOwner() && !$user->hasRole('Super Admin')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to restock products',
            ], 403);
        }

        $product = Product::forBranch($branchId)->where('module_id', $moduleId)->find($productId);
        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'quantity' => ['required', 'integer', 'min:1'],
            'branch_id' => ['nullable', 'exists:branches,id'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $quantity = $request->quantity;
            $branchId = $request->branch_id;

            $product->restock($quantity, $branchId);

            return response()->json([
                'success' => true,
                'message' => 'Product restocked successfully',
                'data' => [
                    'product' => $product->fresh(['branch', 'branches']),
                    'restocked_quantity' => $quantity,
                    'branch_id' => $branchId,
                    'last_restocked_at' => $product->last_restocked_at,
                    'total_stock_lifetime' => $product->total_stock_lifetime,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to restock product: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get branch stock for product - NEW METHOD
     */
    public function getBranchStock($companySlug, $moduleId, $branchId, $productId, Request $request): JsonResponse
    {
        $module = Module::find($moduleId);
        if (!$module) {
            return response()->json(['success' => false, 'message' => 'Module not found'], 404);
        }

        $product = Product::forBranch($branchId)
            ->where('module_id', $moduleId)
            ->with([
                'branches' => function ($q) {
                    $q->wherePivot('is_active', true);
                }
            ])
            ->find($productId);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found',
            ], 404);
        }

        if (!$product->track_by_branch) {
            return response()->json([
                'success' => false,
                'message' => 'This product does not track stock by branch',
            ], 400);
        }

        $branchStocks = $product->branches->map(function ($branch) {
            return [
                'branch_id' => $branch->id,
                'branch_name' => $branch->name,
                'branch_name_ar' => $branch->name_ar,
                'branch_code' => $branch->code,
                'stock' => $branch->pivot->stock,
                'reserved' => $branch->pivot->reserved,
                'available' => max(0, $branch->pivot->stock - $branch->pivot->reserved),
                'min_stock' => $branch->pivot->min_stock,
                'is_low_stock' => $branch->pivot->stock <= $branch->pivot->min_stock,
                'price' => $branch->pivot->price,
                'is_active' => $branch->pivot->is_active,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'product_id' => $product->id,
                'product_title' => $product->title,
                'track_by_branch' => $product->track_by_branch,
                'branch_stocks' => $branchStocks,
            ],
        ]);
    }

    /**
     * Get low stock products - NEW METHOD
     */
    public function lowStock($companySlug, $moduleId, $branchId, Request $request): JsonResponse
    {
        $module = Module::find($moduleId);
        if (!$module) {
            return response()->json(['success' => false, 'message' => 'Module not found'], 404);
        }

        $perPage = $request->query('per_page', 15);
        $branchId = $request->query('branch_id');

        $query = Product::with(['categories:id,name,name_ar', 'branch'])
            ->forBranch($branchId)
            ->where('module_id', $moduleId)
            ->where('status', true);


        // Get all low stock products
        $query->where(function ($q) {
            $q->whereRaw('base_stock <= min_stock')
                ->orWhere(function ($subQ) {
                    $subQ->where('track_by_branch', true)
                        ->whereHas('branches', function ($branchQ) {
                            $branchQ->whereRaw('product_branches.stock <= product_branches.min_stock')
                                ->wherePivot('is_active', true);
                        });
                });
        });

        $products = $query->latest()->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $products,
        ]);
    }

    /**
     * Toggle product status
     */
    public function toggleStatus($companySlug, $moduleId, $branchId, $productId): JsonResponse
    {
        $module = Module::find($moduleId);
        if (!$module) {
            return response()->json(['success' => false, 'message' => 'Module not found'], 404);
        }

        $user = Auth::user();
        if (!$user->isOwner() && !$user->hasRole('Super Admin')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to update products',
            ], 403);
        }

        $product = Product::forBranch($branchId)->where('module_id', $moduleId)->find($productId);
        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found',
            ], 404);
        }

        $product->update(['status' => !$product->status]);

        return response()->json([
            'success' => true,
            'message' => 'Product status updated successfully',
            'data' => $product,
        ]);
    }

    /**
     * Batch delete products
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
                'message' => 'You do not have permission to delete products',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer', 'exists:products,id'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();
        try {
            $products = Product::forBranch($branchId)
                ->where('module_id', $moduleId)
                ->whereIn('id', $request->ids)
                ->get();

            foreach ($products as $product) {
                // Delete images
                if ($product->image && Storage::disk('public')->exists($product->image)) {
                    Storage::disk('public')->delete($product->image);
                }
                if ($product->images) {
                    foreach ($product->images as $image) {
                        if (Storage::disk('public')->exists($image)) {
                            Storage::disk('public')->delete($image);
                        }
                    }
                }

                // Delete variant images
                foreach ($product->variants as $variant) {
                    if ($variant->image && Storage::disk('public')->exists($variant->image)) {
                        Storage::disk('public')->delete($variant->image);
                    }
                }

                $product->delete();
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Products deleted successfully',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete products: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete product
     */
    public function destroy($moduleId, $branchId, $productId): JsonResponse
    {
        $module = Module::find($moduleId);
        if (!$module) {
            return response()->json(['success' => false, 'message' => 'Module not found'], 404);
        }

        $user = Auth::user();
        if (!$user->isOwner() && !$user->hasRole('Super Admin')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to delete products',
            ], 403);
        }

        $product = Product::forBranch($branchId)->where('module_id', $moduleId)->find($productId);
        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found',
            ], 404);
        }

        DB::beginTransaction();
        try {
            // Delete images
            if ($product->image) {
                Storage::disk('public')->delete($product->image);
            }
            if ($product->images) {
                foreach ($product->images as $image) {
                    Storage::disk('public')->delete($image);
                }
            }

            // Delete variant images
            foreach ($product->variants as $variant) {
                if ($variant->image) {
                    Storage::disk('public')->delete($variant->image);
                }
            }

            $product->delete();
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Product deleted successfully',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete product: ' . $e->getMessage(),
            ], 500);
        }
    }
}
