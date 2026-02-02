<?php

namespace App\Models\Modules\Sales;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\HasBranchContext;

class AttributeValue extends Model
{
    use HasFactory, SoftDeletes, HasBranchContext;

    protected $connection = 'tenant';

    protected $fillable = [
        'branch_id',
        'attribute_id',
        'value',
        'value_ar',
        'color_code',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $hidden = ['pivot'];

    /**
     * Get the attribute this value belongs to
     */
    public function attribute()
    {
        return $this->belongsTo(Attribute::class);
    }

    /**
     * Get all product variants using this attribute value
     */
    public function productVariants()
    {
        return $this->belongsToMany(
            ProductVariant::class,
            'product_variant_attribute_value',
            'attribute_value_id',
            'product_variant_id'
        )->withTimestamps();
    }

    /**
     * Check if attribute value is active
     */
    public function isActive(): bool
    {
        return $this->is_active === true && $this->attribute->is_active === true;
    }

    /**
     * Check if this is a color attribute
     */
    public function isColor(): bool
    {
        return !is_null($this->color_code);
    }

    /**
     * Get display name (with fallback)
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->value;
    }

    /**
     * Get full name with attribute
     */
    public function getFullNameAttribute(): string
    {
        return $this->attribute->name . ': ' . $this->value;
    }

    /**
     * Get variants count using this value
     */
    public function getVariantsCountAttribute(): int
    {
        return $this->productVariants()->count();
    }

    /**
     * Scope: Active values
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: For specific attribute
     */
    public function scopeForAttribute($query, $attributeId)
    {
        return $query->where('attribute_id', $attributeId);
    }

    /**
     * Scope: Color attributes only
     */
    public function scopeColors($query)
    {
        return $query->whereNotNull('color_code');
    }

    /**
     * Scope: Search
     */
    public function scopeSearch($query, $search)
    {
        return $query->where(function($q) use ($search) {
            $q->where('value', 'like', "%{$search}%")
              ->orWhere('value_ar', 'like', "%{$search}%");
        });
    }
}
