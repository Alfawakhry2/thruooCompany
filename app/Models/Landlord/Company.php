<?php

namespace App\Models\Landlord;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Multitenancy\Models\Concerns\UsesTenantConnection;
use Spatie\Multitenancy\Models\Concerns\UsesTenantModel;
use Spatie\Multitenancy\Models\Tenant as BaseTenant;

/**
 * Company Model - THIS IS THE ACTUAL TENANT
 * Each company has its own subdomain and database
 *
 * UPDATED: availableModules() method now includes 'key' and 'name_ar' fields
 * This is required for proper module seeding in tenant databases
 */
class Company extends BaseTenant
{
    use HasFactory, HasUuids, SoftDeletes;
    use UsesTenantConnection;

    /**
     * The connection name for the model (Landlord database)
     */
    protected $connection = 'mysql';

    /**
     * The current tenant instance (for static access)
     */
    protected static ?Company $currentTenant = null;

    /**
     * Get the database connection name.
     * Company model is always stored in the landlord database, not tenant databases.
     */
    public function getConnectionName(): string
    {
        return 'mysql';
    }

    /**
     * The table associated with the model
     */
    protected $table = 'companies';


    /**
     * The attributes that are mass assignable
     */
    protected $fillable = [
        // Relationships
        'tenant_id',

        // Owner Info (for backward compatibility with old migration)
        'owner_email',
        'owner_name',
        'owner_phone',

        // Basic Info
        'name',
        'subdomain',
        'slug',
        'domain',
        'database',

        // Company Contact Info (old migration has 'email' field)
        'email',

        // Contact Info
        'business_email',
        'phone',

        // Company Details
        'industry',
        'staff_count',
        'website',
        'country',
        'city',
        'address',

        // Legal Info
        'legal_id',
        'tax_id',

        // Branding
        'logo',

        // Subscription & Status
        'status',
        'trial_ends_at',
        'subscription_ends_at',
        'plan',
        'enabled_modules',

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
            'trial_ends_at' => 'datetime',
            'subscription_ends_at' => 'datetime',
            'enabled_modules' => 'array',
            'settings' => 'array',
            'metadata' => 'array',
        ];
    }

    /**
     * Boot method - Auto-generate subdomain and database name
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($company) {
            // Auto-generate subdomain if not set
            if (empty($company->subdomain)) {
                $company->subdomain = static::generateSubdomain($company->name);
            }

            // Auto-generate slug if not set
            if (empty($company->slug)) {
                $company->slug = static::generateSlug($company->name);
            }

            // Auto-generate database name if not set
            if (empty($company->database)) {
                $company->database = 'company_' . str_replace('-', '_', $company->subdomain);
            }

            // Set default status if not set
            if (empty($company->status)) {
                $company->status = self::STATUS_ACTIVE;
            }

            // Set default plan if not set
            if (empty($company->plan)) {
                $company->plan = self::PLAN_TRIAL;
            }

            // Set trial period (14 days)
            if (empty($company->trial_ends_at) && $company->plan === self::PLAN_TRIAL) {
                $company->trial_ends_at = now()->addDays(14);
            }
        });
    }

    // ============================================================================
    // SPATIE MULTITENANCY METHODS (Required by IsTenant interface)
    // ============================================================================

    /**
     * Get the database name for this tenant
     * Required by Spatie Multitenancy
     */
    public function getDatabaseName(): string
    {
        return $this->database;
    }

    /**
     * Make this company the current tenant
     * Required by Spatie Multitenancy
     */
    public function makeCurrent(): static
    {
        if ($this->isCurrent()) {
            return $this;
        }

        static::forgetCurrent();

        // Use the action from config to make tenant current
        $actionClass = config('multitenancy.actions.make_tenant_current_action');
        $action = app($actionClass);
        $action->execute($this);

        // Also set in our static property and container
        static::$currentTenant = $this;
        $containerKey = config('multitenancy.current_tenant_container_key', 'currentCompany');
        app()->instance($containerKey, $this);

        // Set context key as well (for tenant ID)
        $contextKey = config('multitenancy.current_tenant_context_key', 'companyId');
        app()->instance($contextKey, $this->id);

        return $this;
    }

    /**
     * Check if this company is the current tenant
     */
    public function isCurrent(): bool
    {
        return optional(static::current())->id === $this->id;
    }

    /**
     * Forget the current tenant
     */
    public static function forgetCurrent(): ?static
    {
        $currentTenant = static::current();

        static::$currentTenant = null;

        // Use the container key from config
        $containerKey = config('multitenancy.current_tenant_container_key', 'currentCompany');
        app()->forgetInstance($containerKey);

        return $currentTenant;
    }

    /**
     * Get the current tenant
     */
    public static function current(): ?static
    {
        if (static::$currentTenant) {
            return static::$currentTenant;
        }

        // Use the container key from config
        $containerKey = config('multitenancy.current_tenant_container_key', 'currentCompany');
        $tenant = app()->bound($containerKey) ? app($containerKey) : null;

        if ($tenant instanceof static) {
            return static::$currentTenant = $tenant;
        }

        return null;
    }

    /**
     * Check if there is a current tenant
     */
    public static function checkCurrent(): bool
    {
        return static::current() !== null;
    }

    // ============================================================================
    // SUBDOMAIN GENERATION
    // ============================================================================

    /**
     * Generate subdomain from company name
     * Examples:
     * - "Ahmed Tech" → "ahmed-tech"
     * - "AHMED Compu" → "ahmed-compu"
     * - "Tech@Solutions!" → "tech-solutions"
     */
    public static function generateSubdomain(string $name): string
    {
        // Convert to lowercase
        $subdomain = Str::lower($name);

        // Remove special characters, keep only alphanumeric and spaces
        $subdomain = preg_replace('/[^a-z0-9\s-]/', '', $subdomain);

        // Replace spaces with hyphens
        $subdomain = str_replace(' ', '-', $subdomain);

        // Remove multiple consecutive hyphens
        $subdomain = preg_replace('/-+/', '-', $subdomain);

        // Trim hyphens from start and end
        $subdomain = trim($subdomain, '-');

        // Ensure uniqueness (use landlord connection explicitly)
        $originalSubdomain = $subdomain;
        $counter = 1;

        // Use DB facade directly to ensure we're using the landlord connection
        while (DB::connection('mysql')->table('companies')
            ->where('subdomain', $subdomain)
            ->whereNull('deleted_at')
            ->exists()) {
            $subdomain = $originalSubdomain . '-' . $counter;
            $counter++;
        }

        return $subdomain;
    }

    /**
     * Check if subdomain is available
     */
    public static function isSubdomainAvailable(string $subdomain): bool
    {
        // Use DB facade directly to ensure we're using the landlord connection
        return !DB::connection('mysql')->table('companies')
            ->where('subdomain', $subdomain)
            ->whereNull('deleted_at')
            ->exists();
    }

    // ============================================================================
    // SLUG GENERATION (For Path-Based Routing)
    // ============================================================================

    /**
     * Generate slug from company name
     * Examples:
     * - "Ahmed Tech" → "ahmed-tech"
     * - "AHMED Compu" → "ahmed-compu"
     * - "Tech@Solutions!" → "tech-solutions"
     */
    public static function generateSlug(string $name): string
    {
        // Convert to lowercase
        $slug = Str::lower($name);

        // Remove special characters, keep only alphanumeric and spaces
        $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);

        // Replace spaces with hyphens
        $slug = str_replace(' ', '-', $slug);

        // Remove multiple consecutive hyphens
        $slug = preg_replace('/-+/', '-', $slug);

        // Trim hyphens from start and end
        $slug = trim($slug, '-');

        // Ensure uniqueness (use landlord connection explicitly)
        $originalSlug = $slug;
        $counter = 1;

        // Use DB facade directly to ensure we're using the landlord connection
        while (DB::connection('mysql')->table('companies')
            ->where('slug', $slug)
            ->whereNull('deleted_at')
            ->exists()) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Check if slug is available
     */
    public static function isSlugAvailable(string $slug): bool
    {
        // Use DB facade directly to ensure we're using the landlord connection
        return !DB::connection('mysql')->table('companies')
            ->where('slug', $slug)
            ->whereNull('deleted_at')
            ->exists();
    }

    // ============================================================================
    // RELATIONSHIPS
    // ============================================================================

    /**
     * Relationship with Tenant (Owner)
     */
    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    /**
     * Alias for tenant() - Owner relationship
     */
    public function owner()
    {
        return $this->tenant();
    }

    /**
     * Relationship with Company Details
     */
    public function details()
    {
        return $this->hasOne(CompanyDetails::class, 'company_id');
    }

    // ============================================================================
    // STATUS CONSTANTS
    // ============================================================================

    const STATUS_PENDING = 'pending';
    const STATUS_ACTIVE = 'active';
    const STATUS_SUSPENDED = 'suspended';
    const STATUS_CANCELLED = 'cancelled';

    const PLAN_TRIAL = 'trial';
    const PLAN_STARTER = 'starter';
    const PLAN_PROFESSIONAL = 'professional';
    const PLAN_ENTERPRISE = 'enterprise';

    const MODULE_SALES = 'sales';
    const MODULE_CONTACTS = 'contacts';
    const MODULE_ACCOUNTING = 'accounting';
    const MODULE_INVENTORY = 'inventory';
    const MODULE_HR = 'hr';

    // ============================================================================
    // SUBSCRIPTION & TRIAL METHODS
    // ============================================================================

    /**
     * Check if company is on trial
     */
    public function isOnTrial(): bool
    {
        return $this->plan === self::PLAN_TRIAL &&
               $this->trial_ends_at &&
               $this->trial_ends_at->isFuture();
    }

    /**
     * Check if trial has expired
     */
    public function trialExpired(): bool
    {
        if ($this->plan !== self::PLAN_TRIAL || !$this->trial_ends_at) {
            return false;
        }

        return $this->trial_ends_at->isPast();
    }

    /**
     * Check if company is in grace period (3 days after trial)
     */
    public function isInGracePeriod(): bool
    {
        if (!$this->trialExpired()) {
            return false;
        }

        return $this->trial_ends_at->copy()->addDays(3)->isFuture();
    }

    /**
     * Check if company subscription is active
     */
    public function isActive(): bool
    {
        if ($this->status !== self::STATUS_ACTIVE) {
            return false;
        }

        return $this->isOnTrial() ||
               $this->isInGracePeriod() ||
               ($this->subscription_ends_at && $this->subscription_ends_at->isFuture());
    }

    /**
     * Get remaining trial days
     */
    public function getRemainingTrialDays(): ?int
    {
        if (!$this->isOnTrial()) {
            return null;
        }

        return (int) now()->diffInDays($this->trial_ends_at, false);
    }

    // ============================================================================
    // MODULE MANAGEMENT
    // ============================================================================

    /**
     * Get all available modules with complete configuration
     *
     * UPDATED: Now includes 'key' and 'name_ar' fields required for module seeding
     *
     * @return array Complete module configuration
     */
    public static function availableModules(): array
    {
        return [
            self::MODULE_SALES => [
                'key' => self::MODULE_SALES,
                'name' => 'Sales',
                'name_ar' => 'المبيعات',
                'description' => 'Manage leads, deals, proposals, and invoices',
                'icon' => 'chart-line',
                'available' => true,
            ],
            self::MODULE_CONTACTS => [
                'key' => self::MODULE_CONTACTS,
                'name' => 'Contacts',
                'name_ar' => 'جهات الاتصال',
                'description' => 'Customer and supplier management',
                'icon' => 'users',
                'available' => false,
            ],
            self::MODULE_ACCOUNTING => [
                'key' => self::MODULE_ACCOUNTING,
                'name' => 'Accounting',
                'name_ar' => 'المحاسبة',
                'description' => 'Financial management and reporting',
                'icon' => 'calculator',
                'available' => false,
            ],
            self::MODULE_INVENTORY => [
                'key' => self::MODULE_INVENTORY,
                'name' => 'Inventory',
                'name_ar' => 'المخزون',
                'description' => 'Stock and warehouse management',
                'icon' => 'box',
                'available' => false,
            ],
            self::MODULE_HR => [
                'key' => self::MODULE_HR,
                'name' => 'HR',
                'name_ar' => 'الموارد البشرية',
                'description' => 'Human resources management',
                'icon' => 'briefcase',
                'available' => false,
            ],
        ];
    }

    /**
     * Get industry options for registration
     *
     * @return array Industry options
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
     *
     * @return array Staff count ranges
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
     * Check if module is enabled
     */
    public function hasModule(string $module): bool
    {
        $modules = $this->enabled_modules ?? [];
        return in_array($module, $modules);
    }

    /**
     * Enable a module
     */
    public function enableModule(string $module): void
    {
        $modules = $this->enabled_modules ?? [];
        if (!in_array($module, $modules)) {
            $modules[] = $module;
            $this->update(['enabled_modules' => $modules]);
        }
    }

    /**
     * Disable a module
     */
    public function disableModule(string $module): void
    {
        $modules = $this->enabled_modules ?? [];
        $modules = array_filter($modules, fn($m) => $m !== $module);
        $this->update(['enabled_modules' => array_values($modules)]);
    }

    /**
     * Get enabled modules with their full configuration
     *
     * @return array Enabled modules with complete config
     */
    public function getEnabledModulesWithConfig(): array
    {
        $allModules = static::availableModules();
        $enabledModuleKeys = $this->enabled_modules ?? [];

        $result = [];
        foreach ($enabledModuleKeys as $key) {
            if (isset($allModules[$key])) {
                $result[$key] = $allModules[$key];
            }
        }

        return $result;
    }

    // ============================================================================
    // DOMAIN & URL METHODS
    // ============================================================================

    /**
     * Get full domain (subdomain.thruoo.com or custom domain)
     */
    public function getFullDomainAttribute(): string
    {
        return $this->domain ?? "{$this->subdomain}." . config('app.tenant_domain', 'thruoo.local');
    }

    /**
     * Get full URL
     */
    public function getUrlAttribute(): string
    {
        $protocol = app()->environment('production') ? 'https' : 'http';
        return "{$protocol}://{$this->full_domain}";
    }

    // ============================================================================
    // DATABASE CONFIGURATION
    // ============================================================================

    /**
     * Get database connection configuration
     */
    public function getDatabaseConnectionConfig(): array
    {
        $config = config('database.connections.mysql');

        return array_merge($config, [
            'database' => $this->database,
        ]);
    }

    /**
     * Check if database exists
     *
     * @return bool
     */
    public function databaseExists(): bool
    {
        try {
            $databases = DB::select('SHOW DATABASES LIKE ?', [$this->database]);
            return !empty($databases);
        } catch (\Exception $e) {
            return false;
        }
    }

    // ============================================================================
    // STATUS MANAGEMENT
    // ============================================================================

    /**
     * Activate company
     */
    public function activate(): void
    {
        $this->update(['status' => self::STATUS_ACTIVE]);
    }

    /**
     * Suspend company
     */
    public function suspend(): void
    {
        $this->update(['status' => self::STATUS_SUSPENDED]);
    }

    /**
     * Cancel company
     */
    public function cancel(): void
    {
        $this->update(['status' => self::STATUS_CANCELLED]);
    }

    /**
     * Check if company is pending
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if company is suspended
     */
    public function isSuspended(): bool
    {
        return $this->status === self::STATUS_SUSPENDED;
    }

    /**
     * Check if company is cancelled
     */
    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    // ============================================================================
    // LOGO MANAGEMENT
    // ============================================================================

    /**
     * Get logo URL
     */
    public function getLogoUrlAttribute(): ?string
    {
        if ($this->logo) {
            // If it's already a full URL
            if (filter_var($this->logo, FILTER_VALIDATE_URL)) {
                return $this->logo;
            }

            // If it's a base64 image
            if (str_starts_with($this->logo, 'data:image')) {
                return $this->logo;
            }

            // Otherwise assume it's a storage path
            return asset('storage/' . $this->logo);
        }

        return null;
    }

    /**
     * Get company initials for avatar fallback
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
    // HELPER METHODS
    // ============================================================================

    /**
     * Get company summary for API responses
     *
     * @return array
     */
    public function getSummary(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'subdomain' => $this->subdomain,
            'domain' => $this->full_domain,
            'url' => $this->url,
            'logo_url' => $this->logo_url,
            'initials' => $this->initials,
            'status' => $this->status,
            'plan' => $this->plan,
            'trial_ends_at' => $this->trial_ends_at?->toDateString(),
            'is_on_trial' => $this->isOnTrial(),
            'remaining_trial_days' => $this->getRemainingTrialDays(),
            'enabled_modules' => $this->enabled_modules,
        ];
    }

    /**
     * Get company full details for settings/admin
     *
     * @return array
     */
    public function getFullDetails(): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,

            // Basic Info
            'name' => $this->name,
            'subdomain' => $this->subdomain,
            'domain' => $this->domain,
            'full_domain' => $this->full_domain,
            'url' => $this->url,

            // Contact Info
            'business_email' => $this->business_email,
            'phone' => $this->phone,

            // Details
            'industry' => $this->industry,
            'staff_count' => $this->staff_count,
            'website' => $this->website,
            'country' => $this->country,
            'city' => $this->city,
            'address' => $this->address,

            // Legal
            'legal_id' => $this->legal_id,
            'tax_id' => $this->tax_id,

            // Branding
            'logo' => $this->logo,
            'logo_url' => $this->logo_url,
            'initials' => $this->initials,

            // Subscription
            'status' => $this->status,
            'plan' => $this->plan,
            'trial_ends_at' => $this->trial_ends_at,
            'subscription_ends_at' => $this->subscription_ends_at,
            'is_on_trial' => $this->isOnTrial(),
            'trial_expired' => $this->trialExpired(),
            'is_in_grace_period' => $this->isInGracePeriod(),
            'remaining_trial_days' => $this->getRemainingTrialDays(),

            // Modules
            'enabled_modules' => $this->enabled_modules,
            'enabled_modules_config' => $this->getEnabledModulesWithConfig(),

            // Database
            'database' => $this->database,
            'database_exists' => $this->databaseExists(),

            // Timestamps
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * Scope: Active companies only
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Scope: Companies on trial
     */
    public function scopeOnTrial($query)
    {
        return $query->where('plan', self::PLAN_TRIAL)
            ->where('trial_ends_at', '>', now());
    }

    /**
     * Scope: Trial expired companies
     */
    public function scopeTrialExpired($query)
    {
        return $query->where('plan', self::PLAN_TRIAL)
            ->where('trial_ends_at', '<=', now());
    }

    /**
     * Scope: Companies with specific module enabled
     */
    public function scopeWithModule($query, string $module)
    {
        return $query->whereJsonContains('enabled_modules', $module);
    }
}
