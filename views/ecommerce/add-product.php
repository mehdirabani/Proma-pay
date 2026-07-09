<?php
$product = $product ?? null;
$isEdit = !empty($product);
$imagePath = trim((string) ($product['image_path'] ?? ''));
?>

<section class="card proma-ecommerce-form-card">
  <div class="card-header card-no-border">
    <div class="header-top">
      <div>
        <h2><?= $isEdit ? 'ویرایش محصول' : 'افزودن محصول' ?></h2>
        <p>اطلاعات محصول، قیمت، موجودی و تصویر فروشگاهی را ثبت کنید.</p>
      </div>
      <div class="actions">
        <a class="btn secondary" href="<?= e(url('ecommerce/products')) ?>">فهرست محصولات</a>
        <?php if ($isEdit): ?><a class="btn success" href="<?= e(url('ecommerce/product/' . $product['slug'])) ?>" target="_blank">مشاهده محصول</a><?php endif; ?>
      </div>
    </div>
  </div>
  <form method="post" action="<?= e(url('ecommerce/storeProduct')) ?>" enctype="multipart/form-data">
    <div class="card-body">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= (int) ($product['id'] ?? 0) ?>">
      <div class="proma-product-editor-layout">
        <div class="form-grid two">
          <label class="full">نام محصول<input name="title" value="<?= e($product['title'] ?? '') ?>" required></label>
          <label>کد SKU<input name="sku" value="<?= e($product['sku'] ?? '') ?>" dir="ltr"></label>
          <label>برند<input name="brand" value="<?= e($product['brand'] ?? '') ?>"></label>
          <label>دسته‌بندی<input name="category" value="<?= e($product['category'] ?? '') ?>" placeholder="مثلاً موبایل، لوازم جانبی"></label>
          <label>موجودی<input name="stock_quantity" value="<?= e(to_persian_digits($product['stock_quantity'] ?? 0)) ?>" inputmode="numeric"></label>
          <label>قیمت اصلی<input name="price" value="<?= e(isset($product['price']) ? number_format((float) $product['price'], 0) : '') ?>" data-money required></label>
          <label>قیمت فروش ویژه<input name="sale_price" value="<?= e(!empty($product['sale_price']) ? number_format((float) $product['sale_price'], 0) : '') ?>" data-money></label>
          <label>وضعیت
            <select name="status">
              <option value="active"<?= selected($product['status'] ?? 'active', 'active') ?>>فعال</option>
              <option value="draft"<?= selected($product['status'] ?? '', 'draft') ?>>پیش‌نویس</option>
              <option value="inactive"<?= selected($product['status'] ?? '', 'inactive') ?>>غیرفعال</option>
            </select>
          </label>
          <label class="proma-switch-field">
            <span>محصول ویژه</span>
            <input type="checkbox" name="is_featured" value="1"<?= checked((string) ($product['is_featured'] ?? '0'), '1') ?>>
          </label>
          <label class="full">توضیح کوتاه<textarea name="short_description" rows="3" maxlength="500"><?= e($product['short_description'] ?? '') ?></textarea></label>
          <label class="full">توضیحات کامل<textarea name="description" rows="12" data-rich-editor data-rich-editor-height="320"><?= e($product['description'] ?? '') ?></textarea></label>
        </div>
        <aside class="proma-product-media-panel">
          <div class="proma-product-image-preview">
            <?php if ($imagePath !== ''): ?>
              <img src="<?= e(asset_url($imagePath)) ?>" alt="<?= e($product['title'] ?? '') ?>">
            <?php else: ?>
              <span><i data-feather="image"></i></span>
            <?php endif; ?>
          </div>
          <label>تصویر محصول<input type="file" name="image" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"></label>
          <div class="notice info">تصویر محصول در فروشگاه و صفحه محصول نمایش داده می‌شود. حداکثر حجم مجاز ۱۰ مگابایت است.</div>
          <?php if ($isEdit): ?>
            <div class="proma-product-side-meta">
              <span><small>شناسه</small><strong><?= to_persian_digits($product['id']) ?></strong></span>
              <span><small>لینک</small><strong dir="ltr"><?= e($product['slug']) ?></strong></span>
              <span><small>آخرین وضعیت</small><strong><?= e(status_label($product['status'] ?? '')) ?></strong></span>
            </div>
          <?php endif; ?>
        </aside>
      </div>
    </div>
    <div class="card-footer">
      <div class="actions">
        <button class="btn success" type="submit"><?= $isEdit ? 'ذخیره تغییرات' : 'ثبت محصول' ?></button>
        <a class="btn secondary" href="<?= e(url('ecommerce/products')) ?>">انصراف</a>
      </div>
    </div>
  </form>
</section>
