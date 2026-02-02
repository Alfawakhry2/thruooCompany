<?php

namespace App\Http\Requests\Api\Account;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePersonalInfoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // User can update their own info
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $user = $this->user();

        return [
            // Basic Info
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'phone' => ['sometimes', 'required', 'string', 'max:20'],

            // Password (optional)
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'password_confirmation' => ['required_with:password'],

            // Profile Info
            'title' => ['nullable', 'string', 'max:100'],
            'birth_year' => ['nullable', 'integer', 'min:1940', 'max:' . (date('Y') - 16)],
            'how_know_us' => ['nullable', 'array'],
            'how_know_us.*' => ['string', 'in:google,facebook,twitter,linkedin,instagram,youtube,friend,blog,event,podcast,advertisement,other'],

            // Avatar
            'avatar' => ['nullable'], // Max 2MB
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Full name is required',
            'email.required' => 'Email is required',
            'email.email' => 'Please provide a valid email address',
            'email.unique' => 'This email is already in use',
            'phone.required' => 'Phone number is required',
            'password.min' => 'Password must be at least 8 characters',
            'password.confirmed' => 'Password confirmation does not match',
            'birth_year.min' => 'Birth year must be 1940 or later',
            'birth_year.max' => 'You must be at least 16 years old',
            'avatar.image' => 'Avatar must be an image file',
            'avatar.max' => 'Avatar size must not exceed 2MB',
        ];
    }
}
