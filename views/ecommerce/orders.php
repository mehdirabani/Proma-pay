<section class="card">
  <div class="card-header card-no-border">
    <div class="header-top">
      <div>
        <h2>فهرست سفارشات</h2>
        <p>سفارش‌های فروشگاه و درخواست‌های خرید اقساطی کاربران.</p>
      </div>
      <div class="actions">
        <a class="btn secondary" href="<?= e(url('ecommerce/products')) ?>">محصولات</a>
        <a class="btn" href="<?= e(url('ecommerce/addProduct')) ?>">افزودن محصول</a>
      </div>
    </div>
  </div>
</section>

<div class="grid cols-2">
  <section class="card">
    <div class="card-header card-no-border">
      <div class="header-top">
        <h2>سفارش‌های فروشگاه</h2>
        <span class="badge info"><?= to_persian_digits(count($orders ?? [])) ?> سفارش</span>
      </div>
    </div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>شماره</th><th>مشتری</th><th>مبلغ</th><th>اقلام</th><th>وضعیت</th><th>ثبت</th></tr></thead>
        <tbody>
        <?php foreach ($orders as $order): ?>
          <tr>
            <td><a href="<?= e(url('ecommerce/payment/' . $order['id'])) ?>" target="_blank"><?= e($order['order_number']) ?></a></td>
            <td><?= e($order['full_name']) ?><br><small><?= to_persian_digits($order['mobile']) ?></small></td>
            <td><?= money_toman($order['total_amount']) ?></td>
            <td><?= to_persian_digits($order['quantity_total'] ?? 0) ?> کالا</td>
            <td>
              <span class="badge <?= e(badge_class($order['order_status'])) ?>"><?= e(status_label($order['order_status'])) ?></span>
              <span class="badge <?= e(badge_class($order['payment_status'])) ?>"><?= e(status_label($order['payment_status'])) ?></span>
            </td>
            <td><?= e(jdatetime($order['created_at'])) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$orders): ?><tr><td colspan="6" class="empty">هنوز سفارشی ثبت نشده است.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>

  <section class="card">
    <div class="card-header card-no-border">
      <div class="header-top">
        <h2>درخواست‌های خرید اقساطی</h2>
        <span class="badge warning"><?= to_persian_digits(count($installmentRequests ?? [])) ?> درخواست</span>
      </div>
    </div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>متقاضی</th><th>محصول</th><th>وضعیت</th><th>توضیحات</th><th>ثبت</th></tr></thead>
        <tbody>
        <?php foreach ($installmentRequests as $request): ?>
          <tr>
            <td><?= e($request['full_name']) ?><br><small><?= to_persian_digits($request['mobile']) ?></small></td>
            <td><?= e($request['product_needed']) ?></td>
            <td><span class="badge <?= e(badge_class($request['status'])) ?>"><?= e(status_label($request['status'])) ?></span></td>
            <td><?= $request['notes'] ? nl2br(e($request['notes'])) : '<span class="f-light">بدون توضیح</span>' ?></td>
            <td><?= e(jdatetime($request['created_at'])) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$installmentRequests): ?><tr><td colspan="5" class="empty">درخواست اقساطی ثبت نشده است.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </section>
</div>
