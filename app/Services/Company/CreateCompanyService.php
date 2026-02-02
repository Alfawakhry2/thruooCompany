<?php

namespace App\Services\Company;

use App\Models\Landlord\Company;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Company Creation Service
 * Handles database creation and setup for new companies
 */
class CreateCompanyService
{
    /**
     * Create database for company
     */
    public function createCompanyDatabase(Company $company): void
    {
        $databaseName = $company->database;

        // Create database
        DB::statement("CREATE DATABASE IF NOT EXISTS `{$databaseName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        Log::info("Created database: {$databaseName}");
    }

    /**
     * Run migrations on company database
     */
    public function runCompanyMigrations(Company $company): void
    {
        // Make company current (switches database)
        $company->makeCurrent();

        try {
            // Run migrations on tenant connection
            Artisan::call('migrate', [
                '--database' => 'tenant',
                '--path' => 'database/migrations/tenant',
                '--force' => true,
            ]);

            Log::info("Ran migrations for company: {$company->subdomain}");

        } catch (\Exception $e) {
            Log::error("Migration error for company {$company->subdomain}: " . $e->getMessage());
            throw $e;
        } finally {
            // Forget current tenant
            Company::forgetCurrent();
        }
    }

    /**
     * Delete company database
     */
    public function deleteCompanyDatabase(Company $company): void
    {
        $databaseName = $company->database;

        try {
            DB::statement("DROP DATABASE IF EXISTS `{$databaseName}`");
            Log::info("Deleted database: {$databaseName}");
        } catch (\Exception $e) {
            Log::error("Error deleting database {$databaseName}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Verify company database exists
     */
    public function verifyDatabase(Company $company): bool
    {
        try {
            $databases = DB::select('SHOW DATABASES LIKE ?', [$company->database]);
            return !empty($databases);
        } catch (\Exception $e) {
            Log::error("Error verifying database {$company->database}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get database size
     */
    public function getDatabaseSize(Company $company): ?string
    {
        try {
            $result = DB::select(
                "SELECT
                    ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as size_mb
                FROM information_schema.TABLES
                WHERE table_schema = ?",
                [$company->database]
            );

            return $result[0]->size_mb . ' MB';
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Seed company database with initial data
     */
    public function seedCompanyDatabase(Company $company): void
    {
        $company->makeCurrent();

        try {
            // Run seeders on tenant connection
            Artisan::call('db:seed', [
                '--database' => 'tenant',
                '--class' => 'DatabaseSeeder',
                '--force' => true,
            ]);

            Log::info("Seeded database for company: {$company->subdomain}");

        } catch (\Exception $e) {
            Log::error("Seeder error for company {$company->subdomain}: " . $e->getMessage());
            throw $e;
        } finally {
            Company::forgetCurrent();
        }
    }
}
