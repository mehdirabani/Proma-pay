<?php

declare(strict_types=1);

namespace Proma\Plugins\SignConnect\Services;

final class ProviderCapabilityCatalog
{
    /** @return array<string,array<string,string>> */
    public static function grouped(string $provider): array
    {
        $message = [
            'text_message' => 'ارسال پیام متنی',
            'otp_message' => 'ارسال رمز یک‌بارمصرف',
            'custom_message' => 'ارسال پیام عمومی',
        ];

        if ($provider === 'ippanel') {
            $message['pattern_message'] = 'ارسال پیام الگو/پترن';
        } elseif ($provider === 'smsir') {
            $message['verify_message'] = 'ارسال پیام قالب احراز هویت';
        } elseif (in_array($provider, ['bale', 'telegram'], true)) {
            $message['inline_buttons'] = 'ارسال دکمه‌های تعاملی';
            $message['multimedia'] = 'ارسال فایل و محتوای چندرسانه‌ای';
        }

        return [
            'انواع پیام' => $message,
            'قرارداد و امضا' => [
                'contract_created' => 'ثبت قرارداد جدید',
                'contract_signature_request' => 'درخواست امضای قرارداد',
                'contract_signature_reminder' => 'یادآوری امضای قرارداد',
                'contract_signed' => 'تکمیل امضای قرارداد',
            ],
            'اقساط و پرداخت' => [
                'installment_upcoming' => 'نزدیک شدن موعد قسط',
                'installment_due' => 'رسیدن موعد پرداخت قسط',
                'installment_overdue' => 'معوق شدن قسط',
                'payment_completed' => 'ثبت پرداخت موفق',
                'contract_settled' => 'تسویه کامل قرارداد',
            ],
            'حقوقی و امنیتی' => [
                'pre_legal_warning' => 'اخطار پیش از اقدام حقوقی',
                'legal_case_created' => 'تشکیل پرونده حقوقی',
                'security_otp' => 'کد امنیتی و ورود',
            ],
        ];
    }

    /** @return list<string> */
    public static function keys(string $provider): array
    {
        $keys = [];
        foreach (self::grouped($provider) as $items) {
            $keys = array_merge($keys, array_keys($items));
        }
        return array_values(array_unique($keys));
    }

    public static function resolve(string $provider,string $eventOrTemplate): string
    {
        $map=[
            'account_login_otp'=>'otp_message','otp'=>'otp_message',
            'signature_requested'=>'contract_signature_request','signature_reminder'=>'contract_signature_reminder',
            'signature_completed'=>'contract_signed','installment_due_today'=>'installment_due',
        ];
        $candidate=$map[$eventOrTemplate]??$eventOrTemplate;
        if($provider==='bale'&&in_array($candidate,BaleCapabilityCatalog::keys(),true))return$candidate;
        return in_array($candidate,self::keys($provider),true)?$candidate:'text_message';
    }

    /** @param mixed $value @return list<string> */
    public static function normalize(string $provider, $value): array
    {
        $requested = is_array($value) ? $value : preg_split('/\s*,\s*/', (string) $value, -1, PREG_SPLIT_NO_EMPTY);
        $allowed = array_flip(self::keys($provider));
        return array_values(array_unique(array_filter(array_map('strval', $requested ?: []), static fn (string $key): bool => isset($allowed[$key]))));
    }
}
