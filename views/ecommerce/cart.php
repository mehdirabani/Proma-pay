<?php $items = $cartSummary['items'] ?? []; ?>

<section class="card">
  <div class="card-header card-no-border">
    <div class="header-top">
      <div>
        <h2>سبد خرید</h2>
        <p>محصولات انتخاب‌شده برای ثبت سفارش فروشگاهی.</p>
      </div>
      <div class="actions">
        <a class="btn secondary" href="<?= e(url('ecommerce/shop')) ?>">ادامه خرید</a>
        <?php if ($items): ?><a class="btn success" href="<?= e(url('ecommerce/checkout')) ?>">تسویه حساب</a><?php endif; ?>
      </div>
    </div>
  </div>
  <div class="card-body">
    <?php if (!$items): ?>
      <div class="empty">سبد خرید شما خالی است.</div>
    <?php else: ?>
      <form method="post" action="<?= e(url('ecommerce/updateCart')) ?>">
        <?= csrf_field() ?>
        <div class="table-wrap">
          <table>
            <thead><tr><th>محصول</th><th>قیمت واحد</th><th>تعداد</th><th>جمع</th></tr></thead>
            <tbody>
            <?php foreach ($items as $item): ?>
              <tr>
                <td>
                  <div class="proma-product-table-title">
                    <?php if (!empty($item['image_path'])): ?>
                      <img src="<?= e(asset_url($item['image_path'])) ?>" alt="<?= e($item['title']) ?>">
                    <?php else: ?>
                      <span><i data-feather="image"></i></span>
                    <?php endif; ?>
                    <div>
                      <strong><?= e($item['title']) ?></strong>
                      <small>برای حذف، تعداد را صفر کنید.</small>
                    </div>
                  </div>
                </td>
                <td><?= money_toman($item['display_price']) ?></td>
                <td><input class="proma-cart-qty" name="quantities[<?= (int) $item['id'] ?>]" value="<?= to_persian_digits($item['quantity']) ?>" inputmode="numeric"></td>
                <td><strong><?= money_toman($item['line_total']) ?></strong></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div class="proma-cart-summary">
          <span><small>تعداد کالا</small><strong><?= to_persian_digits($cartSummary['quantity'] ?? 0) ?></strong></span>
          <span><small>جمع سبد</small><strong><?= money_toman($cartSummary['total'] ?? 0) ?></strong></span>
          <div class="actions">
            <button class="btn secondary" type="submit">به‌روزرسانی سبد</button>
            <a class="btn success" href="<?= e(url('ecommerce/checkout')) ?>">تسویه حساب</a>
          </div>
        </div>
      </form>
    <?php endif; ?>
  </div>
</section>
