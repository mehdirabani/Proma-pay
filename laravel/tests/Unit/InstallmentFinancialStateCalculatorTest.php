<?php

namespace Tests\Unit;

use App\Domain\Payments\InstallmentFinancialStateCalculator;
use App\Domain\Payments\MoneyMath;
use App\Domain\Payments\PaymentAllocationPlanner;
use Tests\TestCase;

class InstallmentFinancialStateCalculatorTest extends TestCase
{
    public function test_normal_penalty_matches_legacy_monthly_rate_golden_case(): void
    {
        $state = (new InstallmentFinancialStateCalculator)->state(
            $this->installment('2026-01-01'),
            [],
            $this->settings(['monthly_penalty_rate' => '6']),
            '2026-01-31',
        );

        $this->assertSame(60_000, $state['normal_penalty']);
        $this->assertSame(0, $state['legal_penalty']);
        $this->assertSame(1_060_000, $state['final_payable']);
        $this->assertTrue($state['payment_allowed']);
    }

    public function test_actual_legal_referral_uses_due_date_origin_and_missing_rate_fails_closed(): void
    {
        $calculator = new InstallmentFinancialStateCalculator;
        $row = $this->installment('2026-01-01', ['legal_started_at' => '2026-01-16 09:30:00']);
        $state = $calculator->state($row, [], $this->settings(['legal_monthly_penalty_rate' => '15']), '2026-01-31');

        $this->assertSame(0, $state['normal_penalty']);
        $this->assertSame(150_000, $state['legal_penalty']);
        $this->assertSame(1_150_000, $state['final_payable']);

        $missingRate = $calculator->state($row, [], $this->settings(['legal_monthly_penalty_rate' => '0']), '2026-01-31');
        $this->assertSame('configuration_missing', $missingRate['calculation_status']);
        $this->assertFalse($missingRate['payment_allowed']);

        $inconsistent = $calculator->state(
            $this->installment('2026-01-01', ['inconsistent_legal_referral_count' => 1]),
            [],
            $this->settings(),
            '2026-01-31',
        );
        $this->assertSame('inconsistent_referral', $inconsistent['calculation_status']);
        $this->assertFalse($inconsistent['payment_allowed']);
    }

    public function test_internal_legal_case_does_not_change_normal_payable_penalty(): void
    {
        $row = $this->installment('2026-01-01', ['legal_case_count' => 1]);
        $state = (new InstallmentFinancialStateCalculator)->state(
            $row,
            [],
            $this->settings(['monthly_penalty_rate' => '6', 'legal_monthly_penalty_rate' => '15']),
            '2026-01-31',
        );

        $this->assertSame(60_000, $state['normal_penalty']);
        $this->assertSame(0, $state['legal_penalty']);
        $this->assertSame(1_060_000, $state['final_payable']);
    }

    public function test_partial_payment_replays_explicit_components_and_receives_no_reward(): void
    {
        $payments = [[
            'id' => 1,
            'status' => 'paid',
            'payment_type' => 'installment',
            'payment_date' => '2026-01-16',
            'amount_toman' => 500_000,
            'principal_applied' => 500_000,
            'normal_penalty_applied' => 0,
            'legal_penalty_applied' => 0,
            'reward_applied' => 0,
        ]];
        $state = (new InstallmentFinancialStateCalculator)->state(
            $this->installment('2026-02-01'),
            $payments,
            $this->settings(['monthly_reward_rate' => '5']),
            '2026-01-31',
        );

        $this->assertSame(500_000, $state['remaining_principal']);
        $this->assertGreaterThan(0, $state['eligible_reward']);
        $partialAllocation = (new PaymentAllocationPlanner)->plan([$state], 100_000)['allocations'][0];
        $this->assertSame(0, $partialAllocation['reward_applied']);
        $this->assertSame(1, $state['effective_payment_count']);
    }

    public function test_corrected_future_and_down_payment_rows_are_not_replayed(): void
    {
        $payments = [
            ['id' => 1, 'status' => 'paid', 'payment_type' => 'down_payment', 'payment_date' => '2026-01-02', 'amount_toman' => 1_000_000],
            ['id' => 2, 'status' => 'paid', 'payment_type' => 'installment', 'payment_date' => '2026-01-03', 'amount_toman' => 1_000_000, 'is_corrected' => 1],
            ['id' => 3, 'status' => 'paid', 'payment_type' => 'installment', 'payment_date' => '2026-02-02', 'amount_toman' => 1_000_000],
        ];
        $state = (new InstallmentFinancialStateCalculator)->state(
            $this->installment('2026-01-01'),
            $payments,
            $this->settings(),
            '2026-01-31',
        );

        $this->assertSame(1_000_000, $state['remaining_principal']);
        $this->assertSame(0, $state['effective_payment_count']);
    }

    public function test_late_partial_payment_applies_penalty_before_principal_through_planner(): void
    {
        $state = (new InstallmentFinancialStateCalculator)->state(
            $this->installment('2026-07-01'),
            [],
            $this->settings(['monthly_penalty_rate' => '3']),
            '2026-07-26',
        );
        $allocation = (new PaymentAllocationPlanner)->plan([$state], 100_000)['allocations'][0];

        $this->assertSame(25_000, $state['normal_penalty']);
        $this->assertSame(25_000, $allocation['normal_penalty_applied']);
        $this->assertSame(75_000, $allocation['principal_applied']);
        $this->assertSame(0, $allocation['reward_applied']);
    }

    public function test_money_math_accepts_persian_rate_digits_and_ceil_rounds(): void
    {
        $rate = MoneyMath::rateUnits('۶٫۵');
        $this->assertSame(0, $rate, 'Unsupported decimal punctuation must not silently become a rate.');
        $rate = MoneyMath::rateUnits('۶.۵');
        $this->assertSame(65_000, $rate);
        $this->assertSame(2, MoneyMath::ceilDiv(3, 2));
        $this->assertSame(1_001, MoneyMath::rateForDays(1_000_001, 30_000, 1));
    }

    private function installment(string $dueDate, array $overrides = []): array
    {
        return $overrides + [
            'id' => 15601,
            'contract_id' => 156,
            'installment_number' => 1,
            'due_date' => $dueDate,
            'base_amount' => 1_000_000,
            'paid_amount' => 0,
            'remaining_amount' => 1_000_000,
            'status' => 'pending',
            'contract_status' => 'active',
        ];
    }

    private function settings(array $overrides = []): array
    {
        return $overrides + [
            'monthly_penalty_rate' => '0',
            'legal_monthly_penalty_rate' => '0',
            'monthly_reward_rate' => '0',
            'late_penalty_grace_days' => '0',
        ];
    }
}
