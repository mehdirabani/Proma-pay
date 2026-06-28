<div class="grid cols-2">
  <section class="card">
    <div class="card-header"><h2>ثبت تماس مشتری</h2></div>
    <div class="card-body">
      <form method="post" action="<?= e(url('operator/call')) ?>" class="form-grid">
        <?= csrf_field() ?>
        <label>قرارداد<select name="contract_id" required><?php foreach ($contracts as $contract): ?><option value="<?= (int) $contract['id'] ?>"><?= e($contract['contract_number']) ?> - <?= e($contract['customer_name']) ?> - <?= to_persian_digits($contract['mobile']) ?></option><?php endforeach; ?></select></label>
        <label>نتیجه تماس<input name="call_result" required></label>
        <label>پیگیری بعدی<input name="next_followup_date" placeholder="۱۴۰۳/۰۱/۰۱"></label>
        <label class="full">یادداشت<textarea name="notes"></textarea></label>
        <div class="full"><button class="btn" type="submit">ثبت تماس</button></div>
      </form>
    </div>
  </section>
  <section class="card">
    <div class="card-header"><h2>ارجاع حقوقی</h2></div>
    <div class="card-body">
      <form method="post" action="<?= e(url('operator/referLegal')) ?>" class="form-grid">
        <?= csrf_field() ?>
        <label class="full">قرارداد<select name="contract_id" required><?php foreach ($contracts as $contract): ?><option value="<?= (int) $contract['id'] ?>"><?= e($contract['contract_number']) ?> - <?= e($contract['customer_name']) ?></option><?php endforeach; ?></select></label>
        <label class="full">شرح<textarea name="notes"></textarea></label>
        <div class="full"><button class="btn danger" type="submit">ارسال برای اقدام حقوقی</button></div>
      </form>
    </div>
  </section>
</div>

<section class="card" style="margin-top:16px">
  <div class="card-header"><h2>قراردادهای ارجاع شده</h2></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>قرارداد</th><th>مشتری</th><th>تماس</th><th>مبلغ</th><th>وضعیت</th></tr></thead>
      <tbody>
      <?php foreach ($contracts as $contract): ?>
        <tr><td><?= e($contract['contract_number']) ?></td><td><?= e($contract['customer_name']) ?></td><td><?= to_persian_digits($contract['mobile']) ?><?= $contract['secondary_phone'] ? '، ' . to_persian_digits($contract['secondary_phone']) : '' ?></td><td><?= money_toman($contract['principal_amount']) ?></td><td><span class="badge <?= e(badge_class($contract['status'])) ?>"><?= e(status_label($contract['status'])) ?></span></td></tr>
      <?php endforeach; ?>
      <?php if (!$contracts): ?><tr><td colspan="5" class="empty">قراردادی برای پیگیری وجود ندارد.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<section class="card" style="margin-top:16px">
  <div class="card-header"><h2>گزارش تماس‌ها</h2></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>مشتری</th><th>قرارداد</th><th>نتیجه</th><th>پیگیری بعدی</th><th>یادداشت</th></tr></thead>
      <tbody>
      <?php foreach ($calls as $call): ?>
        <tr><td><?= e($call['customer_name']) ?></td><td><?= e($call['contract_number']) ?></td><td><?= e($call['call_result']) ?></td><td><?= e(jdate($call['next_followup_date'])) ?></td><td><?= e($call['notes']) ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$calls): ?><tr><td colspan="5" class="empty">تماسی ثبت نشده است.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
