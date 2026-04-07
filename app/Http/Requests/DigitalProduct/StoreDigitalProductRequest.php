<?php

namespace App\Http\Requests\DigitalProduct;

use App\Enums\Currency;
use Illuminate\Validation\Rule;
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
            'products.*.face_value' => ['required', 'numeric', 'gt:0'],
            'products.*.selling_price' => ['required', 'numeric', 'gt:0'],
            'products.*.selling_discount' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'products.*.currency' => ['required', 'string', Rule::in(array_map(fn ($c) => $c->value, Currency::cases()))],
            'products.*.metadata' => ['nullable', 'array'],
            'products.*.tags' => ['nullable', 'array'],
            'products.*.tags.*' => ['string', 'max:255'],
            'products.*.region' => ['nullable', 'string', 'max:255'],
            'products.*.image' => ['nullable', 'file', 'image', 'max:2048'],
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
            'products.*.face_value.required' => 'Face value is required for all products.',
            'products.*.face_value.numeric' => 'Face value must be a number.',
            'products.*.face_value.gt' => 'Face value must be greater than 0.',
            'products.*.selling_price.gt' => 'Selling price must be greater than 0.',
            'products.*.selling_discount.numeric' => 'Selling discount must be a number.',
            'products.*.selling_discount.min' => 'Selling discount must be at least 0.',
            'products.*.selling_discount.max' => 'Selling discount must not exceed 100.',
            'products.*.currency.required' => 'Currency is required for all products.',
            'products.*.currency.in' => 'The selected currency is invalid.',
            'products.*.tags.required' => 'Tags are required for all products.',
            'products.*.tags.array' => 'Tags must be an array.',
            'products.*.tags.*.string' => 'Each tag must be a string.',
            'products.*.tags.*.max' => 'Each tag must not exceed 255 characters.',
            'products.*.region.required' => 'Region is required for all products.',
            'products.*.region.string' => 'Region must be a string.',
            'products.*.region.max' => 'Region must not exceed 255 characters.',
            'products.*.image.required' => 'Image is required for all products.',
            'products.*.image.file' => 'Image must be a file.',
            'products.*.image.image' => 'Image must be an image.',
            'products.*.image.max' => 'Image must not exceed 2048 kilobytes.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function (\Illuminate\Validation\Validator $validator) {
            $products = $this->input('products', []);

            foreach ($products as $index => $product) {
                $costPrice = isset($product['cost_price']) ? (float) $product['cost_price'] : null;
                $sellingPrice = isset($product['selling_price']) ? (float) $product['selling_price'] : null;
                $sellingDiscount = isset($product['selling_discount']) ? (float) $product['selling_discount'] : 0;

                if ($costPrice === null || $sellingPrice === null) {
                    continue;
                }

                $effectiveSellingPrice = round($sellingPrice * (1 - $sellingDiscount / 100), 2);

                if ($effectiveSellingPrice < $costPrice) {
                    $validator->errors()->add(
                        "products.{$index}.selling_price",
                        "The effective selling price ({$effectiveSellingPrice}) must not be less than the cost price ({$costPrice})."
                    );
                }
            }
        });
    }
}
