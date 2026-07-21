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
            <?php if (!empty($item['url'])): ?><a class="btn small secondary" href="<?= e(url('notifications/open/' . (int) $item['id'])) ?>"><i data-feather="external-link"></i> مشاهده</a><?php endif; ?>
            <form method="post" action="<?= e(url('notifications/archive/' . (int) $item['id'])) ?>"><?= csrf_field() ?><button class="btn small secondary icon-only" type="submit" title="بایگانی" aria-label="بایگانی"><i data-feather="archive"></i></button></form>
            <form method="post" action="<?= e(url('notifications/delete/' . (int) $item['id'])) ?>"><?= csrf_field() ?><button class="btn small danger icon-only" type="submit" title="حذف از فهرست من" aria-label="حذف از فهرست من"><i data-feather="trash-2"></i></button></form>
          </div>
        </article>
      <?php endforeach; ?>
      <?php if (empty($notifications)): ?><div class="empty">اعلانی برای شما ثبت نشده است.</div><?php endif; ?>
    </div>
  </div>
</section>
