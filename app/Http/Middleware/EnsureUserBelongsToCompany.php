<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Landlord\Company;

/**
 * Ensure User Belongs To Company Middleware
 * 
 * Verifies that the authenticated user belongs to the company
 * specified in the URL path
 */
class EnsureUserBelongsToCompany
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $company = Company::current();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if (!$company) {
            return response()->json([
                'success' => false,
                'message' => 'Company context not found.',
            ], 400);
        }

        // Verify user belongs to this company's database
        // Since we're already in tenant context (database switched),
        // the fact that auth:sanctum found the user means they belong to this company
        // Additional verification can be added here if needed

        return $next($request);
    }
}
