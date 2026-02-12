<?php

namespace App\Http\Requests\Voucher;

use Illuminate\Foundation\Http\FormRequest;

class StoreVoucherRequest extends FormRequest
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => [
                'nullable',
                'required_without:voucher_codes',
                'file',
                'mimes:csv,txt,xlsx,xls,zip',
                'max:10240',
            ],

            'voucher_codes' => [
                'nullable',
                'required_without:file',
                'array',
                'min:1',
            ],

            'voucher_codes.*.code' => [
                'required_with:voucher_codes',
                'string',
                'max:255',
            ],

            'voucher_codes.*.digital_product_id' => [
                'required_with:voucher_codes',
                'integer',
                'exists:digital_products,id',
            ],

            'purchase_order_id' => [
                'required',
                'integer',
                'exists:purchase_orders,id',
            ],
        ];

    }

    /**
     * Get custom error messages for validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required_without' => 'Please provide either a file or voucher codes.',
            'file.file' => 'The uploaded item must be a valid file.',
            'file.mimes' => 'The file must be a CSV, Excel (xlsx/xls), or ZIP file.',
            'file.max' => 'The file size must not exceed 10MB.',
            'voucher_codes.required_without' => 'Please provide either voucher codes or a file.',
            'voucher_codes.array' => 'Voucher codes must be provided as an array.',
            'voucher_codes.min' => 'At least one voucher code is required.',
            'voucher_codes.*.code.required' => 'Each voucher code is required.',
            'voucher_codes.*.code.string' => 'Each voucher code must be a valid string.',
            'voucher_codes.*.code.max' => 'Each voucher code must not exceed 255 characters.',
            'voucher_codes.*.digital_product_id.required' => 'Each digital product ID is required.',
            'voucher_codes.*.digital_product_id.integer' => 'Each digital product ID must be an integer.',
            'voucher_codes.*.digital_product_id.exists' => 'The selected digital product does not exist.',
            'purchase_order_id.required' => 'Purchase order ID is required.',
            'purchase_order_id.integer' => 'Purchase order ID must be a valid number.',
            'purchase_order_id.exists' => 'The selected purchase order does not exist.',
        ];
    }

    /**
     * Get custom attribute names for validation errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'file' => 'voucher file',
            'voucher_codes' => 'voucher codes',
            'voucher_codes.*.code' => 'voucher code',
            'voucher_codes.*.digital_product_id' => 'digital product ID',
            'purchase_order_id' => 'purchase order',
        ];
    }
}
