<?php
$pagination = $pagination ?? ['total' => count($products ?? []), 'page' => 1, 'pages' => 1, 'per_page' => count($products ?? []) ?: 24];
$pageUrl = function ($page) {
    return url('ecommerce/products', array_filter([
        'q' => $_GET['q'] ?? null,
        'status' => $_GET['status'] ?? null,
        'page' => (int) $page > 1 ? (int) $page : null,
    ], fn($value) => $value !== null && $value !== ''));
};
?>

<section class="card">
  <div class="card-header card-no-border">
    <div class="header-top">
      <div>
        <h2>فهرست محصولات</h2>
        <p>مدیریت موجودی و نمایش محصولات فروشگاه مشتریان.</p>
      </div>
      <div class="actions">
        <a class="btn" href="<?= e(url('ecommerce/addProduct')) ?>"><i data-feather="plus-circle"></i><span>افزودن محصول</span></a>
        <a class="btn secondary" href="<?= e(url('ecommerce/orders')) ?>">سفارشات</a>
      </div>
    </div>
  </div>
  <div class="card-body">
    <form method="get" action="<?= e(url('ecommerce/products')) ?>" class="form-grid three">
      <input type="hidden" name="route" value="ecommerce/products">
      <label>جستجو<input name="q" value="<?= e($_GET['q'] ?? '') ?>" placeholder="نام، SKU، برند یا دسته"></label>
      <label>وضعیت
        <select name="status">
          <option value="">همه وضعیت‌ها</option>
          <option value="active"<?= selected($_GET['status'] ?? '', 'active') ?>>فعال</option>
          <option value="draft"<?= selected($_GET['status'] ?? '', 'draft') ?>>پیش‌نویس</option>
          <option value="inactive"<?= selected($_GET['status'] ?? '', 'inactive') ?>>غیرفعال</option>
        </select>
      </label>
      <div class="actions"><button class="btn secondary" type="submit">اعمال فیلتر</button></div>
    </form>
  </div>
</section>

<div class="proma-list-meta">
  <span class="badge info">کل محصولات: <?= to_persian_digits($pagination['total'] ?? count($products ?? [])) ?></span>
  <span class="badge muted">صفحه <?= to_persian_digits($pagination['page'] ?? 1) ?> از <?= to_persian_digits($pagination['pages'] ?? 1) ?></span>
</div>

<section class="card">
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>محصول</th><th>SKU</th><th>دسته / برند</th><th>قیمت</th><th>موجودی</th><th>وضعیت</th><th>عملیات</th></tr>
      </thead>
      <tbody>
      <?php foreach ($products as $product): ?>
        <tr>
          <td>
            <div class="proma-product-table-title">
              <?php if (!empty($product['image_path'])): ?>
                <img src="<?= e(asset_url($product['image_path'])) ?>" alt="<?= e($product['title']) ?>">
              <?php else: ?>
                <span><i data-feather="image"></i></span>
              <?php endif; ?>
              <div>
                <strong><?= e($product['title']) ?></strong>
                <?php if (!empty($product['short_description'])): ?><small><?= e($product['short_description']) ?></small><?php endif; ?>
              </div>
            </div>
          </td>
          <td dir="ltr"><?= e($product['sku'] ?: '-') ?></td>
          <td><?= e($product['category'] ?: 'بدون دسته') ?><br><small><?= e($product['brand'] ?: 'بدون برند') ?></small></td>
          <td>
            <?php if ($product['has_discount']): ?><del><?= money_toman($product['price']) ?></del><br><?php endif; ?>
            <strong><?= money_toman($product['display_price']) ?></strong>
          </td>
          <td><?= to_persian_digits($product['stock_quantity']) ?></td>
          <td><span class="badge <?= e(badge_class($product['status'])) ?>"><?= e(status_label($product['status'])) ?></span></td>
          <td class="actions">
            <a class="btn small secondary" href="<?= e(url('ecommerce/editProduct/' . $product['id'])) ?>">ویرایش</a>
            <a class="btn small success" href="<?= e(url('ecommerce/product/' . $product['slug'])) ?>" target="_blank">نمایش</a>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$products): ?><tr><td colspan="7" class="empty">محصولی ثبت نشده است.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<?= render_pagination($pagination, $pageUrl) ?>
