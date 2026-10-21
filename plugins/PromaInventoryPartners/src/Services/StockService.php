<?php

namespace Proma\Plugins\InventoryPartners\Services;

use Proma\Plugins\InventoryPartners\Domain\Money;

final class StockService
{
    public static function lots($includeCosts = false, $warehouseId = 0)
    {
        $costFields = $includeCosts
            ? ', l.unit_purchase_cost_rial, l.unit_expense_rial, l.unit_landed_cost_rial, l.cost_remainder_rial'
            : '';
        $where = $warehouseId > 0 ? ' WHERE l.warehouse_id = ?' : '';
        $params = $warehouseId > 0 ? [(int) $warehouseId] : [];
        return \Model::fetchAll(
            'SELECT l.id, l.lot_no, l.product_id, l.purchase_id, l.supplier_id, l.warehouse_id, l.entered_at, l.initial_qty, l.on_hand_qty, l.reserved_qty, (l.on_hand_qty - l.reserved_qty) AS available_qty, l.status, p.sku, p.name AS product_name, p.tracking_mode, w.name AS warehouse_name, s.name AS supplier_name' . $costFields . '
             FROM plugin_inventory_stock_lots l
             JOIN plugin_inventory_products p ON p.id = l.product_id
             JOIN plugin_inventory_warehouses w ON w.id = l.warehouse_id
             LEFT JOIN plugin_inventory_suppliers s ON s.id = l.supplier_id' . $where . '
             ORDER BY l.entered_at DESC, l.id DESC LIMIT 500',
            $params
        );
    }

    public static function movements($limit = 100, $includeCosts = false)
    {
        $limit = max(1, min(500, (int) $limit));
        $unitCost = $includeCosts ? 'm.unit_cost_rial' : 'NULL AS unit_cost_rial';
        return \Model::fetchAll(
            'SELECT m.id, m.movement_uuid, m.movement_type, m.product_id, m.lot_id, m.from_warehouse_id, m.to_warehouse_id, m.quantity, ' . $unitCost . ',
                    m.reference_type, m.reference_id, m.reason, m.actor_user_id, m.created_at, p.sku, p.name AS product_name, w1.name AS from_warehouse, w2.name AS to_warehouse
             FROM plugin_inventory_stock_movements m JOIN plugin_inventory_products p ON p.id = m.product_id
             LEFT JOIN plugin_inventory_warehouses w1 ON w1.id = m.from_warehouse_id
             LEFT JOIN plugin_inventory_warehouses w2 ON w2.id = m.to_warehouse_id
             ORDER BY m.id DESC LIMIT ' . $limit
        );
    }

    public static function adjust(array $data, $actorId)
    {
        $productId = (int) ($data['product_id'] ?? 0);
        $lotId = (int) ($data['lot_id'] ?? 0);
        $warehouseId = (int) ($data['warehouse_id'] ?? 0);
        $quantity = Money::quantity($data['quantity'] ?? '');
        $direction = trim((string) ($data['direction'] ?? ''));
        $reason = trim((string) ($data['reason'] ?? ''));
        $idempotencyKey = trim((string) ($data['idempotency_key'] ?? ''));
        if ($productId <= 0 || $warehouseId <= 0 || !$quantity || $quantity < 1 || $quantity > 1000000
            || !in_array($direction, ['in', 'out'], true) || $reason === '' || $idempotencyKey === '') {
            throw new \InvalidArgumentException('مشخصات تعدیل موجودی معتبر نیست.');
        }
        $serials = self::serials($data['serial_numbers'] ?? '');
        $sharesInput = [];
        foreach (['capital_shares', 'profit_shares'] as $shareKey) {
            $sharesInput[$shareKey] = is_array($data[$shareKey] ?? null) ? $data[$shareKey] : [];
            ksort($sharesInput[$shareKey], SORT_NUMERIC);
        }
        $unitCost = $direction === 'in' ? Money::fromToman($data['unit_cost_toman'] ?? '0') : 0;
        if ($direction === 'in' && $unitCost <= 0) {
            throw new \InvalidArgumentException('بهای تعدیل ورودی باید بیشتر از صفر باشد.');
        }
        $hash = hash('sha256', json_encode([
            'product' => $productId, 'lot' => $lotId, 'warehouse' => $warehouseId,
            'quantity' => $quantity, 'direction' => $direction, 'unit_cost_rial' => $unitCost,
            'reason' => $reason, 'serials' => $serials, 'shares' => $sharesInput,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        \Model::begin();
        try {
            $existing = \Model::fetch('SELECT id, request_hash FROM plugin_inventory_stock_adjustments WHERE idempotency_key = ? LIMIT 1 FOR UPDATE', [$idempotencyKey]);
            if ($existing) {
                if (!hash_equals((string) $existing['request_hash'], $hash)) {
                    throw new \InvalidArgumentException('کلید تکرار تعدیل با اطلاعات متفاوت استفاده شده است.');
                }
                \Model::commit();
                return (int) $existing['id'];
            }
            $product = \Model::fetch("SELECT * FROM plugin_inventory_products WHERE id = ? AND status = 'active' LIMIT 1 FOR UPDATE", [$productId]);
            $warehouse = \Model::fetch("SELECT id FROM plugin_inventory_warehouses WHERE id = ? AND status = 'active' LIMIT 1 FOR UPDATE", [$warehouseId]);
            if (!$product || !$warehouse) { throw new \InvalidArgumentException('کالا یا انبار فعال پیدا نشد.'); }
            if (($product['tracking_mode'] ?? '') === 'serial' && count($serials) !== $quantity) {
                throw new \InvalidArgumentException('برای تعدیل کالای سریالی، IMEI/Serial هر واحد را مشخص کنید.');
            }
            if (($product['tracking_mode'] ?? '') !== 'serial' && $serials) {
                throw new \InvalidArgumentException('برای کالای تعدادی نیازی به IMEI/Serial نیست.');
            }
            if ($direction === 'in') {
                $capitalShares = OwnershipService::resolve($data, 'capital_shares');
                $profitShares = OwnershipService::resolve($data, 'profit_shares');
                $cost = Money::multiply($unitCost, $quantity);
                $lotNo = self::number('ADJ');
                \Model::execute(
                    'INSERT INTO plugin_inventory_stock_lots (lot_no, product_id, warehouse_id, entered_at, initial_qty, on_hand_qty, reserved_qty, unit_purchase_cost_rial, unit_expense_rial, unit_landed_cost_rial, cost_remainder_rial, status, note, created_by, created_at)
                     VALUES (?, ?, ?, NOW(), ?, ?, 0, ?, 0, ?, 0, \'active\', ?, ?, NOW())',
                    [$lotNo, $productId, $warehouseId, $quantity, $quantity, Money::db($unitCost), Money::db($unitCost), $reason, $actorId ? (int) $actorId : null]
                );
                $newLotId = (int) \Model::lastInsertId();
                self::saveShares($newLotId, $cost, $capitalShares, $profitShares);
                foreach ($serials as $serial) {
                    \Model::execute('INSERT INTO plugin_inventory_serial_items (product_id, lot_id, warehouse_id, serial_no, status, received_at) VALUES (?, ?, ?, ?, \'in_stock\', NOW())', [$productId, $newLotId, $warehouseId, $serial]);
                }
                PurchaseService::movement('adjustment_in', $productId, $newLotId, null, $warehouseId, $quantity, $unitCost, 'adjustment', $newLotId, $reason, $actorId);
                $lines = [['account_code' => 'inventory_asset', 'debit_rial' => $cost, 'credit_rial' => 0, 'memo' => 'تعدیل افزایشی موجودی']];
                foreach (OwnershipService::allocationRows($cost, $capitalShares) as $allocation) {
                    if ($allocation['amount_rial'] > 0) {
                        $lines[] = ['account_code' => 'partner_capital', 'partner_id' => $allocation['partner_id'], 'debit_rial' => 0, 'credit_rial' => $allocation['amount_rial'], 'memo' => 'سرمایه کالای تعدیلی'];
                    }
                }
                $journalId = LedgerService::post('stock-adjustment-in:' . $idempotencyKey, 'stock_adjustment', $newLotId, 'تعدیل افزایشی موجودی: ' . $reason, $actorId, $lines);
                $adjustmentId = self::recordAdjustment($idempotencyKey, $hash, $productId, $newLotId, $warehouseId, $direction, $quantity, $unitCost, $cost, $reason, $journalId, $actorId);
                AuditService::record('stock_adjustment_in', 'stock_adjustment', $adjustmentId, $actorId, [], ['lot_id' => $newLotId, 'quantity' => $quantity, 'cost_rial' => Money::db($cost), 'reason' => $reason]);
                \Model::commit();
                return $adjustmentId;
            }

            if ($lotId <= 0) { throw new \InvalidArgumentException('برای تعدیل کاهشی محموله را انتخاب کنید.'); }
            $lot = \Model::fetch('SELECT * FROM plugin_inventory_stock_lots WHERE id = ? AND product_id = ? AND warehouse_id = ? AND status = \'active\' LIMIT 1 FOR UPDATE', [$lotId, $productId, $warehouseId]);
            if (!$lot || $quantity > ((int) $lot['on_hand_qty'] - (int) $lot['reserved_qty'])) {
                throw new \InvalidArgumentException('موجودی آزاد برای تعدیل کاهشی کافی نیست.');
            }
            $serialRows = self::lockSerials($serials, $productId, $lotId, $warehouseId, 'in_stock');
            $unitCost = Money::integer($lot['unit_landed_cost_rial']);
            $remainder = min(Money::integer($lot['cost_remainder_rial']), $quantity);
            $cost = Money::add(Money::multiply($unitCost, $quantity), $remainder);
            $shares = \Model::fetchAll('SELECT partner_id, capital_share_bp, profit_share_bp, remaining_capital_rial FROM plugin_inventory_lot_partner_shares WHERE lot_id = ? ORDER BY partner_id ASC FOR UPDATE', [$lotId]);
            $caps = [];
            foreach ($shares as $share) {
                $caps[] = ['partner_id' => (int) $share['partner_id'], 'weight_bp' => (int) $share['capital_share_bp'], 'cap_rial' => Money::integer($share['remaining_capital_rial'])];
            }
            $capital = Money::allocateCapped($cost, $caps);
            \Model::execute('UPDATE plugin_inventory_stock_lots SET on_hand_qty = on_hand_qty - ?, cost_remainder_rial = cost_remainder_rial - ? WHERE id = ?', [$quantity, Money::db($remainder), $lotId]);
            $journalLines = [];
            foreach ($shares as $share) {
                $partnerId = (int) $share['partner_id'];
                $amount = (int) ($capital[$partnerId] ?? 0);
                if ($amount > 0) {
                    $remainingCapital = Money::integer($share['remaining_capital_rial']) - $amount;
                    \Model::execute('UPDATE plugin_inventory_lot_partner_shares SET remaining_capital_rial = ? WHERE lot_id = ? AND partner_id = ?', [Money::db($remainingCapital), $lotId, $partnerId]);
                    $journalLines[] = ['account_code' => 'partner_capital', 'partner_id' => $partnerId, 'debit_rial' => $amount, 'credit_rial' => 0, 'memo' => 'کاهش سرمایه به علت کسری یا آسیب'];
                    $journalLines[] = ['account_code' => 'inventory_asset', 'partner_id' => $partnerId, 'debit_rial' => 0, 'credit_rial' => $amount, 'memo' => 'خروج تعدیلی موجودی'];
                }
            }
            foreach ($serialRows as $serial) {
                \Model::execute("UPDATE plugin_inventory_serial_items SET status = 'damaged' WHERE id = ? AND status = 'in_stock'", [(int) $serial['id']]);
            }
            PurchaseService::movement('adjustment_out', $productId, $lotId, $warehouseId, null, $quantity, $unitCost, 'adjustment', $lotId, $reason, $actorId);
            $journalId = LedgerService::post('stock-adjustment-out:' . $idempotencyKey, 'stock_adjustment', $lotId, 'تعدیل کاهشی موجودی: ' . $reason, $actorId, $journalLines);
            $adjustmentId = self::recordAdjustment($idempotencyKey, $hash, $productId, $lotId, $warehouseId, $direction, $quantity, $unitCost, $cost, $reason, $journalId, $actorId);
            AuditService::record('stock_adjustment_out', 'stock_adjustment', $adjustmentId, $actorId, [], ['lot_id' => $lotId, 'quantity' => $quantity, 'cost_rial' => Money::db($cost), 'reason' => $reason]);
            \Model::commit();
            return $adjustmentId;
        } catch (\Throwable $error) {
            \Model::rollBack();
            throw $error;
        }
    }

    private static function recordAdjustment($key, $hash, $productId, $lotId, $warehouseId, $direction, $quantity, $unitCost, $totalCost, $reason, $journalId, $actorId)
    {
        \Model::execute(
            'INSERT INTO plugin_inventory_stock_adjustments (adjustment_no, idempotency_key, request_hash, product_id, lot_id, warehouse_id, direction, quantity, unit_cost_rial, total_cost_rial, reason, journal_id, status, actor_user_id, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \'posted\', ?, NOW())',
            [self::number('ADJ'), $key, $hash, (int) $productId, $lotId ? (int) $lotId : null, (int) $warehouseId, $direction, (int) $quantity, Money::db($unitCost), Money::db($totalCost), substr($reason, 0, 255), $journalId ? (int) $journalId : null, $actorId ? (int) $actorId : null]
        );
        return (int) \Model::lastInsertId();
    }

    private static function saveShares($lotId, $amount, array $capitalShares, array $profitShares)
    {
        $capital = OwnershipService::allocationRows($amount, $capitalShares);
        $profitMap = [];
        foreach ($profitShares as $share) { $profitMap[(int) $share['partner_id']] = (int) $share['weight_bp']; }
        $capitalMap = [];
        foreach ($capitalShares as $share) { $capitalMap[(int) $share['partner_id']] = $share; }
        $ids = array_values(array_unique(array_merge(array_keys($capitalMap), array_keys($profitMap))));
        sort($ids, SORT_NUMERIC);
        foreach ($ids as $partnerId) {
            $row = $capitalMap[$partnerId] ?? ['weight_bp' => 0];
            $value = $capital[$partnerId]['amount_rial'] ?? 0;
            \Model::execute('INSERT INTO plugin_inventory_lot_partner_shares (lot_id, partner_id, capital_share_bp, profit_share_bp, initial_capital_rial, remaining_capital_rial) VALUES (?, ?, ?, ?, ?, ?)', [
                $lotId, $partnerId, (int) $row['weight_bp'], (int) ($profitMap[$partnerId] ?? 0), Money::db($value), Money::db($value),
            ]);
        }
    }

    public static function createTransfer(array $data, $actorId)
    {
        $lotId = (int) ($data['lot_id'] ?? 0);
        $from = (int) ($data['from_warehouse_id'] ?? 0);
        $to = (int) ($data['to_warehouse_id'] ?? 0);
        $quantity = Money::quantity($data['quantity'] ?? '');
        $key = trim((string) ($data['idempotency_key'] ?? ''));
        $serials = self::serials($data['serial_numbers'] ?? '');
        if ($lotId <= 0 || $from <= 0 || $to <= 0 || $from === $to || !$quantity || $quantity < 1 || $key === '') {
            throw new \InvalidArgumentException('انبار مبدأ، مقصد، محموله و تعداد انتقال معتبر نیست.');
        }
        $hash = hash('sha256', json_encode(['lot' => $lotId, 'from' => $from, 'to' => $to, 'quantity' => $quantity, 'serials' => $serials], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        \Model::begin();
        try {
            $existing = \Model::fetch('SELECT id, request_hash FROM plugin_inventory_transfers WHERE idempotency_key = ? LIMIT 1 FOR UPDATE', [$key]);
            if ($existing) {
                if (!hash_equals((string) $existing['request_hash'], $hash)) { throw new \InvalidArgumentException('کلید تکرار انتقال با اطلاعات متفاوت استفاده شده است.'); }
                \Model::commit();
                return (int) $existing['id'];
            }
            $source = \Model::fetch('SELECT l.*, p.tracking_mode FROM plugin_inventory_stock_lots l JOIN plugin_inventory_products p ON p.id = l.product_id WHERE l.id = ? AND l.warehouse_id = ? AND l.status = \'active\' LIMIT 1 FOR UPDATE', [$lotId, $from]);
            $destination = \Model::fetch("SELECT id FROM plugin_inventory_warehouses WHERE id = ? AND status = 'active' LIMIT 1 FOR UPDATE", [$to]);
            if (!$source || !$destination || $quantity > ((int) $source['on_hand_qty'] - (int) $source['reserved_qty'])) {
                throw new \InvalidArgumentException('موجودی آزاد یا یکی از انبارهای انتقال معتبر نیست.');
            }
            if (($source['tracking_mode'] ?? '') === 'serial' && count($serials) !== $quantity) { throw new \InvalidArgumentException('برای کالای سریالی شناسه هر واحد انتقال را ثبت کنید.'); }
            if (($source['tracking_mode'] ?? '') !== 'serial' && $serials) { throw new \InvalidArgumentException('برای کالای تعدادی شناسه سریال ارسال نکنید.'); }
            $serialRows = self::lockSerials($serials, (int) $source['product_id'], $lotId, $from, 'in_stock');
            $number = self::number('TRF');
            \Model::execute('INSERT INTO plugin_inventory_transfers (transfer_no, from_warehouse_id, to_warehouse_id, status, requested_by, requested_at, note, request_hash, idempotency_key) VALUES (?, ?, ?, \'pending\', ?, NOW(), ?, ?, ?)', [
                $number, $from, $to, $actorId ? (int) $actorId : null, trim((string) ($data['note'] ?? '')) ?: null, $hash, $key,
            ]);
            $transferId = (int) \Model::lastInsertId();
            \Model::execute('INSERT INTO plugin_inventory_transfer_lines (transfer_id, source_lot_id, destination_lot_id, quantity) VALUES (?, ?, NULL, ?)', [$transferId, $lotId, $quantity]);
            $lineId = (int) \Model::lastInsertId();
            foreach ($serialRows as $serial) {
                \Model::execute('INSERT INTO plugin_inventory_transfer_serial_items (transfer_line_id, serial_item_id) VALUES (?, ?)', [$lineId, (int) $serial['id']]);
                \Model::execute("UPDATE plugin_inventory_serial_items SET status = 'reserved' WHERE id = ? AND status = 'in_stock'", [(int) $serial['id']]);
            }
            \Model::execute('UPDATE plugin_inventory_stock_lots SET reserved_qty = reserved_qty + ? WHERE id = ? AND on_hand_qty - reserved_qty >= ?', [$quantity, $lotId, $quantity]);
            PurchaseService::movement('transfer_reserved', (int) $source['product_id'], $lotId, $from, $to, $quantity, Money::integer($source['unit_landed_cost_rial']), 'transfer', $transferId, trim((string) ($data['note'] ?? '')), $actorId);
            AuditService::record('transfer_requested', 'transfer', $transferId, $actorId, [], ['from' => $from, 'to' => $to, 'lot_id' => $lotId, 'quantity' => $quantity]);
            \Model::commit();
            return $transferId;
        } catch (\Throwable $error) {
            \Model::rollBack();
            throw $error;
        }
    }

    public static function receiveTransfer($transferId, $actorId)
    {
        \Model::begin();
        try {
            $transfer = \Model::fetch('SELECT * FROM plugin_inventory_transfers WHERE id = ? LIMIT 1 FOR UPDATE', [(int) $transferId]);
            if (!$transfer || $transfer['status'] !== 'pending') { throw new \InvalidArgumentException('انتقال در وضعیت قابل دریافت نیست.'); }
            $lines = \Model::fetchAll('SELECT * FROM plugin_inventory_transfer_lines WHERE transfer_id = ? ORDER BY id ASC FOR UPDATE', [(int) $transferId]);
            foreach ($lines as $line) {
                $source = \Model::fetch('SELECT * FROM plugin_inventory_stock_lots WHERE id = ? LIMIT 1 FOR UPDATE', [(int) $line['source_lot_id']]);
                $qty = (int) $line['quantity'];
                if (!$source || (int) $source['reserved_qty'] < $qty || (int) $source['on_hand_qty'] < $qty) { throw new \RuntimeException('محموله مبدأ با رزرو انتقال تطبیق ندارد.'); }
                $remainder = min(Money::integer($source['cost_remainder_rial']), $qty);
                $transferCost = Money::add(Money::multiply(Money::integer($source['unit_landed_cost_rial']), $qty), $remainder);
                $shares = \Model::fetchAll('SELECT * FROM plugin_inventory_lot_partner_shares WHERE lot_id = ? ORDER BY partner_id ASC FOR UPDATE', [(int) $source['id']]);
                $caps = [];
                foreach ($shares as $share) { $caps[] = ['partner_id' => (int) $share['partner_id'], 'weight_bp' => (int) $share['capital_share_bp'], 'cap_rial' => Money::integer($share['remaining_capital_rial'])]; }
                $currentCost = Money::add(Money::multiply(Money::integer($source['unit_landed_cost_rial']), (int) $source['on_hand_qty']), Money::integer($source['cost_remainder_rial']));
                if ($qty === (int) $source['on_hand_qty'] && (int) $source['reserved_qty'] === $qty) {
                    $capital = [];
                    foreach ($shares as $share) { $capital[(int) $share['partner_id']] = Money::integer($share['remaining_capital_rial']); }
                    if (array_sum($capital) !== $currentCost) { throw new \RuntimeException('مانده سرمایه محموله با بهای آن تطبیق ندارد.'); }
                } else {
                    $capital = Money::allocateCapped($transferCost, $caps);
                }
                $lotNo = self::number('TRFLOT');
                \Model::execute(
                    'INSERT INTO plugin_inventory_stock_lots (lot_no, product_id, purchase_id, supplier_id, warehouse_id, entered_at, initial_qty, on_hand_qty, reserved_qty, unit_purchase_cost_rial, unit_expense_rial, unit_landed_cost_rial, cost_remainder_rial, status, note, created_by, created_at)
                     VALUES (?, ?, ?, ?, ?, NOW(), ?, ?, 0, ?, ?, ?, ?, \'active\', ?, ?, NOW())',
                    [$lotNo, (int) $source['product_id'], $source['purchase_id'], $source['supplier_id'], (int) $transfer['to_warehouse_id'], $qty, $qty, $source['unit_purchase_cost_rial'], $source['unit_expense_rial'], $source['unit_landed_cost_rial'], Money::db($remainder), 'انتقال از محموله ' . $source['lot_no'], $actorId ? (int) $actorId : null]
                );
                $destinationLotId = (int) \Model::lastInsertId();
                foreach ($shares as $share) {
                    $partnerId = (int) $share['partner_id'];
                    $capitalAmount = (int) ($capital[$partnerId] ?? 0);
                    $remaining = Money::integer($share['remaining_capital_rial']) - $capitalAmount;
                    \Model::execute('UPDATE plugin_inventory_lot_partner_shares SET remaining_capital_rial = ? WHERE lot_id = ? AND partner_id = ?', [Money::db($remaining), (int) $source['id'], $partnerId]);
                    \Model::execute('INSERT INTO plugin_inventory_lot_partner_shares (lot_id, partner_id, capital_share_bp, profit_share_bp, initial_capital_rial, remaining_capital_rial) VALUES (?, ?, ?, ?, ?, ?)', [
                        $destinationLotId, $partnerId, (int) $share['capital_share_bp'], (int) $share['profit_share_bp'], Money::db($capitalAmount), Money::db($capitalAmount),
                    ]);
                }
                \Model::execute('UPDATE plugin_inventory_stock_lots SET on_hand_qty = on_hand_qty - ?, reserved_qty = reserved_qty - ?, cost_remainder_rial = cost_remainder_rial - ? WHERE id = ?', [$qty, $qty, Money::db($remainder), (int) $source['id']]);
                \Model::execute('UPDATE plugin_inventory_serial_items si JOIN plugin_inventory_transfer_serial_items ts ON ts.serial_item_id = si.id SET si.lot_id = ?, si.warehouse_id = ?, si.status = \'in_stock\' WHERE ts.transfer_line_id = ?', [$destinationLotId, (int) $transfer['to_warehouse_id'], (int) $line['id']]);
                \Model::execute('UPDATE plugin_inventory_transfer_lines SET destination_lot_id = ? WHERE id = ?', [$destinationLotId, (int) $line['id']]);
                PurchaseService::movement('transfer_out', (int) $source['product_id'], (int) $source['id'], (int) $transfer['from_warehouse_id'], null, $qty, Money::integer($source['unit_landed_cost_rial']), 'transfer', (int) $transferId, 'ارسال انتقال', $actorId);
                PurchaseService::movement('transfer_in', (int) $source['product_id'], $destinationLotId, null, (int) $transfer['to_warehouse_id'], $qty, Money::integer($source['unit_landed_cost_rial']), 'transfer', (int) $transferId, 'تأیید دریافت انتقال', $actorId);
            }
            \Model::execute("UPDATE plugin_inventory_transfers SET status = 'received', received_by = ?, received_at = NOW() WHERE id = ?", [$actorId ? (int) $actorId : null, (int) $transferId]);
            AuditService::record('transfer_received', 'transfer', (int) $transferId, $actorId, ['status' => 'pending'], ['status' => 'received']);
            \Model::commit();
            return true;
        } catch (\Throwable $error) {
            \Model::rollBack();
            throw $error;
        }
    }

    public static function cancelTransfer($transferId, $reason, $actorId)
    {
        $reason = trim((string) $reason);
        if ($reason === '') { throw new \InvalidArgumentException('علت لغو انتقال الزامی است.'); }
        \Model::begin();
        try {
            $transfer = \Model::fetch('SELECT * FROM plugin_inventory_transfers WHERE id = ? LIMIT 1 FOR UPDATE', [(int) $transferId]);
            if (!$transfer || $transfer['status'] !== 'pending') { throw new \InvalidArgumentException('فقط انتقال در انتظار را می‌توان لغو کرد.'); }
            $lines = \Model::fetchAll('SELECT * FROM plugin_inventory_transfer_lines WHERE transfer_id = ? ORDER BY id ASC FOR UPDATE', [(int) $transferId]);
            foreach ($lines as $line) {
                $source = \Model::fetch('SELECT id, product_id, warehouse_id, unit_landed_cost_rial, reserved_qty FROM plugin_inventory_stock_lots WHERE id = ? LIMIT 1 FOR UPDATE', [(int) $line['source_lot_id']]);
                if (!$source || (int) $source['reserved_qty'] < (int) $line['quantity']) { throw new \RuntimeException('رزرو انتقال قابل آزادسازی نیست.'); }
                \Model::execute('UPDATE plugin_inventory_stock_lots SET reserved_qty = reserved_qty - ? WHERE id = ?', [(int) $line['quantity'], (int) $source['id']]);
                \Model::execute('UPDATE plugin_inventory_serial_items si JOIN plugin_inventory_transfer_serial_items ts ON ts.serial_item_id = si.id SET si.status = \'in_stock\' WHERE ts.transfer_line_id = ? AND si.status = \'reserved\'', [(int) $line['id']]);
                PurchaseService::movement('transfer_released', (int) $source['product_id'], (int) $source['id'], (int) $transfer['from_warehouse_id'], (int) $transfer['to_warehouse_id'], (int) $line['quantity'], Money::integer($source['unit_landed_cost_rial']), 'transfer', (int) $transferId, $reason, $actorId);
            }
            \Model::execute("UPDATE plugin_inventory_transfers SET status = 'cancelled' WHERE id = ?", [(int) $transferId]);
            AuditService::record('transfer_cancelled', 'transfer', (int) $transferId, $actorId, ['status' => 'pending'], ['status' => 'cancelled', 'reason' => $reason]);
            \Model::commit();
            return true;
        } catch (\Throwable $error) {
            \Model::rollBack();
            throw $error;
        }
    }

    public static function transfers($limit = 100)
    {
        $limit = max(1, min(250, (int) $limit));
        return \Model::fetchAll('SELECT t.*, w1.name AS from_name, w2.name AS to_name, u1.full_name AS requested_name, u2.full_name AS received_name,
                GROUP_CONCAT(CONCAT(p.name, \' × \', tl.quantity) ORDER BY tl.id SEPARATOR \'، \') AS items
            FROM plugin_inventory_transfers t
            JOIN plugin_inventory_warehouses w1 ON w1.id = t.from_warehouse_id
            JOIN plugin_inventory_warehouses w2 ON w2.id = t.to_warehouse_id
            LEFT JOIN users u1 ON u1.id = t.requested_by
            LEFT JOIN users u2 ON u2.id = t.received_by
            LEFT JOIN plugin_inventory_transfer_lines tl ON tl.transfer_id = t.id
            LEFT JOIN plugin_inventory_stock_lots l ON l.id = tl.source_lot_id
            LEFT JOIN plugin_inventory_products p ON p.id = l.product_id
            GROUP BY t.id ORDER BY t.id DESC LIMIT ' . $limit);
    }

    private static function lockSerials(array $serials, $productId, $lotId, $warehouseId, $status)
    {
        if (!$serials) { return []; }
        $placeholders = implode(',', array_fill(0, count($serials), '?'));
        $params = array_merge($serials, [(int) $productId, (int) $lotId, (int) $warehouseId, (string) $status]);
        $rows = \Model::fetchAll("SELECT id, serial_no FROM plugin_inventory_serial_items WHERE serial_no IN ($placeholders) AND product_id = ? AND lot_id = ? AND warehouse_id = ? AND status = ? ORDER BY id ASC FOR UPDATE", $params);
        if (count($rows) !== count($serials)) { throw new \InvalidArgumentException('یک یا چند IMEI/Serial در محموله موجود نیست یا وضعیت آن قابل انتقال نیست.'); }
        return $rows;
    }

    private static function serials($raw)
    {
        $items = is_array($raw) ? $raw : (preg_split('/\r\n|\r|\n|,|،/', (string) $raw) ?: []);
        $unique = [];
        foreach ($items as $item) {
            $item = trim((string) $item);
            if ($item === '') { continue; }
            if (strlen($item) > 120 || preg_match('/[\x00-\x1F]/', $item) || isset($unique[$item])) { throw new \InvalidArgumentException('IMEI/Serial تکراری یا نامعتبر است.'); }
            $unique[$item] = $item;
        }
        return array_values($unique);
    }

    private static function number($prefix)
    {
        return strtoupper($prefix) . '-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(3)));
    }
}
