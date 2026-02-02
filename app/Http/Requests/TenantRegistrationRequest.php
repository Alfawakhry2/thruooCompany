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
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Step 1: Personal Information
            'personal.name' => ['required', 'string', 'max:255'],
            'personal.email' => ['required', 'email', 'max:255'],
            'personal.password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()],
            'personal.phone' => ['required', 'string', 'max:20'],
            'personal.title' => ['nullable', 'string', 'max:100'],
            'personal.birth_year' => ['nullable', 'integer', 'min:1940', 'max:' . date('Y')],
            'personal.how_know_us' => ['nullable', 'string', 'max:255'],

            // Step 2: Company Information
            'company.name' => ['required', 'string', 'max:255', 'unique:mysql.tenants,name'],
            'company.industry' => ['required', 'string', 'max:100'],
            'company.staff_count' => ['required', 'in:1-10,11-50,51-200,201-500,500+'],
            'company.website' => ['nullable', 'url', 'max:255'],
            'company.business_email' => ['required', 'email', 'max:255'],
            'company.country' => ['required', 'string', 'max:100'],
            'company.city' => ['required', 'string', 'max:100'],
            'company.legal_id' => ['nullable', 'string', 'max:100'],
            'company.tax_id' => ['nullable', 'string', 'max:100'],
            'company.logo' => ['nullable', 'string'], // Base64 encoded or URL

            // Step 3: Additional Users (Optional, max 4)
            'team_members' => ['nullable', 'array', 'max:4'],
            'team_members.*.name' => ['required', 'string', 'max:255'],
            'team_members.*.email' => ['nullable', 'email', 'max:255', 'distinct'],
            'team_members.*.role' => ['nullable', 'in:Admin,Assistant,Sales,Finance'],

            // Step 4: Modules & Referral
            'modules' => ['required', 'array', 'min:1'],
            'modules.*' => ['in:sales'], // Only sales for now
            'referral.code' => ['nullable', 'string', 'max:50'],
            'referral.relation' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'personal.name.required' => 'Full name is required',
            'personal.email.required' => 'Email address is required',
            'personal.email.email' => 'Please provide a valid email address',
            'personal.password.required' => 'Password is required',
            'personal.phone.required' => 'Phone number is required',

            'company.name.required' => 'Company name is required',
            'company.name.unique' => 'This company name is already registered',
            'company.industry.required' => 'Industry is required',
            'company.staff_count.required' => 'Company size is required',
            'company.business_email.required' => 'Business email is required',
            'company.country.required' => 'Country is required',
            'company.city.required' => 'City is required',

            'team_members.max' => 'You can add maximum 4 team members',
            'team_members.*.email.distinct' => 'Team member emails must be unique',

            'modules.required' => 'Please select at least one module',
        ];
    }
}
