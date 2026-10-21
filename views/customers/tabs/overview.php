<div class="proma-customer-360__overview-grid">
  <section class="card"><div class="card-header"><div><h2>قراردادهای اخیر</h2><p class="text-muted">پنج مورد تازه</p></div><button class="link-only" type="button" data-customer-open-tab="contracts">همه قراردادها</button></div><div class="proma-customer-360__list">
    <?php foreach (($contracts['items'] ?? []) as $contract): ?><a class="proma-customer-360__row" href="<?= e(url('contracts/show/' . (int) $contract['id'])) ?>"><span><strong><?= e($contract['contract_number']) ?></strong><small><?= e(jdate($contract['start_date'] ?? '')) ?> · <?= e(status_label($contract['status'] ?? '')) ?></small></span><b><?= money_toman($contract['principal_amount'] ?? 0) ?></b></a><?php endforeach; ?>
    <?php if (empty($contracts['items'])): ?><div class="proma-empty-state"><p>هنوز قراردادی برای این مشتری ثبت نشده است.</p></div><?php endif; ?>
  </div></section>
  <section class="card"><div class="card-header"><div><h2>پرداخت‌های اخیر</h2><p class="text-muted">پنج تراکنش آخر ثبت‌شده</p></div><button class="link-only" type="button" data-customer-open-tab="payments">همه پرداخت‌ها</button></div><div class="proma-customer-360__list">
    <?php foreach (($payments ?? []) as $payment): ?><div class="proma-customer-360__row"><span><strong><?= money_toman($payment['amount'] ?? 0) ?></strong><small><?= e($payment['contract_number'] ?? '') ?> · <?= e(jdatetime($payment['paid_at'] ?: ($payment['payment_date'] ?: $payment['created_at']))) ?></small></span><em class="badge <?= e(badge_class($payment['status'] ?? 'paid')) ?>"><?= e(status_label($payment['status'] ?? 'paid')) ?></em></div><?php endforeach; ?>
    <?php if (empty($payments)): ?><div class="proma-empty-state"><p>پرداختی برای این مشتری ثبت نشده است.</p></div><?php endif; ?>
  </div></section>
  <section class="card proma-customer-360__summary-card"><div class="card-header"><h2>دسترسی سریع</h2></div><div class="proma-customer-360__shortcut-grid">
    <?php foreach ([['installments','اقساط و سررسیدها','calendar'],['documents','مدارک مشتری','file-text'],['accounting','گردش حساب','credit-card'],['calls','تماس و پیگیری','phone']] as [$tab,$label,$icon]): ?><button type="button" data-customer-open-tab="<?= e($tab) ?>"><?= proma_icon($icon) ?><span><?= e($label) ?></span></button><?php endforeach; ?>
  </div></section>
</div>
