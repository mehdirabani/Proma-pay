<?php
$center = $center ?? [];
$metrics = $center['metrics'] ?? [];
$worklist = $center['worklist'] ?? ['items' => [], 'total' => 0, 'page' => 1, 'pages' => 1, 'per_page' => 25];
$criteria = $criteria ?? OverdueFilterCriteria::fromRequest($_GET);
$operators = $center['operators'] ?? [];
$pageUrl = static function (int $page) use ($criteria): string {
    $params = $criteria->queryParameters(false);
    if ($page > 1) $params['page'] = $page;
    return url('collections', $params);
};
$actionLabels = ['call_customer' => 'تماس با مشتری', 'call_guarantor' => 'تماس با ضامن', 'check_promise' => 'بررسی قول پرداخت', 'send_reminder' => 'ارسال یادآوری مجاز', 'review_payment' => 'بررسی پرداخت', 'review_legal_referral' => 'بررسی ارجاع حقوقی', 'legal_followup' => 'پیگیری حقوقی', 'manager_review' => 'بررسی مدیریتی', 'assign_case' => 'تعیین مسئول'];
$promiseLabels = ['active' => 'فعال', 'fulfilled' => 'محقق‌شده', 'partially_fulfilled' => 'بخشی محقق‌شده', 'broken' => 'منقضی / نقض‌شده', 'needs_review' => 'نیازمند بررسی'];
?>
<div class="proma-collections-workspace">
  <header class="proma-page-intro">
    <div><span class="proma-eyebrow">پیگیری مطالبات</span><h1>مرکز عملیات وصول</h1><p>هر قرارداد معوق یک پروندهٔ عملیاتی است؛ مالک، اقدام بعدی و وضعیت مالی آن را از این صف پیگیری کنید.</p></div>
    <div class="proma-contracts-intro__actions">
      <?php if (Auth::role() === 'admin'): ?><a class="btn secondary" href="<?= e(url('collections/performance')) ?>">گزارش عملکرد</a><a class="btn secondary" href="<?= e(url('settings', ['tab' => 'credit'])) ?>">مسئول صف تخصیص</a><form method="post" action="<?= e(url('collections/runAutomation')) ?>"><?= csrf_field() ?><button class="btn" type="submit">به‌روزرسانی صف‌ها</button></form><?php endif; ?>
      <a class="btn secondary" href="<?= e(url('overdue')) ?>">سررسیدهای گذشته</a>
    </div>
  </header>
  <section class="card proma-collection-center">
    <div class="card-body">
      <?php if (!empty($center['warnings'])): ?><div class="notice warning" role="status">برخی شاخص‌ها به‌دلیل خطای داده یا ساختار کامل نیستند. خروجی ناقص در گزارش سامانه ثبت شده است.</div><?php endif; ?>
      <?php if (Auth::role() === 'admin' && (int) ($metrics['unassigned'] ?? 0) > 0 && empty($center['assignment_manager_configured'])): ?><div class="notice warning" role="alert">برای صف پرونده‌های بدون مسئول، مدیر پیگیری مشخص نشده است؛ تا زمان تنظیم، پرونده‌ها در صف واگذاری مدیران باقی می‌مانند. <a href="<?= e(url('settings', ['tab' => 'credit'])) ?>">تعیین مسئول صف تخصیص</a></div><?php endif; ?>
      <div class="kpi-grid">
        <article class="kpi-card primary"><span>پرونده‌های معوق در فیلتر</span><strong><?= to_persian_digits($worklist['total'] ?? 0) ?></strong></article>
        <article class="kpi-card danger"><span>اقدام‌های سررسیدگذشته</span><strong><?= to_persian_digits($metrics['actions_overdue'] ?? 0) ?></strong></article>
        <article class="kpi-card warning"><span>اقدام‌های موعد امروز</span><strong><?= to_persian_digits($metrics['actions_today'] ?? 0) ?></strong></article>
        <article class="kpi-card"><span>قول‌های پرداخت امروز</span><strong><?= to_persian_digits($metrics['promises_today'] ?? 0) ?></strong></article>
        <article class="kpi-card danger"><span>قول‌های منقضی و نقض‌شده</span><strong><?= to_persian_digits($metrics['promises_broken'] ?? 0) ?></strong></article>
        <article class="kpi-card"><span>بدون تماس در ۷ روز</span><strong><?= to_persian_digits($metrics['no_contact_7d'] ?? 0) ?></strong></article>
        <article class="kpi-card"><span>کل اقدام‌های باز</span><strong><?= to_persian_digits($metrics['open_tasks'] ?? 0) ?></strong></article>
        <?php if (Auth::role() === 'admin'): ?><article class="kpi-card warning"><span>نیازمند تخصیص مالک</span><strong><?= to_persian_digits($metrics['unassigned'] ?? 0) ?></strong></article><article class="kpi-card"><span>واجد بررسی حقوقی</span><strong><?= to_persian_digits($metrics['legal_review'] ?? 0) ?></strong></article><?php endif; ?>
      </div>

      <section class="card collection-queue" aria-label="فیلتر پرونده‌های وصول">
        <div class="card-header"><h3>فیلتر پرونده‌ها</h3><span><?= to_persian_digits($criteria->activeFilterCount()) ?> فیلتر فعال</span></div>
        <div class="card-body"><form class="form-grid two" method="get" action="<?= e(url('collections')) ?>">
          <label>جست‌وجو<input name="q" value="<?= e($criteria->search) ?>" placeholder="نام مشتری یا شماره قرارداد"></label>
          <label>بازهٔ تأخیر<select name="bucket"><option value="">همهٔ معوقات</option><?php foreach (['1-7' => '۱ تا ۷ روز', '8-30' => '۸ تا ۳۰ روز', '30+' => 'بیش از ۳۰ روز'] as $value => $label): ?><option value="<?= e($value) ?>" <?= $criteria->bucket === $value ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
          <label>مرتب‌سازی<select name="sort"><option value="oldest" <?= $criteria->sort === 'oldest' ? 'selected' : '' ?>>قدیمی‌ترین سررسید</option><option value="delay_desc" <?= $criteria->sort === 'delay_desc' ? 'selected' : '' ?>>بیشترین تأخیر</option><option value="overdue_count_desc" <?= $criteria->sort === 'overdue_count_desc' ? 'selected' : '' ?>>بیشترین تعداد قسط</option><option value="amount_desc" <?= $criteria->sort === 'amount_desc' ? 'selected' : '' ?>>بیشترین مبلغ برآوردی</option><option value="name_asc" <?= $criteria->sort === 'name_asc' ? 'selected' : '' ?>>نام مشتری</option></select></label>
          <?php if (Auth::role() === 'admin'): ?><label>مسئول<select name="operator_id"><option value="">همهٔ مسئولان</option><option value="0" <?= $criteria->operatorId === 0 ? 'selected' : '' ?>>بدون مسئول معتبر</option><?php foreach ($operators as $operator): ?><option value="<?= (int) $operator['id'] ?>" <?= $criteria->operatorId === (int) $operator['id'] ? 'selected' : '' ?>><?= e($operator['full_name']) ?></option><?php endforeach; ?></select></label><?php endif; ?>
          <label>تعداد در صفحه<select name="per_page"><?php foreach ([25,50,100] as $size): ?><option value="<?= $size ?>" <?= $criteria->perPage === $size ? 'selected' : '' ?>><?= to_persian_digits($size) ?></option><?php endforeach; ?></select></label>
          <div class="form-actions"><button class="btn" type="submit">اعمال فیلتر</button><a class="btn secondary" href="<?= e(url('collections')) ?>">پاک‌کردن</a></div>
        </form></div>
      </section>

      <section class="card collection-queue" aria-label="صف اصلی پرونده‌های وصول">
        <div class="card-header"><div><h3>صف اصلی وصول</h3><small>نمایش <?= to_persian_digits($worklist['from'] ?? 0) ?> تا <?= to_persian_digits($worklist['to'] ?? 0) ?> از <?= to_persian_digits($worklist['total'] ?? 0) ?> قرارداد</small></div><span>صف‌بندی سمت سرور</span></div>
        <div class="card-body">
          <?php foreach (($worklist['items'] ?? []) as $row):
            $contractId = (int) $row['contract_id'];
            $task = $row['next_task'] ?? null;
            $promise = $row['active_promise'] ?? null;
            $last = $row['last_followup'] ?? null;
          ?>
            <article class="collection-row collection-worklist-row">
              <div class="collection-worklist-main">
                <strong><?= e($row['customer_name'] ?? '') ?> <small>قرارداد <?= e($row['contract_number'] ?? '') ?></small></strong>
                <small><?= to_persian_digits((int) ($row['overdue_count'] ?? 0)) ?> قسط معوق · قدیمی‌ترین سررسید <?= e(jdate($row['oldest_due_date'] ?? '')) ?> · <?= to_persian_digits((int) ($row['max_overdue_days'] ?? 0)) ?> روز تأخیر</small>
                <small>ماندهٔ رسمی سررسیدشده: <?= money_toman($row['total_overdue_amount'] ?? 0) ?><?php if (($row['calculation_warnings'] ?? [])): ?> · <span class="text-danger">نیازمند بررسی مالی</span><?php endif; ?></small>
                <small>مسئول: <?= e($row['owner_valid'] ? ($row['assigned_operator_name'] ?? '') : 'نیازمند تخصیص') ?> · مرحله: <?= e($row['collection_stage'] ?? '') ?> · اولویت: <?= e($row['priority'] ?? '') ?></small>
                <small>آخرین پیگیری: <?= e($last['call_result'] ?? 'ثبت نشده') ?><?php if ($last && empty($last['result_code'])): ?> (قدیمی/طبقه‌بندی‌نشده)<?php endif; ?></small>
                <small>اقدام بعدی: <?= e($actionLabels[$task['action_type'] ?? ''] ?? 'ثبت نشده') ?><?php if (!empty($task['due_at'])): ?> · موعد <?= e(jdatetime($task['due_at'])) ?><?php endif; ?></small>
                <small>قول پرداخت: <?php if ($promise): ?><?= e($promiseLabels[$promise['derived_status'] ?? 'needs_review'] ?? 'نیازمند بررسی') ?> · موعد <?= e(jdate($promise['promise_payment_date'] ?? '')) ?><?php if (!empty($promise['promise_amount_toman'])): ?> · مبلغ <?= money_toman($promise['promise_amount_toman']) ?><?php else: ?> · مبلغ ثبت نشده<?php endif; ?><?php else: ?>ندارد<?php endif; ?></small>
              </div>
              <div class="collection-worklist-actions">
                <a class="btn small secondary" href="<?= e(url('collections/timeline/' . $contractId)) ?>">تاریخچه</a>
                <?php if (Auth::role() === 'admin'): ?><details><summary class="btn small secondary">واگذاری</summary><form method="post" action="<?= e(url('collections/assignContract/' . $contractId)) ?>" class="form-grid">
                  <?= csrf_field() ?><label>اپراتور فعال<select name="operator_id" required><option value="">انتخاب مسئول</option><?php foreach ($operators as $operator): ?><option value="<?= (int) $operator['id'] ?>" <?= (int) ($row['assigned_operator_id'] ?? 0) === (int) $operator['id'] ? 'selected' : '' ?>><?= e($operator['full_name']) ?></option><?php endforeach; ?></select></label><button class="btn small" type="submit">ثبت واگذاری</button>
                </form></details><?php endif; ?>
                <details><summary class="btn small">ثبت پیگیری</summary><form method="post" action="<?= e(url('collections/followup')) ?>" class="form-grid two">
                  <?= csrf_field() ?><input type="hidden" name="contract_id" value="<?= $contractId ?>"><?php if ($task): ?><input type="hidden" name="task_id" value="<?= (int) $task['id'] ?>"><?php endif; ?>
                  <label>نوع اقدام<select name="action_type"><option value="customer_call">تماس با مشتری</option><option value="guarantor_call">تماس با ضامن</option><option value="reminder">یادآوری مجاز</option><option value="payment_review">بررسی پرداخت</option><option value="legal_review">بررسی حقوقی</option><option value="other_authorized">سایر اقدام مجاز</option></select></label>
                  <label>مخاطب<select name="contact_target"><option value="customer">مشتری</option><option value="guarantor">ضامن</option><option value="other_authorized">سایر فرد مجاز</option></select></label>
                  <label>نتیجه<select name="result_code" required><option value="contacted">تماس برقرار شد</option><option value="no_answer">پاسخ نداد</option><option value="unreachable">در دسترس نبود</option><option value="promise_given">قول پرداخت داد</option><option value="requested_extension">درخواست مهلت</option><option value="disputed">اعتراض یا اختلاف</option><option value="needs_followup">نیازمند پیگیری</option><option value="referred_to_legal">پیشنهاد بررسی حقوقی</option><option value="other">سایر</option></select></label>
                  <label>تاریخ قول پرداخت<input name="promise_payment_date" class="jalali-date" autocomplete="off" placeholder="انتخاب تاریخ"></label>
                  <label>مبلغ قول (تومان)<input name="promise_amount_toman" inputmode="numeric" min="1"></label>
                  <label>اقدام بعدی<select name="next_action_type" required><?php foreach ($actionLabels as $value => $label): if ($value === 'assign_case') continue; ?><option value="<?= e($value) ?>"><?= e($label) ?></option><?php endforeach; ?></select></label>
                  <label>موعد اقدام بعدی<input name="next_followup_date" class="jalali-date" autocomplete="off" required placeholder="انتخاب تاریخ"></label>
                  <label class="full">یادداشت<textarea name="notes" rows="2" maxlength="2000"></textarea></label><button class="btn" type="submit">ثبت در تاریخچهٔ مشترک</button>
                </form></details>
              </div>
            </article>
          <?php endforeach; ?>
          <?php if (empty($worklist['items'])): ?><div class="empty">پرونده‌ای با این فیلتر پیدا نشد.</div><?php endif; ?>
          <?php if (($worklist['pages'] ?? 1) > 1): ?><nav class="pagination" aria-label="صفحه‌بندی پرونده‌ها"><?php if (($worklist['page'] ?? 1) > 1): ?><a class="btn small secondary" href="<?= e($pageUrl((int) $worklist['page'] - 1)) ?>">قبلی</a><?php endif; ?><span>صفحهٔ <?= to_persian_digits($worklist['page']) ?> از <?= to_persian_digits($worklist['pages']) ?></span><?php if (($worklist['page'] ?? 1) < ($worklist['pages'] ?? 1)): ?><a class="btn small secondary" href="<?= e($pageUrl((int) $worklist['page'] + 1)) ?>">بعدی</a><?php endif; ?></nav><?php endif; ?>
        </div>
      </section>

      <div class="collection-queues">
        <section class="card collection-queue"><div class="card-header"><h3>اقدام‌های باز</h3></div><div class="card-body">
          <?php foreach (($center['tasks'] ?? []) as $task): ?><article class="collection-row"><div><strong><?= e($task['customer_name'] ?? '') ?> · <?= e($actionLabels[$task['action_type'] ?? ''] ?? ($task['stage'] ?? '')) ?></strong><small>قرارداد <?= e($task['contract_number'] ?? '') ?> · مسئول: <?= e($task['assignee_name'] ?? 'تخصیص‌نیافته') ?> · موعد <?= e(jdatetime($task['due_at'] ?? '')) ?></small></div><div class="actions"><a class="btn small secondary" href="<?= e(url('collections/timeline/' . (int) $task['contract_id'])) ?>">خط زمانی</a><?php if (($task['action_type'] ?? '') !== 'assign_case'): ?><details><summary class="btn small">تکمیل اقدام</summary><form method="post" action="<?= e(url('collections/completeTask/' . (int) $task['id'])) ?>" class="form-grid two">
            <?= csrf_field() ?><label>نتیجه<select name="completion_result" required><option value="action_completed">اقدام انجام شد</option><option value="promise_waiting">در انتظار قول پرداخت</option><option value="payment_review">بررسی پرداخت</option><option value="customer_unreachable">مشتری در دسترس نبود</option><option value="legal_review_required">نیازمند بررسی حقوقی</option><option value="other">سایر</option></select></label><label>اقدام بعدی<select name="next_action_type"><option value="call_customer">تماس با مشتری</option><option value="call_guarantor">تماس با ضامن</option><option value="check_promise">بررسی قول پرداخت</option><option value="send_reminder">ارسال یادآوری مجاز</option><option value="review_payment">بررسی پرداخت</option><option value="review_legal_referral">بررسی ارجاع حقوقی</option><option value="legal_followup">پیگیری حقوقی</option><option value="manager_review">بررسی مدیریتی</option></select></label><label>موعد اقدام بعدی<input name="next_followup_date" class="jalali-date" autocomplete="off" placeholder="انتخاب تاریخ"></label><label>یادداشت<input name="completion_note" maxlength="500"></label><button class="btn" type="submit">ثبت نتیجه</button>
          </form></details><?php elseif (Auth::role() === 'admin'): ?><span class="badge warning">در انتظار تخصیص</span><?php endif; ?></div></article><?php endforeach; ?>
          <?php if (empty($center['tasks'])): ?><div class="empty">وظیفهٔ بازی در این فهرست نیست.</div><?php endif; ?>
        </div></section>
        <section class="card collection-queue"><div class="card-header"><h3>صف‌های فوری</h3></div><div class="card-body">
          <?php foreach ([['critical','پرونده‌های بحرانی'],['promises','قول‌های امروز'],['no_contact','بدون تماس اخیر']] as [$key,$label]): ?><h4><?= e($label) ?></h4><?php foreach (($center[$key] ?? []) as $row): ?><article class="collection-row"><div><strong><?= e($row['customer_name'] ?? '') ?></strong><small>قرارداد <?= e($row['contract_number'] ?? '') ?> · <?= to_persian_digits((int) ($row['max_overdue_days'] ?? 0)) ?> روز تأخیر</small></div><a class="btn small secondary" href="<?= e(url('collections/timeline/' . (int) ($row['contract_id'] ?? 0))) ?>">تاریخچه</a></article><?php endforeach; ?><?php if (empty($center[$key])): ?><div class="empty">موردی در این صف نیست.</div><?php endif; ?><?php endforeach; ?>
          <?php if (Auth::role() === 'admin'): ?><h4>واجد بررسی حقوقی (بدون ارجاع خودکار)</h4><?php foreach (($center['legal'] ?? []) as $row): ?><article class="collection-row"><div><strong><?= e($row['customer_name'] ?? '') ?></strong><small>قرارداد <?= e($row['contract_number'] ?? '') ?> · <?= to_persian_digits((int) ($row['max_overdue_days'] ?? 0)) ?> روز تأخیر</small></div><a class="btn small secondary" href="<?= e(url('collections/timeline/' . (int) ($row['contract_id'] ?? 0))) ?>">تاریخچه</a></article><?php endforeach; ?><?php endif; ?>
        </div></section>
      </div>
      <p class="muted">مبالغ از موتور مالی جاری محاسبه می‌شوند. مرتب‌سازی مبلغ در صف برآوردی است و ترتیب دقیق مبلغ رسمی تنها پس از محاسبهٔ صفحهٔ قابل نمایش تضمین نمی‌شود. مراحل حقوقی از سیاست جاری می‌آیند و ارجاع خودکار انجام نمی‌شود.</p>
    </div>
  </section>
</div>
