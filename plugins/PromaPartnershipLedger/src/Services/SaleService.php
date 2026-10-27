<?php

namespace Proma\Plugins\PartnershipLedger\Services;

use Proma\Plugins\PartnershipLedger\Domain\Money;
use Proma\Plugins\PartnershipLedger\Domain\ProfitCalculator;

final class SaleService
{
    public static function create(array $data, $actorId, $kind)
    {
        $kind = trim((string) $kind);
        if (!in_array($kind, ['cash', 'installment'], true)) {
            throw new \InvalidArgumentException('نوع فروش معتبر نیست.');
        }
        $productId = (int) ($data['product_id'] ?? 0);
        $lotId = (int) ($data['lot_id'] ?? 0);
        $warehouseId = (int) ($data['warehouse_id'] ?? 0);
        $quantity = Money::quantity($data['quantity'] ?? '');
        $unitSale = Money::fromToman($data['unit_sale_toman'] ?? '0');
        $sellingExpenses = Money::fromToman($data['selling_expenses_toman'] ?? '0');
        $contractId = $kind === 'installment' ? (int) ($data['contract_id'] ?? 0) : 0;
        $idempotencyKey = trim((string) ($data['idempotency_key'] ?? ''));
        $serials = self::serials($data['serial_numbers'] ?? '');
        if ($productId <= 0 || $lotId <= 0 || $warehouseId <= 0 || !$quantity || $quantity < 1 || $quantity > 1000000
            || $unitSale <= 0 || $idempotencyKey === '' || ($kind === 'installment' && $contractId <= 0)) {
            throw new \InvalidArgumentException('اطلاعات فروش معتبر نیست.');
        }
        $hash = hash('sha256', json_encode([
            'kind' => $kind, 'contract_id' => $contractId ?: null, 'product_id' => $productId, 'lot_id' => $lotId,
            'warehouse_id' => $warehouseId, 'quantity' => $quantity, 'unit_sale_rial' => $unitSale,
            'selling_expenses_rial' => $sellingExpenses, 'serials' => $serials,
            'customer_name' => trim((string) ($data['customer_name'] ?? '')),
            'payment_method' => trim((string) ($data['payment_method'] ?? '')),
            'payment_reference' => trim((string) ($data['payment_reference'] ?? '')),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        $started = !\Model::db()->inTransaction();
        if ($started) { \Model::begin(); }
        try {
            $existing = \Model::fetch('SELECT id, request_hash FROM plugin_inventory_sales WHERE idempotency_key = ? LIMIT 1 FOR UPDATE', [$idempotencyKey]);
            if ($existing) {
                if (!hash_equals((string) $existing['request_hash'], $hash)) {
                    throw new \InvalidArgumentException('کلید تکرار فروش با اطلاعات متفاوت استفاده شده است.');
                }
                if ($started) { \Model::commit(); }
                return (int) $existing['id'];
            }
            $product = \Model::fetch("SELECT * FROM plugin_inventory_products WHERE id = ? AND status = 'active' LIMIT 1 FOR UPDATE", [$productId]);
            $lot = \Model::fetch("SELECT * FROM plugin_inventory_stock_lots WHERE id = ? AND product_id = ? AND warehouse_id = ? AND status = 'active' LIMIT 1 FOR UPDATE", [$lotId, $productId, $warehouseId]);
            if (!$product || !$lot) {
                throw new \InvalidArgumentException('کالا یا محموله فعال در انبار انتخاب‌شده پیدا نشد.');
            }
            $available = (int) $lot['on_hand_qty'] - (int) $lot['reserved_qty'];
            if ($quantity > $available) {
                throw new \InvalidArgumentException('موجودی آزاد برای این فروش کافی نیست.');
            }
            if ($unitSale < Money::integer($product['minimum_sale_price_rial'])) {
                throw new \InvalidArgumentException('قیمت فروش از حداقل قیمت مجاز کالا کمتر است.');
            }
            if (($product['tracking_mode'] ?? '') === 'serial' && count($serials) !== $quantity) {
                throw new \InvalidArgumentException('برای کالای سریالی، شماره IMEI/Serial هر واحد را انتخاب کنید.');
            }
            if (($product['tracking_mode'] ?? '') !== 'serial' && $serials) {
                throw new \InvalidArgumentException('برای کالای تعدادی، شماره سریال ارسال نکنید.');
            }
            $serialRows = self::lockSerials($serials, $productId, $lotId, $warehouseId);
            if ($kind === 'cash' && !in_array((string) ($data['payment_method'] ?? ''), ['cash', 'bank_transfer', 'card_to_card', 'pos', 'other'], true)) {
                throw new \InvalidArgumentException('روش دریافت وجه فروش نقدی را انتخاب کنید.');
            }

            $contract = null;
            if ($kind === 'installment') {
                $contract = \Model::fetch('SELECT c.id, c.customer_id, c.principal_amount, c.status, u.full_name AS customer_name, u.mobile AS customer_phone
                                             FROM contracts c JOIN users u ON u.id = c.customer_id WHERE c.id = ? LIMIT 1 FOR UPDATE', [$contractId]);
                if (!$contract || !in_array((string) $contract['status'], ['active', 'pending'], true)) {
                    throw new \InvalidArgumentException('قرارداد فعال یا در انتظار پرداخت برای اتصال فروش پیدا نشد.');
                }
            }
            $capitalShares = \Model::fetchAll('SELECT partner_id, capital_share_bp, profit_share_bp, remaining_capital_rial FROM plugin_inventory_lot_partner_shares WHERE lot_id = ? ORDER BY partner_id ASC FOR UPDATE', [$lotId]);
            if (!$capitalShares) {
                throw new \RuntimeException('برای محموله سهم مالکیت ثبت نشده است.');
            }
            $revenue = Money::multiply($unitSale, $quantity);
            $unitCost = Money::integer($lot['unit_landed_cost_rial']);
            $remainderApplied = min(Money::integer($lot['cost_remainder_rial']), $quantity);
            $cost = Money::add(Money::multiply($unitCost, $quantity), $remainderApplied);
            $profit = ProfitCalculator::netProfit($revenue, $cost, $sellingExpenses);
            if ($kind === 'installment' && Money::fromPromaToman($contract['principal_amount']) !== $revenue) {
                throw new \InvalidArgumentException('مبلغ اصل قرارداد Proma Pay باید با جمع قیمت فروش کالا برابر باشد.');
            }
            $capitalWeights = [];
            $capitalCaps = [];
            $profitWeights = [];
            foreach ($capitalShares as $share) {
                if ((int) $share['capital_share_bp'] > 0) {
                    $capitalWeights[] = ['partner_id' => (int) $share['partner_id'], 'weight_bp' => (int) $share['capital_share_bp']];
                }
                if ((int) $share['profit_share_bp'] > 0) {
                    $profitWeights[] = ['partner_id' => (int) $share['partner_id'], 'weight_bp' => (int) $share['profit_share_bp']];
                }
                $capitalCaps[] = ['partner_id' => (int) $share['partner_id'], 'weight_bp' => (int) $share['capital_share_bp'], 'cap_rial' => Money::integer($share['remaining_capital_rial'])];
            }
            $remainingLotCost = Money::add(Money::multiply($unitCost, (int) $lot['on_hand_qty']), Money::integer($lot['cost_remainder_rial']));
            if ($quantity === $available && (int) $lot['reserved_qty'] === 0) {
                $capitalAmounts = [];
                foreach ($capitalCaps as $row) { $capitalAmounts[$row['partner_id']] = $row['cap_rial']; }
                if (array_sum($capitalAmounts) !== $remainingLotCost) {
                    throw new \RuntimeException('مانده بهای محموله با سهم سرمایه شرکا تطبیق ندارد.');
                }
            } else {
                $capitalAmounts = Money::allocateCapped($cost, $capitalCaps);
            }
            if (array_sum($capitalAmounts) !== $cost) {
                throw new \RuntimeException('تخصیص بهای فروش به شرکا برابر بهای تمام‌شده نیست.');
            }
            $profitAmounts = $profitWeights ? ProfitCalculator::distributeProfit($profit, $profitWeights) : [];
            $saleNo = self::number('SAL');
            $customerName = $kind === 'installment' ? (string) ($contract['customer_name'] ?? '') : trim((string) ($data['customer_name'] ?? ''));
            $customerPhone = $kind === 'installment' ? (string) ($contract['customer_phone'] ?? '') : trim((string) ($data['customer_phone'] ?? ''));
            \Model::execute(
                'INSERT INTO plugin_inventory_sales (sale_no, sale_kind, contract_id, customer_name, customer_phone, payment_method, payment_reference, sold_at, status, total_sale_rial, total_cost_rial, selling_expenses_rial, net_profit_rial, collected_principal_rial, request_hash, idempotency_key, note, created_by, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), \'posted\', ?, ?, ?, ?, 0, ?, ?, ?, ?, NOW())',
                [$saleNo, $kind, $contractId ?: null, $customerName ?: null, $customerPhone ?: null, $kind === 'cash' ? (string) $data['payment_method'] : null,
                    $kind === 'cash' ? (trim((string) ($data['payment_reference'] ?? '')) ?: null) : null,
                    Money::db($revenue), Money::db($cost), Money::db($sellingExpenses), (string) $profit, $hash, $idempotencyKey,
                    trim((string) ($data['note'] ?? '')) ?: null, $actorId ? (int) $actorId : null]
            );
            $saleId = (int) \Model::lastInsertId();
            \Model::execute(
                'INSERT INTO plugin_inventory_sale_lines (sale_id, product_id, lot_id, warehouse_id, quantity, unit_sale_price_rial, unit_landed_cost_rial, allocated_selling_expense_rial, line_revenue_rial, line_cost_rial, line_net_profit_rial, returned_qty, returned_cost_rial, cost_remainder_applied_rial)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0, ?)',
                [$saleId, $productId, $lotId, $warehouseId, $quantity, Money::db($unitSale), Money::db($unitCost), Money::db($sellingExpenses), Money::db($revenue), Money::db($cost), (string) $profit, Money::db($remainderApplied)]
            );
            $saleLineId = (int) \Model::lastInsertId();
            foreach ($capitalShares as $share) {
                $partnerId = (int) $share['partner_id'];
                \Model::execute(
                    'INSERT INTO plugin_inventory_sale_partner_shares (sale_line_id, partner_id, capital_share_bp, profit_share_bp, capital_amount_rial, profit_amount_rial, capital_returned_rial, profit_released_rial)
                     VALUES (?, ?, ?, ?, ?, ?, 0, 0)',
                    [$saleLineId, $partnerId, (int) $share['capital_share_bp'], (int) $share['profit_share_bp'], Money::db($capitalAmounts[$partnerId] ?? 0), (string) ($profitAmounts[$partnerId] ?? 0)]
                );
                $nextCapital = Money::integer($share['remaining_capital_rial']) - (int) ($capitalAmounts[$partnerId] ?? 0);
                if ($nextCapital < 0) {
                    throw new \RuntimeException('فروش از مانده سرمایه مالکیت محموله بیشتر است.');
                }
                \Model::execute('UPDATE plugin_inventory_lot_partner_shares SET remaining_capital_rial = ? WHERE lot_id = ? AND partner_id = ?', [Money::db($nextCapital), $lotId, $partnerId]);
            }
            \Model::execute('UPDATE plugin_inventory_stock_lots SET on_hand_qty = on_hand_qty - ?, cost_remainder_rial = cost_remainder_rial - ? WHERE id = ? AND on_hand_qty - reserved_qty >= ?', [$quantity, Money::db($remainderApplied), $lotId, $quantity]);
            foreach ($serialRows as $serial) {
                \Model::execute("UPDATE plugin_inventory_serial_items SET status = 'sold', sale_id = ?, sold_at = NOW() WHERE id = ? AND status = 'in_stock'", [$saleId, (int) $serial['id']]);
                \Model::execute('INSERT INTO plugin_inventory_sale_serial_items (sale_line_id, serial_item_id) VALUES (?, ?)', [$saleLineId, (int) $serial['id']]);
            }
            PurchaseService::movement('sale_out', $productId, $lotId, $warehouseId, null, $quantity, $unitCost, 'sale', $saleId, trim((string) ($data['note'] ?? '')), $actorId);
            self::postSaleJournals($saleId, $saleNo, $kind, $contractId, $revenue, $cost, $sellingExpenses, $profit, $capitalAmounts, $profitAmounts, $actorId);
            AuditService::record('sale_posted', 'sale', $saleId, $actorId, [], ['sale_no' => $saleNo, 'kind' => $kind, 'contract_id' => $contractId ?: null, 'revenue_rial' => Money::db($revenue), 'cost_rial' => Money::db($cost), 'profit_rial' => (string) $profit]);

            if ($kind === 'cash') {
                self::allocateProceeds($saleId, $revenue, 'sale:' . $saleId, $actorId);
                \Model::execute('UPDATE plugin_inventory_sales SET collected_principal_rial = ? WHERE id = ?', [Money::db($revenue), $saleId]);
            } else {
                self::reconcileContract($contractId, $actorId);
            }
            if ($started) { \Model::commit(); }
            return $saleId;
        } catch (\Throwable $error) {
            if ($started) { \Model::rollBack(); }
            throw $error;
        }
    }

    private static function postSaleJournals($saleId, $saleNo, $kind, $contractId, $revenue, $cost, $sellingExpenses, $profit, array $capitalAmounts, array $profitAmounts, $actorId)
    {
        LedgerService::post('sale:' . $saleId . ':revenue', 'inventory_sale', $saleId, 'ثبت درآمد فروش ' . $saleNo, $actorId, [
            ['account_code' => $kind === 'cash' ? 'cash' : 'contract_receivable', 'debit_rial' => $revenue, 'credit_rial' => 0, 'memo' => $kind === 'cash' ? 'دریافت فروش نقدی' : 'طلب قرارداد'],
            ['account_code' => 'sales_revenue', 'debit_rial' => 0, 'credit_rial' => $revenue, 'memo' => 'درآمد فروش کالا'],
        ]);
        $costLines = [];
        foreach ($capitalAmounts as $partnerId => $amount) {
            if ($amount <= 0) { continue; }
            $costLines[] = ['account_code' => 'cost_of_goods_sold', 'partner_id' => (int) $partnerId, 'debit_rial' => $amount, 'credit_rial' => 0, 'memo' => 'بهای کالای فروخته‌شده'];
            $costLines[] = ['account_code' => 'inventory_asset', 'partner_id' => (int) $partnerId, 'debit_rial' => 0, 'credit_rial' => $amount, 'memo' => 'خروج از موجودی'];
        }
        if ($costLines) { LedgerService::post('sale:' . $saleId . ':cogs', 'inventory_sale', $saleId, 'ثبت بهای تمام‌شده فروش ' . $saleNo, $actorId, $costLines); }
        if ($sellingExpenses > 0) {
            LedgerService::post('sale:' . $saleId . ':expenses', 'inventory_sale', $saleId, 'ثبت هزینه فروش ' . $saleNo, $actorId, [
                ['account_code' => 'selling_expense', 'debit_rial' => $sellingExpenses, 'credit_rial' => 0],
                ['account_code' => 'cash', 'debit_rial' => 0, 'credit_rial' => $sellingExpenses],
            ]);
        }
        if ($profit > 0) {
            $lines = [['account_code' => 'partner_profit_distribution', 'debit_rial' => $profit, 'credit_rial' => 0, 'memo' => 'سود شناسایی‌شده فروش']];
            foreach ($profitAmounts as $partnerId => $amount) {
                if ($amount > 0) { $lines[] = ['account_code' => 'partner_profit_accrued', 'partner_id' => (int) $partnerId, 'debit_rial' => 0, 'credit_rial' => $amount, 'memo' => 'سود تحقق‌یافته شریک']; }
            }
            LedgerService::post('sale:' . $saleId . ':profit', 'inventory_sale', $saleId, 'تخصیص سود فروش ' . $saleNo, $actorId, $lines);
        } elseif ($profit < 0) {
            $lines = [];
            $lossTotal = 0;
            foreach ($profitAmounts as $partnerId => $amount) {
                if ($amount >= 0) { continue; }
                $loss = abs((int) $amount);
                $lossTotal = Money::add($lossTotal, $loss);
                $lines[] = ['account_code' => 'partner_capital', 'partner_id' => (int) $partnerId, 'debit_rial' => $loss, 'credit_rial' => 0, 'memo' => 'سهم زیان فروش'];
            }
            if ($lossTotal > 0) {
                $lines[] = ['account_code' => 'partner_loss_absorption', 'debit_rial' => 0, 'credit_rial' => $lossTotal, 'memo' => 'جذب زیان فروش'];
                LedgerService::post('sale:' . $saleId . ':loss', 'inventory_sale', $saleId, 'تخصیص زیان فروش ' . $saleNo, $actorId, $lines);
            }
        }
    }

    private static function allocateProceeds($saleId, $amount, $eventPrefix, $actorId, $sourceType = 'inventory_sale', $sourceId = null)
    {
        $amount = Money::integer($amount);
        $shares = \Model::fetchAll('SELECT * FROM plugin_inventory_sale_partner_shares WHERE sale_line_id IN (SELECT id FROM plugin_inventory_sale_lines WHERE sale_id = ?) ORDER BY partner_id ASC FOR UPDATE', [(int) $saleId]);
        if (!$shares) {
            throw new \RuntimeException('سهم شرکا برای فروش ثبت نشده است.');
        }
        $capitalRows = [];
        $profitRows = [];
        $capitalTotal = 0;
        $profitTotal = 0;
        foreach ($shares as $share) {
            $capitalLeft = Money::integer($share['capital_amount_rial']) - Money::integer($share['capital_returned_rial']);
            $profitAmount = max(0, (int) $share['profit_amount_rial']);
            $profitLeft = max(0, $profitAmount - Money::integer($share['profit_released_rial']));
            $capitalTotal = Money::add($capitalTotal, $capitalLeft);
            $profitTotal = Money::add($profitTotal, $profitLeft);
            $capitalRows[] = ['partner_id' => (int) $share['partner_id'], 'weight_bp' => (int) $share['capital_share_bp'], 'cap_rial' => $capitalLeft];
            $profitRows[] = ['partner_id' => (int) $share['partner_id'], 'weight_bp' => (int) $share['profit_share_bp'], 'cap_rial' => $profitLeft];
        }
        $capitalPay = min($amount, $capitalTotal);
        $capitalApplied = $capitalPay > 0 ? Money::allocateCapped($capitalPay, $capitalRows) : [];
        $profitPay = min($amount - $capitalPay, $profitTotal);
        $profitApplied = $profitPay > 0 ? Money::allocateCapped($profitPay, $profitRows) : [];
        if ($capitalPay > 0) {
            $lines = [];
            foreach ($capitalApplied as $partnerId => $value) {
                if ($value > 0) {
                    $lines[] = ['account_code' => 'partner_capital', 'partner_id' => (int) $partnerId, 'debit_rial' => $value, 'credit_rial' => 0, 'memo' => 'بازیافت اصل سرمایه فروش'];
                    $lines[] = ['account_code' => 'partner_capital_payable', 'partner_id' => (int) $partnerId, 'debit_rial' => 0, 'credit_rial' => $value, 'memo' => 'اصل سرمایه قابل تسویه'];
                }
            }
            LedgerService::post($eventPrefix . ':capital', $sourceType, $sourceId ?: (int) $saleId, 'بازیافت اصل سرمایه فروش ' . $saleId, $actorId, $lines);
        }
        if ($profitPay > 0) {
            $lines = [];
            foreach ($profitApplied as $partnerId => $value) {
                if ($value > 0) {
                    $lines[] = ['account_code' => 'partner_profit_accrued', 'partner_id' => (int) $partnerId, 'debit_rial' => $value, 'credit_rial' => 0, 'memo' => 'آزادسازی سود وصول‌شده'];
                    $lines[] = ['account_code' => 'partner_profit_payable', 'partner_id' => (int) $partnerId, 'debit_rial' => 0, 'credit_rial' => $value, 'memo' => 'سود قابل تسویه'];
                }
            }
            LedgerService::post($eventPrefix . ':profit-release', $sourceType, $sourceId ?: (int) $saleId, 'آزادسازی سود وصول‌شده فروش ' . $saleId, $actorId, $lines);
        }
        foreach ($shares as $share) {
            $partnerId = (int) $share['partner_id'];
            $capitalValue = (int) ($capitalApplied[$partnerId] ?? 0);
            $profitValue = (int) ($profitApplied[$partnerId] ?? 0);
            if ($capitalValue || $profitValue) {
                \Model::execute('UPDATE plugin_inventory_sale_partner_shares SET capital_returned_rial = capital_returned_rial + ?, profit_released_rial = profit_released_rial + ? WHERE id = ?', [Money::db($capitalValue), Money::db($profitValue), (int) $share['id']]);
            }
        }
        return ['capital' => $capitalApplied, 'profit' => $profitApplied, 'capital_total' => $capitalPay, 'profit_total' => $profitPay];
    }

    public static function importPayment($paymentId, $actorId = null)
    {
        $paymentId = (int) $paymentId;
        if ($paymentId <= 0) { return false; }
        $started = !\Model::db()->inTransaction();
        if ($started) { \Model::begin(); }
        try {
            $payment = \Model::fetch('SELECT id, contract_id, amount, status, is_corrected, payment_type, principal_applied FROM payments WHERE id = ? LIMIT 1 FOR UPDATE', [$paymentId]);
            if (!$payment) {
                if ($started) { \Model::commit(); }
                return false;
            }
            $sale = \Model::fetch('SELECT * FROM plugin_inventory_sales WHERE contract_id = ? LIMIT 1 FOR UPDATE', [(int) $payment['contract_id']]);
            if (!$sale) {
                if ($started) { \Model::commit(); }
                return false;
            }
            $contract = \Model::fetch('SELECT status FROM contracts WHERE id = ? LIMIT 1', [(int) $payment['contract_id']]);
            $allocation = \Model::fetch('SELECT * FROM plugin_inventory_payment_allocations WHERE source_payment_id = ? LIMIT 1 FOR UPDATE', [$paymentId]);
            $eligible = (string) $payment['status'] === 'paid'
                && (int) $payment['is_corrected'] === 0
                && (string) $payment['payment_type'] !== 'legal_cost'
                && (string) ($contract['status'] ?? '') !== 'cancelled'
                && (string) $sale['status'] === 'posted';
            if (!$eligible) {
                if ($allocation && $allocation['state'] === 'active') {
                    self::reversePaymentAllocation($allocation, $actorId, 'وضعیت پرداخت در Proma Pay اصلاح یا ابطال شد.');
                }
                if ($started) { \Model::commit(); }
                return false;
            }
            if ($allocation && $allocation['state'] === 'active') {
                if ($started) { \Model::commit(); }
                return false;
            }
            if ($allocation && $allocation['state'] === 'reversed') {
                if ($started) { \Model::commit(); }
                return false;
            }
            $principalToman = null;
            if ((string) $payment['payment_type'] === 'down_payment') {
                $principalToman = $payment['amount'];
            } elseif ($payment['principal_applied'] !== null) {
                $principalToman = $payment['principal_applied'];
            }
            if ($principalToman === null) {
                if ($started) { \Model::commit(); }
                return false;
            }
            $principal = Money::fromPromaToman($principalToman);
            $collected = Money::integer($sale['collected_principal_rial']);
            $outstanding = max(0, Money::integer($sale['total_sale_rial']) - $collected);
            $principal = min($principal, $outstanding);
            if ($principal <= 0) {
                if ($started) { \Model::commit(); }
                return false;
            }
            LedgerService::post('payment:' . $paymentId . ':collection', 'inventory_payment', $paymentId, 'وصول اصل پرداخت Proma Pay شماره ' . $paymentId, $actorId, [
                ['account_code' => 'cash', 'debit_rial' => $principal, 'credit_rial' => 0, 'memo' => 'وجه قطعی وصول‌شده'],
                ['account_code' => 'contract_receivable', 'debit_rial' => 0, 'credit_rial' => $principal, 'memo' => 'کاهش طلب قرارداد'],
            ]);
            $proceeds = self::allocateProceeds((int) $sale['id'], $principal, 'payment:' . $paymentId, $actorId, 'inventory_payment', $paymentId);
            $snapshot = json_encode($proceeds, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            \Model::execute(
                'INSERT INTO plugin_inventory_payment_allocations (sale_id, source_payment_id, principal_rial, applied_capital_rial, applied_profit_rial, allocation_snapshot_json, state, idempotency_key, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, \'active\', ?, NOW())',
                [(int) $sale['id'], $paymentId, Money::db($principal), Money::db($proceeds['capital_total']), Money::db($proceeds['profit_total']), $snapshot, 'payment:' . $paymentId]
            );
            \Model::execute('UPDATE plugin_inventory_sales SET collected_principal_rial = collected_principal_rial + ?, updated_at = NOW() WHERE id = ?', [Money::db($principal), (int) $sale['id']]);
            AuditService::record('contract_payment_allocated', 'sale', (int) $sale['id'], $actorId, [], ['payment_id' => $paymentId, 'principal_rial' => Money::db($principal), 'capital_rial' => Money::db($proceeds['capital_total']), 'profit_rial' => Money::db($proceeds['profit_total'])]);
            if ($started) { \Model::commit(); }
            return true;
        } catch (\Throwable $error) {
            if ($started) { \Model::rollBack(); }
            throw $error;
        }
    }

    private static function reversePaymentAllocation(array $allocation, $actorId, $reason)
    {
        $paymentId = (int) $allocation['source_payment_id'];
        $saleId = (int) $allocation['sale_id'];
        $snapshot = json_decode((string) ($allocation['allocation_snapshot_json'] ?? ''), true);
        $snapshot = is_array($snapshot) ? $snapshot : ['capital' => [], 'profit' => []];
        foreach (['capital', 'profit'] as $kind) {
            foreach ((array) ($snapshot[$kind] ?? []) as $partnerId => $amount) {
                $column = $kind === 'capital' ? 'capital_returned_rial' : 'profit_released_rial';
                $amount = Money::integer($amount);
                if ($amount > 0) {
                    \Model::execute('UPDATE plugin_inventory_sale_partner_shares SET ' . $column . ' = GREATEST(0, ' . $column . ' - ?) WHERE sale_line_id IN (SELECT id FROM plugin_inventory_sale_lines WHERE sale_id = ?) AND partner_id = ?', [Money::db($amount), $saleId, (int) $partnerId]);
                }
            }
        }
        foreach (['payment:' . $paymentId . ':collection', 'payment:' . $paymentId . ':capital', 'payment:' . $paymentId . ':profit-release'] as $eventKey) {
            $journal = \Model::fetch('SELECT id FROM plugin_inventory_journals WHERE event_key = ? LIMIT 1 FOR UPDATE', [$eventKey]);
            if ($journal && !\Model::fetch('SELECT id FROM plugin_inventory_journals WHERE reversal_of_id = ? LIMIT 1', [(int) $journal['id']])) {
                LedgerService::reverse((int) $journal['id'], $actorId, $reason);
            }
        }
        \Model::execute("UPDATE plugin_inventory_payment_allocations SET state = 'reversed' WHERE id = ? AND state = 'active'", [(int) $allocation['id']]);
        $principal = Money::integer($allocation['principal_rial']);
        \Model::execute('UPDATE plugin_inventory_sales SET collected_principal_rial = GREATEST(0, collected_principal_rial - ?), updated_at = NOW() WHERE id = ?', [Money::db($principal), $saleId]);
        AuditService::record('contract_payment_reversed', 'sale', $saleId, $actorId, [], ['payment_id' => $paymentId, 'principal_rial' => Money::db($principal), 'reason' => $reason]);
    }

    public static function reconcileContract($contractId, $actorId = null)
    {
        $contractId = (int) $contractId;
        $sale = \Model::fetch('SELECT id FROM plugin_inventory_sales WHERE contract_id = ? LIMIT 1', [$contractId]);
        if (!$sale) { return 0; }
        $payments = \Model::fetchAll('SELECT id FROM payments WHERE contract_id = ? AND payment_type <> \'legal_cost\' ORDER BY id ASC', [$contractId]);
        $changed = 0;
        foreach ($payments as $payment) {
            if (self::importPayment((int) $payment['id'], $actorId)) { $changed++; }
        }
        $active = \Model::fetchAll("SELECT a.*, p.status AS payment_status, p.is_corrected, p.payment_type
            FROM plugin_inventory_payment_allocations a LEFT JOIN payments p ON p.id = a.source_payment_id
            WHERE a.sale_id = ? AND a.state = 'active' ORDER BY a.id ASC", [(int) $sale['id']]);
        foreach ($active as $allocation) {
            if (($allocation['payment_status'] ?? '') !== 'paid' || (int) ($allocation['is_corrected'] ?? 1) !== 0 || ($allocation['payment_type'] ?? '') === 'legal_cost') {
                $started = !\Model::db()->inTransaction();
                if ($started) { \Model::begin(); }
                try {
                    $locked = \Model::fetch('SELECT * FROM plugin_inventory_payment_allocations WHERE id = ? LIMIT 1 FOR UPDATE', [(int) $allocation['id']]);
                    if ($locked && $locked['state'] === 'active') {
                        self::reversePaymentAllocation($locked, $actorId, 'تطبیق با وضعیت قطعی پرداخت Proma Pay');
                        $changed++;
                    }
                    if ($started) { \Model::commit(); }
                } catch (\Throwable $error) {
                    if ($started) { \Model::rollBack(); }
                    throw $error;
                }
            }
        }
        return $changed;
    }

    public static function reconcileSale($saleId, $actorId = null)
    {
        $sale = \Model::fetch('SELECT id, contract_id FROM plugin_inventory_sales WHERE id = ? LIMIT 1', [(int) $saleId]);
        if (!$sale || empty($sale['contract_id'])) { return 0; }
        return self::reconcileContract((int) $sale['contract_id'], $actorId);
    }

    public static function returnFull($saleId, $reason, $refundReference, $actorId)
    {
        $saleId = (int) $saleId;
        $reason = trim((string) $reason);
        $refundReference = trim((string) $refundReference);
        if ($saleId <= 0 || $reason === '') {
            throw new \InvalidArgumentException('شناسه فروش و علت برگشت الزامی است.');
        }
        $preSale = \Model::fetch('SELECT id, sale_kind, contract_id FROM plugin_inventory_sales WHERE id = ? LIMIT 1', [$saleId]);
        if (!$preSale) { throw new \InvalidArgumentException('فروش پیدا نشد.'); }
        if ($preSale['sale_kind'] === 'installment') {
            self::reconcileContract((int) $preSale['contract_id'], $actorId);
        }
        \Model::begin();
        try {
            $sale = \Model::fetch('SELECT * FROM plugin_inventory_sales WHERE id = ? LIMIT 1 FOR UPDATE', [$saleId]);
            if (!$sale || $sale['status'] !== 'posted') {
                throw new \InvalidArgumentException('فروش قبلاً برگشت خورده یا قابل برگشت نیست.');
            }
            if ($sale['sale_kind'] === 'cash' && $refundReference === '') {
                throw new \InvalidArgumentException('مرجع استرداد وجه فروش نقدی را ثبت کنید.');
            }
            if ($sale['sale_kind'] === 'installment') {
                $contract = \Model::fetch('SELECT status FROM contracts WHERE id = ? LIMIT 1 FOR UPDATE', [(int) $sale['contract_id']]);
                if (!$contract || (string) $contract['status'] !== 'cancelled') {
                    throw new \InvalidArgumentException('برای برگشت فروش اقساطی، ابتدا قرارداد را در Proma Pay لغو و پرداخت‌ها را اصلاح کنید.');
                }
                $activePayments = (int) (\Model::fetch("SELECT COUNT(*) AS total FROM plugin_inventory_payment_allocations WHERE sale_id = ? AND state = 'active'", [$saleId])['total'] ?? 0);
                if ($activePayments > 0) {
                    throw new \InvalidArgumentException('ابتدا پرداخت‌های اصلاح‌شده قرارداد را در صفحه فروش تطبیق کنید.');
                }
            }
            $lines = \Model::fetchAll('SELECT * FROM plugin_inventory_sale_lines WHERE sale_id = ? ORDER BY id ASC FOR UPDATE', [$saleId]);
            if (!$lines) { throw new \RuntimeException('ردیف کالای فروش پیدا نشد.'); }
            $returnNo = self::number('RET');
            $refund = $sale['sale_kind'] === 'cash' ? Money::integer($sale['total_sale_rial']) : 0;
            \Model::execute('INSERT INTO plugin_inventory_returns (return_no, sale_id, reason, refund_rial, refund_reference, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())', [
                $returnNo, $saleId, substr($reason, 0, 255), Money::db($refund), $refundReference ?: null, $actorId ? (int) $actorId : null,
            ]);
            $returnId = (int) \Model::lastInsertId();
            foreach ($lines as $line) {
                $quantity = (int) $line['quantity'] - (int) $line['returned_qty'];
                if ($quantity <= 0) { continue; }
                $lot = \Model::fetch('SELECT id, warehouse_id, unit_landed_cost_rial FROM plugin_inventory_stock_lots WHERE id = ? LIMIT 1 FOR UPDATE', [(int) $line['lot_id']]);
                if (!$lot) { throw new \RuntimeException('محموله فروش برگشتی پیدا نشد.'); }
                $cost = Money::integer($line['line_cost_rial']) - Money::integer($line['returned_cost_rial']);
                $returnRevenue = Money::integer($line['line_revenue_rial']);
                $remainder = Money::integer($line['cost_remainder_applied_rial']);
                \Model::execute('UPDATE plugin_inventory_stock_lots SET on_hand_qty = on_hand_qty + ?, cost_remainder_rial = cost_remainder_rial + ? WHERE id = ?', [$quantity, Money::db($remainder), (int) $line['lot_id']]);
                $saleShares = \Model::fetchAll('SELECT partner_id, capital_amount_rial FROM plugin_inventory_sale_partner_shares WHERE sale_line_id = ? ORDER BY partner_id ASC FOR UPDATE', [(int) $line['id']]);
                foreach ($saleShares as $share) {
                    $restoredCapital = Money::integer($share['capital_amount_rial']);
                    if ($restoredCapital > 0) {
                        \Model::execute('UPDATE plugin_inventory_lot_partner_shares SET remaining_capital_rial = remaining_capital_rial + ? WHERE lot_id = ? AND partner_id = ?', [
                            Money::db($restoredCapital), (int) $line['lot_id'], (int) $share['partner_id'],
                        ]);
                    }
                }
                \Model::execute('UPDATE plugin_inventory_sale_lines SET returned_qty = quantity, returned_cost_rial = line_cost_rial WHERE id = ?', [(int) $line['id']]);
                \Model::execute('UPDATE plugin_inventory_serial_items si JOIN plugin_inventory_sale_serial_items ss ON ss.serial_item_id = si.id SET si.status = \'in_stock\', si.sale_id = NULL, si.sold_at = NULL WHERE ss.sale_line_id = ? AND si.status = \'sold\'', [(int) $line['id']]);
                \Model::execute('INSERT INTO plugin_inventory_return_lines (return_id, sale_line_id, quantity, revenue_rial, cost_rial) VALUES (?, ?, ?, ?, ?)', [$returnId, (int) $line['id'], $quantity, Money::db($returnRevenue), Money::db($cost)]);
                PurchaseService::movement('customer_return', (int) $line['product_id'], (int) $line['lot_id'], null, (int) $lot['warehouse_id'], $quantity, Money::integer($lot['unit_landed_cost_rial']), 'return', $returnId, $reason, $actorId);
            }
            $journals = \Model::fetchAll("SELECT id FROM plugin_inventory_journals WHERE source_type = 'inventory_sale' AND source_id = ? ORDER BY id DESC FOR UPDATE", [$saleId]);
            foreach ($journals as $journal) {
                if (!\Model::fetch('SELECT id FROM plugin_inventory_journals WHERE reversal_of_id = ? LIMIT 1', [(int) $journal['id']])) {
                    LedgerService::reverse((int) $journal['id'], $actorId, 'برگشت فروش ' . $returnNo . ': ' . $reason);
                }
            }
            \Model::execute('UPDATE plugin_inventory_sale_partner_shares SET capital_returned_rial = 0, profit_released_rial = 0
                WHERE sale_line_id IN (SELECT id FROM plugin_inventory_sale_lines WHERE sale_id = ?)', [$saleId]);
            \Model::execute("UPDATE plugin_inventory_sales SET status = 'refunded', collected_principal_rial = 0, updated_at = NOW() WHERE id = ?", [$saleId]);
            AuditService::record('sale_returned', 'sale', $saleId, $actorId, ['status' => 'posted'], ['status' => 'refunded', 'return_no' => $returnNo, 'refund_rial' => Money::db($refund)]);
            \Model::commit();
            return $returnId;
        } catch (\Throwable $error) {
            \Model::rollBack();
            throw $error;
        }
    }

    private static function lockSerials(array $serials, $productId, $lotId, $warehouseId)
    {
        if (!$serials) { return []; }
        $placeholders = implode(',', array_fill(0, count($serials), '?'));
        $params = array_merge($serials, [(int) $productId, (int) $lotId, (int) $warehouseId]);
        $rows = \Model::fetchAll("SELECT id, serial_no FROM plugin_inventory_serial_items WHERE serial_no IN ($placeholders) AND product_id = ? AND lot_id = ? AND warehouse_id = ? AND status = 'in_stock' ORDER BY id ASC FOR UPDATE", $params);
        if (count($rows) !== count($serials)) {
            throw new \InvalidArgumentException('یک یا چند IMEI/Serial در این محموله موجود نیست یا قبلاً فروخته شده است.');
        }
        return $rows;
    }

    private static function serials($raw)
    {
        $items = is_array($raw) ? $raw : (preg_split('/\r\n|\r|\n|,|،/', (string) $raw) ?: []);
        $unique = [];
        foreach ($items as $item) {
            $item = trim((string) $item);
            if ($item === '') { continue; }
            if (strlen($item) > 120 || preg_match('/[\x00-\x1F]/', $item) || isset($unique[$item])) {
                throw new \InvalidArgumentException('IMEI/Serial خالی، تکراری یا نامعتبر است.');
            }
            $unique[$item] = $item;
        }
        return array_values($unique);
    }

    private static function number($prefix)
    {
        return strtoupper($prefix) . '-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(3)));
    }
}
