<?php

/** Replays reviewed, idempotent compatibility migrations after a legacy restore. */
final class RestoreSchemaService
{
    public static function repair(callable $executeSql)
    {
        $names = [
            '2026_07_26_installment_settlement_engine.sql',
            '2026_10_01_group_payment_allocation_scope.sql',
            '2026_10_02_gateway_reconciliation_queue.sql',
            '2026_10_03_contract_guarantee_amount.sql',
            '2026_10_04_customer_360_automation.sql',
            '2026_10_05_contract_sales_attribution.sql',
            '2026_10_06_v4_restored_backup_schema_repair.sql',
            '2026_10_08_customer_loyalty_points.sql',
            '2026_10_08_customer_password_mode.sql',
        ];
        foreach ($names as $name) {
            $sql = file_get_contents(dirname(__DIR__) . '/database/migrations/' . $name);
            if (!is_string($sql) || trim($sql) === '') throw new RuntimeException('فایل سازگاری بازیابی موجود نیست: ' . $name);
            // Do not trust the imported migration ledger: a backup can say
            // success while its actual columns are incomplete.
            $executeSql($sql);
        }
        SchemaGuard::clearCache();
        SchemaGuard::requireColumns('payment_requests', ['contract_id', 'payment_group_id', 'quote_uuid', 'selection_json', 'response_code', 'error_message', 'updated_at']);
        SchemaGuard::requireColumns('payment_groups', ['allocation_scope']);
        SchemaGuard::requireColumns('system_outbox', ['event_type', 'max_attempts', 'next_attempt_at']);
        SchemaGuard::requireColumns('users', ['customer_password_mode']);
    }
}
