<?php
$user = Auth::user();
$settings = Settings::allKeyed();
$ecommerceEnabled = ecommerce_is_enabled();
$route = trim($_GET['route'] ?? 'dashboard', '/');
$unreadNotifications = Notification::unreadCount(Auth::id());
$unreadMessages = Chat::unreadCount(Auth::id());
$pendingIdentityReviews = 0;
$pendingReceiptReviews = 0;
if (Auth::role() === 'admin') {
    $pendingIdentityReviews = IdentityDocument::pendingCount();
    $pendingReceiptReviews = PaymentReceipt::pendingCount();
}
$pendingReviewCount = $pendingIdentityReviews + $pendingReceiptReviews;
$notifications = Notification::latest(Auth::id(), 6);
$latestNotificationId = Notification::latestId(Auth::id());
$notificationSoundEnabled = (int) ($settings['notifications_sound_enabled'] ?? 1);
$notificationSoundVolume = max(0, min(1, (float) ($settings['notifications_sound_volume'] ?? '0.45')));
$headerCartSummary = ['items' => [], 'subtotal' => 0, 'total' => 0, 'quantity' => 0];
if ($ecommerceEnabled && ($user['role'] ?? '') === 'customer') {
    try {
        $headerCartSummary = Ecommerce::cartSummary();
    } catch (Throwable $e) {
        $headerCartSummary = ['items' => [], 'subtotal' => 0, 'total' => 0, 'quantity' => 0];
    }
}
$notificationTone = static function ($type) {
    $map = [
        'payment' => 'success',
        'payment_receipt' => 'warning',
        'legal' => 'danger',
        'chat' => 'info',
        'calendar' => 'primary',
        'identity' => 'warning',
    ];
    return $map[$type] ?? 'primary';
};
$systemName = $settings['system_name'] ?? app_config('app_name', 'پروما');
$logoText = $settings['logo_text'] ?? $systemName;
$logoPath = trim((string) ($settings['logo_path'] ?? ''));
$logoIconPath = trim((string) ($settings['logo_icon_path'] ?? ''));
$faviconPath = trim((string) ($settings['favicon_path'] ?? ''));
$appIconPath = $logoIconPath ?: $faviconPath;
$compactLogoPath = $logoIconPath ?: $logoPath;
$logoInitial = mb_substr($logoText ?: $systemName, 0, 1, 'UTF-8') ?: 'پ';
$renderFullLogo = static function () use ($logoPath, $logoIconPath, $logoText, $logoInitial) {
    ob_start();
    if ($logoPath !== '') {
        ?><img class="proma-uploaded-logo" src="<?= e(asset_url($logoPath)) ?>" alt="<?= e($logoText) ?>"><?php
    } else {
        if ($logoIconPath !== '') {
            ?><img class="proma-uploaded-logo sm" src="<?= e(asset_url($logoIconPath)) ?>" alt="<?= e($logoText) ?>"><?php
        } else {
            ?><span class="proma-logo-mark"><?= e($logoInitial) ?></span><?php
        }
        ?><span><?= e($logoText) ?></span><?php
    }
    return trim(ob_get_clean());
};
$renderCompactLogo = static function () use ($compactLogoPath, $logoText, $logoInitial) {
    ob_start();
    if ($compactLogoPath !== '') {
        ?><img class="proma-uploaded-logo sm" src="<?= e(asset_url($compactLogoPath)) ?>" alt="<?= e($logoText) ?>"><?php
    } else {
        ?><span class="proma-logo-mark sm"><?= e($logoInitial) ?></span><?php
    }
    return trim(ob_get_clean());
};
$footerText = $settings['footer_text'] ?? 'توسعه‌دهنده: مهدی ربانی - pgm.mehdirabani@gmail.com - github.com/mehdirabani';
$sprite = template_asset_url('svg/icon-sprite.svg');
$userInitial = mb_substr($user['full_name'] ?? 'ک', 0, 1, 'UTF-8');
$userAvatarKey = avatar_key_for($user['avatar_key'] ?? null, $user['id'] ?? ($user['full_name'] ?? ''));
$canViewUsers = Auth::canViewUsers();
$nav = [];
if (Auth::role() === 'admin') {
    $nav = [
        ['dashboard', 'داشبورد', 'stroke-home', 'fill-home'],
        ['notifications', 'اعلان‌ها', 'stroke-task', 'fill-task'],
        ['users', 'کاربران', 'stroke-user', 'fill-user'],
        ['customers', 'مشتریان', 'stroke-user', 'fill-user'],
        ['contracts', 'قراردادها', 'stroke-project', 'fill-project'],
        ['installments', 'اقساط', 'stroke-file', 'fill-file'],
        ['overdue', 'سررسید گذشته', 'stroke-board', 'fill-board'],
        ['payments', 'پرداخت‌ها', 'stroke-ecommerce', 'fill-ecommerce'],
        ['ecommerce', 'تجارت الکترونیک', 'stroke-ecommerce', 'fill-ecommerce', [
            ['ecommerce/landing', 'صفحه لندینگ'],
            ['ecommerce/addProduct', 'افزودن محصول'],
            ['ecommerce/products', 'فهرست محصولات'],
            ['ecommerce/orders', 'فهرست سفارشات'],
        ]],
        ['review', 'بررسی موارد ارسالی', 'stroke-task', 'fill-task'],
        ['legal', 'حقوقی و شکایت‌ها', 'stroke-file', 'fill-file'],
        ['chat', 'گفت‌وگو', 'stroke-chat', 'fill-chat'],
        ['calendar', 'تقویم رویدادها', 'stroke-task', 'fill-task'],
        ['ai', 'تحلیل هوشمند', 'stroke-learning', 'fill-learning'],
        ['file-manager', 'مدیریت فایل', 'stroke-file', 'fill-file'],
        ['medals', 'مدیریت مدال‌ها', 'stroke-award', 'fill-award'],
        ['plugins', 'پلاگین‌ها', 'stroke-others', 'fill-others'],
        ['settings', 'تنظیمات', 'stroke-others', 'fill-others'],
    ];
} elseif (Auth::role() === 'operator') {
    $nav = [
        ['calendar', 'تقویم رویدادها', 'stroke-task', 'fill-task'],
        ['notifications', 'اعلان‌ها', 'stroke-task', 'fill-task'],
        ['overdue', 'سررسید گذشته', 'stroke-board', 'fill-board'],
        ['contracts', 'قراردادها', 'stroke-project', 'fill-project'],
        ['ecommerce/landing', 'فروشگاه', 'stroke-ecommerce', 'fill-ecommerce'],
        ['chat', 'گفت‌وگو', 'stroke-chat', 'fill-chat'],
    ];
} elseif (Auth::role() === 'lawyer') {
    $nav = [
        ['calendar', 'تقویم رویدادها', 'stroke-task', 'fill-task'],
        ['dashboard', 'داشبورد', 'stroke-home', 'fill-home'],
        ['notifications', 'اعلان‌ها', 'stroke-task', 'fill-task'],
        ['lawyer', 'پرونده‌ها', 'stroke-file', 'fill-file'],
        ['ecommerce/landing', 'فروشگاه', 'stroke-ecommerce', 'fill-ecommerce'],
        ['chat', 'گفت‌وگو', 'stroke-chat', 'fill-chat'],
    ];
    if ($canViewUsers) {
        array_splice($nav, 2, 0, [['users', 'کاربران', 'stroke-user', 'fill-user']]);
    }
} else {
    $nav = [
        ['dashboard', 'داشبورد', 'stroke-home', 'fill-home'],
        ['notifications', 'اعلان‌ها', 'stroke-task', 'fill-task'],
        ['portal/contracts', 'قراردادها', 'stroke-project', 'fill-project'],
        ['installments/panel', 'اقساط', 'stroke-file', 'fill-file'],
        ['ecommerce/landing', 'فروشگاه', 'stroke-ecommerce', 'fill-ecommerce'],
        ['ecommerce/cart', 'سبد خرید', 'stroke-board', 'fill-board'],
        ['ecommerce/myOrders', 'سفارش‌های من', 'stroke-ecommerce', 'fill-ecommerce'],
        ['portal/guaranteed', 'ضمانت‌ها', 'stroke-board', 'fill-board'],
        ['portal/history', 'سوابق خرید', 'stroke-ecommerce', 'fill-ecommerce'],
        ['calendar', 'تقویم', 'stroke-task', 'fill-task'],
        ['chat', 'گفت‌وگو', 'stroke-chat', 'fill-chat'],
    ];
}
if (!$ecommerceEnabled) {
    $nav = array_values(array_filter($nav, static function ($item) {
        return strpos((string) ($item[0] ?? ''), 'ecommerce') !== 0;
    }));
}
$pluginMenus = [];
if (Auth::role() === 'admin' && class_exists('PluginManager')) {
    try {
        $pluginMenus = PluginManager::boot()->menus();
        foreach ($pluginMenus as $pluginMenu) {
            if (!empty($pluginMenu['permission']) && !PluginManager::can($pluginMenu['permission'])) {
                continue;
            }
            $nav[] = [$pluginMenu['route'], $pluginMenu['label'], 'stroke-others', 'fill-others'];
        }
    } catch (Throwable $e) {
        $pluginMenus = [];
    }
}
$sidebarIcon = static function (array $item, string $sprite, bool $filled = false): string {
    if (($item[0] ?? '') === 'medals') {
        return $filled ? '' : '<i class="proma-sidebar-nav-icon icofont icofont-award" aria-hidden="true"></i>';
    }
    $icon = $filled ? ($item[3] ?? '') : ($item[2] ?? '');
    return '<svg class="' . ($filled ? 'fill-icon' : 'stroke-icon') . '" aria-hidden="true"><use href="' . e($sprite) . '#' . e($icon) . '"></use></svg>';
};
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($title ?? $systemName) ?></title>
  <link rel="manifest" href="<?= e(url('manifest')) ?>">
  <link rel="icon" href="<?= e($faviconPath ? asset_url($faviconPath) : template_asset_url('images/favicon.png')) ?>">
  <link rel="apple-touch-icon" href="<?= e($appIconPath ? asset_url($appIconPath) : template_asset_url('images/favicon.png')) ?>">
  <link rel="stylesheet" href="<?= e(template_asset_url('css/font-awesome.css')) ?>">
  <link rel="stylesheet" href="<?= e(template_asset_url('css/vendors/icofont.css')) ?>">
  <link rel="stylesheet" href="<?= e(template_asset_url('css/vendors/themify.css')) ?>">
  <link rel="stylesheet" href="<?= e(template_asset_url('css/vendors/flag-icon.css')) ?>">
  <link rel="stylesheet" href="<?= e(template_asset_url('css/vendors/feather-icon.css')) ?>">
  <link rel="stylesheet" href="<?= e(template_asset_url('css/vendors/slick.css')) ?>">
  <link rel="stylesheet" href="<?= e(template_asset_url('css/vendors/slick-theme.css')) ?>">
  <link rel="stylesheet" href="<?= e(template_asset_url('css/vendors/scrollbar.css')) ?>">
  <link rel="stylesheet" href="<?= e(template_asset_url('css/vendors/quill.snow.css')) ?>">
  <link rel="stylesheet" href="<?= e(template_asset_url('css/vendors/quill.bubble.css')) ?>">
  <link rel="stylesheet" href="<?= e(template_asset_url('css/vendors/animate.css')) ?>">
  <link rel="stylesheet" href="<?= e(template_asset_url('css/vendors/bootstrap.rtl.min.css')) ?>">
  <link rel="stylesheet" href="<?= e(template_asset_url('css/style.css')) ?>">
  <link id="color" rel="stylesheet" href="<?= e(template_asset_url('css/color-1.css')) ?>" media="screen">
  <link rel="stylesheet" href="<?= e(template_asset_url('css/responsive.css')) ?>">
  <link rel="stylesheet" href="<?= e(asset_url('assets/css/app.css')) ?>">
  <?php if (plugin_is_active('proma-accounting')): ?><link rel="stylesheet" href="<?= e(asset_url('plugins/PromaAccounting/assets/css/accounting.css')) ?>"><?php endif; ?>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js" defer></script>
</head>
<body onload="if (window.startTime) startTime()" data-user-id="<?= (int) Auth::id() ?>" data-notification-sound="<?= $notificationSoundEnabled ? '1' : '0' ?>" data-notification-volume="<?= e($notificationSoundVolume) ?>">
  <div class="loader-wrapper">
    <div class="loader-index"><span></span></div>
    <svg><defs></defs><filter id="goo"><feGaussianBlur in="SourceGraphic" stdDeviation="11" result="blur"></feGaussianBlur><feColorMatrix in="blur" values="1 0 0 0 0  0 1 0 0 0  0 0 1 0 0  0 0 0 19 -9" result="goo"></feColorMatrix></filter></svg>
  </div>
  <div class="tap-top"><i data-feather="chevrons-up"></i></div>

  <div class="page-wrapper compact-wrapper" id="pageWrapper">
    <div class="page-header">
      <div class="header-wrapper row m-0">
        <form class="form-inline search-full col" method="get" action="<?= e(url($route)) ?>">
          <input type="hidden" name="route" value="<?= e($route) ?>">
          <div class="form-group w-100">
            <div class="Typeahead Typeahead--twitterUsers">
              <div class="u-posRelative">
                <input class="demo-input Typeahead-input form-control-plaintext w-100" type="text" name="q" value="<?= e($_GET['q'] ?? '') ?>" placeholder="جستجو در سامانه..." title="">
                <div class="spinner-border Typeahead-spinner" role="status"><span class="sr-only">در حال بارگذاری...</span></div>
                <i class="close-search" data-feather="x"></i>
              </div>
              <div class="Typeahead-menu"></div>
            </div>
          </div>
        </form>

        <div class="header-logo-wrapper col-auto p-0">
          <div class="logo-wrapper">
            <a class="proma-template-logo" href="<?= e(url('dashboard')) ?>">
              <?= $renderFullLogo() ?>
            </a>
          </div>
          <div class="toggle-sidebar"><i class="status_toggle middle sidebar-toggle" data-feather="align-center"></i></div>
        </div>

        <div class="left-header col-xxl-5 col-xl-6 col-lg-5 col-md-4 col-sm-3 p-0">
          <div class="notification-slider">
            <div class="d-flex h-100 align-items-center">
              <img src="<?= e(template_asset_url('images/giftools.gif')) ?>" alt="">
              <h6 class="mb-0 f-w-400"><span class="font-primary">وضعیت امروز </span><span class="f-light">مدیریت اقساط، قراردادها و پیگیری‌ها آماده است.</span></h6>
              <i class="icon-arrow-top-right f-light"></i>
            </div>
            <div class="d-flex h-100 align-items-center">
              <img src="<?= e(template_asset_url('images/giftools.gif')) ?>" alt="">
              <h6 class="mb-0 f-w-400"><span class="font-primary"><?= e(role_label(Auth::role())) ?> </span><span class="f-light">به <?= e($systemName) ?> خوش آمدید.</span></h6>
            </div>
          </div>
        </div>

        <div class="nav-right col-xxl-7 col-xl-6 col-md-7 col-8 pull-right right-header p-0 ms-auto">
          <ul class="nav-menus">
            <li><span class="header-search"><svg><use href="<?= e($sprite) ?>#search"></use></svg></span></li>
            <li>
              <div class="mode"><svg><use href="<?= e($sprite) ?>#moon"></use></svg></div>
            </li>
            <?php if (($user['role'] ?? '') === 'admin'): ?>
              <li class="proma-header-quick-action">
                <a class="proma-header-contract-btn" href="<?= e(url('contracts', ['open' => 'create-contract'])) ?>" title="قرارداد جدید">
                  <i data-feather="plus-circle"></i>
                  <span>قرارداد جدید</span>
                </a>
              </li>
            <?php endif; ?>
            <?php if (($user['role'] ?? '') === 'customer'): ?>
              <li class="onhover-dropdown proma-floating-cart" data-floating-cart>
                <a class="notification-box proma-header-link proma-cart-trigger" href="<?= e(url('ecommerce/cart')) ?>" aria-label="سبد خرید">
                  <svg><use href="<?= e($sprite) ?>#stroke-ecommerce"></use></svg>
                  <span class="badge rounded-pill badge-primary"<?= (int) ($headerCartSummary['quantity'] ?? 0) > 0 ? '' : ' hidden' ?>><?= to_persian_digits($headerCartSummary['quantity'] ?? 0) ?></span>
                </a>
                <div class="onhover-show-div proma-floating-cart-panel">
                  <h6>سبد خرید</h6>
                  <?php if (!empty($headerCartSummary['items'])): ?>
                    <div class="proma-floating-cart-list">
                      <?php foreach (array_slice($headerCartSummary['items'], 0, 4) as $cartItem): ?>
                        <div class="proma-floating-cart-item">
                          <form method="post" action="<?= e(url('ecommerce/removeFromCart/' . (int) $cartItem['id'])) ?>" class="proma-cart-remove-form">
                            <?= csrf_field() ?>
                            <input type="hidden" name="return_to" value="<?= e($route ?: 'dashboard') ?>">
                            <button type="submit" aria-label="حذف"><i data-feather="x"></i></button>
                          </form>
                          <div class="proma-floating-cart-info">
                            <strong><?= e($cartItem['title']) ?></strong>
                            <div class="proma-floating-cart-qty">
                              <form method="post" action="<?= e(url('ecommerce/adjustCart/' . (int) $cartItem['id'])) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="return_to" value="<?= e($route ?: 'dashboard') ?>">
                                <input type="hidden" name="delta" value="1">
                                <button type="submit" aria-label="افزایش">+</button>
                              </form>
                              <span><?= to_persian_digits($cartItem['quantity']) ?></span>
                              <form method="post" action="<?= e(url('ecommerce/adjustCart/' . (int) $cartItem['id'])) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="return_to" value="<?= e($route ?: 'dashboard') ?>">
                                <input type="hidden" name="delta" value="-1">
                                <button type="submit" aria-label="کاهش">-</button>
                              </form>
                            </div>
                            <em><?= money_toman($cartItem['line_total']) ?></em>
                          </div>
                          <a class="proma-floating-cart-thumb" href="<?= e(url('ecommerce/product/' . ($cartItem['slug'] ?? (int) $cartItem['id']))) ?>">
                            <?php if (!empty($cartItem['image_path'])): ?>
                              <img src="<?= e(asset_url($cartItem['image_path'])) ?>" alt="<?= e($cartItem['title']) ?>">
                            <?php else: ?>
                              <span><i data-feather="image"></i></span>
                            <?php endif; ?>
                          </a>
                        </div>
                      <?php endforeach; ?>
                    </div>
                    <div class="proma-floating-cart-total">
                      <span>مجموع سفارش:</span>
                      <strong><?= money_toman($headerCartSummary['total'] ?? 0) ?></strong>
                    </div>
                    <a class="proma-floating-cart-link" href="<?= e(url('ecommerce/cart')) ?>">به سبد خرید خود بروید</a>
                    <a class="btn btn-primary w-100" href="<?= e(url('ecommerce/checkout')) ?>">تسویه حساب</a>
                  <?php else: ?>
                    <div class="proma-floating-cart-empty">سبد خرید شما خالی است.</div>
                    <a class="btn btn-primary w-100" href="<?= e(url('ecommerce/shop')) ?>">مشاهده فروشگاه</a>
                  <?php endif; ?>
                </div>
              </li>
            <?php endif; ?>
            <li class="onhover-dropdown" data-notification-center data-feed-url="<?= e(url('notifications/feed')) ?>" data-read-url="<?= e(url('notifications/read')) ?>" data-latest-id="<?= (int) $latestNotificationId ?>">
              <div class="notification-box">
                <svg><use href="<?= e($sprite) ?>#notification"></use></svg>
                <span class="badge rounded-pill badge-secondary" data-notification-badge<?= $unreadNotifications ? '' : ' hidden' ?>><?= to_persian_digits($unreadNotifications) ?></span>
              </div>
              <div class="onhover-show-div notification-dropdown proma-notification-dropdown">
                <div class="proma-notification-head"><h6>اعلان‌ها</h6></div>
                <ul data-notification-list>
                  <?php if (!$notifications): ?>
                    <li class="proma-notification-empty" data-notification-empty><p>اعلان تازه‌ای ندارید.</p></li>
                  <?php else: foreach ($notifications as $item): ?>
                    <li class="proma-dropdown-notification-item proma-dropdown-notification-item--<?= e($notificationTone($item['type'] ?? '')) ?>" data-notification-item="<?= (int) $item['id'] ?>">
                      <a href="<?= e($item['url'] ?: url('dashboard')) ?>">
                        <span class="proma-notification-time"><?= e($item['relative_time'] ?? jdatetime($item['created_at'] ?? '')) ?></span>
                        <strong><?= e($item['title']) ?></strong>
                        <small><?= e($item['body']) ?></small>
                      </a>
                    </li>
                  <?php endforeach; endif; ?>
                  <li class="proma-notification-actions" data-notification-actions>
                    <a class="proma-notification-all" href="<?= e(url('notifications')) ?>">بررسی همه</a>
                    <form method="post" action="<?= e(url('notifications/read')) ?>">
                      <?= csrf_field() ?>
                      <button class="btn btn-light btn-sm w-100" type="submit">خواندن همه</button>
                    </form>
                  </li>
                </ul>
              </div>
            </li>
            <li>
              <a class="notification-box proma-header-link" href="<?= e(url('chat')) ?>">
                <svg><use href="<?= e($sprite) ?>#stroke-chat"></use></svg>
                <?php if ($unreadMessages): ?><span class="badge rounded-pill badge-success"><?= to_persian_digits($unreadMessages) ?></span><?php endif; ?>
              </a>
            </li>
            <li class="profile-nav onhover-dropdown pe-0 py-0">
              <div class="media profile-media">
                <span class="proma-avatar <?= e($userAvatarKey) ?>" aria-label="<?= e($user['full_name'] ?? $userInitial) ?>"><img data-avatar-image src="<?= e(avatar_asset_url($userAvatarKey)) ?>" alt="آواتار <?= e($user['full_name'] ?? $userInitial) ?>" loading="lazy"></span>
                <div class="media-body">
                  <span><?= e($user['full_name'] ?? '') ?></span>
                  <p class="mb-0"><?= e(role_label($user['role'] ?? '')) ?> <i class="middle fa fa-angle-down"></i></p>
                </div>
              </div>
              <ul class="profile-dropdown onhover-show-div">
                <li><a href="<?= e(url('profile')) ?>"><i data-feather="user"></i><span>پروفایل من</span></a></li>
                <li><a href="<?= e(url('chat')) ?>"><i data-feather="message-square"></i><span>پیام‌ها</span></a></li>
                <li>
                  <form method="post" action="<?= e(url('auth/logout')) ?>">
                    <?= csrf_field() ?>
                    <button class="dropdown-form-button" type="submit"><i data-feather="log-out"></i><span>خروج</span></button>
                  </form>
                </li>
              </ul>
            </li>
          </ul>
        </div>
      </div>
    </div>

    <div class="page-body-wrapper">
      <div class="sidebar-wrapper" sidebar-layout="stroke-svg">
        <div>
          <div class="logo-wrapper">
            <div class="toggle-sidebar"><i class="status_toggle middle sidebar-toggle" data-feather="grid"></i></div>
            <a class="proma-template-logo proma-sidebar-brand" href="<?= e(url('dashboard')) ?>">
              <span class="proma-logo-full"><?= $renderFullLogo() ?></span>
              <span class="proma-logo-compact"><?= $renderCompactLogo() ?></span>
            </a>
            <div class="back-btn"><i class="fa fa-angle-left"></i></div>
          </div>
          <div class="logo-icon-wrapper">
            <a href="<?= e(url('dashboard')) ?>"><?= $renderCompactLogo() ?></a>
          </div>
          <nav class="sidebar-main">
            <div class="left-arrow" id="left-arrow"><i data-feather="arrow-left"></i></div>
            <div id="sidebar-menu">
              <ul class="sidebar-links" id="simple-bar">
                <li class="back-btn">
                  <a href="<?= e(url('dashboard')) ?>"><?= $renderCompactLogo() ?></a>
                  <div class="mobile-back text-end"><span>برگشت</span><i class="fa fa-angle-right ps-2" aria-hidden="true"></i></div>
                </li>
                <li class="pin-title sidebar-main-title"><div><h6>پین شده</h6></div></li>
                <li class="sidebar-main-title"><div><h6>منوی سامانه</h6></div></li>
                <?php foreach ($nav as $item): ?>
                  <?php
                    $children = $item[4] ?? [];
                    $active = strpos($route, $item[0]) === 0 || ($route === 'dashboard' && $item[0] === 'dashboard');
                    if (!$active && $children) {
                        foreach ($children as $child) {
                            if (strpos($route, $child[0]) === 0) {
                                $active = true;
                                break;
                            }
                        }
                    }
                  ?>
                  <li class="sidebar-list">
                    <i class="fa fa-thumb-tack"></i>
                    <?php if ($item[0] === 'chat' && $unreadMessages): ?><label class="badge badge-light-primary"><?= to_persian_digits($unreadMessages) ?></label><?php endif; ?>
                    <?php if ($item[0] === 'notifications' && $unreadNotifications): ?><label class="badge badge-light-primary"><?= to_persian_digits($unreadNotifications) ?></label><?php endif; ?>
                    <?php if ($item[0] === 'review' && $pendingReviewCount): ?><label class="badge badge-light-danger"><?= to_persian_digits($pendingReviewCount) ?></label><?php endif; ?>
                    <?php if ($children): ?>
                      <a class="sidebar-link sidebar-title <?= $active ? 'active' : '' ?>" href="javascript:void(0)">
                        <?= $sidebarIcon($item, $sprite) ?>
                        <?= $sidebarIcon($item, $sprite, true) ?>
                        <span><?= e($item[1]) ?></span>
                      </a>
                      <ul class="sidebar-submenu" style="<?= $active ? 'display:block;' : '' ?>">
                        <?php foreach ($children as $child): ?>
                          <?php $childActive = strpos($route, $child[0]) === 0; ?>
                          <li><a class="<?= $childActive ? 'active' : '' ?>" href="<?= e(url($child[0])) ?>"><?= e($child[1]) ?></a></li>
                        <?php endforeach; ?>
                      </ul>
                    <?php else: ?>
                      <a class="sidebar-link sidebar-title link-nav <?= $active ? 'active' : '' ?>" href="<?= e(url($item[0])) ?>">
                        <?= $sidebarIcon($item, $sprite) ?>
                        <?= $sidebarIcon($item, $sprite, true) ?>
                        <span><?= e($item[1]) ?></span>
                      </a>
                    <?php endif; ?>
                  </li>
                <?php endforeach; ?>
              </ul>
            </div>
            <div class="right-arrow" id="right-arrow"><i data-feather="arrow-right"></i></div>
          </nav>
        </div>
      </div>

      <div class="page-body">
        <div class="container-fluid">
          <div class="page-title">
            <div class="row">
              <div class="col-6">
                <h4><?= e($title ?? '') ?></h4>
              </div>
              <div class="col-6">
                <ol class="breadcrumb">
                  <li class="breadcrumb-item"><a href="<?= e(url('dashboard')) ?>"><svg class="stroke-icon"><use href="<?= e($sprite) ?>#stroke-home"></use></svg></a></li>
                  <li class="breadcrumb-item"><?= e($systemName) ?></li>
                  <li class="breadcrumb-item active"><?= e(role_label(Auth::role())) ?></li>
                </ol>
              </div>
            </div>
          </div>
        </div>
        <div class="container-fluid">
          <?php if ($success = flash('success')): ?><div class="alert alert-light-success" role="alert"><?= e($success) ?></div><?php endif; ?>
          <?php if ($error = flash('error')): ?><div class="alert alert-light-danger" role="alert"><?= e($error) ?></div><?php endif; ?>
          <?= $content ?>
        </div>
      </div>

      <div class="proma-tour" data-tour hidden>
        <div class="proma-tour-backdrop"></div>
        <div class="proma-tour-card">
          <span class="badge badge-light-primary" data-tour-step>۱ از ۴</span>
          <h5 data-tour-title>خوش آمدید</h5>
          <p data-tour-body>در این تور کوتاه با بخش‌های اصلی پنل آشنا می‌شوید.</p>
          <div class="actions">
            <button class="btn secondary" type="button" data-tour-skip>رد کردن</button>
            <button class="btn" type="button" data-tour-next>بعدی</button>
          </div>
        </div>
      </div>

      <div class="modal proma-jalali-modal" id="proma-jalali-modal" data-jalali-modal>
        <div class="modal-content">
          <div class="modal-header">
            <h3 data-jalali-title>انتخاب تاریخ شمسی</h3>
            <button class="icon-btn" type="button" data-close-modal>×</button>
          </div>
          <div class="modal-body">
            <div class="proma-jalali-toolbar">
              <button class="icon-btn" type="button" data-jalali-prev title="ماه قبل">‹</button>
              <label>سال<input data-jalali-year inputmode="numeric"></label>
              <label>ماه<select data-jalali-month></select></label>
              <button class="icon-btn" type="button" data-jalali-next title="ماه بعد">›</button>
            </div>
            <div class="proma-jalali-grid" data-jalali-grid></div>
          </div>
          <div class="modal-footer">
            <button class="btn secondary" type="button" data-jalali-today>امروز</button>
            <button class="btn secondary" type="button" data-close-modal>بستن</button>
          </div>
        </div>
      </div>

      <footer class="footer">
        <div class="container-fluid">
          <div class="row">
            <div class="col-md-12 footer-copyright text-center">
              <p class="mb-0"><?= e($systemName) ?> - <?= e($footerText) ?></p>
            </div>
          </div>
        </div>
      </footer>
    </div>
  </div>

  <script src="<?= e(template_asset_url('js/jquery.min.js')) ?>"></script>
  <script src="<?= e(template_asset_url('js/bootstrap/bootstrap.bundle.min.js')) ?>"></script>
  <script src="<?= e(template_asset_url('js/icons/feather-icon/feather.min.js')) ?>"></script>
  <script src="<?= e(template_asset_url('js/icons/feather-icon/feather-icon.js')) ?>"></script>
  <script src="<?= e(template_asset_url('js/scrollbar/simplebar.js')) ?>"></script>
  <script src="<?= e(template_asset_url('js/scrollbar/custom.js')) ?>"></script>
  <script src="<?= e(template_asset_url('js/config.js')) ?>"></script>
  <script src="<?= e(template_asset_url('js/sidebar-menu.js')) ?>"></script>
  <script src="<?= e(template_asset_url('js/sidebar-pin.js')) ?>"></script>
  <script src="<?= e(template_asset_url('js/clock.js')) ?>"></script>
  <script src="<?= e(template_asset_url('js/slick/slick.min.js')) ?>"></script>
  <script src="<?= e(template_asset_url('js/slick/slick.js')) ?>"></script>
  <script src="<?= e(template_asset_url('js/header-slick.js')) ?>"></script>
  <script src="<?= e(template_asset_url('js/height-equal.js')) ?>"></script>
  <script src="<?= e(template_asset_url('js/script.js')) ?>"></script>
  <script src="<?= e(template_asset_url('js/editors/quill.js')) ?>"></script>
  <script src="<?= e(asset_url('assets/js/app.js')) ?>"></script>
</body>
</html>
