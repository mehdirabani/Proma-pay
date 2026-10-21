<?php
$banners = $banners ?? [];
$editingBanner = $editingBanner ?? null;
$form = array_merge([
    'id' => null,
    'title' => '',
    'body' => '',
    'eyebrow' => '',
    'desktop_image_path' => '',
    'mobile_image_path' => '',
    'image_alt' => '',
    'cta_label' => '',
    'link_type' => 'internal',
    'link_target' => 'installments/panel',
    'open_in_new_tab' => 0,
    'tone' => 'primary',
    'starts_at' => '',
    'ends_at' => '',
    'is_active' => 1,
    'sort_order' => 10,
], $editingBanner ?: []);
$dateInput = static function ($value) {
    if (!$value) {
        return '';
    }
    $timestamp = strtotime((string) $value);
    return $timestamp ? date('Y-m-d\TH:i', $timestamp) : '';
};
$bannerLink = static function (array $banner) {
    if (($banner['link_type'] ?? '') === 'external') {
        return (string) ($banner['link_target'] ?? '');
    }
    if (($banner['link_type'] ?? '') === 'internal' && !empty($banner['link_target'])) {
        return url((string) $banner['link_target']);
    }
    return '';
};
?>
<div class="proma-v2-page-head">
  <div>
    <span class="proma-v2-eyebrow">تجربه مشتری</span>
    <h1>بنرهای پنل مشتری</h1>
    <p>پیام‌های مهم، پیشنهادها و مسیرهای سریع داشبورد مشتری را بدون تغییر کد مدیریت کنید.</p>
  </div>
  <div class="actions">
    <a class="btn secondary" href="<?= e(url('settings')) ?>"><i data-feather="settings"></i> تنظیمات</a>
    <?php if ($editingBanner): ?><a class="btn" href="<?= e(url('portal-banners')) ?>"><i data-feather="plus"></i> بنر جدید</a><?php endif; ?>
  </div>
</div>

<div class="proma-banner-admin-layout">
  <section class="card proma-banner-editor-card">
    <div class="card-header card-no-border">
      <div class="header-top">
        <div><h3><?= $editingBanner ? 'ویرایش بنر' : 'ساخت بنر تازه' ?></h3><p>نسخه دسکتاپ و موبایل را جداگانه بارگذاری کنید؛ در نبود تصویر موبایل، تصویر دسکتاپ استفاده می‌شود.</p></div>
        <span class="badge badge-light-primary"><?= $editingBanner ? 'شناسه ' . to_persian_digits($editingBanner['id']) : 'جدید' ?></span>
      </div>
    </div>
    <div class="card-body">
      <form method="post" action="<?= e(url('portal-banners/save' . ($editingBanner ? '/' . (int) $editingBanner['id'] : ''))) ?>" enctype="multipart/form-data" class="form-grid proma-banner-form">
        <?= csrf_field() ?>
        <label>برچسب کوتاه<input name="eyebrow" maxlength="80" value="<?= e($form['eyebrow']) ?>" placeholder="مثلاً فرصت ویژه این ماه"></label>
        <label>عنوان بنر <span aria-hidden="true">*</span><input name="title" maxlength="190" required value="<?= e($form['title']) ?>" placeholder="عنوان کوتاه و نتیجه‌محور"></label>
        <label class="full">متن توضیح<textarea name="body" maxlength="500" rows="3" placeholder="توضیح روشن و کوتاه برای مشتری"><?= e($form['body']) ?></textarea></label>
        <label>تصویر دسکتاپ<input type="file" name="desktop_image" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"><small>پیشنهاد: ۱۶۰۰×۵۲۰، حداکثر ۳ مگابایت</small></label>
        <label>تصویر موبایل<input type="file" name="mobile_image" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"><small>پیشنهاد: ۷۵۰×۸۰۰، حداکثر ۳ مگابایت</small></label>
        <?php if (!empty($form['desktop_image_path']) || !empty($form['mobile_image_path'])): ?>
          <div class="full proma-banner-current-images">
            <?php if (!empty($form['desktop_image_path'])): ?><span><img src="<?= e(asset_url($form['desktop_image_path'])) ?>" alt="پیش‌نمایش دسکتاپ"><label><input type="checkbox" name="remove_desktop_image" value="1"> حذف تصویر دسکتاپ</label></span><?php endif; ?>
            <?php if (!empty($form['mobile_image_path'])): ?><span><img src="<?= e(asset_url($form['mobile_image_path'])) ?>" alt="پیش‌نمایش موبایل"><label><input type="checkbox" name="remove_mobile_image" value="1"> حذف تصویر موبایل</label></span><?php endif; ?>
          </div>
        <?php endif; ?>
        <label class="full">متن جایگزین تصویر<input name="image_alt" maxlength="190" value="<?= e($form['image_alt']) ?>" placeholder="شرح کوتاه محتوای تصویر برای دسترس‌پذیری"></label>
        <label>متن دکمه<input name="cta_label" maxlength="80" value="<?= e($form['cta_label']) ?>" placeholder="مشاهده اقساط"></label>
        <label>نوع پیوند<select name="link_type"><option value="none"<?= selected($form['link_type'], 'none') ?>>بدون پیوند</option><option value="internal"<?= selected($form['link_type'], 'internal') ?>>مسیر داخلی سامانه</option><option value="external"<?= selected($form['link_type'], 'external') ?>>پیوند خارجی امن</option></select></label>
        <label class="full">مقصد پیوند<input name="link_target" dir="ltr" value="<?= e($form['link_target']) ?>" placeholder="installments/panel یا https://example.com"></label>
        <label>رنگ و حال‌وهوا<select name="tone"><option value="primary"<?= selected($form['tone'], 'primary') ?>>بنفش اصلی</option><option value="success"<?= selected($form['tone'], 'success') ?>>سبز موفقیت</option><option value="warning"<?= selected($form['tone'], 'warning') ?>>نارنجی توجه</option><option value="info"<?= selected($form['tone'], 'info') ?>>آبی اطلاع‌رسانی</option><option value="neutral"<?= selected($form['tone'], 'neutral') ?>>خنثی</option></select></label>
        <label>اولویت نمایش<input name="sort_order" inputmode="numeric" value="<?= e(to_persian_digits($form['sort_order'])) ?>"></label>
        <label>شروع نمایش<input type="datetime-local" name="starts_at" value="<?= e($dateInput($form['starts_at'])) ?>"></label>
        <label>پایان نمایش<input type="datetime-local" name="ends_at" value="<?= e($dateInput($form['ends_at'])) ?>"></label>
        <div class="full switch-options proma-banner-switches">
          <label><input type="checkbox" name="is_active" value="1"<?= !empty($form['is_active']) ? ' checked' : '' ?>><span>بنر فعال باشد</span></label>
          <label><input type="checkbox" name="open_in_new_tab" value="1"<?= !empty($form['open_in_new_tab']) ? ' checked' : '' ?>><span>پیوند خارجی در زبانه جدید</span></label>
        </div>
        <div class="full actions"><button class="btn" type="submit"><i data-feather="save"></i> <?= $editingBanner ? 'ذخیره تغییرات' : 'ایجاد بنر' ?></button><?php if ($editingBanner): ?><a class="btn secondary" href="<?= e(url('portal-banners')) ?>">انصراف</a><?php endif; ?></div>
      </form>
    </div>
  </section>

  <section class="card proma-banner-list-card">
    <div class="card-header card-no-border"><div class="header-top"><div><h3>بنرهای تعریف‌شده</h3><p><?= to_persian_digits(count($banners)) ?> بنر فعال یا زمان‌بندی‌شده</p></div></div></div>
    <div class="card-body pt-0">
      <div class="proma-banner-admin-list">
        <?php foreach ($banners as $banner): ?>
          <?php $previewLink = $bannerLink($banner); ?>
          <article class="proma-banner-admin-item proma-banner-admin-item--<?= e($banner['tone'] ?? 'primary') ?>">
            <div class="proma-banner-admin-media">
              <?php if (!empty($banner['desktop_image_path'])): ?><img src="<?= e(asset_url($banner['desktop_image_path'])) ?>" alt="<?= e($banner['image_alt'] ?: $banner['title']) ?>" loading="lazy"><?php else: ?><span><i data-feather="image"></i></span><?php endif; ?>
            </div>
            <div class="proma-banner-admin-copy">
              <div><span class="badge <?= !empty($banner['is_active']) ? 'badge-light-success' : 'badge-light-dark' ?>"><?= !empty($banner['is_active']) ? 'فعال' : 'غیرفعال' ?></span><small>اولویت <?= to_persian_digits($banner['sort_order']) ?></small></div>
              <strong><?= e($banner['title']) ?></strong>
              <p><?= e($banner['body'] ?: 'بدون توضیح') ?></p>
              <small><?= $banner['starts_at'] ? 'از ' . e(jdatetime($banner['starts_at'])) : 'شروع فوری' ?> · <?= $banner['ends_at'] ? 'تا ' . e(jdatetime($banner['ends_at'])) : 'بدون پایان' ?></small>
            </div>
            <div class="proma-banner-admin-actions">
              <?php if ($previewLink): ?><a class="icon-btn" href="<?= e($previewLink) ?>"<?= !empty($banner['open_in_new_tab']) ? ' target="_blank" rel="noopener noreferrer"' : '' ?> title="بررسی پیوند"><i data-feather="external-link"></i></a><?php endif; ?>
              <a class="btn small secondary" href="<?= e(url('portal-banners', ['edit' => (int) $banner['id']])) ?>"><i data-feather="edit-2"></i> ویرایش</a>
              <form method="post" action="<?= e(url('portal-banners/toggle/' . (int) $banner['id'])) ?>"><?= csrf_field() ?><button class="btn small <?= !empty($banner['is_active']) ? 'warning' : 'success' ?>" type="submit"><?= !empty($banner['is_active']) ? 'غیرفعال' : 'فعال' ?></button></form>
              <form method="post" action="<?= e(url('portal-banners/archive/' . (int) $banner['id'])) ?>" data-confirm="بنر بایگانی شود؟ تاریخچه آن باقی می‌ماند."><?= csrf_field() ?><button class="icon-btn danger" type="submit" title="بایگانی"><i data-feather="archive"></i></button></form>
            </div>
          </article>
        <?php endforeach; ?>
        <?php if (!$banners): ?><div class="empty"><i data-feather="image"></i><strong>هنوز بنری ساخته نشده است.</strong><span>فرم روبه‌رو را تکمیل کنید تا اولین بنر پنل مشتری ساخته شود.</span></div><?php endif; ?>
      </div>
    </div>
  </section>
</div>
