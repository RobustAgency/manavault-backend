<?php

namespace App\Services;

use App\Models\Product;
use App\Models\PriceRule;
use App\Enums\PriceRule\ActionMode;
use App\Enums\PriceRule\ActionOperator;
use App\Repositories\ProductRepository;
use App\Repositories\PriceRuleRepository;
use App\Repositories\PriceRuleConditionRepository;

class PricingRuleService
{
    public function __construct(
        private PriceRuleRepository $priceRuleRepository,
        private PriceRuleConditionRepository $priceRuleConditionRepository,
        private ProductRepository $productRepository,
    ) {}

    public function createPriceRuleWithConditions(array $data): void
    {
        $priceRule = [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'match_type' => $data['match_type'],
            'action_operator' => $data['action_operator'],
            'action_mode' => $data['action_mode'],
            'action_value' => $data['action_value'],
            'status' => $data['status'],
        ];

        $priceRule = $this->priceRuleRepository->createPriceRule($priceRule);

        foreach ($data['conditions'] as $condition) {
            $condition = [
                'price_rule_id' => $priceRule->id,
                'field' => $condition['field'],
                'operator' => $condition['operator'],
                'value' => $condition['value'],
            ];
            $this->priceRuleConditionRepository->create($condition);
        }

        $products = $this->productRepository->getProductsByConditions($data['conditions'], $data['match_type']);

        foreach ($products as $product) {
            $this->applyAction($product, $priceRule);
        }

    }

    public function updatePriceRuleWithConditions(PriceRule $priceRule, array $data): void
    {
        // Implementation for updating price rule and its conditions
        $updatedPriceRule = $this->priceRuleRepository->updatePriceRule($priceRule, $data);

        // Update conditions if provided
        if (isset($data['conditions'])) {
            $this->priceRuleConditionRepository->deleteConditionsByPriceRule($priceRule);
            foreach ($data['conditions'] as $condition) {
                $condition = [
                    'price_rule_id' => $priceRule->id,
                    'field' => $condition['field'],
                    'operator' => $condition['operator'],
                    'value' => $condition['value'],
                ];
                $this->priceRuleConditionRepository->create($condition);
            }
        }

        // Reapply actions to products
        $products = $this->productRepository->getProductsByConditions($data['conditions'], $updatedPriceRule->match_type);
        foreach ($products as $product) {
            $this->applyAction($product, $updatedPriceRule);
        }
    }

    private function applyAction(Product $product, PriceRule $rule): void
    {
        $updatedPrice = $this->calculateNewPrice($product, $rule->action_mode, $rule->action_value, $rule->action_operator);

        $product->update([
            'selling_price' => max($updatedPrice, 0),
        ]);
    }

    /**
     * Preview products and their new prices without applying the rule to the database.
     */
    public function previewPriceRuleEffect(array $data): array
    {
        $products = $this->productRepository->getProductsByConditions($data['conditions'], $data['match_type']);

        $preview = [];
        foreach ($products as $product) {
            $newPrice = $this->calculateNewPrice($product, $data['action_mode'], $data['action_value'], $data['action_operator']);

            $preview[] = [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'face_value' => (float) $product->face_value,
                'current_selling_price' => (float) $product->selling_price,
                'new_selling_price' => (float) max($newPrice, 0),
            ];
        }

        return $preview;
    }

    /**
     * Calculate the new price for a product based on the price rule action.
     */
    private function calculateNewPrice(Product $product, string $actionMode, mixed $actionValue, string $actionOperator): float
    {
        $base = (float) $product->face_value;

        return match ($actionMode) {
            ActionMode::PERCENTAGE->value => $base + ($base * ($actionValue / 100)) * ($actionOperator === ActionOperator::ADDITION->value ? 1 : -1),
            ActionMode::ABSOLUTE->value => $base + ($actionValue * ($actionOperator === ActionOperator::ADDITION->value ? 1 : -1)),
            default => $base,
        };
    }
}
