<?php
$title = 'داده‌ها و سوابق بازیابی‌شده';
$link = static fn($number) => url('backup/data', ['table' => $table, 'page' => $number]);
$secret = static fn($name) => (bool) preg_match('/password|secret|token|api_key|private_key|merchant/i', (string) $name);
?>
<section class="card">
  <div class="card-header"><h1>داده‌ها و سوابق بازیابی‌شده</h1></div>
  <div class="card-body grid">
    <p>همهٔ جدول‌ها، تنظیمات افزونه‌ها و سوابق بخش‌های قدیمی در این بخش قابل مشاهده هستند. این صفحه فقط برای مدیر است و اطلاعات را نمایش می‌دهد. رمزها و کلیدهای اتصال پوشانده می‌شوند؛ مقادیر اصلی در بکاپ حفظ شده‌اند.</p>
    <?php foreach (glob(dirname(__DIR__, 2) . '/storage/restore-archives/*/original-backup.zip') ?: [] as $original): $sourceHash = basename(dirname($original)); if (!preg_match('/\A[a-f0-9]{64}\z/', $sourceHash)) continue; ?>
      <a class="btn secondary" href="<?= e(url('backup/original/' . $sourceHash)) ?>">دانلود اصل بکاپ با تمام فایل‌ها، پارامترها و مشخصات قبلی — <?= e(substr($sourceHash, 0, 12)) ?></a>
    <?php endforeach; ?>
    <form method="get" action="index.php" class="grid">
      <input type="hidden" name="route" value="backup/data">
      <label>جدول داده‌ها
        <select name="table">
          <?php foreach ($tables as $name): ?><option value="<?= e($name) ?>" <?= $name === $table ? 'selected' : '' ?>><?= e($name) ?></option><?php endforeach; ?>
        </select>
      </label>
      <button class="btn" type="submit">نمایش سوابق</button>
    </form>
    <p><strong class="ltr"><?= e($table) ?></strong> — <?= to_persian_digits(number_format($total)) ?> رکورد؛ صفحهٔ <?= to_persian_digits($page) ?> از <?= to_persian_digits($pages) ?></p>
    <div class="table-wrap" tabindex="0" aria-label="سوابق جدول انتخاب‌شده">
      <table>
        <thead><tr><?php foreach ($columns as $column): ?><th scope="col" class="ltr"><?= e($column['Field']) ?></th><?php endforeach; ?></tr></thead>
        <tbody>
          <?php foreach ($rows as $row): ?><tr>
            <?php foreach ($columns as $column): $name = $column['Field']; $value = $row[$name];
              $settingKey = $row['setting_key'] ?? $row['key'] ?? $row['config_key'] ?? $row['name'] ?? '';
              $hidden = $secret($name) || (in_array($name, ['value','setting_value','config_value'], true) && $secret($settingKey)); ?>
              <td><?php if ($hidden && $value !== null && $value !== ''): ?>[مقدار محرمانه محفوظ است]
                <?php elseif ($value === null): ?><span class="muted ltr">NULL</span>
                <?php elseif (strlen((string) $value) > 160): ?><details><summary><?= e(mb_substr((string) $value, 0, 80)) ?>…</summary><pre style="white-space:pre-wrap;max-width:50rem"><?= e($value) ?></pre></details>
                <?php else: ?><?= e($value) ?><?php endif; ?></td>
            <?php endforeach; ?>
          </tr><?php endforeach; ?>
          <?php if (!$rows): ?><tr><td colspan="<?= count($columns) ?>">این جدول رکوردی ندارد.</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
    <nav class="actions" aria-label="صفحه‌های سوابق">
      <?php if ($page > 1): ?><a class="btn secondary" href="<?= e($link(1)) ?>">اولین صفحه</a><a class="btn secondary" href="<?= e($link($page - 1)) ?>">قبلی</a><?php endif; ?>
      <?php if ($page < $pages): ?><a class="btn secondary" href="<?= e($link($page + 1)) ?>">بعدی</a><a class="btn secondary" href="<?= e($link($pages)) ?>">آخرین صفحه</a><?php endif; ?>
      <a class="btn secondary" href="<?= e(url('settings', ['tab'=>'backup'])) ?>">بازگشت به بکاپ</a>
    </nav>
  </div>
</section>
