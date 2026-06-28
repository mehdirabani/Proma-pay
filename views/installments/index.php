<?php $customerMode = $customerMode ?? false; ?>
<section class="card">
  <div class="card-header card-no-border">
    <div class="header-top">
      <h2><?= $customerMode ? 'اقساط قابل پرداخت' : 'فهرست اقساط' ?></h2>
      <?php if (!$customerMode): ?><button class="btn" type="button" data-open-modal="create-installment">افزودن قسط سفارشی</button><?php endif; ?>
    </div>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>قرارداد</th><th>مشتری</th><th>قسط</th><th>سررسید</th><th>مبلغ پایه</th><th>جریمه</th><th>پاداش</th><th>پرداخت شده</th><th>قابل پرداخت</th><th>وضعیت</th><th>عملیات</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($installments as $item): ?>
        <tr>
          <td><?= e($item['contract_number']) ?></td>
          <td><?= e($item['customer_name']) ?></td>
          <td><?= to_persian_digits($item['installment_number']) ?></td>
          <td><?= e(jdate($item['due_date'])) ?></td>
          <td><?= money_toman($item['base_amount']) ?></td>
          <td><?= money_toman($item['penalty']) ?></td>
          <td><?= money_toman($item['reward']) ?></td>
          <td><?= money_toman($item['paid_amount']) ?></td>
          <td><?= money_toman($item['payable']) ?></td>
          <td><span class="badge <?= e(badge_class($item['status'])) ?>"><?= e(status_label($item['status'])) ?></span></td>
          <td class="actions">
            <?php if ($customerMode && $item['payable'] > 0): ?>
              <button class="btn small" type="button" data-open-modal="pay-<?= (int) $item['id'] ?>">پرداخت</button>
            <?php elseif (!$customerMode): ?>
              <button class="btn small secondary" type="button" data-open-modal="manual-<?= (int) $item['id'] ?>">پرداخت دستی</button>
              <button class="btn small warning" type="button" data-open-modal="adjust-<?= (int) $item['id'] ?>">تنظیم</button>
              <form method="post" action="<?= e(url('installments/markPaid/' . $item['id'])) ?>"><?= csrf_field() ?><button class="btn small success" type="submit">تسویه</button></form>
            <?php else: ?>
              <span class="badge success">تسویه شده</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php if ($customerMode): ?>
          <div class="modal" id="pay-<?= (int) $item['id'] ?>">
            <div class="modal-content">
              <div class="modal-header"><h3>پرداخت قسط</h3><button class="icon-btn" type="button" data-close-modal>×</button></div>
              <form method="post" action="<?= e(url('payments/zibal')) ?>">
                <div class="modal-body form-grid">
                  <?= csrf_field() ?>
                  <input type="hidden" name="installment_id" value="<?= (int) $item['id'] ?>">
                  <label>مبلغ پرداخت<input name="amount" data-money value="<?= e(number_format((float) $item['payable'], 0)) ?>" required></label>
                  <div class="full notice">پرداخت کامل پیش از سررسید می‌تواند پاداش خوش‌حسابی ایجاد کند. پرداخت جزئی زودتر از سررسید پاداش زودپرداخت ندارد.</div>
                </div>
                <div class="modal-footer"><button class="btn" type="submit">رفتن به درگاه</button><button class="btn secondary" type="button" data-close-modal>بستن</button></div>
              </form>
            </div>
          </div>
        <?php else: ?>
          <div class="modal" id="manual-<?= (int) $item['id'] ?>">
            <div class="modal-content">
              <div class="modal-header"><h3>ثبت پرداخت دستی</h3><button class="icon-btn" type="button" data-close-modal>×</button></div>
              <form method="post" action="<?= e(url('installments/payment/' . $item['id'])) ?>">
                <div class="modal-body form-grid">
                  <?= csrf_field() ?>
                  <label>مبلغ<input name="amount" data-money value="<?= e(number_format((float) $item['payable'], 0)) ?>" required></label>
                  <label class="full">شرح<input name="description" value="پرداخت دستی"></label>
                </div>
                <div class="modal-footer"><button class="btn" type="submit">ثبت پرداخت</button><button class="btn secondary" type="button" data-close-modal>بستن</button></div>
              </form>
            </div>
          </div>
          <div class="modal" id="adjust-<?= (int) $item['id'] ?>">
            <div class="modal-content">
              <div class="modal-header"><h3>تنظیم جریمه و پاداش</h3><button class="icon-btn" type="button" data-close-modal>×</button></div>
              <form method="post" action="<?= e(url('installments/adjust/' . $item['id'])) ?>">
                <div class="modal-body form-grid">
                  <?= csrf_field() ?>
                  <label>افزایش جریمه<input name="manual_penalty_adjustment" data-money value="<?= e(number_format((float) $item['manual_penalty_adjustment'], 0)) ?>"></label>
                  <label>افزایش پاداش<input name="manual_reward_adjustment" data-money value="<?= e(number_format((float) $item['manual_reward_adjustment'], 0)) ?>"></label>
                </div>
                <div class="modal-footer"><button class="btn" type="submit">ذخیره تنظیمات</button><button class="btn secondary" type="button" data-close-modal>بستن</button></div>
              </form>
            </div>
          </div>
        <?php endif; ?>
      <?php endforeach; ?>
      <?php if (!$installments): ?><tr><td colspan="11" class="empty">قسطی برای نمایش وجود ندارد.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<?php if (!$customerMode): ?>
<div class="modal" id="create-installment">
  <div class="modal-content proma-modal-lg">
    <div class="modal-header"><h3>افزودن قسط سفارشی</h3><button class="icon-btn" type="button" data-close-modal>×</button></div>
    <form method="post" action="<?= e(url('installments/store')) ?>">
      <div class="modal-body form-grid three">
        <?= csrf_field() ?>
        <label>قرارداد<select name="contract_id" required><?php foreach ($contracts as $contract): ?><option value="<?= (int) $contract['id'] ?>"><?= e($contract['contract_number']) ?> - <?= e($contract['customer_name']) ?></option><?php endforeach; ?></select></label>
        <label>سررسید<input name="due_date" value="<?= e($defaultDueDate ?? '') ?>" required placeholder="۱۴۰۳/۰۱/۰۱"></label>
        <label>مبلغ پایه<input name="base_amount" data-money required></label>
      </div>
      <div class="modal-footer"><button class="btn" type="submit">ثبت قسط</button><button class="btn secondary" type="button" data-close-modal>بستن</button></div>
    </form>
  </div>
</div>
<?php endif; ?>
