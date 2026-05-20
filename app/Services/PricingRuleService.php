<?php

namespace App\Services;

use App\Models\PriceRule;
use App\Models\DigitalProduct;
use App\Events\PriceRuleApplied;
use App\Enums\PriceRule\ActionMode;
use App\Enums\PriceRule\ActionOperator;
use App\Repositories\PriceRuleRepository;
use App\Repositories\DigitalProductRepository;
use App\Support\MoneyCalculator;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Repositories\PriceRuleConditionRepository;
use App\Repositories\PriceRuleDigitalProductRepository;
use Money\Money;

class PricingRuleService
{
    public function __construct(
        private PriceRuleRepository $priceRuleRepository,
        private PriceRuleConditionRepository $priceRuleConditionRepository,
        private PriceRuleDigitalProductRepository $priceRuleDigitalProductRepository,
        private DigitalProductRepository $digitalProductRepository,
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

        $products = $this->digitalProductRepository->getDigitalProductsByConditions(
            $data['conditions'],
            $data['match_type'],
            null,
            $data['action_mode'],
            $data['action_operator'],
            $data['action_value'],
        );

        $affectedDigitalProductIds = [];
        foreach ($products as $digitalProduct) {
            $this->applyAction($digitalProduct, $priceRule);
            $affectedDigitalProductIds[] = $digitalProduct->id;
        }

        if (! empty($affectedDigitalProductIds)) {
            event(new PriceRuleApplied($affectedDigitalProductIds));
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

        // Reapply actions to digital products
        $this->priceRuleDigitalProductRepository->deleteByPriceRuleId($priceRule->id);
        $digitalProducts = $this->digitalProductRepository->getDigitalProductsByConditions(
            $data['conditions'],
            $updatedPriceRule->match_type,
            null,
            $updatedPriceRule->action_mode,
            $updatedPriceRule->action_operator,
            $updatedPriceRule->action_value,
        );
        $affectedDigitalProductIds = [];
        foreach ($digitalProducts as $digitalProduct) {
            $this->applyAction($digitalProduct, $updatedPriceRule);
            $affectedDigitalProductIds[] = $digitalProduct->id;
        }

        if (! empty($affectedDigitalProductIds)) {
            event(new PriceRuleApplied($affectedDigitalProductIds));
        }
    }

    public function deletePriceRuleWithSync(PriceRule $priceRule): void
    {
        $affectedDigitalProductIds = $this->priceRuleDigitalProductRepository
            ->getDigitalProductIdsByPriceRuleId($priceRule->id);

        $this->priceRuleDigitalProductRepository->deleteByPriceRuleId($priceRule->id);
        $this->priceRuleRepository->deletePriceRule($priceRule);

        if (! empty($affectedDigitalProductIds)) {
            event(new PriceRuleApplied($affectedDigitalProductIds));
        }
    }

    private function applyAction(DigitalProduct $digitalProduct, PriceRule $rule): void
    {
        $applicationData = $this->buildApplicationData(
            $digitalProduct,
            $rule->action_mode,
            $rule->action_operator,
            $rule->action_value,
        );

        if ($applicationData === null) {
            return;
        }

        $this->priceRuleDigitalProductRepository->create([
            'digital_product_id' => $applicationData['digital_product_id'],
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
    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function previewPriceRuleEffect(array $data): LengthAwarePaginator
    {
        $perPage = $data['per_page'] ?? 15;

        /** @var LengthAwarePaginator<int, DigitalProduct> $digitalProducts */
        $digitalProducts = $this->digitalProductRepository
            ->getDigitalProductsByConditions(
                $data['conditions'],
                $data['match_type'],
                $perPage,
                $data['action_mode'],
                $data['action_operator'],
                $data['action_value'],
            );

        $transformedCollection = $digitalProducts->getCollection()->map(function ($digitalProduct) use ($data) {

            $applicationData = $this->buildApplicationData(
                $digitalProduct,
                $data['action_mode'],
                $data['action_operator'],
                $data['action_value'],
            );

            if ($applicationData === null) {
                return null;
            }

            return [
                'digital_product_id' => $applicationData['digital_product_id'],
                'digital_product_name' => $applicationData['digital_product_name'],
                'face_value' => $applicationData['base_value'],
                'current_selling_price' => $applicationData['current_selling_price'],
                'new_selling_price' => $applicationData['new_selling_price'],
            ];
        })->filter()->values();

        /** @var LengthAwarePaginator<int, array<string, mixed>> $result */
        $result = $digitalProducts->setCollection($transformedCollection); // @phpstan-ignore-line

        return $result;
    }

    /**
     * Build the application data for a single product and price rule action.
     * This is the SINGLE SOURCE OF TRUTH for all price calculations.
     * Used by both preview (no DB write) and apply (DB write).
     *
     * @return array<string, mixed>
     */
    private function buildApplicationData(
        DigitalProduct $digitalProduct,
        string $actionMode,
        string $actionOperator,
        mixed $actionValue,
    ): ?array {
        $currency = $digitalProduct->currency;
        $baseValue = MoneyCalculator::of($digitalProduct->face_value, $currency);
        $originalSellingPrice = MoneyCalculator::of((string) ($digitalProduct->selling_price ?? 0), $currency);
        $calculatedPrice = $this->calculateNewPrice($digitalProduct, $actionMode, $actionValue, $actionOperator);
        $costPrice = MoneyCalculator::of($digitalProduct->getAttribute('cost_price') ?? 0, $currency);
        $zero = MoneyCalculator::zero($currency);

        // Never allow the price to go below 0.
        $finalPrice = $calculatedPrice->greaterThan($zero) ? $calculatedPrice : $zero;

        // Safety net: the SQL filter in getDigitalProductsByConditions already excludes
        // products where the calculated price would fall below cost_price. This guard
        // protects against any caller that bypasses the SQL filter (e.g. floating-point
        // edge cases or future call sites).
        if ($finalPrice->lessThan($costPrice)) {
            return null;
        }

        return [
            'digital_product_id' => $digitalProduct->id,
            'digital_product_name' => $digitalProduct->name,
            'original_selling_price' => MoneyCalculator::toFloat($originalSellingPrice),
            'base_value' => MoneyCalculator::toFloat($baseValue),
            'action_mode' => $actionMode,
            'action_operator' => $actionOperator,
            'action_value' => (float) $actionValue,
            'calculated_price' => MoneyCalculator::toFloat($calculatedPrice),
            'final_selling_price' => MoneyCalculator::toFloat($finalPrice),
            'current_selling_price' => MoneyCalculator::toFloat($originalSellingPrice),
            'new_selling_price' => MoneyCalculator::toFloat($finalPrice),
        ];
    }

    /**
     * Calculate the new price for a product based on the price rule action.
     * Returns a Money object to avoid floating-point precision issues.
     */
    private function calculateNewPrice(DigitalProduct $digitalProduct, string $actionMode, mixed $actionValue, string $actionOperator): Money
    {
        $currency = $digitalProduct->currency;
        $basePrice = MoneyCalculator::of($digitalProduct->face_value, $currency);
        $isAddition = $actionOperator === ActionOperator::ADDITION->value;

        if ($actionMode === ActionMode::PERCENTAGE->value) {
            // Compute base * (actionValue / 100) using integer arithmetic:
            // multiply by actionValue first, then divide by 100 to avoid float imprecision.
            $markup = $basePrice->multiply((string) $actionValue)->divide('100');

            return $isAddition ? $basePrice->add($markup) : $basePrice->subtract($markup);
        }

        if ($actionMode === ActionMode::ABSOLUTE->value) {
            $absoluteAmount = MoneyCalculator::of($actionValue, $currency);

            return $isAddition ? $basePrice->add($absoluteAmount) : $basePrice->subtract($absoluteAmount);
        }

        return $basePrice;
    }
}
