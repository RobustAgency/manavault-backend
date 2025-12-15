<?php

namespace App\Http\Requests\Product;

use App\Enums\Currency;
use Illuminate\Validation\Rule;
use App\Enums\Product\Lifecycle;
use Illuminate\Foundation\Http\FormRequest;

class ListProductRequest extends FormRequest
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
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'name' => ['nullable', 'string', 'max:255'],
            'brand_id' => ['nullable', 'exists:brands,id'],
            'currency' => ['nullable', Rule::enum(Currency::class)],
            'status' => ['nullable', 'string', Rule::in(array_map(fn ($c) => $c->value, Lifecycle::cases()))],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
