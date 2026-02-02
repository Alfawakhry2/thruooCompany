<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Landlord\Company;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * Resolve Tenant From Path Middleware
 * 
 * Extracts company slug from URL path and makes it the current tenant
 * Pattern: /api/ahmed/... -> company slug = "ahmed"
 */
class ResolveTenantFromPath
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Extract company slug from route parameter
        $companySlug = $request->route('companySlug');

        if (!$companySlug) {
            return response()->json([
                'success' => false,
                'message' => 'Company slug is required in the URL path.',
            ], 400);
        }

        // Find company by slug
        $company = Company::on('mysql')
            ->where('slug', $companySlug)
            ->where('status', Company::STATUS_ACTIVE)
            ->first();

        if (!$company) {
            return response()->json([
                'success' => false,
                'message' => 'Company not found or inactive.',
            ], 404);
        }

        // 1. Activate Tenant (Logic from Spatie)
        // This sets the environment but might set DB config incorrectly if logic is buggy
        try {
            $company->makeCurrent();
        } catch (\Exception $e) {
            Log::error('makeCurrent failed: ' . $e->getMessage());
        }

        // 2. FORCE Correct Database Configuration
        // This ensures the connection points to the correct database
        // We override whatever makeCurrent might have set if it failed to set correctly
        Config::set('database.connections.tenant.database', $company->database);

        // 3. Purge and Reconnect
        // This closes any existing connection and forces a fresh connection on next query
        DB::purge('tenant');
        DB::reconnect('tenant');

        // Log for debugging (optional - remove in production)
        if (app()->environment('local')) {
            $tenantDatabase = Config::get('database.connections.tenant.database');

            Log::info('Company resolved from path', [
                'company_id' => $company->id,
                'company_name' => $company->name,
                'company_slug' => $company->slug,
                'company_database' => $company->database,
                'tenant_connection_database' => $tenantDatabase,
            ]);
        }

        // Store in request for later use
        $request->merge(['current_company' => $company]);

        return $next($request);
    }
}
