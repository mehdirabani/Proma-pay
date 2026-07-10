<?php
$settings = Settings::allKeyed();
$systemName = $settings['system_name'] ?? app_config('app_name', 'پروما');
$products = $products ?? [];
$featuredProducts = $featuredProducts ?? [];
$categories = $categories ?? [];
$cartSummary = $cartSummary ?? ['quantity' => 0, 'total' => 0];
$heroProduct = $heroProduct ?? ($products[0] ?? null);
$heroImage = !empty($heroProduct['image_path'])
    ? asset_url($heroProduct['image_path'])
    : template_asset_url('images/dashboard-2/discover.png');
?>

<section class="proma-landing-hero" style="--proma-landing-hero-image: url('<?= e($heroImage) ?>')">
  <div class="proma-landing-hero__content">
    <span class="proma-landing-kicker">فروشگاه <?= e($systemName) ?></span>
    <h1>فروشگاه <?= e($systemName) ?></h1>
    <p>محصولات فعال را ببینید، سفارش نقدی ثبت کنید یا برای خرید اقساطی درخواست بفرستید.</p>
    <div class="proma-landing-actions">
      <a class="btn btn-primary" href="<?= e(url('ecommerce/shop')) ?>">مشاهده محصولات</a>
      <a class="btn btn-light" href="<?= e(url('ecommerce/installmentRequest')) ?>">درخواست خرید اقساطی</a>
      <?php if (Auth::check()): ?>
        <a class="btn btn-outline-light" href="<?= e(url('dashboard')) ?>">ورود به پنل</a>
      <?php else: ?>
        <a class="btn btn-outline-light" href="<?= e(url('auth/register')) ?>">ثبت‌نام مشتری</a>
      <?php endif; ?>
    </div>
  </div>
  <a class="proma-landing-cart" href="<?= e(url('ecommerce/cart')) ?>">
    <i data-feather="shopping-cart"></i>
    <span><?= to_persian_digits($cartSummary['quantity'] ?? 0) ?> کالا</span>
    <strong><?= money_toman($cartSummary['total'] ?? 0) ?></strong>
  </a>
</section>

<?php if ($categories): ?>
  <nav class="proma-landing-categories" aria-label="دسته‌بندی محصولات">
    <a href="<?= e(url('ecommerce/shop')) ?>" class="active">همه محصولات</a>
    <?php foreach ($categories as $category): ?>
      <a href="<?= e(url('ecommerce/shop', ['category' => $category['category']])) ?>">
        <span><?= e($category['category']) ?></span>
        <small><?= to_persian_digits($category['products_count']) ?></small>
      </a>
    <?php endforeach; ?>
  </nav>
<?php endif; ?>

<section class="proma-landing-section">
  <div class="proma-landing-section__head">
    <div>
      <span class="proma-landing-eyebrow">محصولات منتخب</span>
      <h2>آماده برای خرید</h2>
    </div>
    <a class="link-only" href="<?= e(url('ecommerce/shop')) ?>">مشاهده همه</a>
  </div>

  <div class="proma-landing-products">
    <?php foreach ($featuredProducts ?: $products as $product): ?>
      <article class="proma-landing-product">
        <a class="proma-landing-product__media" href="<?= e(url('ecommerce/product/' . $product['slug'])) ?>">
          <?php if (!empty($product['image_path'])): ?>
            <img src="<?= e(asset_url($product['image_path'])) ?>" alt="<?= e($product['title']) ?>">
          <?php else: ?>
            <span><i data-feather="image"></i></span>
          <?php endif; ?>
          <?php if (!empty($product['has_discount'])): ?><em>فروش ویژه</em><?php endif; ?>
        </a>
        <div class="proma-landing-product__body">
          <div class="proma-landing-product__meta">
            <span><?= e($product['category'] ?: 'محصول') ?></span>
            <small><?= to_persian_digits($product['stock_quantity']) ?> موجودی</small>
          </div>
          <h3><a href="<?= e(url('ecommerce/product/' . $product['slug'])) ?>"><?= e($product['title']) ?></a></h3>
          <?php if (!empty($product['short_description'])): ?><p><?= e($product['short_description']) ?></p><?php endif; ?>
          <div class="proma-landing-product__price">
            <?php if (!empty($product['has_discount'])): ?><del><?= money_toman($product['price']) ?></del><?php endif; ?>
            <strong><?= money_toman($product['display_price']) ?></strong>
          </div>
          <div class="proma-landing-product__actions">
            <form method="post" action="<?= e(url('ecommerce/addToCart/' . $product['id'])) ?>">
              <?= csrf_field() ?>
              <input type="hidden" name="quantity" value="1">
              <button class="btn btn-success btn-sm" type="submit"><i data-feather="shopping-cart"></i><span>افزودن</span></button>
            </form>
            <a class="btn btn-outline-primary btn-sm" href="<?= e(url('ecommerce/product/' . $product['slug'])) ?>">جزئیات</a>
            <a class="btn btn-warning btn-sm" href="<?= e(url('ecommerce/installmentRequest/' . $product['id'])) ?>">اقساطی</a>
          </div>
        </div>
      </article>
    <?php endforeach; ?>
    <?php if (!$products): ?>
      <div class="proma-landing-empty">در حال حاضر محصول فعالی برای نمایش وجود ندارد.</div>
    <?php endif; ?>
  </div>
</section>

<section class="proma-landing-band">
  <div>
    <span class="proma-landing-eyebrow">مسیر خرید</span>
    <h2>از انتخاب محصول تا ثبت سفارش</h2>
  </div>
  <div class="proma-landing-steps">
    <a href="<?= e(url('ecommerce/shop')) ?>"><i data-feather="grid"></i><span>انتخاب محصول</span></a>
    <a href="<?= e(url('ecommerce/cart')) ?>"><i data-feather="shopping-bag"></i><span>سبد خرید</span></a>
    <a href="<?= e(url('ecommerce/installmentRequest')) ?>"><i data-feather="file-text"></i><span>درخواست اقساطی</span></a>
    <a href="<?= e(Auth::check() ? url('ecommerce/myOrders') : url('auth/register')) ?>"><i data-feather="user-check"></i><span>پیگیری سفارش</span></a>
  </div>
</section>
