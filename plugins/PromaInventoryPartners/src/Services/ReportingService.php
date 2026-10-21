<?php

namespace Proma\Plugins\InventoryPartners\Services;

use Proma\Plugins\InventoryPartners\Domain\Money;

final class ReportingService
{
    public static function dashboard($includeCosts = false)
    {
        $inventory = \Model::fetch('SELECT COALESCE(SUM(l.on_hand_qty), 0) AS units,
                COALESCE(SUM(l.reserved_qty), 0) AS reserved_units,
                COALESCE(SUM(CAST(l.unit_landed_cost_rial AS DECIMAL(40,0)) * CAST(l.on_hand_qty AS DECIMAL(10,0))
                    + CAST(l.cost_remainder_rial AS DECIMAL(40,0))), 0) AS stock_cost_rial,
                COALESCE(SUM(CAST(p.market_price_rial AS DECIMAL(40,0)) * CAST(l.on_hand_qty AS DECIMAL(10,0))), 0) AS market_value_rial,
                COALESCE(SUM(CAST(p.sale_price_rial AS DECIMAL(40,0)) * CAST(l.on_hand_qty AS DECIMAL(10,0))), 0) AS planned_sale_value_rial
            FROM plugin_inventory_stock_lots l JOIN plugin_inventory_products p ON p.id = l.product_id
            WHERE l.status = \'active\' AND p.status = \'active\'');
        $cost = Money::integer($inventory['stock_cost_rial'] ?? '0');
        $market = Money::integer($inventory['market_value_rial'] ?? '0');
        $saleValue = Money::integer($inventory['planned_sale_value_rial'] ?? '0');
        $units = (int) ($inventory['units'] ?? 0);
        $reserved = (int) ($inventory['reserved_units'] ?? 0);
        $sales = \Model::fetch('SELECT COUNT(*) AS total, COALESCE(SUM(total_sale_rial),0) AS value, COALESCE(SUM(net_profit_rial),0) AS profit
            FROM plugin_inventory_sales WHERE status = \'posted\' AND sold_at >= DATE_FORMAT(NOW(), \'%Y-%m-01 00:00:00\')');
        $allSales = \Model::fetch('SELECT COUNT(*) AS total, COALESCE(SUM(net_profit_rial),0) AS profit FROM plugin_inventory_sales WHERE status = \'posted\'');
        $lowStock = \Model::fetch('SELECT COUNT(*) AS total FROM (
              SELECT p.id, p.minimum_stock, COALESCE(SUM(l.on_hand_qty - l.reserved_qty),0) AS available
              FROM plugin_inventory_products p LEFT JOIN plugin_inventory_stock_lots l ON l.product_id = p.id AND l.status = \'active\'
              WHERE p.status = \'active\' GROUP BY p.id, p.minimum_stock
            ) stock_rows WHERE minimum_stock > 0 AND available <= minimum_stock');
        $idle = \Model::fetch('SELECT COUNT(DISTINCT l.product_id) AS total FROM plugin_inventory_stock_lots l
            WHERE l.on_hand_qty > 0 AND l.entered_at < DATE_SUB(NOW(), INTERVAL 90 DAY)
            AND NOT EXISTS (SELECT 1 FROM plugin_inventory_sale_lines sl JOIN plugin_inventory_sales s ON s.id = sl.sale_id WHERE sl.product_id = l.product_id AND s.status = \'posted\' AND s.sold_at >= DATE_SUB(NOW(), INTERVAL 90 DAY))');
        $summary = [
            'units' => $units,
            'reserved_units' => $reserved,
            'low_stock_count' => (int) ($lowStock['total'] ?? 0),
            'idle_products' => (int) ($idle['total'] ?? 0),
            'sales_month_count' => (int) ($sales['total'] ?? 0),
        ];
        if ($includeCosts) {
            $summary['sales_month_rial'] = (string) ($sales['value'] ?? '0');
            $summary['realized_profit_month_rial'] = (string) ($sales['profit'] ?? '0');
            $summary['realized_profit_total_rial'] = (string) ($allSales['profit'] ?? '0');
            $summary['stock_cost_rial'] = (string) $cost;
            $summary['market_value_rial'] = (string) $market;
            $summary['planned_sale_value_rial'] = (string) $saleValue;
            $summary['potential_profit_rial'] = (string) ($saleValue - $cost);
            $partnerBalances = \Model::fetch("SELECT COALESCE(SUM(CAST(credit_rial AS DECIMAL(20,0)) - CAST(debit_rial AS DECIMAL(20,0))),0) AS total FROM plugin_inventory_journal_lines WHERE account_code IN ('partner_capital_payable','partner_profit_payable')");
            $summary['partners_withdrawable_rial'] = (string) ($partnerBalances['total'] ?? '0');
            $capitalAtRisk = \Model::fetch('SELECT COALESCE(SUM(remaining_capital_rial),0) AS stock FROM plugin_inventory_lot_partner_shares');
            $soldAtRisk = \Model::fetch("SELECT COALESCE(SUM(ps.capital_amount_rial - ps.capital_returned_rial),0) AS sold
                FROM plugin_inventory_sale_partner_shares ps JOIN plugin_inventory_sale_lines sl ON sl.id = ps.sale_line_id
                JOIN plugin_inventory_sales s ON s.id = sl.sale_id WHERE s.status = 'posted'");
            $summary['partners_capital_at_risk_rial'] = (string) ((int) ($capitalAtRisk['stock'] ?? 0) + (int) ($soldAtRisk['sold'] ?? 0));
        }
        return $summary;
    }

    public static function products($query = '', $includeCosts = false)
    {
        $query = trim((string) $query);
        $where = '';
        $params = [];
        if ($query !== '') {
            $where = 'WHERE (p.name LIKE ? OR p.sku LIKE ? OR p.barcode LIKE ? OR p.brand LIKE ? OR p.model LIKE ?)';
            $like = '%' . $query . '%';
            $params = [$like, $like, $like, $like, $like];
        }
        $financial = $includeCosts
            ? ', p.reference_purchase_price_rial, p.market_price_rial, p.sale_price_rial, p.minimum_sale_price_rial'
            : ', NULL AS reference_purchase_price_rial, NULL AS market_price_rial, NULL AS sale_price_rial, NULL AS minimum_sale_price_rial';
        return \Model::fetchAll(
            'SELECT p.id, p.sku, p.barcode, p.name, p.product_type, COALESCE(c.name, p.category) AS category, p.category_id, COALESCE(b.name, p.brand) AS brand, p.brand_id, p.model, p.color, p.capacity, p.ram, p.tracking_mode, p.status, p.minimum_stock, p.created_at' . $financial . ',
             COALESCE(SUM(l.on_hand_qty - l.reserved_qty),0) AS available_qty, COALESCE(SUM(l.reserved_qty),0) AS reserved_qty
             FROM plugin_inventory_products p LEFT JOIN plugin_inventory_brands b ON b.id = p.brand_id
             LEFT JOIN plugin_inventory_categories c ON c.id = p.category_id
             LEFT JOIN plugin_inventory_stock_lots l ON l.product_id = p.id AND l.status = \'active\'
             ' . $where . ' GROUP BY p.id ORDER BY p.id DESC LIMIT 300',
            $params
        );
    }

    public static function purchases($includeCosts = false)
    {
        $cost = $includeCosts ? ', h.total_rial, h.expenses_rial' : ', NULL AS total_rial, NULL AS expenses_rial';
        return \Model::fetchAll('SELECT h.id, h.purchase_no, h.invoice_no, h.purchase_date, h.status, h.note' . $cost . ', s.name AS supplier_name, w.name AS warehouse_name,
            GROUP_CONCAT(CONCAT(p.name, \' × \', pl.quantity) ORDER BY pl.id SEPARATOR \'، \') AS items
            FROM plugin_inventory_purchase_headers h
            LEFT JOIN plugin_inventory_suppliers s ON s.id = h.supplier_id
            JOIN plugin_inventory_warehouses w ON w.id = h.warehouse_id
            LEFT JOIN plugin_inventory_purchase_lines pl ON pl.purchase_id = h.id
            LEFT JOIN plugin_inventory_products p ON p.id = pl.product_id
            GROUP BY h.id ORDER BY h.id DESC LIMIT 250');
    }

    public static function sales($includeCosts = false)
    {
        $financial = $includeCosts
            ? 's.total_sale_rial, s.collected_principal_rial, s.total_cost_rial, s.selling_expenses_rial, s.net_profit_rial, l.unit_sale_price_rial, l.line_cost_rial'
            : 'NULL AS total_sale_rial, NULL AS collected_principal_rial, NULL AS total_cost_rial, NULL AS selling_expenses_rial, NULL AS net_profit_rial, NULL AS unit_sale_price_rial, NULL AS line_cost_rial';
        return \Model::fetchAll('SELECT s.id, s.sale_no, s.sale_kind, s.contract_id, s.customer_name, s.sold_at, s.status, ' . $financial . ',
                p.name AS product_name, l.quantity
            FROM plugin_inventory_sales s JOIN plugin_inventory_sale_lines l ON l.sale_id = s.id JOIN plugin_inventory_products p ON p.id = l.product_id
            ORDER BY s.id DESC LIMIT 250');
    }

    public static function partners($includeFinancial = true)
    {
        if (!$includeFinancial) {
            return \Model::fetchAll('SELECT id, partner_code, full_name, phone, status FROM plugin_inventory_partners ORDER BY status ASC, full_name ASC LIMIT 300');
        }
        return \Model::fetchAll("SELECT p.id, p.partner_code, p.full_name, p.phone, p.status, p.default_capital_share_bp, p.default_profit_share_bp,
                COALESCE(c.total_rial, 0) AS capital_contributions_rial,
                COALESCE(st.total_rial, 0) + COALESCE(sc.total_rial, 0) AS capital_at_risk_rial,
                COALESCE(pr.total_rial, 0) AS recognized_profit_rial,
                COALESCE(pr.released_rial, 0) AS collected_profit_rial,
                COALESCE(pay.balance_rial, 0) AS withdrawable_rial
            FROM plugin_inventory_partners p
            LEFT JOIN (SELECT partner_id, SUM(amount_rial) AS total_rial FROM plugin_inventory_capital_contributions GROUP BY partner_id) c ON c.partner_id = p.id
            LEFT JOIN (SELECT partner_id, SUM(remaining_capital_rial) AS total_rial FROM plugin_inventory_lot_partner_shares GROUP BY partner_id) st ON st.partner_id = p.id
            LEFT JOIN (
                SELECT ps.partner_id, SUM(ps.capital_amount_rial - ps.capital_returned_rial) AS total_rial
                FROM plugin_inventory_sale_partner_shares ps
                JOIN plugin_inventory_sale_lines sl ON sl.id = ps.sale_line_id
                JOIN plugin_inventory_sales s ON s.id = sl.sale_id AND s.status = 'posted'
                GROUP BY ps.partner_id
            ) sc ON sc.partner_id = p.id
            LEFT JOIN (
                SELECT ps.partner_id, SUM(ps.profit_amount_rial) AS total_rial, SUM(ps.profit_released_rial) AS released_rial
                FROM plugin_inventory_sale_partner_shares ps
                JOIN plugin_inventory_sale_lines sl ON sl.id = ps.sale_line_id
                JOIN plugin_inventory_sales s ON s.id = sl.sale_id AND s.status = 'posted'
                GROUP BY ps.partner_id
            ) pr ON pr.partner_id = p.id
            LEFT JOIN (
                SELECT partner_id, SUM(CAST(credit_rial AS DECIMAL(20,0)) - CAST(debit_rial AS DECIMAL(20,0))) AS balance_rial
                FROM plugin_inventory_journal_lines
                WHERE account_code IN ('partner_capital_payable', 'partner_profit_payable')
                GROUP BY partner_id
            ) pay ON pay.partner_id = p.id
            ORDER BY p.status ASC, p.full_name ASC LIMIT 300");
    }

    public static function partnerLedger($partnerId)
    {
        return PartnerService::ledger((int) $partnerId);
    }

    public static function settlements($includeCosts = true)
    {
        $amount = $includeCosts ? 'r.amount_rial' : 'NULL AS amount_rial';
        return \Model::fetchAll('SELECT r.id, r.request_no, r.partner_id, ' . $amount . ', r.status, r.payout_method, r.payment_reference, r.reason, r.created_at, r.approved_at, r.paid_at, p.full_name AS partner_name,
                ur.full_name AS requested_name, ua.full_name AS approved_name, up.full_name AS paid_name
            FROM plugin_inventory_settlement_requests r JOIN plugin_inventory_partners p ON p.id = r.partner_id
            LEFT JOIN users ur ON ur.id = r.requested_by LEFT JOIN users ua ON ua.id = r.approved_by LEFT JOIN users up ON up.id = r.paid_by
            ORDER BY r.id DESC LIMIT 250');
    }

    public static function transfers()
    {
        return StockService::transfers(200);
    }

    public static function profitLoss($from = '', $to = '')
    {
        $where = "WHERE status = 'posted'";
        $params = [];
        if ($from !== '') { $where .= ' AND sold_at >= ?'; $params[] = $from . ' 00:00:00'; }
        if ($to !== '') { $where .= ' AND sold_at < DATE_ADD(?, INTERVAL 1 DAY)'; $params[] = $to; }
        $row = \Model::fetch('SELECT COUNT(*) AS sales_count, COALESCE(SUM(total_sale_rial),0) AS revenue_rial, COALESCE(SUM(total_cost_rial),0) AS cogs_rial,
                COALESCE(SUM(selling_expenses_rial),0) AS selling_expenses_rial, COALESCE(SUM(net_profit_rial),0) AS sales_net_profit_rial,
                COALESCE(SUM(collected_principal_rial),0) AS collected_principal_rial,
                COALESCE(SUM(CASE WHEN total_sale_rial > collected_principal_rial THEN total_sale_rial - collected_principal_rial ELSE 0 END),0) AS uncollected_principal_rial
            FROM plugin_inventory_sales ' . $where, $params) ?: [];
        $expenseWhere = "WHERE e.status = 'posted'";
        $expenseParams = [];
        if ($from !== '') { $expenseWhere .= ' AND expense_date >= ?'; $expenseParams[] = $from; }
        if ($to !== '') { $expenseWhere .= ' AND expense_date <= ?'; $expenseParams[] = $to; }
        $expenses = \Model::fetch('SELECT COALESCE(SUM(e.amount_rial),0) AS operating_expenses_rial FROM plugin_inventory_expenses e ' . $expenseWhere, $expenseParams) ?: [];
        $partnerExpenses = \Model::fetchAll('SELECT p.id AS partner_id, p.full_name AS partner_name, COALESCE(SUM(a.amount_rial),0) AS amount_rial
            FROM plugin_inventory_expense_partner_allocations a JOIN plugin_inventory_expenses e ON e.id = a.expense_id
            JOIN plugin_inventory_partners p ON p.id = a.partner_id ' . str_replace('expense_date', 'e.expense_date', $expenseWhere) . '
            GROUP BY p.id, p.full_name ORDER BY p.full_name ASC', $expenseParams);
        $operatingExpenses = Money::integer($expenses['operating_expenses_rial'] ?? '0');
        $salesProfit = Money::integer($row['sales_net_profit_rial'] ?? '0');
        $sellingExpenses = Money::integer($row['selling_expenses_rial'] ?? '0');
        $row['expenses_rial'] = (string) $sellingExpenses;
        $row['operating_expenses_rial'] = (string) $operatingExpenses;
        $row['total_expenses_rial'] = (string) Money::add($sellingExpenses, $operatingExpenses);
        $row['net_profit_rial'] = (string) ($salesProfit - $operatingExpenses);
        $row['partner_expenses'] = $partnerExpenses;
        return $row;
    }

    public static function priceHistory($productId = 0)
    {
        $where = $productId > 0 ? 'WHERE h.product_id = ?' : '';
        $params = $productId > 0 ? [(int) $productId] : [];
        return \Model::fetchAll('SELECT h.*, p.sku, p.name AS product_name, u.full_name AS changed_by_name FROM plugin_inventory_price_history h
            JOIN plugin_inventory_products p ON p.id = h.product_id LEFT JOIN users u ON u.id = h.changed_by ' . $where . ' ORDER BY h.id DESC LIMIT 250', $params);
    }

    public static function activity()
    {
        return \Model::fetchAll('SELECT a.*, u.full_name AS actor_name FROM plugin_inventory_audit_logs a LEFT JOIN users u ON u.id = a.actor_user_id ORDER BY a.id DESC LIMIT 300');
    }

    public static function warehouses()
    {
        return \Model::fetchAll('SELECT w.*, u.full_name AS manager_name FROM plugin_inventory_warehouses w LEFT JOIN users u ON u.id = w.manager_user_id ORDER BY w.status, w.name');
    }

    public static function suppliers()
    {
        return \Model::fetchAll('SELECT * FROM plugin_inventory_suppliers ORDER BY status, name');
    }

    public static function activeProducts()
    {
        return \Model::fetchAll("SELECT id, sku, name, tracking_mode, sale_price_rial, minimum_sale_price_rial FROM plugin_inventory_products WHERE status = 'active' ORDER BY name ASC LIMIT 300");
    }

    public static function searchActiveProducts($query = '', $limit = 30)
    {
        $query = trim((string) $query);
        $limit = max(1, min(50, (int) $limit));
        $where = "p.status = 'active'";
        $params = [];
        if ($query !== '') {
            $term = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $query) . '%';
            $where .= " AND (p.sku LIKE ? ESCAPE '!' OR p.barcode LIKE ? ESCAPE '!' OR p.name LIKE ? ESCAPE '!' OR p.model LIKE ? ESCAPE '!' OR b.name LIKE ? ESCAPE '!')";
            $params = [$term, $term, $term, $term, $term];
        }
        $exact = $query;
        $params[] = $exact;
        $params[] = $exact;
        $params[] = $limit;
        return \Model::fetchAll('SELECT p.id, p.sku, p.barcode, p.name, p.product_type, p.model, p.color, p.capacity, p.ram,
                p.tracking_mode, p.sale_price_rial, COALESCE(b.name, p.brand) AS brand,
                COALESCE(c.name, p.category) AS category, COALESCE(SUM(l.on_hand_qty - l.reserved_qty),0) AS available_qty
            FROM plugin_inventory_products p
            LEFT JOIN plugin_inventory_brands b ON b.id = p.brand_id
            LEFT JOIN plugin_inventory_categories c ON c.id = p.category_id
            LEFT JOIN plugin_inventory_stock_lots l ON l.product_id = p.id AND l.status = \'active\'
            WHERE ' . $where . ' GROUP BY p.id
            ORDER BY CASE WHEN p.sku = ? THEN 0 WHEN p.barcode = ? THEN 1 ELSE 2 END, p.name ASC LIMIT ?', $params);
    }

    public static function searchAvailableContracts($query = '')
    {
        $query = trim((string) $query);
        $where = "c.status IN ('active','pending') AND NOT EXISTS (SELECT 1 FROM plugin_inventory_sales s WHERE s.contract_id = c.id)";
        $params = [];
        if ($query !== '') {
            $term = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $query) . '%';
            $where .= ' AND (u.full_name LIKE ? OR u.mobile LIKE ? OR CAST(c.id AS CHAR) = ?)';
            $params = [$term, $term, $query];
        }
        $params[] = 30;
        return \Model::fetchAll('SELECT c.id, c.principal_amount, c.status, u.full_name AS customer_name, u.mobile AS customer_phone
            FROM contracts c JOIN users u ON u.id = c.customer_id WHERE ' . $where . ' ORDER BY c.id DESC LIMIT ?', $params);
    }

    public static function expenses($from = '', $to = '')
    {
        $where = "WHERE e.status = 'posted'";
        $params = [];
        if ($from !== '') { $where .= ' AND e.expense_date >= ?'; $params[] = $from; }
        if ($to !== '') { $where .= ' AND e.expense_date <= ?'; $params[] = $to; }
        return \Model::fetchAll('SELECT e.id, e.expense_no, e.expense_type, e.expense_date, e.amount_rial, e.payment_method,
                e.payment_reference, e.note, e.status, e.journal_id, e.created_at
            FROM plugin_inventory_expenses e ' . $where . ' ORDER BY e.expense_date DESC, e.id DESC LIMIT 250', $params);
    }

    public static function expenseAllocations(array $expenseIds)
    {
        $expenseIds = array_values(array_unique(array_filter(array_map('intval', $expenseIds), static function ($id) { return $id > 0; })));
        if (!$expenseIds) { return []; }
        $placeholders = implode(',', array_fill(0, count($expenseIds), '?'));
        $rows = \Model::fetchAll('SELECT a.expense_id, a.partner_id, a.share_bp, a.amount_rial, p.full_name AS partner_name
            FROM plugin_inventory_expense_partner_allocations a JOIN plugin_inventory_partners p ON p.id = a.partner_id
            WHERE a.expense_id IN (' . $placeholders . ') ORDER BY a.expense_id ASC, a.partner_id ASC', $expenseIds);
        $map = [];
        foreach ($rows as $row) { $map[(int) $row['expense_id']][] = $row; }
        return $map;
    }

    public static function activeWarehouses()
    {
        return \Model::fetchAll("SELECT id, code, name FROM plugin_inventory_warehouses WHERE status = 'active' ORDER BY name ASC");
    }

    public static function settings()
    {
        $rows = \Model::fetchAll('SELECT setting_key, setting_value FROM plugin_inventory_settings ORDER BY setting_key');
        $settings = [];
        foreach ($rows as $row) { $settings[(string) $row['setting_key']] = (string) $row['setting_value']; }
        return $settings;
    }
}
