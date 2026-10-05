<?php

namespace App\Support;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;

class PaymentLabels
{
    public static function method(PaymentMethod $method): string
    {
        return match ($method) {
            PaymentMethod::Cash => 'نقدی',
            PaymentMethod::Card => 'کارت',
            PaymentMethod::Pos => 'کارت‌خوان',
            PaymentMethod::BankTransfer => 'انتقال بانکی',
            PaymentMethod::OnlineGateway => 'درگاه آنلاین',
            PaymentMethod::Wallet => 'کیف پول',
            PaymentMethod::Manual => 'ثبت دستی',
        };
    }

    public static function status(PaymentStatus $status): string
    {
        return match ($status) {
            PaymentStatus::Pending => 'در انتظار',
            PaymentStatus::Paid => 'پرداخت‌شده',
            PaymentStatus::Failed => 'ناموفق',
            PaymentStatus::Reversed => 'برگشت‌خورده',
        };
    }
}
