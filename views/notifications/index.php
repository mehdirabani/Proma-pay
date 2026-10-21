<div class="proma-notifications-workspace">
<section class="card proma-notifications-inbox">
  <div class="card-header card-no-border">
    <div class="header-top">
      <h2>اعلان‌های من</h2>
      <div class="actions">
        <?php if (Auth::role() === 'admin'): ?><a class="btn small secondary" href="<?= e(url('settings', ['tab' => 'notifications'])) ?>">تنظیم رویدادهای اعلان</a><?php endif; ?>
        <?php if ((int) ($unreadCount ?? 0) > 0): ?>
          <span class="badge warning"><?= to_persian_digits($unreadCount) ?> خوانده‌نشده</span>
        <?php endif; ?>
        <?php if (!empty($notifications)): ?>
          <form id="notifications-bulk-delete" method="post" action="<?= e(url('notifications/bulkDelete')) ?>">
            <?= csrf_field() ?>
            <input type="checkbox" data-check-all="notifications" aria-label="انتخاب همه اعلان‌ها">
            <button class="btn small danger" type="submit">حذف انتخاب‌شده‌ها</button>
          </form>
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
          <?php if (($item['type'] ?? '') !== 'announcement'): ?><label class="proma-notification-select" aria-label="انتخاب اعلان"><input type="checkbox" name="notification_ids[]" value="<?= (int) $item['id'] ?>" form="notifications-bulk-delete" data-check-item="notifications"></label><?php else: ?><span class="proma-notification-select" aria-hidden="true"></span><?php endif; ?>
            <div>
            <?php if (($item['type'] ?? '') === 'announcement'): ?><span class="badge info">اعلان سراسری</span><?php endif; ?>
            <strong><?= e($item['title'] ?? '') ?></strong>
            <p><?= e($item['body'] ?? '') ?></p>
            <small><?= e($item['relative_time'] ?? jdatetime($item['created_at'] ?? '')) ?></small>
          </div>
          <div class="actions">
            <?php if (empty($item['is_read'])): ?><span class="badge info">جدید</span><?php endif; ?>
            <?php if (!empty($item['url'])): ?><a class="btn small secondary" href="<?= e(url('notifications/open/' . (int) $item['id'])) ?>"><i data-feather="external-link"></i> مشاهده</a><?php endif; ?>
            <?php if (($item['type'] ?? '') !== 'announcement'): ?>
              <form method="post" action="<?= e(url('notifications/archive/' . (int) $item['id'])) ?>"><?= csrf_field() ?><button class="btn small secondary icon-only" type="submit" title="بایگانی" aria-label="بایگانی"><i data-feather="archive"></i></button></form>
              <form method="post" action="<?= e(url('notifications/retire/' . (int) $item['id'])) ?>"><?= csrf_field() ?><button class="btn small danger icon-only" type="submit" title="حذف از فهرست من" aria-label="حذف از فهرست من"><i data-feather="trash-2"></i></button></form>
            <?php endif; ?>
          </div>
        </article>
      <?php endforeach; ?>
      <?php if (empty($notifications)): ?><div class="empty">اعلانی برای شما ثبت نشده است.</div><?php endif; ?>
    </div>
  </div>
</section>
<div class="proma-notifications-utility">
<section class="card proma-web-push-card">
  <div class="card-header card-no-border"><div><h2>اعلان مرورگر و گوشی</h2><p>اعلان‌ها حتی وقتی صفحهٔ سامانه باز نیست، روی این دستگاه نمایش داده می‌شوند.</p></div></div>
  <div class="card-body">
    <?php if (!empty($webPushEnabled) && !empty($webPushPublicKey)): ?>
      <div class="proma-web-push-control" data-web-push-widget
        data-enabled="1"
        data-subscribed="<?= !empty($webPushSubscribed) ? '1' : '0' ?>"
        data-public-key="<?= e($webPushPublicKey) ?>"
        data-worker-url="<?= e(asset_url('service-worker.js')) ?>"
        data-subscribe-url="<?= e(url('notifications/subscribe')) ?>"
        data-unsubscribe-url="<?= e(url('notifications/unsubscribe')) ?>"
        data-csrf="<?= e(Csrf::token()) ?>">
        <p class="proma-web-push-status" data-web-push-status role="status" aria-live="polite">
          <?= !empty($webPushSubscribed) ? 'این دستگاه برای دریافت اعلان فعال است.' : 'برای فعال‌سازی اعلان روی این دستگاه، دکمه را بزنید و اجازهٔ مرورگر را تأیید کنید.' ?>
        </p>
        <div class="actions">
          <button class="btn" type="button" data-web-push-enable<?= !empty($webPushSubscribed) ? ' hidden' : '' ?>>فعال‌سازی روی این دستگاه</button>
          <button class="btn secondary" type="button" data-web-push-disable<?= empty($webPushSubscribed) ? ' hidden' : '' ?>>غیرفعال‌کردن این دستگاه</button>
        </div>
        <small class="proma-form-help">در iPhone و iPad، برای دریافت اعلان باید سامانه را به صفحهٔ اصلی اضافه کنید؛ مرورگر نیز باید اعلان‌ها را پشتیبانی کند.</small>
      </div>
    <?php else: ?>
      <div class="notice info">اعلان مرورگر هنوز از سوی مدیر سامانه فعال نشده است. اعلان‌های داخل سامانه در دسترس می‌مانند.</div>
    <?php endif; ?>
  </div>
</section>
<?php if (Auth::role() === 'admin'): $roleLabels = app_config('roles', []); ?>
<section class="card proma-announcement-composer">
  <div class="card-header"><h2>ارسال اعلان سراسری سامانه</h2></div>
  <div class="card-body">
    <form method="post" action="<?= e(url('notifications/sendBroadcast')) ?>" class="form-grid">
      <?= csrf_field() ?>
      <label>عنوان اعلان<input name="title" maxlength="190" required></label>
      <label>پیوند داخلی (اختیاری)<input name="local_url" maxlength="255" placeholder="legal یا notifications" dir="ltr"></label>
      <label class="full">متن اعلان<textarea name="body" rows="4" maxlength="10000" required></textarea></label>
      <label>مخاطب<select name="audience"><option value="all">همهٔ کاربران فعال</option><option value="roles">نقش‌های انتخاب‌شده</option></select></label>
      <label>انقضا (اختیاری)<input name="expires_at" type="datetime-local"></label>
      <fieldset class="full form-grid"><legend>نقش‌های دریافت‌کننده</legend>
        <?php foreach (($broadcastRoles ?? []) as $roleRow): $role = (string) ($roleRow['role'] ?? ''); ?>
          <?php if ($role !== ''): ?><label><input type="checkbox" name="roles[]" value="<?= e($role) ?>"><span><?= e($roleLabels[$role] ?? $role) ?></span></label><?php endif; ?>
        <?php endforeach; ?>
      </fieldset>
      <div class="full actions"><button class="btn" type="submit">ارسال اعلان</button><small>اعلان برای کاربران فعال ثبت می‌شود و به دستگاه‌های مجاز آن‌ها نیز Web Push می‌شود.</small></div>
    </form>
    <div class="table-responsive"><table class="data-table"><thead><tr><th>عنوان</th><th>ارسال‌کننده</th><th>گیرنده</th><th>زمان ارسال</th></tr></thead><tbody>
      <?php foreach (($broadcastAnnouncements ?? []) as $announcement): ?><tr><td><?= e($announcement['title'] ?? '') ?></td><td><?= e($announcement['publisher_name'] ?? '') ?></td><td><?= to_persian_digits($announcement['recipient_count'] ?? 0) ?></td><td><?= e(jdatetime($announcement['published_at'] ?? '')) ?></td></tr><?php endforeach; ?>
      <?php if (empty($broadcastAnnouncements)): ?><tr><td colspan="4"><div class="empty">اعلان سراسری ارسال نشده است.</div></td></tr><?php endif; ?>
    </tbody></table></div>
  </div>
</section>
<?php endif; ?>
</div>
</div>
