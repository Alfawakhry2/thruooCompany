<?php

namespace App\Http\Controllers\Modules\Sales\Api\Account;

use Illuminate\Http\Request;
use App\Models\Landlord\Tenant;
use App\Models\Landlord\Company;
use App\Models\Landlord\CompanyDetails;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\Api\Account\UpdatePersonalInfoRequest;
use App\Http\Requests\Api\Account\UpdateCompanyDetailsRequest;

/**
 * Account Settings Controller
 *
 * UPDATED: Compatible with new Tenant/Company architecture
 * - Tenant = Owner/Account (stored in landlord DB)
 * - Company = Actual tenant with database (stored in landlord DB)
 * - Users = Stored in tenant databases
 *
 * Changes:
 * - Now uses Company model instead of SpatieTenant
 * - Company details stored in both Company and CompanyDetails models
 * - Social media moved to CompanyDetails table
 * - Proper permission checks using Spatie Permission
 */
class AccountSettingsController extends Controller
{
    /**
     * Get current user's personal info and company details
     *
     * GET /api/account/settings
     */
    public function index(): JsonResponse
    {
        $user = Auth::user();
        $company = Company::current();

        if (!$company) {
            return response()->json([
                'success' => false,
                'message' => 'Company context not found',
            ], 404);
        }

        // Get company details if exists
        $companyDetails = $company->details;

        return response()->json([
            'success' => true,
            'data' => [
                'personal_info' => [
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'title' => $user->title,
                    'birth_year' => $user->birth_year,
                    'how_know_us' => $user->how_know_us,
                    'avatar' => $user->avatar_url,
                    'status' => $user->status,
                    'is_owner' => $user->is_owner,
                ],
                'company_details' => [
                    // Basic Info
                    'company_name' => $company->name,
                    'business_email' => $company->business_email,
                    'company_phone' => $company->phone,
                    'website' => $company->website,

                    // Location
                    'country' => $company->country,
                    'city' => $company->city,
                    'address' => $company->address,

                    // Business Details
                    'industry' => $company->industry,
                    'staff_count' => $company->staff_count,

                    // Legal Info
                    'legal_id' => $company->legal_id,
                    'tax_id' => $company->tax_id,

                    // Branding
                    'logo' => $company->logo_url,
                    'subdomain' => $company->subdomain,
                    'domain' => $company->full_domain,

                    // Additional Details (from CompanyDetails table)
                    'description' => $companyDetails->description ?? null,
                    'founded_year' => $companyDetails->founded_year ?? null,
                    'currency' => $companyDetails->currency ?? 'USD',

                    // Social Media (from CompanyDetails)
                    'facebook' => $companyDetails->facebook ?? null,
                    'instagram' => $companyDetails->instagram ?? null,
                    'linkedin' => $companyDetails->linkedin ?? null,
                    'twitter' => $companyDetails->twitter ?? null,
                    'youtube' => $companyDetails->youtube ?? null,
                    'tiktok' => $companyDetails->tiktok ?? null,
                    'snapchat' => $companyDetails->snapchat ?? null,
                    'whatsapp' => $companyDetails->whatsapp ?? null,

                    // Subscription Info
                    'plan' => $company->plan,
                    'status' => $company->status,
                    'is_on_trial' => $company->isOnTrial(),
                    'trial_ends_at' => $company->trial_ends_at?->toDateString(),
                    'remaining_trial_days' => $company->getRemainingTrialDays(),
                ],
            ],
        ]);
    }

    /**
     * Get current user's personal information
     *
     * GET /api/account/personal-info
     */
    public function getPersonalInfo(): JsonResponse
    {
        $user = Auth::user();

        return response()->json([
            'success' => true,
            'data' => [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'title' => $user->title,
                'birth_year' => $user->birth_year,
                'how_know_us' => $user->how_know_us,
                'avatar' => $user->avatar_url,
                'status' => $user->status,
                'is_owner' => $user->is_owner,
                'profile_completed' => $user->profile_completed,
                'timezone' => $user->timezone,
                'locale' => $user->locale,
            ],
        ]);
    }

    /**
     * Get current company information
     *
     * GET /api/account/company-details
     */
    public function getCompanyInfo(): JsonResponse
    {
        $company = Company::current();

        if (!$company) {
            return response()->json([
                'success' => false,
                'message' => 'Company context not found',
            ], 404);
        }

        // Get company details
        $companyDetails = $company->details;

        return response()->json([
            'success' => true,
            'data' => [
                // Basic Info
                'company_name' => $company->name,
                'business_email' => $company->business_email,
                'company_phone' => $company->phone,
                'website' => $company->website,

                // Location
                'country' => $company->country,
                'city' => $company->city,
                'address' => $company->address,

                // Business Details
                'industry' => $company->industry,
                'staff_count' => $company->staff_count,

                // Legal Info
                'legal_id' => $company->legal_id,
                'tax_id' => $company->tax_id,

                // Branding
                'logo' => $company->logo_url,
                'subdomain' => $company->subdomain,
                'full_domain' => $company->full_domain,

                // Additional Details (from CompanyDetails table)
                'description' => $companyDetails->description ?? null,
                'founded_year' => $companyDetails->founded_year ?? null,
                'employee_count' => $companyDetails->employee_count ?? null,
                'annual_revenue' => $companyDetails->annual_revenue ?? null,
                'currency' => $companyDetails->currency ?? 'USD',

                // Contact Details
                'secondary_email' => $companyDetails->secondary_email ?? null,
                'secondary_phone' => $companyDetails->secondary_phone ?? null,
                'fax' => $companyDetails->fax ?? null,

                // Social Media (from CompanyDetails)
                'facebook' => $companyDetails->facebook ?? null,
                'instagram' => $companyDetails->instagram ?? null,
                'linkedin' => $companyDetails->linkedin ?? null,
                'twitter' => $companyDetails->twitter ?? null,
                'youtube' => $companyDetails->youtube ?? null,
                'tiktok' => $companyDetails->tiktok ?? null,
                'snapchat' => $companyDetails->snapchat ?? null,
                'whatsapp' => $companyDetails->whatsapp ?? null,

                // Business Hours
                'business_hours' => $companyDetails->business_hours ?? null,

                // Subscription Info
                'plan' => $company->plan,
                'status' => $company->status,
                'is_on_trial' => $company->isOnTrial(),
                'trial_ends_at' => $company->trial_ends_at?->toDateString(),
                'remaining_trial_days' => $company->getRemainingTrialDays(),
                'enabled_modules' => $company->enabled_modules,
            ],
        ]);
    }

    /**
     * Update personal information
     *
     * PUT/PATCH /api/account/personal-info
     */
    public function updatePersonalInfo(UpdatePersonalInfoRequest $request): JsonResponse
    {
        $user = Auth::user();
        $data = $request->validated();

        // Handle password update separately
        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        // Handle avatar upload
        if ($request->hasFile('avatar')) {
            // Delete old avatar if exists
            if ($user->avatar && !filter_var($user->avatar, FILTER_VALIDATE_URL)) {
                Storage::disk('public')->delete($user->avatar);
            }

            $avatarPath = $request->file('avatar')->store('avatars', 'public');
            $data['avatar'] = $avatarPath;
        }

        $user->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Personal information updated successfully',
            'data' => [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'title' => $user->title,
                'birth_year' => $user->birth_year,
                'how_know_us' => $user->how_know_us,
                'avatar' => $user->avatar_url,
                'timezone' => $user->timezone,
                'locale' => $user->locale,
            ],
        ]);
    }

    /**
     * Update company details
     * Only owner or users with 'edit_company_info' permission can update
     *
     * PUT/PATCH /api/account/company-details
     */
    public function updateCompanyDetails(UpdateCompanyDetailsRequest $request): JsonResponse
    {
        $user = Auth::user();
        $company = Company::current();

        if (!$company) {
            return response()->json([
                'success' => false,
                'message' => 'Company context not found',
            ], 404);
        }

        // Check if user has permission to update company details
        if (!$user->isOwner() && !$user->can('edit_company_info') && !$user->hasRole('Admin')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to update company details',
            ], 403);
        }

        $data = $request->validated();

        // ====================================================================
        // UPDATE COMPANY TABLE (Landlord DB)
        // ====================================================================
        $companyData = [];

        // Basic Info
        if (isset($data['company_name'])) {
            $companyData['name'] = $data['company_name'];
        }
        if (isset($data['business_email'])) {
            $companyData['business_email'] = $data['business_email'];
            $companyData['email'] = $data['business_email']; // Backward compatibility
        }
        if (isset($data['company_phone'])) {
            $companyData['phone'] = $data['company_phone'];
        }
        if (isset($data['website'])) {
            $companyData['website'] = $data['website'];
        }

        // Location
        if (isset($data['country'])) {
            $companyData['country'] = $data['country'];
        }
        if (isset($data['city'])) {
            $companyData['city'] = $data['city'];
        }
        if (isset($data['address'])) {
            $companyData['address'] = $data['address'];
        }

        // Business Details
        if (isset($data['industry'])) {
            $companyData['industry'] = $data['industry'];
        }
        if (isset($data['staff_count'])) {
            $companyData['staff_count'] = $data['staff_count'];
        }

        // Legal Info
        if (isset($data['legal_id'])) {
            $companyData['legal_id'] = $data['legal_id'];
        }
        if (isset($data['tax_id'])) {
            $companyData['tax_id'] = $data['tax_id'];
        }

        // Handle logo upload
        if ($request->hasFile('logo')) {
            // Delete old logo if exists
            if ($company->logo && !filter_var($company->logo, FILTER_VALIDATE_URL)) {
                Storage::disk('public')->delete($company->logo);
            }

            $logoPath = $request->file('logo')->store('logos', 'public');
            $companyData['logo'] = $logoPath;
        }

        // Update company in landlord database
        if (!empty($companyData)) {
            Company::on('mysql')
                ->where('id', $company->id)
                ->update($companyData);
        }

        // ====================================================================
        // UPDATE COMPANY DETAILS TABLE (Landlord DB)
        // ====================================================================
        $companyDetailsData = [];

        // Additional Details
        if (isset($data['description'])) {
            $companyDetailsData['description'] = $data['description'];
        }
        if (isset($data['founded_year'])) {
            $companyDetailsData['founded_year'] = $data['founded_year'];
        }
        if (isset($data['employee_count'])) {
            $companyDetailsData['employee_count'] = $data['employee_count'];
        }
        if (isset($data['annual_revenue'])) {
            $companyDetailsData['annual_revenue'] = $data['annual_revenue'];
        }
        if (isset($data['currency'])) {
            $companyDetailsData['currency'] = $data['currency'];
        }

        // Contact Details
        if (isset($data['secondary_email'])) {
            $companyDetailsData['secondary_email'] = $data['secondary_email'];
        }
        if (isset($data['secondary_phone'])) {
            $companyDetailsData['secondary_phone'] = $data['secondary_phone'];
        }
        if (isset($data['fax'])) {
            $companyDetailsData['fax'] = $data['fax'];
        }

        // Social Media
        if (isset($data['facebook'])) {
            $companyDetailsData['facebook'] = $data['facebook'];
        }
        if (isset($data['instagram'])) {
            $companyDetailsData['instagram'] = $data['instagram'];
        }
        if (isset($data['linkedin'])) {
            $companyDetailsData['linkedin'] = $data['linkedin'];
        }
        if (isset($data['twitter'])) {
            $companyDetailsData['twitter'] = $data['twitter'];
        }
        if (isset($data['youtube'])) {
            $companyDetailsData['youtube'] = $data['youtube'];
        }
        if (isset($data['tiktok'])) {
            $companyDetailsData['tiktok'] = $data['tiktok'];
        }
        if (isset($data['snapchat'])) {
            $companyDetailsData['snapchat'] = $data['snapchat'];
        }
        if (isset($data['whatsapp']) || isset($data['company_whatsapp'])) {
            $companyDetailsData['whatsapp'] = $data['whatsapp'] ?? $data['company_whatsapp'];
        }

        // Business Hours
        if (isset($data['business_hours'])) {
            $companyDetailsData['business_hours'] = $data['business_hours'];
        }

        // Update or create company details
        if (!empty($companyDetailsData)) {
            CompanyDetails::on('mysql')->updateOrCreate(
                ['company_id' => $company->id],
                $companyDetailsData
            );
        }

        // Refresh company data
        $company = Company::on('mysql')->with('details')->find($company->id);
        $companyDetails = $company->details;

        return response()->json([
            'success' => true,
            'message' => 'Company details updated successfully',
            'data' => [
                // Basic Info
                'company_name' => $company->name,
                'business_email' => $company->business_email,
                'company_phone' => $company->phone,
                'website' => $company->website,

                // Location
                'country' => $company->country,
                'city' => $company->city,
                'address' => $company->address,

                // Business Details
                'industry' => $company->industry,
                'staff_count' => $company->staff_count,

                // Legal Info
                'legal_id' => $company->legal_id,
                'tax_id' => $company->tax_id,

                // Branding
                'logo' => $company->logo_url,

                // Additional Details
                'description' => $companyDetails->description ?? null,
                'founded_year' => $companyDetails->founded_year ?? null,
                'employee_count' => $companyDetails->employee_count ?? null,
                'currency' => $companyDetails->currency ?? 'USD',

                // Social Media
                'facebook' => $companyDetails->facebook ?? null,
                'instagram' => $companyDetails->instagram ?? null,
                'linkedin' => $companyDetails->linkedin ?? null,
                'twitter' => $companyDetails->twitter ?? null,
                'youtube' => $companyDetails->youtube ?? null,
                'tiktok' => $companyDetails->tiktok ?? null,
                'snapchat' => $companyDetails->snapchat ?? null,
                'whatsapp' => $companyDetails->whatsapp ?? null,
            ],
        ]);
    }

    /**
     * Upload avatar
     *
     * POST /api/account/personal-info/avatar
     */
    public function uploadAvatar(Request $request): JsonResponse
    {
        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpeg,png,jpg,gif', 'max:2048'], // Max 2MB
        ]);

        $user = Auth::user();

        // Delete old avatar if exists
        if ($user->avatar && !filter_var($user->avatar, FILTER_VALIDATE_URL)) {
            Storage::disk('public')->delete($user->avatar);
        }

        $avatarPath = $request->file('avatar')->store('avatars', 'public');
        $user->update(['avatar' => $avatarPath]);

        return response()->json([
            'success' => true,
            'message' => 'Avatar uploaded successfully',
            'data' => [
                'avatar' => $user->avatar_url,
            ],
        ]);
    }

    /**
     * Upload company logo
     *
     * POST /api/account/company-info/logo
     */
    public function uploadLogo(Request $request): JsonResponse
    {
        $request->validate([
            'logo' => ['required', 'image', 'mimes:jpeg,png,jpg,gif,svg', 'max:2048'], // Max 2MB
        ]);

        $user = Auth::user();
        $company = Company::current();

        if (!$company) {
            return response()->json([
                'success' => false,
                'message' => 'Company context not found',
            ], 404);
        }

        // Check permission
        if (!$user->isOwner() && !$user->can('edit_company_info') && !$user->hasRole('Admin')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to update company logo',
            ], 403);
        }

        // Delete old logo if exists
        if ($company->logo && !filter_var($company->logo, FILTER_VALIDATE_URL)) {
            Storage::disk('public')->delete($company->logo);
        }

        $logoPath = $request->file('logo')->store('logos', 'public');

        Company::on('mysql')
            ->where('id', $company->id)
            ->update(['logo' => $logoPath]);

        return response()->json([
            'success' => true,
            'message' => 'Company logo uploaded successfully',
            'data' => [
                'logo' => asset('storage/' . $logoPath),
            ],
        ]);
    }

    /**
     * Get industry options
     *
     * GET /api/account/industry-options
     */
    public function getIndustryOptions(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => Company::industryOptions(),
        ]);
    }

    /**
     * Get staff count options
     *
     * GET /api/account/staff-count-options
     */
    public function getStaffCountOptions(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => Company::staffCountOptions(),
        ]);
    }

    /**
     * Get title options for users
     *
     * GET /api/account/title-options
     */
    public function getTitleOptions(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => \App\Models\User::titleOptions(),
        ]);
    }
}
