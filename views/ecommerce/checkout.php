<?php
$items = $cartSummary['items'] ?? [];
$user = $user ?? [];
?>

<div class="grid cols-2">
  <section class="card">
    <div class="card-header card-no-border"><h2>اطلاعات تسویه حساب</h2></div>
    <form method="post" action="<?= e(url('ecommerce/placeOrder')) ?>">
      <div class="card-body form-grid two">
        <?= csrf_field() ?>
        <label>نام و نام خانوادگی<input name="full_name" value="<?= e($user['full_name'] ?? '') ?>" required></label>
        <label>شماره تماس<input name="mobile" value="<?= e(to_persian_digits($user['mobile'] ?? '')) ?>" inputmode="tel" required></label>
        <label>ایمیل<input name="email" value="<?= e($user['email'] ?? '') ?>" dir="ltr"></label>
        <label>روش پرداخت
          <select name="payment_method">
            <option value="manual">هماهنگی با مدیریت</option>
            <option value="card_transfer">کارت به کارت</option>
            <option value="gateway">درگاه پرداخت</option>
          </select>
        </label>
        <label class="full">آدرس<textarea name="address" rows="3"><?= e($user['address'] ?? '') ?></textarea></label>
        <label class="full">توضیحات سفارش<textarea name="notes" rows="3"></textarea></label>
      </div>
      <div class="card-footer"><div class="actions"><button class="btn success" type="submit">ثبت سفارش</button><a class="btn secondary" href="<?= e(url('ecommerce/cart')) ?>">بازگشت به سبد</a></div></div>
    </form>
  </section>

  <section class="card">
    <div class="card-header card-no-border"><h2>خلاصه سفارش</h2></div>
    <div class="card-body">
      <div class="proma-checkout-items">
        <?php foreach ($items as $item): ?>
          <div>
            <span><?= e($item['title']) ?><small> × <?= to_persian_digits($item['quantity']) ?></small></span>
            <strong><?= money_toman($item['line_total']) ?></strong>
          </div>
        <?php endforeach; ?>
      </div>
      <div class="proma-cart-summary compact">
        <span><small>تعداد کالا</small><strong><?= to_persian_digits($cartSummary['quantity'] ?? 0) ?></strong></span>
        <span><small>قابل پرداخت</small><strong><?= money_toman($cartSummary['total'] ?? 0) ?></strong></span>
      </div>
      <div class="notice info">پس از ثبت، سفارش در فهرست سفارشات مدیریت ثبت می‌شود و وضعیت پرداخت از همان صفحه قابل پیگیری است.</div>
    </div>
  </section>
</div>
