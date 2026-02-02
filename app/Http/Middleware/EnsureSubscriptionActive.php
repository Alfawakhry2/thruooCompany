<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Landlord\Company;

class EnsureSubscriptionActive
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $company = Company::current();

        if (!$company) {
            return response()->json([
                'success' => false,
                'message' => 'Company not found',
            ], 404);
        }

        // Check if company is active (trial, grace period, or subscription)
        if (!$company->isActive()) {
            return response()->json([
                'success' => false,
                'message' => 'Subscription has expired. Please renew your subscription.',
                'trial_ends_at' => $company->trial_ends_at?->toIso8601String(),
                'subscription_ends_at' => $company->subscription_ends_at?->toIso8601String(),
            ], 403);
        }

        return $next($request);
    }
}

