<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListVouchersRequest extends FormRequest
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
            'purchase_order_id' => ['sometimes', 'integer', 'exists:purchase_orders,id'],
            'per_page' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ];
    }
}
