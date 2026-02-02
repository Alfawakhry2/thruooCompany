<?php

namespace App\Models\Modules\Sales;

use App\Traits\HasBranchContext;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\Modules\Module;
use App\Models\Modules\Sales\Lead;

class Target extends Model
{
    use HasFactory , HasBranchContext;

    protected $connection = 'tenant';

    protected $fillable = [
        'branch_id',
        'target_type',
        'target_value',
        'target_name',
        'description',
        'user_id',
        'team_id',
        'role_name',
        'start_date',
        'end_date',
        'module_id',
        'created_by',
        'status',
        'achieved_value',
        'progress_percentage',
    ];

    protected $casts = [
        'target_value' => 'decimal:2',
        'achieved_value' => 'decimal:2',
        'progress_percentage' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the user this target is assigned to
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

   public function team()
   {
       return $this->belongsTo(Team::class);
   }

    /**
     * Get the user who created this target
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the module this target belongs to
     */
    public function module()
    {
        return $this->belongsTo(Module::class);
    }

// Update isUserTarget method:
/**
 * Check if target is for a specific user
 */
public function isUserTarget(): bool
{
    return !is_null($this->user_id) && is_null($this->team_id) && is_null($this->role_name);
}
    /**
     * Check if target is for a role (all users with that role)
     */
    public function isRoleTarget(): bool
    {
        return !is_null($this->role_name);
    }

    /**
     * Check if target is active
     */
    public function isActive(): bool
    {
        return $this->status === 'active'
            && $this->start_date <= now()
            && $this->end_date >= now();
    }

    /**
     * Check if target is expired
     */
    public function isExpired(): bool
    {
        return $this->end_date < now();
    }

    /**
     * Calculate and update progress
     */
    public function updateProgress(): void
    {
        $query = Lead::where('module_id', $this->module_id)
            ->where('is_converted', true)
            ->whereBetween('converted_at', [$this->start_date, $this->end_date]);

        // If user-specific target
        if ($this->isUserTarget()) {
            $query->where(function($q) {
                $q->where('assigned_to', $this->user_id)
                  ->orWhere('created_by', $this->user_id);
            });
        }
        // If team target
        elseif ($this->isTeamTarget()) {
            $team = $this->team;
            if ($team) {
                $memberIds = $team->members()->pluck('users.id');
                $query->whereIn('assigned_to', $memberIds);
            }
        }
        // If role-based target
        elseif ($this->isRoleTarget()) {
            $userIds = User::role($this->role_name)->pluck('id');
            $query->whereIn('assigned_to', $userIds);
        }

        $achievedValue = $query->sum('value') ?? 0;
        $progressPercentage = $this->target_value > 0
            ? ($achievedValue / $this->target_value) * 100
            : 0;

        $this->update([
            'achieved_value' => $achievedValue,
            'progress_percentage' => min($progressPercentage, 100),
        ]);

        // Auto-update status
        if ($this->isExpired() && $this->status === 'active') {
            $this->update(['status' => 'expired']);
        }
        if ($progressPercentage >= 100 && $this->status === 'active') {
            $this->update(['status' => 'completed']);
        }
    }

    /**
     * Scope: Active targets
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active')
            ->where('start_date', '<=', now())
            ->where('end_date', '>=', now());
    }
/**
 * Scope: For specific team
 */
public function scopeForTeam($query, $teamId)
{
    return $query->where('team_id', $teamId);
}
    /**
     * Scope: For specific user
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope: For specific role
     */
    public function scopeForRole($query, $roleName)
    {
        return $query->where('role_name', $roleName);
    }

    /**
     * Scope: For specific module
     */
    public function scopeForModule($query, $moduleId)
    {
        return $query->where('module_id', $moduleId);
    }

    /**
     * Get target type label
     */
    public function getTypeLabel(): string
    {
        return match($this->target_type) {
            'monthly' => 'Monthly Target',
            'quarterly' => 'Quarterly Target',
            'yearly' => 'Yearly Target',
            'custom' => 'Custom Period Target',
            default => 'Target',
        };
    }

    /**
     * Get remaining days
     */
    public function getRemainingDays(): int
    {
        return max(0, now()->diffInDays($this->end_date, false));
    }

    /**
     * Get status color
     */
    public function getStatusColor(): string
    {
        return match($this->status) {
            'active' => '#3B82F6', // Blue
            'completed' => '#22C55E', // Green
            'expired' => '#EF4444', // Red
            default => '#6B7280', // Gray
        };
    }
}
