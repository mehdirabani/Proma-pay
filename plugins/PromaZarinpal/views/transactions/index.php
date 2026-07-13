<?php
$items = $pagination['items'] ?? [];
$statusLabels = ['needs_review' => 'همه نیازمند بررسی', 'created' => 'ایجادشده', 'request_uncertain' => 'درخواست نامشخص', 'request_failed' => 'خطای درخواست', 'pending' => 'در انتظار پرداخت', 'callback_received' => 'بازگشت دریافت شد', 'verifying' => 'در حال تأیید', 'paid' => 'موفق', 'sandbox_verified' => 'آزمایشی تأییدشده', 'failed' => 'ناموفق', 'cancelled' => 'لغوشده', 'cancelled_by_customer' => 'لغو توسط مشتری', 'verification_failed' => 'خطای تأیید', 'manual_review' => 'بررسی دستی'];
$statusTone = ['paid' => 'is-success', 'sandbox_verified' => 'is-info', 'failed' => 'is-danger', 'request_failed' => 'is-danger', 'cancelled' => 'is-muted', 'cancelled_by_customer' => 'is-muted', 'verification_failed' => 'is-danger', 'request_uncertain' => 'is-warning', 'manual_review' => 'is-warning', 'verifying' => 'is-info', 'pending' => 'is-warning'];
$pageUrl = static function ($page) {
    $params = $_GET;
    unset($params['route']);
    $params['page'] = $page;
    return url('plugin/zarinpal/transactions', $params);
};
?>
<section class="proma-zp-page">
  <header class="proma-zp-hero">
    <div class="proma-zp-hero-icon"><i data-feather="credit-card"></i></div>
    <div><span>کنترل و تطبیق</span><h1>تراکنش‌های زرین‌پال</h1><p>وضعیت درخواست، Callback، Verify و ثبت مالی هر تراکنش مستقل قابل پیگیری است.</p></div>
    <a class="btn btn-outline-primary" href="<?= e(url('plugin/zarinpal/transactions/export')) ?>"><i data-feather="download"></i> خروجی CSV</a>
  </header>
  <section class="proma-zp-card">
    <form method="get" action="<?= e(url('plugin/zarinpal/transactions')) ?>" class="proma-zp-grid four">
      <input type="hidden" name="route" value="plugin/zarinpal/transactions">
      <label class="span-two">جستجو<input name="q" value="<?= e($filters['q'] ?? '') ?>" placeholder="شناسه محلی، Authority، Ref ID، قرارداد یا مشتری"></label>
      <label>وضعیت<select name="status"><option value="">همه</option><?php foreach ($statusLabels as $key => $label): ?><option value="<?= e($key) ?>"<?= selected($filters['status'] ?? '', $key) ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
      <label>محیط<select name="environment"><option value="">همه</option><option value="production"<?= selected($filters['environment'] ?? '', 'production') ?>>عملیاتی</option><option value="sandbox"<?= selected($filters['environment'] ?? '', 'sandbox') ?>>آزمایشی</option></select></label>
      <label>از تاریخ<input name="date_from" value="<?= e($filters['date_from_input'] ?? '') ?>" placeholder="۱۴۰۵/۰۴/۰۱" inputmode="numeric"></label>
      <label>تا تاریخ<input name="date_to" value="<?= e($filters['date_to_input'] ?? '') ?>" placeholder="۱۴۰۵/۰۴/۳۱" inputmode="numeric"></label>
      <label>حداقل مبلغ تومان<input name="amount_min" value="<?= !empty($filters['amount_min']) ? e(number_format((int) $filters['amount_min'])) : '' ?>" data-money inputmode="numeric"></label>
      <label>حداکثر مبلغ تومان<input name="amount_max" value="<?= !empty($filters['amount_max']) ? e(number_format((int) $filters['amount_max'])) : '' ?>" data-money inputmode="numeric"></label>
      <div class="proma-zp-filter-actions full"><button class="btn btn-primary" type="submit"><i data-feather="search"></i> جستجو</button><a class="btn btn-light" href="<?= e(url('plugin/zarinpal/transactions')) ?>">حذف فیلتر</a><span><?= to_persian_digits($pagination['total'] ?? 0) ?> تراکنش</span></div>
    </form>
  </section>
  <section class="proma-zp-card proma-zp-table-card">
    <div class="table-responsive">
      <table class="table proma-zp-table">
        <thead><tr><th>شناسه</th><th>مشتری / قرارداد</th><th>مبلغ</th><th>محیط</th><th>وضعیت</th><th>Authority / Ref ID</th><th>زمان</th><th>عملیات</th></tr></thead>
        <tbody>
        <?php foreach ($items as $item): ?>
          <tr>
            <td data-label="شناسه"><strong dir="ltr"><?= e($item['local_order_id']) ?></strong><small>#<?= to_persian_digits($item['id']) ?></small></td>
            <td data-label="مشتری / قرارداد"><strong><?= e($item['customer_name']) ?></strong><small><?= e($item['contract_number']) ?></small></td>
            <td data-label="مبلغ"><strong><?= money_toman($item['internal_amount_toman']) ?></strong><small><?= e($item['gateway_currency']) ?>: <?= to_persian_digits(number_format((int) $item['gateway_amount'])) ?></small></td>
            <td data-label="محیط"><span class="proma-zp-badge <?= $item['environment'] === 'sandbox' ? 'is-info' : 'is-muted' ?>"><?= $item['environment'] === 'sandbox' ? 'آزمایشی' : 'عملیاتی' ?></span></td>
            <td data-label="وضعیت"><span class="proma-zp-badge <?= e($statusTone[$item['status']] ?? 'is-muted') ?>"><?= e($statusLabels[$item['status']] ?? $item['status']) ?></span><?php if (!empty($item['error_code'])): ?><small dir="ltr"><?= e($item['error_code']) ?></small><?php endif; ?></td>
            <td data-label="Authority / Ref ID"><strong dir="ltr"><?= e($item['authority'] ? substr($item['authority'], 0, 12) . '…' : '—') ?></strong><small dir="ltr"><?= e($item['ref_id'] ?: '—') ?></small></td>
            <td data-label="زمان"><strong><?= e(jdatetime($item['created_at'])) ?></strong><small><?= $item['verified_at'] ? 'تأیید: ' . e(jdatetime($item['verified_at'])) : '' ?></small></td>
            <td data-label="عملیات">
              <div class="proma-zp-row-actions">
                <button class="icon-btn" type="button" title="جزئیات تراکنش" data-open-modal="zp-details-<?= (int) $item['id'] ?>"><i data-feather="eye"></i></button>
              <?php if (in_array($item['status'], ['verification_failed', 'verifying', 'callback_received', 'request_uncertain'], true) && !empty($item['authority']) && (int) $item['retry_count'] < 5): ?>
                <button class="icon-btn" type="button" title="بررسی مجدد" data-open-modal="zp-retry-<?= (int) $item['id'] ?>"><i data-feather="refresh-cw"></i></button>
              <?php endif; ?>
              <?php if (in_array($item['status'], ['created', 'request_uncertain', 'callback_received', 'verification_failed', 'verifying'], true)): ?><button class="icon-btn" type="button" title="علامت‌گذاری بررسی دستی" data-open-modal="zp-review-<?= (int) $item['id'] ?>"><i data-feather="flag"></i></button><?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$items): ?><tr><td colspan="8" class="text-center text-muted">تراکنشی برای نمایش وجود ندارد.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
  <?php if (($pagination['pages'] ?? 1) > 1): ?><nav class="proma-pagination"><?php for ($page = 1; $page <= (int) $pagination['pages']; $page++): ?><a class="tab-link <?= $page === (int) $pagination['page'] ? 'active' : '' ?>" href="<?= e($pageUrl($page)) ?>"><?= to_persian_digits($page) ?></a><?php endfor; ?></nav><?php endif; ?>
</section>
<?php foreach ($items as $item): ?>
  <?php $timeline = ['created_at' => 'تراکنش محلی ساخته شد', 'requested_at' => 'درخواست به زرین‌پال ارسال شد', 'redirected_at' => 'مشتری به درگاه منتقل شد', 'callback_at' => 'Callback دریافت شد', 'verifying_at' => 'Verify آغاز شد', 'verified_at' => 'نتیجه نهایی ثبت شد', 'failed_at' => 'خطا یا لغو ثبت شد']; ?>
  <div class="modal" id="zp-details-<?= (int) $item['id'] ?>"><div class="modal-content proma-zp-modal-wide"><div class="modal-header"><div><h3>جزئیات تراکنش</h3><small dir="ltr"><?= e($item['local_order_id']) ?></small></div><button class="icon-btn" type="button" data-close-modal>×</button></div><div class="modal-body">
    <div class="proma-zp-detail-grid">
      <div><span>مشتری</span><strong><?= e($item['customer_name']) ?></strong></div><div><span>قرارداد</span><strong><?= e($item['contract_number']) ?></strong></div>
      <div><span>قسط / گروه</span><strong><?= !empty($item['installment_number']) ? 'قسط ' . to_persian_digits($item['installment_number']) : (!empty($item['payment_group_id']) ? 'گروه #' . to_persian_digits($item['payment_group_id']) : '—') ?></strong></div><div><span>محیط</span><strong><?= $item['environment'] === 'sandbox' ? 'آزمایشی' : 'عملیاتی' ?></strong></div>
      <div><span>مبلغ داخلی</span><strong><?= money_toman($item['internal_amount_toman']) ?></strong></div><div><span>مبلغ درگاه</span><strong><?= to_persian_digits(number_format((int) $item['gateway_amount'])) ?> <?= e($item['gateway_currency']) ?></strong></div>
      <div><span>وضعیت</span><strong><?= e($statusLabels[$item['status']] ?? $item['status']) ?></strong></div><div><span>کارمزد</span><strong><?= to_persian_digits(number_format((int) ($item['fee'] ?? 0))) ?> <small><?= e($item['fee_type'] ?? '') ?></small></strong></div>
      <div><span>Authority</span><strong dir="ltr"><?= e($item['authority'] ?: '—') ?></strong></div><div><span>Reference ID</span><strong dir="ltr"><?= e($item['ref_id'] ?: '—') ?></strong></div>
      <div><span>کارت</span><strong dir="ltr"><?= e(($item['card_pan_masked'] ?? '') ?: '—') ?></strong></div><div><span>خطای امن</span><strong dir="ltr"><?= e(($item['error_code'] ?? '') ?: '—') ?></strong></div>
      <div class="full"><span>توضیح تراکنش</span><strong><?= e($item['description'] ?? '—') ?></strong></div><div class="full"><span>نتیجه امن درخواست / تأیید</span><strong><?= e(trim((string) ($item['request_message'] ?? '') . ' ' . (string) ($item['verify_message'] ?? '')) ?: '—') ?></strong></div>
    </div>
    <div class="proma-zp-timeline"><?php foreach ($timeline as $field => $label): ?><?php if (!empty($item[$field])): ?><div><i></i><span><?= e($label) ?></span><time><?= e(jdatetime($item[$field])) ?></time></div><?php endif; ?><?php endforeach; ?></div>
  </div><div class="modal-footer"><button class="btn btn-light" type="button" data-close-modal>بستن</button></div></div></div>
<?php endforeach; ?>
<?php foreach ($items as $item): ?>
  <?php if (!in_array($item['status'], ['verification_failed', 'verifying', 'callback_received', 'request_uncertain'], true) || empty($item['authority']) || (int) $item['retry_count'] >= 5): continue; endif; ?>
  <div class="modal" id="zp-retry-<?= (int) $item['id'] ?>"><div class="modal-content"><div class="modal-header"><h3>بررسی مجدد تراکنش</h3><button class="icon-btn" type="button" data-close-modal>×</button></div><form method="post" action="<?= e(url('plugin/zarinpal/transactions/retry/' . (int) $item['id'])) ?>" data-disable-on-submit><div class="modal-body form-grid"><?= csrf_field() ?><div class="notice warning full">این عملیات دوباره Verify را با مبلغ و Merchant ذخیره‌شده انجام می‌دهد و درخواست پرداخت تازه‌ای نمی‌سازد.</div><label class="full">کد تأیید <strong dir="ltr"><?= e(substr($item['local_order_id'], -6)) ?></strong><input name="confirmation" dir="ltr" required autocomplete="off"></label></div><div class="modal-footer"><button class="btn btn-primary" type="submit" data-submit-label="در حال بررسی...">بررسی مجدد</button><button class="btn btn-light" type="button" data-close-modal>انصراف</button></div></form></div></div>
<?php endforeach; ?>
<?php foreach ($items as $item): ?>
  <?php if (!in_array($item['status'], ['created', 'request_uncertain', 'callback_received', 'verification_failed', 'verifying'], true)): continue; endif; ?>
  <div class="modal" id="zp-review-<?= (int) $item['id'] ?>"><div class="modal-content"><div class="modal-header"><h3>علامت‌گذاری برای بررسی دستی</h3><button class="icon-btn" type="button" data-close-modal>×</button></div><form method="post" action="<?= e(url('plugin/zarinpal/transactions/review/' . (int) $item['id'])) ?>" data-disable-on-submit><div class="modal-body form-grid"><?= csrf_field() ?><div class="notice warning full">این عملیات تراکنش را موفق نمی‌کند و هیچ مانده یا پرداختی را تغییر نمی‌دهد؛ فقط آن را وارد صف بررسی دستی می‌کند.</div><label class="full">کد تأیید <strong dir="ltr"><?= e(substr($item['local_order_id'], -6)) ?></strong><input name="confirmation" dir="ltr" required autocomplete="off"></label></div><div class="modal-footer"><button class="btn btn-primary" type="submit">ثبت برای بررسی</button><button class="btn btn-light" type="button" data-close-modal>انصراف</button></div></form></div></div>
<?php endforeach; ?>
