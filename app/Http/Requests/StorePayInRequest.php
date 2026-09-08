<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePayInRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'merchant_id' => ['required', 'integer', 'exists:merchants,id'],
            'amount' => [
                'required',
                'numeric',
                'gt:0',
                'max:999999999999.99',
                'decimal:0,2',
            ],
        ];
    }

    /**
     * Get the custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'merchant_id.exists' => 'The merchant identifier is not recognized.',
            'amount.decimal'     => 'The amount is invalid; at most 2 decimal places are allowed.',
            'amount.numeric'     => 'The amount is invalid; it must be numeric.',
            'amount.gt'          => 'The amount is out of range; it must be greater than 0.',
            'amount.max'         => 'The amount is out of range; it exceeds the maximum allowed value.',
        ];
    }
}
