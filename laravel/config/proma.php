<?php

return [
    'currency_code' => env('PROMA_CURRENCY', 'TMN'),
    'currency_label' => 'تومان',
    'payment_receipt_temporary_url_ttl_seconds' => (int) env('PROMA_PAYMENT_RECEIPT_TEMPORARY_URL_TTL_SECONDS', 120),
];
