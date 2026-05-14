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
            'subtotal' => ['required', 'numeric', 'min:0'],
            'conversion_fees' => ['required', 'numeric', 'min:0'],
            'total' => ['required', 'numeric', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.product_name' => ['nullable', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.price' => ['required', 'numeric', 'min:0'],
            'items.*.conversion_fee' => ['nullable', 'numeric', 'min:0'],
            'items.*.total_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
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
            'subtotal.numeric' => 'Subtotal must be a number.',
            'subtotal.min' => 'Subtotal must be at least 0.',
            'conversion_fees.required' => 'Conversion fees is required.',
            'conversion_fees.numeric' => 'Conversion fees must be a number.',
            'conversion_fees.min' => 'Conversion fees must be at least 0.',
            'total.required' => 'Total is required.',
            'total.numeric' => 'Total must be a number.',
            'total.min' => 'Total must be at least 0.',
            'items.required' => 'At least one item is required.',
            'items.array' => 'Items must be an array.',
            'items.min' => 'At least one item must be provided.',
            'items.*.product_id.required' => 'Product ID is required for each item.',
            'items.*.product_id.integer' => 'Product ID must be an integer.',
            'items.*.product_id.exists' => 'Selected product does not exist.',
            'items.*.product_name.string' => 'Product name must be a string.',
            'items.*.product_name.max' => 'Product name must not exceed 255 characters.',
            'items.*.quantity.required' => 'Quantity is required for each item.',
            'items.*.quantity.integer' => 'Quantity must be an integer.',
            'items.*.quantity.min' => 'Quantity must be at least 1.',
            'items.*.price.required' => 'Price is required for each item.',
            'items.*.price.numeric' => 'Price must be a number.',
            'items.*.price.min' => 'Price must be at least 0.',
            'items.*.conversion_fee.numeric' => 'Conversion fee must be a number.',
            'items.*.conversion_fee.min' => 'Conversion fee must be at least 0.',
            'items.*.total_price.numeric' => 'Total price must be a number.',
            'items.*.total_price.min' => 'Total price must be at least 0.',
            'items.*.discount_amount.numeric' => 'Discount amount must be a number.',
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
            'items.*.conversion_fee' => 'conversion fee',
            'items.*.total_price' => 'total price',
            'items.*.discount_amount' => 'discount amount',
            'items.*.currency' => 'currency',
        ];
    }
}
