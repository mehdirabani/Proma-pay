<?php
$eventsByDay = [];
foreach ($events as $event) {
    [$gy, $gm, $gd] = array_map('intval', explode('-', substr($event['event_date'], 0, 10)));
    [$ey, $em, $ed] = gregorian_to_jalali($gy, $gm, $gd);
    if ($ey === (int) $jYear && $em === (int) $jMonth) {
        $eventsByDay[$ed][] = $event;
    }
}
?>
<div class="row">
  <div class="col-xxl-8 col-xl-7">
    <section class="card">
      <div class="card-header card-no-border">
        <div class="header-top">
          <h5>تقویم <?= e($monthTitle) ?></h5>
          <div class="actions">
            <a class="btn small secondary" href="<?= e(url('calendar', ['j_month' => $prevMonth])) ?>">ماه قبل</a>
            <form method="get" action="<?= e(url('calendar')) ?>" class="actions">
              <input type="hidden" name="route" value="calendar">
              <input class="form-control" name="j_month" value="<?= e($jMonthValue) ?>" placeholder="۱۴۰۳/۰۷" inputmode="numeric">
              <button class="btn small secondary" type="submit">نمایش</button>
            </form>
            <a class="btn small secondary" href="<?= e(url('calendar', ['j_month' => $nextMonth])) ?>">ماه بعد</a>
          </div>
        </div>
      </div>
      <div class="card-body pt-0">
        <div class="proma-calendar-grid">
          <?php foreach (['شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه'] as $dayName): ?>
            <div class="proma-calendar-head"><?= e($dayName) ?></div>
          <?php endforeach; ?>
          <?php for ($i = 0; $i < $startOffset; $i++): ?><div class="proma-calendar-cell muted"></div><?php endfor; ?>
          <?php for ($day = 1; $day <= $daysInMonth; $day++): ?>
            <div class="proma-calendar-cell">
              <strong><?= to_persian_digits($day) ?></strong>
              <?php foreach (($eventsByDay[$day] ?? []) as $event): ?>
                <span class="calendar-event <?= e($event['color']) ?>"><?= e($event['title']) ?></span>
              <?php endforeach; ?>
            </div>
          <?php endfor; ?>
        </div>
      </div>
    </section>
  </div>

  <div class="col-xxl-4 col-xl-5">
    <section class="card">
      <div class="card-header card-no-border"><h5>ثبت رویداد</h5></div>
      <div class="card-body">
        <form method="post" action="<?= e(url('calendar/store')) ?>" class="form-grid">
          <?= csrf_field() ?>
          <input type="hidden" name="j_month" value="<?= e(sprintf('%04d/%02d', $jYear, $jMonth)) ?>">
          <label class="full">عنوان<input name="title" required></label>
          <label>تاریخ شمسی<input name="event_date" value="<?= e(jdate(date('Y-m-d'))) ?>" required placeholder="۱۴۰۳/۰۷/۰۱"></label>
          <label>رنگ
            <select name="color">
              <option value="primary">بنفش</option>
              <option value="success">سبز</option>
              <option value="warning">زرد</option>
              <option value="danger">قرمز</option>
              <option value="info">آبی</option>
            </select>
          </label>
          <label class="full">کاربر مرتبط
            <select name="user_id">
              <option value="">بدون کاربر</option>
              <?php foreach ($users as $user): ?><option value="<?= (int) $user['id'] ?>"><?= e($user['full_name']) ?> - <?= e(role_label($user['role'])) ?></option><?php endforeach; ?>
            </select>
          </label>
          <label class="full">توضیح<textarea name="description"></textarea></label>
          <div class="full"><button class="btn" type="submit">ثبت رویداد</button></div>
        </form>
      </div>
    </section>

    <section class="card" style="margin-top:16px">
      <div class="card-header card-no-border"><h5>رویدادهای <?= e($monthTitle) ?></h5></div>
      <div class="card-body pt-0">
        <div class="proma-event-list">
          <?php foreach ($events as $event): ?>
            <div class="proma-event-item">
              <span class="calendar-event <?= e($event['color']) ?>"><?= e(jdate($event['event_date'])) ?></span>
              <div><strong><?= e($event['title']) ?></strong><small><?= e($event['user_name'] ?: 'بدون کاربر') ?></small></div>
              <form method="post" action="<?= e(url('calendar/delete/' . $event['id'])) ?>"><?= csrf_field() ?><input type="hidden" name="j_month" value="<?= e(sprintf('%04d/%02d', $jYear, $jMonth)) ?>"><button class="icon-btn" type="submit">×</button></form>
            </div>
          <?php endforeach; ?>
          <?php if (!$events): ?><div class="empty">رویدادی برای این ماه ثبت نشده است.</div><?php endif; ?>
        </div>
      </div>
    </section>
  </div>
</div>
