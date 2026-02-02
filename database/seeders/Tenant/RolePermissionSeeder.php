<?php

namespace Database\Seeders\Tenant;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

/**
 * Role and Permission Seeder for Tenant Databases
 *
 * This seeder is run for each new tenant database during registration
 * It creates default roles and permissions for the tenant's company
 */
class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // ====================================================================
        // CREATE PERMISSIONS
        // ====================================================================

        $permissions = [
            // Lead Management
            'view_leads' => 'View all leads',
            'create_leads' => 'Create new leads',
            'edit_leads' => 'Edit existing leads',
            'delete_leads' => 'Delete leads',
            'reassign_leads' => 'Reassign leads to other users',
            'convert_leads' => 'Convert leads to customers',
            'dismiss_leads' => 'Dismiss/archive leads',

            // User Management
            'view_users' => 'View all users',
            'create_users' => 'Create new users',
            'edit_users' => 'Edit user details',
            'delete_users' => 'Delete users',
            'manage_roles' => 'Manage user roles and permissions',
            'invite_users' => 'Send user invitations',

            // Team Management
            'view_teams' => 'View all teams',
            'manage_teams' => 'Create and manage teams',
            'assign_team_members' => 'Assign users to teams',

            // Settings Management
            'view_settings' => 'View system settings',
            'edit_settings' => 'Edit system settings',
            'manage_modules' => 'Enable/disable modules',
            'manage_lead_sources' => 'Manage lead sources',
            'manage_lead_statuses' => 'Manage lead statuses',
            'manage_branches' => 'Manage company branches',
            'manage_departments' => 'Manage departments',

            // Product & Service Management
            'view_products' => 'View products',
            'manage_products' => 'Create and manage products',
            'view_services' => 'View services',
            'manage_services' => 'Create and manage services',
            'manage_categories' => 'Manage product/service categories',
            'manage_vendors' => 'Manage vendors',

            // Contract Management
            'view_contracts' => 'View contracts',
            'create_contracts' => 'Create new contracts',
            'edit_contracts' => 'Edit contracts',
            'delete_contracts' => 'Delete contracts',
            'manage_contract_templates' => 'Manage contract templates',

            // Financial Operations
            'view_financial' => 'View financial data',
            'manage_invoices' => 'Create and manage invoices',
            'manage_payments' => 'Process payments',
            'manage_taxes' => 'Manage tax settings',
            'manage_currencies' => 'Manage currency settings',
            'manage_payment_methods' => 'Manage payment methods',

            // Target Management
            'view_targets' => 'View sales targets',
            'manage_targets' => 'Create and manage sales targets',
            'view_team_performance' => 'View team performance metrics',

            // Reports & Analytics
            'view_reports' => 'View reports and analytics',
            'export_data' => 'Export data to files',
            'view_activity_logs' => 'View activity logs',

            // Account Settings
            'edit_personal_info' => 'Edit own personal information',
            'edit_company_info' => 'Edit company information',
            'manage_company_details' => 'Manage company details and branding',
        ];

        foreach ($permissions as $name => $description) {
            Permission::create([
                'name' => $name,
                'guard_name' => 'web',
            ]);
        }

        // ====================================================================
        // CREATE ROLES WITH PERMISSIONS
        // ====================================================================

        // ----------------------------------------------------------------
        // ADMIN ROLE - Full System Access
        // ----------------------------------------------------------------
        $adminRole = Role::create([
            'name' => 'Admin',
            'guard_name' => 'web',
        ]);

        // Admin gets ALL permissions
        $adminRole->givePermissionTo(Permission::all());

        // ----------------------------------------------------------------
        // SALES ROLE - Sales Operations Focus
        // ----------------------------------------------------------------
        $salesRole = Role::create([
            'name' => 'Sales',
            'guard_name' => 'web',
        ]);

        $salesRole->givePermissionTo([
            // Lead Management
            'view_leads',
            'create_leads',
            'edit_leads',
            'reassign_leads',
            'convert_leads',

            // Limited User Access
            'view_users',

            // Team Access
            'view_teams',

            // View Settings (no edit)
            'view_settings',

            // Product/Service View
            'view_products',
            'view_services',

            // Contract Management
            'view_contracts',
            'create_contracts',
            'edit_contracts',

            // Target Tracking
            'view_targets',
            'view_team_performance',

            // Reports
            'view_reports',

            // Personal Settings
            'edit_personal_info',
        ]);

        // ----------------------------------------------------------------
        // FINANCE ROLE - Financial Operations Focus
        // ----------------------------------------------------------------
        $financeRole = Role::create([
            'name' => 'Finance',
            'guard_name' => 'web',
        ]);

        $financeRole->givePermissionTo([
            // View Leads (for financial data)
            'view_leads',

            // Limited User Access
            'view_users',

            // View Settings
            'view_settings',

            // Product/Service View
            'view_products',
            'view_services',

            // Contract View
            'view_contracts',

            // Full Financial Access
            'view_financial',
            'manage_invoices',
            'manage_payments',
            'manage_taxes',
            'manage_currencies',
            'manage_payment_methods',

            // Reports & Export
            'view_reports',
            'export_data',
            'view_activity_logs',

            // Personal Settings
            'edit_personal_info',
        ]);

        // ----------------------------------------------------------------
        // ASSISTANT ROLE - View-Only Access with Limited Actions
        // ----------------------------------------------------------------
        $assistantRole = Role::create([
            'name' => 'Assistant',
            'guard_name' => 'web',
        ]);

        $assistantRole->givePermissionTo([
            // View Access Only
            'view_leads',
            'view_users',
            'view_teams',
            'view_settings',
            'view_products',
            'view_services',
            'view_contracts',
            'view_targets',
            'view_reports',

            // Personal Settings
            'edit_personal_info',
        ]);

        // ----------------------------------------------------------------
        // MANAGER ROLE - Team Management Focus (Optional)
        // ----------------------------------------------------------------
        $managerRole = Role::create([
            'name' => 'Manager',
            'guard_name' => 'web',
        ]);

        $managerRole->givePermissionTo([
            // Lead Management
            'view_leads',
            'create_leads',
            'edit_leads',
            'reassign_leads',
            'convert_leads',
            'dismiss_leads',

            // User Management
            'view_users',
            'invite_users',

            // Full Team Management
            'view_teams',
            'manage_teams',
            'assign_team_members',

            // Settings View
            'view_settings',

            // Product/Service Management
            'view_products',
            'manage_products',
            'view_services',
            'manage_services',

            // Contract Management
            'view_contracts',
            'create_contracts',
            'edit_contracts',
            'manage_contract_templates',

            // Target Management
            'view_targets',
            'manage_targets',
            'view_team_performance',

            // Reports
            'view_reports',
            'export_data',
            'view_activity_logs',

            // Personal Settings
            'edit_personal_info',
        ]);

        $this->command->info('✓ Roles and permissions seeded successfully');
        $this->command->info('  - Created ' . Permission::count() . ' permissions');
        $this->command->info('  - Created ' . Role::count() . ' roles');
    }
}
