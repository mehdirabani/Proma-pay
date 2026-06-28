<?php $avatars = ['avatar-1', 'avatar-2', 'avatar-3', 'avatar-4', 'avatar-5', 'avatar-6']; ?>
<section class="card">
  <div class="card-header card-no-border">
    <div class="header-top">
      <h2>فهرست کاربران</h2>
      <?php if (!empty($canManageUsers)): ?><button class="btn" type="button" data-open-modal="create-user">افزودن کاربر</button><?php endif; ?>
    </div>
  </div>
  <div class="card-body">
    <form method="get" action="<?= e(url('users')) ?>" class="form-grid four">
      <input type="hidden" name="route" value="users">
      <label>جستجو<input name="q" value="<?= e($_GET['q'] ?? '') ?>" placeholder="نام، موبایل، ایمیل یا شناسه"></label>
      <label>نقش
        <select name="role">
          <option value="">همه نقش‌ها</option>
          <?php foreach ($roles as $role): ?><option value="<?= e($role) ?>"<?= selected($_GET['role'] ?? '', $role) ?>><?= e(role_label($role)) ?></option><?php endforeach; ?>
        </select>
      </label>
      <label>وضعیت
        <select name="status">
          <option value="">همه وضعیت‌ها</option>
          <option value="active"<?= selected($_GET['status'] ?? '', 'active') ?>>فعال</option>
          <option value="inactive"<?= selected($_GET['status'] ?? '', 'inactive') ?>>غیرفعال</option>
        </select>
      </label>
      <div class="actions"><button class="btn secondary" type="submit">اعمال فیلتر</button></div>
    </form>
  </div>
</section>

<section class="proma-profile-grid">
  <?php foreach ($users as $item): ?>
    <article class="card proma-profile-tile">
      <div class="card-body">
        <div class="proma-profile-head">
          <span class="proma-avatar-choice <?= e($item['avatar_key'] ?: 'avatar-1') ?>"><?= e(mb_substr($item['full_name'], 0, 1, 'UTF-8')) ?></span>
          <div>
            <h5><?= e($item['full_name']) ?></h5>
            <p><?= e(role_label($item['role'])) ?> · <?= e(department_label($item['department'] ?? '')) ?> · <?= to_persian_digits($item['mobile']) ?></p>
          </div>
          <span class="badge <?= e(badge_class($item['status'])) ?>"><?= e(status_label($item['status'])) ?></span>
        </div>
        <div class="proma-user-meta">
          <span><small>شناسه</small><strong><?= e($item['username'] ?: $item['national_id'] ?: '-') ?></strong></span>
          <span><small>ایمیل</small><strong><?= e($item['email'] ?: '-') ?></strong></span>
        </div>
        <?php if ($item['role'] === 'customer'): ?>
          <div class="proma-medal-row">
            <?php foreach (($item['medals'] ?? []) as $medal): ?><span class="badge badge-light-warning"><?= e($medal['title']) ?></span><?php endforeach; ?>
            <?php if (empty($item['medals'])): ?><span class="badge muted">بدون مدال</span><?php endif; ?>
          </div>
        <?php endif; ?>
        <?php if (!empty($canManageUsers)): ?><div class="actions">
          <button class="btn small secondary" type="button" data-open-modal="edit-user-<?= (int) $item['id'] ?>">ویرایش</button>
          <?php if ($item['role'] === 'customer'): ?><button class="btn small warning" type="button" data-open-modal="medals-user-<?= (int) $item['id'] ?>">مدال‌ها</button><?php endif; ?>
          <form method="post" action="<?= e(url('users/delete/' . $item['id'])) ?>">
            <?= csrf_field() ?>
            <button class="btn small danger" type="submit">حذف</button>
          </form>
        </div><?php endif; ?>
      </div>
    </article>
  <?php endforeach; ?>
  <?php if (!$users): ?><div class="card"><div class="empty">کاربری با این فیلترها پیدا نشد.</div></div><?php endif; ?>
</section>

<section class="card" style="margin-top:16px">
  <div class="card-header card-no-border"><h2>نمای جدولی کاربران</h2></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>نام</th><th>نقش</th><th>شناسه</th><th>موبایل</th><th>وضعیت</th><th>عملیات</th></tr></thead>
      <tbody>
      <?php foreach ($users as $item): ?>
        <tr>
          <td><?= e($item['full_name']) ?></td>
          <td><?= e(role_label($item['role'])) ?><?php if (!empty($item['is_department_manager'])): ?><br><span class="badge badge-light-primary">مدیر بخش</span><?php endif; ?></td>
          <td><?= e($item['username'] ?: $item['national_id'] ?: '-') ?></td>
          <td><?= to_persian_digits($item['mobile']) ?></td>
          <td><span class="badge <?= e(badge_class($item['status'])) ?>"><?= e(status_label($item['status'])) ?></span></td>
          <td class="actions"><?php if (!empty($canManageUsers)): ?><button class="btn small secondary" type="button" data-open-modal="edit-user-<?= (int) $item['id'] ?>">ویرایش</button><?php else: ?><span class="badge muted">نمایش</span><?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$users): ?><tr><td colspan="6" class="empty">کاربری ثبت نشده است.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</section>

<?php if (!empty($profileRequests)): ?>
<section class="card" style="margin-top:16px">
  <div class="card-header card-no-border"><h5>درخواست‌های ویرایش پروفایل</h5></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>کاربر</th><th>نقش</th><th>تغییرات</th><th>تاریخ</th><th>عملیات</th></tr></thead>
      <tbody>
      <?php foreach ($profileRequests as $request): ?>
        <?php $payload = json_decode($request['payload_json'], true) ?: []; ?>
        <tr>
          <td><?= e($request['full_name']) ?><br><span class="badge muted"><?= to_persian_digits($request['mobile']) ?></span></td>
          <td><?= e(role_label($request['role'])) ?></td>
          <td>
            <span class="badge badge-light-info"><?= e($payload['email'] ?? '') ?></span>
            <span class="badge badge-light-primary"><?= e($payload['mobile'] ?? '') ?></span>
            <?php if (!empty($payload['password'])): ?><span class="badge badge-light-warning">تغییر رمز</span><?php endif; ?>
          </td>
          <td><?= e(jdate($request['created_at'])) ?></td>
          <td class="actions">
            <form method="post" action="<?= e(url('profile/approve/' . $request['id'])) ?>"><?= csrf_field() ?><button class="btn small success" type="submit">تأیید</button></form>
            <form method="post" action="<?= e(url('profile/reject/' . $request['id'])) ?>"><?= csrf_field() ?><button class="btn small danger" type="submit">رد</button></form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
<?php endif; ?>

<?php if (!empty($canManageUsers)): ?>
<div class="modal" id="create-user">
  <div class="modal-content proma-modal-lg">
    <div class="modal-header"><h3>افزودن کاربر</h3><button class="icon-btn" type="button" data-close-modal>×</button></div>
    <form method="post" action="<?= e(url('users/store')) ?>">
      <div class="modal-body form-grid three">
        <?= csrf_field() ?>
        <label>نقش
          <select name="role">
            <?php foreach (array_filter($roles, fn($role) => $role !== 'customer') as $role): ?><option value="<?= e($role) ?>"><?= e(role_label($role)) ?></option><?php endforeach; ?>
          </select>
        </label>
        <label>نام کامل<input name="full_name" required></label>
        <label>نام کاربری<input name="username" required dir="ltr"></label>
        <label>کد ملی<input name="national_id" inputmode="numeric"></label>
        <label>موبایل<input name="mobile" inputmode="tel"></label>
        <label>ایمیل<input name="email" type="email" dir="ltr"></label>
        <label>رمز عبور<input name="password" type="password" required></label>
        <label>وضعیت<select name="status"><option value="active">فعال</option><option value="inactive">غیرفعال</option></select></label>
        <label>واحد سازمانی<select name="department"><?php foreach ($departments as $key => $label): ?><option value="<?= e($key) ?>"><?= e($label) ?></option><?php endforeach; ?></select></label>
        <label class="proma-checkline"><input type="checkbox" name="is_department_manager" value="1"> مدیر بخش</label>
      </div>
      <div class="modal-footer"><button class="btn" type="submit">ثبت کاربر</button><button class="btn secondary" type="button" data-close-modal>بستن</button></div>
    </form>
  </div>
</div>

<?php foreach ($users as $item): ?>
  <div class="modal" id="edit-user-<?= (int) $item['id'] ?>">
    <div class="modal-content proma-modal-lg">
      <div class="modal-header"><h3>ویرایش کاربر</h3><button class="icon-btn" type="button" data-close-modal>×</button></div>
      <form method="post" action="<?= e(url('users/update/' . $item['id'])) ?>">
        <div class="modal-body form-grid three">
          <?= csrf_field() ?>
          <label>نقش<select name="role"><?php foreach ($roles as $role): ?><option value="<?= e($role) ?>"<?= selected($item['role'], $role) ?>><?= e(role_label($role)) ?></option><?php endforeach; ?></select></label>
          <label>نام کامل<input name="full_name" value="<?= e($item['full_name']) ?>" required></label>
          <label>نام کاربری<input name="username" value="<?= e($item['username']) ?>" dir="ltr"></label>
          <label>کد ملی<input name="national_id" value="<?= e($item['national_id']) ?>" inputmode="numeric"></label>
          <label>موبایل<input name="mobile" value="<?= e($item['mobile']) ?>" inputmode="tel"></label>
          <label>ایمیل<input name="email" value="<?= e($item['email']) ?>" type="email" dir="ltr"></label>
          <label>رمز عبور تازه<input name="password" type="password"></label>
          <label>وضعیت<select name="status"><option value="active"<?= selected($item['status'], 'active') ?>>فعال</option><option value="inactive"<?= selected($item['status'], 'inactive') ?>>غیرفعال</option></select></label>
          <label>واحد سازمانی<select name="department"><?php foreach ($departments as $key => $label): ?><option value="<?= e($key) ?>"<?= selected($item['department'] ?? '', $key) ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
          <label class="proma-checkline"><input type="checkbox" name="is_department_manager" value="1"<?= checked((int) ($item['is_department_manager'] ?? 0), 1) ?>> مدیر بخش</label>
          <label class="full">نشانی<textarea name="address"><?= e($item['address'] ?? '') ?></textarea></label>
          <div class="full">
            <span class="field-title">آواتارهای مجاز</span>
            <div class="proma-avatar-options">
              <?php foreach ($avatars as $avatar): ?>
                <label><input type="radio" name="avatar_key" value="<?= e($avatar) ?>"<?= checked($item['avatar_key'] ?: 'avatar-1', $avatar) ?>><span class="proma-avatar-choice <?= e($avatar) ?>"><?= e(mb_substr($item['full_name'], 0, 1, 'UTF-8')) ?></span></label>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
        <div class="modal-footer"><button class="btn" type="submit">ذخیره تغییرات</button><button class="btn secondary" type="button" data-close-modal>بستن</button></div>
      </form>
    </div>
  </div>

  <?php if ($item['role'] === 'customer'): ?>
    <div class="modal" id="medals-user-<?= (int) $item['id'] ?>">
      <div class="modal-content">
        <div class="modal-header"><h3>مدال‌های <?= e($item['full_name']) ?></h3><button class="icon-btn" type="button" data-close-modal>×</button></div>
        <div class="modal-body">
          <div class="proma-medal-list">
            <?php foreach (($item['medals'] ?? []) as $medal): ?>
              <div class="proma-medal-item">
                <div><strong><?= e($medal['title']) ?></strong><small><?= to_persian_digits($medal['points']) ?> امتیاز · <?= e($medal['description']) ?></small></div>
                <form method="post" action="<?= e(url('users/medalDelete/' . $medal['id'])) ?>"><?= csrf_field() ?><button class="btn small danger" type="submit">حذف</button></form>
              </div>
            <?php endforeach; ?>
            <?php if (empty($item['medals'])): ?><div class="empty">مدالی ثبت نشده است.</div><?php endif; ?>
          </div>
          <form method="post" action="<?= e(url('users/medalStore/' . $item['id'])) ?>" class="form-grid" style="margin-top:16px">
            <?= csrf_field() ?>
            <label>عنوان مدال<input name="title" required></label>
            <label>امتیاز<input name="points" inputmode="numeric" value="0"></label>
            <label class="full">توضیح<textarea name="description"></textarea></label>
            <div class="full"><button class="btn warning" type="submit">افزودن مدال</button></div>
          </form>
        </div>
      </div>
    </div>
  <?php endif; ?>
<?php endforeach; ?>
<?php endif; ?>
