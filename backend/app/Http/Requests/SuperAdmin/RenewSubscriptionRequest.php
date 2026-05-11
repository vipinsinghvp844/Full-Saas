<?php

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RenewSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'coupon_code' => ['nullable', 'string', 'max:100'],
            'payment_method' => ['nullable', Rule::in(['manual', 'cash', 'card', 'upi', 'bank_transfer'])],
        ];
    }
}
