<?php

namespace App\Models\Modules\Sales;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\User;
use App\Models\Modules\Module;
use App\Traits\HasBranchContext;

class Vendor extends Model
{
    use HasFactory, SoftDeletes, HasBranchContext;

    protected $connection = 'tenant';

    protected $fillable = [
        'branch_id',
        'name',
        'name_ar',
        'email',
        'phone',
        'company_name',
        'address',
        'tax_number',
        'contact_person',
        'contact_phone',
        'contact_email',
        'module_id',
        'created_by',
        'status',
        'notes',
        'settings',
    ];

    protected $casts = [
        'settings' => 'array',
        'status' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $hidden = ['pivot'];

    /**
     * Get the module this vendor belongs to
     */
    public function module()
    {
        return $this->belongsTo(Module::class);
    }

    /**
     * Get the user who created this vendor
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get all products from this vendor
     */
    public function products()
    {
        return $this->belongsToMany(Product::class, 'product_vendor', 'vendor_id', 'product_id')
            ->withPivot('vendor_price', 'is_primary')
            ->withTimestamps();
    }

    /**
     * Get products where this is the primary vendor
     */
    public function primaryProducts()
    {
        return $this->products()->wherePivot('is_primary', true);
    }

    /**
     * Check if vendor is active
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Get total products count
     */
    public function getTotalProductsAttribute(): int
    {
        return $this->products()->count();
    }

    /**
     * Get primary products count
     */
    public function getPrimaryProductsCountAttribute(): int
    {
        return $this->primaryProducts()->count();
    }

    /**
     * Scope: Active vendors
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope: For specific module
     */
    public function scopeForModule($query, $moduleId)
    {
        return $query->where('module_id', $moduleId);
    }

    /**
     * Scope: Search
     */
    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
                ->orWhere('name_ar', 'like', "%{$search}%")
                ->orWhere('company_name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")
                ->orWhere('tax_number', 'like', "%{$search}%");
        });
    }
}
