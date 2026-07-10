<?php
$legalStages = app_config('legal_stages', []);
$readyCases = $readyCases ?? [];
$filedCases = $filedCases ?? [];
$referredCases = $referredCases ?? [];
?>
<div data-ajax-results="lawyer">
<section class="card">
  <div class="card-body">
    <form method="get" action="<?= e(url('lawyer')) ?>" class="form-grid four" data-ajax-filter data-ajax-target="[data-ajax-results='lawyer']">
      <input type="hidden" name="route" value="lawyer">
      <label>جستجو<input name="q" value="<?= e($_GET['q'] ?? '') ?>" placeholder="قرارداد، مشتری یا موبایل"></label>
      <label>وضعیت
        <select name="status">
          <option value="">همه</option>
          <option value="open"<?= selected($_GET['status'] ?? '', 'open') ?>>باز</option>
          <option value="referred"<?= selected($_GET['status'] ?? '', 'referred') ?>>ارجاع‌شده</option>
          <option value="closed"<?= selected($_GET['status'] ?? '', 'closed') ?>>بسته</option>
        </select>
      </label>
      <label>مرتب‌سازی
        <select name="sort">
          <option value="created"<?= selected($_GET['sort'] ?? 'created', 'created') ?>>تاریخ ثبت</option>
          <option value="updated"<?= selected($_GET['sort'] ?? '', 'updated') ?>>آخرین تغییر</option>
          <option value="contract"<?= selected($_GET['sort'] ?? '', 'contract') ?>>قرارداد</option>
          <option value="customer"<?= selected($_GET['sort'] ?? '', 'customer') ?>>مشتری</option>
        </select>
      </label>
      <div class="actions">
        <button class="btn secondary" type="submit">اعمال</button>
        <a class="btn small secondary" href="<?= e(url('lawyer/export', ['q' => $_GET['q'] ?? null, 'status' => $_GET['status'] ?? null, 'sort' => $_GET['sort'] ?? null])) ?>">خروجی</a>
        <span class="proma-ajax-status" data-ajax-status></span>
      </div>
    </form>
  </div>
</section>
</div>

<div class="proma-lawyer-workflow">
  <section class="card">
    <div class="card-header"><h2>پرونده‌های ارجاعی</h2><span class="badge warning"><?= to_persian_digits(count($cases)) ?></span></div>
    <div class="card-body proma-workflow-list">
      <?php foreach (array_slice($cases, 0, 6) as $case): ?>
        <div><strong><?= e($case['contract_number']) ?></strong><small><?= e($case['customer_name']) ?> - <?= e($case['stage']) ?></small></div>
      <?php endforeach; ?>
      <?php if (!$cases): ?><div class="empty">پرونده‌ای به شما ارجاع نشده است.</div><?php endif; ?>
    </div>
  </section>
  <section class="card">
    <div class="card-header"><h2>ثبت‌شده</h2><span class="badge info"><?= to_persian_digits(count($filedCases)) ?></span></div>
    <div class="card-body proma-workflow-list">
      <?php foreach (array_slice($filedCases, 0, 6) as $case): ?>
        <div><strong><?= e($case['contract_number']) ?></strong><small><?= e($case['stage']) ?> - <?= e(status_label($case['status'])) ?></small></div>
      <?php endforeach; ?>
      <?php if (!$filedCases): ?><div class="empty">پرونده ثبت‌شده‌ای نیست.</div><?php endif; ?>
    </div>
  </section>
  <section class="card">
    <div class="card-header"><h2>ارجاع‌شده</h2><span class="badge danger"><?= to_persian_digits(count($referredCases)) ?></span></div>
    <div class="card-body proma-workflow-list">
      <?php foreach (array_slice($referredCases, 0, 6) as $case): ?>
        <div><strong><?= e($case['contract_number']) ?></strong><small><?= e($case['customer_name']) ?> - <?= e($case['stage']) ?></small></div>
      <?php endforeach; ?>
      <?php if (!$referredCases): ?><div class="empty">ارجاع فعالی نیست.</div><?php endif; ?>
    </div>
  </section>
</div>
<section class="card">
  <div class="card-header"><h2>ثبت پرونده شکایت</h2></div>
  <div class="card-body">
    <div class="empty">ثبت پرونده حقوقی فقط پس از ارجاع مدیریت فعال می‌شود. وکیل فقط به قرارداد، اقساط معوقه و پرداخت‌های مشتری همان پرونده دسترسی دارد.</div>
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
          <td><?= e($case['stage']) ?></td>
          <td><?= to_persian_digits($case['complaint_number']) ?></td>
          <td><?= money_toman($case['expense_amount']) ?></td>
          <td><span class="badge <?= e(badge_class($case['status'])) ?>"><?= e(status_label($case['status'])) ?></span></td>
          <td class="actions">
            <a class="btn small info" href="<?= e(url('legal/show/' . (int) $case['id'])) ?>">جزئیات</a>
            <button class="btn small secondary" type="button" data-open-modal="case-<?= (int) $case['id'] ?>">به‌روزرسانی</button>
          </td>
        </tr>
        <div class="modal" id="case-<?= (int) $case['id'] ?>">
          <div class="modal-content">
            <div class="modal-header"><h3>به‌روزرسانی پرونده</h3><button class="icon-btn" type="button" data-close-modal>×</button></div>
            <form method="post" action="<?= e(url('lawyer/update/' . $case['id'])) ?>">
              <div class="modal-body form-grid">
                <?= csrf_field() ?>
                <input type="hidden" name="lawyer_id" value="<?= (int) ($case['lawyer_id'] ?? Auth::id()) ?>">
                <label>مرحله
                  <select name="stage" required>
                    <?php foreach ($legalStages as $stage): ?><option value="<?= e($stage) ?>"<?= selected($case['stage'], $stage) ?>><?= e($stage) ?></option><?php endforeach; ?>
                  </select>
                </label>
                <label>وضعیت<select name="status"><option value="open"<?= selected($case['status'], 'open') ?>>باز</option><option value="closed"<?= selected($case['status'], 'closed') ?>>بسته</option><option value="referred"<?= selected($case['status'], 'referred') ?>>ارجاع شده</option></select></label>
                <label>شماره شکایت<input name="complaint_number" value="<?= e($case['complaint_number']) ?>"></label>
                <label>تاریخ ابلاغ<input name="notice_date" value="<?= e(!empty($case['notice_date']) ? jdate($case['notice_date']) : '') ?>" placeholder="۱۴۰۳/۰۱/۰۱"></label>
                <label>تاریخ دادگاه<input name="court_date" value="<?= e(!empty($case['court_date']) ? jdate($case['court_date']) : '') ?>" placeholder="۱۴۰۳/۰۱/۰۱"></label>
                <label>تاریخ جلسه رسیدگی<input name="hearing_date" value="<?= e(!empty($case['hearing_date']) ? jdate($case['hearing_date']) : '') ?>" placeholder="۱۴۰۳/۰۱/۰۱"></label>
                <label>هزینه حقوقی<input name="expense_amount" data-money value="<?= e(number_format((float) $case['expense_amount'], 0)) ?>"></label>
                <label class="full">علت هزینه<input name="expense_reason" value="<?= e($case['expense_reason'] ?? '') ?>" placeholder="برای هر هزینه، علت را ثبت کنید"></label>
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
