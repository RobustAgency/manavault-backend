<?php

namespace App\Http\Requests\Product;

use Illuminate\Validation\Rule;
use App\Enums\Product\Lifecycle;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRequest extends FormRequest
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
            'brand' => ['sometimes', 'nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'short_description' => ['sometimes', 'nullable', 'string'],
            'long_description' => ['sometimes', 'nullable', 'string'],
            'tags' => ['sometimes', 'nullable', 'array'],
            'tags.*' => ['string'],
            'image' => ['sometimes', 'nullable', 'string', 'max:255'],
            'selling_price' => ['sometimes', 'numeric', 'min:0'],
            'status' => ['sometimes', 'string', Rule::in(array_map(fn ($c) => $c->value, Lifecycle::cases()))],
            'regions' => ['sometimes', 'nullable', 'array'],
            'regions.*' => ['string'],
        ];
    }
}
