<?php

namespace App\Services\Tenant;

use App\Helpers\SubdomainGenerator;
use App\Models\Landlord\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Models\Modules\Sales\Role;
use Spatie\Permission\Models\Permission;
use Exception;

class CreateTenantService
{
    /**
     * Trial period in days
     */
    protected int $trialDays = 14;

    /**
     * Create a new tenant with database and initial setup
     */
    public function create(array $data): Tenant
    {
        // Extract data sections
        $personal = $data['personal'];
        $company = $data['company'];
        $teamMembers = $data['team_members'] ?? [];
        $modules = $data['modules'] ?? ['sales'];
        $referral = $data['referral'] ?? [];
        $customSubdomain = $data['subdomain'] ?? null;

        // Generate subdomain from company name or use custom
        $subdomain = $customSubdomain
            ? SubdomainGenerator::sanitize($customSubdomain)
            : SubdomainGenerator::generate($company['name']);

        // Validate subdomain is available
        if (!SubdomainGenerator::isAvailable($subdomain)) {
            throw new Exception("Subdomain '{$subdomain}' is not available. " . SubdomainGenerator::getValidationError($subdomain));
        }

        // Generate database name
        $databaseName = 'tenant_' . str_replace('-', '_', $subdomain);

        // Handle logo upload if base64
        $logoPath = $this->handleLogo($company['logo'] ?? null, $subdomain);

        // Create tenant database first (DDL statements can't be in transactions)
        $this->createTenantDatabase($databaseName);

        // Now start transaction for tenant record creation
        DB::connection('mysql')->beginTransaction();

        try {
            // Create tenant record
            $tenant = Tenant::on('mysql')->create([
                // Basic Info
                'name' => $company['name'],
                'subdomain' => $subdomain,
                'database' => $databaseName,

                // Contact Info
                'email' => $personal['email'],
                'phone' => $personal['phone'] ?? null,
                'business_email' => $company['business_email'] ?? null,

                // Company Details
                'industry' => $company['industry'] ?? null,
                'staff_count' => $company['staff_count'] ?? null,
                'website' => $company['website'] ?? null,
                'country' => $company['country'] ?? null,
                'city' => $company['city'] ?? null,
                'address' => $company['address'] ?? null,

                // Legal Info
                'legal_id' => $company['legal_id'] ?? null,
                'tax_id' => $company['tax_id'] ?? null,

                // Branding
                'logo' => $logoPath,

                // Referral Info
                'referral_code' => $referral['code'] ?? null,
                'referral_relation' => $referral['relation'] ?? null,

                // Subscription & Status
                'status' => Tenant::STATUS_ACTIVE,
                'trial_ends_at' => now()->addDays($this->trialDays),
                'plan' => Tenant::PLAN_TRIAL,
                'enabled_modules' => $modules,

                // Settings
                'settings' => [
                    'timezone' => 'UTC',
                    'date_format' => 'Y-m-d',
                    'currency' => 'USD',
                ],
            ]);

            // Commit tenant record first
            DB::connection('mysql')->commit();

            // Now do tenant database operations (outside transaction)
            try {
                // Run tenant migrations
                $this->runTenantMigrations($tenant);

                // Seed roles and permissions FIRST
                $this->seedRolesAndPermissions($tenant);

                // Create owner/admin user
                $owner = $this->createOwnerUser($tenant, $personal);

                // Create team members
                if (!empty($teamMembers)) {
                    $this->createTeamMembers($tenant, $teamMembers, $owner->id);
                }

                Log::info("Tenant created successfully", [
                    'tenant_id' => $tenant->id,
                    'subdomain' => $tenant->subdomain,
                    'owner_email' => $owner->email,
                    'team_count' => count($teamMembers),
                ]);
            } catch (Exception $e) {
                // If tenant DB operations fail, mark tenant as failed
                $tenant->update(['status' => Tenant::STATUS_SUSPENDED]);
                Log::error("Tenant setup failed", [
                    'tenant_id' => $tenant->id,
                    'error' => $e->getMessage(),
                ]);
                throw new Exception("Tenant created but setup failed: " . $e->getMessage());
            }

            return $tenant;
        } catch (Exception $e) {
            DB::connection('mysql')->rollBack();

            // Try to clean up the database if tenant creation failed
            try {
                DB::connection('mysql')->statement("DROP DATABASE IF EXISTS `{$databaseName}`");
            } catch (Exception $dropError) {
                Log::error("Failed to drop database after tenant creation failure", [
                    'database' => $databaseName,
                    'error' => $dropError->getMessage(),
                ]);
            }

            throw $e;
        }
    }

    /**
     * Handle logo upload
     */
    protected function handleLogo(?string $logo, string $subdomain): ?string
    {
        if (empty($logo)) {
            return null;
        }

        // If it's already a URL, return as is
        if (filter_var($logo, FILTER_VALIDATE_URL)) {
            return $logo;
        }

        // Handle base64 encoded image
        if (preg_match('/^data:image\/(\w+);base64,/', $logo, $matches)) {
            $extension = $matches[1];
            $data = substr($logo, strpos($logo, ',') + 1);
            $data = base64_decode($data);

            if ($data === false) {
                Log::warning("Failed to decode base64 logo", ['subdomain' => $subdomain]);
                return null;
            }

            $filename = "tenants/{$subdomain}/logo.{$extension}";

            try {
                Storage::disk('public')->put($filename, $data);
                return $filename;
            } catch (Exception $e) {
                Log::error("Failed to save logo", [
                    'subdomain' => $subdomain,
                    'error' => $e->getMessage(),
                ]);
                return null;
            }
        }

        return null;
    }

    /**
     * Create tenant database
     */
    protected function createTenantDatabase(string $databaseName): void
    {
        try {
            // Drop database if it exists (in case of previous failed attempt)
            DB::connection('mysql')->statement("DROP DATABASE IF EXISTS `{$databaseName}`");

            // Create fresh database
            DB::connection('mysql')->statement(
                "CREATE DATABASE `{$databaseName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
            );

            Log::info("Tenant database created", ['database' => $databaseName]);
        } catch (Exception $e) {
            throw new Exception("Failed to create tenant database: " . $e->getMessage());
        }
    }

    /**
     * Run migrations on tenant database
     */
    protected function runTenantMigrations(Tenant $tenant): void
    {
        // Configure tenant database connection
        Config::set('database.connections.tenant.database', $tenant->database);
        DB::purge('tenant');
        DB::reconnect('tenant');

        try {
            Artisan::call('migrate', [
                '--database' => 'tenant',
                '--path' => 'database/migrations/tenant',
                '--force' => true,
            ]);

            Log::info("Tenant migrations completed", ['tenant_id' => $tenant->id]);
        } catch (Exception $e) {
            throw new Exception("Failed to run tenant migrations: " . $e->getMessage());
        }
    }

    // /**
    //  * Create owner/admin user in tenant database
    //  */
    // protected function createOwnerUser(Tenant $tenant, array $personal): User
    // {
    //     $this->switchToTenantConnection($tenant);

    //     try {
    //         // Create the owner user
    //         $user = User::create([
    //             'name' => $personal['name'],
    //             'email' => $personal['email'],
    //             'password' => Hash::make($personal['password']),
    //             'phone' => $personal['phone'] ?? null,
    //             'title' => $personal['title'] ?? null,
    //             'birth_year' => $personal['birth_year'] ?? null,
    //             'how_know_us' => $personal['how_know_us'] ?? null,
    //             'status' => User::STATUS_ACTIVE,
    //             'is_owner' => true,
    //             'profile_completed' => true,
    //             'email_verified_at' => now(),
    //         ]);

    //         // After: $user = User::create([...]);
    //         // Add this:


    //         // Assign Super Admin role
    //         $adminRole = Role::where('name', 'Super Admin')
    //             ->where('guard_name', 'web')
    //             ->first();

    //         if ($adminRole) {
    //             $user->assignRole($adminRole);
    //         } else {
    //             // Fallback to Admin if Super Admin doesn't exist
    //             $adminRole = Role::where('name', 'Admin')
    //                 ->where('guard_name', 'web')
    //                 ->first();

    //             if ($adminRole) {
    //                 $user->assignRole($adminRole);
    //             } else {
    //                 throw new Exception("No admin role found. Make sure roles are seeded.");
    //             }
    //         }

    //         $this->resetDefaultConnection();

    //         Log::info("Owner user created", [
    //             'tenant_id' => $tenant->id,
    //             'user_id' => $user->id,
    //             'email' => $user->email,
    //         ]);

    //         return $user;
    //     } catch (Exception $e) {
    //         $this->resetDefaultConnection();
    //         throw new Exception("Failed to create owner user: " . $e->getMessage());
    //     }
    // }

    protected function createOwnerUser(Tenant $tenant, array $personal): User
{
    $this->switchToTenantConnection($tenant);

    try {
        // Create the owner user
        $user = User::create([
            'name' => $personal['name'],
            'email' => $personal['email'],
            'password' => Hash::make($personal['password']),
            'phone' => $personal['phone'] ?? null,
            'title' => $personal['title'] ?? null,
            'birth_year' => $personal['birth_year'] ?? null,
            'how_know_us' => $personal['how_know_us'] ?? null,
            'status' => User::STATUS_ACTIVE,
            'is_owner' => true,
            'profile_completed' => true,
            'email_verified_at' => now(),
        ]);

        // ============================================
        // CREATE MODULES BASED ON SUBSCRIPTION
        // ============================================
        $enabledModules = $tenant->enabled_modules ?? ['sales'];

        foreach ($enabledModules as $moduleName) {
            \App\Models\Modules\Module::create([
                'name' => ucfirst($moduleName),
                'status' => 'active',
                'subscription_start' => now(),
                'trial_end' => now()->addDays(14),
            ]);
        }
        // ============================================

        // Assign Super Admin role
        $adminRole = Role::where('name', 'Super Admin')
            ->where('guard_name', 'web')
            ->first();

        if ($adminRole) {
            $user->assignRole($adminRole);
        } else {
            // Fallback to Admin if Super Admin doesn't exist
            $adminRole = Role::where('name', 'Admin')
                ->where('guard_name', 'web')
                ->first();

            if ($adminRole) {
                $user->assignRole($adminRole);
            } else {
                throw new Exception("No admin role found. Make sure roles are seeded.");
            }
        }

        $this->resetDefaultConnection();

        Log::info("Owner user created", [
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'email' => $user->email,
        ]);

        return $user;
    } catch (Exception $e) {
        $this->resetDefaultConnection();
        throw new Exception("Failed to create owner user: " . $e->getMessage());
    }
}
    /**
     * Create team members in tenant database
     */
    protected function createTeamMembers(Tenant $tenant, array $teamMembers, int $invitedBy): array
    {
        $this->switchToTenantConnection($tenant);

        $createdUsers = [];

        try {
            foreach ($teamMembers as $member) {
                // Skip if email is same as owner
                if (User::where('email', $member['email'])->exists()) {
                    continue;
                }

                $user = User::create([
                    'name' => $member['name'] ?? $this->extractNameFromEmail($member['email']),
                    'email' => $member['email'],
                    'password' => 'P@ssw0rd', // Will be set when user completes profile
                    'status' => User::STATUS_PENDING,
                    'is_owner' => false,
                    'profile_completed' => false,
                    'invited_by' => $invitedBy,
                    'invited_at' => now(),
                    'invitation_token' => Str::random(64),
                ]);

                // Assign role (default to Assistant if not specified)
                $roleName = $member['role'] ?? 'Assistant';
                $role = Role::where('name', $roleName)
                    ->where('guard_name', 'web')
                    ->first();

                if ($role) {
                    $user->assignRole($role);
                }

                $createdUsers[] = $user;

                Log::info("Team member created", [
                    'tenant_id' => $tenant->id,
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'role' => $roleName,
                ]);
            }

            $this->resetDefaultConnection();

            return $createdUsers;
        } catch (Exception $e) {
            $this->resetDefaultConnection();
            Log::error("Failed to create some team members", [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
            ]);
            // Don't throw - team member creation failure shouldn't fail entire registration
            return $createdUsers;
        }
    }

    /**
     * Switch to tenant database connection
     */
    protected function switchToTenantConnection(Tenant $tenant): void
    {
        Config::set('database.connections.tenant.database', $tenant->database);
        DB::purge('tenant');
        DB::reconnect('tenant');
        Config::set('database.default', 'tenant');
    }

    /**
     * Reset to default database connection
     */
    protected function resetDefaultConnection(): void
    {
        Config::set('database.default', env('DB_CONNECTION', 'mysql'));
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
     * Seed roles and permissions for tenant
     */
    protected function seedRolesAndPermissions(Tenant $tenant): void
    {
        $this->switchToTenantConnection($tenant);

        try {
            // Clear cache
            try {
                Artisan::call('cache:clear', ['--quiet' => true]);
            } catch (Exception $e) {
                // Ignore cache errors during initial setup
            }

            // Define permissions by module
            $permissions = [
                // Sales Module
                'leads.view',
                'leads.create',
                'leads.edit',
                'leads.delete',
                'deals.view',
                'deals.create',
                'deals.edit',
                'deals.delete',
                'proposals.view',
                'proposals.create',
                'proposals.edit',
                'proposals.delete',
                'invoices.view',
                'invoices.create',
                'invoices.edit',
                'invoices.delete',

                // General
                'reports.view',
                'settings.view',
                'settings.edit',
                'users.view',
                'users.create',
                'users.edit',
                'users.delete',
                'roles.view',
                'roles.edit',
            ];

            // Create all permissions
            foreach ($permissions as $permission) {
                Permission::firstOrCreate([
                    'name' => $permission,
                    'guard_name' => 'web',
                ]);
            }

            // Create roles with their permissions
            $roles = [
                'Super Admin' => Permission::all()->pluck('name')->toArray(),

                'Admin' => [
                    'leads.view',
                    'leads.create',
                    'leads.edit',
                    'leads.delete',
                    'deals.view',
                    'deals.create',
                    'deals.edit',
                    'deals.delete',
                    'proposals.view',
                    'proposals.create',
                    'proposals.edit',
                    'proposals.delete',
                    'invoices.view',
                    'invoices.create',
                    'invoices.edit',
                    'invoices.delete',
                    'reports.view',
                    'settings.view',
                    'settings.edit',
                    'users.view',
                    'users.create',
                    'users.edit',
                ],

                'Assistant' => [
                    'leads.view',
                    'leads.create',
                    'leads.edit',
                    'deals.view',
                    'deals.create',
                    'deals.edit',
                    'proposals.view',
                    'proposals.create',
                    'proposals.edit',
                    'invoices.view',
                    'invoices.create',
                    'reports.view',
                ],

                'Sales' => [
                    'leads.view',
                    'leads.create',
                    'leads.edit',
                    'deals.view',
                    'deals.create',
                    'deals.edit',
                    'proposals.view',
                    'proposals.create',
                    'invoices.view',
                ],

                'Finance' => [
                    'invoices.view',
                    'invoices.create',
                    'invoices.edit',
                    'invoices.delete',
                    'reports.view',
                ],
            ];

            foreach ($roles as $roleName => $rolePermissions) {
                $role = Role::firstOrCreate([
                    'name' => $roleName,
                    'guard_name' => 'web',
                ]);
                $role->syncPermissions($rolePermissions);
            }

            $this->resetDefaultConnection();

            Log::info("Roles and permissions seeded", ['tenant_id' => $tenant->id]);
        } catch (Exception $e) {
            $this->resetDefaultConnection();
            Log::error("Failed to seed roles/permissions", [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
            ]);
            // Don't throw - this shouldn't fail tenant creation
        }
    }
}
