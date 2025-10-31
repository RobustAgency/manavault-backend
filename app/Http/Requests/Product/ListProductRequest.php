<?php

namespace App\Http\Requests\Product;

use App\Enums\Product\Lifecycle;
use Illuminate\Validation\Rule;
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
            'name' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', Rule::in(array_map(fn($c) => $c->value, Lifecycle::cases()))],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
