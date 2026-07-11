<?php
$userFormMode = $userFormMode ?? 'create';
$userFormData = is_array($userFormData ?? null) ? $userFormData : [];
$userFormRoles = $userFormRoles ?? [];
$userFormDepartments = $userFormDepartments ?? [];
$userFormAvatarKeys = $userFormAvatarKeys ?? avatar_options();
$value = function ($key, $default = '') use ($userFormData) {
    return e($userFormData[$key] ?? $default);
};
$isEdit = $userFormMode === 'edit';
?>
<section class="proma-form-section">
  <div class="proma-section-title">
    <h4>حساب کاربری</h4>
    <span>نقش، وضعیت و دسترسی‌های اصلی را اینجا تنظیم کنید.</span>
  </div>
  <div class="form-grid three">
    <label>نقش
      <select name="role" required>
        <?php foreach ($userFormRoles as $role): ?>
          <option value="<?= e($role) ?>"<?= selected($userFormData['role'] ?? '', $role) ?>><?= e(role_label($role)) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>نام کاربری<input name="username" value="<?= $value('username') ?>" required dir="ltr" autocomplete="username"></label>
    <label>رمز عبور<?= $isEdit ? ' جدید' : '' ?><input name="password" type="password" <?= $isEdit ? 'autocomplete="new-password"' : 'required autocomplete="new-password"' ?>></label>
    <label>وضعیت
      <select name="status">
        <option value="active"<?= selected($userFormData['status'] ?? 'active', 'active') ?>>فعال</option>
        <option value="inactive"<?= selected($userFormData['status'] ?? 'active', 'inactive') ?>>غیرفعال</option>
      </select>
    </label>
    <label>واحد
      <select name="department">
        <option value="">بدون واحد</option>
        <?php foreach ($userFormDepartments as $key => $label): ?>
          <?php if ($key === '') continue; ?>
          <option value="<?= e($key) ?>"<?= selected($userFormData['department'] ?? '', $key) ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="proma-checkbox-field">
      <input name="is_department_manager" type="checkbox" value="1"<?= checked((int) ($userFormData['is_department_manager'] ?? 0), 1) ?>>
      <span>مدیر این واحد</span>
    </label>
  </div>
</section>

<section class="proma-form-section">
  <div class="proma-section-title">
    <h4>اطلاعات هویتی</h4>
    <span>اطلاعات پایه کاربر را با فاصله‌گذاری یکسان وارد کنید.</span>
  </div>
  <div class="form-grid three">
    <label>نام کامل<input name="full_name" value="<?= $value('full_name') ?>" required></label>
    <label>نام پدر<input name="father_name" value="<?= $value('father_name') ?>"></label>
    <label>صادره از<input name="issued_from" value="<?= $value('issued_from') ?>"></label>
    <label>کد ملی<input name="national_id" value="<?= $value('national_id') ?>" inputmode="numeric" dir="ltr"></label>
    <label>موبایل<input name="mobile" value="<?= $value('mobile') ?>" inputmode="tel" dir="ltr"></label>
    <label>ایمیل<input name="email" value="<?= $value('email') ?>" type="email" dir="ltr"></label>
    <label class="full">آدرس<textarea name="address"><?= $value('address') ?></textarea></label>
  </div>
</section>

<section class="proma-form-section">
  <div class="proma-section-title">
    <h4>آواتار</h4>
    <span>یک آیکن شناسا برای نمایش سریع انتخاب کنید.</span>
  </div>
  <div class="proma-avatar-options">
    <?php $currentAvatar = $userFormData['avatar_key'] ?? 'avatar-1'; ?>
    <?php foreach ($userFormAvatarKeys as $avatar): ?>
      <label>
        <input type="radio" name="avatar_key" value="<?= e($avatar) ?>"<?= checked($currentAvatar ?: 'avatar-1', $avatar) ?>>
        <span class="proma-avatar-choice <?= e($avatar) ?>" aria-label="<?= e($avatar) ?>"><img data-avatar-image src="<?= e(avatar_asset_url($avatar)) ?>" alt="<?= e($avatar) ?>"></span>
      </label>
    <?php endforeach; ?>
  </div>
</section>
