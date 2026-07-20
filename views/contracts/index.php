<?php
$readOnly = $readOnly ?? false;
$contractsRoute = $contractsRoute ?? ($readOnly ? 'portal/guaranteed' : 'contracts');
$pageTitle = $readOnlyTitle ?? ($readOnly ? 'قراردادهای ضمانت شده' : 'فهرست قراردادها');
$singleOperator = !$readOnly && count($operators ?? []) === 1 ? $operators[0] : null;
$viewMode = in_array($_GET['view'] ?? '', ['cards', 'list'], true) ? $_GET['view'] : 'cards';
$pagination = $pagination ?? ['total' => count($contracts ?? []), 'page' => 1, 'pages' => 1, 'per_page' => count($contracts ?? []) ?: 24];
$pageUrl = function ($page) use ($contractsRoute, $viewMode) {
    $params = [
        'q' => $_GET['q'] ?? null,
        'view' => $viewMode,
        'page' => (int) $page > 1 ? (int) $page : null,
    ];
    return url($contractsRoute, array_filter($params, fn($value) => $value !== null && $value !== ''));
};
$contractTrendLabels = [];
$contractTrendStart = (new DateTime('first day of this month'))->modify('-5 months');
for ($i = 0; $i < 6; $i++) {
    $contractTrendLabels[] = mb_substr(jdate((clone $contractTrendStart)->modify('+' . $i . ' months')->format('Y-m-01')), 0, 7, 'UTF-8');
}
?>
<section class="card">
  <div class="card-header card-no-border">
    <div class="header-top">
      <h2><?= e($pageTitle) ?></h2>
      <div class="actions">
        <a class="btn small <?= $viewMode === 'cards' ? '' : 'secondary' ?>" href="<?= e(url($contractsRoute, array_filter(['q' => $_GET['q'] ?? null, 'view' => 'cards', 'page' => $_GET['page'] ?? null]))) ?>">کارت‌ها</a>
        <a class="btn small <?= $viewMode === 'list' ? '' : 'secondary' ?>" href="<?= e(url($contractsRoute, array_filter(['q' => $_GET['q'] ?? null, 'view' => 'list', 'page' => $_GET['page'] ?? null]))) ?>">لیست</a>
        <?php if (!$readOnly): ?><button class="btn" type="button" data-open-modal="create-contract">افزودن قرارداد</button><?php endif; ?>
      </div>
    </div>
  </div>
  <div class="card-body">
    <form method="get" action="<?= e(url($contractsRoute)) ?>" class="form-grid three" data-ajax-filter data-ajax-target="[data-ajax-results='contracts']">
      <input type="hidden" name="route" value="<?= e($contractsRoute) ?>">
      <input type="hidden" name="view" value="<?= e($viewMode) ?>">
      <label class="full">جستجو در قرارداد و مشتری<input name="q" value="<?= e($_GET['q'] ?? '') ?>" placeholder="شماره قرارداد، نام، کد ملی یا موبایل"></label>
      <div class="actions"><button class="btn secondary" type="submit">جستجو</button><span class="proma-ajax-status" data-ajax-status></span></div>
    </form>
  </div>
</section>

<div data-ajax-results="contracts">
<div class="proma-list-meta">
  <span class="badge info">کل قراردادها: <?= to_persian_digits($pagination['total'] ?? count($contracts ?? [])) ?></span>
  <span class="badge muted">صفحه <?= to_persian_digits($pagination['page'] ?? 1) ?> از <?= to_persian_digits($pagination['pages'] ?? 1) ?></span>
</div>
<section class="card">
  <?php if ($contracts && $viewMode === 'cards'): ?>
    <div class="card-body pt-0">
      <div class="proma-contract-card-grid">
        <?php foreach ($contracts as $cardContract): ?>
          <?php
          $stats = Contract::installmentStats((int) $cardContract['id']);
          $trend = Payment::monthlyTrendForContract((int) $cardContract['id']);
          $timeline = Payment::recentForContract((int) $cardContract['id'], 3);
          $remainingCount = (int) ($stats['active_remaining'] ?? max(0, (int) $stats['total'] - (int) $stats['paid'] - (int) ($stats['cancelled'] ?? 0)));
          $financedAmount = max(0, (float) $cardContract['principal_amount'] - (float) ($cardContract['down_payment_amount'] ?? 0));
          $progress = (int) $stats['total'] > 0 ? (int) round(((int) $stats['paid'] / (int) $stats['total']) * 100) : 0;
          ?>
            <article class="proma-contract-card" data-contract-card data-card-href="<?= e(url('contracts/show/' . $cardContract['id'])) ?>" tabindex="0" role="link" aria-label="مشاهده جزئیات قرارداد <?= e($cardContract['contract_number']) ?>">
              <header class="proma-contract-card__header">
                <div class="proma-contract-card-main proma-contract-card__identity">
                  <span class="proma-progress-avatar" style="--progress: <?= $progress ?>">
                    <?php $cardAvatar = avatar_key_for($cardContract['avatar_key'] ?? null, $cardContract['customer_id'] ?? $cardContract['id']); ?>
                    <span class="proma-avatar-choice <?= e($cardAvatar) ?>" aria-label="<?= e($cardContract['customer_name']) ?>"><img data-avatar-image src="<?= e(avatar_asset_url($cardAvatar)) ?>" alt="آواتار <?= e($cardContract['customer_name']) ?>" loading="lazy"></span>
                  </span>
                  <div>
                    <h6><?= e($cardContract['customer_name']) ?></h6>
                    <p><?= money_toman($financedAmount) ?></p>
                  </div>
                </div>
                <div class="proma-contract-card__controls" aria-label="عملیات سریع قرارداد">
                  <?php if (!$readOnly): ?>
                    <div class="proma-card-top-actions" aria-label="عملیات سریع قرارداد">
                      <button class="proma-icon-button" type="button" data-open-modal="edit-contract-<?= (int) $cardContract['id'] ?>" title="ویرایش قرارداد" aria-label="ویرایش قرارداد"><?= proma_icon('edit') ?></button>
                      <button class="proma-icon-button danger" type="button" data-open-modal="delete-contract-<?= (int) $cardContract['id'] ?>" title="حذف دائمی قرارداد" aria-label="حذف دائمی قرارداد"><?= proma_icon('trash') ?></button>
                    </div>
                  <?php endif; ?>
                </div>
                <div class="proma-contract-card__meta">
                  <span class="proma-contract-badge"><?= e($cardContract['contract_number']) ?></span>
                  <span class="badge <?= e(badge_class($cardContract['status'] ?? '')) ?>"><?= e(status_label($cardContract['status'] ?? '')) ?></span>
                </div>
              </header>
            <div class="proma-contract-stats four">
              <span><strong><?= to_persian_digits($stats['total']) ?></strong><small>کل اقساط</small></span>
              <span><strong><?= to_persian_digits($stats['paid']) ?></strong><small>پرداخت‌شده</small></span>
              <span><strong><?= to_persian_digits($remainingCount) ?></strong><small>باقی‌مانده</small></span>
              <span><strong><?= to_persian_digits($stats['overdue']) ?></strong><small>معوق</small></span>
            </div>
            <div class="proma-contract-card-footer">
              <span class="proma-contract-card-balance">مانده: <?= money_toman($stats['outstanding']) ?></span>
              <div class="proma-contract-card-actions" aria-label="عملیات قرارداد">
                <button class="proma-icon-button info" type="button" data-open-modal="contract-chart-<?= (int) $cardContract['id'] ?>" title="مشاهده نمودار قرارداد" aria-label="مشاهده نمودار قرارداد"><?= proma_icon('chart') ?></button>
                <button class="proma-icon-button warning" type="button" data-open-modal="contract-timeline-<?= (int) $cardContract['id'] ?>" title="مشاهده تایم‌لاین قرارداد" aria-label="مشاهده تایم‌لاین قرارداد"><?= proma_icon('history') ?></button>
                <a class="proma-icon-button success" href="<?= e(url('contracts/printDocument/' . $cardContract['id'])) ?>" target="_blank" rel="noopener" title="چاپ قرارداد" aria-label="چاپ قرارداد"><?= proma_icon('printer') ?></a>
                <a class="proma-icon-button success" href="<?= e(url('contracts/booklet/' . $cardContract['id'])) ?>" target="_blank" title="چاپ دفترچه اقساط" aria-label="چاپ دفترچه اقساط"><?= proma_icon('book') ?></a>
                  <?php if (!$readOnly): ?>
                  <?php if (($cardContract['status'] ?? '') !== 'cancelled'): ?><button class="proma-icon-button danger" type="button" data-open-modal="cancel-contract-<?= (int) $cardContract['id'] ?>" title="لغو قرارداد" aria-label="لغو قرارداد"><?= proma_icon('slash') ?></button><?php endif; ?>
                <?php endif; ?>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>
  <?php if ($viewMode === 'list'): ?>
  <div class="table-wrap">
    <table>
      <thead><tr><th>شماره</th><th>مشتری</th><th>مبالغ قرارداد</th><th>سود</th><th>اقساط</th><th>ضامنان</th><th>وضعیت</th><th>عملیات</th></tr></thead>
      <tbody>
      <?php foreach ($contracts as $contract): ?>
        <?php $guarantors = Contract::guarantors($contract['id']); ?>
        <tr data-contract-card data-card-href="<?= e(url('contracts/show/' . $contract['id'])) ?>" tabindex="0" role="link" aria-label="مشاهده جزئیات قرارداد <?= e($contract['contract_number']) ?>">
          <td><a href="<?= e(url('contracts/show/' . $contract['id'])) ?>"><?= e($contract['contract_number']) ?></a></td>
          <td><?= e($contract['customer_name']) ?><br><span class="badge muted"><?= to_persian_digits($contract['mobile']) ?></span></td>
          <?php $financedAmount = max(0, (float) $contract['principal_amount'] - (float) ($contract['down_payment_amount'] ?? 0)); ?>
          <td>
            <strong><?= money_toman($contract['principal_amount']) ?></strong><br>
            <small>پیش‌پرداخت: <?= money_toman($contract['down_payment_amount'] ?? 0) ?></small><br>
            <small>قابل تقسیط: <?= money_toman($financedAmount) ?></small>
          </td>
          <td><?= percent_label($contract['monthly_interest_rate']) ?>، <?= $contract['interest_type'] === 'compound' ? 'مرکب' : 'ساده' ?></td>
          <td><?= to_persian_digits($contract['months']) ?></td>
          <td><?= $guarantors ? e(implode('، ', array_column($guarantors, 'full_name'))) : 'ندارد' ?></td>
          <td><span class="badge <?= e(badge_class($contract['status'])) ?>"><?= e(status_label($contract['status'])) ?></span></td>
          <td class="actions">
            <?php if (!$readOnly): ?><button class="btn small secondary icon-only" type="button" data-open-modal="edit-contract-<?= (int) $contract['id'] ?>" title="ویرایش" aria-label="ویرایش"><i data-feather="edit-2"></i></button><?php endif; ?>
            <button class="btn small info" type="button" data-open-modal="contract-chart-<?= (int) $contract['id'] ?>">نمودار</button>
            <button class="btn small warning" type="button" data-open-modal="contract-timeline-<?= (int) $contract['id'] ?>">تایم‌لاین</button>
            <a class="btn small success" href="<?= e(url('contracts/printDocument/' . $contract['id'])) ?>" target="_blank" rel="noopener"><i data-feather="printer"></i> چاپ قرارداد</a>
            <?php if (!$readOnly): ?><button class="btn small info" type="button" data-open-modal="custom-installment-<?= (int) $contract['id'] ?>">قسط دلخواه</button><?php endif; ?>
            <a class="btn small success" href="<?= e(url('contracts/booklet/' . $contract['id'])) ?>" target="_blank">چاپ دفترچه</a>
            <?php if (!$readOnly && ($contract['status'] ?? '') !== 'cancelled'): ?><button class="btn small danger" type="button" data-open-modal="cancel-contract-<?= (int) $contract['id'] ?>"><i data-feather="slash"></i> لغو</button><?php endif; ?>
            <?php if (!$readOnly): ?><button class="btn small danger icon-only" type="button" data-open-modal="delete-contract-<?= (int) $contract['id'] ?>" title="حذف دائمی قرارداد" aria-label="حذف دائمی قرارداد"><i data-feather="trash-2"></i></button><?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$contracts): ?><tr><td colspan="8" class="empty">قراردادی ثبت نشده است.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</section>

<?= render_pagination($pagination, $pageUrl) ?>

<?php foreach ($contracts as $contract): ?>
  <?php
    $contractTrend = Payment::monthlyTrendForContract((int) $contract['id']);
    $contractTimeline = Payment::recentForContract((int) $contract['id'], 8);
  ?>
  <div class="modal" id="contract-chart-<?= (int) $contract['id'] ?>">
    <div class="modal-content">
      <div class="modal-header"><h3>نمودار پرداخت <?= e($contract['contract_number']) ?></h3><button class="icon-btn" type="button" data-close-modal>×</button></div>
      <div class="modal-body">
        <?php if (array_sum($contractTrend) > 0): ?>
          <canvas data-chart="line" data-title="روند پرداخت" data-labels='<?= e(json_encode($contractTrendLabels, JSON_UNESCAPED_UNICODE)) ?>' data-values='<?= e(json_encode($contractTrend)) ?>'></canvas>
        <?php else: ?>
          <div class="empty">پرداخت موفقی برای نمودار ثبت نشده است.</div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="modal" id="contract-timeline-<?= (int) $contract['id'] ?>">
    <div class="modal-content">
      <div class="modal-header"><h3>تایم‌لاین پرداخت <?= e($contract['contract_number']) ?></h3><button class="icon-btn" type="button" data-close-modal>×</button></div>
      <div class="modal-body">
        <div class="proma-payment-timeline compact">
          <?php foreach ($contractTimeline as $payment): ?>
            <div class="proma-timeline-item">
              <span class="proma-timeline-dot"></span>
              <div>
                <strong><?= money_toman($payment['amount']) ?></strong>
                <p><?= e(payment_type_label($payment['payment_type'] ?? 'installment')) ?><?= !empty($payment['installment_number']) ? ' · قسط ' . to_persian_digits($payment['installment_number']) : '' ?></p>
              </div>
              <time><?= e(jdatetime($payment['paid_at'] ?? $payment['payment_date'] ?? $payment['created_at'])) ?></time>
            </div>
          <?php endforeach; ?>
          <?php if (!$contractTimeline): ?><div class="empty">پرداخت موفقی ثبت نشده است.</div><?php endif; ?>
        </div>
      </div>
    </div>
  </div>
<?php endforeach; ?>

<?php if (!$readOnly): ?>
<div class="modal" id="create-contract">
  <div class="modal-content proma-modal-xl">
    <div class="modal-header"><h3>افزودن قرارداد</h3><button class="icon-btn" type="button" data-close-modal>×</button></div>
    <form method="post" action="<?= e(url('contracts/store')) ?>" id="create-contract-form" data-contract-form data-disable-on-submit data-preview-url="<?= e(url('contracts/preview')) ?>" data-identity-check-url="<?= e(url('contracts/checkIdentity')) ?>" novalidate>
      <div class="modal-body proma-contract-form">
        <?= csrf_field() ?>
        <input type="hidden" name="contract_request_uuid" value="<?= e(bin2hex(random_bytes(24))) ?>">
        <section class="proma-form-section">
          <div class="proma-section-title"><h4>اطلاعات قرارداد</h4><span>شماره قرارداد به صورت خودکار ساخته می‌شود.</span></div>
          <div class="form-grid three">
            <label>پیشوند قرارداد<input name="prefix" value="<?= e($settings['contract_prefix'] ?? 'Pr') ?>" dir="ltr"></label>
            <label>سریال بعدی<input value="<?= to_persian_digits($settings['contract_next_serial'] ?? '') ?>" disabled></label>
            <?php if ($singleOperator): ?>
              <label>اپراتور پیگیری<input value="<?= e($singleOperator['full_name']) ?>" disabled><input type="hidden" name="assigned_operator_id" value="<?= (int) $singleOperator['id'] ?>"></label>
            <?php else: ?>
              <label>اپراتور پیگیری
                <span class="proma-live-search" data-user-live-search data-search-url="<?= e(url('users/search', ['roles' => 'operator'])) ?>">
                  <input data-user-search-input placeholder="جستجوی نام، موبایل یا واحد اپراتور">
                  <input type="hidden" name="assigned_operator_id" data-user-id-input>
                  <span class="proma-live-results" data-user-search-results hidden></span>
                  <span class="proma-chip-row" data-user-chip></span>
                </span>
              </label>
            <?php endif; ?>
          </div>
        </section>

        <section class="proma-form-section">
          <div class="proma-section-title"><h4>اطلاعات مشتری</h4><span>حالت پیش‌فرض، مشتری موجود است.</span></div>
          <div class="proma-customer-mode" data-customer-mode role="tablist" aria-label="نوع مشتری">
            <button type="button" class="active" data-customer-mode-tab="existing" role="tab" aria-selected="true">مشتری موجود</button>
            <button type="button" data-customer-mode-tab="new" role="tab" aria-selected="false">مشتری جدید</button>
          </div>
          <div class="proma-customer-mode-panel" data-customer-mode-panel="existing">
            <label class="full">جستجوی مشتری
              <span class="proma-live-search" data-customer-live-search data-search-url="<?= e(url('contracts/search-customers')) ?>">
                <input data-customer-search-input autocomplete="off" placeholder="نام، شماره موبایل، کد ملی یا شناسه مشتری را وارد کنید">
                <input type="hidden" name="customer_id" data-customer-select>
                <span class="proma-live-results" data-customer-search-results hidden></span>
              </span>
            </label>
            <div class="proma-chip-row full" data-customer-chip></div>
          </div>
          <div class="proma-customer-mode-panel" data-customer-mode-panel="new" hidden>
            <div class="form-grid three proma-new-customer-fields" data-new-customer-fields>
              <label>نام و نام خانوادگی<input data-new-customer-field="full_name" name="new_customer_full_name" placeholder="نام مشتری"></label>
              <label>نام پدر<input data-new-customer-field="father_name" name="new_customer_father_name"></label>
              <label>محل صدور<input data-new-customer-field="issued_from" name="new_customer_issued_from"></label>
              <label>کد ملی<input data-new-customer-field="national_id" name="new_customer_national_id" inputmode="numeric"></label>
              <label>موبایل<input data-new-customer-field="mobile" name="new_customer_mobile" inputmode="tel"></label>
              <label>تلفن دوم<input data-new-customer-field="secondary_phone" name="new_customer_secondary_phone" inputmode="tel"></label>
              <label>ایمیل<input data-new-customer-field="email" name="new_customer_email" type="email" dir="ltr"></label>
              <label class="full">آدرس<input data-new-customer-field="address" name="new_customer_address"></label>
            </div>
            <div class="proma-customer-identity-status" data-customer-identity-status hidden></div>
            <div class="proma-chip-row full" data-new-customer-summary hidden></div>
          </div>
        </section>

        <section class="proma-form-section">
          <div class="proma-section-title"><h4>اطلاعات مالی</h4><span>سود فقط روی مانده قابل تقسیط محاسبه می‌شود.</span></div>
          <div class="form-grid three">
            <label>مبلغ اصل قرارداد<input name="principal_amount" data-money data-contract-principal required placeholder="مبلغ به تومان"></label>
            <label>مبلغ پیش‌پرداخت<input name="down_payment_amount" data-money data-contract-down-payment value="0" placeholder="۰ تومان"></label>
            <div class="proma-live-card">
              <span>مانده قابل تقسیط</span>
              <strong data-financed-balance>۰ تومان</strong>
            </div>
            <label>نرخ سود ماهانه<input name="monthly_interest_rate" data-contract-rate required inputmode="decimal" placeholder="درصد"></label>
            <label>تعداد اقساط<input name="months" data-contract-months required inputmode="numeric" value="6"></label>
            <div>
              <span class="field-title">نوع سود</span>
              <div class="switch-options" data-exclusive>
                <label><input type="checkbox" name="interest_type" value="simple"><span>ساده ماهانه</span></label>
                <label><input type="checkbox" name="interest_type" value="compound" checked><span>مرکب ماهانه</span></label>
              </div>
            </div>
          </div>
          <div class="proma-preview-grid" data-contract-preview>
            <span><small>مبلغ اصل قرارداد</small><strong data-preview-principal>۰ تومان</strong></span>
            <span><small>مبلغ پیش‌پرداخت</small><strong data-preview-down-payment>۰ تومان</strong></span>
            <span><small>مانده قابل تقسیط</small><strong data-preview-financed>۰ تومان</strong></span>
            <span><small>مبلغ تقریبی هر قسط</small><strong data-preview-installment>۰ تومان</strong></span>
            <span><small>مجموع قابل پرداخت</small><strong data-preview-total>۰ تومان</strong></span>
          </div>
          <div class="proma-inline-error" data-contract-error hidden></div>
        </section>

        <section class="proma-form-section">
          <div class="proma-section-title"><h4>زمان‌بندی اقساط</h4><span>تاریخ‌ها در فرم شمسی هستند و در دیتابیس میلادی ذخیره می‌شوند.</span></div>
          <div class="form-grid two">
            <label>تاریخ شروع<input name="start_date" required value="<?= e($defaultStartDate ?? '') ?>" placeholder="۱۴۰۳/۰۱/۰۱"></label>
            <label>نخستین سررسید<input name="first_due_date" required value="<?= e($defaultFirstDueDate ?? '') ?>" placeholder="۱۴۰۳/۰۲/۰۱"></label>
          </div>
        </section>

        <section class="proma-form-section" data-repeater="items">
          <div class="proma-section-title">
            <h4>مشخصات کالای امانت</h4>
            <button class="btn small secondary" type="button" data-repeater-add>افزودن کالا</button>
          </div>
          <div class="proma-repeat-list" data-repeater-list>
            <div class="proma-repeat-row" data-repeater-row>
              <div class="form-grid four">
                <label>مدل کالا<input name="items[0][product_model]" placeholder="مثلاً iPhone 13"></label>
                <label>IMEI 1<input name="items[0][imei_1]" dir="ltr"></label>
                <label>IMEI 2<input name="items[0][imei_2]" dir="ltr"></label>
                <label>توضیحات کالا<input name="items[0][description]"></label>
              </div>
              <button class="icon-btn danger" type="button" data-repeater-remove title="حذف">×</button>
            </div>
          </div>
          <template data-repeater-template>
            <div class="proma-repeat-row" data-repeater-row>
              <div class="form-grid four">
                <label>مدل کالا<input name="items[__INDEX__][product_model]"></label>
                <label>IMEI 1<input name="items[__INDEX__][imei_1]" dir="ltr"></label>
                <label>IMEI 2<input name="items[__INDEX__][imei_2]" dir="ltr"></label>
                <label>توضیحات کالا<input name="items[__INDEX__][description]"></label>
              </div>
              <button class="icon-btn danger" type="button" data-repeater-remove title="حذف">×</button>
            </div>
          </template>
        </section>

        <section class="proma-form-section">
          <div class="proma-section-title"><h4>شرایط ضمانت و پرداخت اقساط</h4></div>
          <div class="form-grid four">
            <label>نوع ضمانت
              <select name="guarantee[guarantee_type]" data-guarantee-type>
                <option value="">انتخاب کنید</option>
                <option value="چک">چک</option>
                <option value="سفته">سفته</option>
                <option value="چک و سفته">چک و سفته</option>
                <option value="ضامن">ضامن</option>
                <option value="سایر">سایر</option>
              </select>
            </label>
            <label>تعداد ضمانت<input name="guarantee[guarantee_count]" value="1" inputmode="numeric"></label>
            <label>شناسه / شماره سریال<input name="guarantee[guarantee_serial]" dir="ltr"></label>
            <label data-guarantee-other hidden>توضیح نوع ضمانت<input name="guarantee[guarantee_type_other]"></label>
            <label class="full">توضیحات ضمانت<textarea name="guarantee[guarantee_description]"></textarea></label>
          </div>
        </section>

        <section class="proma-form-section" data-repeater="guarantor_people">
          <div class="proma-section-title">
            <h4>مشخصات ضامن‌ها</h4>
            <button class="btn small secondary" type="button" data-repeater-add>افزودن ضامن</button>
          </div>
          <div class="proma-repeat-list" data-repeater-list></div>
          <template data-repeater-template>
            <div class="proma-repeat-row" data-repeater-row>
              <div class="form-grid four">
                <label>نام و نام خانوادگی<input name="guarantor_people[__INDEX__][full_name]"></label>
                <label>نام پدر<input name="guarantor_people[__INDEX__][father_name]"></label>
                <label>شماره ملی<input name="guarantor_people[__INDEX__][national_id]" inputmode="numeric"></label>
                <label>شماره تماس<input name="guarantor_people[__INDEX__][mobile]" inputmode="tel"></label>
                <label>نسبت با مشتری<input name="guarantor_people[__INDEX__][relationship]"></label>
                <label class="full">آدرس<input name="guarantor_people[__INDEX__][address]"></label>
                <label class="full">توضیحات<input name="guarantor_people[__INDEX__][description]"></label>
              </div>
              <button class="icon-btn danger" type="button" data-repeater-remove title="حذف">×</button>
            </div>
          </template>
        </section>

          <section class="proma-form-section">
            <div class="proma-section-title"><h4>ضامنان</h4><span>مشتری اصلی نمی‌تواند ضامن خودش باشد.</span></div>
            <label class="full">جستجوی ضامن
              <span class="proma-live-search proma-guarantor-picker" data-guarantor-live-search data-search-url="<?= e(url('contracts/search-guarantors')) ?>">
                <input data-guarantor-search-input autocomplete="off" placeholder="نام، موبایل، کد ملی یا شماره مشتری را وارد کنید">
                <span class="proma-live-results" data-guarantor-search-results hidden></span>
                <span class="proma-chip-row" data-guarantor-chips></span>
              </span>
            </label>
        </section>

        <section class="proma-form-section">
          <label class="full">یادداشت<textarea name="notes"></textarea></label>
        </section>
      </div>
      <div class="modal-footer"><button class="btn" type="submit">ثبت و ساخت اقساط</button><button class="btn secondary" type="button" data-close-modal>بستن</button></div>
    </form>
  </div>
</div>

<?php foreach ($contracts as $contract): ?>
  <?php
    $guarantors = Contract::guarantors($contract['id']);
    $contractItems = ContractDocument::items((int) $contract['id']);
    $contractGuarantees = ContractDocument::guarantees((int) $contract['id']);
    $contractGuarantee = $contractGuarantees[0] ?? [];
    $contractGuarantorPeople = ContractDocument::guarantorPeople((int) $contract['id']);
  ?>
  <div class="modal" id="edit-contract-<?= (int) $contract['id'] ?>">
    <div class="modal-content proma-modal-xl">
      <div class="modal-header"><h3>ویرایش قرارداد</h3><button class="icon-btn" type="button" data-close-modal>×</button></div>
      <form method="post" action="<?= e(url('contracts/update/' . $contract['id'])) ?>" data-contract-form data-preview-url="<?= e(url('contracts/preview')) ?>" novalidate>
        <div class="modal-body proma-contract-form">
          <?= csrf_field() ?>
          <section class="proma-form-section">
            <div class="proma-section-title"><h4>اطلاعات قرارداد</h4><span><?= e($contract['contract_number']) ?></span></div>
            <div class="form-grid two">
              <label>مشتری
                <span class="proma-live-search" data-customer-live-search data-search-url="<?= e(url('contracts/search-customers')) ?>">
                  <input data-customer-search-input autocomplete="off" value="<?= e($contract['customer_name']) ?>" placeholder="نام، شماره تماس، کد ملی یا تلفن دوم">
                  <input type="hidden" name="customer_id" value="<?= (int) $contract['customer_id'] ?>" data-customer-select data-customer-label="<?= e($contract['customer_name']) ?>" data-customer-name="<?= e($contract['customer_name']) ?>" data-customer-mobile="<?= e($contract['mobile'] ?? '') ?>" data-customer-national-id="<?= e($contract['national_id'] ?? '') ?>">
                  <span class="proma-live-results" data-customer-search-results hidden></span>
                </span>
              </label>
              <?php if ($singleOperator): ?>
                <label>اپراتور پیگیری<input value="<?= e($singleOperator['full_name']) ?>" disabled><input type="hidden" name="assigned_operator_id" value="<?= (int) $singleOperator['id'] ?>"></label>
              <?php else: ?>
                <label>اپراتور پیگیری
                  <span class="proma-live-search" data-user-live-search data-search-url="<?= e(url('users/search', ['roles' => 'operator'])) ?>">
                    <input data-user-search-input autocomplete="off" value="<?= e($contract['operator_name'] ?? '') ?>" placeholder="جستجوی نام، موبایل یا واحد اپراتور">
                    <input type="hidden" name="assigned_operator_id" value="<?= (int) ($contract['assigned_operator_id'] ?? 0) ?>" data-user-id-input>
                    <span class="proma-live-results" data-user-search-results hidden></span>
                    <span class="proma-chip-row" data-user-chip></span>
                  </span>
                </label>
              <?php endif; ?>
            </div>
            <div class="proma-chip-row" data-customer-chip></div>
          </section>

          <section class="proma-form-section">
            <div class="proma-section-title"><h4>اطلاعات مالی</h4><span>مبالغ به تومان هستند.</span></div>
            <div class="form-grid three">
              <label>مبلغ اصل<input name="principal_amount" data-money data-contract-principal value="<?= e(number_format((float) $contract['principal_amount'], 0)) ?>"></label>
              <label>مبلغ پیش‌پرداخت<input name="down_payment_amount" data-money data-contract-down-payment value="<?= e(number_format((float) ($contract['down_payment_amount'] ?? 0), 0)) ?>"></label>
              <div class="proma-live-card">
                <span>مانده قابل تقسیط</span>
                <strong data-financed-balance><?= money_toman(max(0, (float) $contract['principal_amount'] - (float) ($contract['down_payment_amount'] ?? 0))) ?></strong>
              </div>
              <label>نرخ سود ماهانه<input name="monthly_interest_rate" data-contract-rate value="<?= e($contract['monthly_interest_rate']) ?>"></label>
              <label>تعداد اقساط<input name="months" data-contract-months value="<?= e($contract['months']) ?>"></label>
              <div>
                <span class="field-title">نوع سود</span>
                <div class="switch-options" data-exclusive>
                  <label><input type="checkbox" name="interest_type" value="simple"<?= checked($contract['interest_type'], 'simple') ?>><span>ساده ماهانه</span></label>
                  <label><input type="checkbox" name="interest_type" value="compound"<?= checked($contract['interest_type'], 'compound') ?>><span>مرکب ماهانه</span></label>
                </div>
              </div>
            </div>
            <div class="proma-preview-grid" data-contract-preview>
              <span><small>مبلغ اصل قرارداد</small><strong data-preview-principal>۰ تومان</strong></span>
              <span><small>مبلغ پیش‌پرداخت</small><strong data-preview-down-payment>۰ تومان</strong></span>
              <span><small>مانده قابل تقسیط</small><strong data-preview-financed>۰ تومان</strong></span>
              <span><small>مبلغ تقریبی هر قسط</small><strong data-preview-installment>۰ تومان</strong></span>
              <span><small>مجموع قابل پرداخت</small><strong data-preview-total>۰ تومان</strong></span>
            </div>
            <div class="proma-inline-error" data-contract-error hidden></div>
          </section>

          <section class="proma-form-section">
            <div class="proma-section-title"><h4>زمان‌بندی اقساط</h4></div>
            <div class="form-grid two">
              <label>تاریخ شروع<input name="start_date" value="<?= e(jdate($contract['start_date'])) ?>"></label>
              <label>نخستین سررسید<input name="first_due_date" value="<?= e(jdate($contract['first_due_date'])) ?>"></label>
            </div>
          </section>

          <section class="proma-form-section" data-repeater="items">
            <div class="proma-section-title">
              <h4>مشخصات کالای امانت</h4>
              <button class="btn small secondary" type="button" data-repeater-add>افزودن کالا</button>
            </div>
            <div class="proma-repeat-list" data-repeater-list>
              <?php foreach ($contractItems ?: [['product_model' => '', 'imei_1' => '', 'imei_2' => '', 'description' => '']] as $itemIndex => $item): ?>
                <div class="proma-repeat-row" data-repeater-row>
                  <div class="form-grid four">
                    <label>مدل کالا<input name="items[<?= (int) $itemIndex ?>][product_model]" value="<?= e($item['product_model'] ?? '') ?>"></label>
                    <label>IMEI 1<input name="items[<?= (int) $itemIndex ?>][imei_1]" value="<?= e($item['imei_1'] ?? '') ?>" dir="ltr"></label>
                    <label>IMEI 2<input name="items[<?= (int) $itemIndex ?>][imei_2]" value="<?= e($item['imei_2'] ?? '') ?>" dir="ltr"></label>
                    <label>توضیحات کالا<input name="items[<?= (int) $itemIndex ?>][description]" value="<?= e($item['description'] ?? '') ?>"></label>
                  </div>
                  <button class="icon-btn danger" type="button" data-repeater-remove title="حذف">×</button>
                </div>
              <?php endforeach; ?>
            </div>
            <template data-repeater-template>
              <div class="proma-repeat-row" data-repeater-row>
                <div class="form-grid four">
                  <label>مدل کالا<input name="items[__INDEX__][product_model]"></label>
                  <label>IMEI 1<input name="items[__INDEX__][imei_1]" dir="ltr"></label>
                  <label>IMEI 2<input name="items[__INDEX__][imei_2]" dir="ltr"></label>
                  <label>توضیحات کالا<input name="items[__INDEX__][description]"></label>
                </div>
                <button class="icon-btn danger" type="button" data-repeater-remove title="حذف">×</button>
              </div>
            </template>
          </section>

          <section class="proma-form-section">
            <div class="proma-section-title"><h4>شرایط ضمانت و پرداخت اقساط</h4></div>
            <div class="form-grid four">
              <label>نوع ضمانت
                <select name="guarantee[guarantee_type]" data-guarantee-type>
                  <?php foreach (['' => 'انتخاب کنید', 'چک' => 'چک', 'سفته' => 'سفته', 'چک و سفته' => 'چک و سفته', 'ضامن' => 'ضامن', 'سایر' => 'سایر'] as $value => $label): ?>
                    <option value="<?= e($value) ?>"<?= selected($contractGuarantee['guarantee_type'] ?? '', $value) ?>><?= e($label) ?></option>
                  <?php endforeach; ?>
                </select>
              </label>
              <label>تعداد ضمانت<input name="guarantee[guarantee_count]" value="<?= e(to_persian_digits($contractGuarantee['guarantee_count'] ?? 1)) ?>" inputmode="numeric"></label>
              <label>شناسه / شماره سریال<input name="guarantee[guarantee_serial]" value="<?= e($contractGuarantee['guarantee_serial'] ?? '') ?>" dir="ltr"></label>
              <label data-guarantee-other<?= ($contractGuarantee['guarantee_type'] ?? '') === 'سایر' ? '' : ' hidden' ?>>توضیح نوع ضمانت<input name="guarantee[guarantee_type_other]"></label>
              <label class="full">توضیحات ضمانت<textarea name="guarantee[guarantee_description]"><?= e($contractGuarantee['guarantee_description'] ?? '') ?></textarea></label>
            </div>
          </section>

          <section class="proma-form-section" data-repeater="guarantor_people">
            <div class="proma-section-title">
              <h4>مشخصات ضامن‌ها</h4>
              <button class="btn small secondary" type="button" data-repeater-add>افزودن ضامن</button>
            </div>
            <div class="proma-repeat-list" data-repeater-list>
              <?php foreach ($contractGuarantorPeople as $personIndex => $person): ?>
                <div class="proma-repeat-row" data-repeater-row>
                  <div class="form-grid four">
                    <label>نام و نام خانوادگی<input name="guarantor_people[<?= (int) $personIndex ?>][full_name]" value="<?= e($person['full_name'] ?? '') ?>"></label>
                    <label>نام پدر<input name="guarantor_people[<?= (int) $personIndex ?>][father_name]" value="<?= e($person['father_name'] ?? '') ?>"></label>
                    <label>شماره ملی<input name="guarantor_people[<?= (int) $personIndex ?>][national_id]" value="<?= e($person['national_id'] ?? '') ?>" inputmode="numeric"></label>
                    <label>شماره تماس<input name="guarantor_people[<?= (int) $personIndex ?>][mobile]" value="<?= e($person['mobile'] ?? '') ?>" inputmode="tel"></label>
                    <label>نسبت با مشتری<input name="guarantor_people[<?= (int) $personIndex ?>][relationship]" value="<?= e($person['relationship'] ?? '') ?>"></label>
                    <label class="full">آدرس<input name="guarantor_people[<?= (int) $personIndex ?>][address]" value="<?= e($person['address'] ?? '') ?>"></label>
                    <label class="full">توضیحات<input name="guarantor_people[<?= (int) $personIndex ?>][description]" value="<?= e($person['description'] ?? '') ?>"></label>
                  </div>
                  <button class="icon-btn danger" type="button" data-repeater-remove title="حذف">×</button>
                </div>
              <?php endforeach; ?>
            </div>
            <template data-repeater-template>
              <div class="proma-repeat-row" data-repeater-row>
                <div class="form-grid four">
                  <label>نام و نام خانوادگی<input name="guarantor_people[__INDEX__][full_name]"></label>
                  <label>نام پدر<input name="guarantor_people[__INDEX__][father_name]"></label>
                  <label>شماره ملی<input name="guarantor_people[__INDEX__][national_id]" inputmode="numeric"></label>
                  <label>شماره تماس<input name="guarantor_people[__INDEX__][mobile]" inputmode="tel"></label>
                  <label>نسبت با مشتری<input name="guarantor_people[__INDEX__][relationship]"></label>
                  <label class="full">آدرس<input name="guarantor_people[__INDEX__][address]"></label>
                  <label class="full">توضیحات<input name="guarantor_people[__INDEX__][description]"></label>
                </div>
                <button class="icon-btn danger" type="button" data-repeater-remove title="حذف">×</button>
              </div>
            </template>
          </section>

          <section class="proma-form-section">
            <div class="proma-section-title"><h4>ضامنان</h4><span>مشتری اصلی نمی‌تواند ضامن خودش باشد.</span></div>
            <label class="full">جستجوی ضامن
              <span class="proma-live-search proma-guarantor-picker" data-guarantor-live-search data-search-url="<?= e(url('contracts/search-guarantors')) ?>">
                <input data-guarantor-search-input autocomplete="off" placeholder="نام، موبایل، کد ملی یا شماره مشتری را وارد کنید">
                <span class="proma-live-results" data-guarantor-search-results hidden></span>
                <span class="proma-chip-row" data-guarantor-chips>
                  <?php foreach ($guarantors as $guarantor): ?>
                    <span class="proma-chip" data-guarantor-chip data-guarantor-id="<?= (int) $guarantor['id'] ?>">
                      <?= e($guarantor['full_name']) ?>
                      <input type="hidden" name="guarantors[]" value="<?= (int) $guarantor['id'] ?>">
                      <button type="button" aria-label="حذف انتخاب">×</button>
                    </span>
                  <?php endforeach; ?>
                </span>
              </span>
            </label>
          </section>

          <section class="proma-form-section">
            <label class="full">یادداشت<textarea name="notes"><?= e($contract['notes']) ?></textarea></label>
            <label class="full">دلیل ویرایش<input name="change_reason" placeholder="اختیاری؛ مثلاً اصلاح اطلاعات کالا یا ضمانت"></label>
          </section>
        </div>
        <div class="modal-footer"><button class="btn" type="submit">ذخیره تغییرات</button><button class="btn secondary" type="button" data-close-modal>بستن</button></div>
      </form>
    </div>
  </div>

  <div class="modal" id="custom-installment-<?= (int) $contract['id'] ?>">
    <div class="modal-content">
      <div class="modal-header"><h3>افزودن قسط دلخواه</h3><button class="icon-btn" type="button" data-close-modal>×</button></div>
      <form method="post" action="<?= e(url('installments/store')) ?>">
        <div class="modal-body form-grid">
          <?= csrf_field() ?>
          <input type="hidden" name="contract_id" value="<?= (int) $contract['id'] ?>">
          <input type="hidden" name="redirect_to" value="contracts">
          <label>قرارداد<input value="<?= e($contract['contract_number']) ?> - <?= e($contract['customer_name']) ?>" disabled></label>
          <label>سررسید<input name="due_date" value="<?= e($defaultFirstDueDate ?? '') ?>" required placeholder="۱۴۰۳/۰۱/۰۱"></label>
          <label>مبلغ پایه<input name="base_amount" data-money required></label>
          <label>عنوان قسط<input name="custom_title" maxlength="100" placeholder="مثلاً هزینه خدمات اضافه"></label>
          <label>شناسه ضمانت<input name="guarantee_serial" dir="ltr" placeholder="شماره چک یا سفته"></label>
          <label class="full">توضیح قسط برای مشتری<textarea name="customer_description" required rows="3" placeholder="علت ایجاد این قسط را به زبان قابل نمایش برای مشتری بنویسید."></textarea><small class="proma-form-help">این توضیح در پنل مدیریت و پنل مشتری نمایش داده می‌شود.</small></label>
          <label class="full">یادداشت داخلی<textarea name="internal_note" rows="2" placeholder="نکته داخلی برای مدیریت؛ این متن به مشتری نمایش داده نمی‌شود."></textarea><small class="proma-form-help">این یادداشت فقط برای کاربران مجاز مدیریت قابل مشاهده است.</small></label>
          <label class="proma-confirm-check full"><input type="checkbox" name="customer_visible" value="1" checked> توضیح قسط در پنل مشتری نمایش داده شود.</label>
        </div>
        <div class="modal-footer"><button class="btn" type="submit">ثبت قسط</button><button class="btn secondary" type="button" data-close-modal>بستن</button></div>
      </form>
    </div>
  </div>

  <?php $cancellationSummary = Contract::cancellationSummary((int) $contract['id']); ?>
  <div class="modal" id="cancel-contract-<?= (int) $contract['id'] ?>">
    <div class="modal-content">
      <div class="modal-header"><h3><?= proma_icon('slash') ?> لغو قرارداد</h3><button class="icon-btn" type="button" data-close-modal aria-label="بستن پنجره لغو قرارداد" title="بستن"><?= proma_icon('close') ?></button></div>
      <form method="post" action="<?= e(url('contracts/cancel/' . $contract['id'])) ?>">
        <div class="modal-body form-grid">
          <?= csrf_field() ?>
          <div class="notice error">لغو قرارداد باعث حذف سوابق مالی و پرداخت‌ها نمی‌شود و تنها قرارداد را از چرخه فعال وصول خارج می‌کند. تمام اقساط فعال نیز لغو می‌شوند و دیگر در مطالبات و معوقات قرار نمی‌گیرند.</div>
          <div class="proma-cancellation-summary">
            <span><small>قرارداد</small><strong><?= e($contract['contract_number']) ?></strong></span>
            <span><small>مشتری</small><strong><?= e($contract['customer_name']) ?></strong></span>
            <span><small>وضعیت</small><strong><?= e(status_label($contract['status'] ?? '')) ?></strong></span>
            <span><small>اقساط فعال</small><strong><?= to_persian_digits($cancellationSummary['active_installments'] ?? 0) ?></strong></span>
            <span><small>اقساط معوق</small><strong><?= to_persian_digits($cancellationSummary['overdue_installments'] ?? 0) ?></strong></span>
            <span><small>مانده فعال</small><strong><?= money_toman($cancellationSummary['outstanding_amount'] ?? 0) ?></strong></span>
            <span><small>پرداخت ثبت‌شده</small><strong><?= money_toman($cancellationSummary['confirmed_payment_amount'] ?? 0) ?></strong></span>
            <span><small>تعداد پرداخت</small><strong><?= to_persian_digits($cancellationSummary['confirmed_payment_count'] ?? 0) ?></strong></span>
            <span><small>سند قرارداد</small><strong><?= to_persian_digits($cancellationSummary['document_count'] ?? 0) ?></strong></span>
            <span><small>پرونده حقوقی</small><strong><?= to_persian_digits($cancellationSummary['legal_case_count'] ?? 0) ?></strong></span>
            <span><small>وابستگی حسابداری</small><strong><?= to_persian_digits($cancellationSummary['accounting_relation_count'] ?? 0) ?></strong></span>
          </div>
          <label>علت لغو قرارداد<textarea name="cancellation_reason" required minlength="3" rows="3"></textarea></label>
          <label class="proma-confirm-check"><input type="checkbox" name="confirm_cancel" value="1" required> پیامدهای لغو قرارداد را مطالعه کردم.</label>
          <label class="proma-confirm-check proma-danger-check"><input type="checkbox" name="correct_contract_payments" value="1"> برای پرداخت‌های موفق همین قرارداد، اصلاحیه مالی ثبت شود و اثر آن‌ها در محاسبات داخلی صفر شود. این عملیات بازگشت وجه بانکی انجام نمی‌دهد.</label>
        </div>
        <div class="modal-footer"><button class="btn danger" type="submit"><?= proma_icon('slash') ?><span>لغو قطعی قرارداد</span></button><button class="btn secondary" type="button" data-close-modal>انصراف</button></div>
      </form>
    </div>
  </div>
  <?php $deletionPreview = Contract::deletionPreview((int) $contract['id']); $canPermanentlyDelete = !empty($deletionPreview['eligible_for_permanent_delete']); ?>
  <div class="modal" id="delete-contract-<?= (int) $contract['id'] ?>">
    <div class="modal-content">
      <div class="modal-header"><h3>حذف قرارداد آزمایشی یا اشتباهی</h3><button class="icon-btn" type="button" data-close-modal aria-label="بستن"><i data-feather="x"></i></button></div>
      <form method="post" action="<?= e(url('contracts/delete/' . $contract['id'])) ?>">
        <div class="modal-body form-grid">
          <?= csrf_field() ?>
          <div class="notice <?= $canPermanentlyDelete ? 'success' : 'error' ?> full"><?= $canPermanentlyDelete ? 'این قرارداد فقط به‌عنوان رکورد آزمایشی یا اشتباهی و بدون سابقه مالی، حقوقی، سند یا حسابداری قابل حذف است.' : 'این قرارداد دارای سابقه مالی یا حقوقی است و قابل حذف نیست. از گزینه لغو یا بایگانی استفاده کنید.' ?></div>
          <div class="proma-cancellation-summary full"><span><small>کل پرداخت‌ها</small><strong><?= to_persian_digits($deletionPreview['payment_count'] ?? 0) ?></strong></span><span><small>رسیدها</small><strong><?= to_persian_digits($deletionPreview['dependencies']['payment_receipt_count'] ?? 0) ?></strong></span><span><small>پرونده حقوقی</small><strong><?= to_persian_digits($deletionPreview['legal_case_count'] ?? 0) ?></strong></span><span><small>اسناد قرارداد</small><strong><?= to_persian_digits(($deletionPreview['dependencies']['generated_document_count'] ?? 0) + ($deletionPreview['dependencies']['document_version_count'] ?? 0)) ?></strong></span></div>
          <?php if (!$canPermanentlyDelete): ?><p class="full small text-muted">وابستگی‌های مانع: <?= e(implode('، ', $deletionPreview['blocking_dependencies'] ?? [])) ?></p><?php endif; ?>
          <label class="full">علت حذف<textarea name="deletion_reason" required minlength="5" rows="3" placeholder="مثلاً قرارداد آزمایشی اشتباه ثبت شده است"<?= $canPermanentlyDelete ? '' : ' disabled' ?>></textarea></label>
          <label class="full">برای تأیید، شماره قرارداد را وارد کنید<input name="confirm_contract_number" required autocomplete="off" placeholder="<?= e($contract['contract_number']) ?>"<?= $canPermanentlyDelete ? '' : ' disabled' ?>></label>
          <label class="proma-confirm-check proma-danger-check"><input type="checkbox" name="confirm_delete_mistake" value="1" required<?= $canPermanentlyDelete ? '' : ' disabled' ?>> تأیید می‌کنم این قرارداد آزمایشی یا اشتباهی است و حذف آن سوابق مالی واقعی را تحت تأثیر قرار نمی‌دهد.</label>
        </div>
        <div class="modal-footer"><button class="btn danger" type="submit"<?= $canPermanentlyDelete ? '' : ' disabled' ?>>حذف قطعی قرارداد آزمایشی</button><button class="btn secondary" type="button" data-close-modal>انصراف</button></div>
      </form>
    </div>
  </div>
<?php endforeach; ?>
<?php endif; ?>
</div>
