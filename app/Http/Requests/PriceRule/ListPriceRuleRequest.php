<?php

namespace App\Http\Requests\PriceRule;

use Illuminate\Validation\Rule;
use App\Enums\PriceRule\MatchType;
use App\Enums\PriceRule\ActionMode;
use Illuminate\Foundation\Http\FormRequest;

class ListPriceRuleRequest extends FormRequest
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
            'match_type' => ['nullable', Rule::enum(MatchType::class)],
            'action_mode' => ['nullable', Rule::enum(ActionMode::class)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
