<?php

return [
    'app_name' => 'پرما پرداخت',
    'asset_version' => '1.0.4',
    'timezone' => 'Asia/Tehran',
    'base_url' => '',
    'session_name' => 'proma_pay_session',
    'roles' => [
        'admin' => 'مدیر',
        'operator' => 'اپراتور',
        'lawyer' => 'وکیل',
        'customer' => 'مشتری',
    ],
    'statuses' => [
        'active' => 'فعال',
        'inactive' => 'غیرفعال',
        'pending' => 'در انتظار پرداخت',
        'partial' => 'پرداخت جزئی',
        'paid' => 'پرداخت شده',
        'corrected' => 'اصلاح‌شده',
        'overdue' => 'سررسید گذشته',
        'failed' => 'ناموفق',
        'confirmed' => 'تأیید شده',
        'approved' => 'تأیید شده',
        'rejected' => 'رد شده',
        'uploaded' => 'بارگذاری شده',
        'previewed' => 'آماده تأیید',
        'raw' => 'خام',
        'open' => 'باز',
        'closed' => 'بسته',
        'referred' => 'ارجاع شده',
    ],
    'payment_methods' => [
        'manual' => 'پرداخت دستی',
        'zibal' => 'درگاه زیبال',
    ],
];
