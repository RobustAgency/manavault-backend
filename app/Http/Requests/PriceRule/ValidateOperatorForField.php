<?php

namespace App\Http\Requests\PriceRule;

use Closure;
use App\Enums\PriceRuleCondition\Operator;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidateOperatorForField implements ValidationRule
{
    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Extract the condition index from the attribute (e.g., 'conditions.0.operator' -> 0)
        preg_match('/conditions\.(\d+)\.operator/', $attribute, $matches);
        $index = $matches[1] ?? null;

        if ($index === null) {
            return;
        }

        $field = request("conditions.{$index}.field");

        // Define allowed operators for each field
        $allowedOperators = [
            'name' => [Operator::EQUAL->value, Operator::CONTAINS->value, Operator::NOT_EQUAL->value],
            'supplier_name' => [Operator::EQUAL->value, Operator::NOT_EQUAL->value],
            'sku' => [Operator::EQUAL->value, Operator::NOT_EQUAL->value],
            'selling_price' => [Operator::EQUAL->value, Operator::LESS_THAN_OR_EQUAL->value,
                Operator::GREATER_THAN_OR_EQUAL->value, Operator::LESS_THAN->value,
                Operator::GREATER_THAN->value, Operator::NOT_EQUAL->value,
            ],
            'cost_price' => [Operator::EQUAL->value, Operator::LESS_THAN_OR_EQUAL->value,
                Operator::GREATER_THAN_OR_EQUAL->value, Operator::LESS_THAN->value,
                Operator::GREATER_THAN->value, Operator::NOT_EQUAL->value,
            ],
            'currency' => [Operator::EQUAL->value, Operator::NOT_EQUAL->value],
            'brand' => [Operator::EQUAL->value, Operator::CONTAINS->value, Operator::NOT_EQUAL->value],
            'region' => [Operator::EQUAL->value, Operator::NOT_EQUAL->value],
        ];

        // Get allowed operators for this field
        $allowed = $allowedOperators[$field] ?? [];

        // Check if the operator is allowed for this field
        if (! in_array($value, $allowed)) {
            $operatorList = implode(', ', $allowed);
            $fail("The operator for field '{$field}' must be one of: {$operatorList}.", null);
        }
    }
}
