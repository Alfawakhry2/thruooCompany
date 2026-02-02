<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Landlord\Company;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class MainDomainAuthController extends Controller
{
    /**
     * Login from main domain with company_id, email, password
     * This will authenticate and redirect to company subdomain
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'company_id' => ['required', 'string', 'uuid'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            // 1. Find company in landlord database
            $company = Company::on('mysql')
                ->where('id', $request->company_id)
                ->first();

            if (!$company) {
                return response()->json([
                    'success' => false,
                    'message' => 'Company not found',
                ], 404);
            }

            // 2. Check company status
            if ($company->status !== Company::STATUS_ACTIVE) {
                return response()->json([
                    'success' => false,
                    'message' => 'Company account is not active',
                    'status' => $company->status,
                ], 403);
            }

            // Check subscription
            if (!$company->isActive()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Company subscription has expired',
                    'trial_expired' => $company->trialExpired(),
                ], 403);
            }

            // 3. Switch to company database
            $this->switchToCompanyDatabase($company);

            // 4. Find user in company database
            $user = User::where('email', $request->email)->first();

            if (!$user) {
                $this->resetDefaultConnection();
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid credentials',
                ], 401);
            }

            // 5. Verify password
            if (!Hash::check($request->password, $user->password)) {
                $this->resetDefaultConnection();
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid credentials',
                ], 401);
            }

            // 6. Check user status
            if ($user->status !== User::STATUS_ACTIVE) {
                $this->resetDefaultConnection();
                return response()->json([
                    'success' => false,
                    'message' => 'User account is not active',
                    'status' => $user->status,
                ], 403);
            }

            // 7. Create authentication token
            $token = $user->createToken('auth-token')->plainTextToken;

            $this->resetDefaultConnection();

            // 8. Return success with redirect URL
            return response()->json([
                'success' => true,
                'message' => 'Authentication successful',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'is_owner' => $user->is_owner,
                        'status' => $user->status,
                    ],
                    'company' => [
                        'id' => $company->id,
                        'name' => $company->name,
                        'subdomain' => $company->subdomain,
                        'plan' => $company->plan,
                        'enabled_modules' => $company->enabled_modules,
                    ],
                    'token' => $token,
                    'redirect_url' => $company->url,
                ],
            ], 200);

        } catch (\Exception $e) {
            $this->resetDefaultConnection();

            return response()->json([
                'success' => false,
                'message' => 'Authentication failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get list of companies for a user email (for login selection)
     */
    public function getCompaniesForEmail(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            // Check if this email belongs to a tenant owner
            $tenant = \App\Models\Landlord\Tenant::on('mysql')
                ->where('email', $request->email)
                ->first();

            if (!$tenant) {
                return response()->json([
                    'success' => false,
                    'message' => 'No account found with this email',
                ], 404);
            }

            // Get all companies for this tenant
            $companies = Company::on('mysql')
                ->where('tenant_id', $tenant->id)
                ->where('status', Company::STATUS_ACTIVE)
                ->select('id', 'name', 'subdomain', 'logo', 'plan')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'tenant' => [
                        'id' => $tenant->id,
                        'name' => $tenant->name,
                        'email' => $tenant->email,
                    ],
                    'companies' => $companies->map(function ($company) {
                        return [
                            'id' => $company->id,
                            'name' => $company->name,
                            'subdomain' => $company->subdomain,
                            'logo_url' => $company->logo_url,
                            'plan' => $company->plan,
                            'url' => $company->url,
                        ];
                    }),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch companies',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Switch to company database
     */
    protected function switchToCompanyDatabase(Company $company): void
    {
        Config::set('database.connections.tenant.database', $company->database);
        DB::purge('tenant');
        DB::reconnect('tenant');
        Config::set('database.default', 'tenant');
    }

    /**
     * Reset to default connection
     */
    protected function resetDefaultConnection(): void
    {
        Config::set('database.default', env('DB_CONNECTION', 'mysql'));
    }
}
