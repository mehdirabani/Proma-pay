<?php $cartSummary = $cartSummary ?? ['quantity' => 0]; ?>

<section class="card proma-product-page">
  <div class="card-body">
    <div class="proma-product-page__media">
      <?php if (!empty($product['image_path'])): ?>
        <img src="<?= e(asset_url($product['image_path'])) ?>" alt="<?= e($product['title']) ?>">
      <?php else: ?>
        <span><i data-feather="image"></i></span>
      <?php endif; ?>
    </div>
    <div class="proma-product-page__content">
      <div class="proma-product-page__top">
        <span class="badge badge-light-primary"><?= e($product['category'] ?: 'محصول فروشگاه') ?></span>
        <a class="proma-shop-cart-pill compact" href="<?= e(url('ecommerce/cart')) ?>">
          <i data-feather="shopping-cart"></i>
          <span><?= to_persian_digits($cartSummary['quantity'] ?? 0) ?></span>
        </a>
      </div>
      <h2><?= e($product['title']) ?></h2>
      <?php if (!empty($product['brand'])): ?><p class="f-light">برند: <?= e($product['brand']) ?></p><?php endif; ?>
      <?php if (!empty($product['short_description'])): ?><p><?= e($product['short_description']) ?></p><?php endif; ?>
      <div class="proma-product-page__price">
        <?php if ($product['has_discount']): ?><del><?= money_toman($product['price']) ?></del><?php endif; ?>
        <strong><?= money_toman($product['display_price']) ?></strong>
      </div>
      <div class="proma-preview-grid">
        <span><small>موجودی</small><strong><?= to_persian_digits($product['stock_quantity']) ?></strong></span>
        <span><small>کد محصول</small><strong dir="ltr"><?= e($product['sku'] ?: '-') ?></strong></span>
      </div>
      <form method="post" action="<?= e(url('ecommerce/addToCart/' . $product['id'])) ?>" class="proma-product-buy-row">
        <?= csrf_field() ?>
        <label>تعداد<input name="quantity" value="۱" inputmode="numeric"></label>
        <button class="btn success" type="submit"><i data-feather="shopping-cart"></i><span>افزودن به سبد</span></button>
        <a class="btn warning" href="<?= e(url('ecommerce/installmentRequest/' . $product['id'])) ?>">درخواست خرید اقساطی</a>
      </form>
    </div>
  </div>
</section>

<section class="card">
  <div class="card-header card-no-border"><h2>توضیحات محصول</h2></div>
  <div class="card-body proma-rich-content">
    <?php if (!empty($product['description'])): ?>
      <?= $product['description'] ?>
    <?php else: ?>
      <div class="empty">توضیح کاملی برای این محصول ثبت نشده است.</div>
    <?php endif; ?>
  </div>
</section>
