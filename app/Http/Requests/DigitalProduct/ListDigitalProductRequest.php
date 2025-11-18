<?php

namespace App\Http\Requests\DigitalProduct;

use Illuminate\Foundation\Http\FormRequest;

class ListDigitalProductRequest extends FormRequest
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
            'brand' => ['sometimes', 'string', 'max:255'],
            'supplier_id' => ['sometimes', 'integer', 'exists:suppliers,id'],
            'status' => ['sometimes', 'string', 'in:active,inactive'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
