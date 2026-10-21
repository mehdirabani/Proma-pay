<?php

namespace Tests\Unit;

use App\Domain\Payments\PaymentAllocationPlanner;
use App\Domain\Payments\SettlementQuotePlanner;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SettlementQuotePlannerTest extends TestCase
{
    public function test_schedule_scope_includes_all_rows_and_selected_scope_keeps_only_explicit_ids(): void
    {
        $planner = new SettlementQuotePlanner(new PaymentAllocationPlanner);
        $states = [$this->row(1, 1, '2026-11-01'), $this->row(2, 2, '2026-12-01'), $this->row(3, 3, '2027-01-01')];

        $selected = $planner->plan($states, [2], 'selected', 500);
        $schedule = $planner->plan($states, [], 'schedule', 1_500);

        $this->assertSame([2], $selected['installment_ids']);
        $this->assertSame([1, 2, 3], $schedule['installment_ids']);
        $this->assertSame([1_000, 500, 0], array_column($schedule['plan']['allocations'], 'allocated_amount'));
    }

    public function test_forward_scope_expands_from_the_earliest_selected_schedule_position(): void
    {
        $planner = new SettlementQuotePlanner(new PaymentAllocationPlanner);
        $states = [$this->row(1, 1, '2026-11-01'), $this->row(2, 2, '2026-12-01'), $this->row(3, 3, '2027-01-01')];

        $result = $planner->plan($states, [3, 2], 'forward', 2_000);

        $this->assertSame([2, 3], $result['installment_ids']);
        $this->assertSame([2, 3], array_column($result['plan']['allocations'], 'installment_id'));
    }

    public function test_selected_scope_rejects_ids_from_a_different_contract(): void
    {
        $planner = new SettlementQuotePlanner(new PaymentAllocationPlanner);

        $this->expectException(ValidationException::class);
        $planner->plan([$this->row(1, 1, '2026-11-01')], [2], 'selected', 500);
    }

    /** @return array<string, int|string|bool> */
    private function row(int $id, int $number, string $dueDate): array
    {
        return [
            'id' => $id,
            'contract_id' => 77,
            'installment_number' => $number,
            'due_date' => $dueDate,
            'financial_priority' => $number,
            'original_principal' => 1_000,
            'remaining_principal' => 1_000,
            'normal_penalty' => 0,
            'legal_penalty' => 0,
            'eligible_reward' => 0,
            'final_payable' => 1_000,
            'payment_allowed' => true,
            'status' => 'pending',
        ];
    }
}
