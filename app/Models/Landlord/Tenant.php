<?php

namespace App\Models\Landlord;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Tenant Model - OWNER/ACCOUNT (Updated Version)
 *
 * IMPORTANT ARCHITECTURE NOTE:
 * - Tenant = Owner/Account (ONE person who can own MULTIPLE companies)
 * - Company = Actual multi-tenant entity with its own database
 * - This is NOT the "tenant" in multitenancy sense
 *
 * Example:
 * - Ahmed Hassan (Tenant) owns:
 *   ├── Ahmed Tech Solutions (Company 1)
 *   ├── Ahmed Consulting (Company 2)
 *   └── Ahmed Trading (Company 3)
 *
 * UPDATED FEATURES:
 * - Enhanced metadata handling
 * - Personal information helpers
 * - Company management methods
 * - Statistics and insights
 * - Industry and staff count options
 * - Query scopes
 */
class Tenant extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    /**
     * The connection name for the model (Landlord database)
     */
    protected $connection = 'mysql';

    /**
     * The table associated with the model
     */
    protected $table = 'tenants';

    /**
     * The attributes that are mass assignable
     */
    protected $fillable = [
        // Basic Info
        'name',
        'email',
        'phone',

        // Address
        'country',
        'city',
        'address',

        // Referral Info
        'referral_code',
        'referral_relation',

        // Settings & Meta
        'settings',
        'metadata',
    ];

    /**
     * Get the attributes that should be cast
     */
    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'metadata' => 'array',
        ];
    }

    // ============================================================================
    // RELATIONSHIPS
    // ============================================================================

    /**
     * Get all companies owned by this tenant
     */
    public function companies()
    {
        return $this->hasMany(Company::class, 'tenant_id');
    }

    /**
     * Get active companies only
     */
    public function activeCompanies()
    {
        return $this->companies()->where('status', Company::STATUS_ACTIVE);
    }

    /**
     * Get pending companies (awaiting setup completion)
     */
    public function pendingCompanies()
    {
        return $this->companies()->where('status', Company::STATUS_PENDING);
    }

    /**
     * Get suspended companies
     */
    public function suspendedCompanies()
    {
        return $this->companies()->where('status', Company::STATUS_SUSPENDED);
    }

    /**
     * Get companies on trial
     */
    public function trialCompanies()
    {
        return $this->companies()
            ->where('plan', Company::PLAN_TRIAL)
            ->where('trial_ends_at', '>', now());
    }

    /**
     * Get companies with specific module enabled
     */
    public function companiesWithModule(string $module)
    {
        return $this->companies()->whereJsonContains('enabled_modules', $module);
    }

    /**
     * Get companies with details for selection/switching
     */
    public function companiesForSelection()
    {
        return $this->companies()
            ->select('id', 'name', 'subdomain', 'domain', 'logo', 'status', 'plan', 'trial_ends_at')
            ->whereIn('status', [Company::STATUS_ACTIVE, Company::STATUS_PENDING])
            ->get()
            ->map(function ($company) {
                return [
                    'id' => $company->id,
                    'name' => $company->name,
                    'subdomain' => $company->subdomain,
                    'domain' => $company->full_domain,
                    'url' => $company->url,
                    'logo_url' => $company->logo_url,
                    'initials' => $company->initials,
                    'status' => $company->status,
                    'plan' => $company->plan,
                    'is_on_trial' => $company->isOnTrial(),
                    'trial_ends_at' => $company->trial_ends_at?->toDateString(),
                    'remaining_trial_days' => $company->getRemainingTrialDays(),
                ];
            });
    }

    // ============================================================================
    // PERSONAL INFO ACCESSORS (from metadata)
    // ============================================================================

    /**
     * Get job title from metadata
     */
    public function getTitleAttribute(): ?string
    {
        return $this->metadata['title'] ?? null;
    }

    /**
     * Get birth year from metadata
     */
    public function getBirthYearAttribute(): ?int
    {
        return $this->metadata['birth_year'] ?? null;
    }

    /**
     * Get how they know us from metadata
     */
    public function getHowKnowUsAttribute(): ?array
    {
        return $this->metadata['how_know_us'] ?? null;
    }

    /**
     * Get age based on birth year
     */
    public function getAgeAttribute(): ?int
    {
        if (!$this->birth_year) {
            return null;
        }

        return (int) (date('Y') - $this->birth_year);
    }

    /**
     * Get full name with title
     */
    public function getFullNameWithTitleAttribute(): string
    {
        if ($this->title) {
            return "{$this->name} ({$this->title})";
        }
        return $this->name;
    }

    /**
     * Get initials for avatar
     */
    public function getInitialsAttribute(): string
    {
        $words = explode(' ', $this->name);

        if (count($words) >= 2) {
            return strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1));
        }

        return strtoupper(substr($this->name, 0, 2));
    }

    // ============================================================================
    // COMPANY MANAGEMENT METHODS
    // ============================================================================

    /**
     * Check if tenant has any companies
     */
    public function hasCompanies(): bool
    {
        return $this->companies()->exists();
    }

    /**
     * Check if tenant has any active companies
     */
    public function hasActiveCompanies(): bool
    {
        return $this->activeCompanies()->exists();
    }

    /**
     * Get total companies count
     */
    public function companiesCount(): int
    {
        return $this->companies()->count();
    }

    /**
     * Get active companies count
     */
    public function activeCompaniesCount(): int
    {
        return $this->activeCompanies()->count();
    }

    /**
     * Find company by subdomain
     */
    public function findCompanyBySubdomain(string $subdomain): ?Company
    {
        return $this->companies()->where('subdomain', $subdomain)->first();
    }

    /**
     * Find company by ID
     */
    public function findCompany(string $companyId): ?Company
    {
        return $this->companies()->where('id', $companyId)->first();
    }

    /**
     * Get first active company (for auto-login if only one company)
     */
    public function getFirstActiveCompany(): ?Company
    {
        return $this->activeCompanies()->first();
    }

    /**
     * Check if tenant owns a specific company
     */
    public function ownsCompany(string $companyId): bool
    {
        return $this->companies()->where('id', $companyId)->exists();
    }

    /**
     * Check if tenant owns company by subdomain
     */
    public function ownsSubdomain(string $subdomain): bool
    {
        return $this->companies()->where('subdomain', $subdomain)->exists();
    }

    // ============================================================================
    // STATISTICS & INSIGHTS
    // ============================================================================

    /**
     * Get tenant statistics
     */
    public function getStatistics(): array
    {
        return [
            'total_companies' => $this->companiesCount(),
            'active_companies' => $this->activeCompaniesCount(),
            'pending_companies' => $this->pendingCompanies()->count(),
            'suspended_companies' => $this->suspendedCompanies()->count(),
            'trial_companies' => $this->trialCompanies()->count(),
            'companies_by_plan' => $this->getCompaniesByPlan(),
            'companies_by_industry' => $this->getCompaniesByIndustry(),
            'total_modules_enabled' => $this->getTotalModulesEnabled(),
        ];
    }

    /**
     * Get companies grouped by plan
     */
    public function getCompaniesByPlan(): array
    {
        $companies = $this->companies()->get();

        return [
            'trial' => $companies->where('plan', Company::PLAN_TRIAL)->count(),
            'starter' => $companies->where('plan', Company::PLAN_STARTER)->count(),
            'professional' => $companies->where('plan', Company::PLAN_PROFESSIONAL)->count(),
            'enterprise' => $companies->where('plan', Company::PLAN_ENTERPRISE)->count(),
        ];
    }

    /**
     * Get companies grouped by industry
     */
    public function getCompaniesByIndustry(): array
    {
        return $this->companies()
            ->selectRaw('industry, COUNT(*) as count')
            ->whereNotNull('industry')
            ->groupBy('industry')
            ->pluck('count', 'industry')
            ->toArray();
    }

    /**
     * Get total modules enabled across all companies
     */
    public function getTotalModulesEnabled(): int
    {
        $companies = $this->companies()->get();
        $totalModules = 0;

        foreach ($companies as $company) {
            if ($company->enabled_modules) {
                $totalModules += count($company->enabled_modules);
            }
        }

        return $totalModules;
    }

    /**
     * Check if any company has specific module enabled
     */
    public function hasModuleAnyCompany(string $module): bool
    {
        return $this->companiesWithModule($module)->exists();
    }

    // ============================================================================
    // REFERRAL METHODS
    // ============================================================================

    /**
     * Check if tenant was referred
     */
    public function wasReferred(): bool
    {
        return !empty($this->referral_code);
    }

    /**
     * Get referral information
     */
    public function getReferralInfo(): ?array
    {
        if (!$this->wasReferred()) {
            return null;
        }

        return [
            'code' => $this->referral_code,
            'relation' => $this->referral_relation,
        ];
    }

    // ============================================================================
    // HELPER METHODS
    // ============================================================================

    /**
     * Get tenant summary for API responses
     */
    public function getSummary(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'initials' => $this->initials,
            'title' => $this->title,
            'country' => $this->country,
            'city' => $this->city,
            'companies_count' => $this->companiesCount(),
            'active_companies_count' => $this->activeCompaniesCount(),
            'created_at' => $this->created_at?->toDateString(),
        ];
    }

    /**
     * Get full tenant details
     */
    public function getFullDetails(): array
    {
        return [
            'id' => $this->id,

            // Basic Info
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,

            // Address
            'country' => $this->country,
            'city' => $this->city,
            'address' => $this->address,

            // Personal Info (from metadata)
            'title' => $this->title,
            'birth_year' => $this->birth_year,
            'age' => $this->age,
            'how_know_us' => $this->how_know_us,
            'initials' => $this->initials,

            // Referral Info
            'referral_code' => $this->referral_code,
            'referral_relation' => $this->referral_relation,
            'was_referred' => $this->wasReferred(),

            // Companies Info
            'companies_count' => $this->companiesCount(),
            'active_companies_count' => $this->activeCompaniesCount(),
            'has_companies' => $this->hasCompanies(),

            // Metadata
            'settings' => $this->settings,
            'metadata' => $this->metadata,

            // Timestamps
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    // ============================================================================
    // STATIC HELPER METHODS
    // ============================================================================

    /**
     * Get industry options for registration
     */
    public static function industryOptions(): array
    {
        return [
            'technology' => 'Technology',
            'healthcare' => 'Healthcare',
            'finance' => 'Finance & Banking',
            'retail' => 'Retail & E-commerce',
            'manufacturing' => 'Manufacturing',
            'education' => 'Education',
            'real_estate' => 'Real Estate',
            'hospitality' => 'Hospitality & Tourism',
            'transportation' => 'Transportation & Logistics',
            'construction' => 'Construction',
            'legal' => 'Legal Services',
            'consulting' => 'Consulting',
            'marketing' => 'Marketing & Advertising',
            'media' => 'Media & Entertainment',
            'agriculture' => 'Agriculture',
            'energy' => 'Energy & Utilities',
            'telecommunications' => 'Telecommunications',
            'automotive' => 'Automotive',
            'insurance' => 'Insurance',
            'other' => 'Other',
        ];
    }

    /**
     * Get staff count options for registration
     */
    public static function staffCountOptions(): array
    {
        return [
            '1-10' => '1-10 employees',
            '11-50' => '11-50 employees',
            '51-200' => '51-200 employees',
            '201-500' => '201-500 employees',
            '500+' => '500+ employees',
        ];
    }

    /**
     * Find tenant by email
     */
    public static function findByEmail(string $email): ?self
    {
        return static::where('email', $email)->first();
    }

    /**
     * Check if email exists
     */
    public static function emailExists(string $email): bool
    {
        return static::where('email', $email)->exists();
    }

    // ============================================================================
    // QUERY SCOPES
    // ============================================================================

    /**
     * Scope: Tenants with active companies
     */
    public function scopeWithActiveCompanies($query)
    {
        return $query->whereHas('companies', function ($q) {
            $q->where('status', Company::STATUS_ACTIVE);
        });
    }

    /**
     * Scope: Tenants with multiple companies
     */
    public function scopeWithMultipleCompanies($query)
    {
        return $query->has('companies', '>=', 2);
    }

    /**
     * Scope: Tenants from specific country
     */
    public function scopeFromCountry($query, string $country)
    {
        return $query->where('country', $country);
    }

    /**
     * Scope: Referred tenants
     */
    public function scopeReferred($query)
    {
        return $query->whereNotNull('referral_code');
    }

    /**
     * Scope: Tenants who joined recently (last N days)
     */
    public function scopeRecentlyJoined($query, int $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    // ============================================================================
    // VALIDATION HELPERS
    // ============================================================================

    /**
     * Validate if tenant can create another company
     *
     * @param string|null $plan Current plan (if any)
     * @return array ['allowed' => bool, 'message' => string, 'limit' => int]
     */
    public function canCreateAnotherCompany(?string $plan = null): array
    {
        $currentCount = $this->companiesCount();

        // Default limits based on plan (you can adjust these)
        $limits = [
            Company::PLAN_TRIAL => 1,
            Company::PLAN_STARTER => 3,
            Company::PLAN_PROFESSIONAL => 10,
            Company::PLAN_ENTERPRISE => 999, // Unlimited
        ];

        // If no plan specified, check the highest plan among tenant's companies
        if (!$plan) {
            $companies = $this->companies()->get();
            $maxPlan = Company::PLAN_TRIAL;

            foreach ($companies as $company) {
                if ($company->plan === Company::PLAN_ENTERPRISE) {
                    $maxPlan = Company::PLAN_ENTERPRISE;
                    break;
                } elseif ($company->plan === Company::PLAN_PROFESSIONAL && $maxPlan !== Company::PLAN_ENTERPRISE) {
                    $maxPlan = Company::PLAN_PROFESSIONAL;
                } elseif ($company->plan === Company::PLAN_STARTER && !in_array($maxPlan, [Company::PLAN_PROFESSIONAL, Company::PLAN_ENTERPRISE])) {
                    $maxPlan = Company::PLAN_STARTER;
                }
            }

            $plan = $maxPlan;
        }

        $limit = $limits[$plan] ?? 1;
        $allowed = $currentCount < $limit;

        return [
            'allowed' => $allowed,
            'current_count' => $currentCount,
            'limit' => $limit,
            'remaining' => max(0, $limit - $currentCount),
            'message' => $allowed
                ? "You can create up to {$limit} companies. Currently you have {$currentCount}."
                : "You have reached your company limit ({$limit}). Please upgrade your plan to create more companies.",
        ];
    }
}
