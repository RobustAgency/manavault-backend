<?php

namespace App\Http\Requests\PriceRule;

use App\Enums\PriceRule\Status;
use Illuminate\Validation\Rule;
use App\Enums\PriceRule\MatchType;
use App\Enums\PriceRule\ActionMode;
use App\Enums\PriceRule\ActionOperator;
use App\Enums\PriceRuleCondition\Operator;
use Illuminate\Foundation\Http\FormRequest;

class StorePriceRuleController extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'match_type' => ['required', Rule::enum(MatchType::class)],
            'action_operator' => ['required', Rule::enum(ActionOperator::class)],
            'action_mode' => ['required', Rule::enum(ActionMode::class)],
            'action_value' => ['required', 'numeric', 'min:0'],
            'status' => ['required', Rule::enum(Status::class)],
            'conditions' => ['required', 'array', 'min:1'],
            'conditions.*.field' => ['required', 'string'],
            'conditions.*.operator' => ['required', Rule::enum(Operator::class)],
            'conditions.*.value' => ['required', 'string'],
        ];
    }
}
