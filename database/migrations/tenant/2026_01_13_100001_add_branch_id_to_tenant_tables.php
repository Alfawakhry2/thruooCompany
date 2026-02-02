<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add branch_id to all tenant tables that need branch-level data isolation
 * 
 * Tables affected:
 * - leads (required for branch-specific lead management)
 * - contracts (inherits from lead or direct assignment)
 * - contract_templates (can be branch-specific or company-wide)
 * - teams (branch-specific teams)
 * - targets (can target specific branches)
 * - activity_logs (tracks branch context)
 * - categories (optional branch scope)
 * - taxes (usually company-wide, optional branch)
 * - currencies (usually company-wide, optional branch)
 * - payment_methods (optional branch scope)
 * - vendors (optional branch scope)
 * - units (usually company-wide)
 * - attributes (usually company-wide)
 */
return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Leads - Core branch-specific data
        if (Schema::hasTable('leads') && !Schema::hasColumn('leads', 'branch_id')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->foreignId('branch_id')
                    ->nullable()
                    ->after('module_id')
                    ->constrained('branches')
                    ->onDelete('set null');

                $table->index('branch_id');
            });
        }

        // Contracts - Branch-specific documents
        if (Schema::hasTable('contracts') && !Schema::hasColumn('contracts', 'branch_id')) {
            Schema::table('contracts', function (Blueprint $table) {
                $table->foreignId('branch_id')
                    ->nullable()
                    ->after('module_id')
                    ->constrained('branches')
                    ->onDelete('set null');

                $table->index('branch_id');
            });
        }

        // Contract Templates - Can be branch-specific or company-wide
        if (Schema::hasTable('contract_templates') && !Schema::hasColumn('contract_templates', 'branch_id')) {
            Schema::table('contract_templates', function (Blueprint $table) {
                $table->foreignId('branch_id')
                    ->nullable()
                    ->after('module_id')
                    ->constrained('branches')
                    ->onDelete('set null');

                $table->index('branch_id');
            });
        }

        // Teams - Branch-specific teams
        if (Schema::hasTable('teams') && !Schema::hasColumn('teams', 'branch_id')) {
            Schema::table('teams', function (Blueprint $table) {
                $table->foreignId('branch_id')
                    ->nullable()
                    ->after('module_id')
                    ->constrained('branches')
                    ->onDelete('set null');

                $table->index('branch_id');
            });
        }

        // Team Members - Branch context (inherits from team usually)
        if (Schema::hasTable('team_members') && !Schema::hasColumn('team_members', 'branch_id')) {
            Schema::table('team_members', function (Blueprint $table) {
                $table->foreignId('branch_id')
                    ->nullable()
                    ->after('module_id')
                    ->constrained('branches')
                    ->onDelete('set null');

                $table->index('branch_id');
            });
        }

        // Targets - Can be branch-specific or company-wide
        if (Schema::hasTable('targets') && !Schema::hasColumn('targets', 'branch_id')) {
            Schema::table('targets', function (Blueprint $table) {
                $table->foreignId('branch_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('branches')
                    ->onDelete('set null');

                $table->index('branch_id');
            });
        }

        // Activity Logs - Tracks branch context
        if (Schema::hasTable('activity_logs') && !Schema::hasColumn('activity_logs', 'branch_id')) {
            Schema::table('activity_logs', function (Blueprint $table) {
                $table->foreignId('branch_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('branches')
                    ->onDelete('set null');

                $table->index('branch_id');
            });
        }

        // Categories - Can be branch-specific or company-wide
        if (Schema::hasTable('categories') && !Schema::hasColumn('categories', 'branch_id')) {
            Schema::table('categories', function (Blueprint $table) {
                $table->foreignId('branch_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('branches')
                    ->onDelete('set null');

                $table->index('branch_id');
            });
        }

        // Taxes - Usually company-wide, optional branch-specific
        if (Schema::hasTable('taxes') && !Schema::hasColumn('taxes', 'branch_id')) {
            Schema::table('taxes', function (Blueprint $table) {
                $table->foreignId('branch_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('branches')
                    ->onDelete('set null');

                $table->index('branch_id');
            });
        }

        // Currencies - Usually company-wide
        if (Schema::hasTable('currencies') && !Schema::hasColumn('currencies', 'branch_id')) {
            Schema::table('currencies', function (Blueprint $table) {
                $table->foreignId('branch_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('branches')
                    ->onDelete('set null');

                $table->index('branch_id');
            });
        }

        // Payment Methods - Can be branch-specific
        if (Schema::hasTable('payment_methods') && !Schema::hasColumn('payment_methods', 'branch_id')) {
            Schema::table('payment_methods', function (Blueprint $table) {
                $table->foreignId('branch_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('branches')
                    ->onDelete('set null');

                $table->index('branch_id');
            });
        }

        // Vendors - Can be branch-specific
        if (Schema::hasTable('vendors') && !Schema::hasColumn('vendors', 'branch_id')) {
            Schema::table('vendors', function (Blueprint $table) {
                $table->foreignId('branch_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('branches')
                    ->onDelete('set null');

                $table->index('branch_id');
            });
        }

        // Units - Usually company-wide
        if (Schema::hasTable('units') && !Schema::hasColumn('units', 'branch_id')) {
            Schema::table('units', function (Blueprint $table) {
                $table->foreignId('branch_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('branches')
                    ->onDelete('set null');

                $table->index('branch_id');
            });
        }

        // Attributes - Usually company-wide
        if (Schema::hasTable('attributes') && !Schema::hasColumn('attributes', 'branch_id')) {
            Schema::table('attributes', function (Blueprint $table) {
                $table->foreignId('branch_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('branches')
                    ->onDelete('set null');

                $table->index('branch_id');
            });
        }

        // Lead Sources - Can be branch-specific
        if (Schema::hasTable('lead_sources') && !Schema::hasColumn('lead_sources', 'branch_id')) {
            Schema::table('lead_sources', function (Blueprint $table) {
                $table->foreignId('branch_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('branches')
                    ->onDelete('set null');

                $table->index('branch_id');
            });
        }

        // Lead Statuses - Can be branch-specific
        if (Schema::hasTable('lead_statuses') && !Schema::hasColumn('lead_statuses', 'branch_id')) {
            Schema::table('lead_statuses', function (Blueprint $table) {
                $table->foreignId('branch_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('branches')
                    ->onDelete('set null');

                $table->index('branch_id');
            });
        }

        // Departments - Can be branch-specific
        if (Schema::hasTable('departments') && !Schema::hasColumn('departments', 'branch_id')) {
            Schema::table('departments', function (Blueprint $table) {
                $table->foreignId('branch_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('branches')
                    ->onDelete('set null');

                $table->index('branch_id');
            });
        }
        // Products - Branch-specific inventory
        if (Schema::hasTable('products') && !Schema::hasColumn('products', 'branch_id')) {
            Schema::table('products', function (Blueprint $table) {
                $table->foreignId('branch_id')
                    ->nullable()
                    ->after('module_id')
                    ->constrained('branches')
                    ->onDelete('set null');

                $table->index('branch_id');
            });
        }

        // Services - Branch-specific services
        if (Schema::hasTable('services') && !Schema::hasColumn('services', 'branch_id')) {
            Schema::table('services', function (Blueprint $table) {
                $table->foreignId('branch_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('branches')
                    ->onDelete('set null');

                $table->index('branch_id');
            });
        }

        // Product Variants - Inherits from product
        if (Schema::hasTable('product_variants') && !Schema::hasColumn('product_variants', 'branch_id')) {
            Schema::table('product_variants', function (Blueprint $table) {
                $table->foreignId('branch_id')
                    ->nullable()
                    ->after('product_id')
                    ->constrained('branches')
                    ->onDelete('set null');

                $table->index('branch_id');
            });
        }

        // Attribute Values - Usually company-wide
        if (Schema::hasTable('attribute_values') && !Schema::hasColumn('attribute_values', 'branch_id')) {
            Schema::table('attribute_values', function (Blueprint $table) {
                $table->foreignId('branch_id')
                    ->nullable()
                    ->after('attribute_id')
                    ->constrained('branches')
                    ->onDelete('set null');

                $table->index('branch_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = [
            'leads',
            'contracts',
            'contract_templates',
            'teams',
            'team_members',
            'targets',
            'activity_logs',
            'categories',
            'taxes',
            'currencies',
            'payment_methods',
            'vendors',
            'units',
            'attributes',
            'lead_sources',
            'lead_statuses',
            'departments',
            'products',
            'services',
            'product_variants',
            'attribute_values'
        ];

        foreach ($tables as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'branch_id')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropForeign(['branch_id']);
                    $table->dropIndex(['branch_id']);
                    $table->dropColumn('branch_id');
                });
            }
        }
    }
};
