<?php

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tenant_id' => ['required', 'integer', 'exists:tenants,id'],
            'plan_id' => ['required', 'integer', 'exists:platform_plans,id'],
            'coupon_code' => ['nullable', 'string', 'max:100'],
            'start_date' => ['nullable', 'date'],
            'payment_method' => ['nullable', Rule::in(['manual', 'cash', 'card', 'upi', 'bank_transfer'])],
        ];
    }
}
