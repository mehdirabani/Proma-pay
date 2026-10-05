<?php

namespace Tests\Unit;

use App\Domain\Contracts\ContractPricingCalculator;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ContractPricingCalculatorTest extends TestCase
{
    public function test_simple_and_compound_quotes_match_the_legacy_golden_case(): void
    {
        $calculator = new ContractPricingCalculator;

        $simple = $calculator->quote('1,000,000', '0', '۵٪', 2, 'simple');
        $compound = $calculator->quote('۱٬۰۰۰٬۰۰۰', '۰', '۵', 2, 'compound');

        $this->assertSame('legacy-contract-finance-v1', $simple['calculation_version']);
        $this->assertSame(50_000, $simple['monthly_rate_units']);
        $this->assertSame('600000', $simple['installment_amount_toman']);
        $this->assertSame('1200000', $simple['total_installments_toman']);
        $this->assertSame('600000', $compound['installment_amount_toman']);
        $this->assertSame('1200000', $compound['total_installments_toman']);
    }

    public function test_down_payment_is_removed_before_financing_and_zero_rate_rounds_each_installment_up(): void
    {
        $quote = (new ContractPricingCalculator)->quote('۱٬۵۰۰٬۰۰۰', '۵۰۰٬۰۰۰', '۰', 3, 'simple');

        $this->assertSame('1000000', $quote['financed_toman']);
        $this->assertSame('400000', $quote['installment_amount_toman']);
        $this->assertSame('1200000', $quote['total_installments_toman']);
    }

    public function test_due_dates_keep_the_original_day_across_short_months(): void
    {
        $dates = iterator_to_array((new ContractPricingCalculator)->dueDates('2026-01-31', 4), false);

        $this->assertSame(['2026-01-31', '2026-02-28', '2026-03-31', '2026-04-30'], $dates);
    }

    #[DataProvider('invalidQuoteProvider')]
    public function test_invalid_quote_inputs_are_rejected(string|int $principal, string|int $down, string|int $rate, int $months, string $type): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new ContractPricingCalculator)->quote($principal, $down, $rate, $months, $type);
    }

    public static function invalidQuoteProvider(): array
    {
        return [
            'negative principal' => ['-100', '0', '0', 1, 'simple'],
            'down payment above principal' => ['100', '101', '0', 1, 'simple'],
            'zero installments' => ['100', '0', '0', 0, 'simple'],
            'unknown interest type' => ['100', '0', '0', 1, 'flat'],
            'malformed rate' => ['100', '0', '5.12345', 1, 'simple'],
        ];
    }
}
