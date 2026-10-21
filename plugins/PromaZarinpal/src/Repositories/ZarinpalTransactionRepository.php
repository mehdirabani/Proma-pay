<?php

namespace Proma\Plugins\Zarinpal\Repositories;

class ZarinpalTransactionRepository
{
    public function create(array $data)
    {
        \Model::execute(
            'INSERT INTO proma_zarinpal_transactions
             (uuid, local_order_id, idempotency_key, customer_id, contract_id, installment_id, payment_group_id, payment_id,
              environment, merchant_fingerprint, merchant_snapshot_encrypted, internal_amount_toman, gateway_amount,
              gateway_currency, description, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())',
            [
                $data['uuid'], $data['local_order_id'], $data['idempotency_key'], (int) $data['customer_id'],
                (int) $data['contract_id'], $data['installment_id'] ?: null, $data['payment_group_id'] ?: null,
                $data['payment_id'] ?: null, $data['environment'], $data['merchant_fingerprint'],
                $data['merchant_snapshot_encrypted'], $data['internal_amount_toman'], (int) $data['gateway_amount'],
                $data['gateway_currency'], $data['description'], $data['status'] ?? 'created',
            ]
        );
        return $this->find((int) \Model::lastInsertId());
    }

    public function find($id)
    {
        return \Model::fetch(
            'SELECT t.*, c.contract_number, i.installment_number
             FROM proma_zarinpal_transactions t
             JOIN contracts c ON c.id = t.contract_id
             LEFT JOIN installments i ON i.id = t.installment_id
             WHERE t.id = ? LIMIT 1',
            [(int) $id]
        );
    }

    public function lock($id)
    {
        return \Model::fetch('SELECT * FROM proma_zarinpal_transactions WHERE id = ? LIMIT 1 FOR UPDATE', [(int) $id]);
    }

    public function findByAuthority($authority)
    {
        $authority = trim((string) $authority);
        $environment = strtoupper(substr($authority, 0, 1)) === 'S' ? 'sandbox' : 'production';
        return \Model::fetch(
            'SELECT t.*, c.contract_number, i.installment_number
             FROM proma_zarinpal_transactions t
             JOIN contracts c ON c.id = t.contract_id
             LEFT JOIN installments i ON i.id = t.installment_id
             WHERE t.environment = ? AND t.authority = ? ORDER BY t.id DESC LIMIT 1',
            [$environment, $authority]
        );
    }

    public function findByIdempotency($key)
    {
        return \Model::fetch('SELECT * FROM proma_zarinpal_transactions WHERE idempotency_key = ? LIMIT 1', [trim((string) $key)]);
    }

    public function update($id, array $values)
    {
        $allowed = [
            'authority', 'ref_id', 'card_pan_masked', 'card_hash', 'fee', 'fee_type', 'status', 'callback_status',
            'request_code', 'request_message', 'verify_code', 'verify_message', 'error_code', 'recovery_status',
            'retry_count', 'requested_at', 'redirected_at', 'callback_at', 'verifying_at', 'verified_at', 'failed_at',
            'payment_id', 'payment_group_id',
        ];
        $sets = [];
        $params = [];
        foreach ($values as $key => $value) {
            if (!in_array($key, $allowed, true)) {
                continue;
            }
            $sets[] = $key . ' = ?';
            $params[] = $value;
        }
        if (!$sets) {
            return 0;
        }
        $sets[] = 'updated_at = NOW()';
        $params[] = (int) $id;
        return \Model::execute('UPDATE proma_zarinpal_transactions SET ' . implode(', ', $sets) . ' WHERE id = ?', $params);
    }

    public function paginate(array $filters = [])
    {
        $where = [];
        $params = [];
        if (($filters['status'] ?? '') === 'needs_review') {
            $where[] = "t.status IN ('request_uncertain','callback_received','verification_failed','verifying','manual_review')";
        } elseif (!empty($filters['status'])) {
            $where[] = 't.status = ?';
            $params[] = trim((string) $filters['status']);
        }
        if (!empty($filters['environment'])) {
            $where[] = 't.environment = ?';
            $params[] = trim((string) $filters['environment']);
        }
        if (!empty($filters['date_from'])) {
            $where[] = 'DATE(t.created_at) >= ?';
            $params[] = (string) $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'DATE(t.created_at) <= ?';
            $params[] = (string) $filters['date_to'];
        }
        if (!empty($filters['amount_min'])) {
            $where[] = 't.internal_amount_toman >= ?';
            $params[] = (int) $filters['amount_min'];
        }
        if (!empty($filters['amount_max'])) {
            $where[] = 't.internal_amount_toman <= ?';
            $params[] = (int) $filters['amount_max'];
        }
        if (!empty($filters['q'])) {
            $needle = '%' . trim((string) $filters['q']) . '%';
            $where[] = '(t.local_order_id LIKE ? OR t.authority LIKE ? OR t.ref_id LIKE ? OR c.contract_number LIKE ? OR u.full_name LIKE ?)';
            array_push($params, $needle, $needle, $needle, $needle, $needle);
        }
        $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        $count = \Model::fetch(
            'SELECT COUNT(*) AS total FROM proma_zarinpal_transactions t JOIN contracts c ON c.id = t.contract_id JOIN users u ON u.id = t.customer_id' . $whereSql,
            $params
        );
        $total = (int) ($count['total'] ?? 0);
        $perPage = max(10, min(100, (int) ($filters['per_page'] ?? 25)));
        $page = max(1, (int) ($filters['page'] ?? 1));
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);
        $offset = ($page - 1) * $perPage;
        $items = \Model::fetchAll(
            'SELECT t.*, c.contract_number, u.full_name AS customer_name, i.installment_number
             FROM proma_zarinpal_transactions t
             JOIN contracts c ON c.id = t.contract_id
             JOIN users u ON u.id = t.customer_id'
            . ' LEFT JOIN installments i ON i.id = t.installment_id'
            . $whereSql . " ORDER BY t.id DESC LIMIT {$perPage} OFFSET {$offset}",
            $params
        );
        return ['items' => $items, 'total' => $total, 'page' => $page, 'pages' => $pages, 'per_page' => $perPage];
    }

    public function unresolvedCount()
    {
        $row = \Model::fetch("SELECT COUNT(*) AS total FROM proma_zarinpal_transactions WHERE status IN ('created','request_uncertain','pending','callback_received','verifying','manual_review')");
        return (int) ($row['total'] ?? 0);
    }

    public function exportRows()
    {
        return \Model::fetchAll(
            'SELECT t.local_order_id, u.full_name AS customer_name, c.contract_number, t.internal_amount_toman,
                    t.gateway_currency, t.environment, t.status, t.authority, t.ref_id, t.fee, t.created_at, t.verified_at
             FROM proma_zarinpal_transactions t
             JOIN users u ON u.id = t.customer_id
             JOIN contracts c ON c.id = t.contract_id
             ORDER BY t.id DESC LIMIT 10000'
        );
    }
}
