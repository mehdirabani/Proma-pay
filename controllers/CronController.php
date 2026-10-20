<?php

class CronController extends Controller
{
    public function outbox()
    {
        $expected = (string) Settings::get('calendar_cron_token', '');
        $provided = (string) ($_GET['token'] ?? '');
        if ($expected === '' || !hash_equals($expected, $provided)) {
            http_response_code(403);
            $this->json(['ok' => false, 'message' => 'توکن کران معتبر نیست.'], 403);
        }

        if (!defined('PROMA_OUTBOX_WORKER')) {
            define('PROMA_OUTBOX_WORKER', true);
        }
        $lock = CronLock::acquire('outbox');
        if ($lock === false) {
            header('Retry-After: 30');
            $this->json(['ok' => false, 'message' => 'پردازش قبلی صف هنوز در حال اجرا است.'], 409);
        }
        $limit = max(1, min(50, (int) ($_GET['limit'] ?? 25)));
        try {
            $result = SystemOutbox::processPending($limit);
        } finally {
            CronLock::release($lock);
        }
        $this->json(['ok' => true, 'result' => $result]);
    }

    public function calendarreminders()
    {
        $expected = (string) Settings::get('calendar_cron_token', '');
        $provided = (string) ($_GET['token'] ?? '');
        if ($expected === '' || !hash_equals($expected, $provided)) {
            http_response_code(403);
            $this->json(['ok' => false, 'message' => 'توکن کران معتبر نیست.'], 403);
        }

        $lock = CronLock::acquire('calendar-reminders');
        if ($lock === false) {
            header('Retry-After: 30');
            $this->json(['ok' => false, 'message' => 'پردازش قبلی اعلان‌های تقویم هنوز در حال اجرا است.'], 409);
        }
        try {
            $result = [
                'calendar' => Event::processDueReminders(),
                'customer_automation' => $this->runCustomerAutomationBatch(50),
            ];
        } finally {
            CronLock::release($lock);
        }
        $this->json([
            'ok' => true,
            'message' => 'بررسی اعلان‌های تقویم انجام شد.',
            'result' => $result,
        ]);
    }

    public function customerAutomations()
    {
        $expected = (string) Settings::get('calendar_cron_token', '');
        $provided = (string) ($_GET['token'] ?? '');
        if ($expected === '' || !hash_equals($expected, $provided)) {
            http_response_code(403);
            $this->json(['ok' => false, 'message' => 'توکن کران معتبر نیست.'], 403);
        }
        $lock = CronLock::acquire('customer-automations');
        if ($lock === false) {
            header('Retry-After: 30');
            $this->json(['ok' => false, 'message' => 'پردازش قبلی اتوماسیون مشتری هنوز در حال اجرا است.'], 409);
        }
        $error = null;
        $result = null;
        try {
            $limit = max(1, min(250, (int) ($_GET['limit'] ?? 100)));
            $result = $this->runCustomerAutomationBatch($limit);
        } catch (Throwable $e) {
            if (class_exists('PluginRegistry')) PluginRegistry::logRuntimeError('cron.customer_automations', $e);
            $error = $e;
        } finally {
            CronLock::release($lock);
        }
        if ($error) $this->json(['ok' => false, 'message' => 'اجرای اتوماسیون مشتری با خطا متوقف شد.'], 500);
        $this->json(['ok' => true, 'message' => 'بررسی اتوماسیون مشتری انجام شد.', 'result' => $result]);
    }

    public function collectionStages()
    {
        $expected = (string) Settings::get('calendar_cron_token', '');
        $provided = (string) ($_GET['token'] ?? '');
        if ($expected === '' || !hash_equals($expected, $provided)) {
            $this->json(['ok' => false, 'message' => 'توکن کران معتبر نیست.'], 403);
        }
        $lock = CronLock::acquire('collection-stages');
        if ($lock === false) {
            header('Retry-After: 30');
            $this->json(['ok' => false, 'message' => 'اجرای قبلی مرکز وصول هنوز در حال انجام است.'], 409);
        }
        try {
            $admin = User::all('admin', null, 'active')[0] ?? null;
            if (!$admin) $this->json(['ok' => false, 'message' => 'مدیر فعالی برای ثبت ممیزی مرحله وصول پیدا نشد.'], 409);
            $created = CollectionCommandCenter::runStageAutomation((int) $admin['id']);
        } finally {
            CronLock::release($lock);
        }
        $this->json(['ok' => true, 'message' => 'مراحل وصول بررسی شد.', 'result' => ['tasks_created' => $created]]);
    }

    private function runCustomerAutomationBatch(int $limit): array
    {
        $result = ['installments' => InstallmentMilestoneAutomation::run($limit), 'salary' => null];
        if (class_exists('PluginManager') && PluginManager::isActive('proma-accounting')
            && class_exists('Proma\\Plugins\\Accounting\\Services\\SalaryPayrollService')) {
            $result['salary'] = \Proma\Plugins\Accounting\Services\SalaryPayrollService::processDue($limit);
        }
        return $result;
    }
}
