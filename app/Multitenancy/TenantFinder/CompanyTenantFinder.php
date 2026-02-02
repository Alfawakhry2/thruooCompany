<?php

namespace App\Multitenancy\TenantFinder;

use App\Models\Landlord\Company;
use Illuminate\Http\Request;
use Spatie\Multitenancy\TenantFinder\TenantFinder;

/**
 * Company Tenant Finder
 * Resolves tenant by Company slug from URL path (not subdomain)
 * 
 * URL Pattern: thruoo.com/ahmed/... → company slug = "ahmed"
 */
class CompanyTenantFinder extends TenantFinder
{
    /**
     * Find company (tenant) by slug from request path
     *
     * @param Request $request
     * @return Company|null
     */
    public function findForRequest(Request $request): ?Company
    {
        $host = $request->getHost();
        $path = $request->path();

        // Remove port if present (e.g., thruoo.local:8000 → thruoo.local)
        $host = preg_replace('/:\d+$/', '', $host);

        $tenantDomain = config('app.tenant_domain', 'thruoo.local');

        // Check if we're on the main domain
        if ($host !== $tenantDomain) {
            // Check for custom domain (use landlord connection)
            $company = Company::on('mysql')
                ->where('domain', $host)
                ->where('status', Company::STATUS_ACTIVE)
                ->first();

            if ($company) {
                return $company;
            }

            // Not main domain and not custom domain - invalid
            return null;
        }

        // Extract company slug from path
        // Pattern: /ahmed/api/... or /ahmed/modules/... or /ahmed
        $pathSegments = explode('/', trim($path, '/'));

        // If no path or empty first segment - landlord route
        if (empty($pathSegments[0])) {
            return null;
        }

        // Check if first segment is a landlord route prefix
        $landlordPrefixes = ['api', 'registration', 'auth'];
        if (in_array($pathSegments[0], $landlordPrefixes)) {
            return null;
        }

        // First segment is the company slug
        $slug = $pathSegments[0];

        // Find company by slug (use landlord connection)
        return Company::on('mysql')
            ->where('slug', $slug)
            ->where('status', Company::STATUS_ACTIVE)
            ->first();
    }
}
