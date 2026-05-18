<?php

namespace App\Support;

use Money\Money;
use Money\Currency;
use Money\Currencies\ISOCurrencies;
use Money\Formatter\DecimalMoneyFormatter;
use Money\Parser\DecimalMoneyParser;

/**
 * Thin wrapper around moneyphp/money for precise monetary arithmetic.
 *
 * All monetary values are stored as integers in the smallest currency unit
 * (e.g. cents for USD) to eliminate floating-point precision issues.
 */
class MoneyCalculator
{
    private static ?ISOCurrencies $currencies = null;

    private static ?DecimalMoneyParser $parser = null;

    private static ?DecimalMoneyFormatter $formatter = null;

    private static function currencies(): ISOCurrencies
    {
        return self::$currencies ??= new ISOCurrencies();
    }

    private static function parser(): DecimalMoneyParser
    {
        return self::$parser ??= new DecimalMoneyParser(self::currencies());
    }

    private static function formatter(): DecimalMoneyFormatter
    {
        return self::$formatter ??= new DecimalMoneyFormatter(self::currencies());
    }

    /**
     * Parse a decimal amount and currency code into a Money object.
     * The currency stored in the database is lowercase (e.g. 'usd'); it is
     * uppercased automatically to match ISO 4217 codes.
     */
    public static function of(string|float|int $amount, string $currency): Money
    {
        return self::parser()->parse((string) $amount, new Currency(strtoupper($currency)));
    }

    /**
     * Return a zero-value Money object for the given currency.
     */
    public static function zero(string $currency): Money
    {
        return new Money(0, new Currency(strtoupper($currency)));
    }

    /**
     * Format a Money object back to a float suitable for database storage.
     * Precision is determined by the ISO currency scale (2 decimal places for USD/EUR/GBP).
     */
    public static function toFloat(Money $money): float
    {
        return (float) self::formatter()->format($money);
    }
}
