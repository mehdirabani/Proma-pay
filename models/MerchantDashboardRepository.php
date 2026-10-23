<?php

declare(strict_types=1);

/** Read-only, membership-scoped merchant operations dashboard. */
final class MerchantDashboardRepository
{
    public static function membershipsForUser(int $userId): array
    {
        if ($userId < 1) {
            return [];
        }

        return Model::fetchAll(
            "SELECT m.id AS merchant_id, m.merchant_uuid, m.display_name, m.status AS merchant_status,
                    mm.membership_role
             FROM merchant_memberships mm
             INNER JOIN merchant_accounts m ON m.id = mm.merchant_id
             WHERE mm.user_id = ? AND mm.status = 'active' AND m.status = 'active'
             ORDER BY m.display_name ASC, m.id ASC",
            [$userId]
        );
    }

    public static function dashboardForUser(int $userId, int $merchantId): array
    {
        if ($userId < 1 || $merchantId < 1) {
            throw new DomainException('merchant_dashboard_forbidden');
        }

        $merchant = Model::fetch(
            "SELECT m.id AS merchant_id, m.merchant_uuid, m.display_name, mm.membership_role
             FROM merchant_memberships mm
             INNER JOIN merchant_accounts m ON m.id = mm.merchant_id
             WHERE mm.user_id = ? AND mm.merchant_id = ?
               AND mm.status = 'active' AND m.status = 'active'
             LIMIT 1",
            [$userId, $merchantId]
        );
        if (!$merchant) {
            throw new DomainException('merchant_dashboard_forbidden');
        }

        $canSettlement = MerchantMembershipPolicy::allows((string) $merchant['membership_role'], 'settlement.read');
        $orderRows = Model::fetchAll(
            'SELECT status, COUNT(*) AS total
             FROM merchant_api_order_requests WHERE merchant_id = ? GROUP BY status',
            [$merchantId]
        );
        $orders = ['pending_review' => 0, 'approved' => 0, 'rejected' => 0, 'cancelled' => 0, 'total' => 0];
        foreach ($orderRows as $row) {
            $status = (string) $row['status'];
            if (array_key_exists($status, $orders) && $status !== 'total') {
                $orders[$status] = (int) $row['total'];
                $orders['total'] += (int) $row['total'];
            }
        }

        $contractRow = Model::fetch(
            "SELECT COUNT(*) AS total
             FROM merchant_contract_links l
             INNER JOIN contracts c ON c.id = l.contract_id
             WHERE l.merchant_id = ? AND l.order_status = 'contracted' AND c.status = 'active'",
            [$merchantId]
        ) ?: ['total' => 0];

        $guaranteeStatusRows = Model::fetchAll(
            'SELECT sayad_status, physical_status, COUNT(*) AS total
             FROM merchant_cheque_guarantees WHERE merchant_id = ?
             GROUP BY sayad_status, physical_status ORDER BY sayad_status, physical_status',
            [$merchantId]
        );
        $guaranteeTotal = 0;
        foreach ($guaranteeStatusRows as $row) {
            $guaranteeTotal += (int) $row['total'];
        }
        $guaranteeAmountColumns = $canSettlement
            ? 'scheduled_installments_toman, cheque_amount_rial, '
            : '0 AS scheduled_installments_toman, 0 AS cheque_amount_rial, ';
        $guarantees = Model::fetchAll(
            'SELECT guarantee_uuid, guarantee_type, ' . $guaranteeAmountColumns . '
                    first_installment_due_date, cheque_due_date, sayad_status, physical_status
             FROM merchant_cheque_guarantees WHERE merchant_id = ?
             ORDER BY created_at DESC, id DESC LIMIT 25',
            [$merchantId]
        );

        $settlementRows = $canSettlement
            ? Model::fetchAll(
                'SELECT status, COUNT(*) AS total, SUM(gross_receivable_toman) AS gross_receivable_toman,
                        SUM(net_settlement_toman) AS net_settlement_toman
                 FROM merchant_settlement_obligations WHERE merchant_id = ? GROUP BY status ORDER BY status',
                [$merchantId]
            )
            : Model::fetchAll(
                'SELECT status, COUNT(*) AS total
                 FROM merchant_settlement_obligations WHERE merchant_id = ? GROUP BY status ORDER BY status',
                [$merchantId]
            );
        $settlements = ['rows' => $settlementRows, 'items' => []];
        if ($canSettlement) {
            $settlements['items'] = Model::fetchAll(
                'SELECT obligation_uuid, external_order_id, status, settlement_due_date,
                        gross_receivable_toman, net_settlement_toman, funding_account_id
                 FROM merchant_settlement_obligations WHERE merchant_id = ?
                 ORDER BY COALESCE(settlement_due_date, DATE(created_at)) DESC, id DESC LIMIT 25',
                [$merchantId]
            );
        }

        $reconciliationRows = Model::fetchAll(
            'SELECT reconciliation_status, COUNT(*) AS total
             FROM merchant_financial_reconciliations WHERE merchant_id = ?
             GROUP BY reconciliation_status ORDER BY reconciliation_status',
            [$merchantId]
        );
        $webhookRows = Model::fetchAll(
            'SELECT delivery_status, COUNT(*) AS total
             FROM merchant_api_webhook_outbox WHERE merchant_id = ?
             GROUP BY delivery_status ORDER BY delivery_status',
            [$merchantId]
        );
        $webhookErrors = Model::fetchAll(
            "SELECT event_type, delivery_status, attempt_count, last_http_status, last_error_code, updated_at
             FROM merchant_api_webhook_outbox
             WHERE merchant_id = ? AND (delivery_status = 'dead_letter' OR last_error_code IS NOT NULL)
             ORDER BY updated_at DESC, id DESC LIMIT 25",
            [$merchantId]
        );
        $apiEvents = Model::fetchAll(
            "SELECT event_type, subject_type, created_at
             FROM merchant_audit_events WHERE merchant_id = ? AND event_type LIKE 'api.%'
             ORDER BY created_at DESC, id DESC LIMIT 25",
            [$merchantId]
        );

        return [
            'merchant' => $merchant,
            'can_settlement' => $canSettlement,
            'orders' => $orders,
            'active_contracts' => (int) ($contractRow['total'] ?? 0),
            'guarantee_status_rows' => $guaranteeStatusRows,
            'guarantee_total' => $guaranteeTotal,
            'guarantees' => $guarantees,
            'settlements' => $settlements,
            'reconciliations' => $reconciliationRows,
            'webhooks' => $webhookRows,
            'webhook_errors' => $webhookErrors,
            'api_events' => $apiEvents,
        ];
    }
}
