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
            'order_number' => ['required', 'string', 'max:255'],
            'source' => ['nullable', 'string', 'max:255'],
            'customer' => ['required', 'array'],
            'customer.external_id' => ['required', 'string', 'max:255'],
            'customer.name' => ['required', 'string', 'max:255'],
            'customer.email' => ['nullable', 'email', 'max:255'],
            'customer.company_name' => ['nullable', 'string', 'max:255'],
            'customer.company_email' => ['nullable', 'email', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
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
            'customer.external_id' => 'customer external ID',
            'customer.name' => 'customer name',
            'customer.email' => 'customer email',
            'customer.company_name' => 'company name',
            'customer.company_email' => 'company email',
            'items' => 'items',
            'items.*.product_id' => 'product ID',
            'items.*.quantity' => 'quantity',
        ];
    }
}
