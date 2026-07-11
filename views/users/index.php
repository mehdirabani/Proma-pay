<?php
$avatars = ['avatar-1', 'avatar-2', 'avatar-3', 'avatar-4', 'avatar-5', 'avatar-6'];
$viewMode = in_array($_GET['view'] ?? '', ['cards', 'list'], true) ? $_GET['view'] : 'cards';
$canManageUsers = $canManageUsers ?? Auth::role() === 'admin';
$socialLinks = $socialLinks ?? [];
$pagination = $pagination ?? ['total' => count($users ?? []), 'page' => 1, 'pages' => 1, 'per_page' => count($users ?? []) ?: 36];
$pageUrl = function ($page) use ($viewMode) {
    $params = [
        'q' => $_GET['q'] ?? null,
        'role' => $_GET['role'] ?? null,
        'status' => $_GET['status'] ?? null,
        'department' => $_GET['department'] ?? null,
        'view' => $viewMode,
        'page' => (int) $page > 1 ? (int) $page : null,
    ];
    return url('users', array_filter($params, fn($value) => $value !== null && $value !== ''));
};
?>
<section class="card">
  <div class="card-header card-no-border">
    <div class="header-top">
      <h2>فهرست کاربران</h2>
      <div class="actions">
        <a class="btn small <?= $viewMode === 'cards' ? '' : 'secondary' ?>" href="<?= e(url('users', array_filter(['q' => $_GET['q'] ?? null, 'role' => $_GET['role'] ?? null, 'status' => $_GET['status'] ?? null, 'department' => $_GET['department'] ?? null, 'view' => 'cards', 'page' => $_GET['page'] ?? null]))) ?>">کارت‌ها</a>
        <a class="btn small <?= $viewMode === 'list' ? '' : 'secondary' ?>" href="<?= e(url('users', array_filter(['q' => $_GET['q'] ?? null, 'role' => $_GET['role'] ?? null, 'status' => $_GET['status'] ?? null, 'department' => $_GET['department'] ?? null, 'view' => 'list', 'page' => $_GET['page'] ?? null]))) ?>">لیست</a>
        <?php if ($canManageUsers): ?><button class="btn" type="button" data-open-modal="create-user">افزودن کاربر</button><?php endif; ?>
      </div>
    </div>
  </div>
  <div class="card-body">
    <form method="get" action="<?= e(url('users')) ?>" class="form-grid four" data-ajax-filter data-ajax-target="[data-ajax-results='users']">
      <input type="hidden" name="route" value="users">
      <input type="hidden" name="view" value="<?= e($viewMode) ?>">
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
      <?php if (Auth::role() === 'admin'): ?>
        <label>واحد
          <select name="department">
            <option value="">همه واحدها</option>
            <?php foreach (($departments ?? []) as $key => $label): ?>
              <?php if ($key === '') continue; ?>
              <option value="<?= e($key) ?>"<?= selected($_GET['department'] ?? '', $key) ?>><?= e($label) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
      <?php endif; ?>
      <div class="actions"><button class="btn secondary" type="submit">اعمال فیلتر</button><span class="proma-ajax-status" data-ajax-status></span></div>
    </form>
  </div>
</section>

<?php if ($socialLinks): ?>
<section class="proma-social-card-grid proma-users-social-strip">
  <?php foreach ($socialLinks as $social): ?>
    <a class="proma-social-card proma-social-card--<?= e($social['class']) ?>" href="<?= e($social['url']) ?>" target="_blank" rel="noopener noreferrer">
      <span class="proma-social-card__icon"><i data-feather="<?= e($social['icon']) ?>"></i></span>
      <span>
        <strong><?= e($social['label']) ?></strong>
        <small><?= e(parse_url($social['url'], PHP_URL_HOST) ?: $social['url']) ?></small>
      </span>
      <em>مشاهده</em>
    </a>
  <?php endforeach; ?>
</section>
<?php endif; ?>

<div data-ajax-results="users">
<div class="proma-list-meta">
  <span class="badge info">کل کاربران: <?= to_persian_digits($pagination['total'] ?? count($users ?? [])) ?></span>
  <span class="badge muted">صفحه <?= to_persian_digits($pagination['page'] ?? 1) ?> از <?= to_persian_digits($pagination['pages'] ?? 1) ?></span>
</div>

<?php if ($viewMode === 'cards'): ?>
<section class="proma-profile-grid">
  <?php foreach ($users as $item): ?>
    <article class="card proma-profile-tile">
      <div class="card-body">
        <div class="proma-profile-head">
          <?php $userListAvatar = avatar_key_for($item['avatar_key'] ?? null, $item['id'] ?? $item['full_name']); ?>
          <span class="proma-avatar-choice <?= e($userListAvatar) ?>" style="background-image:url('<?= e(avatar_asset_url($userListAvatar)) ?>')" aria-label="<?= e($item['full_name']) ?>"></span>
          <div>
            <h5><?= e($item['full_name']) ?> <?php if (!empty($item['identity_verified'])): ?><span class="badge badge-light-info" title="مدارک هویتی تأیید شده">✓</span><?php endif; ?></h5>
            <p><?= e(role_label($item['role'])) ?> · <?= e(department_label($item['department'] ?? '')) ?> · <?= to_persian_digits($item['mobile']) ?></p>
          </div>
          <span class="badge <?= e(badge_class($item['status'])) ?>"><?= e(status_label($item['status'])) ?></span>
        </div>
        <div class="proma-user-meta">
          <span><small>شناسه</small><strong><?= e($item['username'] ?: $item['national_id'] ?: '-') ?></strong></span>
          <span><small>واحد</small><strong><?= e(department_label($item['department'] ?? '')) ?><?= !empty($item['is_department_manager']) ? ' / مدیر بخش' : '' ?></strong></span>
        </div>
        <?php if ($canManageUsers): ?>
          <div class="actions">
            <button class="btn small secondary icon-only" type="button" data-open-modal="edit-user-<?= (int) $item['id'] ?>" title="ویرایش" aria-label="ویرایش"><i data-feather="edit-2"></i></button>
            <button class="btn small danger icon-only" type="button" data-open-modal="delete-user-<?= (int) $item['id'] ?>" title="حذف" aria-label="حذف"><i data-feather="trash-2"></i></button>
          </div>
        <?php endif; ?>
      </div>
    </article>
  <?php endforeach; ?>
  <?php if (!$users): ?><div class="card"><div class="empty">کاربری با این فیلترها پیدا نشد.</div></div><?php endif; ?>
</section>
<?php endif; ?>

<?php if ($viewMode === 'list'): ?>
<section class="card" style="margin-top:16px">
  <div class="card-header card-no-border"><h2>نمای جدولی کاربران</h2></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>نام</th><th>نقش</th><th>واحد</th><th>شناسه</th><th>موبایل</th><th>وضعیت</th><th>عملیات</th></tr></thead>
      <tbody>
      <?php foreach ($users as $item): ?>
        <tr>
          <td><?= e($item['full_name']) ?> <?php if (!empty($item['identity_verified'])): ?><span class="badge badge-light-info" title="مدارک هویتی تأیید شده">✓</span><?php endif; ?></td>
          <td><?= e(role_label($item['role'])) ?></td>
          <td><?= e(department_label($item['department'] ?? '')) ?><?= !empty($item['is_department_manager']) ? ' / مدیر بخش' : '' ?></td>
          <td><?= e($item['username'] ?: $item['national_id'] ?: '-') ?></td>
          <td><?= to_persian_digits($item['mobile']) ?></td>
          <td><span class="badge <?= e(badge_class($item['status'])) ?>"><?= e(status_label($item['status'])) ?></span></td>
          <td class="actions">
            <?php if ($canManageUsers): ?>
              <button class="btn small secondary icon-only" type="button" data-open-modal="edit-user-<?= (int) $item['id'] ?>" title="ویرایش" aria-label="ویرایش"><i data-feather="edit-2"></i></button>
              <button class="btn small danger icon-only" type="button" data-open-modal="delete-user-<?= (int) $item['id'] ?>" title="حذف" aria-label="حذف"><i data-feather="trash-2"></i></button>
            <?php else: ?>
              <span class="badge muted">مشاهده</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$users): ?><tr><td colspan="7" class="empty">کاربری ثبت نشده است.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
<?php endif; ?>

<?= render_pagination($pagination, $pageUrl) ?>

<?php if ($canManageUsers && !empty($profileRequests)): ?>
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

<?php if ($canManageUsers && !empty($identityRequests)): ?>
<section class="card" style="margin-top:16px">
  <div class="card-header card-no-border"><h5>مدارک هویتی در انتظار بررسی</h5></div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>کاربر</th><th>نقش</th><th>نوع مدرک</th><th>زمان بارگذاری</th><th>فایل</th><th>عملیات</th></tr></thead>
      <tbody>
      <?php foreach ($identityRequests as $document): ?>
        <tr>
          <td><?= e($document['full_name']) ?><br><span class="badge muted"><?= to_persian_digits($document['mobile']) ?></span></td>
          <td><?= e(role_label($document['role'])) ?></td>
          <td><?= e(IdentityDocument::typeLabel($document['document_type'])) ?></td>
          <td><?= e(jdatetime($document['uploaded_at'])) ?></td>
          <td><a class="btn small secondary" href="<?= e(url('profile/identityFile/' . $document['id'])) ?>" target="_blank">مشاهده</a></td>
          <td class="actions">
            <form method="post" action="<?= e(url('profile/identityApprove/' . $document['id'])) ?>"><?= csrf_field() ?><input type="hidden" name="review_note" value=""><button class="btn small success" type="submit">تأیید</button></form>
            <form method="post" action="<?= e(url('profile/identityReject/' . $document['id'])) ?>"><?= csrf_field() ?><input name="review_note" placeholder="علت رد"><button class="btn small danger" type="submit">رد</button></form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>
<?php endif; ?>

<?php if ($canManageUsers): ?>
<div class="modal" id="create-user">
  <div class="modal-content proma-modal-lg">
    <div class="modal-header"><h3>افزودن کاربر</h3><button class="icon-btn" type="button" data-close-modal>×</button></div>
    <form method="post" action="<?= e(url('users/store')) ?>">
      <div class="modal-body proma-user-form">
        <?= csrf_field() ?>
        <?php
          $userFormMode = 'create';
          $userFormData = [];
          $userFormRoles = array_values(array_filter($roles, fn($role) => $role !== 'customer'));
          $userFormDepartments = $departments ?? [];
          $userFormAvatarKeys = $avatars;
          include __DIR__ . '/_form_fields.php';
        ?>
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
        <div class="modal-body proma-user-form">
          <?= csrf_field() ?>
          <?php
            $userFormMode = 'edit';
            $userFormData = $item;
            $userFormRoles = $roles;
            $userFormDepartments = $departments ?? [];
            $userFormAvatarKeys = $avatars;
            include __DIR__ . '/_form_fields.php';
          ?>
        </div>
        <div class="modal-footer"><button class="btn" type="submit">ذخیره تغییرات</button><button class="btn secondary" type="button" data-close-modal>بستن</button></div>
      </form>
    </div>
  </div>

  <div class="modal" id="delete-user-<?= (int) $item['id'] ?>">
    <div class="modal-content">
      <div class="modal-header"><h3>تأیید حذف کاربر</h3><button class="icon-btn" type="button" data-close-modal>×</button></div>
      <form method="post" action="<?= e(url('users/delete/' . $item['id'])) ?>">
        <div class="modal-body">
          <?= csrf_field() ?>
          <?php $deleteCode = ConfirmationCode::hint('user_delete_' . (int) $item['id']); ?>
          <div class="notice error">برای حذف <?= e($item['full_name']) ?> عدد <strong class="ltr"><?= e($deleteCode) ?></strong> را وارد کنید.</div>
          <label>عدد تأیید<input name="confirm_text" required inputmode="numeric" autocomplete="off" placeholder="<?= e($deleteCode) ?>"></label>
        </div>
        <div class="modal-footer"><button class="btn danger icon-only" type="submit" title="حذف" aria-label="حذف"><i data-feather="trash-2"></i></button><button class="btn secondary" type="button" data-close-modal>بستن</button></div>
      </form>
    </div>
  </div>

<?php endforeach; ?>
<?php endif; ?>
</div>
