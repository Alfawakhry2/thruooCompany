<?php

namespace App\Http\Requests\Api\Sales;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class StoreTargetRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = Auth::user();
        return $user->isOwner() || $user->hasRole('Super Admin');
    }

    public function rules(): array
    {
        return [
            'target_type' => ['required', 'in:monthly,quarterly,yearly,custom'],
            'target_value' => ['required', 'numeric', 'min:1'],
            'target_name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'user_id' => ['nullable', 'exists:users,id', 'required_without_all:role_name,team_id'],
            'role_name' => ['nullable', 'string', 'in:Admin,Assistant,Sales,Finance', 'required_without_all:user_id,team_id'],
            'team_id' => ['nullable', 'exists:teams,id', 'required_without_all:user_id,role_name'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ];
    }

    public function messages(): array
    {
        return [
            'target_type.required' => 'Target type is required',
            'target_value.required' => 'Target value is required',
            'target_value.min' => 'Target value must be greater than 0',
            'user_id.required_without' => 'Either user or role must be specified',
            'role_name.required_without' => 'Either user or role must be specified',
            'end_date.after_or_equal' => 'End date must be after or equal to start date',
        ];
    }
}
