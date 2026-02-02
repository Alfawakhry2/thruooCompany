<?php

namespace App\Models\Modules;

use App\Models\User;
use App\Models\Modules\Sales\Lead;
use Illuminate\Database\Eloquent\Model;
use App\Models\Modules\Sales\LeadSource;
use App\Models\Modules\Sales\LeadStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Module extends Model
{
    use HasFactory;

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
        'status',
        'subscription_start',
        'trial_end',
    ];

    /**
     * The attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'subscription_start' => 'datetime',
            'trial_end' => 'datetime',
        ];
    }

    /**
     * Check if module is active
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if module is on trial
     */
    public function isOnTrial(): bool
    {
        return $this->trial_end && $this->trial_end->isFuture();
    }

    /**
     * Get all leads for this module
     */
    public function leads()
    {
        return $this->hasMany(Lead::class);
    }

    /**
     * Get lead sources for this module
     */
    public function leadSources()
    {
        return $this->hasMany(LeadSource::class);
    }

    /**
     * Get lead statuses for this module
     */
    public function leadStatuses()
    {
        return $this->hasMany(LeadStatus::class);
    }
}
