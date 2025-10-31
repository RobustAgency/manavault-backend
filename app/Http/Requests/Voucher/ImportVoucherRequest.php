<?php

namespace App\Http\Requests\Voucher;

use Illuminate\Foundation\Http\FormRequest;

class ImportVoucherRequest extends FormRequest
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
                'required',
                'file',
                'mimes:csv,xlsx,xls,zip',
                'max:10240',
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
            'file.required' => 'Please select a file to upload.',
            'file.file' => 'The uploaded item must be a valid file.',
            'file.mimes' => 'The file must be a CSV, Excel (xlsx/xls), or ZIP file.',
            'file.max' => 'The file size must not exceed 10MB.',
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
            'purchase_order_id' => 'purchase order',
        ];
    }
}
