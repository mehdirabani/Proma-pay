<?php

namespace Proma\Plugins\Accounting\Controllers;

use Proma\Plugins\Accounting\Services\AccountingRepository;
use Proma\Plugins\Accounting\Services\CommissionService;
use Proma\Plugins\Accounting\Services\LedgerService;
use Proma\Plugins\Accounting\Services\SalesService;

class AccountingController extends \Controller
{
    public function dashboard()
    {
        $this->render('plugin:proma-accounting/dashboard', [
            'title' => 'داشبورد حسابداری',
            'summary' => AccountingRepository::dashboard(),
            'accounts' => AccountingRepository::accounts('', 1, 8)['items'],
            'settings' => AccountingRepository::settings(),
        ]);
    }

    public function accounts()
    {
        $result = AccountingRepository::accounts($_GET['q'] ?? '', $_GET['page'] ?? 1, 25, ['balance' => $_GET['balance'] ?? '']);
        $this->render('plugin:proma-accounting/accounts', [
            'title' => 'حساب کاربران',
            'accounts' => $result['items'],
            'pagination' => $result,
        ]);
    }

    public function ledger($userId)
    {
        $user = \User::find((int) $userId);
        if (!$user || !in_array($user['role'] ?? '', ['admin', 'operator', 'lawyer'], true)) {
            throw new \InvalidArgumentException('کاربر حسابداری پیدا نشد.');
        }
        $result = AccountingRepository::ledger((int) $userId, $_GET['page'] ?? 1, 50);
        $this->render('plugin:proma-accounting/ledger', [
            'title' => 'دفترکل ' . ($user['full_name'] ?? ''),
            'accountUser' => $user,
            'entries' => $result['items'],
            'pagination' => $result,
            'balance' => LedgerService::balance((int) $userId),
            'categories' => AccountingRepository::categories(),
        ]);
    }

    public function postLedger()
    {
        $this->onlyPost();
        $entryType = trim((string) ($_POST['entry_type'] ?? ''));
        $map = [
            'commission' => ['increase', 'کمیسیون فروش', 'commission'],
            'bonus' => ['increase', 'پاداش', 'bonus'],
            'expense' => ['increase', 'هزینه قابل پرداخت', 'expense'],
            'deduction' => ['decrease', 'کسر از حساب', 'deduction'],
            'payment' => ['decrease', 'پرداخت وجه به کاربر', 'payment_to_user'],
            'receipt' => ['increase', 'دریافت وجه از کاربر', 'receipt_from_user'],
            'manual_increase' => ['increase', 'افزایش دستی', 'manual_adjustment'],
            'manual_decrease' => ['decrease', 'کاهش دستی', 'manual_adjustment'],
        ];
        if (!isset($map[$entryType])) {
            throw new \InvalidArgumentException('نوع سند حسابداری معتبر نیست.');
        }
        $userId = (int) ($_POST['user_id'] ?? 0);
        $description = trim((string) ($_POST['description'] ?? ''));
        if ($description === '') {
            throw new \InvalidArgumentException('شرح عملیات الزامی است.');
        }
        $idempotency = trim((string) ($_POST['operation_key'] ?? ''));
        if ($idempotency === '') {
            $idempotency = 'manual:' . hash('sha256', $userId . '|' . $entryType . '|' . ($_POST['amount'] ?? '') . '|' . $description . '|' . \Csrf::token());
        }
        $category = \Model::fetch('SELECT id FROM accounting_categories WHERE category_type = ? AND is_active = 1 ORDER BY is_system DESC, id LIMIT 1', [$map[$entryType][2]]);
        LedgerService::post($userId, $entryType, $map[$entryType][0], $_POST['amount'] ?? 0, $description, \Auth::id(), 'manual', null, ['label' => $map[$entryType][1], 'method' => trim((string) ($_POST['method'] ?? '')), 'tracking' => trim((string) ($_POST['tracking'] ?? ''))], $idempotency, $category['id'] ?? null, $_POST['contract_id'] ?? null, $_POST['commission_id'] ?? null);
        \set_flash('success', 'سند دفترکل با موفقیت ثبت شد.');
        \redirect('plugin/accounting/ledger/' . $userId);
    }

    public function reverseLedger($entryId)
    {
        $this->onlyPost();
        LedgerService::reverse((int) $entryId, \Auth::id(), $_POST['reason'] ?? 'اصلاح سند حسابداری');
        \set_flash('success', 'سند معکوس ثبت شد.');
        \redirect('plugin/accounting/dashboard');
    }

    public function sales()
    {
        $result = AccountingRepository::sales($_GET['page'] ?? 1, 25);
        $this->render('plugin:proma-accounting/sales', ['title' => 'فروش‌ها', 'sales' => $result['items'], 'pagination' => $result, 'staff' => AccountingRepository::staff('')]);
    }

    public function assignSeller()
    {
        $this->onlyPost();
        SalesService::assignSeller((int) ($_POST['sale_id'] ?? 0), (int) ($_POST['seller_user_id'] ?? 0), \Auth::id());
        \set_flash('success', 'فروشنده با ثبت تاریخچه تغییر کرد و کمیسیون مجدداً محاسبه شد.');
        \redirect('plugin/accounting/sales');
    }

    public function commissions()
    {
        $result = AccountingRepository::commissions($_GET['page'] ?? 1, 25);
        $this->render('plugin:proma-accounting/commissions', ['title' => 'کمیسیون فروش', 'commissions' => $result['items'], 'pagination' => $result]);
    }

    public function rules()
    {
        $this->render('plugin:proma-accounting/rules', ['title' => 'قوانین کمیسیون', 'rules' => AccountingRepository::rules(), 'staff' => AccountingRepository::staff('')]);
    }

    public function saveRule()
    {
        $this->onlyPost();
        $type = in_array($_POST['commission_type'] ?? '', ['fixed', 'percentage'], true) ? $_POST['commission_type'] : 'percentage';
        $basis = in_array($_POST['calculation_basis'] ?? '', ['principal_amount', 'financed_amount', 'collected_amount'], true) ? $_POST['calculation_basis'] : 'financed_amount';
        $timing = in_array($_POST['calculation_timing'] ?? '', ['at_contract_creation', 'after_down_payment', 'after_first_installment', 'after_full_settlement', 'manual_approval'], true) ? $_POST['calculation_timing'] : 'at_contract_creation';
        $userId = !empty($_POST['user_id']) ? (int) $_POST['user_id'] : null;
        \Model::execute(
            'INSERT INTO plugin_accounting_commission_rules (user_id, name, commission_type, commission_value, calculation_basis, minimum_amount, maximum_amount, calculation_timing, requires_approval, is_active, priority, created_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, NOW())',
            [$userId, trim((string) ($_POST['name'] ?? 'قانون کمیسیون')), $type, trim((string) ($_POST['commission_value'] ?? '0')), $basis, trim((string) ($_POST['minimum_amount'] ?? '')) !== '' ? (int) to_english_digits($_POST['minimum_amount']) : null, trim((string) ($_POST['maximum_amount'] ?? '')) !== '' ? (int) to_english_digits($_POST['maximum_amount']) : null, $timing, isset($_POST['requires_approval']) ? 1 : 0, (int) ($_POST['priority'] ?? 0), \Auth::id()]
        );
        \AuditLog::record('accounting', 'commission_rule_saved', 'plugin_accounting_commission_rule', (int) \Model::lastInsertId(), ['actor_user_id' => \Auth::id()]);
        \set_flash('success', 'قانون کمیسیون ذخیره شد.');
        \redirect('plugin/accounting/rules');
    }

    public function settings()
    {
        $this->render('plugin:proma-accounting/settings', ['title' => 'تنظیمات حسابداری', 'settings' => AccountingRepository::settings()]);
    }

    public function saveSettings()
    {
        $this->onlyPost();
        $allowed = ['default_commission_type', 'default_commission_value', 'default_calculation_basis', 'default_calculation_timing', 'minimum_commission', 'maximum_commission', 'rounding_rule'];
        foreach ($allowed as $key) {
            $value = trim((string) ($_POST[$key] ?? ''));
            \Model::execute('INSERT INTO plugin_accounting_settings (setting_key, setting_value, updated_by, updated_at) VALUES (?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by), updated_at = NOW()', [$key, $value, \Auth::id()]);
        }
        foreach (['require_commission_approval', 'automatic_ledger_posting'] as $key) {
            $value = isset($_POST[$key]) ? '1' : '0';
            \Model::execute('INSERT INTO plugin_accounting_settings (setting_key, setting_value, updated_by, updated_at) VALUES (?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by), updated_at = NOW()', [$key, $value, \Auth::id()]);
        }
        \AuditLog::record('accounting', 'settings_updated', 'plugin_accounting_settings', 0, ['actor_user_id' => \Auth::id(), 'new_values' => ['keys' => $allowed]]);
        \set_flash('success', 'تنظیمات حسابداری ذخیره شد.');
        \redirect('plugin/accounting/settings');
    }

    public function backfill()
    {
        $this->render('plugin:proma-accounting/backfill', ['title' => 'بازسازی فروش‌های قبلی', 'preview' => SalesService::backfillPreview()]);
    }

    public function runBackfill()
    {
        $this->onlyPost();
        $result = SalesService::backfill((int) ($_POST['batch_size'] ?? 25), \Auth::id());
        \set_flash('success', 'تعداد ' . (int) $result['processed'] . ' فروش قبلی بررسی شد. موارد بدون فروشنده نیازمند تخصیص هستند.');
        \redirect('plugin/accounting/backfill');
    }
}
