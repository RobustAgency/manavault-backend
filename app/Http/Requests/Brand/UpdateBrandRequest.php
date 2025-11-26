<?php

namespace App\Http\Requests\Brand;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBrandRequest extends FormRequest
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

        $brand = $this->route('brand');
        $brandId = is_object($brand) ? ($brand->id ?? null) : $brand;

        return [
            'name' => ['sometimes', 'string', 'max:255', 'unique:brands,name,'.$brandId],
            'image' => ['sometimes', 'nullable', 'file', 'mimes:jpeg,png,jpg', 'max:2048'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'The brand name is required.',
            'name.string' => 'The brand name must be a string.',
            'name.max' => 'The brand name may not be greater than 255 characters.',
            'name.unique' => 'A brand with this name already exists.',
            'image.file' => 'The image must be a file.',
            'image.mimes' => 'The image must be a file of type: jpeg, png, jpg.',
            'image.max' => 'The image may not be greater than 2048 kilobytes.',
        ];
    }
}
