<section class="card proma-order-payment">
  <div class="card-header card-no-border">
    <div class="header-top">
      <div>
        <h2>پرداخت سفارش <?= e($order['order_number']) ?></h2>
        <p><?= e($order['full_name']) ?> - <?= to_persian_digits($order['mobile']) ?></p>
      </div>
      <div class="actions">
        <a class="btn secondary" href="<?= e(url(Auth::role() === 'admin' ? 'ecommerce/orders' : 'ecommerce/shop')) ?>">بازگشت</a>
      </div>
    </div>
  </div>
  <div class="card-body">
    <div class="proma-preview-grid">
      <span><small>وضعیت سفارش</small><strong><span class="badge <?= e(badge_class($order['order_status'])) ?>"><?= e(status_label($order['order_status'])) ?></span></strong></span>
      <span><small>وضعیت پرداخت</small><strong><span class="badge <?= e(badge_class($order['payment_status'])) ?>"><?= e(status_label($order['payment_status'])) ?></span></strong></span>
      <span><small>روش پرداخت</small><strong><?= e(payment_method_label($order['payment_method'] === 'gateway' ? 'zibal' : ($order['payment_method'] ?: 'manual'))) ?></strong></span>
      <span><small>مبلغ سفارش</small><strong><?= money_toman($order['total_amount']) ?></strong></span>
    </div>
    <?php if (($order['payment_status'] ?? '') !== 'paid'): ?>
      <div class="notice info">سفارش ثبت شده است. پرداخت فروشگاهی در این نسخه به‌صورت پرونده سفارش ثبت می‌شود و مدیریت می‌تواند از بخش سفارشات پیگیری کند.</div>
    <?php endif; ?>
  </div>
</section>

<section class="card">
  <div class="card-header card-no-border"><h2>اقلام سفارش</h2></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>محصول</th><th>تعداد</th><th>قیمت واحد</th><th>جمع</th></tr></thead>
      <tbody>
      <?php foreach ($order['items'] ?? [] as $item): ?>
        <tr>
          <td><?= e($item['product_title']) ?><br><small dir="ltr"><?= e($item['product_sku'] ?: '') ?></small></td>
          <td><?= to_persian_digits($item['quantity']) ?></td>
          <td><?= money_toman($item['unit_price']) ?></td>
          <td><strong><?= money_toman($item['total_price']) ?></strong></td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($order['items'])): ?><tr><td colspan="4" class="empty">آیتمی برای سفارش ثبت نشده است.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
