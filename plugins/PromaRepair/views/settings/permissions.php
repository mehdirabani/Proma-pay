<div class="proma-repair">
  <header class="pr-hero"><span class="pr-hero-icon"><i data-feather="shield"></i></span><div class="pr-hero-copy"><small>Role Permission Matrix</small><h2>مجوزهای تعمیرات</h2><p>دسترسی‌های افزونه برای هر نقش مستقل نگهداری می‌شوند.</p></div></header>
  <div class="pr-alert">دسترسی تعمیرکار به پرونده‌ها همچنان فقط با ارجاع فعال می‌شود؛ این مجوزها جایگزین کنترل مالکیت پرونده نیستند. برای تعمیرکار معمولاً «مشاهده تعمیرات»، «تغییر وضعیت»، «ثبت تشخیص» و «بارگذاری تصاویر» کافی است. مجوزهای مالی و مشاهده رمز را فقط در صورت نیاز روشن کنید.</div>
  <form method="post" action="<?= e(url('plugin/repair/permissions')) ?>"><?= csrf_field() ?>
    <section class="pr-card"><div class="pr-table-wrap"><table class="pr-table"><thead><tr><th scope="col">مجوز</th><?php foreach ($roles as $role => $label): ?><th scope="col"><?= e($label) ?></th><?php endforeach; ?></tr></thead><tbody>
      <?php foreach ($permissions as $key => $label): ?><tr><td data-label="مجوز"><strong><?= e($label) ?></strong><small class="pr-code"><?= e(str_replace('plugin.proma-repair.', '', $key)) ?></small></td><?php foreach ($roles as $role => $roleLabel): ?><td data-label="<?= e($roleLabel) ?>"><label class="pr-check"><input type="checkbox" name="permissions[<?= e($role) ?>][<?= e($key) ?>]" value="1"<?= !empty($matrix[$role][$key]) ? ' checked' : '' ?>> مجاز</label></td><?php endforeach; ?></tr><?php endforeach; ?>
    </tbody></table></div></section>
    <div class="pr-actions"><button class="btn primary" type="submit">ذخیره مجوزها</button><a class="btn secondary" href="<?= e(url('plugin/repair/settings')) ?>">تنظیمات ماژول</a></div>
  </form>
</div>
