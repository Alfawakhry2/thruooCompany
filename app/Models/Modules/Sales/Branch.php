<?php

namespace App\Models\Modules\Sales;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Branch extends Model
{
    use HasFactory, SoftDeletes ;

    /**
     * The connection name for the model.
     */
    protected $connection = 'tenant';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'name_ar',
        'description',
        'is_default',
        'is_active',
        'address',
        'phone',
        'email',
        'created_by',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected $hidden = [
        'created_at',
        'updated_at'
    ];

    /**
     * Check if branch is active
     */
    public function isActive(): bool
    {
        return $this->is_active;
    }

    /**
     * Check if branch is the default branch
     */
    public function isDefault(): bool
    {
        return $this->is_default;
    }

    /**
     * Get the user who created this branch
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get users belonging to this branch (many-to-many via branch_user)
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'branch_user')
            ->withTimestamps();
    }

    /**
     * Get all leads for this branch
     */
    public function leads()
    {
        return $this->hasMany(Lead::class);
    }

    /**
     * Get all contracts for this branch
     */
    public function contracts()
    {
        return $this->hasMany(Contract::class);
    }

    /**
     * Get all teams for this branch
     */
    public function teams()
    {
        return $this->hasMany(Team::class);
    }

    /**
     * Scope to get only active branches
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get default branch
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }
}
