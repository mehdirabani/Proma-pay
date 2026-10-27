-- Read-only snapshot for zero-copy migration verification.
-- Run once before and once after cutover against the same database/schema.
-- Export every result set with a timestamp; do not compare snapshots if writes
-- occurred between them without reconciling the cutover window separately.
START TRANSACTION READ ONLY;

SELECT 'catalog' AS section,
       (SELECT COUNT(*) FROM plugin_inventory_products) AS products,
       (SELECT COUNT(*) FROM plugin_inventory_warehouses) AS warehouses,
       (SELECT COUNT(*) FROM plugin_inventory_suppliers) AS suppliers,
       (SELECT COUNT(*) FROM plugin_inventory_partners) AS partners;

SELECT 'lots' AS section, COUNT(*) AS lots,
       COALESCE(SUM(on_hand_qty), 0) AS on_hand_qty,
       COALESCE(SUM(reserved_qty), 0) AS reserved_qty,
       COALESCE(SUM(CAST(unit_landed_cost_rial AS DECIMAL(40,0)) * CAST(on_hand_qty AS DECIMAL(10,0)) + CAST(cost_remainder_rial AS DECIMAL(40,0))), 0) AS inventory_cost_rial
FROM plugin_inventory_stock_lots;

SELECT 'serials' AS section, status, COUNT(*) AS serial_count
FROM plugin_inventory_serial_items GROUP BY status ORDER BY status;

SELECT 'movements' AS section, movement_type, COUNT(*) AS rows_count,
       COALESCE(SUM(quantity), 0) AS quantity
FROM plugin_inventory_stock_movements GROUP BY movement_type ORDER BY movement_type;

SELECT 'purchases' AS section, status, COUNT(*) AS purchases,
       COALESCE(SUM(total_rial), 0) AS total_rial,
       COALESCE(SUM(expenses_rial), 0) AS expenses_rial
FROM plugin_inventory_purchase_headers GROUP BY status ORDER BY status;

SELECT 'sales' AS section, status, sale_kind, COUNT(*) AS sales,
       COALESCE(SUM(total_sale_rial), 0) AS sale_rial,
       COALESCE(SUM(total_cost_rial), 0) AS cost_rial,
       COALESCE(SUM(net_profit_rial), 0) AS profit_rial,
       COALESCE(SUM(collected_principal_rial), 0) AS collected_principal_rial
FROM plugin_inventory_sales GROUP BY status, sale_kind ORDER BY status, sale_kind;

SELECT 'returns' AS section, COUNT(*) AS returns,
       COALESCE(SUM(refund_rial), 0) AS refund_rial
FROM plugin_inventory_returns;

SELECT 'return_lines' AS section, COUNT(*) AS return_lines,
       COALESCE(SUM(quantity), 0) AS returned_qty,
       COALESCE(SUM(revenue_rial), 0) AS revenue_rial,
       COALESCE(SUM(cost_rial), 0) AS cost_rial
FROM plugin_inventory_return_lines;

SELECT 'capital' AS section, COUNT(*) AS contributions,
       COALESCE(SUM(amount_rial), 0) AS amount_rial
FROM plugin_inventory_capital_contributions;

SELECT 'journal_totals' AS section, COUNT(*) AS journal_lines,
       COALESCE(SUM(debit_rial), 0) AS debit_rial,
       COALESCE(SUM(credit_rial), 0) AS credit_rial
FROM plugin_inventory_journal_lines;

SELECT 'unbalanced_journals' AS section, COUNT(*) AS unbalanced_count
FROM (
    SELECT journal_id
    FROM plugin_inventory_journal_lines
    GROUP BY journal_id
    HAVING SUM(debit_rial) <> SUM(credit_rial)
) AS unbalanced;

SELECT 'payment_allocations' AS section, state, COUNT(*) AS allocation_count,
       COALESCE(SUM(principal_rial), 0) AS principal_rial,
       COALESCE(SUM(applied_capital_rial), 0) AS applied_capital_rial,
       COALESCE(SUM(applied_profit_rial), 0) AS applied_profit_rial
FROM plugin_inventory_payment_allocations GROUP BY state ORDER BY state;

SELECT 'expenses' AS section, status, COUNT(*) AS expense_count,
       COALESCE(SUM(amount_rial), 0) AS amount_rial
FROM plugin_inventory_expenses GROUP BY status ORDER BY status;

SELECT 'settlements' AS section, status, COUNT(*) AS settlement_count,
       COALESCE(SUM(amount_rial), 0) AS amount_rial
FROM plugin_inventory_settlement_requests GROUP BY status ORDER BY status;

COMMIT;
