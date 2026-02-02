<?php

namespace App\Models\Modules\Sales;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Traits\HasBranchContext;

class LeadSource extends Model
{
    use HasFactory , HasBranchContext;

    /**
     * The connection name for the model.
     */
    protected $connection = 'tenant';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'branch_id',
        'name',
        'name_ar',
        'description',
        'status',
        'created_by',
    ];

    protected $hidden = [
        'created_at',
        'updated_at'
    ];
    /**
     * Check if source is active
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Get the user who created this source
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get all leads with this source
     */
    public function leads()
    {
        return $this->hasMany(Lead::class, 'source_id');
    }

    /**
     * Scope to get only active sources
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
