<?php

namespace App\Http\Requests\Voucher;

use Illuminate\Foundation\Http\FormRequest;

class LogVoucherCopyRequest extends FormRequest
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
            'voucher_id' => ['required', 'exists:vouchers,id'],
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
            'voucher_id.required' => 'Voucher ID is required.',
            'voucher_id.exists' => 'The selected voucher does not exist.',
        ];
    }

    /**
     * Get the client's IP address.
     */
    public function getIpAddress(): ?string
    {
        return $this->ip();
    }

    /**
     * Get the client's user agent.
     */
    public function getUserAgent(): ?string
    {
        return $this->userAgent();
    }

    /**
     * Get all audit-related data.
     *
     * @return array<string, string|null>
     */
    public function getAuditData(): array
    {
        return [
            'ip_address' => $this->getIpAddress(),
            'user_agent' => $this->getUserAgent(),
        ];
    }
}
