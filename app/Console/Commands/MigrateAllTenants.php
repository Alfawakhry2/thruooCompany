<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Landlord\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Artisan;

class MigrateAllTenants extends Command
{
    protected $signature = 'tenants:migrate {--fresh : Drop all tables and re-run all migrations} {--seed : Seed the database after migrating}';
    protected $description = 'Run migrations for all tenants';

    public function handle()
    {
        $fresh = $this->option('fresh');
        $seed = $this->option('seed');

        // IMPORTANT: Get tenants from landlord database explicitly
        $tenants = Tenant::on('mysql')
            ->where('status', 'active')
            ->get();

        if ($tenants->isEmpty()) {
            $this->error('No active tenants found!');
            return 1;
        }

        $this->info("Found {$tenants->count()} active tenant(s)");
        $this->newLine();

        $successCount = 0;
        $failCount = 0;

        foreach ($tenants as $tenant) {
            $this->info("Processing tenant: {$tenant->name} ({$tenant->subdomain})");
            $this->info("Database: {$tenant->database}");

            try {
                // CRITICAL: Configure tenant connection properly
                Config::set('database.connections.tenant.database', $tenant->database);

                // Purge and reconnect
                DB::purge('tenant');
                DB::reconnect('tenant');

                // Test connection
                try {
                    DB::connection('tenant')->getPdo();
                    $this->info("✓ Connected to database: {$tenant->database}");
                } catch (\Exception $e) {
                    throw new \Exception("Failed to connect to tenant database: " . $e->getMessage());
                }

                // Run migrations
                if ($fresh) {
                    $this->info("Running fresh migrations...");
                    Artisan::call('migrate:fresh', [
                        '--database' => 'tenant',
                        '--path' => 'database/migrations/tenant',
                        '--force' => true,
                    ]);
                } else {
                    $this->info("Running migrations...");
                    Artisan::call('migrate', [
                        '--database' => 'tenant',
                        '--path' => 'database/migrations/tenant',
                        '--force' => true,
                    ]);
                }

                // Get migration output
                $output = Artisan::output();
                $this->line($output);

                // Run seeder if requested
                if ($seed) {
                    $this->info("Running seeders...");
                    Artisan::call('db:seed', [
                        '--database' => 'tenant',
                        '--force' => true,
                    ]);
                    $this->line(Artisan::output());
                }

                $this->info("✅ Migration completed for: {$tenant->name}");
                $successCount++;

            } catch (\Exception $e) {
                $this->error("❌ Migration failed for: {$tenant->name}");
                $this->error("Error: " . $e->getMessage());
                $this->error("Trace: " . $e->getTraceAsString());
                $failCount++;
            }

            $this->newLine();
        }

        // Reset to default connection
        Config::set('database.default', 'mysql');
        DB::purge('tenant');

        // Summary
        $this->newLine();
        $this->info("=================================");
        $this->info("Migration Summary:");
        $this->info("Total tenants: {$tenants->count()}");
        $this->info("✅ Successful: {$successCount}");
        if ($failCount > 0) {
            $this->error("❌ Failed: {$failCount}");
        }
        $this->info("=================================");

        return $failCount > 0 ? 1 : 0;
    }
}
