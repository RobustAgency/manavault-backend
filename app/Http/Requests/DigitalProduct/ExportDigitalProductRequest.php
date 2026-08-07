<?php

namespace App\Http\Requests\DigitalProduct;

use App\Enums\Currency;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class ExportDigitalProductRequest extends FormRequest
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
     * Mirrors the digital product index filters, except supplier_id is required
     * so an export is always scoped to a single supplier.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'brand' => ['sometimes', 'string', 'max:255'],
            'region' => ['sometimes', 'string', 'max:255'],
            'currency' => ['sometimes', Rule::enum(Currency::class)],
        ];
    }
}
