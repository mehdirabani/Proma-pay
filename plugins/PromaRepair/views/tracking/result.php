<div class="proma-repair">
  <header class="pr-hero"><span class="pr-hero-icon"><i data-feather="map-pin"></i></span><div class="pr-hero-copy"><small>نتیجه پیگیری</small><h2><?= e(trim(($repair['brand'] ?? '') . ' ' . ($repair['model'] ?? '')) ?: 'دستگاه') ?></h2><p><span class="pr-code"><?= e($repair['tracking_code']) ?></span></p></div></header>
  <?php if ($repair['status'] === 'ready_for_delivery'): ?><div class="pr-alert"><strong>دستگاه شما آماده تحویل است.</strong></div><?php endif; ?>
  <section class="pr-card"><div class="pr-card-head"><div><h3>وضعیت فعلی</h3></div><span class="pr-badge is-info"><?= e($repair['status_label']) ?></span></div>
    <div class="pr-dl"><div><dt>دستگاه</dt><dd><?= e(trim(($repair['brand'] ?? '') . ' ' . ($repair['model'] ?? '')) ?: '—') ?></dd></div><div><dt>زمان تحویل تخمینی</dt><dd><?= $repair['estimated_delivery_at'] ? e(jdatetime($repair['estimated_delivery_at'])) : 'اعلام نشده' ?></dd></div></div>
    <div class="pr-timeline mt-3"><?php foreach ($repair['timeline'] as $item): ?><div class="pr-timeline-item"><strong><?= e(\Proma\Plugins\Repair\Domain\RepairWorkflow::customerLabel($item['new_status'])) ?></strong><small><?= e(jdatetime($item['created_at'])) ?></small></div><?php endforeach; ?></div>
    <a class="btn secondary mt-3" href="<?= e(url('plugin/repair/track')) ?>">پیگیری کد دیگر</a>
  </section>
</div>
