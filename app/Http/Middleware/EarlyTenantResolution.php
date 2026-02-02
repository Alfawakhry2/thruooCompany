<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Landlord\Company;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Early Tenant Resolution Middleware
 * 
 * This middleware runs VERY EARLY in the request lifecycle,
 * BEFORE Sanctum tries to authenticate, to ensure the tenant
 * database is configured when Sanctum looks for tokens.
 */
class EarlyTenantResolution
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Only process if there's a companySlug in the route
        $companySlug = $request->route('companySlug');
        
        if (!$companySlug) {
            return $next($request);
        }

        // Check if tenant is already resolved
        if (Company::checkCurrent()) {
            return $next($request);
        }

        try {
            // Find company by slug (using landlord connection)
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

            // Validate database configuration
            if (empty($company->database)) {
                Log::error('Company has no database configured', [
                    'company_id' => $company->id,
                    'company_slug' => $company->slug,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Company database configuration is invalid.',
                ], 500);
            }

            // Configure the tenant database connection
            Config::set('database.connections.tenant.database', $company->database);

            // Purge and reconnect
            DB::purge('tenant');
            
            // Test connection
            try {
                DB::connection('tenant')->getPdo();
            } catch (\Exception $e) {
                Log::error('Failed to connect to tenant database', [
                    'company_id' => $company->id,
                    'company_slug' => $company->slug,
                    'database' => $company->database,
                    'error' => $e->getMessage(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Unable to connect to company database.',
                ], 500);
            }

            // Make company current
            $company->makeCurrent();

            // Store in request
            $request->attributes->set('current_company', $company);

            // Optional debug logging
            if (config('app.debug')) {
                Log::info('Early tenant resolution successful', [
                    'company_id' => $company->id,
                    'company_slug' => $company->slug,
                    'database' => $company->database,
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Error in early tenant resolution', [
                'company_slug' => $companySlug,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while resolving the tenant.',
            ], 500);
        }

        return $next($request);
    }
}