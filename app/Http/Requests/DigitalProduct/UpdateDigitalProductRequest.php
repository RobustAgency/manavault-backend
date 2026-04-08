<?php

namespace App\Http\Requests\DigitalProduct;

use App\Enums\Currency;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateDigitalProductRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:255'],
            'sku' => ['sometimes', 'string', 'max:255'],
            'brand' => ['sometimes', 'nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'tags' => ['sometimes', 'nullable', 'array'],
            'tags.*' => ['string', 'max:255'],
            'region' => ['sometimes', 'nullable', 'string', 'max:255'],
            'cost_price' => ['sometimes', 'numeric', 'min:0'],
            'face_value' => ['sometimes', 'numeric', 'gt:0'],
            'selling_price' => ['sometimes', 'numeric', 'gt:0'],
            'selling_discount' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],
            'currency' => ['sometimes', 'string', Rule::in(array_map(fn ($c) => $c->value, Currency::cases()))],
            'status' => ['sometimes', 'string', 'in:active,inactive'],
            'metadata' => ['sometimes', 'nullable', 'array'],
            'image' => ['sometimes', 'nullable', 'file', 'image', 'max:2048'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function (\Illuminate\Validation\Validator $validator) {
            /** @var \App\Models\DigitalProduct $digitalProduct */
            $digitalProduct = $this->route('digitalProduct');

            // Resolve the values to use: prefer incoming request values, fall back to existing model values
            $costPrice = $this->has('cost_price')
                ? (float) $this->input('cost_price')
                : (float) $digitalProduct->getAttribute('cost_price');

            $sellingPrice = $this->has('selling_price')
                ? (float) $this->input('selling_price')
                : (float) $digitalProduct->getAttribute('face_value');

            $sellingDiscount = $this->has('selling_discount')
                ? (float) $this->input('selling_discount')
                : (float) $digitalProduct->getAttribute('selling_discount');

            $effectiveSellingPrice = round($sellingPrice * (1 - $sellingDiscount / 100), 2);

            if ($effectiveSellingPrice < $costPrice) {
                $validator->errors()->add(
                    'selling_price',
                    "The effective selling price ({$effectiveSellingPrice}) must not be less than the cost price ({$costPrice})."
                );
            }
        });
    }
}
