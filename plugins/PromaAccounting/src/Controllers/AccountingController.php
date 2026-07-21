<?php

namespace Proma\Plugins\Accounting\Controllers;

use Proma\Plugins\Accounting\Services\AccountingRepository;
use Proma\Plugins\Accounting\Services\CommissionCalculationService;
use Proma\Plugins\Accounting\Services\CommissionService;
use Proma\Plugins\Accounting\Services\LedgerService;
use Proma\Plugins\Accounting\Services\Money;
use Proma\Plugins\Accounting\Services\SalesService;

class AccountingController extends \Controller
{
    public function dashboard()
    {
        $widgetErrors = [];
        $settings = $this->dashboardWidget('settings', [AccountingRepository::class, 'settings'], ['setup_status' => 'completed'], $widgetErrors);
        if (($settings['setup_status'] ?? 'pending') === 'pending') {
            \redirect('plugin/accounting/setup');
        }
        \Auth::releaseSessionLock();
        $this->render('plugin:proma-accounting/dashboard', [
            'title' => 'داشبورد حسابداری',
            'summary' => $this->dashboardWidget('summary', [AccountingRepository::class, 'dashboard'], $this->emptyDashboardSummary(), $widgetErrors),
            'series' => $this->dashboardWidget('series', [AccountingRepository::class, 'dashboardSeries'], ['labels' => [], 'receipts' => [], 'payments' => []], $widgetErrors),
            'recentEntries' => $this->dashboardWidget('recent_ledger', static function () {
                return AccountingRepository::recentLedger(8);
            }, [], $widgetErrors),
            'topSellers' => $this->dashboardWidget('top_sellers', static function () {
                return AccountingRepository::topSellers(5);
            }, [], $widgetErrors),
            'settings' => $settings,
            'widgetErrors' => $widgetErrors,
        ]);
    }

    private function dashboardWidget($name, callable $loader, $fallback, array &$errors)
    {
        $startedAt = microtime(true);
        try {
            return call_user_func($loader);
        } catch (\Throwable $e) {
            $errors[] = (string) $name;
            \ErrorHandler::log('accounting_dashboard_' . $name, $e, 503);
            return $fallback;
        } finally {
            if (class_exists('RequestTelemetry', false)) {
                \RequestTelemetry::recordSpan('accounting.widget', $startedAt, ['widget' => $name]);
            }
        }
    }

    private function emptyDashboardSummary()
    {
        return [
            'users' => 0,
            'sales' => 0,
            'commission' => '0',
            'pending_commissions' => '0',
            'payable_commissions' => '0',
            'positive_balances' => '0',
            'negative_balances' => '0',
            'ledger' => '0',
            'payments_this_month' => '0',
            'receipts_this_month' => '0',
        ];
    }

    public function accounts()
    {
        $filters = [
            'balance' => $_GET['balance'] ?? '',
            'role' => $_GET['role'] ?? '',
            'status' => $_GET['status'] ?? '',
            'sort' => $_GET['sort'] ?? 'name',
        ];
        $result = AccountingRepository::accounts($_GET['q'] ?? '', $_GET['page'] ?? 1, 25, $filters);
        $this->render('plugin:proma-accounting/accounts', ['title' => 'حساب کاربران', 'accounts' => $result['items'], 'pagination' => $result]);
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
            'accountSummary' => AccountingRepository::accountSummary((int) $userId),
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
        $idempotency = trim((string) ($_POST['accounting_request_uuid'] ?? ''));
        if (!preg_match('/^[a-f0-9]{32}$/i', $idempotency)) {
            throw new \InvalidArgumentException('کد پیگیری عملیات مالی معتبر نیست. صفحه را تازه‌سازی کرده و دوباره تلاش کنید.');
        }
        $idempotency = 'manual:' . strtolower($idempotency);
        $category = \Model::fetch('SELECT id FROM accounting_categories WHERE category_type = ? AND is_active = 1 ORDER BY is_system DESC, id LIMIT 1', [$map[$entryType][2]]);
        LedgerService::post($userId, $entryType, $map[$entryType][0], $_POST['amount'] ?? 0, $description, \Auth::id(), 'manual', null, ['label' => $map[$entryType][1], 'method' => trim((string) ($_POST['method'] ?? '')), 'tracking' => trim((string) ($_POST['tracking'] ?? ''))], $idempotency, $category['id'] ?? null, $_POST['contract_id'] ?? null, $_POST['commission_id'] ?? null);
        \set_flash('success', 'سند دفترکل با موفقیت ثبت شد.');
        \redirect('plugin/accounting/ledger/' . $userId);
    }

    public function ledgerPostFallback()
    {
        \set_flash('error', 'ثبت سند حسابداری باید از فرم دفترکل و با ارسال امن انجام شود. لطفاً حساب کاربر را باز کرده و دوباره ثبت کنید.');
        \redirect('plugin/accounting/accounts');
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
        $filters = ['q' => $_GET['q'] ?? '', 'status' => $_GET['status'] ?? ''];
        $result = AccountingRepository::sales($_GET['page'] ?? 1, 25, $filters);
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
        $filters = ['q' => $_GET['q'] ?? '', 'status' => $_GET['status'] ?? '', 'seller_user_id' => $_GET['seller_user_id'] ?? 0];
        $result = AccountingRepository::commissions($_GET['page'] ?? 1, 25, $filters);
        $this->render('plugin:proma-accounting/commissions', ['title' => 'کمیسیون فروش', 'commissions' => $result['items'], 'pagination' => $result, 'staff' => AccountingRepository::staff('')]);
    }

    public function approveCommission($commissionId)
    {
        $this->onlyPost();
        CommissionService::approve((int) $commissionId, \Auth::id());
        \set_flash('success', 'کمیسیون تأیید شد.');
        \redirect('plugin/accounting/commissions');
    }

    public function postCommission($commissionId)
    {
        $this->onlyPost();
        CommissionService::post((int) $commissionId, \Auth::id());
        \set_flash('success', 'کمیسیون با ثبت یکتا وارد حساب کاربر شد.');
        \redirect('plugin/accounting/commissions');
    }

    public function reverseCommission($commissionId)
    {
        $this->onlyPost();
        CommissionService::reverse((int) $commissionId, \Auth::id(), $_POST['reason'] ?? 'اصلاح کمیسیون');
        \set_flash('success', 'کمیسیون با حفظ تاریخچه معکوس شد.');
        \redirect('plugin/accounting/commissions');
    }

    public function rules()
    {
        $this->render('plugin:proma-accounting/rules', ['title' => 'قوانین کمیسیون', 'rules' => AccountingRepository::rules(), 'staff' => AccountingRepository::staff('')]);
    }

    public function saveRule()
    {
        $this->onlyPost();
        $ruleId = (int) ($_POST['rule_id'] ?? 0);
        $payload = $this->rulePayload($_POST);
        if ($ruleId > 0) {
            $existing = \Model::fetch('SELECT * FROM plugin_accounting_commission_rules WHERE id = ? LIMIT 1', [$ruleId]);
            if (!$existing) {
                throw new \InvalidArgumentException('قانون کمیسیون پیدا نشد.');
            }
            \Model::execute(
                'UPDATE plugin_accounting_commission_rules
                 SET user_id = ?, name = ?, commission_type = ?, commission_value = ?, calculation_basis = ?, minimum_amount = ?, maximum_amount = ?,
                     calculation_timing = ?, requires_approval = ?, is_active = ?, priority = ?, updated_at = NOW()
                 WHERE id = ?',
                [
                    $payload['user_id'],
                    $payload['name'],
                    $payload['commission_type'],
                    $payload['commission_value'],
                    $payload['calculation_basis'],
                    $payload['minimum_amount'],
                    $payload['maximum_amount'],
                    $payload['calculation_timing'],
                    $payload['requires_approval'],
                    $payload['is_active'],
                    $payload['priority'],
                    $ruleId,
                ]
            );
            \AuditLog::record('accounting', 'commission_rule_updated', 'plugin_accounting_commission_rule', $ruleId, ['actor_user_id' => \Auth::id(), 'old_values' => $existing, 'new_values' => $payload]);
            \set_flash('success', 'قانون کمیسیون ویرایش شد. تغییرات فقط روی محاسبات آینده اثر دارد.');
            \redirect('plugin/accounting/rules');
        }
        \Model::execute(
            'INSERT INTO plugin_accounting_commission_rules (user_id, name, commission_type, commission_value, calculation_basis, minimum_amount, maximum_amount, calculation_timing, requires_approval, is_active, priority, created_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())',
            [$payload['user_id'], $payload['name'], $payload['commission_type'], $payload['commission_value'], $payload['calculation_basis'], $payload['minimum_amount'], $payload['maximum_amount'], $payload['calculation_timing'], $payload['requires_approval'], $payload['is_active'], $payload['priority'], \Auth::id()]
        );
        \AuditLog::record('accounting', 'commission_rule_saved', 'plugin_accounting_commission_rule', (int) \Model::lastInsertId(), ['actor_user_id' => \Auth::id(), 'new_values' => $payload]);
        \set_flash('success', 'قانون کمیسیون ذخیره شد و فقط روی محاسبات آینده اثر دارد.');
        \redirect('plugin/accounting/rules');
    }

    public function deleteRule($ruleId)
    {
        $this->onlyPost();
        $ruleId = (int) $ruleId;
        $rule = \Model::fetch('SELECT * FROM plugin_accounting_commission_rules WHERE id = ? LIMIT 1', [$ruleId]);
        if (!$rule) {
            throw new \InvalidArgumentException('قانون کمیسیون پیدا نشد.');
        }
        $used = (int) ((\Model::fetch('SELECT COUNT(*) AS total FROM plugin_accounting_commissions WHERE commission_rule_id = ?', [$ruleId])['total'] ?? 0));
        if ($used > 0) {
            \Model::execute('UPDATE plugin_accounting_commission_rules SET is_active = 0, updated_at = NOW() WHERE id = ?', [$ruleId]);
            \AuditLog::record('accounting', 'commission_rule_deactivated', 'plugin_accounting_commission_rule', $ruleId, ['actor_user_id' => \Auth::id(), 'reason' => 'rule_used_by_commissions', 'used_count' => $used]);
            \set_flash('success', 'این قانون در سوابق کمیسیون استفاده شده بود؛ برای حفظ تاریخچه مالی حذف فیزیکی نشد و غیرفعال شد.');
            \redirect('plugin/accounting/rules');
        }
        \Model::execute('DELETE FROM plugin_accounting_commission_rules WHERE id = ?', [$ruleId]);
        \AuditLog::record('accounting', 'commission_rule_deleted', 'plugin_accounting_commission_rule', $ruleId, ['actor_user_id' => \Auth::id(), 'old_values' => $rule]);
        \set_flash('success', 'قانون کمیسیون حذف شد.');
        \redirect('plugin/accounting/rules');
    }

    private function rulePayload(array $input)
    {
        $type = in_array($input['commission_type'] ?? '', ['fixed', 'percentage'], true) ? $input['commission_type'] : 'percentage';
        $basis = CommissionCalculationService::basisType($input['calculation_basis'] ?? 'financed_amount');
        $timing = $this->timing($input['calculation_timing'] ?? 'at_contract_creation');
        $value = $this->commissionValue($type, $input['commission_value'] ?? '');
        $minimum = trim((string) ($input['minimum_amount'] ?? '')) !== '' ? $this->money($input['minimum_amount'], 'حداقل کمیسیون') : null;
        $maximum = trim((string) ($input['maximum_amount'] ?? '')) !== '' ? $this->money($input['maximum_amount'], 'حداکثر کمیسیون') : null;
        if ($minimum !== null && $maximum !== null && $minimum > $maximum) {
            throw new \InvalidArgumentException('حداقل کمیسیون نمی‌تواند از حداکثر کمیسیون بیشتر باشد.');
        }
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('نام قانون الزامی است.');
        }
        $userId = !empty($input['user_id']) ? (int) $input['user_id'] : null;
        if ($userId && !\Model::fetch("SELECT id FROM users WHERE id = ? AND role IN ('admin','operator','lawyer') AND status = 'active' LIMIT 1", [$userId])) {
            throw new \InvalidArgumentException('فروشنده انتخاب‌شده معتبر یا فعال نیست.');
        }
        return [
            'user_id' => $userId,
            'name' => $name,
            'commission_type' => $type,
            'commission_value' => $value,
            'calculation_basis' => $basis,
            'minimum_amount' => $minimum,
            'maximum_amount' => $maximum,
            'calculation_timing' => $timing,
            'requires_approval' => isset($input['requires_approval']) ? 1 : 0,
            'is_active' => isset($input['is_active']) ? 1 : 0,
            'priority' => (int) ($input['priority'] ?? 0),
        ];
    }

    public function settings()
    {
        $this->renderSettings();
    }

    public function saveSettings()
    {
        $this->onlyPost();
        $values = $this->settingsPayload($_POST);
        $old = AccountingRepository::settings();
        \Model::begin();
        try {
            $this->upsertSettings($values);
            \AuditLog::record('accounting', 'settings_updated', 'plugin_accounting_settings', 0, ['actor_user_id' => \Auth::id(), 'old_values' => $old, 'new_values' => $values]);
            \Model::commit();
        } catch (\Throwable $e) {
            \Model::rollBack();
            throw $e;
        }
        \set_flash('success', 'تنظیمات ذخیره شد. کمیسیون‌ها و اسناد قبلی بدون تغییر باقی ماندند.');
        \redirect('plugin/accounting/settings');
    }

    public function previewCommission()
    {
        $this->onlyPost();
        $input = $this->previewPayload($_POST);
        $preview = CommissionCalculationService::preview((int) $input['seller_user_id'], $input);
        $this->renderSettings($preview, $input);
    }

    public function setup()
    {
        $settings = AccountingRepository::settings();
        $step = max(1, min(10, (int) ($_GET['step'] ?? ($settings['setup_step'] ?? 1))));
        $preview = null;
        if ($step === 9) {
            $preview = CommissionCalculationService::preview(0, [
                'principal_amount' => 20000000,
                'down_payment_amount' => 5000000,
                'financed_amount' => 15000000,
                'profit_amount' => 3000000,
                'collected_amount' => 5000000,
            ]);
        }
        $this->render('plugin:proma-accounting/setup', ['title' => 'راه‌اندازی حسابداری', 'settings' => $settings, 'step' => $step, 'preview' => $preview]);
    }

    public function saveSetup()
    {
        $this->onlyPost();
        $step = max(1, min(10, (int) ($_POST['step'] ?? 1)));
        $settings = AccountingRepository::settings();
        $values = [];
        if ($step === 1) {
            $values['default_commission_type'] = in_array($_POST['default_commission_type'] ?? '', ['percentage', 'fixed'], true) ? $_POST['default_commission_type'] : 'percentage';
        } elseif ($step === 2) {
            $values['default_commission_value'] = $this->commissionValue($settings['default_commission_type'] ?? 'percentage', $_POST['default_commission_value'] ?? '');
        } elseif ($step === 3) {
            $values['default_calculation_basis'] = CommissionCalculationService::basisType($_POST['default_calculation_basis'] ?? 'financed_amount');
        } elseif ($step === 4) {
            $values['minimum_commission_enabled'] = isset($_POST['minimum_commission_enabled']) ? '1' : '0';
            $values['minimum_commission'] = (string) $this->money($_POST['minimum_commission'] ?? 0, 'حداقل کمیسیون');
            $values['maximum_commission_enabled'] = isset($_POST['maximum_commission_enabled']) ? '1' : '0';
            $values['maximum_commission'] = (string) $this->money($_POST['maximum_commission'] ?? 0, 'حداکثر کمیسیون');
            if ($values['minimum_commission_enabled'] === '1' && $values['maximum_commission_enabled'] === '1' && (int) $values['minimum_commission'] > (int) $values['maximum_commission']) {
                throw new \InvalidArgumentException('حداقل کمیسیون نمی‌تواند از حداکثر کمیسیون بیشتر باشد.');
            }
        } elseif ($step === 5) {
            $values['rounding_method'] = CommissionCalculationService::roundingMethod($_POST['rounding_method'] ?? 'none');
            $values['rounding_unit'] = (string) $this->roundingUnit($_POST['rounding_unit'] ?? 1000);
        } elseif ($step === 6) {
            $values['default_calculation_timing'] = $this->timing($_POST['default_calculation_timing'] ?? 'at_contract_creation');
        } elseif ($step === 7) {
            $values['require_commission_approval'] = isset($_POST['require_commission_approval']) ? '1' : '0';
        } elseif ($step === 8) {
            $values['automatic_ledger_posting'] = isset($_POST['automatic_ledger_posting']) ? '1' : '0';
        } elseif ($step === 10) {
            $values['setup_status'] = 'completed';
        }
        $next = min(10, $step + 1);
        $values['setup_step'] = (string) $next;
        $this->upsertSettings($values);
        \AuditLog::record('accounting', 'setup_progress_saved', 'plugin_accounting_settings', 0, ['actor_user_id' => \Auth::id(), 'new_values' => ['step' => $step, 'completed' => $step === 10]]);
        if ($step === 10) {
            \set_flash('success', 'راه‌اندازی حسابداری کامل شد.');
            \redirect('plugin/accounting/dashboard');
        }
        \redirect('plugin/accounting/setup', ['step' => $next]);
    }

    public function skipSetup()
    {
        $this->onlyPost();
        $this->upsertSettings(['setup_status' => 'skipped']);
        \AuditLog::record('accounting', 'setup_skipped', 'plugin_accounting_settings', 0, ['actor_user_id' => \Auth::id()]);
        \set_flash('success', 'راهنمای راه‌اندازی فعلاً رد شد؛ هر زمان از تنظیمات قابل اجرا است.');
        \redirect('plugin/accounting/dashboard');
    }

    public function help()
    {
        $this->render('plugin:proma-accounting/help', ['title' => 'راهنمای حسابداری']);
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

    private function renderSettings($preview = null, array $previewInput = [])
    {
        $this->render('plugin:proma-accounting/settings', [
            'title' => 'تنظیمات حسابداری',
            'settings' => AccountingRepository::settings(),
            'staff' => AccountingRepository::staff(''),
            'preview' => $preview,
            'previewInput' => $previewInput,
        ]);
    }

    private function settingsPayload(array $input)
    {
        $type = in_array($input['default_commission_type'] ?? '', ['percentage', 'fixed'], true) ? $input['default_commission_type'] : 'percentage';
        $current = AccountingRepository::settings();
        $minimumEnabled = isset($input['minimum_commission_enabled']);
        $maximumEnabled = isset($input['maximum_commission_enabled']);
        $minimum = $minimumEnabled ? $this->money($input['minimum_commission'] ?? 0, 'حداقل کمیسیون') : Money::integer($current['minimum_commission'] ?? 0);
        $maximum = $maximumEnabled ? $this->money($input['maximum_commission'] ?? 0, 'حداکثر کمیسیون') : Money::integer($current['maximum_commission'] ?? 0);
        if ($minimumEnabled && $maximumEnabled && $minimum > $maximum) {
            throw new \InvalidArgumentException('حداقل کمیسیون نمی‌تواند از حداکثر کمیسیون بیشتر باشد.');
        }
        return [
            'default_commission_type' => $type,
            'default_commission_value' => $this->commissionValue($type, $input['default_commission_value'] ?? ''),
            'default_calculation_basis' => CommissionCalculationService::basisType($input['default_calculation_basis'] ?? 'financed_amount'),
            'minimum_commission_enabled' => $minimumEnabled ? '1' : '0',
            'minimum_commission' => (string) $minimum,
            'maximum_commission_enabled' => $maximumEnabled ? '1' : '0',
            'maximum_commission' => (string) $maximum,
            'rounding_method' => CommissionCalculationService::roundingMethod($input['rounding_method'] ?? 'none'),
            'rounding_unit' => (string) $this->roundingUnit($input['rounding_unit'] ?? 1000),
            'default_calculation_timing' => $this->timing($input['default_calculation_timing'] ?? 'at_contract_creation'),
            'require_commission_approval' => isset($input['require_commission_approval']) ? '1' : '0',
            'automatic_ledger_posting' => isset($input['automatic_ledger_posting']) ? '1' : '0',
            'setup_status' => 'completed',
            'setup_step' => '10',
        ];
    }

    private function previewPayload(array $input)
    {
        $values = ['seller_user_id' => (int) ($input['seller_user_id'] ?? 0)];
        foreach (['principal_amount', 'down_payment_amount', 'financed_amount', 'profit_amount', 'collected_amount'] as $key) {
            $values[$key] = $this->money($input[$key] ?? 0, 'مبلغ پیش‌نمایش');
        }
        return $values;
    }

    private function commissionValue($type, $value)
    {
        if ($type === 'fixed') {
            return (string) $this->money($value, 'مقدار پیش‌فرض کمیسیون');
        }
        $normalized = str_replace([',', '،', '٪', '%', ' '], '', \to_english_digits((string) $value));
        if ($normalized === '' || !preg_match('/^\d+(?:\.\d{1,2})?$/', $normalized) || Money::rateBasisPoints($normalized) > 10000) {
            throw new \InvalidArgumentException('مقدار درصد باید بین صفر تا صد و حداکثر دارای دو رقم اعشار باشد.');
        }
        return $normalized;
    }

    private function money($value, $label)
    {
        $normalized = str_replace(['٬', ',', '،', 'تومان', 'ریال', ' '], '', \to_english_digits((string) $value));
        if ($normalized === '' || !preg_match('/^\d+$/', $normalized)) {
            throw new \InvalidArgumentException($label . ' باید یک مبلغ صحیح و نامنفی باشد.');
        }
        return Money::integer($normalized);
    }

    private function roundingUnit($value)
    {
        $unit = Money::integer($value);
        if (!in_array($unit, [100, 500, 1000, 5000, 10000], true)) {
            throw new \InvalidArgumentException('برای روش انتخاب‌شده، واحد گرد کردن را مشخص کنید.');
        }
        return $unit;
    }

    private function timing($value)
    {
        return in_array($value, ['at_contract_creation', 'after_down_payment', 'after_first_installment', 'after_full_settlement', 'manual_approval'], true)
            ? $value
            : 'at_contract_creation';
    }

    private function upsertSettings(array $values)
    {
        foreach ($values as $key => $value) {
            \Model::execute(
                'INSERT INTO plugin_accounting_settings (setting_key, setting_value, updated_by, updated_at) VALUES (?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by), updated_at = NOW()',
                [(string) $key, (string) $value, \Auth::id()]
            );
        }
    }
}
