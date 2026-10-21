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
$landingText = function ($key, $fallback = '') use ($settings, $systemName) {
    $value = trim((string) ($settings[$key] ?? ''));
    $value = $value !== '' ? $value : $fallback;
    return str_replace('{{system_name}}', $systemName, $value);
};
$showcaseProducts = array_slice($featuredProducts ?: $products, 0, 3);
?>

<section class="proma-landing-hero" style="--proma-landing-hero-image: url('<?= e($heroImage) ?>')">
  <div class="proma-landing-hero__content">
    <span class="proma-landing-kicker"><?= e($landingText('landing_kicker', 'فروشگاه {{system_name}}')) ?></span>
    <h1><?= e($landingText('landing_title', 'خرید نقدی و اقساطی با {{system_name}}')) ?></h1>
    <p><?= e($landingText('landing_subtitle', 'محصولات منتخب را ببینید، سفارش نقدی ثبت کنید یا درخواست خرید اقساطی بفرستید.')) ?></p>
    <div class="proma-landing-actions">
      <a class="btn btn-primary" href="<?= e(url('ecommerce/shop')) ?>"><?= e($landingText('landing_primary_cta', 'مشاهده محصولات')) ?></a>
      <a class="btn btn-light" href="<?= e(url('ecommerce/installmentRequest')) ?>"><?= e($landingText('landing_secondary_cta', 'درخواست خرید اقساطی')) ?></a>
      <?php if (Auth::check()): ?>
        <a class="btn btn-outline-light" href="<?= e(url('dashboard')) ?>">ورود به پنل</a>
      <?php else: ?>
        <a class="btn btn-outline-light" href="<?= e(url('auth/register')) ?>">ثبت‌نام مشتری</a>
      <?php endif; ?>
    </div>
    <div class="proma-landing-hero__chips">
      <span><i data-feather="shield"></i> پرداخت امن</span>
      <span><i data-feather="file-text"></i> درخواست اقساطی</span>
      <span><i data-feather="truck"></i> پیگیری سفارش</span>
    </div>
  </div>
  <div class="proma-landing-hero__visual">
    <?php if ($heroProduct): ?>
      <a class="proma-landing-spotlight" href="<?= e(url('ecommerce/product/' . $heroProduct['slug'])) ?>">
        <span>محصول شاخص</span>
        <strong><?= e($heroProduct['title']) ?></strong>
        <em><?= money_toman($heroProduct['display_price'] ?? $heroProduct['price'] ?? 0) ?></em>
      </a>
    <?php endif; ?>
    <a class="proma-landing-cart" href="<?= e(url('ecommerce/cart')) ?>">
      <i data-feather="shopping-cart"></i>
      <span><?= to_persian_digits($cartSummary['quantity'] ?? 0) ?> کالا</span>
      <strong><?= money_toman($cartSummary['total'] ?? 0) ?></strong>
    </a>
  </div>
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
      <span class="proma-landing-eyebrow"><?= e($landingText('landing_featured_eyebrow', 'پیشنهادهای فروشگاه')) ?></span>
      <h2><?= e($landingText('landing_featured_title', 'محصولات آماده خرید')) ?></h2>
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
    <span class="proma-landing-eyebrow"><?= e($landingText('landing_steps_eyebrow', 'مسیر خرید')) ?></span>
    <h2><?= e($landingText('landing_steps_title', 'از انتخاب محصول تا پیگیری سفارش')) ?></h2>
  </div>
  <div class="proma-landing-steps">
    <a href="<?= e(url('ecommerce/shop')) ?>"><i data-feather="grid"></i><span>انتخاب محصول</span></a>
    <a href="<?= e(url('ecommerce/cart')) ?>"><i data-feather="shopping-bag"></i><span>سبد خرید</span></a>
    <a href="<?= e(url('ecommerce/installmentRequest')) ?>"><i data-feather="file-text"></i><span>درخواست اقساطی</span></a>
    <a href="<?= e(Auth::check() ? url('ecommerce/myOrders') : url('auth/register')) ?>"><i data-feather="user-check"></i><span>پیگیری سفارش</span></a>
  </div>
</section>

<?php if ($showcaseProducts): ?>
  <section class="proma-landing-mini-vitrine" aria-label="ویترین سریع محصولات">
    <?php foreach ($showcaseProducts as $item): ?>
      <a href="<?= e(url('ecommerce/product/' . $item['slug'])) ?>">
        <?php if (!empty($item['image_path'])): ?><img src="<?= e(asset_url($item['image_path'])) ?>" alt="<?= e($item['title']) ?>"><?php endif; ?>
        <span><?= e($item['category'] ?: 'پیشنهاد ویژه') ?></span>
        <strong><?= e($item['title']) ?></strong>
      </a>
    <?php endforeach; ?>
  </section>
<?php endif; ?>
