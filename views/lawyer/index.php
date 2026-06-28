<section class="card">
  <div class="card-header"><h2>ثبت پرونده شکایت</h2></div>
  <div class="card-body">
    <form method="post" action="<?= e(url('lawyer/create')) ?>" class="form-grid">
      <?= csrf_field() ?>
      <label>قرارداد واجد شرایط<select name="contract_id" required><?php foreach ($eligible as $contract): ?><option value="<?= (int) $contract['id'] ?>"><?= e($contract['contract_number']) ?> - <?= e($contract['customer_name']) ?> - <?= to_persian_digits($contract['mobile']) ?></option><?php endforeach; ?></select></label>
      <label class="full">شرح<textarea name="notes"></textarea></label>
      <div class="full"><button class="btn danger" type="submit">ثبت شکایت</button></div>
    </form>
  </div>
</section>

<section class="card" style="margin-top:16px">
  <div class="card-header"><h2>پرونده‌های حقوقی</h2></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>قرارداد</th><th>مشتری</th><th>مرحله</th><th>شماره شکایت</th><th>هزینه</th><th>وضعیت</th><th>عملیات</th></tr></thead>
      <tbody>
      <?php foreach ($cases as $case): ?>
        <tr>
          <td><?= e($case['contract_number']) ?></td>
          <td><?= e($case['customer_name']) ?><br><span class="badge muted"><?= to_persian_digits($case['mobile']) ?></span></td>
          <td><?= e(legal_stage_label($case['stage'])) ?></td>
          <td><?= to_persian_digits($case['complaint_number']) ?></td>
          <td><?= money_toman($case['expense_amount']) ?></td>
          <td><span class="badge <?= e(badge_class($case['status'])) ?>"><?= e(status_label($case['status'])) ?></span></td>
          <td><button class="btn small secondary" type="button" data-open-modal="case-<?= (int) $case['id'] ?>">به‌روزرسانی</button></td>
        </tr>
        <div class="modal" id="case-<?= (int) $case['id'] ?>">
          <div class="modal-content">
            <div class="modal-header"><h3>به‌روزرسانی پرونده</h3><button class="icon-btn" type="button" data-close-modal>×</button></div>
            <form method="post" action="<?= e(url('lawyer/update/' . $case['id'])) ?>">
              <div class="modal-body form-grid">
                <?= csrf_field() ?>
                <input type="hidden" name="lawyer_id" value="<?= (int) ($case['lawyer_id'] ?? Auth::id()) ?>">
                <label>مرحله<select name="stage" required><?php foreach (app_config('legal_stages', []) as $stageKey => $stageLabel): ?><option value="<?= e($stageKey) ?>"<?= selected($case['stage'], $stageKey) ?>><?= e($stageLabel) ?></option><?php endforeach; ?></select></label>
                <label>وضعیت<select name="status"><option value="open"<?= selected($case['status'], 'open') ?>>باز</option><option value="closed"<?= selected($case['status'], 'closed') ?>>بسته</option><option value="referred"<?= selected($case['status'], 'referred') ?>>ارجاع شده</option></select></label>
                <label>شماره شکایت<input name="complaint_number" value="<?= e($case['complaint_number']) ?>"></label>
                <label>هزینه حقوقی<input name="expense_amount" data-money value="<?= e(number_format((float) $case['expense_amount'], 0)) ?>"></label>
                <label class="full">علت هزینه<textarea name="expense_reason" placeholder="در صورت ثبت هزینه، علت آن الزامی است"><?= e($case['expense_reason'] ?? '') ?></textarea></label>
                <label class="full">یادداشت<textarea name="notes"><?= e($case['notes']) ?></textarea></label>
              </div>
              <div class="modal-footer"><button class="btn" type="submit">ثبت تغییرات</button><button class="btn secondary" type="button" data-close-modal>بستن</button></div>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
      <?php if (!$cases): ?><tr><td colspan="7" class="empty">پرونده‌ای ثبت نشده است.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
