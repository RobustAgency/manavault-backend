<?php

namespace App\Http\Requests\Product;

use App\Models\DigitalProduct;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;

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

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            // Only perform custom validation if the basic array validation passed
            if ($validator->errors()->has('digital_product_ids')) {
                return;
            }

            /** @var \App\Models\Product $product */
            $product = $this->route('product');
            $productCurrency = $product->currency;
            /** @var array<int> $digitalProductIds */
            $digitalProductIds = $this->input('digital_product_ids', []);

            foreach ($digitalProductIds as $index => $digitalProductId) {
                try {
                    /** @var DigitalProduct $digitalProduct */
                    $digitalProduct = DigitalProduct::findOrFail($digitalProductId);
                    if ($digitalProduct->currency !== $productCurrency) {
                        $validator->errors()->add(
                            "digital_product_ids.{$index}",
                            "The digital product currency must match the product currency ({$productCurrency})."
                        );
                    }
                    if (is_null($digitalProduct->selling_price)) {
                        $validator->errors()->add(
                            "digital_product_ids.{$index}",
                            'The digital product must have a selling price set before it can be assigned.'
                        );
                    }
                } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
                    $validator->errors()->add(
                        "digital_product_ids.{$index}",
                        'The selected digital product does not exist.'
                    );
                }
            }
        });
    }
}
