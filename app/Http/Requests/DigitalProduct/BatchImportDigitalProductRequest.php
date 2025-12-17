<?php

namespace App\Http\Requests\DigitalProduct;

use Illuminate\Foundation\Http\FormRequest;

class BatchImportDigitalProductRequest extends FormRequest
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
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'], // 5MB max
            'supplier_id' => ['required', 'exists:suppliers,id'],
        ];
    }

    /**
     * Get custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'A CSV file is required.',
            'file.mimes' => 'The file must be a CSV file.',
            'file.max' => 'The file must not exceed 5MB.',
            'supplier_id.required' => 'A supplier ID is required.',
            'supplier_id.exists' => 'The selected supplier does not exist.',
        ];
    }
}
