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
          <div class="proma-calendar-toolbar">
            <a class="btn small secondary" href="<?= e(url('calendar', ['j_month' => $prevMonth])) ?>">ماه قبل</a>
            <form method="get" action="<?= e(url('calendar')) ?>">
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
                <span class="calendar-event <?= e($event['color']) ?>">
                  <?= e($event['title']) ?>
                  <?php if (!empty($event['event_time'])): ?><small><?= e(Event::displayTime($event['event_time'])) ?></small><?php endif; ?>
                </span>
              <?php endforeach; ?>
            </div>
          <?php endfor; ?>
        </div>
      </div>
    </section>
  </div>

  <div class="col-xxl-4 col-xl-5">
    <?php if ($canManageCalendar ?? false): ?>
    <section class="card">
      <div class="card-header card-no-border"><h5>ثبت رویداد</h5></div>
      <div class="card-body">
        <form method="post" action="<?= e(url('calendar/store')) ?>" class="form-grid">
          <?= csrf_field() ?>
          <input type="hidden" name="j_month" value="<?= e(sprintf('%04d/%02d', $jYear, $jMonth)) ?>">
          <label class="full">عنوان<input name="title" required></label>
          <label>تاریخ شمسی<input name="event_date" value="<?= e(jdate(date('Y-m-d'))) ?>" required placeholder="۱۴۰۳/۰۷/۰۱"></label>
          <label>ساعت رویداد<input name="event_time" type="time" value="09:00"></label>
          <label>نوع رویداد
            <select name="event_type">
              <?php foreach ($eventTypeOptions as $value => $label): ?><option value="<?= e($value) ?>"><?= e($label) ?></option><?php endforeach; ?>
            </select>
          </label>
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
            <span class="proma-live-search" data-user-live-search data-search-url="<?= e(url('users/search', ['roles' => 'admin,operator,lawyer,customer'])) ?>">
              <input data-user-search-input placeholder="نام، موبایل، کد ملی یا نقش را جستجو کنید">
              <input type="hidden" name="assigned_user_id" data-user-id-input>
              <span class="proma-live-results" data-user-search-results hidden></span>
              <span class="proma-chip-row" data-user-chip></span>
            </span>
          </label>
          <label>زمان یادآوری
            <select name="reminder_type" data-calendar-reminder-type>
              <?php foreach ($reminderOptions as $value => $label): ?>
                <option value="<?= e($value) ?>"<?= selected($value, '') ?>><?= e($label) ?><?= $value === '' ? ' (' . e(Event::reminderLabel($settings['calendar_default_reminder_type'] ?? '1_day')) . ')' : '' ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <div class="form-grid two full proma-calendar-custom-reminder" data-calendar-custom-reminder hidden>
            <label>تاریخ یادآوری دلخواه<input name="custom_reminder_date" placeholder="۱۴۰۳/۰۷/۰۱"></label>
            <label>ساعت یادآوری دلخواه<input name="custom_reminder_time" type="time" value="09:00"></label>
          </div>
          <label class="full">توضیح<textarea name="description"></textarea></label>
          <div class="full"><button class="btn" type="submit">ثبت رویداد</button></div>
        </form>
      </div>
    </section>
    <?php endif; ?>

    <section class="card" style="margin-top:16px">
      <div class="card-header card-no-border"><h5>رویدادهای <?= e($monthTitle) ?></h5></div>
      <div class="card-body pt-0">
        <div class="proma-event-list">
          <?php foreach ($events as $event): ?>
            <?php
              $isSystem = !empty($event['is_system']);
              $status = $isSystem ? ['label' => status_label($event['installment_status'] ?? 'pending'), 'class' => badge_class($event['installment_status'] ?? 'pending')] : Event::notificationStatus($event);
              $anchor = $isSystem ? e($event['system_key']) : 'event-' . (int) $event['id'];
            ?>
            <div class="proma-event-item">
              <span class="calendar-event <?= e($event['color']) ?>">
                <?= e(jdate($event['event_date'])) ?>
                <small><?= e(Event::displayTime($event['event_time'] ?? null)) ?></small>
              </span>
              <div class="proma-event-meta" id="<?= e($anchor) ?>">
                <strong><?= e($event['title']) ?></strong>
                <small><?= e($event['user_name'] ?: 'بدون کاربر؛ ارسال به مدیران') ?> · <?= e(Event::eventTypeLabel($event['event_type'] ?? 'general')) ?></small>
                <div class="proma-event-badges">
                  <span class="badge badge-light-<?= e($status['class']) ?>"><?= e($status['label']) ?></span>
                  <?php if ($isSystem): ?>
                    <a class="badge badge-light-info" href="<?= e(url('contracts/show/' . $event['contract_id'])) ?>">مشاهده قرارداد</a>
                  <?php else: ?>
                    <span class="badge badge-light-info">یادآوری: <?= e(Event::reminderLabel($event['reminder_type'] ?? '')) ?></span>
                    <?php if (!empty($event['reminder_at'])): ?><span class="badge muted">موعد اعلان: <?= e(jdatetime($event['reminder_at'])) ?></span><?php endif; ?>
                  <?php endif; ?>
                </div>
              </div>
              <?php if (!$isSystem && ($canManageCalendar ?? false)): ?>
                <button class="btn danger icon-only" type="button" data-open-modal="delete-event-<?= (int) $event['id'] ?>" title="حذف" aria-label="حذف"><i data-feather="trash-2"></i></button>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
          <?php if (!$events): ?><div class="empty">رویدادی برای این ماه ثبت نشده است.</div><?php endif; ?>
        </div>
      </div>
    </section>
  </div>
</div>

<?php if ($canManageCalendar ?? false): ?>
  <?php foreach ($events as $event): if (!empty($event['is_system'])) continue; ?>
    <div class="modal" id="delete-event-<?= (int) $event['id'] ?>">
      <div class="modal-content">
        <div class="modal-header"><h3>تأیید حذف رویداد</h3><button class="icon-btn" type="button" data-close-modal>×</button></div>
        <form method="post" action="<?= e(url('calendar/delete/' . $event['id'])) ?>">
          <div class="modal-body">
            <?= csrf_field() ?>
            <input type="hidden" name="j_month" value="<?= e(sprintf('%04d/%02d', $jYear, $jMonth)) ?>">
            <?php $deleteCode = ConfirmationCode::hint('calendar_delete_' . (int) $event['id']); ?>
            <p>برای حذف رویداد «<?= e($event['title']) ?>» عدد <strong class="ltr"><?= e($deleteCode) ?></strong> را وارد کنید.</p>
            <label>عدد تأیید<input name="confirm_text" required inputmode="numeric" autocomplete="off" placeholder="<?= e($deleteCode) ?>"></label>
          </div>
          <div class="modal-footer">
            <button class="btn danger icon-only" type="submit" title="حذف" aria-label="حذف"><i data-feather="trash-2"></i></button>
            <button class="btn secondary" type="button" data-close-modal>بستن</button>
          </div>
        </form>
      </div>
    </div>
  <?php endforeach; ?>
<?php endif; ?>
