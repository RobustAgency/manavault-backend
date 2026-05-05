<?php

namespace App\Http\Requests\SaleOrder;

use App\Models\Product;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StoreSaleOrderRequest extends FormRequest
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
     */
    public function rules(): array
    {
        return [
            'order_number' => ['required', 'string', 'max:255', 'unique:sale_orders,order_number'],
            'source' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * Get custom messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'source.string' => 'Source must be a valid string.',
            'source.max' => 'Source must not exceed 255 characters.',
            'items.required' => 'At least one item is required.',
            'items.array' => 'Items must be an array.',
            'items.min' => 'At least one item must be provided.',
            'items.*.product_id.required' => 'Product ID is required for each item.',
            'items.*.product_id.integer' => 'Product ID must be an integer.',
            'items.*.product_id.exists' => 'Selected product does not exist.',
            'items.*.quantity.required' => 'Quantity is required for each item.',
            'items.*.quantity.integer' => 'Quantity must be an integer.',
            'items.*.quantity.min' => 'Quantity must be at least 1.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            /** @var array<int, array<string, mixed>> $items */
            $items = $this->input('items', []);
            $productIds = collect($items)->pluck('product_id')->filter()->unique();

            if ($productIds->isEmpty()) {
                return;
            }

            $products = Product::whereIn('id', $productIds)
                ->with('digitalProducts')
                ->get()
                ->keyBy('id');

            foreach ($items as $index => $item) {
                $productId = $item['product_id'] ?? null;

                if (! $productId || ! isset($products[$productId])) {
                    continue;
                }

                if ($products[$productId]->digitalProducts->isEmpty()) {
                    Log::warning('Sale order rejected: product has no active digital product assigned.', [
                        'product_id' => $productId,
                        'product_name' => $products[$productId]->name,
                        'order_number' => $this->input('order_number'),
                    ]);

                    $validator->errors()->add(
                        "items.{$index}.product_id",
                        "Product \"{$products[$productId]->name}\" has no active digital product assigned."
                    );
                }
            }
        });
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'currency' => 'currency',
            'source' => 'source',
            'items' => 'items',
            'items.*.product_id' => 'product ID',
            'items.*.quantity' => 'quantity',
        ];
    }
}
