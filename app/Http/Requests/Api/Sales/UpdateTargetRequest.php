<?php

namespace App\Http\Requests\Api\Sales;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UpdateTargetRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = Auth::user();
        return $user->isOwner() || $user->hasRole('Super Admin');
    }

    public function rules(): array
    {
        return [
            'target_type' => ['sometimes', 'required', 'in:monthly,quarterly,yearly,custom'],
            'target_value' => ['sometimes', 'required', 'numeric', 'min:1'],
            'target_name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'user_id' => ['nullable', 'exists:users,id'],
            'role_name' => ['nullable', 'string', 'in:Admin,Assistant,Sales,Finance'],
            'start_date' => ['sometimes', 'required', 'date'],
            'end_date' => ['sometimes', 'required', 'date', 'after_or_equal:start_date'],
            'status' => ['sometimes', 'required', 'in:active,completed,expired'],
        ];
    }

    public function messages(): array
    {
        return [
            'target_type.required' => 'Target type is required',
            'target_value.required' => 'Target value is required',
            'target_value.min' => 'Target value must be greater than 0',
            'end_date.after_or_equal' => 'End date must be after or equal to start date',
        ];
    }
}
