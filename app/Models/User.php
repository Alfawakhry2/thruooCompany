<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use App\Models\Modules\Sales\Branch;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens, HasRoles, SoftDeletes;

    /**
     * The connection name for the model.
     * Uses tenant connection when tenant is resolved
     */
    protected $connection = 'tenant';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        // Basic Info
        'name',
        'email',
        'password',
        'phone',

        // Profile Info
        'title',
        'birth_year',
        'avatar',

        // Discovery Source
        'how_know_us',

        // Status
        'status',
        'is_owner',
        'profile_completed',

        // Invitation Info
        'invitation_token',
        'invited_at',
        'invited_by',

        // Settings
        'timezone',
        'locale',
        'preferences',

        'email_verified_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'password',
        'remember_token',
        'invitation_token',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'invited_at' => 'datetime',
            'password' => 'hashed',
            'how_know_us' => 'array',
            'preferences' => 'array',
            'is_owner' => 'boolean',
            'profile_completed' => 'boolean',
        ];
    }

    /**
     * Status constants
     */
    const STATUS_PENDING = 'pending';
    const STATUS_ACTIVE = 'active';
    const STATUS_SUSPENDED = 'suspended';

    /**
     * Get available "how know us" options
     */
    public static function howKnowUsOptions(): array
    {
        return [
            'google' => 'Google Search',
            'facebook' => 'Facebook',
            'twitter' => 'Twitter/X',
            'linkedin' => 'LinkedIn',
            'instagram' => 'Instagram',
            'youtube' => 'YouTube',
            'friend' => 'Friend/Colleague',
            'blog' => 'Blog/Article',
            'event' => 'Event/Conference',
            'podcast' => 'Podcast',
            'advertisement' => 'Advertisement',
            'other' => 'Other',
        ];
    }

    /**
     * Get available title options
     */
    public static function titleOptions(): array
    {
        return [
            'ceo' => 'CEO/Owner',
            'cto' => 'CTO',
            'cfo' => 'CFO',
            'coo' => 'COO',
            'director' => 'Director',
            'manager' => 'Manager',
            'team_lead' => 'Team Lead',
            'senior' => 'Senior Employee',
            'employee' => 'Employee',
            'consultant' => 'Consultant',
            'freelancer' => 'Freelancer',
            'intern' => 'Intern',
            'other' => 'Other',
        ];
    }

    /**
     * Check if user is owner
     */
    public function isOwner(): bool
    {
        return $this->is_owner;
    }

    /**
     * Check if user is active
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Check if user is pending (invited but not activated)
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if profile is completed
     */
    public function hasCompletedProfile(): bool
    {
        return $this->profile_completed;
    }

    /**
     * Generate invitation token
     */
    public function generateInvitationToken(): string
    {
        $token = Str::random(64);
        $this->update([
            'invitation_token' => $token,
            'invited_at' => now(),
        ]);
        return $token;
    }

    /**
     * Accept invitation and set password
     */
    public function acceptInvitation(string $password): void
    {
        $this->update([
            'password' => bcrypt($password),
            'invitation_token' => null,
            'status' => self::STATUS_ACTIVE,
            'email_verified_at' => now(),
        ]);
    }

    /**
     * Check if invitation is valid (not expired - 7 days)
     */
    public function hasValidInvitation(): bool
    {
        if (!$this->invitation_token || !$this->invited_at) {
            return false;
        }

        return $this->invited_at->addDays(7)->isFuture();
    }

    /**
     * Activate user
     */
    public function activate(): void
    {
        $this->update(['status' => self::STATUS_ACTIVE]);
    }

    /**
     * Suspend user
     */
    public function suspend(): void
    {
        $this->update(['status' => self::STATUS_SUSPENDED]);
    }

    /**
     * Mark profile as completed
     */
    public function markProfileCompleted(): void
    {
        $this->update(['profile_completed' => true]);
    }

    /**
     * Get the inviter
     */
    public function inviter()
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    /**
     * Get users invited by this user
     */
    public function invitedUsers()
    {
        return $this->hasMany(User::class, 'invited_by');
    }

    /**
     * Get branches this user belongs to (many-to-many via branch_user)
     */
    public function branches()
    {
        return $this->belongsToMany(Branch::class, 'branch_user')
            ->withTimestamps();
    }

    /**
     * Get the user's default branch (first branch or the one marked as default)
     */
    public function defaultBranch()
    {
        return $this->branches()->where('is_default', true)->first()
            ?? $this->branches()->first();
    }

    /**
     * Check if user belongs to a specific branch
     */
    public function belongsToBranch($branchId): bool
    {
        return $this->branches()->where('branches.id', $branchId)->exists();
    }

    /**
     * Get full name with title
     */
    public function getFullNameWithTitleAttribute(): string
    {
        if ($this->title) {
            $titles = self::titleOptions();
            $titleLabel = $titles[$this->title] ?? $this->title;
            return "{$this->name} ({$titleLabel})";
        }
        return $this->name;
    }

    /**
     * Get avatar URL
     */
    public function getAvatarUrlAttribute(): ?string
    {
        if ($this->avatar) {
            // If it's already a URL, return as is
            if (filter_var($this->avatar, FILTER_VALIDATE_URL)) {
                return $this->avatar;
            }
            // Otherwise, generate storage URL
            return asset('storage/' . $this->avatar);
        }
        return null;
    }
}
