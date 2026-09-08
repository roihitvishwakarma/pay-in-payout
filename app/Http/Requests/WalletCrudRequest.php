<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WalletCrudRequest extends FormRequest
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
        $walletId = $this->route('id');

        return [
            // One wallet per merchant.
            'merchant_id' => [
                'required',
                'integer',
                'exists:merchants,id',
                'unique:wallets,merchant_id,' . ($walletId ?? 'NULL') . ',id',
            ],
            'balance' => ['required', 'numeric', 'min:0', 'max:999999999999.99', 'decimal:0,2'],
            'currency' => ['required', 'string', 'max:3'],
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
        ];
    }

    /**
     * Get the validation messages that apply to the request.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'merchant_id.unique' => 'This merchant already has a wallet.',
        ];
    }
}
