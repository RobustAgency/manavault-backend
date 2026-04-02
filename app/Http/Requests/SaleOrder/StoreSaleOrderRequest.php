<?php

namespace App\Http\Requests\SaleOrder;

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
            'currency' => ['required', 'string', 'max:3'],
            'subtotal' => ['required', 'integer', 'min:0'],
            'conversion_fees' => ['required', 'integer', 'min:0'],
            'total' => ['required', 'integer', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.product_name' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.price' => ['required', 'integer', 'min:0'],
            'items.*.purchase_price' => ['required', 'integer', 'min:0'],
            'items.*.conversion_fee' => ['required', 'integer', 'min:0'],
            'items.*.total_price' => ['required', 'integer', 'min:0'],
            'items.*.discount_amount' => ['required', 'integer', 'min:0'],
            'items.*.currency' => ['required', 'string', 'max:3'],
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
            'currency.required' => 'Currency is required.',
            'currency.string' => 'Currency must be a valid string.',
            'currency.max' => 'Currency must not exceed 3 characters.',
            'subtotal.required' => 'Subtotal is required.',
            'subtotal.integer' => 'Subtotal must be an integer.',
            'subtotal.min' => 'Subtotal must be at least 0.',
            'conversion_fees.required' => 'Conversion fees is required.',
            'conversion_fees.integer' => 'Conversion fees must be an integer.',
            'conversion_fees.min' => 'Conversion fees must be at least 0.',
            'total.required' => 'Total is required.',
            'total.integer' => 'Total must be an integer.',
            'total.min' => 'Total must be at least 0.',
            'items.required' => 'At least one item is required.',
            'items.array' => 'Items must be an array.',
            'items.min' => 'At least one item must be provided.',
            'items.*.product_id.required' => 'Product ID is required for each item.',
            'items.*.product_id.integer' => 'Product ID must be an integer.',
            'items.*.product_id.exists' => 'Selected product does not exist.',
            'items.*.product_name.required' => 'Product name is required for each item.',
            'items.*.product_name.string' => 'Product name must be a string.',
            'items.*.product_name.max' => 'Product name must not exceed 255 characters.',
            'items.*.quantity.required' => 'Quantity is required for each item.',
            'items.*.quantity.integer' => 'Quantity must be an integer.',
            'items.*.quantity.min' => 'Quantity must be at least 1.',
            'items.*.price.required' => 'Price is required for each item.',
            'items.*.price.integer' => 'Price must be an integer.',
            'items.*.price.min' => 'Price must be at least 0.',
            'items.*.purchase_price.required' => 'Purchase price is required for each item.',
            'items.*.purchase_price.integer' => 'Purchase price must be an integer.',
            'items.*.purchase_price.min' => 'Purchase price must be at least 0.',
            'items.*.conversion_fee.required' => 'Conversion fee is required for each item.',
            'items.*.conversion_fee.integer' => 'Conversion fee must be an integer.',
            'items.*.conversion_fee.min' => 'Conversion fee must be at least 0.',
            'items.*.total_price.required' => 'Total price is required for each item.',
            'items.*.total_price.integer' => 'Total price must be an integer.',
            'items.*.total_price.min' => 'Total price must be at least 0.',
            'items.*.discount_amount.required' => 'Discount amount is required for each item.',
            'items.*.discount_amount.integer' => 'Discount amount must be an integer.',
            'items.*.discount_amount.min' => 'Discount amount must be at least 0.',
            'items.*.currency.required' => 'Currency is required for each item.',
            'items.*.currency.string' => 'Currency must be a string.',
            'items.*.currency.max' => 'Currency must not exceed 3 characters.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'currency' => 'currency',
            'subtotal' => 'subtotal',
            'conversion_fees' => 'conversion fees',
            'total' => 'total',
            'source' => 'source',
            'items' => 'items',
            'items.*.product_id' => 'product ID',
            'items.*.product_name' => 'product name',
            'items.*.quantity' => 'quantity',
            'items.*.price' => 'price',
            'items.*.purchase_price' => 'purchase price',
            'items.*.conversion_fee' => 'conversion fee',
            'items.*.total_price' => 'total price',
            'items.*.discount_amount' => 'discount amount',
            'items.*.currency' => 'currency',
        ];
    }
}
