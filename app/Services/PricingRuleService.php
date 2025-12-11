<?php

namespace App\Services;

use App\Models\Product;
use App\Models\PriceRule;
use App\Enums\PriceRule\Status;
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
            'status' => Status::DRAFT,
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

    private function applyAction(Product $product, PriceRule $rule): void
    {
        $value = $rule->action_value;
        $base = $product->face_value;

        $newPrice = match ($rule['action_mode']) {
            ActionMode::PERCENTAGE => $base + ($base * ($value / 100)) * ($rule['action_operator'] === ActionOperator::ADDITION ? 1 : -1),
            ActionMode::ABSOLUTE => $base + ($value * ($rule['action_operator'] === ActionOperator::ADDITION ? 1 : -1)),
            default => $base,
        };

        $product->update([
            'sale_price' => max($newPrice, 0), // never negative
        ]);
    }
}
