<?php

/**
 * Evidence-grounded rules layered on the existing payment and installment
 * services. It never writes to source financial tables.
 */
final class FinancialReconciliationEngine
{
    public const RULE_VERSION = 'financial-reconciliation-v1';
    public const COVERAGE_BASIS = 'first-observed-core-ledger-event-is-a-lower-bound-only';

    public static function inspectContract(int $contractId, string $asOf, int $limit = 5000): array
    {
        $contract = Model::fetch('SELECT id, customer_id, contract_number, status FROM contracts WHERE id = ? LIMIT 1', [$contractId]);
        if (!$contract) throw new InvalidArgumentException('قرارداد برای تطبیق پیدا نشد.', 404);
        $limit = max(1, min(5000, $limit));
        $findings = [];
        $truncatedSources = [];
        foreach ([
            'installments' => ['SELECT COUNT(*) AS total FROM installments WHERE contract_id=?', [$contractId]],
            'payments' => ['SELECT COUNT(*) AS total FROM payments WHERE contract_id=?', [$contractId]],
            'ledger_events' => ['SELECT COUNT(*) AS total FROM financial_ledger_events WHERE contract_id=?', [$contractId]],
            'legal_costs' => ["SELECT COUNT(*) AS total FROM legal_case_costs WHERE contract_id=? AND approval_status IN ('approved','reversed') AND chargeable_to_customer=1", [$contractId]],
        ] as $name => [$sql, $params]) {
            $count = (int) (Model::fetch($sql, $params)['total'] ?? 0);
            if ($count > $limit) $truncatedSources[$name] = ['observed_count' => $count, 'limit' => $limit];
        }
        $installmentIssues = InstallmentReconciliationService::scan($limit, $contractId, $asOf);
        foreach ($installmentIssues as $issue) {
            $ruleId = (string) ($issue['rule_id'] ?? 'R01');
            $entity = self::entityForIssue($issue);
            $kind = (string) ($issue['issue_type'] ?? 'financial_mismatch');
            $findings[] = self::finding($ruleId, $kind, $entity, $contract, $issue, $issue['evidence'] ?? $issue);
        }

        $payments = Model::fetchAll(
            'SELECT p.id, p.installment_id, p.payment_group_id, p.contract_id, p.user_id, p.amount, p.method,
                    p.status, p.payment_type, p.is_corrected, p.correction_reason, p.payment_date, p.paid_at,
                    p.created_at, p.principal_applied, p.normal_penalty_applied, p.legal_penalty_applied,
                    p.reward_applied, p.calculated_reward, p.remaining_after_payment,
                    c.customer_id, c.contract_number
             FROM payments p JOIN contracts c ON c.id = p.contract_id WHERE p.contract_id = ? ORDER BY p.id LIMIT ' . $limit,
            [$contractId]
        );
        $paymentIds = array_map(static function ($payment) { return (int) $payment['id']; }, $payments);
        $events = Model::fetchAll(
            'SELECT * FROM financial_ledger_events WHERE contract_id = ? ORDER BY id LIMIT ' . $limit,
            [$contractId]
        );
        $eventsByPayment = [];
        foreach ($events as $event) {
            if (($event['source_type'] ?? '') === 'payment' && !empty($event['source_id'])) {
                $eventsByPayment[(int) $event['source_id']][] = $event;
            }
        }
        // Source links are not constrained by a foreign key in old schemas.
        if ($paymentIds) {
            $placeholders = implode(',', array_fill(0, count($paymentIds), '?'));
            foreach (Model::fetchAll("SELECT * FROM financial_ledger_events WHERE source_type = 'payment' AND source_id IN ({$placeholders}) ORDER BY id", $paymentIds) as $event) {
                $eventsByPayment[(int) $event['source_id']][] = $event;
            }
        }
        foreach ($eventsByPayment as &$linked) {
            $unique = [];
            foreach ($linked as $event) $unique[(int) $event['id']] = $event;
            $linked = array_values($unique);
        }
        unset($linked);

        $allEventIds = [];
        foreach ($events as $event) $allEventIds[] = (int) $event['id'];
        foreach ($eventsByPayment as $linked) foreach ($linked as $event) $allEventIds[] = (int) $event['id'];
        $allEventIds = array_values(array_unique(array_filter($allEventIds)));
        $linesByEvent = [];
        if ($allEventIds) {
            $placeholders = implode(',', array_fill(0, count($allEventIds), '?'));
            foreach (Model::fetchAll("SELECT * FROM financial_ledger_lines WHERE event_id IN ({$placeholders}) ORDER BY id", $allEventIds) as $line) {
                $linesByEvent[(int) $line['event_id']][] = $line;
            }
        }
        $firstLedger = Model::fetch('SELECT MIN(created_at) AS first_event_at FROM financial_ledger_events') ?: [];
        $firstLedgerAt = $firstLedger['first_event_at'] ?? null;

        $allocationsByPayment = [];
        $legalAllocationsByPayment = [];
        if ($paymentIds) {
            $placeholders = implode(',', array_fill(0, count($paymentIds), '?'));
            foreach (Model::fetchAll("SELECT * FROM payment_allocations WHERE payment_id IN ({$placeholders}) ORDER BY id", $paymentIds) as $allocation) {
                $allocationsByPayment[(int) $allocation['payment_id']][] = $allocation;
            }
            foreach (Model::fetchAll("SELECT * FROM legal_cost_payment_allocations WHERE payment_id IN ({$placeholders}) ORDER BY id", $paymentIds) as $allocation) {
                $legalAllocationsByPayment[(int) $allocation['payment_id']][] = $allocation;
            }
        }

        $correctionRows = [];
        if ($paymentIds) {
            $placeholders = implode(',', array_fill(0, count($paymentIds), '?'));
            foreach (Model::fetchAll("SELECT payment_id, snapshot_json FROM payment_corrections WHERE payment_id IN ({$placeholders})", $paymentIds) as $correction) {
                $correctionRows[(int) $correction['payment_id']] = $correction;
            }
        }

        foreach ($payments as $payment) {
            $paymentId = (int) $payment['id'];
            $isPaid = ($payment['status'] ?? '') === 'paid' && (int) ($payment['is_corrected'] ?? 0) !== 1;
            $isCorrected = (int) ($payment['is_corrected'] ?? 0) === 1 || ($payment['status'] ?? '') === 'corrected';
            $paymentType = (string) ($payment['payment_type'] ?? 'installment');
            $linkedEvents = $eventsByPayment[$paymentId] ?? [];
            $paymentEvents = array_values(array_filter($linkedEvents, static function ($event) {
                return ($event['event_type'] ?? '') === 'payment';
            }));
            $allocations = $allocationsByPayment[$paymentId] ?? [];
            $legalAllocations = $legalAllocationsByPayment[$paymentId] ?? [];
            $ledgerEligible = $paymentType === 'down_payment' || $paymentType === 'legal_cost'
                || !empty($payment['payment_group_id']) || $allocations || $legalAllocations;

            if ($isPaid && !$paymentEvents && !$linkedEvents) {
                $date = (string) ($payment['paid_at'] ?: ($payment['payment_date'] ?: $payment['created_at']));
                $beforeFirstObservedEvent = !$firstLedgerAt || ($date !== '' && strcmp($date, (string) $firstLedgerAt) < 0);
                $classification = $beforeFirstObservedEvent ? 'historical_coverage_gap' : 'insufficient_evidence';
                $findings[] = self::finding('R08', 'payment_without_core_ledger_event', 'payment', $contract, [
                    'payment_id' => $paymentId,
                    'severity' => $beforeFirstObservedEvent ? 'info' : 'review',
                    'classification' => $classification,
                    'coverage_status' => $beforeFirstObservedEvent ? 'pre_first_observed_event' : 'coverage_not_proven_for_source',
                    'expected_toman' => null,
                    'actual_toman' => normalize_money($payment['amount'] ?? 0),
                    'difference_toman' => null,
                    'source_fingerprint' => null,
                ], [
                    'payment_id' => $paymentId,
                    'payment_type' => $paymentType,
                    'payment_status' => (string) ($payment['status'] ?? ''),
                    'created_at' => $payment['created_at'] ?? null,
                    'paid_at' => $payment['paid_at'] ?? null,
                    'payment_date' => $payment['payment_date'] ?? null,
                    'amount_toman' => normalize_money($payment['amount'] ?? 0),
                    'ledger_event_expected_by_current_path' => $ledgerEligible,
                    'first_observed_ledger_event_at' => $firstLedgerAt,
                    'coverage_boundary_is_only_a_lower_bound' => true,
                ]);
            }
            if (!$paymentEvents && $linkedEvents) {
                foreach ($linkedEvents as $event) {
                    $findings[] = self::finding('R02', 'payment_ledger_event_type_mismatch', 'ledger_event', $contract, [
                        'payment_id' => $paymentId, 'ledger_event_id' => (int) $event['id'], 'severity' => 'critical',
                        'classification' => 'verified_mismatch', 'coverage_status' => 'direct_source_link_wrong_event_type',
                    ], ['payment_id' => $paymentId, 'event_id' => (int) $event['id'], 'expected_event_type' => 'payment', 'actual_event_type' => (string) $event['event_type']]);
                }
            }
            if ($paymentEvents) {
                if (count($paymentEvents) > 1) {
                    $findings[] = self::finding('R02', 'duplicate_payment_ledger_event', 'payment', $contract, [
                        'payment_id' => $paymentId, 'severity' => 'critical', 'classification' => 'verified_mismatch',
                        'coverage_status' => 'direct_source_link', 'expected_toman' => 1, 'actual_toman' => count($paymentEvents),
                        'difference_toman' => 1 - count($paymentEvents),
                    ], ['payment_id' => $paymentId, 'event_ids' => array_map(static function ($event) { return (int) $event['id']; }, $paymentEvents)]);
                }
                foreach ($paymentEvents as $event) {
                    $eventId = (int) $event['id'];
                    if ((string) $event['idempotency_key'] !== 'payment:' . $paymentId) {
                        $findings[] = self::finding('R02', 'payment_ledger_idempotency_key_mismatch', 'ledger_event', $contract, [
                            'payment_id' => $paymentId, 'ledger_event_id' => $eventId, 'severity' => 'high',
                            'classification' => 'verified_mismatch', 'coverage_status' => 'direct_source_link',
                        ], ['payment_id' => $paymentId, 'event_id' => $eventId, 'expected_idempotency_key' => 'payment:' . $paymentId, 'actual_idempotency_key' => (string) $event['idempotency_key']]);
                    }
                    if ((int) $event['contract_id'] !== $contractId || (int) $event['customer_id'] !== (int) $contract['customer_id']) {
                        $findings[] = self::finding('R02', 'payment_ledger_header_mismatch', 'ledger_event', $contract, [
                            'payment_id' => $paymentId, 'ledger_event_id' => $eventId, 'severity' => 'critical',
                            'classification' => 'verified_mismatch', 'coverage_status' => 'direct_source_link',
                        ], ['payment_id' => $paymentId, 'event_id' => $eventId, 'ledger_contract_id' => (int) $event['contract_id'], 'source_contract_id' => $contractId, 'ledger_customer_id' => (int) $event['customer_id'], 'source_customer_id' => (int) $contract['customer_id']]);
                    }
                    if ($isPaid) {
                        $expectedLines = self::expectedPaymentLines($payment, $allocations, $legalAllocations);
                        $actualLines = self::sumLines($linesByEvent[$eventId] ?? []);
                        $hasDirectBasis = $paymentType === 'down_payment' || $allocations || $legalAllocations;
                        if ($expectedLines !== $actualLines && ($hasDirectBasis || $actualLines)) {
                            $findings[] = self::finding('R02', 'payment_ledger_line_mismatch', 'ledger_event', $contract, [
                                'payment_id' => $paymentId, 'ledger_event_id' => $eventId, 'severity' => 'critical',
                                'classification' => $hasDirectBasis ? 'verified_mismatch' : 'insufficient_evidence',
                                'coverage_status' => $hasDirectBasis ? 'source_allocation_rows_available' : 'source_allocation_rows_missing',
                            ], ['payment_id' => $paymentId, 'event_id' => $eventId, 'expected_component_delta_toman' => $expectedLines, 'actual_component_delta_toman' => $actualLines]);
                        }
                    }
                    foreach ($linesByEvent[$eventId] ?? [] as $line) {
                        if (empty($line['source_line_id'])) continue;
                        $sourceRows = $paymentType === 'legal_cost' ? $legalAllocations : $allocations;
                        $sourceId = (int) $line['source_line_id'];
                        $matches = false;
                        foreach ($sourceRows as $sourceRow) {
                            if ((int) $sourceRow['id'] === $sourceId) { $matches = true; break; }
                        }
                        if (!$matches) {
                            $findings[] = self::finding('R07', 'ledger_source_line_mismatch', 'ledger_event', $contract, [
                                'payment_id' => $paymentId, 'ledger_event_id' => $eventId, 'severity' => 'high',
                                'classification' => 'verified_mismatch', 'coverage_status' => 'source_line_not_linked_to_payment',
                            ], ['payment_id' => $paymentId, 'event_id' => $eventId, 'line_id' => (int) $line['id'], 'source_line_id' => $sourceId]);
                        }
                    }
                }
            }

            if ($isCorrected && $paymentType !== 'down_payment') {
                $hasCorrection = isset($correctionRows[$paymentId]);
                $originalEvents = $paymentEvents;
                $reversals = array_values(array_filter($eventsByPayment[$paymentId] ?? [], static function ($event) {
                    return ($event['event_type'] ?? '') === 'reversal';
                }));
                if (!$hasCorrection && !$originalEvents) {
                    $when = (string) ($payment['corrected_at'] ?? $payment['created_at'] ?? '');
                    $legacy = !$firstLedgerAt || ($when !== '' && strcmp($when, (string) $firstLedgerAt) < 0);
                    $findings[] = self::finding('R08', 'legacy_correction_without_snapshot', 'payment', $contract, [
                        'payment_id' => $paymentId, 'severity' => 'info',
                        'classification' => $legacy ? 'historical_coverage_gap' : 'insufficient_evidence',
                        'coverage_status' => $legacy ? 'pre_first_observed_event' : 'correction_snapshot_missing',
                    ], ['payment_id' => $paymentId, 'corrected_at' => $payment['corrected_at'] ?? null, 'correction_reason_present' => trim((string) ($payment['correction_reason'] ?? '')) !== '', 'first_observed_ledger_event_at' => $firstLedgerAt]);
                } elseif ($hasCorrection && $originalEvents && !$reversals) {
                    $findings[] = self::finding('R05', 'corrected_payment_missing_ledger_reversal', 'payment', $contract, [
                        'payment_id' => $paymentId, 'ledger_event_id' => (int) $originalEvents[0]['id'], 'severity' => 'critical',
                        'classification' => 'verified_mismatch', 'coverage_status' => 'original_core_event_exists',
                    ], ['payment_id' => $paymentId, 'correction_record_exists' => true, 'original_event_id' => (int) $originalEvents[0]['id'], 'reversal_event_count' => 0]);
                }
            }
        }

        foreach ($events as $event) {
            if (($event['source_type'] ?? '') === 'payment') {
                $sourceId = (int) ($event['source_id'] ?? 0);
                $source = $sourceId ? Model::fetch('SELECT id,contract_id FROM payments WHERE id=? LIMIT 1', [$sourceId]) : null;
                if (!$source) {
                    $findings[] = self::finding('R07', 'ledger_payment_source_missing', 'ledger_event', $contract, [
                        'ledger_event_id' => (int) $event['id'], 'severity' => 'critical', 'classification' => 'verified_mismatch',
                        'coverage_status' => 'ledger_source_type_payment',
                    ], ['event_id' => (int) $event['id'], 'source_type' => 'payment', 'source_id' => $sourceId ?: null]);
                } elseif ((int) $source['contract_id'] !== (int) $event['contract_id']) {
                    $findings[] = self::finding('R02', 'ledger_payment_contract_reference_mismatch', 'ledger_event', $contract, [
                        'payment_id' => $sourceId, 'ledger_event_id' => (int) $event['id'], 'severity' => 'critical',
                        'classification' => 'verified_mismatch', 'coverage_status' => 'source_payment_exists_under_different_contract',
                    ], ['event_id' => (int) $event['id'], 'source_payment_contract_id' => (int) $source['contract_id'], 'ledger_contract_id' => (int) $event['contract_id']]);
                }
            } elseif (($event['source_type'] ?? '') === 'legal_cost') {
                $sourceId = (int) ($event['source_id'] ?? 0);
                $source = $sourceId ? Model::fetch('SELECT id,contract_id FROM legal_case_costs WHERE id=? LIMIT 1', [$sourceId]) : null;
                if (!$source || (int) $source['contract_id'] !== (int) $event['contract_id']) {
                    $findings[] = self::finding('R07', 'ledger_legal_cost_source_invalid', 'ledger_event', $contract, [
                        'ledger_event_id' => (int) $event['id'], 'severity' => 'critical', 'classification' => 'verified_mismatch',
                        'coverage_status' => 'ledger_source_type_legal_cost',
                    ], ['event_id' => (int) $event['id'], 'source_type' => 'legal_cost', 'source_id' => $sourceId ?: null, 'source_contract_id' => $source['contract_id'] ?? null]);
                }
            } else {
                $findings[] = self::finding('R07', 'ledger_source_type_unsupported', 'ledger_event', $contract, [
                    'ledger_event_id' => (int) $event['id'], 'severity' => 'critical', 'classification' => 'verified_mismatch',
                    'coverage_status' => 'source_type_outside_current_core_ledger_contract',
                ], ['event_id' => (int) $event['id'], 'source_type' => (string) ($event['source_type'] ?? ''), 'source_id' => $event['source_id'] ?? null]);
            }
        }

        $allEventsById = [];
        foreach ($events as $event) $allEventsById[(int) $event['id']] = $event;
        foreach ($eventsByPayment as $linked) foreach ($linked as $event) $allEventsById[(int) $event['id']] = $event;
        foreach ($allEventsById as $event) {
            if (($event['event_type'] ?? '') !== 'reversal') continue;
            $eventId = (int) $event['id'];
            $originalId = (int) ($event['reverses_event_id'] ?? 0);
            $original = $originalId ? Model::fetch('SELECT * FROM financial_ledger_events WHERE id = ? LIMIT 1', [$originalId]) : null;
            $reason = null;
            if (!$original) $reason = 'original_event_missing';
            elseif ((int) $original['contract_id'] !== (int) $event['contract_id'] || (int) $original['customer_id'] !== (int) $event['customer_id']) $reason = 'source_scope_mismatch';
            elseif (!in_array((string) $original['event_type'], ['payment', 'charge'], true)
                || (string) $original['source_type'] !== (string) $event['source_type']
                || (int) ($original['source_id'] ?? 0) !== (int) ($event['source_id'] ?? 0)) $reason = 'source_link_mismatch';
            else {
                $originalTotals = self::sumLines($linesByEvent[$originalId] ?? []);
                $reversalTotals = self::sumLines($linesByEvent[$eventId] ?? []);
                $inverse = [];
                foreach ($originalTotals as $component => $amount) $inverse[$component] = -$amount;
                ksort($inverse);
                if ($inverse !== $reversalTotals) $reason = 'reversal_amount_mismatch';
                $duplicates = Model::fetch('SELECT COUNT(*) AS total FROM financial_ledger_events WHERE reverses_event_id = ?', [$originalId]);
                if ((int) ($duplicates['total'] ?? 0) > 1) $reason = 'duplicate_reversal';
            }
            if ($reason !== null) {
                $findings[] = self::finding('R05', 'invalid_ledger_reversal', 'ledger_event', $contract, [
                    'ledger_event_id' => $eventId, 'severity' => 'critical', 'classification' => 'verified_mismatch',
                    'coverage_status' => 'reversal_chain_evidence',
                ], ['reversal_event_id' => $eventId, 'original_event_id' => $originalId ?: null, 'reason' => $reason]);
            }
        }

        $findings = array_merge($findings, self::legalCostIssues($contract, $payments, $firstLedgerAt, $limit));
        if ($truncatedSources) {
            $findings[] = self::finding('R08', 'contract_source_scan_limit_reached', 'contract', $contract, [
                'severity' => 'review', 'classification' => 'insufficient_evidence', 'coverage_status' => 'contract_scan_bounded_and_incomplete',
            ], ['truncated_sources' => $truncatedSources, 'rule_limit' => $limit]);
        }
        return ['contract' => $contract, 'findings' => self::dedupe($findings), 'complete' => !$truncatedSources, 'truncated_sources' => $truncatedSources, 'coverage' => [
            'basis' => self::COVERAGE_BASIS,
            'first_observed_core_ledger_event_at' => $firstLedgerAt,
            'coverage_is_proven_for_missing_events' => false,
            'as_of_date' => $asOf,
        ]];
    }

    private static function legalCostIssues(array $contract, array $payments, $firstLedgerAt, int $limit): array
    {
        $contractId = (int) $contract['id'];
        $costs = Model::fetchAll(
            "SELECT k.*, lc.legal_referred_at FROM legal_case_costs k
             LEFT JOIN legal_cases lc ON lc.id = k.legal_case_id
             WHERE k.contract_id = ? AND k.approval_status IN ('approved','reversed') AND k.chargeable_to_customer = 1
             ORDER BY k.id LIMIT {$limit}",
            [$contractId]
        );
        $findings = [];
        $costIds = array_map(static function ($cost) { return (int) $cost['id']; }, $costs);
        $eventsByCost = [];
        if ($costIds) {
            $placeholders = implode(',', array_fill(0, count($costIds), '?'));
            foreach (Model::fetchAll("SELECT * FROM financial_ledger_events WHERE source_type = 'legal_cost' AND source_id IN ({$placeholders}) ORDER BY id", $costIds) as $event) {
                $eventsByCost[(int) $event['source_id']][] = $event;
            }
        }
        foreach ($costs as $cost) {
            $costId = (int) $cost['id'];
            $eventRows = $eventsByCost[$costId] ?? [];
            $referred = !empty($cost['legal_case_id']) && !empty($cost['legal_referred_at']);
            if (($cost['approval_status'] ?? '') === 'approved' && $referred && !$eventRows) {
                $when = (string) ($cost['approved_at'] ?? $cost['created_at'] ?? '');
                $legacy = !$firstLedgerAt || ($when !== '' && strcmp($when, (string) $firstLedgerAt) < 0);
                $findings[] = self::finding('R08', 'legal_cost_without_core_ledger_event', 'legal_cost', $contract, [
                    'payment_id' => null, 'severity' => $legacy ? 'info' : 'review',
                    'classification' => $legacy ? 'historical_coverage_gap' : 'insufficient_evidence',
                    'coverage_status' => $legacy ? 'pre_first_observed_event' : 'coverage_not_proven_for_source',
                ], ['legal_cost_id' => $costId, 'amount_toman' => (int) $cost['amount_toman'], 'approved_at' => $cost['approved_at'] ?? null, 'legal_referred_at' => $cost['legal_referred_at'], 'first_observed_ledger_event_at' => $firstLedgerAt]);
            }
            foreach ($eventRows as $event) {
                if (($event['event_type'] ?? '') !== 'charge') continue;
                $eventId = (int) $event['id'];
                if ((string) ($event['idempotency_key'] ?? '') !== 'legal-cost:' . $costId) {
                    $findings[] = self::finding('R06', 'legal_cost_ledger_idempotency_mismatch', 'ledger_event', $contract, [
                        'ledger_event_id' => $eventId, 'severity' => 'high', 'classification' => 'verified_mismatch',
                        'coverage_status' => 'approved_cost_direct_source_link',
                    ], ['legal_cost_id' => $costId, 'event_id' => $eventId, 'expected_idempotency_key' => 'legal-cost:' . $costId, 'actual_idempotency_key' => (string) ($event['idempotency_key'] ?? '')]);
                }
                if ((int) $event['contract_id'] !== $contractId || (int) $event['customer_id'] !== (int) $contract['customer_id']) {
                    $findings[] = self::finding('R06', 'legal_cost_ledger_header_mismatch', 'ledger_event', $contract, [
                        'ledger_event_id' => $eventId, 'severity' => 'critical', 'classification' => 'verified_mismatch',
                        'coverage_status' => 'approved_cost_direct_source_link',
                    ], ['legal_cost_id' => $costId, 'event_id' => $eventId, 'ledger_contract_id' => (int) $event['contract_id'], 'ledger_customer_id' => (int) $event['customer_id']]);
                }
                $totals = self::sumLines(self::linesForEvent((int) $event['id']));
                $actual = (int) ($totals['legal_cost'] ?? 0);
                // The original charge remains immutable even when a separate
                // reversal event later offsets it.
                $expected = (int) ($cost['amount_toman'] ?? 0);
                if ($actual !== $expected) {
                    $findings[] = self::finding('R06', 'legal_cost_ledger_charge_mismatch', 'legal_cost', $contract, [
                        'ledger_event_id' => (int) $event['id'], 'severity' => 'critical', 'classification' => 'verified_mismatch',
                        'coverage_status' => 'approved_referred_cost_and_event_linked', 'expected_toman' => $expected,
                        'actual_toman' => $actual, 'difference_toman' => $expected - $actual,
                    ], ['legal_cost_id' => $costId, 'event_id' => (int) $event['id'], 'expected_legal_cost_delta_toman' => $expected, 'actual_legal_cost_delta_toman' => $actual]);
                }
            }
            $chargeEventCount = count(array_filter($eventRows, static function ($event) { return ($event['event_type'] ?? '') === 'charge'; }));
            if ($chargeEventCount > 1) {
                $findings[] = self::finding('R06', 'duplicate_legal_cost_ledger_charge', 'legal_cost', $contract, [
                    'severity' => 'critical', 'classification' => 'verified_mismatch', 'coverage_status' => 'duplicate_direct_source_events',
                    'expected_toman' => 1, 'actual_toman' => $chargeEventCount, 'difference_toman' => 1 - $chargeEventCount,
                ], ['legal_cost_id' => $costId, 'charge_event_ids' => array_values(array_map(static function ($event) { return (int) $event['id']; }, array_filter($eventRows, static function ($event) { return ($event['event_type'] ?? '') === 'charge'; })))]);
            }
            $paidAllocations = Model::fetch(
                "SELECT COALESCE(SUM(a.allocated_amount_toman),0) AS active_paid_toman
                   FROM legal_cost_payment_allocations a
                   JOIN payments p ON p.id = a.payment_id
                  WHERE a.legal_case_cost_id = ? AND COALESCE(a.is_reversal,0)=0
                    AND p.status='paid' AND COALESCE(p.is_corrected,0)=0",
                [$costId]
            );
            $activePaid = (int) ($paidAllocations['active_paid_toman'] ?? 0);
            $storedPaid = (int) ($cost['paid_amount_toman'] ?? 0);
            if ($activePaid !== $storedPaid) {
                $findings[] = self::finding('R06', 'legal_cost_paid_state_mismatch', 'legal_cost', $contract, [
                    'severity' => 'high', 'classification' => 'verified_mismatch', 'coverage_status' => 'active_payment_allocations_available',
                    'expected_toman' => $activePaid, 'actual_toman' => $storedPaid, 'difference_toman' => $activePaid - $storedPaid,
                ], ['legal_cost_id' => $costId, 'active_paid_allocation_toman' => $activePaid, 'stored_paid_amount_toman' => $storedPaid, 'status' => $cost['payment_status'] ?? null]);
            }
            if ($activePaid > (int) ($cost['amount_toman'] ?? 0)) {
                $findings[] = self::finding('R06', 'legal_cost_overallocated', 'legal_cost', $contract, [
                    'severity' => 'critical', 'classification' => 'verified_mismatch', 'coverage_status' => 'active_payment_allocations_available',
                    'expected_toman' => (int) $cost['amount_toman'], 'actual_toman' => $activePaid, 'difference_toman' => (int) $cost['amount_toman'] - $activePaid,
                ], ['legal_cost_id' => $costId, 'approved_charge_toman' => (int) $cost['amount_toman'], 'active_paid_allocation_toman' => $activePaid]);
            }
        }
        return $findings;
    }

    private static function expectedPaymentLines(array $payment, array $allocations, array $legalAllocations): array
    {
        $totals = [];
        $type = (string) ($payment['payment_type'] ?? 'installment');
        if ($type === 'down_payment') {
            $totals['down_payment'] = -normalize_money($payment['amount'] ?? 0);
        } elseif ($type === 'legal_cost') {
            foreach ($legalAllocations as $allocation) {
                if ((int) ($allocation['is_reversal'] ?? 0) !== 0) continue;
                $totals['legal_cost'] = ($totals['legal_cost'] ?? 0) - (int) ($allocation['allocated_amount_toman'] ?? 0);
            }
        } else {
            foreach ($allocations as $allocation) {
                if ((int) ($allocation['is_reversal'] ?? 0) !== 0) continue;
                foreach (['principal_applied' => 'principal', 'normal_penalty_applied' => 'normal_late_fee', 'legal_penalty_applied' => 'legal_late_fee'] as $field => $component) {
                    $amount = normalize_money($allocation[$field] ?? 0);
                    if ($amount !== 0) $totals[$component] = ($totals[$component] ?? 0) - $amount;
                }
            }
        }
        ksort($totals);
        return $totals;
    }

    private static function sumLines(array $lines): array
    {
        $totals = [];
        foreach ($lines as $line) {
            $component = (string) ($line['component'] ?? '');
            if ($component === '') continue;
            $totals[$component] = ($totals[$component] ?? 0) + (int) ($line['delta_toman'] ?? 0);
        }
        ksort($totals);
        return $totals;
    }

    private static function linesForEvent(int $eventId): array
    {
        return Model::fetchAll('SELECT component, delta_toman FROM financial_ledger_lines WHERE event_id = ? ORDER BY id', [$eventId]);
    }

    private static function entityForIssue(array $issue): string
    {
        if (!empty($issue['installment_id']) || (isset($issue['id']) && isset($issue['base_amount']))) return 'installment';
        if (!empty($issue['payment_id'])) return 'payment';
        if (!empty($issue['id']) && isset($issue['requested_amount'])) return 'payment_group';
        if (!empty($issue['id']) && isset($issue['allocated_amount'])) return 'allocation';
        return 'contract';
    }

    private static function finding(string $ruleId, string $kind, string $entityType, array $contract, array $data, array $evidence): array
    {
        $entityId = (int) ($data['installment_id'] ?? $data['payment_id'] ?? $data['payment_group_id'] ?? $data['ledger_event_id'] ?? $data['allocation_id'] ?? $data['id'] ?? $contract['id']);
        if ($entityType === 'legal_cost') $entityId = (int) ($evidence['legal_cost_id'] ?? $entityId);
        $stableKey = hash('sha256', implode('|', [$ruleId, $kind, $entityType, (string) $entityId]));
        $evidence = self::safeEvidence($evidence);
        $evidenceHash = hash('sha256', json_encode($evidence, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION) ?: '{}');
        return [
            'stable_key' => $stableKey,
            'rule_id' => $ruleId,
            'rule_version' => self::RULE_VERSION,
            'issue_type' => $kind,
            'classification' => (string) ($data['classification'] ?? 'verified_mismatch'),
            'coverage_status' => (string) ($data['coverage_status'] ?? 'source_records_compared'),
            'severity' => (string) ($data['severity'] ?? 'high'),
            'contract_id' => (int) ($data['contract_id'] ?? $contract['id']),
            'customer_id' => (int) ($data['customer_id'] ?? $contract['customer_id']),
            'payment_id' => !empty($data['payment_id']) ? (int) $data['payment_id'] : null,
            'payment_group_id' => !empty($data['payment_group_id']) ? (int) $data['payment_group_id'] : ($entityType === 'payment_group' ? $entityId : null),
            'allocation_id' => !empty($data['allocation_id']) ? (int) $data['allocation_id'] : ($entityType === 'allocation' ? $entityId : null),
            'installment_id' => !empty($data['installment_id']) ? (int) $data['installment_id'] : ($entityType === 'installment' ? $entityId : null),
            'ledger_event_id' => !empty($data['ledger_event_id']) ? (int) $data['ledger_event_id'] : null,
            'expected_toman' => array_key_exists('expected_toman', $data) ? $data['expected_toman'] : null,
            'actual_toman' => array_key_exists('actual_toman', $data) ? $data['actual_toman'] : null,
            'difference_toman' => array_key_exists('difference_toman', $data) ? $data['difference_toman'] : null,
            'source_fingerprint' => $data['source_fingerprint'] ?? null,
            'evidence' => $evidence,
            'evidence_hash' => $evidenceHash,
            'contract_number' => (string) ($contract['contract_number'] ?? ''),
            'customer_name' => '',
        ];
    }

    private static function safeEvidence(array $evidence): array
    {
        unset($evidence['gateway_track_id'], $evidence['gateway_ref_id'], $evidence['receipt_path'], $evidence['attachment_path'], $evidence['user_agent'], $evidence['ip_address']);
        return $evidence;
    }

    private static function dedupe(array $findings): array
    {
        $unique = [];
        foreach ($findings as $finding) $unique[$finding['stable_key']] = $finding;
        return array_values($unique);
    }
}
