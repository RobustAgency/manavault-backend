<?php

namespace Tests\Unit\Support;

use Money\Money;
use PHPUnit\Framework\TestCase;
use App\Support\MoneyCalculator;

class MoneyCalculatorTest extends TestCase
{
    public function test_of_parses_decimal_string(): void
    {
        $money = MoneyCalculator::of('10.50', 'usd');

        $this->assertInstanceOf(Money::class, $money);
        $this->assertSame('1050', $money->getAmount());
        $this->assertSame('USD', $money->getCurrency()->getCode());
    }

    public function test_of_parses_float(): void
    {
        $money = MoneyCalculator::of(25.75, 'usd');

        $this->assertSame('2575', $money->getAmount());
    }

    public function test_of_parses_integer(): void
    {
        $money = MoneyCalculator::of(100, 'usd');

        $this->assertSame('10000', $money->getAmount());
    }

    public function test_of_upcases_lowercase_currency(): void
    {
        $money = MoneyCalculator::of('5.00', 'usd');

        $this->assertSame('USD', $money->getCurrency()->getCode());
    }

    public function test_of_accepts_uppercase_currency(): void
    {
        $money = MoneyCalculator::of('5.00', 'USD');

        $this->assertSame('USD', $money->getCurrency()->getCode());
    }

    public function test_of_handles_eur_currency(): void
    {
        $money = MoneyCalculator::of('9.99', 'eur');

        $this->assertSame('999', $money->getAmount());
        $this->assertSame('EUR', $money->getCurrency()->getCode());
    }

    public function test_of_handles_gbp_currency(): void
    {
        $money = MoneyCalculator::of('14.99', 'gbp');

        $this->assertSame('1499', $money->getAmount());
        $this->assertSame('GBP', $money->getCurrency()->getCode());
    }

    public function test_of_handles_zero_amount(): void
    {
        $money = MoneyCalculator::of('0.00', 'usd');

        $this->assertSame('0', $money->getAmount());
    }

    public function test_zero_returns_money_with_zero_amount(): void
    {
        $money = MoneyCalculator::zero('usd');

        $this->assertSame('0', $money->getAmount());
        $this->assertSame('USD', $money->getCurrency()->getCode());
    }

    public function test_zero_upcases_lowercase_currency(): void
    {
        $money = MoneyCalculator::zero('eur');

        $this->assertSame('EUR', $money->getCurrency()->getCode());
    }

    public function test_to_float_converts_money_back_to_float(): void
    {
        $money = MoneyCalculator::of('10.50', 'usd');

        $this->assertSame(10.50, MoneyCalculator::toFloat($money));
    }

    public function test_to_float_round_trips_cleanly(): void
    {
        $original = '49.99';
        $money = MoneyCalculator::of($original, 'usd');

        $this->assertSame(49.99, MoneyCalculator::toFloat($money));
    }

    public function test_to_float_returns_zero_for_zero_money(): void
    {
        $this->assertSame(0.0, MoneyCalculator::toFloat(MoneyCalculator::zero('usd')));
    }

    /**
     * The classic PHP float trap: 0.1 + 0.7 != 0.8 in native floats.
     * Money addition must produce exactly 0.80.
     */
    public function test_addition_avoids_classic_float_trap(): void
    {
        // PHP native: 0.1 + 0.7 === 0.7999999999999999...
        $this->assertNotEquals(0.8, 0.1 + 0.7, 'Sanity: native PHP floats are imprecise here.');

        $result = MoneyCalculator::of('0.10', 'usd')
            ->add(MoneyCalculator::of('0.70', 'usd'));

        $this->assertSame(0.80, MoneyCalculator::toFloat($result));
    }

    public function test_addition_accumulates_order_total_precisely(): void
    {
        // Simulate: 3 items at 9.99 each → total should be 29.97, not 29.970000000000003
        $unitPrice = MoneyCalculator::of('9.99', 'usd');
        $total = MoneyCalculator::zero('usd');

        for ($i = 0; $i < 3; $i++) {
            $total = $total->add($unitPrice);
        }

        $this->assertSame(29.97, MoneyCalculator::toFloat($total));
    }

    public function test_addition_with_many_items_stays_exact(): void
    {
        // 100 items at 0.10 → must be exactly 10.00
        $unitPrice = MoneyCalculator::of('0.10', 'usd');
        $total = MoneyCalculator::zero('usd');

        for ($i = 0; $i < 100; $i++) {
            $total = $total->add($unitPrice);
        }

        $this->assertSame(10.00, MoneyCalculator::toFloat($total));
    }

    public function test_subtraction_computes_discounted_price_precisely(): void
    {
        // face_value=25.00, discount=5% → 25.00 - 1.25 = 23.75
        $faceValue = MoneyCalculator::of('25.00', 'usd');
        $discountAmount = $faceValue->multiply('5')->divide('100');
        $result = $faceValue->subtract($discountAmount);

        $this->assertSame(23.75, MoneyCalculator::toFloat($result));
    }

    public function test_subtraction_does_not_produce_negative_when_equal(): void
    {
        $a = MoneyCalculator::of('10.00', 'usd');
        $b = MoneyCalculator::of('10.00', 'usd');

        $result = $a->subtract($b);

        $this->assertSame(0.0, MoneyCalculator::toFloat($result));
    }

    public function test_multiply_by_quantity_computes_subtotal_precisely(): void
    {
        // unit_price=19.99, quantity=3 → 59.97
        $unitPrice = MoneyCalculator::of('19.99', 'usd');
        $subtotal = $unitPrice->multiply(3);

        $this->assertSame(59.97, MoneyCalculator::toFloat($subtotal));
    }

    public function test_multiply_by_large_quantity_stays_exact(): void
    {
        // unit_price=9.99, quantity=1000 → 9990.00
        $unitPrice = MoneyCalculator::of('9.99', 'usd');
        $subtotal = $unitPrice->multiply(1000);

        $this->assertSame(9990.0, MoneyCalculator::toFloat($subtotal));
    }

    public function test_multiply_by_one_is_identity(): void
    {
        $unitPrice = MoneyCalculator::of('14.50', 'usd');
        $subtotal = $unitPrice->multiply(1);

        $this->assertSame(14.50, MoneyCalculator::toFloat($subtotal));
    }

    /**
     * Mirrors PricingRuleService::calculateNewPrice() for PERCENTAGE + ADDITION.
     * base + (base * pct / 100)
     */
    public function test_percentage_addition_markup_is_precise(): void
    {
        // face_value=50.00, +10% → 55.00
        $base = MoneyCalculator::of('50.00', 'usd');
        $markup = $base->multiply('10')->divide('100');
        $result = $base->add($markup);

        $this->assertSame(55.00, MoneyCalculator::toFloat($result));
    }

    public function test_percentage_subtraction_markdown_is_precise(): void
    {
        // face_value=50.00, -10% → 45.00
        $base = MoneyCalculator::of('50.00', 'usd');
        $markup = $base->multiply('10')->divide('100');
        $result = $base->subtract($markup);

        $this->assertSame(45.00, MoneyCalculator::toFloat($result));
    }

    public function test_percentage_with_fractional_pct_rounds_half_up(): void
    {
        // face_value=10.00, +7.5% → 10.00 + 0.75 = 10.75
        $base = MoneyCalculator::of('10.00', 'usd');
        $markup = $base->multiply('7.5')->divide('100');
        $result = $base->add($markup);

        $this->assertSame(10.75, MoneyCalculator::toFloat($result));
    }

    public function test_percentage_result_that_needs_rounding(): void
    {
        // face_value=10.00, +7.7% → 10.00 + 0.77 = 10.77
        $base = MoneyCalculator::of('10.00', 'usd');
        $markup = $base->multiply('7.7')->divide('100');
        $result = $base->add($markup);

        $this->assertSame(10.77, MoneyCalculator::toFloat($result));
    }

    public function test_percentage_avoids_float_drift_on_non_round_base(): void
    {
        // face_value=10.50, +7.5% → 10.50 + 0.79 (0.7875 rounds to 0.79) = 11.29
        $base = MoneyCalculator::of('10.50', 'usd');
        $markup = $base->multiply('7.5')->divide('100');
        $result = $base->add($markup);

        $this->assertSame(11.29, MoneyCalculator::toFloat($result));
    }

    public function test_absolute_addition_is_precise(): void
    {
        // face_value=20.00, +5.00 → 25.00
        $base = MoneyCalculator::of('20.00', 'usd');
        $amount = MoneyCalculator::of('5.00', 'usd');
        $result = $base->add($amount);

        $this->assertSame(25.00, MoneyCalculator::toFloat($result));
    }

    public function test_absolute_subtraction_is_precise(): void
    {
        // face_value=20.00, -3.50 → 16.50
        $base = MoneyCalculator::of('20.00', 'usd');
        $amount = MoneyCalculator::of('3.50', 'usd');
        $result = $base->subtract($amount);

        $this->assertSame(16.50, MoneyCalculator::toFloat($result));
    }

    public function test_greater_than_returns_true_when_larger(): void
    {
        $higher = MoneyCalculator::of('10.00', 'usd');
        $lower = MoneyCalculator::of('5.00', 'usd');

        $this->assertTrue($higher->greaterThan($lower));
    }

    public function test_greater_than_returns_false_when_equal(): void
    {
        $a = MoneyCalculator::of('10.00', 'usd');
        $b = MoneyCalculator::of('10.00', 'usd');

        $this->assertFalse($a->greaterThan($b));
    }

    public function test_less_than_returns_true_when_smaller(): void
    {
        $lower = MoneyCalculator::of('4.99', 'usd');
        $higher = MoneyCalculator::of('5.00', 'usd');

        $this->assertTrue($lower->lessThan($higher));
    }

    public function test_less_than_returns_false_when_equal(): void
    {
        $a = MoneyCalculator::of('10.00', 'usd');
        $b = MoneyCalculator::of('10.00', 'usd');

        $this->assertFalse($a->lessThan($b));
    }

    public function test_zero_floor_guard_used_in_pricing_rule(): void
    {
        $calculatedPrice = MoneyCalculator::of('-5.00', 'usd');
        $zero = MoneyCalculator::zero('usd');

        $finalPrice = $calculatedPrice->greaterThan($zero) ? $calculatedPrice : $zero;

        $this->assertSame(0.0, MoneyCalculator::toFloat($finalPrice));
    }

    public function test_cost_price_guard_used_in_pricing_rule(): void
    {
        $finalPrice = MoneyCalculator::of('8.00', 'usd');
        $costPrice = MoneyCalculator::of('9.00', 'usd');

        $this->assertTrue($finalPrice->lessThan($costPrice));
    }

    public function test_sale_order_total_matches_sum_of_subtotals(): void
    {
        // 2 × $9.99 + 1 × $14.99 = 19.98 + 14.99 = 34.97
        $itemA = MoneyCalculator::of('9.99', 'usd')->multiply(2);
        $itemB = MoneyCalculator::of('14.99', 'usd')->multiply(1);

        $total = $itemA->add($itemB);

        $this->assertSame(34.97, MoneyCalculator::toFloat($total));
    }

    public function test_discount_selling_price_calculation_is_exact(): void
    {
        // face_value=100.00, discount=5% → selling_price=95.00
        $faceValue = MoneyCalculator::of('100.00', 'usd');
        $discountAmount = $faceValue->multiply('5')->divide('100');
        $sellingPrice = $faceValue->subtract($discountAmount);

        $this->assertSame(95.00, MoneyCalculator::toFloat($sellingPrice));
    }

    public function test_discount_selling_price_does_not_drift_with_fractional_discount(): void
    {
        // face_value=99.99, discount=3.33% → 99.99 * 0.9667 = 96.659...
        // Without Money this drifts; with Money it rounds to 96.66
        $faceValue = MoneyCalculator::of('99.99', 'usd');
        $discountAmount = $faceValue->multiply('3.33')->divide('100');
        $sellingPrice = $faceValue->subtract($discountAmount);

        // Verify it's a clean 2-decimal float, not something like 96.65999...
        $this->assertSame(MoneyCalculator::toFloat($sellingPrice), round(MoneyCalculator::toFloat($sellingPrice), 2));
    }
}
