<?php
declare(strict_types=1);

$view = file_get_contents(__DIR__ . '/../views/installments/index.php');
$dashboard = file_get_contents(__DIR__ . '/../views/dashboard/customer.php');
$styles = file_get_contents(__DIR__ . '/../assets/css/components/v2-system.css');

foreach ([
    'customer installment cards rendered from server financial values' => str_contains($view, 'proma-customer-installment-grid') && str_contains($view, 'money_toman($item[\'payable\'])'),
    'customer card preserves the existing payment modal action' => str_contains($view, 'data-open-modal="pay-<?= (int) $item[\'id\'] ?>"'),
    'operator installment grid remains a responsive labeled table' => str_contains($view, 'proma-installment-list-table') && str_contains($view, 'data-label="عملیات"'),
    'customer dashboard contract table has mobile labels' => str_contains($dashboard, 'proma-customer-contract-table') && str_contains($dashboard, 'data-label="شماره قرارداد"'),
    'customer dashboard schedule table has mobile labels' => str_contains($dashboard, 'proma-customer-schedule-table') && str_contains($dashboard, 'data-label="قابل پرداخت"'),
    'mobile cells allow Persian names and financial strings to wrap without clipping' => str_contains($styles, 'overflow-wrap: anywhere; word-break: normal; writing-mode: horizontal-tb'),
    'customer installment layout collapses to one card column on phones' => str_contains($styles, 'proma-customer-installment-grid { grid-template-columns: minmax(0, 1fr)'),
] as $name => $passed) {
    if (!$passed) {
        fwrite(STDERR, "FAIL: {$name}\n");
        exit(1);
    }
}

echo "STATIC_V204_MOBILE_CUSTOMER_INSTALLMENTS_OK\n";
