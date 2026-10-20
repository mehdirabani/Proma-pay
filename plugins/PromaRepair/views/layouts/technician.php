<?php
$assetBase = PluginManager::displayPath(dirname(__DIR__, 2));
$currentRoute = strtolower(trim((string) ($_GET['route'] ?? ''), '/'));
$portalUser = Auth::user();
$navigation = [
    'dashboard' => 'داشبورد',
    'orders' => 'تعمیرات من',
    'technician-expenses' => 'هزینه‌های من',
    'technician-requests' => 'درخواست‌های من',
    'technician-history' => 'تاریخچه تعمیرات',
    'technician-profile' => 'پروفایل تعمیرکار',
];
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title) ?></title>
  <link rel="stylesheet" href="<?= e(asset_url('assets/css/app.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset_url('assets/css/design-system/tokens.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset_url('assets/css/components/forms.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset_url($assetBase . '/assets/css/repair-v1-1-3.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset_url($assetBase . '/assets/css/portal-v1-1-3.css')) ?>">
</head>
<body class="pr-technician-portal">
  <a class="pr-skip-link" href="#repair-content">رفتن به محتوای صفحه</a>
  <header class="pr-portal-header">
    <a href="<?= e(url('plugin/repair/dashboard')) ?>" class="pr-portal-brand">Proma Pay <span>پنل تعمیرکار</span></a>
    <div class="pr-portal-account">
      <span><?= e($portalUser['full_name'] ?? '') ?></span>
      <form method="post" action="<?= e(url('auth/logout')) ?>"><?= csrf_field() ?><button type="submit" class="btn secondary">خروج</button></form>
    </div>
  </header>
  <div class="pr-portal-shell">
    <nav class="pr-portal-nav" aria-label="بخش‌های تعمیرکار">
      <?php foreach ($navigation as $path => $label): $target = 'plugin/repair/' . $path; ?>
        <a href="<?= e(url($target)) ?>"<?= $currentRoute === $target ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
      <?php endforeach; ?>
    </nav>
    <main id="repair-content" class="pr-portal-content" tabindex="-1">
      <?php if ($success = flash('success')): ?><div class="pr-alert" role="status"><?= e($success) ?></div><?php endif; ?>
      <?php if ($error = flash('error')): ?><div class="pr-alert danger" role="alert"><?= e($error) ?></div><?php endif; ?>
      <?= $content ?>
    </main>
  </div>
  <script src="<?= e(asset_url('assets/js/app.js')) ?>" defer></script>
  <script src="<?= e(asset_url($assetBase . '/assets/js/repair-v1-1-3.js')) ?>" defer></script>
</body>
</html>
