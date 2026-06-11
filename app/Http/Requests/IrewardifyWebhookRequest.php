<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;
use App\Enums\IrewardifyWebhookStatus;
use Illuminate\Foundation\Http\FormRequest;

class IrewardifyWebhookRequest extends FormRequest
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
            'event' => ['required', 'string'],
            'message' => ['nullable', 'string'],
            'orderId' => ['required', 'string'],
            'status' => ['required', Rule::enum(IrewardifyWebhookStatus::class)],
        ];
    }
}
