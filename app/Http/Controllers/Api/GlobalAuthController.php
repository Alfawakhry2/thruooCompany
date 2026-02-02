<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Landlord\Company;
use App\Models\Landlord\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Global Authentication Controller
 * Handles login across all companies from main domain
 */
class GlobalAuthController extends Controller
{
    /**
     * Get all companies associated with an email
     *
     * POST /api/auth/companies-by-email
     * Body: { "email": "user@example.com" }
     */
    public function getCompaniesByEmail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $email = $request->email;

        // Find all companies where this email exists as a user
        $companiesWithUser = [];

        $companies = Company::on('mysql')
            ->where('status', Company::STATUS_ACTIVE)
            ->get();

        foreach ($companies as $company) {
            try {
                // Switch to company database
                $company->makeCurrent();

                // Check if user exists in this company
                $user = User::where('email', $email)->first();

                if ($user) {
                    $companiesWithUser[] = [
                        'id' => $company->id,
                        'name' => $company->name,
                        'subdomain' => $company->subdomain,
                        'domain' => $company->full_domain,
                        'url' => $company->url,
                        'logo_url' => $company->logo_url,
                        'user' => [
                            'id' => $user->id,
                            'name' => $user->name,
                            'email' => $user->email,
                        ],
                    ];
                }
            } catch (\Exception $e) {
                // Company database might not exist, skip
                Log::error("Error checking user in company {$company->subdomain}: " . $e->getMessage());
                continue;
            }
        }

        // Forget current tenant
        Company::forgetCurrent();

        if (empty($companiesWithUser)) {
            return response()->json([
                'success' => false,
                'message' => 'No companies found for this email address.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'email' => $email,
                'companies' => $companiesWithUser,
                'total' => count($companiesWithUser),
            ],
        ]);
    }

    /**
     * Login with company selection
     * Supports company_id (UUID), subdomain, or company_slug
     *
     * POST /api/auth/login-with-company
     * Body: { "company_slug": "ahmed-tech", "email": "user@example.com", "password": "..." }
     * OR: { "company_id": "uuid|subdomain", "email": "user@example.com", "password": "..." }
     */
    public function loginWithCompany(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'company_id' => 'required_without:company_slug|string',
            'company_slug' => 'required_without:company_id|string',
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        // Find company by slug (preferred), ID, or subdomain
        $companyIdentifier = $request->company_slug ?? $request->company_id;
        
        $company = Company::on('mysql')
            ->where(function ($query) use ($companyIdentifier) {
                $query->where('slug', $companyIdentifier)
                      ->orWhere('id', $companyIdentifier)
                      ->orWhere('subdomain', $companyIdentifier);
            })
            ->where('status', Company::STATUS_ACTIVE)
            ->first();

        if (!$company) {
            return response()->json([
                'success' => false,
                'message' => 'Company not found or inactive.',
            ], 404);
        }

        try {
            // Make company current (switches database)
            $company->makeCurrent();

            // Find user in company database
            $user = User::where('email', $request->email)->first();

            if (!$user) {
                Company::forgetCurrent();
                return response()->json([
                    'success' => false,
                    'message' => 'User not found in this company.',
                ], 404);
            }

            // Check password
            if (!Hash::check($request->password, $user->password)) {
                Company::forgetCurrent();
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid credentials.',
                ], 401);
            }

            // Check if user is active
            if (!$user->isActive()) {
                Company::forgetCurrent();
                return response()->json([
                    'success' => false,
                    'message' => 'Your account is inactive. Please contact your administrator.',
                ], 403);
            }

            // Generate token
            $token = $user->createToken('auth-token')->plainTextToken;

            // Forget current tenant (will be set again when user accesses path)
            Company::forgetCurrent();

            return response()->json([
                'success' => true,
                'message' => 'Login successful. Redirecting to your company...',
                'data' => [
                    'token' => $token,
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'role' => $user->role ?? null,
                    ],
                    'company' => [
                        'id' => $company->id,
                        'name' => $company->name,
                        'slug' => $company->slug,
                        'subdomain' => $company->subdomain,
                        'domain' => $company->full_domain,
                        'logo_url' => $company->logo_url,
                    ],
                    'redirect' => [
                        'url' => url("/{$company->slug}"), // Path-based URL
                        'legacy_url' => $company->url, // Subdomain URL (for backward compatibility)
                        'with_token' => true,
                    ],
                ],
            ]);

        } catch (\Exception $e) {
            Company::forgetCurrent();
            Log::error('Login error: ' . $e->getMessage());
            Log::error($e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'An error occurred during login. Please try again.',
                'error' => app()->environment('local') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Direct company login (alternative method)
     * This is an alias for loginWithCompany
     *
     * POST /api/auth/company-login
     * Body: { "company_id": "subdomain or uuid", "email": "...", "password": "..." }
     */
    public function companyLogin(Request $request)
    {
        return $this->loginWithCompany($request);
    }

    /**
     * Global login - Step 1: Get companies by email
     * Then use loginWithCompany with the company_id
     *
     * POST /api/auth/global-login
     * Body: { "email": "user@example.com", "password": "..." }
     *
     * This will:
     * 1. Find all companies where user exists
     * 2. Validate password in each company
     * 3. Return companies where user exists and password is valid
     */
    public function globalLogin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $email = $request->email;
        $password = $request->password;

        // Find all companies where this email exists as a user
        $validCompanies = [];

        $companies = Company::on('mysql')
            ->where('status', Company::STATUS_ACTIVE)
            ->get();

        foreach ($companies as $company) {
            try {
                // Switch to company database
                $company->makeCurrent();

                // Check if user exists in this company
                $user = User::where('email', $email)->first();

                if ($user && Hash::check($password, $user->password) && $user->isActive()) {
                    $validCompanies[] = [
                        'id' => $company->id,
                        'name' => $company->name,
                        'subdomain' => $company->subdomain,
                        'domain' => $company->full_domain,
                        'url' => $company->url,
                        'logo_url' => $company->logo_url,
                        'user' => [
                            'id' => $user->id,
                            'name' => $user->name,
                            'email' => $user->email,
                        ],
                    ];
                }
            } catch (\Exception $e) {
                // Company database might not exist, skip
                Log::error("Error checking user in company {$company->subdomain}: " . $e->getMessage());
                continue;
            }
        }

        // Forget current tenant
        Company::forgetCurrent();

        if (empty($validCompanies)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials or no companies found for this email address.',
            ], 401);
        }

        // If only one company, auto-login
        if (count($validCompanies) === 1) {
            $company = Company::on('mysql')->find($validCompanies[0]['id']);
            $request->merge(['company_id' => $company->id]);
            return $this->loginWithCompany($request);
        }

        // Multiple companies - return list for user to select
        return response()->json([
            'success' => true,
            'data' => [
                'email' => $email,
                'companies' => $validCompanies,
                'total' => count($validCompanies),
                'message' => 'Please select a company to continue.',
            ],
        ]);
    }

    /**
     * Get current authenticated user info
     * This endpoint works on company subdomains
     *
     * GET /api/auth/me
     */
    public function me(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $company = Company::current();

        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'is_active' => $user->is_active,
                ],
                'company' => $company ? [
                    'id' => $company->id,
                    'name' => $company->name,
                    'subdomain' => $company->subdomain,
                    'domain' => $company->full_domain,
                    'logo_url' => $company->logo_url,
                ] : null,
            ],
        ]);
    }

    /**
     * Logout
     *
     * POST /api/auth/logout
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully.',
        ]);
    }
}
