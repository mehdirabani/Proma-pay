<?php
$product = $product ?? null;
$user = $user ?? [];
$productTitle = $product['title'] ?? '';
?>

<div class="grid cols-2">
  <section class="card">
    <div class="card-header card-no-border">
      <div>
        <h2>فرم درخواست خرید اقساطی</h2>
        <p>درخواست شما برای بررسی شرایط اقساطی در اختیار مدیریت قرار می‌گیرد.</p>
      </div>
    </div>
    <form method="post" action="<?= e(url('ecommerce/storeInstallmentRequest')) ?>">
      <div class="card-body form-grid two">
        <?= csrf_field() ?>
        <input type="hidden" name="product_id" value="<?= (int) ($product['id'] ?? 0) ?>">
        <label>نام و نام خانوادگی<input name="full_name" value="<?= e($user['full_name'] ?? '') ?>" required></label>
        <label>شماره تماس<input name="mobile" value="<?= e(to_persian_digits($user['mobile'] ?? '')) ?>" inputmode="tel" required></label>
        <label class="full">محصول مورد نیاز<input name="product_needed" value="<?= e($productTitle) ?>" required></label>
        <label class="full">توضیحات<textarea name="notes" rows="4" placeholder="در صورت نیاز، مدل، رنگ، ظرفیت یا شرایط مدنظر را بنویسید."></textarea></label>
      </div>
      <div class="card-footer"><div class="actions"><button class="btn success" type="submit">ثبت درخواست</button><a class="btn secondary" href="<?= e(url('ecommerce/shop')) ?>">بازگشت به فروشگاه</a></div></div>
    </form>
  </section>

  <section class="card">
    <div class="card-header card-no-border"><h2>محصول انتخاب‌شده</h2></div>
    <div class="card-body">
      <?php if ($product): ?>
        <div class="proma-installment-product-summary">
          <div class="proma-product-image-preview compact">
            <?php if (!empty($product['image_path'])): ?>
              <img src="<?= e(asset_url($product['image_path'])) ?>" alt="<?= e($product['title']) ?>">
            <?php else: ?>
              <span><i data-feather="image"></i></span>
            <?php endif; ?>
          </div>
          <h3><?= e($product['title']) ?></h3>
          <p><?= e($product['short_description'] ?? '') ?></p>
          <strong><?= money_toman($product['display_price']) ?></strong>
          <a class="btn small secondary" href="<?= e(url('ecommerce/product/' . $product['slug'])) ?>">مشاهده محصول</a>
        </div>
      <?php else: ?>
        <div class="notice info">اگر محصول خاصی مدنظر دارید نام آن را در فرم وارد کنید. مدیریت پس از بررسی با شما هماهنگ می‌کند.</div>
      <?php endif; ?>
    </div>
  </section>
</div>
