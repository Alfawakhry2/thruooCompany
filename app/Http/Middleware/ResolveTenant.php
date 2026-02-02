<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Landlord\Company;

class ResolveTenant
{
    /**
     * Handle an incoming request.
     * Resolves company (tenant) from subdomain using CompanyTenantFinder
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Spatie's tenant finder (CompanyTenantFinder) automatically resolves company from request
        // and makes it current. We just need to verify it exists and is active.
        $company = Company::current();

        if (!$company) {
            return response()->json([
                'success' => false,
                'message' => 'Company not found. Please check your subdomain.',
            ], 404);
        }

        // Check if company is active
        if ($company->status !== Company::STATUS_ACTIVE) {
            return response()->json([
                'success' => false,
                'message' => 'Company is not active. Please contact support.',
            ], 403);
        }

        // Explicitly make company current to ensure database connection is switched
        // This is important for Sanctum to find tokens in the correct database
        $company->makeCurrent();

        $currentConnection = Config::get('database.default');
        $tenantDatabase = Config::get('database.connections.tenant.database');

        // Log for debugging (optional - remove in production)
        if (app()->environment('local')) {
            Log::info('Company resolved', [
                'company_id' => $company->id,
                'company_name' => $company->name,
                'company_subdomain' => $company->subdomain,
                'company_database' => $company->database,
                'current_connection' => $currentConnection,
                'tenant_connection_database' => $tenantDatabase,
            ]);
        }

        return $next($request);
    }
}

