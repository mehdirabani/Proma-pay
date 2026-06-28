<?php

class CalendarController extends Controller
{
    public function index()
    {
        $this->requireRole('admin');
        [$jy, $jm] = $this->requestedJalaliMonth();
        [$gy, $gm, $gd] = jalali_to_gregorian($jy, $jm, 1);
        $startDate = sprintf('%04d-%02d-%02d', $gy, $gm, $gd);
        [$nextJy, $nextJm] = $jm === 12 ? [$jy + 1, 1] : [$jy, $jm + 1];
        [$egy, $egm, $egd] = jalali_to_gregorian($nextJy, $nextJm, 1);
        $endDate = sprintf('%04d-%02d-%02d', $egy, $egm, $egd);
        $weekday = (int) date('w', strtotime($startDate));
        $monthTitle = $this->monthName($jm) . ' ' . to_persian_digits($jy);

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
            'events' => Event::allBetween($startDate, $endDate),
            'users' => User::all(null),
        ]);
    }

    public function store()
    {
        $this->requireRole('admin');
        $this->onlyPost();
        $date = parse_jalali_date($_POST['event_date'] ?? '');
        if (!$date || trim($_POST['title'] ?? '') === '') {
            set_flash('error', 'عنوان و تاریخ رویداد الزامی است.');
            redirect('calendar', ['j_month' => $_POST['j_month'] ?? '']);
        }
        Event::createEvent([
            'user_id' => $_POST['user_id'] ?? null,
            'title' => $_POST['title'],
            'event_date' => $date,
            'description' => $_POST['description'] ?? '',
            'color' => $_POST['color'] ?? 'primary',
        ]);
        [$gy, $gm, $gd] = array_map('intval', explode('-', $date));
        [$jy, $jm] = gregorian_to_jalali($gy, $gm, $gd);
        set_flash('success', 'رویداد ثبت شد.');
        redirect('calendar', ['j_month' => sprintf('%04d/%02d', $jy, $jm)]);
    }

    public function delete($id)
    {
        $this->requireRole('admin');
        $this->onlyPost();
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
