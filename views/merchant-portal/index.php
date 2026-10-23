<?php
$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$number = static function ($value): string {
    $formatted = number_format(max(0, (int) $value));
    return function_exists('to_persian_digits') ? to_persian_digits($formatted) : $formatted;
};
$money = static function ($value) use ($number): string { return $number($value) . ' تومان'; };
$statuses = [
    'pending_review' => 'در انتظار بررسی', 'approved' => 'تأییدشده', 'rejected' => 'ردشده', 'cancelled' => 'لغوشده',
    'pending' => 'در انتظار', 'eligible' => 'واجد شرایط', 'scheduled' => 'زمان‌بندی‌شده', 'processing' => 'در حال پرداخت',
    'paid' => 'پرداخت‌شده', 'failed' => 'ناموفق', 'suspended' => 'متوقف', 'reversed' => 'معکوس‌شده',
    'registered' => 'ثبت‌شده در صیاد', 'confirmed' => 'تأییدشده در صیاد', 'unmatched' => 'تطبیق‌نیافته',
    'mismatch' => 'دارای مغایرت', 'matched' => 'تطبیق‌شده', 'reviewed' => 'بازبینی‌شده', 'dead_letter' => 'خطای نهایی',
    'queued' => 'صف‌شده', 'delivered' => 'تحویل‌شده', 'returned' => 'عودت‌شده', 'released' => 'آزادشده',
    'legal_followup' => 'پیگیری حقوقی',
];
$label = static function ($value) use ($statuses): string { return $statuses[(string) $value] ?? (string) $value; };
$merchantId = (int) $dashboard['merchant']['merchant_id'];
?>
<link rel="stylesheet" href="<?= $escape(url('assets/css/merchant-portal.css')) ?>">
<main class="merchant-portal" dir="rtl">
    <header class="merchant-portal__header">
        <div>
            <p class="merchant-portal__eyebrow">پنل فروشنده</p>
            <h1><?= $escape($dashboard['merchant']['display_name']) ?></h1>
            <p>گزارش سفارش‌ها، ضمانت‌ها، تسویه و وضعیت اتصال‌های API</p>
        </div>
        <?php if (count($merchants) > 1): ?>
            <form method="get" action="<?= $escape(url('merchant-portal')) ?>" class="merchant-portal__switcher">
                <label for="merchant-id">فروشگاه</label>
                <select id="merchant-id" name="merchant_id" onchange="this.form.submit()">
                    <?php foreach ($merchants as $merchant): ?>
                        <option value="<?= (int) $merchant['merchant_id'] ?>" <?= (int) $merchant['merchant_id'] === $merchantId ? 'selected' : '' ?>><?= $escape($merchant['display_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        <?php endif; ?>
    </header>

    <section class="merchant-portal__cards" aria-label="خلاصه وضعیت">
        <article class="merchant-portal__card"><span>کل درخواست‌های خرید اقساطی</span><strong><?= $number($dashboard['orders']['total']) ?></strong><small>این درخواست‌ها به‌تنهایی قرارداد یا طلب نیستند.</small></article>
        <article class="merchant-portal__card"><span>در انتظار بررسی</span><strong><?= $number($dashboard['orders']['pending_review']) ?></strong><small>برای تصمیم‌گیری داخلی سامانه</small></article>
        <article class="merchant-portal__card"><span>قراردادهای فعال مرتبط</span><strong><?= $number($dashboard['active_contracts']) ?></strong><small>قراردادهای داخلی که به سفارش فروشگاه پیوند خورده‌اند</small></article>
        <article class="merchant-portal__card"><span>رکوردهای ضمانت</span><strong><?= $number($dashboard['guarantee_total']) ?></strong><small>نمایش ۲۵ مورد اخیر؛ ثبت صیاد از این صفحه انجام نمی‌شود.</small></article>
    </section>

    <section class="merchant-portal__panel">
        <div class="merchant-portal__section-heading"><div><h2>ضمانت‌های چک صیادی</h2><p>سررسید چک و قسط اول برای کنترل مغایرت کنار هم نمایش داده می‌شوند.</p></div></div>
        <div class="merchant-portal__table-wrap"><table>
            <thead><tr><th>شناسه ضمانت</th><th>مبنای اقساط</th><?php if ($dashboard['can_settlement']): ?><th>مبلغ اسمی چک (ریال)</th><?php endif; ?><th>اولین قسط</th><th>سررسید چک</th><th>وضعیت صیاد</th><th>وضعیت فیزیکی</th></tr></thead>
            <tbody>
            <?php foreach ($dashboard['guarantees'] as $guarantee): ?>
                <tr>
                    <td><code><?= $escape(substr((string) $guarantee['guarantee_uuid'], 0, 8)) ?></code></td>
                    <td><?= $dashboard['can_settlement'] ? $money($guarantee['scheduled_installments_toman']) : 'محدود' ?></td>
                    <?php if ($dashboard['can_settlement']): ?><td><?= $number($guarantee['cheque_amount_rial']) ?> ریال</td><?php endif; ?>
                    <td><?= $escape($guarantee['first_installment_due_date']) ?></td>
                    <td><?= $escape($guarantee['cheque_due_date']) ?></td>
                    <td><?= $escape($label($guarantee['sayad_status'])) ?></td>
                    <td><?= $escape($label($guarantee['physical_status'])) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$dashboard['guarantees']): ?><tr><td colspan="7" class="merchant-portal__empty">هنوز ضمانتی به قراردادهای این فروشگاه پیوند نخورده است.</td></tr><?php endif; ?>
            </tbody>
        </table></div>
    </section>

    <section class="merchant-portal__panel">
        <div class="merchant-portal__section-heading"><div><h2>گزارش تسویه و مطالبات</h2><p>تعهد یا مطالبهٔ وصول‌نشده وجه نقد یا موجودی قابل‌پرداخت نیست.</p></div></div>
        <?php if ($dashboard['can_settlement']): ?>
            <div class="merchant-portal__table-wrap"><table>
                <thead><tr><th>وضعیت</th><th>تعداد تعهد</th><th>مطالبهٔ ثبت‌شده (وجه نقد نیست)</th><th>خالص تسویهٔ ثبت‌شده</th></tr></thead>
                <tbody>
                <?php foreach ($dashboard['settlements']['rows'] as $row): ?>
                    <tr><td><?= $escape($label($row['status'])) ?></td><td><?= $number($row['total']) ?></td><td><?= $money($row['gross_receivable_toman']) ?></td><td><?= $money($row['net_settlement_toman']) ?></td></tr>
                <?php endforeach; ?>
                <?php if (!$dashboard['settlements']['rows']): ?><tr><td colspan="4" class="merchant-portal__empty">تعهد تسویه‌ای ثبت نشده است.</td></tr><?php endif; ?>
                </tbody>
            </table></div>
            <h3 class="merchant-portal__subheading">جزئیات تعهدها</h3>
            <div class="merchant-portal__table-wrap"><table>
                <thead><tr><th>شناسه سفارش فروشگاه</th><th>وضعیت</th><th>موعد تسویه</th><th>مطالبهٔ ثبت‌شده</th><th>خالص تسویه</th><th>منبع وجه</th></tr></thead>
                <tbody>
                <?php foreach ($dashboard['settlements']['items'] as $row): ?>
                    <tr><td><?= $escape($row['external_order_id']) ?></td><td><?= $escape($label($row['status'])) ?></td><td><?= $escape($row['settlement_due_date'] ?? '—') ?></td><td><?= $money($row['gross_receivable_toman']) ?></td><td><?= $money($row['net_settlement_toman']) ?></td><td><?= !empty($row['funding_account_id']) ? 'منبع ثبت شده' : 'منبع ثبت نشده' ?></td></tr>
                <?php endforeach; ?>
                <?php if (!$dashboard['settlements']['items']): ?><tr><td colspan="6" class="merchant-portal__empty">تعهدی برای نمایش وجود ندارد.</td></tr><?php endif; ?>
                </tbody>
            </table></div>
        <?php else: ?>
            <p class="merchant-portal__notice">نقش شما گزارش مالی را می‌بیند؛ نمایش مبالغ تسویه نیازمند دسترسی «مشاهده تسویه» است.</p>
        <?php endif; ?>
    </section>

    <div class="merchant-portal__grid">
        <section class="merchant-portal__panel">
            <div class="merchant-portal__section-heading"><div><h2>مغایرت‌های مالی</h2><p>وضعیت تطبیق صورت‌حساب بانکی با دستهٔ تسویه</p></div></div>
            <ul class="merchant-portal__status-list">
                <?php foreach ($dashboard['reconciliations'] as $row): ?><li><span><?= $escape($label($row['reconciliation_status'])) ?></span><strong><?= $number($row['total']) ?></strong></li><?php endforeach; ?>
                <?php if (!$dashboard['reconciliations']): ?><li class="merchant-portal__empty">مغایرتی ثبت نشده است.</li><?php endif; ?>
            </ul>
        </section>
        <section class="merchant-portal__panel">
            <div class="merchant-portal__section-heading"><div><h2>وضعیت Webhook API</h2><p>وضعیت ارسال رخدادها برای اتصال‌های این فروشنده</p></div></div>
            <ul class="merchant-portal__status-list">
                <?php foreach ($dashboard['webhooks'] as $row): ?><li><span><?= $escape($label($row['delivery_status'])) ?></span><strong><?= $number($row['total']) ?></strong></li><?php endforeach; ?>
                <?php if (!$dashboard['webhooks']): ?><li class="merchant-portal__empty">رویدادی در صف نیست.</li><?php endif; ?>
            </ul>
        </section>
    </div>

    <section class="merchant-portal__panel">
        <div class="merchant-portal__section-heading"><div><h2>خطاهای اتصال و رخدادهای API</h2><p>فقط کد خطا و نام رخداد نمایش داده می‌شود؛ payload و دادهٔ شخصی افشا نمی‌شود.</p></div></div>
        <h3 class="merchant-portal__subheading">خطاهای تحویل</h3>
        <div class="merchant-portal__table-wrap"><table><thead><tr><th>رخداد</th><th>وضعیت</th><th>تلاش</th><th>HTTP</th><th>کد خطا</th><th>آخرین تغییر</th></tr></thead><tbody>
            <?php foreach ($dashboard['webhook_errors'] as $error): ?><tr><td><?= $escape($error['event_type']) ?></td><td><?= $escape($label($error['delivery_status'])) ?></td><td><?= $number($error['attempt_count']) ?></td><td><?= $escape($error['last_http_status'] ?? '—') ?></td><td><code><?= $escape($error['last_error_code'] ?? '—') ?></code></td><td><?= $escape($error['updated_at']) ?></td></tr><?php endforeach; ?>
            <?php if (!$dashboard['webhook_errors']): ?><tr><td colspan="6" class="merchant-portal__empty">خطای ثبت‌شده‌ای نیست.</td></tr><?php endif; ?>
        </tbody></table></div>
        <h3 class="merchant-portal__subheading">تاریخچهٔ API</h3>
        <div class="merchant-portal__table-wrap"><table><thead><tr><th>رخداد</th><th>نوع موضوع</th><th>زمان</th></tr></thead><tbody>
            <?php foreach ($dashboard['api_events'] as $event): ?><tr><td><code><?= $escape($event['event_type']) ?></code></td><td><?= $escape($event['subject_type']) ?></td><td><?= $escape($event['created_at']) ?></td></tr><?php endforeach; ?>
            <?php if (!$dashboard['api_events']): ?><tr><td colspan="3" class="merchant-portal__empty">تاریخچه‌ای ثبت نشده است.</td><?php endif; ?>
        </tbody></table></div>
    </section>

    <p class="merchant-portal__footnote">این پنل فقط گزارش می‌دهد. ثبت وجه یا تسویه از روی وضعیت قرارداد/مطالبه انجام نمی‌شود؛ انتقال بانکی فقط پس از تأییدهای مالی و ثبت وضعیت واقعی قابل گزارش است.</p>
</main>
