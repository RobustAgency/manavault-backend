<?php

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDigitalProductsPriorityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'digital_products' => ['required', 'array', 'min:1'],
            'digital_products.*.digital_product_id' => ['required', 'integer', 'exists:digital_products,id'],
            'digital_products.*.priority_order' => ['required', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'digital_products.required' => 'Digital products array is required.',
            'digital_products.array' => 'Digital products must be an array.',
            'digital_products.min' => 'At least one digital product must be provided.',
            'digital_products.*.digital_product_id.required' => 'Digital product ID is required for each item.',
            'digital_products.*.digital_product_id.exists' => 'One or more digital product IDs do not exist.',
            'digital_products.*.priority_order.required' => 'Priority order is required for each item.',
            'digital_products.*.priority_order.integer' => 'Priority order must be an integer.',
            'digital_products.*.priority_order.min' => 'Priority order must be at least 1.',
        ];
    }
}
