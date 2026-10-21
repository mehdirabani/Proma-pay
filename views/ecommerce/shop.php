<?php
$pagination = $pagination ?? ['total' => count($products ?? []), 'page' => 1, 'pages' => 1, 'per_page' => count($products ?? []) ?: 24];
$cartSummary = $cartSummary ?? ['quantity' => 0, 'total' => 0];
$pageUrl = function ($page) {
    return url('ecommerce/shop', array_filter([
        'q' => $_GET['q'] ?? null,
        'category' => $_GET['category'] ?? null,
        'page' => (int) $page > 1 ? (int) $page : null,
    ], fn($value) => $value !== null && $value !== ''));
};
?>

<section class="card proma-shop-hero">
  <div class="card-body">
    <div>
      <span class="badge badge-light-primary">فروشگاه پروما</span>
      <h2>انتخاب محصول و ثبت درخواست خرید</h2>
      <p>محصول را انتخاب کنید، به سبد خرید اضافه کنید یا درخواست خرید اقساطی بفرستید.</p>
    </div>
    <a class="proma-shop-cart-pill" href="<?= e(url('ecommerce/cart')) ?>">
      <i data-feather="shopping-cart"></i>
      <span><?= to_persian_digits($cartSummary['quantity'] ?? 0) ?> کالا</span>
      <strong><?= money_toman($cartSummary['total'] ?? 0) ?></strong>
    </a>
  </div>
</section>

<section class="card">
  <div class="card-body">
    <form method="get" action="<?= e(url('ecommerce/shop')) ?>" class="form-grid three">
      <input type="hidden" name="route" value="ecommerce/shop">
      <label>جستجوی محصول<input name="q" value="<?= e($_GET['q'] ?? '') ?>" placeholder="نام، برند یا دسته"></label>
      <label>دسته‌بندی<input name="category" value="<?= e($_GET['category'] ?? '') ?>"></label>
      <div class="actions"><button class="btn secondary" type="submit">جستجو</button><a class="btn small secondary" href="<?= e(url('ecommerce/shop')) ?>">حذف فیلتر</a></div>
    </form>
  </div>
</section>

<div class="proma-list-meta">
  <span class="badge info">محصولات: <?= to_persian_digits($pagination['total'] ?? count($products ?? [])) ?></span>
  <span class="badge muted">صفحه <?= to_persian_digits($pagination['page'] ?? 1) ?> از <?= to_persian_digits($pagination['pages'] ?? 1) ?></span>
</div>

<section class="proma-store-grid">
  <?php foreach ($products as $product): ?>
    <article class="card proma-store-product">
      <a class="proma-store-product__media" href="<?= e(url('ecommerce/product/' . $product['slug'])) ?>">
        <?php if (!empty($product['image_path'])): ?>
          <img src="<?= e(asset_url($product['image_path'])) ?>" alt="<?= e($product['title']) ?>">
        <?php else: ?>
          <span><i data-feather="image"></i></span>
        <?php endif; ?>
        <?php if ($product['has_discount']): ?><em>فروش ویژه</em><?php endif; ?>
      </a>
      <div class="card-body">
        <div class="proma-store-product__meta">
          <span><?= e($product['category'] ?: 'محصول') ?></span>
          <small><?= to_persian_digits($product['stock_quantity']) ?> موجودی</small>
        </div>
        <h3><a href="<?= e(url('ecommerce/product/' . $product['slug'])) ?>"><?= e($product['title']) ?></a></h3>
        <?php if (!empty($product['short_description'])): ?><p><?= e($product['short_description']) ?></p><?php endif; ?>
        <div class="proma-store-product__price">
          <?php if ($product['has_discount']): ?><del><?= money_toman($product['price']) ?></del><?php endif; ?>
          <strong><?= money_toman($product['display_price']) ?></strong>
        </div>
        <div class="proma-store-actions">
          <form method="post" action="<?= e(url('ecommerce/addToCart/' . $product['id'])) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="quantity" value="1">
            <button class="btn small success" type="submit"><i data-feather="shopping-cart"></i><span>افزودن</span></button>
          </form>
          <a class="btn small secondary" href="<?= e(url('ecommerce/product/' . $product['slug'])) ?>">جزئیات</a>
          <a class="btn small warning" href="<?= e(url('ecommerce/installmentRequest/' . $product['id'])) ?>">خرید اقساطی</a>
        </div>
      </div>
    </article>
  <?php endforeach; ?>
  <?php if (!$products): ?>
    <div class="card"><div class="empty">در حال حاضر محصولی برای نمایش وجود ندارد.</div></div>
  <?php endif; ?>
</section>

<?= render_pagination($pagination, $pageUrl) ?>
