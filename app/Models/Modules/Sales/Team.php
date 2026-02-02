<?php

namespace App\Models\Modules\Sales;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\Modules\Module;
use App\Models\Modules\Sales\Lead;
use App\Traits\HasBranchContext;

class Team extends Model
{
    use HasFactory , HasBranchContext;

    protected $connection = 'tenant';

    protected $fillable = [
        'branch_id',
        'name',
        'description',
        'team_lead_id',
        'module_id',
        'created_by',
        'status',
        'settings',
    ];

    protected $casts = [
        'settings' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the team leader
     */
    public function leader()
    {
        return $this->belongsTo(User::class, 'team_lead_id');
    }

    /**
     * Get the user who created this team
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the module this team belongs to
     */
    public function module()
    {
        return $this->belongsTo(Module::class);
    }

    /**
     * Get all team members (many-to-many)
     */
    public function members()
    {
        return $this->belongsToMany(User::class, 'team_members', 'team_id', 'user_id')
            ->withPivot('module_id')
            ->withTimestamps();
    }

    /**
     * Get all targets assigned to this team
     */
    public function targets()
    {
        return $this->hasMany(Target::class);
    }

    /**
     * Get active targets for this team
     */
    public function activeTargets()
    {
        return $this->targets()->active();
    }

    /**
     * Check if team is active
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Get all leads assigned to team members
     */
    public function leads()
    {
        $memberIds = $this->members()->pluck('users.id');

        return Lead::where('module_id', $this->module_id)
            ->whereIn('assigned_to', $memberIds);
    }

    /**
     * Get total converted leads value for team
     */
    public function getTotalConvertedValue($startDate = null, $endDate = null)
    {
        $query = $this->leads()
            ->where('is_converted', true);

        if ($startDate) {
            $query->where('converted_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('converted_at', '<=', $endDate);
        }

        return $query->sum('value') ?? 0;
    }

    /**
     * Get total number of converted leads for team
     */
    public function getTotalConvertedLeads($startDate = null, $endDate = null)
    {
        $query = $this->leads()
            ->where('is_converted', true);

        if ($startDate) {
            $query->where('converted_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('converted_at', '<=', $endDate);
        }

        return $query->count();
    }

    /**
     * Get team performance for current month
     */
    public function getCurrentMonthPerformance()
    {
        $startOfMonth = now()->startOfMonth();
        $endOfMonth = now()->endOfMonth();

        return [
            'converted_value' => $this->getTotalConvertedValue($startOfMonth, $endOfMonth),
            'converted_count' => $this->getTotalConvertedLeads($startOfMonth, $endOfMonth),
            'period' => [
                'start' => $startOfMonth->format('Y-m-d'),
                'end' => $endOfMonth->format('Y-m-d'),
            ],
        ];
    }

    /**
     * Add member to team
     */
    public function addMember(int $userId): bool
    {
        if ($this->members()->where('user_id', $userId)->exists()) {
            return false; // Already a member
        }

        $this->members()->attach($userId, [
            'module_id' => $this->module_id,
        ]);

        return true;
    }

    /**
     * Remove member from team
     */
    public function removeMember(int $userId): bool
    {
        return $this->members()->detach($userId) > 0;
    }

    /**
     * Check if user is team member
     */
    public function hasMember(int $userId): bool
    {
        return $this->members()->where('user_id', $userId)->exists();
    }

    /**
     * Check if user is team leader
     */
    public function isLeader(int $userId): bool
    {
        return $this->team_lead_id === $userId;
    }

    /**
     * Scope: Active teams
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
     * Scope: Led by specific user
     */
    public function scopeLedBy($query, $userId)
    {
        return $query->where('team_lead_id', $userId);
    }
}
