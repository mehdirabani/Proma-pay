<section class="card">
  <div class="card-header card-no-border">
    <div class="header-top">
      <div>
        <h2>جزئیات قرارداد <?= e($contract['contract_number']) ?></h2>
        <p><?= e($contract['customer_name']) ?> - <?= to_persian_digits($contract['mobile'] ?? '') ?></p>
      </div>
      <div class="actions">
        <a class="btn secondary" href="<?= e(url('contracts')) ?>">بازگشت</a>
        <a class="btn success" href="<?= e(url('contracts/printDocument/' . $contract['id'])) ?>" target="_blank">چاپ قرارداد</a>
        <?php if ($canManageDocument): ?>
          <form method="post" action="<?= e(url('contracts/generateDocument/' . $contract['id'])) ?>">
            <?= csrf_field() ?>
            <button class="btn" type="submit"><?= $document ? 'تولید مجدد قرارداد' : 'تولید قرارداد' ?></button>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <div class="card-body">
    <div class="proma-preview-grid">
      <span><small>مبلغ اصل قرارداد</small><strong><?= money_toman($contract['principal_amount']) ?></strong></span>
      <span><small>پیش‌پرداخت</small><strong><?= money_toman($contract['down_payment_amount'] ?? 0) ?></strong></span>
      <span><small>مانده قابل تقسیط</small><strong><?= money_toman(max(0, (float) $contract['principal_amount'] - (float) ($contract['down_payment_amount'] ?? 0))) ?></strong></span>
      <span><small>تعداد اقساط</small><strong><?= to_persian_digits($contract['months']) ?></strong></span>
      <span><small>تاریخ قرارداد</small><strong><?= e(jdate($contract['start_date'])) ?></strong></span>
    </div>
  </div>
</section>

<div class="grid cols-2">
  <section class="card">
    <div class="card-header card-no-border"><h2>متن قرارداد</h2></div>
    <div class="card-body">
      <?php if ($document): ?>
        <div class="contract-document-preview"><?= $document['rendered_body'] ?></div>
      <?php else: ?>
        <div class="empty">هنوز متن قرارداد تولید نشده است.</div>
      <?php endif; ?>
    </div>
    <?php if ($canManageDocument): ?>
      <div class="card-body">
        <form method="post" action="<?= e(url('contracts/saveDocument/' . $contract['id'])) ?>" class="form-grid">
          <?= csrf_field() ?>
          <label class="full">ویرایش دستی متن قرارداد<textarea name="rendered_body" rows="18" required><?= e($document['rendered_body'] ?? ContractDocument::render((int) $contract['id'])) ?></textarea></label>
          <label class="full">دلیل ویرایش<input name="change_reason" required placeholder="علت ویرایش نسخه نهایی"></label>
          <div class="actions"><button class="btn" type="submit">ذخیره نسخه نهایی</button></div>
        </form>
      </div>
    <?php endif; ?>
  </section>

  <section class="card">
    <div class="card-header card-no-border"><h2>کالاهای قرارداد</h2></div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>مدل کالا</th><th>IMEI 1</th><th>IMEI 2</th></tr></thead>
        <tbody>
        <?php foreach ($items as $item): ?>
          <tr><td><?= e($item['product_model']) ?></td><td dir="ltr"><?= e(to_persian_digits($item['imei_1'] ?? '')) ?></td><td dir="ltr"><?= e(to_persian_digits($item['imei_2'] ?? '')) ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$items): ?><tr><td colspan="3" class="empty">کالایی ثبت نشده است.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>

    <div class="card-header card-no-border"><h2>ضمانت‌ها</h2></div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>نوع</th><th>تعداد</th><th>شناسه</th><th>توضیحات</th></tr></thead>
        <tbody>
        <?php foreach ($guarantees as $guarantee): ?>
          <tr>
            <td><?= e($guarantee['guarantee_type']) ?></td>
            <td><?= to_persian_digits($guarantee['guarantee_count']) ?></td>
            <td dir="ltr"><?= e(to_persian_digits($guarantee['guarantee_serial'] ?? '')) ?></td>
            <td><?= nl2br(e($guarantee['guarantee_description'] ?? '')) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$guarantees): ?><tr><td colspan="4" class="empty">ضمانتی ثبت نشده است.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
</div>

<div class="grid cols-2">
  <section class="card">
    <div class="card-header card-no-border"><h2>ضامن‌ها</h2></div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>نام</th><th>کد ملی</th><th>تماس</th><th>نسبت</th></tr></thead>
        <tbody>
        <?php foreach ($guarantorPeople as $person): ?>
          <tr>
            <td><?= e($person['full_name']) ?><br><small><?= e($person['father_name'] ?? '') ?></small></td>
            <td><?= to_persian_digits($person['national_id'] ?? '') ?></td>
            <td><?= to_persian_digits($person['mobile'] ?? '') ?></td>
            <td><?= e($person['relationship'] ?? '') ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$guarantorPeople): ?><tr><td colspan="4" class="empty">ضامنی ثبت نشده است.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>

  <section class="card">
    <div class="card-header card-no-border"><h2>اقساط</h2></div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>قسط</th><th>سررسید</th><th>مبلغ</th><th>شناسه ضمانت</th><th>وضعیت</th></tr></thead>
        <tbody>
        <?php foreach ($installments as $installment): ?>
          <tr>
            <td><?= to_persian_digits($installment['installment_number']) ?></td>
            <td><?= e(jdate($installment['due_date'])) ?></td>
            <td><?= money_toman($installment['base_amount']) ?></td>
            <td dir="ltr"><?= e(to_persian_digits($installment['guarantee_serial'] ?? '')) ?></td>
            <td><span class="badge <?= e(badge_class($installment['status'])) ?>"><?= e(status_label($installment['status'])) ?></span></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$installments): ?><tr><td colspan="5" class="empty">قسطی ثبت نشده است.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
</div>

<?php if ($canManageDocument): ?>
<section class="card">
  <div class="card-header card-no-border"><h2>تاریخچه تغییرات قرارداد</h2></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>نوع تغییر</th><th>کاربر</th><th>دلیل</th><th>زمان</th></tr></thead>
      <tbody>
      <?php foreach ($logs as $log): ?>
        <tr>
          <td><?= e($log['change_type']) ?></td>
          <td><?= e($log['changed_by_name'] ?? 'سامانه') ?></td>
          <td><?= e($log['reason'] ?? '') ?></td>
          <td><?= e(jdatetime($log['created_at'])) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$logs): ?><tr><td colspan="4" class="empty">لاگی ثبت نشده است.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
<?php endif; ?>
