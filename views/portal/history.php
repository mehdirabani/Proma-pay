<?php
$summary = $summary ?? [];
$contracts = $contracts ?? [];
$statusCounts = $summary['statuses'] ?? [];
$query = trim((string) ($query ?? ''));
$statusFilter = (string) ($statusFilter ?? '');
$pagination = $pagination ?? ['page' => 1, 'pages' => 1, 'total' => count($contracts), 'per_page' => 6];
$contractCount = (int) ($summary['contracts'] ?? 0);
$itemCount = (int) ($summary['items'] ?? 0);
$isFiltered = $query !== '' || $statusFilter !== '';
$firstResult = $pagination['total'] > 0 ? (($pagination['page'] - 1) * $pagination['per_page']) + 1 : 0;
$lastResult = min($pagination['total'], $pagination['page'] * $pagination['per_page']);
?>
<section class="customer-history-page" aria-labelledby="customer-history-title">
  <header class="customer-history-hero">
    <div class="customer-history-hero__copy">
      <span class="customer-history-eyebrow"><i data-feather="shopping-bag"></i> مرکز خرید من</span>
      <h1 id="customer-history-title">سوابق خرید</h1>
      <p>قراردادها، کالاهای خریداری‌شده و روند پرداخت اقساطتان را یک‌جا ببینید.</p>
    </div>
    <div class="customer-history-hero__actions">
      <a class="ch-button ch-button--light" href="<?= e(url('installments/panel')) ?>"><i data-feather="calendar"></i> اقساط من</a>
      <a class="ch-button ch-button--white" href="<?= e(url('portal/contracts')) ?>"><i data-feather="file-text"></i> قراردادهای من</a>
    </div>
    <div class="customer-history-hero__mark" aria-hidden="true"><i data-feather="shopping-bag"></i></div>
  </header>

  <div class="customer-history-summary" aria-label="خلاصه سوابق خرید">
    <article class="customer-history-stat customer-history-stat--violet">
      <span class="customer-history-stat__icon"><i data-feather="file-text"></i></span>
      <div><small>قراردادهای خرید</small><strong><?= to_persian_digits($contractCount) ?></strong><span>تمام قراردادهای ثبت‌شده</span></div>
    </article>
    <article class="customer-history-stat customer-history-stat--blue">
      <span class="customer-history-stat__icon"><i data-feather="package"></i></span>
      <div><small>کالاهای ثبت‌شده</small><strong><?= to_persian_digits($itemCount) ?></strong><span><?= $itemCount ? 'در قراردادهای خرید شما' : 'بر پایه اقلام ثبت‌شده' ?></span></div>
    </article>
    <article class="customer-history-stat customer-history-stat--green">
      <span class="customer-history-stat__icon"><i data-feather="check-circle"></i></span>
      <div><small>قراردادهای فعال</small><strong><?= to_persian_digits($summary['active_contracts'] ?? 0) ?></strong><span>قابل پیگیری و پرداخت</span></div>
    </article>
    <article class="customer-history-stat customer-history-stat--orange">
      <span class="customer-history-stat__icon"><i data-feather="credit-card"></i></span>
      <div><small>مجموع پرداخت ثبت‌شده</small><strong><?= money_toman($summary['total_paid'] ?? 0) ?></strong><span>مبالغ پرداخت‌شده قراردادها</span></div>
    </article>
  </div>

  <section class="customer-history-workspace" aria-label="فهرست خریدها">
    <div class="customer-history-workspace__heading">
      <div>
        <span class="customer-history-section-kicker">گزارش شخصی</span>
        <h2><i data-feather="layers"></i> خریدها و قراردادهای من</h2>
        <p>برای دیدن مبلغ، اقساط و وضعیت هر خرید، قرارداد مربوط را باز کنید.</p>
      </div>
      <span class="customer-history-count"><?= to_persian_digits($contractCount) ?> قرارداد</span>
    </div>

    <form class="customer-history-filters" method="get" action="<?= e(url('portal/history')) ?>">
      <label class="customer-history-search">
        <span class="visually-hidden">جستجو در سوابق خرید</span>
        <i data-feather="search" aria-hidden="true"></i>
        <input type="search" name="q" value="<?= e($query) ?>" placeholder="جستجو با شماره قرارداد یا نام کالا" autocomplete="off">
      </label>
      <label class="customer-history-status">
        <span class="visually-hidden">وضعیت قرارداد</span>
        <i data-feather="filter" aria-hidden="true"></i>
        <select name="status" aria-label="فیلتر بر اساس وضعیت قرارداد">
          <option value="">همه وضعیت‌ها</option>
          <?php foreach ($statusCounts as $status => $count): ?>
            <option value="<?= e($status) ?>" <?= $statusFilter === (string) $status ? 'selected' : '' ?>><?= e(status_label($status)) ?> (<?= to_persian_digits($count) ?>)</option>
          <?php endforeach; ?>
        </select>
      </label>
      <button class="ch-button ch-button--primary" type="submit"><i data-feather="search"></i> جستجو</button>
      <?php if ($isFiltered): ?><a class="ch-button ch-button--quiet" href="<?= e(url('portal/history')) ?>">پاک‌کردن فیلتر</a><?php endif; ?>
    </form>

    <?php if (!$contracts): ?>
      <div class="customer-history-empty">
        <div class="customer-history-empty__icon"><i data-feather="shopping-bag"></i></div>
        <?php if ($isFiltered): ?>
          <h3>موردی با این جستجو پیدا نشد</h3>
          <p>عبارت جستجو یا وضعیت انتخاب‌شده را تغییر دهید تا سوابق منطبق نمایش داده شوند.</p>
          <a class="ch-button ch-button--primary" href="<?= e(url('portal/history')) ?>"><i data-feather="rotate-ccw"></i> نمایش همه سوابق</a>
        <?php else: ?>
          <h3>هنوز خریدی در سوابق شما ثبت نشده</h3>
          <p>بعد از ثبت قرارداد خرید، کالاها، مبلغ پرداخت‌شده و برنامه اقساط اینجا نمایش داده می‌شود.</p>
          <div class="customer-history-empty__actions">
            <a class="ch-button ch-button--quiet" href="<?= e(url('portal/contracts')) ?>"><i data-feather="file-text"></i> قراردادهای من</a>
          </div>
        <?php endif; ?>
      </div>
    <?php else: ?>
      <div class="customer-history-list">
        <?php foreach ($contracts as $contract): ?>
          <?php
            $history = $contract['history'] ?? [];
            $items = $contract['items'] ?? [];
            $productLabels = [];
            foreach ($items as $purchaseItem) {
                $label = trim((string) ($purchaseItem['product_model'] ?? ''));
                if ($label === '') $label = trim((string) ($purchaseItem['description'] ?? ''));
                if ($label !== '') $productLabels[] = $label;
            }
            $productLabels = array_values(array_unique($productLabels));
            $productTitle = $productLabels[0] ?? ('خرید ثبت‌شده در قرارداد ' . ($contract['contract_number'] ?? ''));
            $progress = max(0, min(100, (int) ($history['progress'] ?? 0)));
            $overdueCount = (int) ($history['overdue_installment_count'] ?? 0);
          ?>
          <article class="customer-purchase-card">
            <div class="customer-purchase-card__top">
              <div class="customer-purchase-product">
                <span class="customer-purchase-product__icon"><i data-feather="package"></i></span>
                <div>
                  <span class="customer-history-section-kicker"><?= count($items) > 1 ? to_persian_digits(count($items)) . ' قلم کالا' : 'خرید اقساطی' ?></span>
                  <h3><?= e($productTitle) ?></h3>
                  <?php if (count($productLabels) > 1): ?><p>و <?= to_persian_digits(count($productLabels) - 1) ?> کالای دیگر</p><?php elseif (!empty($contract['description'])): ?><p><?= e($contract['description']) ?></p><?php endif; ?>
                </div>
              </div>
              <div class="customer-purchase-contract">
                <span>شماره قرارداد</span>
                <strong><?= e($contract['contract_number'] ?? '—') ?></strong>
                <span class="customer-purchase-status <?= e(badge_class($contract['status'] ?? '')) ?>"><?= e(status_label($contract['status'] ?? '')) ?></span>
              </div>
            </div>

            <div class="customer-purchase-card__meta">
              <span><i data-feather="calendar"></i> تاریخ خرید <strong><?= !empty($contract['start_date']) ? e(jdate($contract['start_date'])) : 'ثبت نشده' ?></strong></span>
              <span><i data-feather="repeat"></i> برنامه اقساط <strong><?= (int) ($history['installment_count'] ?? 0) ? to_persian_digits($history['installment_count']) . ' قسط' : 'ثبت نشده' ?></strong></span>
              <span class="<?= $overdueCount > 0 ? 'is-overdue' : '' ?>"><i data-feather="clock"></i> <?= $overdueCount > 0 ? 'اقساط معوق' : 'سررسید بعدی' ?> <strong><?= $overdueCount > 0 ? to_persian_digits($overdueCount) . ' قسط' : (!empty($history['next_due_date']) ? e(jdate($history['next_due_date'])) : 'سررسید بازی ندارید') ?></strong></span>
            </div>

            <div class="customer-purchase-money-grid">
              <div><small>مبلغ قرارداد</small><strong><?= money_toman($contract['principal_amount'] ?? 0) ?></strong></div>
              <div><small>پیش‌پرداخت</small><strong><?= money_toman($contract['down_payment_amount'] ?? 0) ?></strong></div>
              <div><small>پرداخت‌شده</small><strong><?= money_toman($history['paid_total'] ?? 0) ?></strong></div>
              <div><small>مانده اقساط</small><strong><?= money_toman($history['remaining_total'] ?? 0) ?></strong></div>
            </div>

            <div class="customer-purchase-progress">
              <div class="customer-purchase-progress__label"><span>پیشرفت پرداخت اقساط</span><strong><?= to_persian_digits($progress) ?>٪</strong></div>
              <div class="customer-purchase-progress__track" role="progressbar" aria-label="پیشرفت پرداخت اقساط" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= $progress ?>"><span style="width: <?= $progress ?>%"></span></div>
              <span class="customer-purchase-progress__hint"><?= to_persian_digits($history['paid_installment_count'] ?? 0) ?> از <?= to_persian_digits($history['installment_count'] ?? 0) ?> قسط پرداخت شده</span>
            </div>

            <footer class="customer-purchase-card__actions">
              <a class="ch-button ch-button--primary" href="<?= e(url('contracts/show/' . (int) $contract['id'])) ?>"><i data-feather="file-text"></i> جزئیات قرارداد</a>
              <a class="ch-button ch-button--quiet" href="<?= e(url('installments/panel')) ?>"><i data-feather="calendar"></i> برنامه اقساط</a>
              <?php if ((int) ($history['open_installment_count'] ?? 0) > 0): ?><a class="ch-button ch-button--pay" href="<?= e(url('installments/panel')) ?>"><i data-feather="credit-card"></i> پرداخت قسط</a><?php endif; ?>
              <a class="customer-purchase-card__document" href="<?= e(url('contracts/booklet/' . (int) $contract['id'])) ?>" target="_blank" rel="noopener"><i data-feather="download"></i> دریافت قرارداد</a>
            </footer>
          </article>
        <?php endforeach; ?>
      </div>

      <div class="customer-history-pagination">
        <span>نمایش <?= to_persian_digits($firstResult) ?> تا <?= to_persian_digits($lastResult) ?> از <?= to_persian_digits($pagination['total']) ?> قرارداد</span>
        <?php if ($pagination['pages'] > 1): ?>
          <nav aria-label="صفحه‌های سوابق خرید">
            <?php if ($pagination['page'] > 1): ?><a href="<?= e(url('portal/history', ['q' => $query ?: null, 'status' => $statusFilter ?: null, 'page' => $pagination['page'] - 1])) ?>" aria-label="صفحه قبل"><i data-feather="chevron-right"></i></a><?php endif; ?>
            <span>صفحه <?= to_persian_digits($pagination['page']) ?> از <?= to_persian_digits($pagination['pages']) ?></span>
            <?php if ($pagination['page'] < $pagination['pages']): ?><a href="<?= e(url('portal/history', ['q' => $query ?: null, 'status' => $statusFilter ?: null, 'page' => $pagination['page'] + 1])) ?>" aria-label="صفحه بعد"><i data-feather="chevron-left"></i></a><?php endif; ?>
          </nav>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </section>
</section>
