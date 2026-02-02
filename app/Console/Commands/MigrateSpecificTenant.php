<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Landlord\Tenant;

class MigrateSpecificTenant extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'tenant:migrate {subdomain} {--fresh : Drop all tables and re-run all migrations} {--seed : Seed the database after migrating}';

    /**
     * The console command description.
     */
    protected $description = 'Run migrations for a specific tenant';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $subdomain = $this->argument('subdomain');
        $fresh = $this->option('fresh');
        $seed = $this->option('seed');

        // Find tenant
        $tenant = Tenant::on('mysql')
            ->where('subdomain', $subdomain)
            ->first();

        if (!$tenant) {
            $this->error("Tenant not found: {$subdomain}");
            return 1;
        }

        $this->info("Found tenant: {$tenant->name} ({$tenant->subdomain})");
        $this->info("Status: {$tenant->status}");
        $this->newLine();

        try {
            // Make tenant current
            $tenant->makeCurrent();

            // Run migrations
            if ($fresh) {
                $this->info("Running fresh migrations...");
                $this->call('migrate:fresh', [
                    '--path' => 'database/migrations/tenant',
                    '--force' => true,
                ]);
            } else {
                $this->info("Running migrations...");
                $this->call('migrate', [
                    '--path' => 'database/migrations/tenant',
                    '--force' => true,
                ]);
            }

            // Run seeder if requested
            if ($seed) {
                $this->info("Running seeder...");
                $this->call('db:seed', [
                    '--force' => true,
                ]);
            }

            $this->newLine();
            $this->info("✅ Migration completed successfully!");

            // Forget current tenant
            Tenant::forgetCurrent();

            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Migration failed!");
            $this->error("Error: " . $e->getMessage());
            
            // Forget current tenant
            Tenant::forgetCurrent();
            
            return 1;
        }
    }
}
