<?php

class CalendarController extends Controller
{
    public function index()
    {
        Auth::requireLogin();
        $viewer = User::find(Auth::id());
        [$jy, $jm] = $this->requestedJalaliMonth();
        [$gy, $gm, $gd] = jalali_to_gregorian($jy, $jm, 1);
        $startDate = sprintf('%04d-%02d-%02d', $gy, $gm, $gd);
        [$nextJy, $nextJm] = $jm === 12 ? [$jy + 1, 1] : [$jy, $jm + 1];
        [$egy, $egm, $egd] = jalali_to_gregorian($nextJy, $nextJm, 1);
        $endDate = sprintf('%04d-%02d-%02d', $egy, $egm, $egd);
        $weekday = (int) date('w', strtotime($startDate));
        $monthTitle = $this->monthName($jm) . ' ' . to_persian_digits($jy);
        $events = Event::allVisibleBetween($startDate, $endDate, $viewer ?: ['id' => Auth::id(), 'role' => Auth::role()]);
        $installmentEvents = Event::installmentCalendarEvents($startDate, $endDate, Auth::role() === 'customer' ? Auth::id() : null);
        $events = array_merge($events, $installmentEvents);
        usort($events, function ($a, $b) {
            return strcmp(($a['event_date'] ?? '') . ' ' . ($a['event_time'] ?? '23:59:59'), ($b['event_date'] ?? '') . ' ' . ($b['event_time'] ?? '23:59:59'));
        });

        $this->render('calendar/index', [
            'title' => 'تقویم رویدادها',
            'jYear' => $jy,
            'jMonth' => $jm,
            'jMonthValue' => to_persian_digits(sprintf('%04d/%02d', $jy, $jm)),
            'monthTitle' => $monthTitle,
            'daysInMonth' => $this->jalaliMonthDays($jy, $jm),
            'startOffset' => ($weekday + 1) % 7,
            'prevMonth' => $jm === 1 ? sprintf('%04d/%02d', $jy - 1, 12) : sprintf('%04d/%02d', $jy, $jm - 1),
            'nextMonth' => $jm === 12 ? sprintf('%04d/%02d', $jy + 1, 1) : sprintf('%04d/%02d', $jy, $jm + 1),
            'events' => $events,
            'users' => in_array(Auth::role(), ['admin', 'operator', 'lawyer'], true) ? User::all(null) : [],
            'reminderOptions' => Event::reminderOptions(),
            'eventTypeOptions' => Event::eventTypeOptions(),
            'settings' => Settings::allKeyed(),
            'canManageCalendar' => in_array(Auth::role(), ['admin', 'operator', 'lawyer'], true),
        ]);
    }

    public function store()
    {
        $this->requireRole(['admin', 'operator', 'lawyer']);
        $this->onlyPost();
        $date = parse_jalali_date($_POST['event_date'] ?? '');
        if (!$date || trim($_POST['title'] ?? '') === '') {
            set_flash('error', 'عنوان و تاریخ رویداد الزامی است.');
            redirect('calendar', ['j_month' => $_POST['j_month'] ?? '']);
        }
        try {
            Event::createEvent([
                'assigned_user_id' => $_POST['assigned_user_id'] ?? ($_POST['user_id'] ?? null),
                'title' => $_POST['title'],
                'event_date' => $date,
                'event_time' => $_POST['event_time'] ?? null,
                'event_type' => $_POST['event_type'] ?? 'general',
                'description' => $_POST['description'] ?? '',
                'color' => $_POST['color'] ?? 'primary',
                'reminder_type' => $_POST['reminder_type'] ?? '',
                'custom_reminder_date' => $_POST['custom_reminder_date'] ?? '',
                'custom_reminder_time' => $_POST['custom_reminder_time'] ?? '',
            ]);
        } catch (Throwable $e) {
            set_flash('error', $e instanceof InvalidArgumentException ? $e->getMessage() : 'ثبت رویداد انجام نشد.');
            redirect('calendar', ['j_month' => $_POST['j_month'] ?? '']);
        }
        [$gy, $gm, $gd] = array_map('intval', explode('-', $date));
        [$jy, $jm] = gregorian_to_jalali($gy, $gm, $gd);
        set_flash('success', 'رویداد ثبت شد.');
        redirect('calendar', ['j_month' => sprintf('%04d/%02d', $jy, $jm)]);
    }

    public function delete($id)
    {
        $this->requireRole(['admin', 'operator', 'lawyer']);
        $this->onlyPost();
        if (!ConfirmationCode::verify('calendar_delete_' . (int) $id, $_POST['confirm_text'] ?? '')) {
            set_flash('error', 'عدد تأیید حذف رویداد درست وارد نشده است.');
            redirect('calendar', ['j_month' => $_POST['j_month'] ?? '']);
        }
        Event::deleteEvent((int) $id);
        set_flash('success', 'رویداد حذف شد.');
        redirect('calendar', ['j_month' => $_POST['j_month'] ?? '']);
    }

    protected function requestedJalaliMonth()
    {
        $value = trim(to_english_digits($_GET['j_month'] ?? ''));
        if (preg_match('/^(\d{4})[\/\-](\d{1,2})$/', $value, $matches)) {
            return [(int) $matches[1], max(1, min(12, (int) $matches[2]))];
        }
        [$jy, $jm] = gregorian_to_jalali((int) date('Y'), (int) date('n'), (int) date('j'));
        return [$jy, $jm];
    }

    protected function jalaliMonthDays($year, $month)
    {
        if ($month <= 6) {
            return 31;
        }
        if ($month <= 11) {
            return 30;
        }
        [$gy, $gm, $gd] = jalali_to_gregorian($year, 12, 30);
        [$jy, $jm, $jd] = gregorian_to_jalali($gy, $gm, $gd);
        return ($jy === (int) $year && $jm === 12 && $jd === 30) ? 30 : 29;
    }

    protected function monthName($month)
    {
        $names = [1 => 'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
        return $names[(int) $month] ?? '';
    }
}
