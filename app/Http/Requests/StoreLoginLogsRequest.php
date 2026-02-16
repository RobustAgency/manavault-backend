<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLoginLogsRequest extends FormRequest
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
            'email' => ['required', 'string', 'email', 'max:255'],
            'ip_address' => ['required', 'string', 'ip'],
            'user_agent' => ['nullable', 'string', 'max:500'],
            'activity' => ['required', 'string', 'max:255'],
            'logged_in_at' => ['nullable', 'date'],
            'logged_out_at' => ['nullable', 'date', 'after_or_equal:logged_in_at'],
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
            'logged_out_at.after_or_equal' => 'The logged out time must be after or equal to the logged in time.',
        ];
    }
}
