<?php

namespace App\Domain\Payments;

use App\Models\Contract;

final class SettlementQuoteSnapshot
{
    /** @param array<string, mixed> $plan */
    public function create(Contract $contract, int $actorId, string $scope, array $requestedIds, array $calculation, array $ruleSnapshot, int $amount, string $asOf): array
    {
        $resolvedIds = $calculation['installment_ids'];
        sort($requestedIds);
        sort($resolvedIds);
        $plan = $calculation['plan'];
        $snapshot = [
            'contract_id' => (int) $contract->id,
            'customer_id' => (int) $contract->customer_id,
            'actor_id' => $actorId,
            'scope' => $scope,
            'requested_installment_ids' => array_values($requestedIds),
            'installment_ids' => array_values($resolvedIds),
            'amount_toman' => $amount,
            'calculation_date' => $asOf,
            'calculation_version' => $calculation['calculation_version'],
            'financial_rules_snapshot' => $ruleSnapshot,
            'full_settlement_toman' => (int) $calculation['quote']['full_settlement_total'],
            'principal_total' => (int) $calculation['quote']['principal_total'],
            'normal_penalty_total' => (int) $calculation['quote']['normal_penalty_total'],
            'legal_penalty_total' => (int) $calculation['quote']['legal_penalty_total'],
            'reward_total' => (int) $calculation['quote']['reward_total'],
            'states' => $calculation['states'],
            'allocations' => $plan['allocations'],
        ];

        return ['snapshot' => $snapshot, 'hash' => hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE))];
    }
}
