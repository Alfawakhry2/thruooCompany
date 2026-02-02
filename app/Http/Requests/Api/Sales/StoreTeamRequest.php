<?php

namespace App\Http\Requests\Api\Sales;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class StoreTeamRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = Auth::user();
        return $user->isOwner() || $user->hasRole('Super Admin');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'team_lead_id' => ['required', 'exists:users,id'],
            'member_ids' => ['nullable', 'array'],
            'member_ids.*' => ['exists:users,id'],
            'status' => ['nullable', 'in:active,inactive'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Team name is required',
            'team_lead_id.required' => 'Team leader is required',
            'team_lead_id.exists' => 'Selected team leader does not exist',
            'member_ids.*.exists' => 'One or more members do not exist',
        ];
    }
}
