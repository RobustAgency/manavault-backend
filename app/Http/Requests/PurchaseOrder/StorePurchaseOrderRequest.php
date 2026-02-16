<?php

namespace App\Http\Requests\PurchaseOrder;

use App\Enums\Currency;
use App\Models\DigitalProduct;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;

class StorePurchaseOrderRequest extends FormRequest
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
            'currency' => ['required', Rule::enum(Currency::class)],
            'items' => ['required', 'array', 'min:1'],
            'items.*.supplier_id' => ['required', 'exists:suppliers,id'],
            'items.*.digital_product_id' => ['required', 'exists:digital_products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
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
            'currency.required' => 'Currency is required.',
            'currency.enum' => 'Currency must be a valid enum value.',
            'items.required' => 'At least one item is required.',
            'items.min' => 'At least one item is required.',
            'items.*.supplier_id.required' => 'Supplier is required for all items.',
            'items.*.supplier_id.exists' => 'The selected supplier does not exist.',
            'items.*.digital_product_id.required' => 'Digital product is required for all items.',
            'items.*.digital_product_id.exists' => 'The selected digital product does not exist.',
            'items.*.quantity.required' => 'Quantity is required for all items.',
            'items.*.quantity.integer' => 'Quantity must be an integer.',
            'items.*.quantity.min' => 'Quantity must be at least 1.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->has('digital_product_ids')) {
                return;
            }
            $currency = $this->input('currency');
            $items = $this->input('items', []);

            foreach ($items as $index => $item) {
                $digitalProductId = $item['digital_product_id'] ?? null;
                if ($digitalProductId) {
                    try {
                        /** @var DigitalProduct $digitalProduct */
                        $digitalProduct = DigitalProduct::findOrFail($digitalProductId);
                        if ($digitalProduct->currency !== $currency) {
                            $validator->errors()->add(
                                "items.{$index}.digital_product_id",
                                "The digital product currency must match the request currency ({$currency})."
                            );
                        }
                    } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
                        $validator->errors()->add(
                            "items.{$index}.digital_product_id",
                            'The selected digital product does not exist.'
                        );
                    }
                }
            }
        });
    }
}
