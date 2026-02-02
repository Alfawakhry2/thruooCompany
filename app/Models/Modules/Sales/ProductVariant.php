<?php

namespace App\Models\Modules\Sales;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\HasBranchContext;

class ProductVariant extends Model
{
    use HasFactory, SoftDeletes , HasBranchContext;

    protected $connection = 'tenant';

    protected $fillable = [
        'branch_id',
        'product_id',
        'name',
        'name_ar',
        'sku',
        'price',
        'cost_price',
        'stock',
        'reserved',
        'image',
        'status',
        'attributes',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'stock' => 'integer',
        'reserved' => 'integer',
        'status' => 'boolean',
        'attributes' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the product this variant belongs to
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get all attribute values for this variant
     */
    public function attributeValues()
    {
        return $this->belongsToMany(
            AttributeValue::class,
            'product_variant_attribute_value',
            'product_variant_id',
            'attribute_value_id'
        )->withTimestamps();
    }

    /**
     * Check if variant has sufficient stock
     */
    public function hasSufficientStock(int $quantity): bool
    {
        return $this->stock >= $quantity;
    }

    /**
     * Reserve stock
     */
    public function reserve(int $quantity): void
    {
        if (!$this->hasSufficientStock($quantity)) {
            throw new \Exception("Insufficient stock for variant: {$this->name}");
        }

        $this->stock -= $quantity;
        $this->reserved += $quantity;
        $this->save();
    }

    /**
     * Release reserved stock
     */
    public function release(int $quantity): void
    {
        $this->stock += $quantity;
        $this->reserved = max(0, $this->reserved - $quantity);
        $this->save();
    }

    /**
     * Deduct reserved stock
     */
    public function deductReserved(int $quantity): void
    {
        $this->reserved = max(0, $this->reserved - $quantity);
        $this->save();
    }

    /**
     * Check if variant is active
     */
    public function isActive(): bool
    {
        return $this->status === true && $this->product->status === true;
    }

    /**
     * Get available stock
     */
    public function getAvailableStockAttribute(): int
    {
        return max(0, $this->stock - $this->reserved);
    }

    /**
     * Scope: Active variants
     */
    public function scopeActive($query)
    {
        return $query->where('status', true);
    }
}
