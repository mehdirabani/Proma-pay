<?php
$legalStages = app_config('legal_stages', []);
$readyCases = $readyCases ?? [];
$filedCases = $filedCases ?? [];
$referredCases = $referredCases ?? [];
$eligible = $eligible ?? [];
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
          <?php foreach (['open', 'referred', 'under_legal_review', 'warning_prepared', 'draft_prepared', 'external_submission_confirmed', 'closed', 'archived'] as $status): ?>
            <option value="<?= e($status) ?>"<?= selected($_GET['status'] ?? '', $status) ?>><?= e(status_label($status)) ?></option>
          <?php endforeach; ?>
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
        <a class="btn small secondary" data-xlsx-export href="<?= e(url('lawyer/export')) ?>"><?= proma_icon('download') ?> خروجی Excel</a>
        <span class="proma-ajax-status" data-ajax-status></span>
      </div>
    </form>
  </div>
</section>

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
  <div class="card-header"><h2>قراردادهای واجد شرایط بررسی حقوقی</h2><span class="badge warning"><?= to_persian_digits(count($eligible)) ?></span></div>
  <div class="card-body">
    <div class="notice info">تشکیل پرونده در این بخش داخلی است. اخطار قراردادی و پیش‌نویس‌ها تا زمان ثبت واقعی و تایید صریح در مرجع مربوط، ابلاغ قضایی یا دادخواست ثبت‌شده نیستند.</div>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th scope="col">قرارداد</th><th scope="col">مشتری</th><th scope="col">قدیمی‌ترین سررسید</th><th scope="col">روز تاخیر</th><th scope="col">اقساط معوق</th><th scope="col">مانده موثر</th><th scope="col">سیاست</th><th scope="col">اقدام</th></tr></thead>
      <tbody>
      <?php foreach ($eligible as $item): ?>
        <tr>
          <td><?= e($item['contract_number'] ?? '') ?></td>
          <td><?= e($item['customer_name'] ?? '') ?><br><span class="badge muted"><?= to_persian_digits($item['mobile'] ?? '') ?></span></td>
          <td><?= e(jdate($item['oldest_due_date'] ?? '')) ?></td>
          <td><?= to_persian_digits($item['delay_days'] ?? 0) ?></td>
          <td><?= to_persian_digits($item['overdue_count'] ?? 0) ?></td>
          <td><?= money_toman($item['overdue_amount'] ?? 0) ?></td>
          <td>نسخه <?= to_persian_digits($item['policy_version'] ?? 0) ?></td>
          <td><button class="btn small warning" type="button" data-open-modal="legal-initiate-<?= (int) $item['id'] ?>">تشکیل پرونده داخلی</button></td>
        </tr>
        <div class="modal" id="legal-initiate-<?= (int) $item['id'] ?>"><div class="modal-content"><div class="modal-header"><h3>تشکیل پرونده داخلی</h3><button class="icon-btn" type="button" data-close-modal>×</button></div><form method="post" action="<?= e(url('lawyer/create')) ?>"><div class="modal-body form-grid"><?= csrf_field() ?><input type="hidden" name="contract_id" value="<?= (int) $item['id'] ?>"><input type="hidden" name="legal_case_request_uuid" value="<?= e(bin2hex(random_bytes(16))) ?>"><div class="full notice warning">این عمل پرونده داخلی ایجاد می‌کند و به معنای ثبت رسمی قضایی یا ابلاغ رسمی نیست.</div><label class="full">یادداشت بررسی اولیه<textarea name="notes" rows="4" placeholder="علت بررسی و اقدامات داخلی"></textarea></label></div><div class="modal-footer"><button class="btn warning" type="submit">ایجاد پرونده داخلی</button><button class="btn secondary" type="button" data-close-modal>بستن</button></div></form></div></div>
      <?php endforeach; ?>
      <?php if (!$eligible): ?><tr><td colspan="8" class="empty">در محدوده بررسی فعلی، قرارداد واجد شرایطی یافت نشد.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<section class="card" style="margin-top:16px">
  <div class="card-header"><h2>پرونده‌های حقوقی</h2></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th scope="col">قرارداد</th><th scope="col">مشتری</th><th scope="col">مرحله</th><th scope="col">شماره شکایت</th><th scope="col">هزینه</th><th scope="col">وضعیت</th><th scope="col">عملیات</th></tr></thead>
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
            <a class="btn small info" href="<?= e(url('legal/show/' . (int) $case['id'])) ?>">مدیریت پرونده</a>
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
</div>
