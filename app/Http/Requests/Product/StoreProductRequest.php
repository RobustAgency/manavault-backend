<?php

namespace App\Http\Requests\Product;

use Illuminate\Validation\Rule;
use App\Enums\Product\Lifecycle;
use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['required', 'string', 'max:100', 'unique:products,sku'],
            'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
            'description' => ['nullable', 'string'],
            'short_description' => ['nullable', 'string'],
            'long_description' => ['nullable', 'string'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string'],
            'image' => ['nullable', 'file', 'mimes:jpeg,png,jpg', 'max:2048'],
            'selling_price' => ['required', 'numeric', 'min:0'],
            'status' => ['required', 'string', Rule::in(array_map(fn ($c) => $c->value, Lifecycle::cases()))],
            'regions' => ['nullable', 'array'],
            'regions.*' => ['string'],
        ];
    }
}
