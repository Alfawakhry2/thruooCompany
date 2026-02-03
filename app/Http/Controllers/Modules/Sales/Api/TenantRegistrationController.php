<?php

namespace App\Http\Controllers\Modules\Sales\Api;

use App\Http\Controllers\Controller;
use App\Models\Landlord\Company;
use App\Models\Landlord\CompanyDetails;
use App\Models\Landlord\Tenant;
use App\Models\Modules\Module;
use App\Models\User;
use App\Services\Company\CreateCompanyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * Tenant Registration Controller (FIXED VERSION)
 * Handles registration of new owners (tenants) and their first company
 *
 * FIXES APPLIED:
 * - ✅ Modules now inserted in tenant database
 * - ✅ Team members now created as users
 * - ✅ Complete tenant data saved
 * - ✅ Complete company data saved
 * - ✅ Company details record created
 * - ✅ Roles and permissions seeded
 * - ✅ Admin role assigned to owner
 * - ✅ Team member roles assigned
 */
class TenantRegistrationController extends Controller
{
    protected $companyService;

    public function __construct(CreateCompanyService $companyService)
    {
        $this->companyService = $companyService;
    }

    /**
     * Get registration options (industries, staff counts, etc.)
     *
     * GET /api/registration/options
     */
    public function getOptions()
    {
        return response()->json([
            'success' => true,
            'data' => [
                'industries' => Tenant::industryOptions(),
                'staff_counts' => Tenant::staffCountOptions(),
                'modules' => Company::availableModules(),
                'roles' => [
                    'Admin' => 'Full system access',
                    'Sales' => 'Sales and leads management',
                    'Finance' => 'Financial operations',
                    'Assistant' => 'View-only access',
                ],
                'plans' => [
                    Company::PLAN_TRIAL => 'Free Trial (14 days)',
                    Company::PLAN_STARTER => 'Starter Plan',
                    Company::PLAN_PROFESSIONAL => 'Professional Plan',
                    Company::PLAN_ENTERPRISE => 'Enterprise Plan',
                ],
            ],
        ]);
    }

    /**
     * Suggest subdomain based on company name
     *
     * POST /api/registration/suggest-subdomain
     * Body: { "company_name": "Ahmed Tech" }
     */
    public function suggestSubdomain(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'company_name' => 'required|string|min:2|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $subdomain = Company::generateSubdomain($request->company_name);

        return response()->json([
            'success' => true,
            'data' => [
                'company_name' => $request->company_name,
                'suggested_subdomain' => $subdomain,
                'full_domain' => "{$subdomain}." . config('app.tenant_domain', 'thruoo.local'),
                'available' => Company::isSubdomainAvailable($subdomain),
            ],
        ]);
    }

    /**
     * Check if subdomain is available
     *
     * POST /api/registration/check-subdomain
     * Body: { "subdomain": "ahmed-tech" }
     */
    public function checkSubdomain(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'subdomain' => 'required|string|min:2|max:50|regex:/^[a-z0-9-]+$/',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $available = Company::isSubdomainAvailable($request->subdomain);

        return response()->json([
            'success' => true,
            'data' => [
                'subdomain' => $request->subdomain,
                'available' => $available,
                'full_domain' => "{$request->subdomain}." . config('app.tenant_domain', 'thruoo.local'),
            ],
        ]);
    }

    /**
     * Suggest slug based on company name (for path-based routing)
     *
     * POST /api/registration/suggest-slug
     * Body: { "company_name": "Ahmed Tech" }
     */
    public function suggestSlug(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'company_name' => 'required|string|min:2|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $slug = Company::generateSlug($request->company_name);

        return response()->json([
            'success' => true,
            'data' => [
                'company_name' => $request->company_name,
                'suggested_slug' => $slug,
                'path_url' => url("/{$slug}"),
                'available' => Company::isSlugAvailable($slug),
            ],
        ]);
    }

    /**
     * Check if slug is available (for path-based routing)
     *
     * POST /api/registration/check-slug
     * Body: { "slug": "ahmed-tech" }
     */
    public function checkSlug(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'slug' => 'required|string|min:2|max:50|regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $available = Company::isSlugAvailable($request->slug);

        return response()->json([
            'success' => true,
            'data' => [
                'slug' => $request->slug,
                'available' => $available,
                'path_url' => url("/{$request->slug}"),
                'message' => $available
                    ? 'Slug is available'
                    : 'This slug is already taken. Please choose another.',
            ],
        ]);
    }

    /**
     * Register new tenant (owner) with first company
     *
     * POST /api/registration/register
     */
    public function register(Request $request)
    {
        // Validate request using TenantRegistrationRequest rules
        $validator = Validator::make($request->all(), [
            // Step 1: Personal Information
            'personal' => ['required', 'array'],
            'personal.name' => ['required', 'string', 'max:255'],
            'personal.email' => ['required', 'email', 'max:255', 'unique:mysql.tenants,email'],
            'personal.password' => ['required', 'confirmed', 'string', 'min:8'],
            'personal.phone' => ['required', 'string', 'max:20'],
            'personal.title' => ['nullable', 'string', 'max:100'],
            'personal.birth_year' => ['nullable', 'integer', 'min:1940', 'max:' . (date('Y') - 16)],
            'personal.how_know_us' => ['nullable', 'array'],

            // Step 2: Company Information
            'company' => ['required', 'array'],
            'company.name' => ['required', 'string', 'max:255'],
            'company.industry' => ['required', 'string', 'max:100'],
            'company.staff_count' => ['required', 'string', 'in:1-10,11-50,51-200,201-500,500+'],
            'company.website' => ['nullable', 'url', 'max:255'],
            'company.business_email' => ['nullable', 'email', 'max:255'],
            'company.country' => ['required', 'string', 'max:100'],
            'company.city' => ['required', 'string', 'max:100'],
            'company.address' => ['nullable', 'string', 'max:500'],
            'company.legal_id' => ['nullable', 'string', 'max:100'],
            'company.tax_id' => ['nullable', 'string', 'max:100'],
            'company.logo' => ['nullable', 'string', 'max:5000'],

            // Custom subdomain (optional)
            'subdomain' => ['nullable', 'string', 'alpha_dash', 'min:3', 'max:63', 'unique:mysql.companies,subdomain'],

            // Step 3: Team Members (Optional)
            'team_members' => ['nullable', 'array', 'max:10'],
            'team_members.*.email' => ['required', 'email', 'max:255', 'distinct'],
            'team_members.*.name' => ['nullable', 'string', 'max:255'],
            'team_members.*.role' => ['nullable', 'string', 'in:Admin,Sales,Finance,Assistant'],

            // Step 4: Modules & Referral
            'modules' => ['required', 'array', 'min:1'],
            'modules.*' => ['string', 'in:sales'],

            'referral' => ['nullable', 'array'],
            'referral.code' => ['nullable', 'string', 'max:50'],
            'referral.relation' => ['nullable', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        // ====================================================================
        // ORPHAN DATABASE CLEANUP
        // ====================================================================
        $subdomain = $request->input('subdomain')
            ?? Company::generateSubdomain($request->input('company.name'));
        $expectedDbName = 'company_' . str_replace('-', '_', $subdomain);

        $existingDb = DB::select("SHOW DATABASES LIKE '{$expectedDbName}'");
        $existingCompany = Company::on('mysql')
            ->where('subdomain', $subdomain)
            ->exists();

        if (!empty($existingDb) && !$existingCompany) {
            DB::statement("DROP DATABASE IF EXISTS `{$expectedDbName}`");
            Log::warning("Dropped orphan database: {$expectedDbName}");
        }

        DB::beginTransaction();

        try {
            // ====================================================================
            // STEP 1: Create Tenant (Owner) with COMPLETE data
            // ====================================================================
            Log::info('Creating Tenant (Owner)...');

            $tenant = Tenant::create([
                // Basic Info
                'name' => $request->input('personal.name'),
                'email' => $request->input('personal.email'),
                'phone' => $request->input('personal.phone'),

                // Address
                'country' => $request->input('company.country'),
                'city' => $request->input('company.city'),
                'address' => $request->input('company.address'),

                // Referral Info
                'referral_code' => $request->input('referral.code'),
                'referral_relation' => $request->input('referral.relation'),

                // Settings & Meta (store additional info that doesn't have dedicated columns)
                'metadata' => [
                    'title' => $request->input('personal.title'),
                    'birth_year' => $request->input('personal.birth_year'),
                    'how_know_us' => $request->input('personal.how_know_us'),
                ],
            ]);

            Log::info("Tenant created: {$tenant->id}");

            // ====================================================================
            // STEP 2: Create Company with COMPLETE data
            // ====================================================================
            Log::info('Creating Company...');

            $company = Company::on('mysql')->create([
                // Relationship
                'tenant_id' => $tenant->id,

                // Owner info (for backward compatibility)
                'owner_email' => $tenant->email,
                'owner_name' => $tenant->name,
                'owner_phone' => $tenant->phone,

                // Company Basic Info
                'name' => $request->input('company.name'),
                'business_email' => $request->input('company.business_email') ?? $tenant->email,
                'email' => $request->input('company.business_email') ?? $tenant->email, // Backward compatibility
                'phone' => $request->input('company.phone'),

                // Company Details
                'industry' => $request->input('company.industry'),
                'staff_count' => $request->input('company.staff_count'),
                'website' => $request->input('company.website'),
                'country' => $request->input('company.country'),
                'city' => $request->input('company.city'),
                'address' => $request->input('company.address'),

                // Legal Info
                'legal_id' => $request->input('company.legal_id'),
                'tax_id' => $request->input('company.tax_id'),

                // Branding
                'logo' => $request->input('company.logo'),

                // Subscription & Status
                'status' => Company::STATUS_ACTIVE,
                'plan' => Company::PLAN_TRIAL,
                'trial_ends_at' => now()->addDays(14),
                'enabled_modules' => $request->input('modules', ['sales']),

                // Custom subdomain if provided (otherwise auto-generated in boot())
                'subdomain' => $request->input('subdomain'),
            ]);

            Log::info("Company created: {$company->subdomain} (DB: {$company->database})");

            // ====================================================================
            // STEP 3: Create Company Details (Additional Info)
            // ====================================================================
            Log::info('Creating Company Details...');

            CompanyDetails::create([
                'company_id' => $company->id,
                'description' => $request->input('company.description'),
                'founded_year' => $request->input('company.founded_year', date('Y')),
                'currency' => $request->input('company.currency', 'USD'),
                'metadata' => [
                    'registration_source' => 'web',
                    'registration_ip' => $request->ip(),
                ],
            ]);

            Log::info('Company Details created');

            // ====================================================================
            // STEP 4: Create Company Database
            // ====================================================================
            Log::info('Creating company database...');
            $this->companyService->createCompanyDatabase($company);
            Log::info("Database created: {$company->database}");

            // ====================================================================
            // STEP 5: Run Migrations on Company Database
            // ====================================================================
            Log::info('Running migrations...');
            $this->companyService->runCompanyMigrations($company);
            Log::info('Migrations completed');

            // ====================================================================
            // STEP 6: Seed Roles and Permissions
            // ====================================================================
            Log::info('Seeding roles and permissions...');
            $company->makeCurrent();

            try {
                Artisan::call('db:seed', [
                    '--database' => 'tenant',
                    '--class' => 'Database\\Seeders\\Tenant\\RolePermissionSeeder',
                    '--force' => true,
                ]);
                Log::info('Roles and permissions seeded');
            } catch (\Exception $e) {
                Log::warning("Seeder not found or failed: {$e->getMessage()}. Creating basic roles manually...");
                $this->createBasicRoles();
            }

            Company::forgetCurrent();

            // ====================================================================
            // STEP 7: Create Module Records in Tenant Database
            // ====================================================================
            Log::info('Creating module records...');
            $company->makeCurrent();

            $selectedModules = $request->input('modules', ['sales']);
            $availableModules = Company::availableModules();

            foreach ($selectedModules as $moduleKey) {
                if (isset($availableModules[$moduleKey])) {
                    $moduleConfig = $availableModules[$moduleKey];

                    Module::create([
                        'name' => $moduleConfig['name'],
                        'name_ar' => $moduleConfig['name_ar'] ?? null,
                        'description' => $moduleConfig['description'],
                        'status' => 'active',
                        'subscription_start' => now(),
                        'trial_end' => now()->addDays(14),
                    ]);

                    Log::info("Module created: {$moduleConfig['name']}");
                }
            }

            // ====================================================================
            // STEP 7.5: Create Default Branch
            // ====================================================================
            Log::info('Creating default branch...');

            $defaultBranch = \App\Models\Modules\Sales\Branch::create([
                'name' => $request->input('company.name'),
                'name_ar' => $request->input('company.name_ar'),
                'is_default' => true,
                'is_active' => true,
                'address' => $request->input('company.address'),
                'phone' => $request->input('company.phone'),
                'email' => $request->input('company.business_email') ?? $tenant->email,
            ]);

            Log::info("Default branch created: {$defaultBranch->name} (ID: {$defaultBranch->id})");

            Company::forgetCurrent();

            // ====================================================================
            // STEP 8: Create Admin User with COMPLETE profile
            // ====================================================================
            Log::info('Creating admin user...');
            $company->makeCurrent();

            $adminUser = User::create([
                // Basic Info
                'name' => $request->input('personal.name'),
                'email' => $request->input('personal.email'),
                'password' => Hash::make($request->input('personal.password')),
                'phone' => $request->input('personal.phone'),

                // Profile Info
                'title' => $request->input('personal.title'),
                'birth_year' => $request->input('personal.birth_year'),
                'how_know_us' => $request->input('personal.how_know_us'),

                // Status
                'status' => User::STATUS_ACTIVE,
                'is_owner' => true,
                'profile_completed' => true,
                'email_verified_at' => now(),
            ]);

            Log::info("Admin user created: {$adminUser->email}");

            // ====================================================================
            // STEP 9: Assign Admin Role to Owner
            // ====================================================================
            Log::info('Assigning Admin role to owner...');

            $company->makeCurrent(); // ✅ add this

            $adminRole = Role::where('name', 'Admin')->first();
            if ($adminRole) {
                $adminUser->assignRole($adminRole);
                Log::info('Admin role assigned');
            } else {
                Log::warning('Admin role not found - role assignment skipped');
            }

            // ====================================================================
            // STEP 9.5: Assign Admin User to Default Branch
            // ====================================================================
            Log::info('Assigning admin user to default branch...');
            $adminUser->branches()->attach($defaultBranch->id);
            Log::info("Admin user assigned to branch: {$defaultBranch->name}");

            // ====================================================================
            // STEP 10: Create Team Member Users
            // ====================================================================
            if ($request->has('team_members') && is_array($request->team_members)) {
                Log::info('Creating team member users...');

                foreach ($request->team_members as $member) {
                    try {
                        $teamUser = User::create([
                            'name' => $this->extractNameFromEmail($member['email']) ?? 'guest ' . random_int(100000, 999999),
                            'email' => $member['email'],
                            'role' => $member['role'],
                            'password' => Hash::make(Str::random(32)), // Random password
                            'status' => User::STATUS_PENDING,
                            'invited_by' => $adminUser->id,
                            'invited_at' => now(),
                            'invitation_token' => Str::random(64),
                        ]);

                        // Assign role if provided
                        if (!empty($member['role'])) {
                            $roleName = $member['role'];
                            $role = Role::where('name', $roleName)->first();

                            if ($role) {
                                $teamUser->assignRole($role);
                                Log::info("Team member created: {$teamUser->email} (Role: {$roleName})");
                            } else {
                                Log::warning("Role '{$roleName}' not found for team member: {$teamUser->email}");
                            }
                        }

                        // Assign team member to default branch
                        $teamUser->branches()->attach($defaultBranch->id);
                        Log::info("Team member assigned to default branch: {$teamUser->email}");

                        // TODO: Send invitation email
                        // Mail::to($teamUser->email)->send(new TeamMemberInvitation($teamUser, $company));

                    } catch (\Exception $e) {
                        Log::error("Failed to create team member {$member['email']}: {$e->getMessage()}");
                        // Continue with other team members
                    }
                }
            }

            // Generate token for admin user
            $token = $adminUser->createToken('auth-token')->plainTextToken;

            // Forget current tenant before committing
            Company::forgetCurrent();

            DB::commit();

            Log::info('Registration completed successfully');

            // ====================================================================
            // SUCCESS RESPONSE
            // ====================================================================
            return response()->json([
                'success' => true,
                'message' => 'Registration successful! Redirecting to your company dashboard...',
                'data' => [
                    'token' => $token,
                    'tenant' => [
                        'id' => $tenant->id,
                        'name' => $tenant->name,
                        'email' => $tenant->email,
                    ],
                    'company' => [
                        'id' => $company->id,
                        'name' => $company->name,
                        'subdomain' => $company->subdomain,
                        'domain' => $company->full_domain,
                        'database' => $company->database,
                        'modules' => $company->enabled_modules,
                    ],
                    'user' => [
                        'id' => $adminUser->id,
                        'name' => $adminUser->name,
                        'email' => $adminUser->email,
                        'role' => 'Admin',
                        'is_owner' => true,
                    ],
                    'redirect' => [
                        'url' => $company->url,
                        'with_token' => true,
                    ],
                ],
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Company::forgetCurrent();

            Log::error('Registration error: ' . $e->getMessage());
            Log::error($e->getTraceAsString());

            // Try to cleanup
            if (isset($company)) {
                try {
                    $this->companyService->deleteCompanyDatabase($company);
                    Log::info('Database cleanup completed');
                } catch (\Exception $cleanupError) {
                    Log::error('Cleanup error: ' . $cleanupError->getMessage());
                }
            }

            return response()->json([
                'success' => false,
                'message' => 'Registration failed. Please try again.',
                'error' => app()->environment('local') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Extract a name from email address
     */
    protected function extractNameFromEmail(string $email): string
    {
        $parts = explode('@', $email);
        $localPart = $parts[0];

        // Replace dots and underscores with spaces
        $name = str_replace(['.', '_', '-'], ' ', $localPart);

        // Capitalize each word
        return Str::title($name);
    }
    /**
     * Create basic roles manually if seeder fails
     */
    protected function createBasicRoles(): void
    {
        try {
            // Create basic roles
            $roles = ['Admin', 'Sales', 'Finance', 'Assistant'];

            foreach ($roles as $roleName) {
                Role::firstOrCreate(['name' => $roleName]);
            }

            Log::info('Basic roles created manually');
        } catch (\Exception $e) {
            Log::error("Failed to create basic roles: {$e->getMessage()}");
        }
    }
}
