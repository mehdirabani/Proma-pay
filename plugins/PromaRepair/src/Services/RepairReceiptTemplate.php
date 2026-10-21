<?php

namespace Proma\Plugins\Repair\Services;

final class RepairReceiptTemplate
{
    public static function defaultTemplate(): string
    {
        return '<article class="repair-document">'
            . '<header class="repair-doc-header"><div class="repair-doc-brand">{{logo_html}}<div><strong>{{business_name}}</strong><span>{{contact}}</span><span>{{address}}</span></div></div>'
            . '<div class="repair-doc-code"><small>{{copy_label}}</small><strong>{{tracking_code}}</strong><span>{{repair_number}}</span><span>{{accepted_at}}</span>{{qr_html}}</div></header>'
            . '<section class="repair-doc-section"><h2>مشخصات مشتری و پذیرش</h2><dl class="repair-doc-facts"><div><dt>مشتری</dt><dd>{{customer_name}}</dd></div><div><dt>شماره تماس</dt><dd>{{customer_mobile}}</dd></div><div><dt>وضعیت پرونده</dt><dd>{{status}}</dd></div><div><dt>زمان تحویل تقریبی</dt><dd>{{estimated_delivery}}</dd></div></dl></section>'
            . '<section class="repair-doc-section"><h2>مشخصات دستگاه</h2><dl class="repair-doc-facts"><div><dt>نوع و مدل</dt><dd>{{device}}</dd></div><div><dt>شناسه دستگاه</dt><dd>{{device_identifiers}}</dd></div><div><dt>لوازم همراه</dt><dd>{{accessories}}</dd></div><div><dt>وضعیت ظاهری</dt><dd>{{conditions}}</dd></div></dl><div class="repair-doc-note"><strong>شرح خرابی اعلام‌شده</strong><p>{{issue_summary}}</p></div><div class="repair-doc-note"><strong>یادداشت پذیرش</strong><p>{{condition_notes}}</p></div></section>'
            . '<section class="repair-doc-section repair-doc-financial"><h2>هزینه و ضمانت</h2>{{financial_summary}}<p><strong>مدت ضمانت تعمیر:</strong> {{warranty}}</p></section>'
            . '<section class="repair-doc-terms">{{receipt_terms_html}}{{receipt_union_html}}</section>'
            . '{{signatures_html}}<footer class="repair-doc-footer"><span>{{footer}}</span><span>برای پیگیری، کد رهگیری را نگه دارید.</span></footer>'
            . '</article>';
    }

    public static function isLegacyDefault(string $template): bool
    {
        $legacy = '<p><strong>{{copy_label}}</strong></p>'
            . '{{logo_html}}<h1>{{business_name}}</h1><p>{{contact}}</p><p>{{address}}</p>'
            . '<h2>رسید پذیرش تعمیر</h2><p><strong>کد رهگیری:</strong> {{tracking_code}}</p>{{qr_html}}'
            . '<p><strong>شماره پرونده:</strong> {{repair_number}} · <strong>تاریخ پذیرش:</strong> {{accepted_at}}</p>'
            . '<p><strong>مشتری:</strong> {{customer_name}} · <strong>موبایل:</strong> {{customer_mobile}}</p>'
            . '<p><strong>دستگاه:</strong> {{device}} · <strong>وضعیت:</strong> {{status}}</p>'
            . '<p><strong>زمان تحویل تخمینی:</strong> {{estimated_delivery}}</p>'
            . '<p><strong>لوازم همراه:</strong> {{accessories}}</p><p><strong>وضعیت ظاهری:</strong> {{conditions}}</p>'
            . '<p><strong>شرح خرابی:</strong> {{issue_summary}}</p><p><strong>یادداشت وضعیت دستگاه:</strong> {{condition_notes}}</p>'
            . '{{financial_summary}}<p><strong>گارانتی:</strong> {{warranty}}</p>{{signatures_html}}'
            . '{{receipt_terms_html}}{{receipt_union_html}}<p>{{footer}}</p>';
        return trim($template) === $legacy;
    }

    public static function render(string $template, array $variables, array $rawKeys = []): string
    {
        if (trim($template) === '') {
            $template = self::defaultTemplate();
        }
        $rawKeys = array_fill_keys($rawKeys, true);
        return (string) preg_replace_callback('/{{\s*([a-z0-9_.]+)\s*}}/i', static function (array $match) use ($variables, $rawKeys): string {
            $key = (string) $match[1];
            if (!array_key_exists($key, $variables)) {
                return '';
            }
            $value = (string) $variables[$key];
            return isset($rawKeys[$key]) ? $value : htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }, $template);
    }
}
