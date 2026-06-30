<section class="card">
  <div class="card-header card-no-border">
    <div class="header-top">
      <h2>گزارش پرداخت‌ها</h2>
      <span class="badge badge-light-info">اصلاحیه فقط وضعیت داخلی سامانه را تغییر می‌دهد.</span>
    </div>
  </div>
  <div class="card-body">
    <form method="get" action="<?= e(url('payments')) ?>" class="form-grid four">
      <input type="hidden" name="route" value="payments">
      <label>از تاریخ<input name="date_from" value="<?= e($_GET['date_from'] ?? '') ?>" placeholder="۱۴۰۳/۰۱/۰۱"></label>
      <label>تا تاریخ<input name="date_to" value="<?= e($_GET['date_to'] ?? '') ?>" placeholder="۱۴۰۳/۱۲/۲۹"></label>
      <label>شماره قرارداد<input name="contract_number" value="<?= e($_GET['contract_number'] ?? '') ?>"></label>
      <label>مشتری<input name="customer" value="<?= e($_GET['customer'] ?? '') ?>" placeholder="نام، کد ملی یا موبایل"></label>
      <div class="full actions"><button class="btn" type="submit">اعمال فیلتر</button></div>
    </form>
  </div>
</section>

<section class="card" style="margin-top:16px">
  <div class="card-header card-no-border">
    <div class="header-top">
      <h2>سوابق پرداخت</h2>
      <span class="badge muted"><?= to_persian_digits(count($payments)) ?> رکورد</span>
    </div>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>تاریخ</th>
          <th>قرارداد</th>
          <th>مشتری</th>
          <th>قسط</th>
          <th>نوع پرداخت</th>
          <th>مبلغ</th>
          <th>روش</th>
          <th>کدها</th>
          <th>وضعیت</th>
          <th>اصلاحیه</th>
          <th>عملیات</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($payments as $payment): ?>
        <?php
        $isCorrected = (int) ($payment['is_corrected'] ?? 0) === 1 || ($payment['status'] ?? '') === 'corrected';
        $paymentType = $payment['payment_type'] ?? 'installment';
        ?>
        <tr>
          <td><?= e(jdatetime($payment['paid_at'] ?: ($payment['payment_date'] ?: $payment['created_at']))) ?></td>
          <td><?= e($payment['contract_number']) ?></td>
          <td><strong><?= e($payment['customer_name']) ?></strong></td>
          <td><?= $payment['installment_number'] ? to_persian_digits($payment['installment_number']) : '-' ?></td>
          <td><span class="badge badge-light-info"><?= e(payment_type_label($paymentType)) ?></span></td>
          <td><?= money_toman($payment['amount']) ?></td>
          <td><span class="badge badge-light-info"><?= e(payment_method_label($payment['method'])) ?></span></td>
          <td>
            <small class="ltr"><?= e($payment['gateway_track_id'] ?: '-') ?></small><br>
            <small class="ltr"><?= e($payment['gateway_ref_id'] ?: '-') ?></small>
          </td>
          <td><span class="badge <?= e(badge_class($payment['status'])) ?>"><?= e(status_label($payment['status'])) ?></span></td>
          <td>
            <?php if ($isCorrected): ?>
              <span class="badge muted">اصلاح‌شده</span>
              <small class="d-block"><?= e(jdatetime($payment['corrected_at'])) ?> - <?= e($payment['corrected_by_name'] ?: 'مدیر') ?></small>
              <small class="d-block"><?= e($payment['correction_reason'] ?? '') ?></small>
            <?php else: ?>
              <span class="badge badge-light-success">بدون اصلاحیه</span>
            <?php endif; ?>
          </td>
          <td class="actions">
            <a class="btn small secondary" href="<?= e(url('contracts')) ?>">مشاهده</a>
            <?php if (($payment['status'] ?? '') === 'paid' && !$isCorrected && $paymentType !== 'down_payment'): ?>
              <button class="btn small warning" type="button" data-open-modal="correct-payment-<?= (int) $payment['id'] ?>">اصلاحیه</button>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$payments): ?><tr><td colspan="11" class="empty">پرداختی با این فیلترها پیدا نشد.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<?php foreach ($payments as $payment): ?>
  <?php $isCorrected = (int) ($payment['is_corrected'] ?? 0) === 1 || ($payment['status'] ?? '') === 'corrected'; ?>
  <?php if (($payment['status'] ?? '') !== 'paid' || $isCorrected || ($payment['payment_type'] ?? 'installment') === 'down_payment') continue; ?>
  <div class="modal" id="correct-payment-<?= (int) $payment['id'] ?>">
    <div class="modal-content">
      <div class="modal-header">
        <h3>اصلاحیه پرداخت</h3>
        <button class="icon-btn" type="button" data-close-modal>×</button>
      </div>
      <form method="post" action="<?= e(url('payments/correct/' . $payment['id'])) ?>">
        <div class="modal-body form-grid">
          <?= csrf_field() ?>
          <input type="hidden" name="date_from" value="<?= e($_GET['date_from'] ?? '') ?>">
          <input type="hidden" name="date_to" value="<?= e($_GET['date_to'] ?? '') ?>">
          <input type="hidden" name="contract_number" value="<?= e($_GET['contract_number'] ?? '') ?>">
          <input type="hidden" name="customer" value="<?= e($_GET['customer'] ?? '') ?>">
          <div class="notice error full">آیا مطمئن هستید؟ این عملیات وضعیت قسط را به قبل از این پرداخت برمی‌گرداند.</div>
          <?php if (($payment['method'] ?? '') === 'zibal'): ?>
            <div class="notice info full">این اصلاحیه فقط وضعیت داخلی سامانه را تغییر می‌دهد و بازگشت وجه بانکی انجام نمی‌دهد.</div>
          <?php endif; ?>
          <label>مشتری<input value="<?= e($payment['customer_name']) ?>" disabled></label>
          <label>مبلغ<input value="<?= e(money_toman($payment['amount'])) ?>" disabled></label>
          <label class="full">علت اصلاحیه<textarea name="correction_reason" required placeholder="علت دقیق اصلاحیه را وارد کنید"></textarea></label>
        </div>
        <div class="modal-footer">
          <button class="btn warning" type="submit">ثبت اصلاحیه</button>
          <button class="btn secondary" type="button" data-close-modal>انصراف</button>
        </div>
      </form>
    </div>
  </div>
<?php endforeach; ?>
