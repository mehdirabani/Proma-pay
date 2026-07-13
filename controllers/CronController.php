<?php

class CronController extends Controller
{
    public function calendarreminders()
    {
        $expected = (string) Settings::get('calendar_cron_token', '');
        $provided = (string) ($_GET['token'] ?? '');
        if ($expected === '' || !hash_equals($expected, $provided)) {
            http_response_code(403);
            $this->json(['ok' => false, 'message' => 'توکن کران معتبر نیست.'], 403);
        }

        $result = Event::processDueReminders();
        $this->json([
            'ok' => true,
            'message' => 'بررسی اعلان‌های تقویم انجام شد.',
            'result' => $result,
        ]);
    }
}
