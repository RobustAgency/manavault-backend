<?php

namespace App\Http\Requests\PriceRule;

use App\Enums\PriceRule\Status;
use Illuminate\Validation\Rule;
use App\Enums\PriceRule\MatchType;
use App\Enums\PriceRule\ActionMode;
use App\Enums\PriceRule\ActionOperator;
use App\Enums\PriceRuleCondition\Operator;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePriceRuleRequest extends FormRequest
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
            'description' => ['nullable', 'string'],
            'match_type' => ['sometimes', Rule::enum(MatchType::class)],
            'action_operator' => ['sometimes', Rule::enum(ActionOperator::class)],
            'action_mode' => ['sometimes', Rule::enum(ActionMode::class)],
            'action_value' => ['sometimes', 'numeric', 'min:0'],
            'status' => ['sometimes', Rule::enum(Status::class)],
            'conditions' => ['sometimes', 'array', 'min:1'],
            'conditions.*.field' => ['required_with:conditions', 'string'],
            'conditions.*.operator' => ['required_with:conditions', Rule::enum(Operator::class)],
            'conditions.*.value' => ['required_with:conditions', 'string'],
        ];
    }
}
