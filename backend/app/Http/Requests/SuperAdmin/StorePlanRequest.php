<?php

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:platform_plans,name'],
            'description' => ['nullable', 'string'],
            'plan_type' => ['required', Rule::in(['monthly', 'quarterly', 'yearly'])],
            'price' => ['required', 'numeric', 'min:0'],
            'duration' => ['required', 'integer', 'min:1'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'max_members' => ['nullable', 'integer', 'min:1'],
            'max_trainers' => ['nullable', 'integer', 'min:1'],
            'max_branches' => ['nullable', 'integer', 'min:1'],
            'features' => ['nullable'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ];
    }
}
