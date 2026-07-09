<section class="card">
  <div class="card-header card-no-border">
    <div class="header-top">
      <h2>اعلان‌های من</h2>
      <div class="actions">
        <?php if ((int) ($unreadCount ?? 0) > 0): ?>
          <span class="badge warning"><?= to_persian_digits($unreadCount) ?> خوانده‌نشده</span>
        <?php endif; ?>
        <form method="post" action="<?= e(url('notifications/read')) ?>">
          <?= csrf_field() ?>
          <button class="btn small secondary" type="submit">خواندن همه</button>
        </form>
      </div>
    </div>
  </div>
  <div class="card-body">
    <div class="proma-notification-list">
      <?php foreach (($notifications ?? []) as $item): ?>
        <article class="proma-notification-item <?= empty($item['is_read']) ? 'unread' : '' ?>">
          <div>
            <strong><?= e($item['title'] ?? '') ?></strong>
            <p><?= e($item['body'] ?? '') ?></p>
            <small><?= e($item['relative_time'] ?? jdatetime($item['created_at'] ?? '')) ?></small>
          </div>
          <div class="actions">
            <?php if (empty($item['is_read'])): ?><span class="badge info">جدید</span><?php endif; ?>
            <?php if (!empty($item['url'])): ?><a class="btn small secondary" href="<?= e($item['url']) ?>">مشاهده</a><?php endif; ?>
          </div>
        </article>
      <?php endforeach; ?>
      <?php if (empty($notifications)): ?><div class="empty">اعلانی برای شما ثبت نشده است.</div><?php endif; ?>
    </div>
  </div>
</section>
