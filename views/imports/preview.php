<section class="card">
  <div class="card-header"><h2>ردیف‌های خام</h2><span class="badge muted"><?= e($batch['filename']) ?></span></div>
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
      <div class="grid cols-3">
        <div class="notice">تعداد مشتریان: <?= to_persian_digits(count($parsed['customers'] ?? [])) ?></div>
        <div class="notice">تعداد قراردادها: <?= to_persian_digits(count($parsed['contracts'] ?? [])) ?></div>
        <div class="notice">تعداد اقساط: <?= to_persian_digits(count($parsed['installments'] ?? [])) ?></div>
      </div>
      <?php if (!empty($parsed['customers'])): ?>
        <h3>مشتریان شناسایی شده</h3>
        <div class="table-wrap"><table><thead><tr><th>نام</th><th>کد ملی</th><th>موبایل</th></tr></thead><tbody>
          <?php foreach ($parsed['customers'] as $customer): ?><tr><td><?= e($customer['full_name'] ?? '') ?></td><td><?= to_persian_digits($customer['national_id'] ?? '') ?></td><td><?= to_persian_digits($customer['mobile'] ?? '') ?></td></tr><?php endforeach; ?>
        </tbody></table></div>
      <?php endif; ?>
      <?php if (!empty($parsed['contracts'])): ?>
        <h3>قراردادهای شناسایی شده</h3>
        <div class="table-wrap"><table><thead><tr><th>کد ملی مشتری</th><th>مبلغ</th><th>سود ماهانه</th><th>تعداد اقساط</th></tr></thead><tbody>
          <?php foreach ($parsed['contracts'] as $contract): ?><tr><td><?= to_persian_digits($contract['customer_national_id'] ?? '') ?></td><td><?= money_toman(normalize_money($contract['principal_amount'] ?? 0)) ?></td><td><?= percent_label($contract['monthly_interest_rate'] ?? 0) ?></td><td><?= to_persian_digits($contract['months'] ?? '') ?></td></tr><?php endforeach; ?>
        </tbody></table></div>
      <?php endif; ?>
      <form method="post" action="<?= e(url('imports/confirm/' . $batch['id'])) ?>">
        <?= csrf_field() ?>
        <button class="btn success" type="submit">تأیید و ذخیره در پایگاه داده</button>
      </form>
    <?php else: ?>
      <div class="notice error">داده پردازش شده معتبر نیست یا کلید هوش مصنوعی تنظیم نشده است. هیچ ردیفی ذخیره نمی‌شود.</div>
    <?php endif; ?>
  </div>
</section>
