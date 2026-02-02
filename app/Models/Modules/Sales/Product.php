<?php

namespace App\Models\Modules\Sales;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\User;
use App\Models\Modules\Module;
use App\Traits\HasBranchContext;

class Product extends Model
{
    use HasFactory, SoftDeletes  , HasBranchContext;

    protected $connection = 'tenant';

    protected $fillable = [
        'title',
        'title_ar',
        'description',
        'description_ar',
        'sku',
        'barcode',
        'base_price',
        'discount_price',
        'cost_price',
        'base_stock',
        'reserved',
        'min_stock',
        'track_by_branch',
        'last_restocked_at',
        'total_stock_lifetime',
        'tax_id',
        'currency_id',
        'module_id',
        'created_by',
        'branch_id', // Default branch
        'image',
        'images',
        'status',
        'is_featured',
        'track_stock',
        'slug',
        'meta',
        'settings',
    ];

    protected $casts = [
        'base_price' => 'decimal:2',
        'discount_price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'base_stock' => 'integer',
        'reserved' => 'integer',
        'min_stock' => 'integer',
        'total_stock_lifetime' => 'integer',
        'status' => 'boolean',
        'is_featured' => 'boolean',
        'track_stock' => 'boolean',
        'track_by_branch' => 'boolean',
        'images' => 'array',
        'meta' => 'array',
        'settings' => 'array',
        'last_restocked_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $hidden = ['pivot'];

    protected $appends = ['available_stock', 'effective_price'];

    // ============================================
    // RELATIONSHIPS
    // ============================================

    /**
     * Get the module this product belongs to
     */
    public function module()
    {
        return $this->belongsTo(Module::class);
    }

    /**
     * Get the user who created this product
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the default branch for this product
     */
    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    /**
     * Get the tax applied to this product
     */
    public function tax()
    {
        return $this->belongsTo(Tax::class);
    }

    /**
     * Get the currency for this product
     */
    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }

    /**
     * Get all categories for this product
     */
    public function categories()
    {
        return $this->belongsToMany(Category::class, 'product_category', 'product_id', 'category_id')
            ->withTimestamps();
    }

    /**
     * Get all vendors for this product
     */
    public function vendors()
    {
        return $this->belongsToMany(Vendor::class, 'product_vendor', 'product_id', 'vendor_id')
            ->withPivot('vendor_price', 'is_primary')
            ->withTimestamps();
    }

    /**
     * Get all branches where this product is available
     */
    public function branches()
    {
        return $this->belongsToMany(Branch::class, 'product_branches', 'product_id', 'branch_id')
            ->withPivot('stock', 'reserved', 'min_stock', 'price', 'is_active')
            ->withTimestamps();
    }

    /**
     * Get active branches only
     */
    public function activeBranches()
    {
        return $this->branches()->wherePivot('is_active', true);
    }

    /**
     * Get all variants for this product
     */
    public function variants()
    {
        return $this->hasMany(ProductVariant::class);
    }

    /**
     * Get only active variants
     */
    public function activeVariants()
    {
        return $this->variants()->where('status', true);
    }

    // ============================================
    // STOCK MANAGEMENT METHODS
    // ============================================

    /**
     * Check if product has sufficient stock
     */
    public function hasSufficientStock(int $quantity): bool
    {
        return $this->base_stock >= $quantity;
    }

    /**
     * Reserve stock (reduce available, increase reserved)
     */
    public function reserve(int $quantity): void
    {
        if (!$this->track_stock) {
            return;
        }

        if (!$this->hasSufficientStock($quantity)) {
            throw new \Exception("Insufficient stock for product: {$this->title}");
        }

        $this->base_stock -= $quantity;
        $this->reserved += $quantity;
        $this->save();
    }

    /**
     * Release reserved stock (increase available, decrease reserved)
     */
    public function release(int $quantity): void
    {
        if (!$this->track_stock) {
            return;
        }

        $this->base_stock += $quantity;
        $this->reserved = max(0, $this->reserved - $quantity);
        $this->save();
    }

    /**
     * Deduct reserved stock (finalize sale)
     */
    public function deductReserved(int $quantity): void
    {
        if (!$this->track_stock) {
            return;
        }

        $this->reserved = max(0, $this->reserved - $quantity);
        $this->save();
    }

    // ============================================
    // BRANCH STOCK MANAGEMENT METHODS
    // ============================================

    /**
     * Get stock for specific branch
     */
    public function getStockForBranch(int $branchId): int
    {
        if (!$this->track_by_branch) {
            return $this->base_stock;
        }

        $branchStock = $this->branches()
            ->where('branch_id', $branchId)
            ->first();

        return $branchStock ? $branchStock->pivot->stock : 0;
    }

    /**
     * Get available stock for specific branch (stock - reserved)
     */
    public function getAvailableStockForBranch(int $branchId): int
    {
        if (!$this->track_by_branch) {
            return $this->available_stock;
        }

        $branchStock = $this->branches()
            ->where('branch_id', $branchId)
            ->first();

        if (!$branchStock) {
            return 0;
        }

        return max(0, $branchStock->pivot->stock - $branchStock->pivot->reserved);
    }

    /**
     * Reserve stock for specific branch
     */
    public function reserveForBranch(int $branchId, int $quantity): void
    {
        if (!$this->track_stock) {
            return;
        }

        if (!$this->track_by_branch) {
            // Use regular reserve method
            $this->reserve($quantity);
            return;
        }

        $branchStock = $this->branches()
            ->where('branch_id', $branchId)
            ->first();

        if (!$branchStock) {
            throw new \Exception("Product not available in this branch");
        }

        $available = $branchStock->pivot->stock - $branchStock->pivot->reserved;

        if ($available < $quantity) {
            throw new \Exception("Insufficient stock in branch for product: {$this->title}");
        }

        $this->branches()->updateExistingPivot($branchId, [
            'reserved' => $branchStock->pivot->reserved + $quantity,
        ]);
    }

    /**
     * Release reserved stock for specific branch
     */
    public function releaseForBranch(int $branchId, int $quantity): void
    {
        if (!$this->track_stock) {
            return;
        }

        if (!$this->track_by_branch) {
            $this->release($quantity);
            return;
        }

        $branchStock = $this->branches()
            ->where('branch_id', $branchId)
            ->first();

        if (!$branchStock) {
            return;
        }

        $newReserved = max(0, $branchStock->pivot->reserved - $quantity);

        $this->branches()->updateExistingPivot($branchId, [
            'reserved' => $newReserved,
        ]);
    }

    /**
     * Deduct reserved stock from specific branch (finalize sale)
     */
    public function deductReservedFromBranch(int $branchId, int $quantity): void
    {
        if (!$this->track_stock) {
            return;
        }

        if (!$this->track_by_branch) {
            $this->deductReserved($quantity);
            return;
        }

        $branchStock = $this->branches()
            ->where('branch_id', $branchId)
            ->first();

        if (!$branchStock) {
            return;
        }

        $newStock = max(0, $branchStock->pivot->stock - $quantity);
        $newReserved = max(0, $branchStock->pivot->reserved - $quantity);

        $this->branches()->updateExistingPivot($branchId, [
            'stock' => $newStock,
            'reserved' => $newReserved,
        ]);
    }

    /**
     * Restock product (add stock)
     */
    public function restock(int $quantity, ?int $branchId = null): void
    {
        if ($this->track_by_branch && $branchId) {
            // Restock specific branch
            $branchStock = $this->branches()->where('branch_id', $branchId)->first();

            if ($branchStock) {
                $this->branches()->updateExistingPivot($branchId, [
                    'stock' => $branchStock->pivot->stock + $quantity,
                ]);
            }
        } else {
            // Restock main inventory
            $this->increment('base_stock', $quantity);
        }

        // Update tracking fields
        $this->updateLastRestocked();
        $this->addToLifetimeStock($quantity);
    }

    /**
     * Update last restocked timestamp
     */
    public function updateLastRestocked(): void
    {
        $this->update(['last_restocked_at' => now()]);
    }

    /**
     * Add to lifetime stock (when restocking)
     */
    public function addToLifetimeStock(int $quantity): void
    {
        $this->increment('total_stock_lifetime', $quantity);
    }

    // ============================================
    // HELPER METHODS
    // ============================================

    /**
     * Check if product is active
     */
    public function isActive(): bool
    {
        return $this->status === true;
    }

    /**
     * Check if stock is low
     */
    public function isLowStock(): bool
    {
        return $this->base_stock <= $this->min_stock;
    }

    /**
     * Check if product is low stock in any branch
     */
    public function isLowStockInAnyBranch(): bool
    {
        if (!$this->track_by_branch) {
            return $this->isLowStock();
        }

        $lowStockBranches = $this->branches()
            ->wherePivot('is_active', true)
            ->whereRaw('product_branches.stock <= product_branches.min_stock')
            ->count();

        return $lowStockBranches > 0;
    }

    /**
     * Get branches where stock is low
     */
    public function getLowStockBranches()
    {
        if (!$this->track_by_branch) {
            return collect();
        }

        return $this->branches()
            ->wherePivot('is_active', true)
            ->whereRaw('product_branches.stock <= product_branches.min_stock')
            ->get();
    }

    // ============================================
    // ACCESSORS
    // ============================================

    /**
     * Get available stock (base_stock - reserved)
     */
    public function getAvailableStockAttribute(): int
    {
        return max(0, $this->base_stock - $this->reserved);
    }

    /**
     * Get effective price (discount or base)
     */
    public function getEffectivePriceAttribute(): float
    {
        return $this->discount_price ?? $this->base_price;
    }

    /**
     * Get total stock across all branches
     */
    public function getTotalBranchStockAttribute(): int
    {
        if (!$this->track_by_branch) {
            return $this->base_stock;
        }

        return $this->branches()
            ->wherePivot('is_active', true)
            ->sum('product_branches.stock');
    }

    // ============================================
    // SCOPES
    // ============================================

    /**
     * Scope: Active products
     */
    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    /**
     * Scope: Featured products
     */
    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    /**
     * Scope: Low stock products
     */
    public function scopeLowStock($query)
    {
        return $query->whereRaw('base_stock <= min_stock');
    }

    /**
     * Scope: Products tracked by branch
     */
    public function scopeTrackedByBranch($query)
    {
        return $query->where('track_by_branch', true);
    }

    /**
     * Scope: Products in specific branch
     */
    public function scopeInBranch($query, $branchId)
    {
        return $query->whereHas('branches', function($q) use ($branchId) {
            $q->where('branches.id', $branchId)
              ->wherePivot('is_active', true);
        });
    }

    /**
     * Scope: Products with low stock in any branch
     */
    public function scopeLowStockInBranches($query)
    {
        return $query->where('track_by_branch', true)
            ->whereHas('branches', function($q) {
                $q->whereRaw('product_branches.stock <= product_branches.min_stock')
                  ->wherePivot('is_active', true);
            });
    }

    /**
     * Scope: For specific module
     */
    public function scopeForModule($query, $moduleId)
    {
        return $query->where('module_id', $moduleId);
    }

    /**
     * Scope: In category
     */
    public function scopeInCategory($query, $categoryId)
    {
        return $query->whereHas('categories', function($q) use ($categoryId) {
            $q->where('categories.id', $categoryId);
        });
    }

    /**
     * Scope: For specific branch (default branch)
     */
    public function scopeForBranch($query, $branchId)
    {
        return $query->where('branch_id', $branchId);
    }

    /**
     * Scope: Search
     */
    public function scopeSearch($query, $search)
    {
        return $query->where(function($q) use ($search) {
            $q->where('title', 'like', "%{$search}%")
              ->orWhere('title_ar', 'like', "%{$search}%")
              ->orWhere('sku', 'like', "%{$search}%")
              ->orWhere('barcode', 'like', "%{$search}%");
        });
    }
}
