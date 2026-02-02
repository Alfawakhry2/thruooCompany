<?php

namespace App\Models\Modules\Sales;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\User;
use App\Models\Modules\Module;
use App\Traits\HasBranchContext;

class Currency extends Model
{
    use HasFactory, SoftDeletes , HasBranchContext  ;

    protected $connection = 'tenant';

    protected $fillable = [
        'branch_id',
        'name',
        'name_ar',
        'code',
        'symbol',
        'exchange_rate',
        'module_id',
        'created_by',
        'is_active',
        'is_base',
    ];

    protected $casts = [
        'exchange_rate' => 'decimal:8',
        'is_active' => 'boolean',
        'is_base' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the module this currency belongs to
     */
    public function module()
    {
        return $this->belongsTo(Module::class);
    }

    /**
     * Get the user who created this currency
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get all products using this currency
     */
    public function products()
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Check if currency is active
     */
    public function isActive(): bool
    {
        return $this->is_active === true;
    }

    /**
     * Check if this is the base currency
     */
    public function isBase(): bool
    {
        return $this->is_base === true;
    }

    /**
     * Convert amount from this currency to base currency
     */
    public function toBase(float $amount): float
    {
        if ($this->isBase()) {
            return $amount;
        }
        return $amount / $this->exchange_rate;
    }

    /**
     * Convert amount from base currency to this currency
     */
    public function fromBase(float $amount): float
    {
        if ($this->isBase()) {
            return $amount;
        }
        return $amount * $this->exchange_rate;
    }

    /**
     * Convert amount from one currency to another
     */
    public static function convert(float $amount, Currency $from, Currency $to): float
    {
        if ($from->id === $to->id) {
            return $amount;
        }

        // Convert to base currency first
        $baseAmount = $from->toBase($amount);

        // Then convert to target currency
        return $to->fromBase($baseAmount);
    }

    /**
     * Format amount with currency symbol
     */
    public function format(float $amount, int $decimals = 2): string
    {
        return $this->symbol . ' ' . number_format($amount, $decimals);
    }

    /**
     * Scope: Active currencies
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Base currency
     */
    public function scopeBase($query)
    {
        return $query->where('is_base', true);
    }

    /**
     * Scope: For specific module
     */
    public function scopeForModule($query, $moduleId)
    {
        return $query->where('module_id', $moduleId);
    }

    /**
     * Boot method - ensure only one base currency per module
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($currency) {
            // If setting as base currency, unset other base currencies in same module
            if ($currency->is_base) {
                static::where('module_id', $currency->module_id)
                    ->where('id', '!=', $currency->id)
                    ->update(['is_base' => false]);
            }
        });
    }
}
