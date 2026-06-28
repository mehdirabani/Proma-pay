<section class="card">
  <div class="card-header"><h2>داده خام</h2><span class="badge muted"><?= e($batch['filename']) ?></span></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>ردیف</th><th>داده خام</th><th>وضعیت</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $row): ?>
        <tr><td><?= to_persian_digits($row['row_number']) ?></td><td><code><?= e($row['raw_json']) ?></code></td><td><span class="badge muted"><?= e(status_label($row['status'])) ?></span></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

<section class="card" style="margin-top:16px">
  <div class="card-header"><h2>نتیجه تحلیل هوشمند</h2></div>
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
            <tr><td><?= to_persian_digits($contract['customer_national_id'] ?? '') ?></td><td><?= money_toman($principal) ?></td><td><?= money_toman($downPayment) ?></td><td><?= money_toman(max(0, $principal - $downPayment)) ?></td><td><?= percent_label($contract['monthly_interest_rate'] ?? 0) ?></td><td><?= to_persian_digits($contract['months'] ?? '') ?></td></tr>
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
      <div class="notice error">داده پردازش شده معتبر نیست یا کلید هوش مصنوعی تنظیم نشده است. هیچ ردیفی ذخیره نمی‌شود.</div>
    <?php endif; ?>
  </div>
</section>
