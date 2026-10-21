<header class="proma-contract-letterhead">
  <section class="proma-letterhead-number">
    <small><?= e($documentNumberLabel ?? 'شماره قرارداد') ?></small>
    <strong dir="ltr"><?= e($contractNumber ?? '-') ?></strong>
  </section>

  <section class="proma-letterhead-logo">
    <?php if (!empty($printLogoPath)): ?>
      <img src="<?= e(asset_url($printLogoPath)) ?>" alt="لوگوی <?= e($logoText ?? '') ?>" onerror="this.hidden=true;this.nextElementSibling.hidden=false">
      <span class="proma-letterhead-logo-fallback" hidden><?= e($logoText ?? '') ?></span>
    <?php else: ?>
      <span class="proma-letterhead-logo-fallback"><?= e($logoText ?? '') ?></span>
    <?php endif; ?>
  </section>

  <section class="proma-letterhead-meta">
    <span>تاریخ قرارداد: <?= e($contractDate ?? '-') ?></span>
    <?php if (!empty($printProfile['show_customer_header'])): ?>
      <span class="<?= !empty($isCancelled) ? 'contract-cancelled-note' : '' ?>">نام مشتری: <?= e($customerName ?? '-') ?></span>
    <?php endif; ?>
  </section>

  <?php if (!empty($printProfile['show_contract_title'])): ?>
    <section class="proma-letterhead-title">
      <h1><?= e($documentTitle ?? '') ?></h1>
      <?php if (!empty($documentHeader)): ?><p><?= e($documentHeader) ?></p><?php endif; ?>
    </section>
  <?php endif; ?>
</header>
