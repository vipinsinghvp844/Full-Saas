<?php

namespace App\Http\Requests\SuperAdmin;

use App\Models\Coupon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var Coupon|null $coupon */
        $coupon = $this->route('coupon');

        return [
            'tenant_id' => ['required', 'integer', 'exists:tenants,id'],
            'code' => ['required', 'string', 'max:100', Rule::unique('coupons', 'code')->ignore($coupon?->id)],
            'discount_type' => ['required', Rule::in(['percentage', 'fixed'])],
            'discount_value' => ['required', 'numeric', 'min:0.01'],
            'valid_from' => ['nullable', 'date'],
            'valid_to' => ['required', 'date', 'after_or_equal:valid_from'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ];
    }
}
