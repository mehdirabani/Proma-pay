<?php if (!empty($banners)): ?>
  <section class="proma-customer-banners" aria-label="پیام‌های مهم پنل مشتری" data-v2-banner-rail>
    <div class="proma-customer-banner-track">
      <?php foreach ($banners as $index => $banner): ?>
        <?php
          $link = '';
          if (($banner['link_type'] ?? '') === 'external') {
              $link = (string) ($banner['link_target'] ?? '');
          } elseif (($banner['link_type'] ?? '') === 'internal' && !empty($banner['link_target'])) {
              $link = url((string) $banner['link_target']);
          }
        ?>
        <article class="proma-customer-banner proma-customer-banner--<?= e($banner['tone'] ?? 'primary') ?>" data-v2-banner-slide aria-label="بنر <?= to_persian_digits($index + 1) ?> از <?= to_persian_digits(count($banners)) ?>">
          <div class="proma-customer-banner-copy">
            <?php if (!empty($banner['eyebrow'])): ?><span><?= e($banner['eyebrow']) ?></span><?php endif; ?>
            <h2><?= e($banner['title']) ?></h2>
            <?php if (!empty($banner['body'])): ?><p><?= e($banner['body']) ?></p><?php endif; ?>
            <?php if ($link && !empty($banner['cta_label'])): ?><a class="btn" href="<?= e($link) ?>"<?= !empty($banner['open_in_new_tab']) ? ' target="_blank" rel="noopener noreferrer"' : '' ?>><?= e($banner['cta_label']) ?><i data-feather="arrow-left"></i></a><?php endif; ?>
          </div>
          <?php if (!empty($banner['desktop_image_path'])): ?>
            <picture class="proma-customer-banner-art">
              <?php if (!empty($banner['mobile_image_path'])): ?><source media="(max-width: 767px)" srcset="<?= e(asset_url($banner['mobile_image_path'])) ?>"><?php endif; ?>
              <img src="<?= e(asset_url($banner['desktop_image_path'])) ?>" alt="<?= e($banner['image_alt'] ?: $banner['title']) ?>" loading="<?= $index === 0 ? 'eager' : 'lazy' ?>">
            </picture>
          <?php else: ?>
            <div class="proma-customer-banner-symbol" aria-hidden="true"><i data-feather="star"></i></div>
          <?php endif; ?>
        </article>
      <?php endforeach; ?>
    </div>
    <?php if (count($banners) > 1): ?><div class="proma-customer-banner-dots" aria-hidden="true"><?php foreach ($banners as $index => $_): ?><span<?= $index === 0 ? ' class="active"' : '' ?>></span><?php endforeach; ?></div><?php endif; ?>
  </section>
<?php endif; ?>
