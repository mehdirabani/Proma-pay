<section class="card">
  <div class="card-header">
    <h2>داده خام</h2>
    <div class="actions">
      <span class="badge muted"><?= e($batch['filename']) ?></span>
      <button class="btn small danger icon-only" type="button" data-open-modal="delete-import-preview" title="حذف بسته ورود دیتا" aria-label="حذف بسته ورود دیتا"><i data-feather="trash-2"></i></button>
    </div>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>ردیف</th><th>داده خام</th><th>وضعیت</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $row): ?>
        <tr><td><?= to_persian_digits($row['row_index'] ?? $row['row_number'] ?? '') ?></td><td><code><?= e($row['raw_json']) ?></code></td><td><span class="badge muted"><?= e(status_label($row['status'])) ?></span></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

<div class="modal" id="delete-import-preview">
  <div class="modal-content">
    <div class="modal-header"><h3>حذف بسته ورود دیتا</h3><button class="icon-btn" type="button" data-close-modal>×</button></div>
    <form method="post" action="<?= e(url('imports/delete/' . (int) $batch['id'])) ?>">
      <div class="modal-body grid">
        <?= csrf_field() ?>
        <?php $deleteCode = ConfirmationCode::hint('import_batch_delete_' . (int) $batch['id']); ?>
        <div class="notice error">سوابق خام و پیش‌نمایش بسته «<?= e($batch['filename']) ?>» حذف می‌شود. داده‌هایی که قبلاً در مشتری/قرارداد ذخیره شده‌اند با این عملیات حذف نمی‌شوند. برای تایید عدد <strong class="ltr"><?= e($deleteCode) ?></strong> را وارد کنید.</div>
        <label>عدد تایید<input name="confirm_text" required inputmode="numeric" autocomplete="off" placeholder="<?= e($deleteCode) ?>"></label>
      </div>
      <div class="modal-footer">
        <button class="btn danger icon-only" type="submit" title="حذف" aria-label="حذف"><i data-feather="trash-2"></i></button>
        <button class="btn secondary" type="button" data-close-modal>بستن</button>
      </div>
    </form>
  </div>
</div>

<section class="card" style="margin-top:16px">
  <div class="card-header"><h2>نتیجه پردازش دیتا</h2></div>
  <div class="card-body">
    <?php if ($parsed): ?>
      <?php $validationErrors = $parsed['_validation_errors'] ?? json_decode($batch['error_summary'] ?? '[]', true) ?: []; ?>
      <?php if ($validationErrors): ?>
        <div class="notice error">
          <strong>خطاهای اعتبارسنجی:</strong>
          <ul class="ai-list">
            <?php foreach ($validationErrors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>
      <div class="grid cols-4">
        <div class="notice">تعداد مشتریان: <?= to_persian_digits(count($parsed['customers'] ?? [])) ?></div>
        <div class="notice">تعداد قراردادها: <?= to_persian_digits(count($parsed['contracts'] ?? [])) ?></div>
        <div class="notice">تعداد اقساط: <?= to_persian_digits(count($parsed['installments'] ?? [])) ?></div>
        <div class="notice">تعداد پرداخت‌ها: <?= to_persian_digits(count($parsed['payments'] ?? [])) ?></div>
      </div>
      <?php if (!empty($parsed['customers'])): ?>
        <h3>مشتریان شناسایی شده</h3>
        <div class="table-wrap"><table><thead><tr><th>نام</th><th>کد ملی</th><th>موبایل</th></tr></thead><tbody>
          <?php foreach ($parsed['customers'] as $customer): ?><tr><td><?= e($customer['full_name'] ?? '') ?></td><td><?= to_persian_digits($customer['national_id'] ?? '') ?></td><td><?= to_persian_digits($customer['mobile'] ?? '') ?></td></tr><?php endforeach; ?>
        </tbody></table></div>
      <?php endif; ?>
      <?php if (!empty($parsed['contracts'])): ?>
        <h3>قراردادهای شناسایی شده</h3>
        <div class="table-wrap"><table><thead><tr><th>کد ملی مشتری</th><th>مبلغ اصل</th><th>پیش‌پرداخت</th><th>مانده قابل تقسیط</th><th>سود ماهانه</th><th>تعداد اقساط</th></tr></thead><tbody>
          <?php foreach ($parsed['contracts'] as $contract): ?>
            <?php
            $principal = normalize_money($contract['principal_amount'] ?? 0);
            $downPayment = normalize_money($contract['down_payment_amount'] ?? $contract['down_payment'] ?? $contract['down payment'] ?? $contract['بیعانه'] ?? $contract['پیش پرداخت'] ?? 0);
            ?>
            <tr><td><?= e($contract['customer_full_name'] ?? '') ?><?= !empty($contract['customer_mobile']) ? '<br><span class="badge muted">' . to_persian_digits($contract['customer_mobile']) . '</span>' : to_persian_digits($contract['customer_national_id'] ?? '') ?></td><td><?= money_toman($principal) ?></td><td><?= money_toman($downPayment) ?></td><td><?= money_toman(max(0, $principal - $downPayment)) ?></td><td><?= percent_label($contract['monthly_interest_rate'] ?? 0) ?></td><td><?= to_persian_digits($contract['months'] ?? '') ?></td></tr>
          <?php endforeach; ?>
        </tbody></table></div>
      <?php endif; ?>
      <?php if (!empty($parsed['payments'])): ?>
        <h3>پرداخت‌های شناسایی شده</h3>
        <div class="table-wrap"><table><thead><tr><th>قرارداد</th><th>قسط</th><th>مبلغ</th><th>تاریخ</th><th>شرح</th></tr></thead><tbody>
          <?php foreach ($parsed['payments'] as $payment): ?>
            <tr><td><?= e($payment['contract_number'] ?? '') ?></td><td><?= to_persian_digits($payment['installment_number'] ?? '-') ?></td><td><?= money_toman(normalize_money($payment['amount'] ?? 0)) ?></td><td><?= e($payment['payment_date'] ?? '') ?></td><td><?= e($payment['notes'] ?? '') ?></td></tr>
          <?php endforeach; ?>
        </tbody></table></div>
      <?php endif; ?>
      <form method="post" action="<?= e(url('imports/confirm/' . $batch['id'])) ?>">
        <?= csrf_field() ?>
        <?php if ($validationErrors): ?>
          <label class="d-block" style="margin-bottom:12px"><input type="checkbox" name="skip_invalid" value="1"> فقط ردیف‌های معتبر ذخیره شوند و ردیف‌های نامعتبر رد شوند</label>
        <?php endif; ?>
        <button class="btn success" type="submit">تأیید و ذخیره در پایگاه داده</button>
      </form>
    <?php else: ?>
      <div class="notice error">داده پردازش شده معتبر نیست. هیچ ردیفی ذخیره نمی‌شود.</div>
    <?php endif; ?>
  </div>
</section>
