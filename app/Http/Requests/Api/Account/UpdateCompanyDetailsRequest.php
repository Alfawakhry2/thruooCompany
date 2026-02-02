<?php

namespace App\Http\Requests\Api\Account;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCompanyDetailsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Only owner or super admin can update company details
        $user = $this->user();
        return $user->isOwner() || $user->hasRole('Super Admin');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            // Company Basic Info
            'company_name' => ['sometimes', 'required', 'string', 'max:255'],
            'city' => ['sometimes', 'required', 'string', 'max:100'],
            'country' => ['sometimes', 'required', 'string', 'max:100'],
            'industry' => ['sometimes', 'required', 'string', 'max:100'],
            
            // Contact Info
            'website' => ['nullable', 'url', 'max:255'],
            'company_phone' => ['nullable', 'string', 'max:20'],
            'company_whatsapp' => ['nullable', 'string', 'max:20'],
            'business_email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            
            // Legal Info
            'legal_id' => ['nullable', 'string', 'max:100'],
            'tax_id' => ['nullable', 'string', 'max:100'],
            
            // Social Media
            'facebook' => ['nullable', 'url', 'max:255'],
            'instagram' => ['nullable', 'url', 'max:255'],
            'linkedin' => ['nullable', 'url', 'max:255'],
            'snapchat' => ['nullable', 'url', 'max:255'],
            'tiktok' => ['nullable', 'url', 'max:255'],
            'youtube' => ['nullable', 'url', 'max:255'],
            
            // Logo
            'logo' => ['nullable', 'image', 'max:2048'], // Max 2MB
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'company_name.required' => 'Company name is required',
            'city.required' => 'City is required',
            'country.required' => 'Country is required',
            'industry.required' => 'Industry is required',
            'website.url' => 'Please provide a valid website URL',
            'business_email.email' => 'Please provide a valid business email',
            'facebook.url' => 'Please provide a valid Facebook URL',
            'instagram.url' => 'Please provide a valid Instagram URL',
            'linkedin.url' => 'Please provide a valid LinkedIn URL',
            'snapchat.url' => 'Please provide a valid Snapchat URL',
            'tiktok.url' => 'Please provide a valid TikTok URL',
            'youtube.url' => 'Please provide a valid YouTube URL',
            'logo.image' => 'Logo must be an image file',
            'logo.max' => 'Logo size must not exceed 2MB',
        ];
    }

    /**
     * Handle a failed authorization attempt.
     */
    protected function failedAuthorization()
    {
        throw new \Illuminate\Auth\Access\AuthorizationException(
            'You do not have permission to update company details. Only company owner or admin can perform this action.'
        );
    }
}
