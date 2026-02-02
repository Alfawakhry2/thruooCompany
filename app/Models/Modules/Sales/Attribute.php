<?php

namespace App\Models\Modules\Sales;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\User;
use App\Models\Modules\Module;
use App\Traits\HasBranchContext;

class Attribute extends Model
{
    use HasFactory, SoftDeletes, HasBranchContext;

    protected $connection = 'tenant';

    protected $fillable = [
        'branch_id',
        'name',
        'name_ar',
        'module_id',
        'created_by',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the module this attribute belongs to
     */
    public function module()
    {
        return $this->belongsTo(Module::class);
    }

    /**
     * Get the user who created this attribute
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get all values for this attribute
     */
    public function values()
    {
        return $this->hasMany(AttributeValue::class);
    }

    /**
     * Get only active values
     */
    public function activeValues()
    {
        return $this->values()->where('is_active', true);
    }

    /**
     * Check if attribute is active
     */
    public function isActive(): bool
    {
        return $this->is_active === true;
    }

    /**
     * Check if attribute has values
     */
    public function hasValues(): bool
    {
        return $this->values()->count() > 0;
    }

    /**
     * Get total values count
     */
    public function getValuesCountAttribute(): int
    {
        return $this->values()->count();
    }

    /**
     * Get active values count
     */
    public function getActiveValuesCountAttribute(): int
    {
        return $this->activeValues()->count();
    }

    /**
     * Scope: Active attributes
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: With active values
     */
    public function scopeWithActiveValues($query)
    {
        return $query->with(['values' => function($q) {
            $q->where('is_active', true);
        }]);
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
        return $query->where(function($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('name_ar', 'like', "%{$search}%");
        });
    }
}
