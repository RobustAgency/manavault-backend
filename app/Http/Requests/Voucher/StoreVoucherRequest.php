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
            'voucher_codes.*' => [
                'required',
                'unique:vouchers,code',
                'string',
                'max:255',
            ],
            'purchase_order_id' => ['required', 'integer', 'exists:purchase_orders,id'],
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
            'voucher_codes.*.required' => 'Each voucher code is required.',
            'voucher_codes.*.string' => 'Each voucher code must be a valid string.',
            'voucher_codes.*.max' => 'Each voucher code must not exceed 255 characters.',
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
            'voucher_codes.*' => 'voucher code',
            'purchase_order_id' => 'purchase order',
        ];
    }
}
