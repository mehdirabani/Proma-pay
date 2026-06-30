<?php
$readOnly = $readOnly ?? false;
$viewMode = in_array($_GET['view'] ?? '', ['cards', 'list'], true) ? $_GET['view'] : 'cards';
$contractTrendLabels = [];
$contractTrendStart = (new DateTime('first day of this month'))->modify('-5 months');
for ($i = 0; $i < 6; $i++) {
    $contractTrendLabels[] = mb_substr(jdate((clone $contractTrendStart)->modify('+' . $i . ' months')->format('Y-m-01')), 0, 7, 'UTF-8');
}
?>
<section class="card">
  <div class="card-header card-no-border">
    <div class="header-top">
      <h2><?= $readOnly ? 'قراردادهای ضمانت شده' : 'فهرست قراردادها' ?></h2>
      <div class="actions">
        <a class="btn small <?= $viewMode === 'cards' ? '' : 'secondary' ?>" href="<?= e(url($readOnly ? 'portal/guaranteed' : 'contracts', array_filter(['q' => $_GET['q'] ?? null, 'view' => 'cards']))) ?>">کارت‌ها</a>
        <a class="btn small <?= $viewMode === 'list' ? '' : 'secondary' ?>" href="<?= e(url($readOnly ? 'portal/guaranteed' : 'contracts', array_filter(['q' => $_GET['q'] ?? null, 'view' => 'list']))) ?>">لیست</a>
        <?php if (!$readOnly): ?><button class="btn" type="button" data-open-modal="create-contract">افزودن قرارداد</button><?php endif; ?>
      </div>
    </div>
  </div>
  <div class="card-body">
    <form method="get" action="<?= e(url($readOnly ? 'portal/guaranteed' : 'contracts')) ?>" class="form-grid three">
      <input type="hidden" name="route" value="<?= e($readOnly ? 'portal/guaranteed' : 'contracts') ?>">
      <input type="hidden" name="view" value="<?= e($viewMode) ?>">
      <label class="full">جستجو در قرارداد و مشتری<input name="q" value="<?= e($_GET['q'] ?? '') ?>" placeholder="شماره قرارداد، نام، کد ملی یا موبایل"></label>
      <div class="actions"><button class="btn secondary" type="submit">جستجو</button></div>
    </form>
  </div>
  <?php if ($contracts && $viewMode === 'cards'): ?>
    <div class="card-body pt-0">
      <div class="proma-contract-card-grid">
        <?php foreach ($contracts as $cardContract): ?>
          <?php
          $stats = Contract::installmentStats((int) $cardContract['id']);
          $trend = Payment::monthlyTrendForContract((int) $cardContract['id']);
          $timeline = Payment::recentForContract((int) $cardContract['id'], 3);
          $remainingCount = max(0, (int) $stats['total'] - (int) $stats['paid']);
          $financedAmount = max(0, (float) $cardContract['principal_amount'] - (float) ($cardContract['down_payment_amount'] ?? 0));
          $progress = (int) $stats['total'] > 0 ? (int) round(((int) $stats['paid'] / (int) $stats['total']) * 100) : 0;
          ?>
          <article class="proma-contract-card" data-card-href="<?= e(url('contracts/show/' . $cardContract['id'])) ?>">
            <div class="proma-contract-card-main">
              <span class="proma-progress-avatar" style="--progress: <?= $progress ?>">
                <span class="proma-avatar-choice <?= e($cardContract['avatar_key'] ?? 'avatar-1') ?>"><?= e(mb_substr($cardContract['customer_name'], 0, 1, 'UTF-8')) ?></span>
              </span>
              <div>
                <span class="proma-contract-badge"><?= e($cardContract['contract_number']) ?></span>
                <h6><?= e($cardContract['customer_name']) ?></h6>
                <p><?= money_toman($financedAmount) ?></p>
              </div>
            </div>
            <div class="proma-contract-stats four">
              <span><strong><?= to_persian_digits($stats['total']) ?></strong><small>کل اقساط</small></span>
              <span><strong><?= to_persian_digits($stats['paid']) ?></strong><small>پرداخت‌شده</small></span>
              <span><strong><?= to_persian_digits($remainingCount) ?></strong><small>باقی‌مانده</small></span>
              <span><strong><?= to_persian_digits($stats['overdue']) ?></strong><small>معوق</small></span>
            </div>
            <div class="proma-contract-card-footer">
              <span>مانده: <?= money_toman($stats['outstanding']) ?></span>
              <button class="btn small info" type="button" data-open-modal="contract-chart-<?= (int) $cardContract['id'] ?>">نمودار</button>
              <button class="btn small warning" type="button" data-open-modal="contract-timeline-<?= (int) $cardContract['id'] ?>">تایم‌لاین</button>
              <a href="<?= e(url('contracts/show/' . $cardContract['id'])) ?>">جزئیات</a>
              <a href="<?= e(url('contracts/booklet/' . $cardContract['id'])) ?>" target="_blank">دفترچه</a>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>
  <?php if ($viewMode === 'list'): ?>
  <div class="table-wrap">
    <table>
      <thead><tr><th>شماره</th><th>مشتری</th><th>مبالغ قرارداد</th><th>سود</th><th>اقساط</th><th>ضامنان</th><th>وضعیت</th><?php if (!$readOnly): ?><th>عملیات</th><?php endif; ?></tr></thead>
      <tbody>
      <?php foreach ($contracts as $contract): ?>
        <?php $guarantors = Contract::guarantors($contract['id']); ?>
        <tr>
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
          <?php if (!$readOnly): ?>
          <td class="actions">
            <button class="btn small secondary" type="button" data-open-modal="edit-contract-<?= (int) $contract['id'] ?>">ویرایش</button>
            <button class="btn small info" type="button" data-open-modal="contract-chart-<?= (int) $contract['id'] ?>">نمودار</button>
            <button class="btn small warning" type="button" data-open-modal="contract-timeline-<?= (int) $contract['id'] ?>">تایم‌لاین</button>
            <a class="btn small secondary" href="<?= e(url('contracts/show/' . $contract['id'])) ?>">جزئیات</a>
            <button class="btn small info" type="button" data-open-modal="custom-installment-<?= (int) $contract['id'] ?>">قسط دلخواه</button>
            <a class="btn small success" href="<?= e(url('contracts/booklet/' . $contract['id'])) ?>" target="_blank">چاپ دفترچه</a>
            <button class="btn small danger" type="button" data-open-modal="delete-contract-<?= (int) $contract['id'] ?>">حذف</button>
          </td>
          <?php endif; ?>
        </tr>
      <?php endforeach; ?>
      <?php if (!$contracts): ?><tr><td colspan="<?= $readOnly ? 7 : 8 ?>" class="empty">قراردادی ثبت نشده است.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</section>

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
    <form method="post" action="<?= e(url('contracts/store')) ?>" data-contract-form data-preview-url="<?= e(url('contracts/preview')) ?>" novalidate>
      <div class="modal-body proma-contract-form">
        <?= csrf_field() ?>
        <section class="proma-form-section">
          <div class="proma-section-title"><h4>اطلاعات قرارداد</h4><span>شماره قرارداد به صورت خودکار ساخته می‌شود.</span></div>
          <div class="form-grid three">
            <label>پیشوند قرارداد<input name="prefix" value="<?= e($settings['contract_prefix'] ?? 'Pr') ?>" dir="ltr"></label>
            <label>سریال بعدی<input value="<?= to_persian_digits($settings['contract_next_serial'] ?? '') ?>" disabled></label>
            <label>اپراتور پیگیری
              <select name="assigned_operator_id"><option value="">بدون ارجاع</option><?php foreach ($operators as $operator): ?><option value="<?= (int) $operator['id'] ?>"><?= e($operator['full_name']) ?></option><?php endforeach; ?></select>
            </label>
          </div>
        </section>

        <section class="proma-form-section">
          <div class="proma-section-title"><h4>اطلاعات مشتری</h4><button class="btn small secondary" type="button" data-toggle-new-customer>مشتری جدید</button></div>
          <div class="form-grid two">
            <label class="full">انتخاب مشتری موجود
              <input data-select-filter="customer-select" placeholder="جست‌وجوی نام، کد ملی یا موبایل">
              <select id="customer-select" name="customer_id" data-customer-select>
                <option value="">انتخاب مشتری موجود</option>
                <?php foreach ($customers as $customer): ?>
                  <option value="<?= (int) $customer['id'] ?>"><?= e($customer['full_name']) ?> - <?= to_persian_digits($customer['national_id']) ?> - <?= to_persian_digits($customer['mobile']) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
            <div class="proma-chip-row full" data-customer-chip></div>
          </div>
          <div class="form-grid four proma-new-customer-fields" data-new-customer-fields hidden>
            <label>نام مشتری تازه<input name="new_customer_full_name" placeholder="نام و نام خانوادگی"></label>
            <label>نام پدر<input name="new_customer_father_name"></label>
            <label>صادره از<input name="new_customer_issued_from"></label>
            <label>کد ملی مشتری تازه<input name="new_customer_national_id" inputmode="numeric"></label>
            <label>موبایل مشتری تازه<input name="new_customer_mobile" inputmode="tel"></label>
            <label>تلفن دوم مشتری تازه<input name="new_customer_secondary_phone" inputmode="tel"></label>
            <label class="full">آدرس مشتری تازه<input name="new_customer_address"></label>
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
          <label class="full">انتخاب ضامن
            <input data-select-filter="guarantor-select" placeholder="جست‌وجوی ضامن">
            <select id="guarantor-select" name="guarantors[]" multiple data-guarantor-select>
              <?php foreach ($customers as $customer): ?>
                <option value="<?= (int) $customer['id'] ?>"><?= e($customer['full_name']) ?> - <?= to_persian_digits($customer['national_id']) ?> - <?= to_persian_digits($customer['mobile']) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <div class="proma-chip-row" data-guarantor-chips></div>
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
                <select name="customer_id" data-customer-select><?php foreach ($customers as $customer): ?><option value="<?= (int) $customer['id'] ?>"<?= selected($contract['customer_id'], $customer['id']) ?>><?= e($customer['full_name']) ?></option><?php endforeach; ?></select>
              </label>
              <label>اپراتور<select name="assigned_operator_id"><option value="">بدون ارجاع</option><?php foreach ($operators as $operator): ?><option value="<?= (int) $operator['id'] ?>"<?= selected($contract['assigned_operator_id'], $operator['id']) ?>><?= e($operator['full_name']) ?></option><?php endforeach; ?></select></label>
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
            <label class="full">ضامنان<select name="guarantors[]" multiple data-guarantor-select><?php $selectedGuarantors = array_column($guarantors, 'id'); foreach ($customers as $customer): ?><option value="<?= (int) $customer['id'] ?>"<?= in_array($customer['id'], $selectedGuarantors) ? ' selected' : '' ?>><?= e($customer['full_name']) ?></option><?php endforeach; ?></select></label>
            <div class="proma-chip-row" data-guarantor-chips></div>
          </section>

          <section class="proma-form-section">
            <label class="full">یادداشت<textarea name="notes"><?= e($contract['notes']) ?></textarea></label>
            <label class="full">دلیل ویرایش<input name="change_reason" required placeholder="مثلاً اصلاح اطلاعات کالا یا ضمانت"></label>
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
          <label>شناسه ضمانت<input name="guarantee_serial" dir="ltr" placeholder="شماره چک یا سفته"></label>
          <label class="full">توضیحات<input name="notes" required placeholder="علت یا توضیح قسط دلخواه"></label>
        </div>
        <div class="modal-footer"><button class="btn" type="submit">ثبت قسط</button><button class="btn secondary" type="button" data-close-modal>بستن</button></div>
      </form>
    </div>
  </div>

  <div class="modal" id="delete-contract-<?= (int) $contract['id'] ?>">
    <div class="modal-content">
      <div class="modal-header"><h3>تأیید دو مرحله‌ای حذف</h3><button class="icon-btn" type="button" data-close-modal>×</button></div>
      <form method="post" action="<?= e(url('contracts/delete/' . $contract['id'])) ?>">
        <div class="modal-body">
          <?= csrf_field() ?>
          <p>برای حذف قرارداد <?= e($contract['contract_number']) ?> عبارت «حذف قطعی» را وارد کنید.</p>
          <label>عبارت تأیید<input name="confirm_text" required></label>
        </div>
        <div class="modal-footer"><button class="btn danger" type="submit">حذف قرارداد</button><button class="btn secondary" type="button" data-close-modal>بستن</button></div>
      </form>
    </div>
  </div>
<?php endforeach; ?>
<?php endif; ?>
