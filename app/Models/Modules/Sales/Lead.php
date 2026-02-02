<?php

namespace App\Models\Modules\Sales;

use App\Models\User;
use App\Models\Modules\Module;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\HasBranchContext;

class Lead extends Model
{
    use HasFactory, SoftDeletes , HasBranchContext;

    protected $connection = 'tenant';

    protected $fillable = [
        // Basic Info
        'name',
        'email',
        'phone',
        'position',

        // Company Info
        'company',
        'company_phone',
        'company_email',
        'website',
        'address',

        // Lead Details
        'ask',
        'service',
        'description',
        'value',

        // Campaign & Source
        'campaign_id',
        'source_id',

        // Status & Stage
        'status_id',
        'priority',

        // Assignment & Branch
        'assigned_to',
        'created_by',
        'module_id',
        'branch_id',

        // Social Media
        'instagram',
        'facebook',
        'tiktok',
        'snapchat',
        'linkedin',
        'youtube',

        // Conversion
        'is_converted',
        'converted_at',

        // Tracking
        'first_contact_at',
        'last_contact_at',
        'next_followup_at',

        // Custom
        'custom_fields',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'is_converted' => 'boolean',
        'converted_at' => 'datetime',
        'first_contact_at' => 'datetime',
        'last_contact_at' => 'datetime',
        'next_followup_at' => 'datetime',
        'custom_fields' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the source of the lead
     */
    public function source()
    {
        return $this->belongsTo(LeadSource::class, 'source_id');
    }

    /**
     * Get the status/stage of the lead
     */
    public function status()
    {
        return $this->belongsTo(LeadStatus::class, 'status_id');
    }

    /**
     * Get the user assigned to this lead (Sales Name)
     */
    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Get the user who created this lead
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the module this lead belongs to
     */
    public function module()
    {
        return $this->belongsTo(Module::class);
    }

    /**
     * Get the branch this lead belongs to
     */
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Get all contracts for this lead
     */
    public function contracts()
    {
        return $this->hasMany(\App\Models\Modules\Sales\Contract::class);
    }

    /**
     * Get all activity logs for this lead
     */
    public function activityLogs()
    {
        return $this->hasMany(\App\Models\Modules\Sales\ActivityLog::class);
    }

    /**
     * Scope: Filter by branch
     */
    public function scopeForBranch($query, $branchId)
    {
        return $query->where('branch_id', $branchId);
    }

    /**
     * Scope: Filter by status
     */
    public function scopeByStatus($query, $statusId)
    {
        return $query->where('status_id', $statusId);
    }

    /**
     * Scope: Filter by source
     */
    public function scopeBySource($query, $sourceId)
    {
        return $query->where('source_id', $sourceId);
    }

    /**
     * Scope: Filter by assigned user
     */
    public function scopeAssignedTo($query, $userId)
    {
        return $query->where('assigned_to', $userId);
    }

    /**
     * Scope: Filter by priority
     */
    public function scopeByPriority($query, $priority)
    {
        return $query->where('priority', $priority);
    }

    /**
     * Scope: Only converted leads
     */
    public function scopeConverted($query)
    {
        return $query->where('is_converted', true);
    }

    /**
     * Scope: Only unconverted leads
     */
    public function scopeUnconverted($query)
    {
        return $query->where('is_converted', false);
    }

    /**
     * Scope: Search leads
     */
    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")
                ->orWhere('company', 'like', "%{$search}%")
                ->orWhere('position', 'like', "%{$search}%")
                ->orWhere('ask', 'like', "%{$search}%")
                ->orWhere('campaign_id', 'like', "%{$search}%");
        });
    }

    /**
     * Mark lead as converted
     */
    public function markAsConverted(): void
    {
        $this->update([
            'is_converted' => true,
            'converted_at' => now(),
        ]);
    }

    /**
     * Mark lead as contacted
     */
    public function markAsContacted(): void
    {
        if (!$this->first_contact_at) {
            $this->update(['first_contact_at' => now()]);
        }
        $this->update(['last_contact_at' => now()]);
    }

    /**
     * Get "Since" date (when lead was created)
     */
    public function getSinceAttribute()
    {
        return $this->created_at->format('M d, Y');
    }

    /**
     * Get stage name (status name)
     */
    public function getStageAttribute()
    {
        return $this->status?->name ?? 'No Stage';
    }

    /**
     * Get sales person name
     */
    public function getSalesNameAttribute()
    {
        return $this->assignedUser?->name ?? 'Unassigned';
    }
}
