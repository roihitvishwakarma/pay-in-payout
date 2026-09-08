<?php

namespace App\Http\Requests;

use App\Enums\PaymentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class PayoutCrudRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return backpack_auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $payoutId = $this->route('id');

        return [
            'merchant_id' => ['required', 'integer', 'exists:merchants,id'],
            'transaction_id' => [
                'required',
                'string',
                'max:64',
                Rule::unique('payouts', 'transaction_id')->ignore($payoutId),
            ],
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999999.99', 'decimal:0,2'],
            'status' => ['required', new Enum(PaymentStatus::class)],
            'processed' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Get the validation attributes that apply to the request.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'merchant_id' => 'merchant',
            'transaction_id' => 'transaction ID',
        ];
    }
}
