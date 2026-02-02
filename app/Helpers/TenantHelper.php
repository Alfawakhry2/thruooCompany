<?php

namespace App\Helpers;

use App\Models\Landlord\Company;
use Illuminate\Support\Str;

/**
 * Tenant Helper
 * Utility methods for working with companies (tenants)
 */
class TenantHelper
{
    /**
     * Get current company
     */
    public static function current(): ?Company
    {
        return Company::current();
    }

    /**
     * Get current company ID
     */
    public static function currentId(): ?string
    {
        return optional(static::current())->id;
    }

    /**
     * Get current subdomain
     */
    public static function currentSubdomain(): ?string
    {
        return optional(static::current())->subdomain;
    }

    /**
     * Check if there is a current company
     */
    public static function hasCompany(): bool
    {
        return static::current() !== null;
    }

    /**
     * Switch to a specific company
     */
    public static function switchTo(Company $company): void
    {
        $company->makeCurrent();
    }

    /**
     * Forget current company
     */
    public static function forget(): void
    {
        Company::forgetCurrent();
    }

    /**
     * Run code in the context of a specific company
     */
    public static function runForCompany(Company $company, callable $callback)
    {
        $previousCompany = static::current();

        try {
            static::switchTo($company);
            return $callback($company);
        } finally {
            if ($previousCompany) {
                static::switchTo($previousCompany);
            } else {
                static::forget();
            }
        }
    }

    /**
     * Generate subdomain from name
     */
    public static function generateSubdomain(string $name): string
    {
        return Company::generateSubdomain($name);
    }

    /**
     * Check if subdomain is available
     */
    public static function isSubdomainAvailable(string $subdomain): bool
    {
        return Company::isSubdomainAvailable($subdomain);
    }

    /**
     * Find company by subdomain
     */
    public static function findBySubdomain(string $subdomain): ?Company
    {
        return Company::on('mysql')
            ->where('subdomain', $subdomain)
            ->first();
    }

    /**
     * Find company by ID
     */
    public static function findById(string $id): ?Company
    {
        return Company::on('mysql')->find($id);
    }

    /**
     * Get all active companies
     */
    public static function allActive()
    {
        return Company::on('mysql')
            ->where('status', Company::STATUS_ACTIVE)
            ->get();
    }

    /**
     * Get company database name
     */
    public static function databaseName(Company $company): string
    {
        return $company->database;
    }

    /**
     * Check if company database exists
     */
    public static function databaseExists(Company $company): bool
    {
        try {
            $databases = \DB::select('SHOW DATABASES LIKE ?', [$company->database]);
            return !empty($databases);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get full domain for company
     */
    public static function fullDomain(Company $company): string
    {
        return $company->full_domain;
    }

    /**
     * Get full URL for company
     */
    public static function url(Company $company): string
    {
        return $company->url;
    }

    /**
     * Get company stats (users, leads, etc.)
     */
    public static function stats(Company $company = null): array
    {
        $company = $company ?? static::current();

        if (!$company) {
            return [];
        }

        return static::runForCompany($company, function ($company) {
            try {
                return [
                    'users_count' => \DB::table('users')->count(),
                    'leads_count' => \DB::table('leads')->count(),
                    // Add more stats as needed
                ];
            } catch (\Exception $e) {
                return [];
            }
        });
    }
}
