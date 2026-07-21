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
            $result = Event::processDueReminders();
        } finally {
            CronLock::release($lock);
        }
        $this->json([
            'ok' => true,
            'message' => 'بررسی اعلان‌های تقویم انجام شد.',
            'result' => $result,
        ]);
    }
}
