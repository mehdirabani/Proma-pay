<section class="card">
  <div class="card-header card-no-border">
    <div class="header-top">
      <div>
        <h2>سفارش‌های من</h2>
        <p>وضعیت سفارش‌های فروشگاهی و پرداخت‌های ثبت‌شده شما.</p>
      </div>
      <div class="actions">
        <a class="btn secondary" href="<?= e(url('ecommerce/shop')) ?>">ادامه خرید</a>
        <a class="btn" href="<?= e(url('ecommerce/cart')) ?>">سبد خرید</a>
      </div>
    </div>
  </div>
  <div class="card-body">
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>شماره سفارش</th>
            <th>تعداد کالا</th>
            <th>مبلغ</th>
            <th>پرداخت</th>
            <th>وضعیت سفارش</th>
            <th>تاریخ ثبت</th>
            <th>عملیات</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach (($orders ?? []) as $order): ?>
            <tr>
              <td><strong><?= e($order['order_number']) ?></strong></td>
              <td><?= to_persian_digits($order['quantity_total'] ?? 0) ?> کالا</td>
              <td><?= money_toman($order['total_amount'] ?? 0) ?></td>
              <td><span class="badge <?= e(badge_class($order['payment_status'] ?? 'pending')) ?>"><?= e(status_label($order['payment_status'] ?? 'pending')) ?></span></td>
              <td><span class="badge <?= e(badge_class($order['order_status'] ?? 'pending')) ?>"><?= e(status_label($order['order_status'] ?? 'pending')) ?></span></td>
              <td><?= e(jdatetime($order['created_at'] ?? '')) ?></td>
              <td><a class="btn small secondary" href="<?= e(url('ecommerce/payment/' . (int) $order['id'])) ?>">جزئیات</a></td>
            </tr>
          <?php endforeach; ?>
          <?php if (empty($orders)): ?>
            <tr><td colspan="7" class="empty">هنوز سفارشی ثبت نکرده‌اید.</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</section>
