<?php

namespace App\Traits;

use App\Models\Modules\Sales\Branch;

trait HasBranchContext
{
    /**
     * Boot the trait
     */
    protected static function bootHasBranchContext()
    {
        // Auto-set branch_id on create if not provided
        static::creating(function ($model) {
            if (!$model->branch_id && $branchId = currentBranchId()) {
                $model->branch_id = $branchId;
            }
        });
    }

    /**
     * Get the branch this model belongs to
     */
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Scope: Filter by branch
     */
    public function scopeForBranch($query, $branchId)
    {
        return $query->where('branch_id', $branchId);
    }

    /**
     * Scope: Company-wide items (no branch)
     */
    public function scopeCompanyWide($query)
    {
        return $query->whereNull('branch_id');
    }

    /**
     * Scope: Branch-specific or company-wide
     */
    public function scopeAvailableForBranch($query, $branchId)
    {
        return $query->where(function ($q) use ($branchId) {
            $q->where('branch_id', $branchId)
              ->orWhereNull('branch_id');
        });
    }
}