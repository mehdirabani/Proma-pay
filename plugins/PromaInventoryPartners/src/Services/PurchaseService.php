<?php

namespace Proma\Plugins\InventoryPartners\Services;

use Proma\Plugins\InventoryPartners\Domain\Money;

final class PurchaseService
{
    public static function create(array $data, $actorId)
    {
        $productId = (int) ($data['product_id'] ?? 0);
        $warehouseId = (int) ($data['warehouse_id'] ?? 0);
        $supplierId = (int) ($data['supplier_id'] ?? 0);
        $quantity = Money::quantity($data['quantity'] ?? '');
        $unitPurchase = Money::fromToman($data['unit_purchase_toman'] ?? '0');
        $expenses = Money::fromToman($data['expenses_toman'] ?? '0');
        $idempotencyKey = trim((string) ($data['idempotency_key'] ?? ''));
        $purchaseDate = trim((string) ($data['purchase_date'] ?? date('Y-m-d')));
        if ($productId <= 0 || $warehouseId <= 0 || !$quantity || $quantity < 1 || $quantity > 1000000 || $unitPurchase <= 0
            || $idempotencyKey === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $purchaseDate)) {
            throw new \InvalidArgumentException('مشخصات خرید، تعداد و مبلغ معتبر نیست.');
        }
        $capitalShares = OwnershipService::resolve($data, 'capital_shares');
        $profitShares = OwnershipService::resolve($data, 'profit_shares');
        $serials = self::serials($data['serial_numbers'] ?? '');
        $hash = hash('sha256', json_encode([
            'product_id' => $productId, 'warehouse_id' => $warehouseId, 'supplier_id' => $supplierId,
            'quantity' => $quantity, 'unit_purchase_rial' => $unitPurchase, 'expenses_rial' => $expenses,
            'purchase_date' => $purchaseDate, 'capital_shares' => $capitalShares, 'profit_shares' => $profitShares, 'serials' => $serials,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        \Model::begin();
        try {
            $existing = \Model::fetch('SELECT id, request_hash FROM plugin_inventory_purchase_headers WHERE idempotency_key = ? LIMIT 1 FOR UPDATE', [$idempotencyKey]);
            if ($existing) {
                if (!hash_equals((string) $existing['request_hash'], $hash)) {
                    throw new \InvalidArgumentException('کلید تکرار خرید با اطلاعات متفاوت استفاده شده است.');
                }
                \Model::commit();
                return (int) $existing['id'];
            }
            $product = \Model::fetch("SELECT * FROM plugin_inventory_products WHERE id = ? AND status = 'active' LIMIT 1 FOR UPDATE", [$productId]);
            $warehouse = \Model::fetch("SELECT id FROM plugin_inventory_warehouses WHERE id = ? AND status = 'active' LIMIT 1 FOR UPDATE", [$warehouseId]);
            if (!$product || !$warehouse) {
                throw new \InvalidArgumentException('کالای فعال یا انبار فعال پیدا نشد.');
            }
            if ($supplierId > 0 && !\Model::fetch("SELECT id FROM plugin_inventory_suppliers WHERE id = ? AND status = 'active' LIMIT 1 FOR UPDATE", [$supplierId])) {
                throw new \InvalidArgumentException('تأمین‌کننده فعال پیدا نشد.');
            }
            if (($product['tracking_mode'] ?? '') === 'serial' && count($serials) !== $quantity) {
                throw new \InvalidArgumentException('برای کالای سریالی باید IMEI/Serial هر واحد جداگانه ثبت شود.');
            }
            if (($product['tracking_mode'] ?? '') !== 'serial' && $serials) {
                throw new \InvalidArgumentException('برای کالای تعدادی شناسه سریال ارسال نکنید.');
            }

            $unitExpense = intdiv($expenses, $quantity);
            $remainder = $expenses % $quantity;
            $unitLanded = Money::add($unitPurchase, $unitExpense);
            $total = Money::add(Money::multiply($unitPurchase, $quantity), $expenses);
            $purchaseNo = self::number('PUR');
            \Model::execute(
                'INSERT INTO plugin_inventory_purchase_headers (purchase_no, supplier_id, warehouse_id, invoice_no, purchase_date, expenses_rial, total_rial, request_hash, idempotency_key, status, note, created_by, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, \'posted\', ?, ?, NOW())',
                [$purchaseNo, $supplierId ?: null, $warehouseId, trim((string) ($data['invoice_no'] ?? '')) ?: null, $purchaseDate, Money::db($expenses), Money::db($total), $hash, $idempotencyKey, trim((string) ($data['note'] ?? '')) ?: null, $actorId ? (int) $actorId : null]
            );
            $purchaseId = (int) \Model::lastInsertId();
            $lotNo = self::number('LOT');
            \Model::execute(
                'INSERT INTO plugin_inventory_stock_lots (lot_no, product_id, purchase_id, supplier_id, warehouse_id, entered_at, initial_qty, on_hand_qty, reserved_qty, unit_purchase_cost_rial, unit_expense_rial, unit_landed_cost_rial, cost_remainder_rial, status, note, created_by, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?, \'active\', ?, ?, NOW())',
                [$lotNo, $productId, $purchaseId, $supplierId ?: null, $warehouseId, $purchaseDate . ' 00:00:00', $quantity, $quantity, Money::db($unitPurchase), Money::db($unitExpense), Money::db($unitLanded), Money::db($remainder), trim((string) ($data['note'] ?? '')) ?: null, $actorId ? (int) $actorId : null]
            );
            $lotId = (int) \Model::lastInsertId();
            \Model::execute('INSERT INTO plugin_inventory_purchase_lines (purchase_id, lot_id, product_id, quantity, unit_purchase_cost_rial, allocated_expense_rial, line_total_rial) VALUES (?, ?, ?, ?, ?, ?, ?)', [
                $purchaseId, $lotId, $productId, $quantity, Money::db($unitPurchase), Money::db($expenses), Money::db($total),
            ]);
            self::saveLotShares($lotId, $total, $capitalShares, $profitShares);
            foreach ($serials as $serial) {
                \Model::execute('INSERT INTO plugin_inventory_serial_items (product_id, lot_id, warehouse_id, serial_no, status, received_at) VALUES (?, ?, ?, ?, \'in_stock\', NOW())', [$productId, $lotId, $warehouseId, $serial]);
            }
            self::movement('purchase_in', $productId, $lotId, null, $warehouseId, $quantity, $unitLanded, 'purchase', $purchaseId, trim((string) ($data['note'] ?? '')), $actorId);
            $journalLines = [['account_code' => 'inventory_asset', 'debit_rial' => $total, 'credit_rial' => 0, 'memo' => 'ورود کالا ' . $purchaseNo]];
            foreach (OwnershipService::allocationRows($total, $capitalShares) as $allocation) {
                if ($allocation['amount_rial'] > 0) {
                    $journalLines[] = ['account_code' => 'partner_capital', 'partner_id' => $allocation['partner_id'], 'debit_rial' => 0, 'credit_rial' => $allocation['amount_rial'], 'memo' => 'سرمایه تخصیص‌یافته به محموله'];
                }
            }
            $journalId = LedgerService::post('purchase:' . $purchaseId, 'inventory_purchase', $purchaseId, 'ثبت خرید ' . $purchaseNo, $actorId, $journalLines);
            AuditService::record('purchase_posted', 'purchase', $purchaseId, $actorId, [], ['lot_id' => $lotId, 'quantity' => $quantity, 'total_rial' => Money::db($total), 'journal_id' => $journalId]);
            \Model::commit();
            return $purchaseId;
        } catch (\Throwable $error) {
            \Model::rollBack();
            throw $error;
        }
    }

    private static function saveLotShares($lotId, $total, array $capitalShares, array $profitShares)
    {
        $capitalAmounts = OwnershipService::allocationRows($total, $capitalShares);
        $profitByPartner = [];
        foreach ($profitShares as $share) {
            $profitByPartner[(int) $share['partner_id']] = (int) $share['weight_bp'];
        }
        $capitalByPartner = [];
        foreach ($capitalShares as $capital) {
            $capitalByPartner[(int) $capital['partner_id']] = $capital;
        }
        $partnerIds = array_values(array_unique(array_merge(array_keys($capitalByPartner), array_keys($profitByPartner))));
        sort($partnerIds, SORT_NUMERIC);
        foreach ($partnerIds as $partnerId) {
            $capital = $capitalByPartner[$partnerId] ?? ['weight_bp' => 0];
            $capitalAmount = $capitalAmounts[$partnerId]['amount_rial'] ?? 0;
            \Model::execute(
                'INSERT INTO plugin_inventory_lot_partner_shares (lot_id, partner_id, capital_share_bp, profit_share_bp, initial_capital_rial, remaining_capital_rial) VALUES (?, ?, ?, ?, ?, ?)',
                [$lotId, $partnerId, (int) $capital['weight_bp'], (int) ($profitByPartner[$partnerId] ?? 0), Money::db($capitalAmount), Money::db($capitalAmount)]
            );
        }
    }

    private static function serials($raw)
    {
        if (is_array($raw)) {
            $items = $raw;
        } else {
            $items = preg_split('/\r\n|\r|\n|,|،/', (string) $raw) ?: [];
        }
        $serials = [];
        foreach ($items as $item) {
            $item = trim((string) $item);
            if ($item === '') { continue; }
            if (strlen($item) > 120 || preg_match('/[\x00-\x1F]/', $item) || isset($serials[$item])) {
                throw new \InvalidArgumentException('IMEI/Serial تکراری یا نامعتبر است.');
            }
            $serials[$item] = $item;
        }
        return array_values($serials);
    }

    public static function movement($type, $productId, $lotId, $fromWarehouseId, $toWarehouseId, $quantity, $unitCost, $referenceType, $referenceId, $reason, $actorId)
    {
        \Model::execute(
            'INSERT INTO plugin_inventory_stock_movements (movement_uuid, movement_type, product_id, lot_id, from_warehouse_id, to_warehouse_id, quantity, unit_cost_rial, reference_type, reference_id, reason, actor_user_id, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())',
            [bin2hex(random_bytes(16)), $type, (int) $productId, (int) $lotId, $fromWarehouseId ? (int) $fromWarehouseId : null, $toWarehouseId ? (int) $toWarehouseId : null, (int) $quantity, Money::db($unitCost), $referenceType, $referenceId ? (int) $referenceId : null, trim((string) $reason) ?: null, $actorId ? (int) $actorId : null]
        );
    }

    private static function number($prefix)
    {
        return strtoupper($prefix) . '-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(3)));
    }
}
