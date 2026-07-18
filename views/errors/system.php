<?php
$safeStatus = max(400, min(599, (int) ($status ?? 500)));
$safeTitle = (string) ($title ?? 'مشکل داخلی سامانه');
$safeMessage = (string) ($message ?? 'در پردازش درخواست خطایی رخ داد.');
$safeRequestId = (string) ($requestId ?? '');
$isSessionError = $safeStatus === 419;
$isAuthError = $safeStatus === 401;
$isSafeRetry = in_array($safeStatus, [500, 502, 503, 504], true) && strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'GET';
$isFinancialRoute = preg_match('#(?:payment|installment|settlement|gateway)#i', (string) ($_GET['route'] ?? '')) === 1;
$actionUrl = $isSessionError || $isAuthError ? url('auth/login') : url('');
$actionLabel = $isSessionError || $isAuthError ? 'ورود به سامانه' : 'رفتن به داشبورد';
$retryUrl = (string) ($_SERVER['REQUEST_URI'] ?? url(''));
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="robots" content="noindex, nofollow, noarchive">
  <meta name="color-scheme" content="light dark">
  <title><?= e($safeTitle) ?></title>
  <link rel="stylesheet" href="<?= e(asset_url('assets/css/error-pages.css')) ?>">
</head>
<body class="proma-error-body">
  <main class="proma-error-shell" aria-labelledby="error-title">
    <section class="proma-error-card" role="alert" aria-live="assertive">
      <div class="proma-error-mark" aria-hidden="true">
        <span><?= e((string) $safeStatus) ?></span>
      </div>
      <p class="proma-error-kicker">پرما پی</p>
      <h1 id="error-title" tabindex="-1"><?= e($safeTitle) ?></h1>
      <p class="proma-error-message"><?= e($safeMessage) ?></p>
      <?php if ($safeRequestId !== ''): ?>
        <p class="proma-error-request-id">شناسه پیگیری <code dir="ltr"><?= e($safeRequestId) ?></code></p>
      <?php endif; ?>
      <div class="proma-error-actions">
        <?php if ($isSafeRetry && !$isFinancialRoute): ?><a class="proma-error-primary" href="<?= e($retryUrl) ?>">تلاش دوباره</a><?php elseif ($isFinancialRoute): ?><a class="proma-error-primary" href="<?= e(url('payments')) ?>">بررسی وضعیت عملیات</a><?php else: ?><a class="proma-error-primary" href="<?= e($actionUrl) ?>"><?= e($actionLabel) ?></a><?php endif; ?>
        <a class="proma-error-secondary" href="<?= e($actionUrl) ?>">بازگشت</a>
      </div>
      <p class="proma-error-support">در صورت تکرار خطا، شناسه پیگیری را در اختیار پشتیبانی قرار دهید.</p>
    </section>
  </main>
</body>
</html>
