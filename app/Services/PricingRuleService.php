<?php

namespace App\Services;

use App\Models\Product;
use App\Models\PriceRule;
use App\Enums\PriceRule\ActionMode;
use App\Enums\PriceRule\ActionOperator;
use App\Repositories\ProductRepository;
use App\Repositories\PriceRuleRepository;
use App\Repositories\PriceRuleProductRepository;
use App\Repositories\PriceRuleConditionRepository;

class PricingRuleService
{
    public function __construct(
        private PriceRuleRepository $priceRuleRepository,
        private PriceRuleConditionRepository $priceRuleConditionRepository,
        private PriceRuleProductRepository $priceRuleProductRepository,
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
        $this->priceRuleProductRepository->deleteByPriceRuleId($priceRule->id);
        $products = $this->productRepository->getProductsByConditions($data['conditions'], $updatedPriceRule->match_type);
        foreach ($products as $product) {
            $this->applyAction($product, $updatedPriceRule);
        }
    }

    private function applyAction(Product $product, PriceRule $rule): void
    {
        $applicationData = $this->buildApplicationData(
            $product,
            $rule->action_mode,
            $rule->action_operator,
            $rule->action_value,
        );

        $this->priceRuleProductRepository->create([
            'product_id' => $applicationData['product_id'],
            'price_rule_id' => $rule->id,
            'original_selling_price' => $applicationData['original_selling_price'],
            'base_value' => $applicationData['base_value'],
            'action_mode' => $applicationData['action_mode'],
            'action_operator' => $applicationData['action_operator'],
            'action_value' => $applicationData['action_value'],
            'calculated_price' => $applicationData['calculated_price'],
            'final_selling_price' => $applicationData['final_selling_price'],
            'applied_at' => now(),
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
            $applicationData = $this->buildApplicationData(
                $product,
                $data['action_mode'],
                $data['action_operator'],
                $data['action_value'],
            );

            $preview[] = [
                'product_id' => $applicationData['product_id'],
                'product_name' => $applicationData['product_name'],
                'face_value' => $applicationData['face_value'],
                'current_selling_price' => $applicationData['current_selling_price'],
                'new_selling_price' => $applicationData['new_selling_price'],
            ];
        }

        return $preview;
    }

    /**
     * Build the application data for a single product and price rule action.
     * This is the SINGLE SOURCE OF TRUTH for all price calculations.
     * Used by both preview (no DB write) and apply (DB write).
     *
     * @return array<string, mixed>
     */
    private function buildApplicationData(
        Product $product,
        string $actionMode,
        string $actionOperator,
        mixed $actionValue,
    ): array {
        $baseValue = (float) $product->face_value;
        $originalSellingPrice = (float) $product->selling_price;
        $calculatedPrice = $this->calculateNewPrice($product, $actionMode, $actionValue, $actionOperator);
        $finalSellingPrice = (float) max($calculatedPrice, 0);

        return [
            'product_id' => $product->id,
            'product_name' => $product->name,
            'original_selling_price' => $originalSellingPrice,
            'base_value' => $baseValue,
            'action_mode' => $actionMode,
            'action_operator' => $actionOperator,
            'action_value' => (float) $actionValue,
            'calculated_price' => $calculatedPrice,
            'final_selling_price' => $finalSellingPrice,
            'face_value' => $baseValue,
            'current_selling_price' => $originalSellingPrice,
            'new_selling_price' => $finalSellingPrice,
        ];
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
