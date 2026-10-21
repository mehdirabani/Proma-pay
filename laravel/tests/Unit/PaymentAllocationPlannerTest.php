<?php

namespace Tests\Unit;

use App\Domain\Payments\PaymentAllocationPlanner;
use DomainException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PaymentAllocationPlannerTest extends TestCase
{
    #[Test]
    public function legacy_golden_selected_payment_uses_financial_then_due_and_installment_order(): void
    {
        $planner = new PaymentAllocationPlanner;
        $rows = [
            $this->row(3, 3, '2026-08-01', 2_000_000),
            $this->row(2, 2, '2026-08-01', 1_500_000),
            $this->row(1, 1, '2026-08-01', 1_500_000),
        ];

        $plan = $planner->plan($rows, 4_000_000);

        $this->assertSame([1_500_000, 1_500_000, 1_000_000], array_column($plan['allocations'], 'allocated_amount'));
        $this->assertSame([1, 2, 3], array_column($plan['allocations'], 'installment_id'));
        $this->assertSame(0, $plan['unused_amount']);
        $this->assertSame('partial', $plan['allocations'][2]['final_status']);
        $this->assertSame(0, $plan['allocations'][2]['reward_applied']);
    }

    #[Test]
    public function legacy_golden_allocation_pays_legal_penalty_then_normal_penalty_then_principal(): void
    {
        $planner = new PaymentAllocationPlanner;
        $row = $this->row(11, 1, '2026-07-01', 500_000, 1);
        $row['normal_penalty'] = 30_000;
        $row['legal_penalty'] = 50_000;
        $row['eligible_reward'] = 20_000;
        $row['final_payable'] = 560_000;

        $partial = $planner->plan([$row], 60_000)['allocations'][0];
        $this->assertSame(50_000, $partial['legal_penalty_applied']);
        $this->assertSame(10_000, $partial['normal_penalty_applied']);
        $this->assertSame(0, $partial['principal_applied']);
        $this->assertSame(0, $partial['reward_applied']);

        $full = $planner->plan([$row], 560_000)['allocations'][0];
        $this->assertSame(500_000, $full['principal_applied']);
        $this->assertSame(30_000, $full['normal_penalty_applied']);
        $this->assertSame(50_000, $full['legal_penalty_applied']);
        $this->assertSame(20_000, $full['reward_applied']);
        $this->assertTrue($full['is_full_settlement']);
    }

    #[Test]
    public function legacy_financial_priority_precedes_checkbox_and_installment_order(): void
    {
        $planner = new PaymentAllocationPlanner;
        $legalOverdue = $this->row(5, 5, '2026-01-05', 100, 0);
        $normalOverdue = $this->row(4, 4, '2026-01-04', 100, 1);
        $dueToday = $this->row(3, 3, '2026-01-03', 100, 2);
        $future = $this->row(1, 1, '2026-01-01', 100, 5);

        $plan = $planner->plan([$future, $dueToday, $normalOverdue, $legalOverdue], 150);

        $this->assertSame([5, 4, 3, 1], array_column($plan['allocations'], 'installment_id'));
        $this->assertSame([100, 50, 0, 0], array_column($plan['allocations'], 'allocated_amount'));
    }

    #[Test]
    public function zero_amount_and_amount_above_selected_quote_are_rejected(): void
    {
        $planner = new PaymentAllocationPlanner;
        $rows = [$this->row(1, 1, '2026-08-01', 100)];

        try {
            $planner->plan($rows, 0);
            $this->fail('Zero payments must be rejected.');
        } catch (DomainException $exception) {
            $this->assertSame('مبلغ پرداخت باید بیشتر از صفر باشد.', $exception->getMessage());
        }

        $this->expectException(DomainException::class);
        $planner->plan($rows, 101);
    }

    /** @return array<string, int|string|bool> */
    private function row(int $id, int $number, string $dueDate, int $principal, int $priority = 5): array
    {
        return [
            'id' => $id,
            'contract_id' => 77,
            'installment_number' => $number,
            'due_date' => $dueDate,
            'financial_priority' => $priority,
            'original_principal' => $principal,
            'remaining_principal' => $principal,
            'normal_penalty' => 0,
            'legal_penalty' => 0,
            'eligible_reward' => 0,
            'final_payable' => $principal,
            'payment_allowed' => true,
            'status' => 'pending',
        ];
    }
}
