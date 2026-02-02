<?php

namespace App\Models\Modules\Sales;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\User;
use App\Models\Modules\Module;
use App\Traits\HasBranchContext;

class PaymentMethod extends Model
{
    use HasFactory, SoftDeletes, HasBranchContext;

    protected $connection = 'tenant';

    protected $fillable = [
        'branch_id',
        'type',
        'bank_name',
        'account_number',
        'account_holder',
        'iban',
        'swift_code',
        'name',
        'name_ar',
        'description',
        'module_id',
        'created_by',
        'is_active',
        'is_default',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the module this payment method belongs to
     */
    public function module()
    {
        return $this->belongsTo(Module::class);
    }

    /**
     * Get the user who created this payment method
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Check if payment method is active
     */
    public function isActive(): bool
    {
        return $this->is_active === true;
    }

    /**
     * Check if this is the default payment method
     */
    public function isDefault(): bool
    {
        return $this->is_default === true;
    }

    /**
     * Check if this is a bank transfer
     */
    public function isBankTransfer(): bool
    {
        return $this->type === 'bank_transfer';
    }

    /**
     * Get display name
     */
    public function getDisplayNameAttribute(): string
    {
        if ($this->isBankTransfer() && $this->bank_name) {
            return $this->bank_name . ' - ' . $this->account_number;
        }
        return $this->name;
    }

    /**
     * Get masked account number (for security)
     */
    public function getMaskedAccountNumberAttribute(): ?string
    {
        if (!$this->account_number) {
            return null;
        }

        $length = strlen($this->account_number);
        if ($length <= 4) {
            return $this->account_number;
        }

        return str_repeat('*', $length - 4) . substr($this->account_number, -4);
    }

    /**
     * Scope: Active payment methods
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Default payment method
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    /**
     * Scope: By type
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope: For specific module
     */
    public function scopeForModule($query, $moduleId)
    {
        return $query->where('module_id', $moduleId);
    }

    /**
     * Boot method - ensure only one default per module
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($paymentMethod) {
            // If setting as default, unset other defaults in same module
            if ($paymentMethod->is_default) {
                static::where('module_id', $paymentMethod->module_id)
                    ->where('id', '!=', $paymentMethod->id)
                    ->update(['is_default' => false]);
            }
        });
    }
}
