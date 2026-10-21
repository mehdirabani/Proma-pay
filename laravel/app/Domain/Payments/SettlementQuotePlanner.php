<?php

namespace App\Domain\Payments;

use Illuminate\Validation\ValidationException;

/** Resolves Legacy selected/schedule/forward scopes and creates a canonical quote plan. */
final class SettlementQuotePlanner
{
    public function __construct(private readonly PaymentAllocationPlanner $planner) {}

    /** @param array<int, array<string, mixed>> $states
     * @param  array<int, int>  $requestedInstallmentIds
     * @return array<string, mixed>
     */
    public function plan(array $states, array $requestedInstallmentIds, string $scope, int|string $amount): array
    {
        $requestedInstallmentIds = array_values(array_unique(array_map('intval', $requestedInstallmentIds)));
        sort($requestedInstallmentIds);
        $allIds = array_map(static fn (array $row): int => (int) $row['id'], $states);
        if (array_diff($requestedInstallmentIds, $allIds) !== []) {
            throw ValidationException::withMessages(['installment_ids' => 'یکی از اقساط انتخاب‌شده به این قرارداد تعلق ندارد.']);
        }

        $resolvedIds = match ($scope) {
            'selected' => $requestedInstallmentIds,
            'schedule' => $allIds,
            'forward' => $this->forwardIds($states, $requestedInstallmentIds),
            default => throw ValidationException::withMessages(['scope' => 'دامنهٔ تخصیص پرداخت معتبر نیست.']),
        };
        if ($resolvedIds === []) {
            throw ValidationException::withMessages(['installment_ids' => 'برای دامنهٔ انتخاب‌شده یک یا چند قسط مشخص کنید.']);
        }

        $resolvedMap = array_fill_keys($resolvedIds, true);
        $rows = array_values(array_filter($states, static fn (array $row): bool => isset($resolvedMap[(int) $row['id']])));
        try {
            $quote = $this->planner->quote($rows);
            if ($quote['full_settlement_total'] <= 0) {
                throw ValidationException::withMessages(['installment_ids' => 'در اقساط انتخاب‌شده بدهی قابل پرداختی وجود ندارد.']);
            }
            $plan = $this->planner->plan($rows, $amount);
        } catch (\DomainException $exception) {
            throw ValidationException::withMessages(['amount_toman' => $exception->getMessage()]);
        }

        return [
            'scope' => $scope,
            'requested_installment_ids' => $requestedInstallmentIds,
            'installment_ids' => $resolvedIds,
            'states' => $rows,
            'quote' => $quote,
            'plan' => $plan,
            'calculation_version' => InstallmentFinancialStateCalculator::CALCULATION_VERSION.'+'.PaymentAllocationPlanner::ALLOCATION_VERSION,
        ];
    }

    /** @param array<int, array<string, mixed>> $states
     * @param  array<int, int>  $requestedInstallmentIds
     * @return array<int, int>
     */
    private function forwardIds(array $states, array $requestedInstallmentIds): array
    {
        if ($requestedInstallmentIds === []) {
            throw ValidationException::withMessages(['installment_ids' => 'برای دامنهٔ «از قسط انتخاب‌شده به بعد» حداقل یک قسط شروع انتخاب کنید.']);
        }

        usort($states, static function (array $left, array $right): int {
            $date = strcmp((string) $left['due_date'], (string) $right['due_date']);

            return $date !== 0 ? $date : (((int) $left['installment_number'] <=> (int) $right['installment_number']) ?: ((int) $left['id'] <=> (int) $right['id']));
        });

        $positions = [];
        foreach ($states as $position => $row) {
            $positions[(int) $row['id']] = $position;
        }

        $startAt = null;
        foreach ($requestedInstallmentIds as $id) {
            if (isset($positions[$id])) {
                $startAt = $startAt === null ? $positions[$id] : min($startAt, $positions[$id]);
            }
        }
        if ($startAt === null) {
            throw ValidationException::withMessages(['installment_ids' => 'قسط شروع تخصیص در قرارداد پیدا نشد.']);
        }

        return array_map(static fn (array $row): int => (int) $row['id'], array_slice($states, $startAt));
    }
}
