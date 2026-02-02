<?php

namespace App\Multitenancy\TenantFinder;

use App\Models\Landlord\Tenant;
use Illuminate\Http\Request;
use Spatie\Multitenancy\TenantFinder\TenantFinder;

class SubdomainTenantFinder extends TenantFinder
{
    /**
     * Find tenant by subdomain from request host
     *
     * @param Request $request
     * @return Tenant|null
     */
    public function findForRequest(Request $request): ?Tenant
    {
        $host = $request->getHost();

        // Remove port if present (e.g., acme.thruoo.local:8000 -> acme.thruoo.local)
        $host = preg_replace('/:\d+$/', '', $host);

        // Extract subdomain from host (e.g., demo.thruoo.local -> demo)
        $tenantDomain = config('app.tenant_domain', 'thruoo.local');

        // Check if it's a subdomain request
        if (str_ends_with($host, '.' . $tenantDomain)) {
            $subdomain = str_replace('.' . $tenantDomain, '', $host);
        } elseif ($host === $tenantDomain) {
            // Main domain - no tenant
            return null;
        } else {
            // Check for custom domain (use landlord connection)
            $tenant = Tenant::on('mysql')->where('domain', $host)
                ->where('status', 'active')
                ->first();

            if ($tenant) {
                return $tenant;
            }

            // Try to extract subdomain from any domain
            $parts = explode('.', $host);
            if (count($parts) >= 2) {
                $subdomain = $parts[0];
            } else {
                return null;
            }
        }

        // Find tenant by subdomain (use landlord connection)
        return Tenant::on('mysql')->where('subdomain', $subdomain)
            ->where('status', 'active')
            ->first();
    }
}

