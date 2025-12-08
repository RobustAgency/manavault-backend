<?php

namespace App\Http\Requests\DigitalProduct;

use Illuminate\Foundation\Http\FormRequest;

class StoreDigitalProductRequest extends FormRequest
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
            'products' => ['required', 'array', 'min:1'],
            'products.*.supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'products.*.name' => ['required', 'string', 'max:255'],
            'products.*.sku' => ['required', 'string', 'max:255'],
            'products.*.brand' => ['nullable', 'string', 'max:255'],
            'products.*.description' => ['nullable', 'string'],
            'products.*.cost_price' => ['required', 'numeric', 'min:0'],
            'products.*.metadata' => ['nullable', 'array'],
            'products.*.tags' => ['nullable', 'array'],
            'products.*.tags.*' => ['string', 'max:255'],
            'products.*.region' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'products.required' => 'At least one product is required.',
            'products.array' => 'Products must be an array.',
            'products.min' => 'At least one product is required.',
            'products.*.supplier_id.required' => 'Supplier ID is required for all products.',
            'products.*.supplier_id.exists' => 'The selected supplier does not exist.',
            'products.*.name.required' => 'Product name is required for all products.',
            'products.*.cost_price.required' => 'Cost price is required for all products.',
            'products.*.cost_price.min' => 'Cost price must be at least 0.',
            'products.*.tags.required' => 'Tags are required for all products.',
            'products.*.tags.array' => 'Tags must be an array.',
            'products.*.tags.*.string' => 'Each tag must be a string.',
            'products.*.tags.*.max' => 'Each tag must not exceed 255 characters.',
            'products.*.region.required' => 'Region is required for all products.',
            'products.*.region.string' => 'Region must be a string.',
            'products.*.region.max' => 'Region must not exceed 255 characters.',
        ];
    }
}
