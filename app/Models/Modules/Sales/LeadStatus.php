<?php

namespace App\Models\Modules\Sales;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Traits\HasBranchContext;

class LeadStatus extends Model
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
        'order',
        'color',
        'status',
        'created_by',
    ];
    protected $hidden = [
        'created_at',
        'updated_at'
    ];
    /**
     * The attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'order' => 'integer',
        ];
    }

    /**
     * Check if status is active
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Get the user who created this status
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get all leads with this status
     */
    public function leads()
    {
        return $this->hasMany(Lead::class, 'status_id');
    }

    /**
     * Scope to get only active statuses
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope to order by custom order
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('order', 'asc');
    }
}
