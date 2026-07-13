<?php

if (!function_exists('pa_page_header')) {
    function pa_page_header($eyebrow, $title, $description, $icon = 'pie-chart', $actions = '')
    {
        ?>
        <header class="proma-accounting-page-header">
          <div class="proma-accounting-page-heading">
            <span class="proma-accounting-page-icon" aria-hidden="true"><i data-feather="<?= e($icon) ?>"></i></span>
            <div><span class="proma-accounting-eyebrow"><?= e($eyebrow) ?></span><h2><?= e($title) ?></h2><p><?= e($description) ?></p></div>
          </div>
          <?php if (trim((string) $actions) !== ''): ?><div class="proma-accounting-header-actions"><?= $actions ?></div><?php endif; ?>
        </header>
        <?php
    }

    function pa_initials($name)
    {
        $name = trim((string) $name);
        if ($name === '') {
            return 'ک';
        }
        $parts = preg_split('/\s+/u', $name) ?: [];
        $first = mb_substr((string) ($parts[0] ?? ''), 0, 1, 'UTF-8');
        $last = count($parts) > 1 ? mb_substr((string) end($parts), 0, 1, 'UTF-8') : '';
        return $first . $last;
    }

    function pa_user_cell(array $user, $secondary = '')
    {
        $name = trim((string) ($user['full_name'] ?? $user['seller_name'] ?? $user['user_name'] ?? 'کاربر'));
        $role = trim((string) ($user['role'] ?? ''));
        $secondary = trim((string) $secondary) ?: trim((string) ($user['mobile'] ?? $user['username'] ?? ''));
        ob_start();
        ?><div class="proma-accounting-user-cell"><span class="proma-accounting-avatar" aria-hidden="true"><?= e(pa_initials($name)) ?></span><span><strong><?= e($name) ?></strong><small><?= e($secondary ?: ($role !== '' ? role_label($role) : '')) ?></small></span></div><?php
        return trim(ob_get_clean());
    }

    function pa_status($status)
    {
        $status = trim((string) $status);
        $map = [
            'pending' => ['در انتظار تأیید', 'is-warning'],
            'approved' => ['تأییدشده', 'is-info'],
            'payable' => ['قابل پرداخت', 'is-purple'],
            'posted' => ['ثبت‌شده در حساب', 'is-success'],
            'paid' => ['پرداخت‌شده', 'is-success'],
            'reversed' => ['برگشت‌خورده', 'is-danger'],
            'cancelled' => ['لغوشده', 'is-neutral'],
            'deleted' => ['حذف‌شده', 'is-neutral'],
            'active' => ['فعال', 'is-success'],
            'inactive' => ['غیرفعال', 'is-neutral'],
            'open' => ['باز', 'is-success'],
        ];
        [$label, $class] = $map[$status] ?? [$status !== '' ? $status : 'نامشخص', 'is-neutral'];
        return '<span class="proma-accounting-status ' . e($class) . '"><span aria-hidden="true"></span>' . e($label) . '</span>';
    }

    function pa_entry_type_label($type)
    {
        $labels = [
            'commission' => 'کمیسیون فروش', 'bonus' => 'پاداش', 'expense' => 'هزینه قابل پرداخت',
            'deduction' => 'کسورات', 'payment' => 'پرداخت به کاربر', 'payment_to_user' => 'پرداخت به کاربر',
            'receipt' => 'دریافت از کاربر', 'receipt_from_user' => 'دریافت از کاربر',
            'manual_increase' => 'افزایش دستی', 'manual_decrease' => 'کاهش دستی', 'reversal' => 'سند معکوس',
        ];
        return $labels[(string) $type] ?? (string) $type;
    }

    function pa_money_effect($amount, $direction, $showDirection = true)
    {
        $increase = (string) $direction === 'increase';
        $class = $increase ? 'is-increase' : 'is-decrease';
        $label = $increase ? 'افزایش مانده' : 'کاهش مانده';
        $sign = $increase ? '+' : '-';
        ob_start();
        ?><span class="proma-accounting-money-effect <?= $class ?>"><strong><?= $sign ?> <?= money_toman(abs((int) $amount)) ?></strong><?php if ($showDirection): ?><small><?= $label ?></small><?php endif; ?></span><?php
        return trim(ob_get_clean());
    }

    function pa_balance($amount)
    {
        $amount = (int) $amount;
        $class = $amount > 0 ? 'is-positive' : ($amount < 0 ? 'is-negative' : 'is-zero');
        $label = $amount > 0 ? 'مانده مثبت' : ($amount < 0 ? 'مانده منفی' : 'تسویه');
        return '<span class="proma-accounting-balance ' . $class . '"><strong>' . money_toman($amount) . '</strong><small>' . e($label) . '</small></span>';
    }

    function pa_empty_state($title, $description, $icon = 'inbox', $actionLabel = '', $actionUrl = '')
    {
        ob_start();
        ?><div class="proma-accounting-empty"><span aria-hidden="true"><i data-feather="<?= e($icon) ?>"></i></span><strong><?= e($title) ?></strong><p><?= e($description) ?></p><?php if ($actionLabel !== '' && $actionUrl !== ''): ?><a class="btn btn-primary btn-sm" href="<?= e($actionUrl) ?>"><?= e($actionLabel) ?></a><?php endif; ?></div><?php
        return trim(ob_get_clean());
    }

    function pa_financial_dialog()
    {
        ?>
        <dialog class="proma-accounting-dialog proma-accounting-financial-dialog" data-accounting-confirm-dialog aria-labelledby="pa-confirm-title">
          <form method="dialog">
            <header><span class="proma-accounting-dialog-icon" aria-hidden="true"><i data-feather="alert-circle"></i></span><div><h3 id="pa-confirm-title" data-confirm-title>تأیید عملیات مالی</h3><p data-confirm-description>اثر این عملیات را پیش از ثبت بررسی کنید.</p></div><button class="proma-accounting-icon-button" type="submit" value="cancel" aria-label="بستن"><i data-feather="x"></i></button></header>
            <div class="proma-accounting-dialog-body"><dl class="proma-accounting-confirm-grid"><div><dt>شخص یا مرجع</dt><dd data-confirm-person>—</dd></div><div><dt>عملیات</dt><dd data-confirm-operation>—</dd></div><div><dt>مبلغ</dt><dd data-confirm-amount>—</dd></div><div><dt>اثر مالی</dt><dd data-confirm-effect>—</dd></div></dl><label class="proma-accounting-field"><span class="proma-accounting-label">علت یا توضیح تأیید</span><span class="proma-accounting-control"><input data-confirm-reason autocomplete="off" placeholder="علت انجام یا اصلاح عملیات"></span></label></div>
            <footer><button class="btn btn-light" type="submit" value="cancel">انصراف</button><button class="btn btn-primary" type="button" data-confirm-submit><i data-feather="check"></i><span>تأیید و ادامه</span></button></footer>
          </form>
        </dialog>
        <?php
    }
}
