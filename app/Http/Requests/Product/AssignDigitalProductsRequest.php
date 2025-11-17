<?php

namespace App\Http\Requests\Product;

use Illuminate\Foundation\Http\FormRequest;

class AssignDigitalProductsRequest extends FormRequest
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
            'digital_product_ids' => ['required', 'array', 'min:1'],
            'digital_product_ids.*' => ['required', 'integer', 'exists:digital_products,id'],
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
            'digital_product_ids.required' => 'Please provide at least one digital product ID.',
            'digital_product_ids.array' => 'Digital product IDs must be provided as an array.',
            'digital_product_ids.min' => 'At least one digital product ID is required.',
            'digital_product_ids.*.required' => 'Each digital product ID is required.',
            'digital_product_ids.*.integer' => 'Each digital product ID must be a valid integer.',
            'digital_product_ids.*.exists' => 'One or more digital product IDs do not exist.',
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
            'digital_product_ids' => 'digital product IDs',
            'digital_product_ids.*' => 'digital product ID',
        ];
    }
}
