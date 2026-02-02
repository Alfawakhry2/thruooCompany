<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class TenantRegistrationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            // Step 1: Personal Information
            'personal' => ['required', 'array'],
            'personal.name' => ['required', 'string', 'max:255'],
            'personal.email' => ['required', 'email', 'max:255', 'unique:mysql.tenants,email'],
            'personal.password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()],
            'personal.phone' => ['required', 'string', 'max:20'],
            'personal.title' => ['nullable', 'string', 'max:100'],
            'personal.birth_year' => ['nullable', 'integer', 'min:1940', 'max:' . (date('Y') - 16)], // At least 16 years old
            'personal.how_know_us' => ['nullable', 'array'],
            'personal.how_know_us.*' => ['string', 'in:google,facebook,twitter,linkedin,instagram,youtube,friend,blog,event,podcast,advertisement,other'],

            // Step 2: Company Information
            'company' => ['required', 'array'],
            'company.name' => ['required', 'string', 'max:255'],
            'company.industry' => ['required', 'string', 'max:100'],
            'company.staff_count' => ['required', 'string', 'in:1-10,11-50,51-200,201-500,500+'],
            'company.website' => ['nullable', 'url', 'max:255'],
            'company.business_email' => ['nullable', 'email', 'max:255'],
            'company.country' => ['required', 'string', 'max:100'],
            'company.city' => ['required', 'string', 'max:100'],
            'company.address' => ['nullable', 'string', 'max:500'],
            'company.legal_id' => ['nullable', 'string', 'max:100'],
            'company.tax_id' => ['nullable', 'string', 'max:100'],
            'company.logo' => ['nullable', 'string', 'max:5000'], // Base64 encoded or URL

            // Optional: Custom subdomain (if user wants to override auto-generated)
            'subdomain' => ['nullable', 'string', 'alpha_dash', 'min:3', 'max:63', 'unique:mysql.tenants,subdomain'],

            // Step 3: Team Members (Optional)
            'team_members' => ['nullable', 'array', 'max:10'],
            'team_members.*.email' => ['required', 'email', 'max:255', 'distinct'],
            'team_members.*.name' => ['nullable', 'string', 'max:255'],
            'team_members.*.role' => ['nullable', 'string', 'in:Admin,Assistant,Sales,Finance'],

            // Step 4: Modules & Referral
            'modules' => ['required', 'array', 'min:1'],
            'modules.*' => ['string', 'in:sales'], // Only sales for now, expand later
            
            'referral' => ['nullable', 'array'],
            'referral.code' => ['nullable', 'string', 'max:50'],
            'referral.relation' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            // Personal Info
            'personal.required' => 'Personal information is required',
            'personal.name.required' => 'Full name is required',
            'personal.email.required' => 'Email address is required',
            'personal.email.email' => 'Please provide a valid email address',
            'personal.email.unique' => 'This email is already registered',
            'personal.password.required' => 'Password is required',
            'personal.phone.required' => 'Phone number is required',
            'personal.birth_year.min' => 'Please enter a valid birth year',
            'personal.birth_year.max' => 'You must be at least 16 years old to register',

            // Company Info
            'company.required' => 'Company information is required',
            'company.name.required' => 'Company name is required',
            'company.industry.required' => 'Industry is required',
            'company.staff_count.required' => 'Company size is required',
            'company.staff_count.in' => 'Please select a valid company size',
            'company.business_email.email' => 'Please provide a valid business email',
            'company.country.required' => 'Country is required',
            'company.city.required' => 'City is required',

            // Subdomain
            'subdomain.alpha_dash' => 'Subdomain can only contain letters, numbers, dashes and underscores',
            'subdomain.min' => 'Subdomain must be at least 3 characters',
            'subdomain.max' => 'Subdomain cannot exceed 63 characters',
            'subdomain.unique' => 'This subdomain is already taken',

            // Team Members
            'team_members.max' => 'You can add maximum 10 team members during registration',
            'team_members.*.email.required' => 'Team member email is required',
            'team_members.*.email.email' => 'Please provide a valid email for team member',
            'team_members.*.email.distinct' => 'Team member emails must be unique',
            'team_members.*.role.in' => 'Invalid role selected for team member',

            // Modules
            'modules.required' => 'Please select at least one module',
            'modules.min' => 'Please select at least one module',
            'modules.*.in' => 'Invalid module selected',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'personal.name' => 'full name',
            'personal.email' => 'email',
            'personal.password' => 'password',
            'personal.phone' => 'phone number',
            'personal.title' => 'job title',
            'personal.birth_year' => 'birth year',
            'company.name' => 'company name',
            'company.industry' => 'industry',
            'company.staff_count' => 'company size',
            'company.website' => 'website',
            'company.business_email' => 'business email',
            'company.country' => 'country',
            'company.city' => 'city',
        ];
    }
}
