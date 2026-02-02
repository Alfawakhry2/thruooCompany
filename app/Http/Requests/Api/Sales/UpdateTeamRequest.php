<?php

namespace App\Http\Requests\Api\Sales;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UpdateTeamRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = Auth::user();
        return $user->isOwner() || $user->hasRole('Super Admin');
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'team_lead_id' => ['sometimes', 'required', 'exists:users,id'],
            'status' => ['sometimes', 'required', 'in:active,inactive'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Team name is required',
            'team_lead_id.required' => 'Team leader is required',
            'team_lead_id.exists' => 'Selected team leader does not exist',
        ];
    }
}
