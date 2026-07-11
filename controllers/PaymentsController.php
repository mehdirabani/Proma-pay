<?php

class PaymentsController extends Controller
{
    public function index()
    {
        $this->requireRole('admin');
        $filters = [
            'date_from' => parse_jalali_date($_GET['date_from'] ?? '') ?: null,
            'date_to' => parse_jalali_date($_GET['date_to'] ?? '') ?: null,
            'contract_number' => $_GET['contract_number'] ?? null,
            'customer' => $_GET['customer'] ?? null,
            'limit' => 80,
        ];
        $this->render('payments/index', [
            'title' => 'گزارش پرداخت‌ها',
            'payments' => Payment::logs($filters),
        ], is_ajax_request() ? null : 'app');
    }

    public function zibal()
    {
        $this->requireRole('customer');
        $this->onlyPost();
        $installment = Installment::find((int) ($_POST['installment_id'] ?? 0));
        if (!$installment || (int) $installment['customer_id'] !== (int) Auth::id()) {
            set_flash('error', 'قسط برای پرداخت پیدا نشد.');
            redirect('installments/panel');
        }
        $amount = normalize_money($_POST['amount'] ?? $installment['payable']);
        if ($amount <= 0 || $amount > $installment['payable']) {
            set_flash('error', 'مبلغ پرداخت معتبر نیست.');
            redirect('installments/panel');
        }
        $settings = Settings::allKeyed();
        if ((string) ($settings['zibal_enabled'] ?? '1') !== '1') {
            set_flash('error', 'پرداخت آنلاین در حال حاضر غیرفعال است.');
            redirect('installments/panel');
        }
        $zibalTestMode = (string) ($settings['zibal_test_mode'] ?? '0') === '1';
        if (!$zibalTestMode && trim((string) ($settings['zibal_merchant'] ?? '')) === '') {
            set_flash('error', 'مرچنت زیبال برای پرداخت آنلاین تنظیم نشده است.');
            redirect('installments/panel');
        }
        $base = rtrim($settings['callback_base_url'] ?: detected_base_url(), '/');
        $callback = $base . '/index.php?route=payments/callback';
        $client = new ZibalClient($settings['zibal_merchant'], $zibalTestMode);
        $request = $client->request($amount, $callback, 'پرداخت قسط قرارداد ' . $installment['contract_number']);
        if (!$request['ok']) {
            set_flash('error', $request['message']);
            redirect('installments/panel');
        }
        Payment::createPendingGateway($installment['id'], $installment['contract_id'], Auth::id(), $amount, $request['track_id']);
        redirect_raw($request['start_url']);
    }

    public function zibalGroup()
    {
        $this->requireRole('customer');
        $this->onlyPost();
        $contractId = (int) ($_POST['contract_id'] ?? 0);
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['installment_ids'] ?? [])))));
        $installments = [];
        foreach ($ids as $id) {
            $installment = Installment::find($id);
            if (!$installment || (int) ($installment['customer_id'] ?? 0) !== (int) Auth::id() || (int) ($installment['contract_id'] ?? 0) !== $contractId || (float) ($installment['payable'] ?? 0) <= 0) {
                set_flash('error', 'یکی از اقساط انتخاب‌شده متعلق به شما نیست یا قابل پرداخت نیست.');
                redirect('installments/panel');
            }
            $installments[] = $installment;
        }
        $amount = normalize_money($_POST['amount'] ?? 0);
        if (!$ids || $amount <= 0) {
            set_flash('error', 'حداقل یک قسط و مبلغ معتبر انتخاب کنید.');
            redirect('installments/panel');
        }
        $settings = Settings::allKeyed();
        $zibalTestMode = (string) ($settings['zibal_test_mode'] ?? '0') === '1';
        if ((string) ($settings['zibal_enabled'] ?? '1') !== '1' || (!$zibalTestMode && trim((string) ($settings['zibal_merchant'] ?? '')) === '')) {
            set_flash('error', 'پرداخت آنلاین در حال حاضر فعال یا تنظیم نشده است.');
            redirect('installments/panel');
        }
        $base = rtrim($settings['callback_base_url'] ?: detected_base_url(), '/');
        $callback = $base . '/index.php?route=payments/callback';
        $client = new ZibalClient($settings['zibal_merchant'], $zibalTestMode);
        $request = $client->request($amount, $callback, 'پرداخت چندقسطی قرارداد ' . ($installments[0]['contract_number'] ?? $contractId));
        if (!$request['ok']) {
            set_flash('error', $request['message']);
            redirect('installments/panel');
        }
        try {
            PaymentGroupService::createPendingGateway($contractId, $ids, $amount, Auth::id(), $request['track_id'], 'zibal-group:' . hash('sha256', Auth::id() . '|' . $contractId . '|' . implode(',', $ids) . '|' . $amount . '|' . Csrf::token()));
        } catch (Throwable $e) {
            set_flash('error', $e instanceof InvalidArgumentException ? $e->getMessage() : 'ذخیره پرداخت گروهی آنلاین انجام نشد.');
            redirect('installments/panel');
        }
        redirect_raw($request['start_url']);
    }

    public function cardTransfer()
    {
        $this->requireRole('customer');
        $this->onlyPost();
        if ((string) Settings::get('card_transfer_enabled', '1') !== '1') {
            set_flash('error', 'پرداخت کارت به کارت در حال حاضر فعال نیست.');
            redirect('installments/panel');
        }
        $installment = Installment::find((int) ($_POST['installment_id'] ?? 0));
        if (!$installment || (int) $installment['customer_id'] !== (int) Auth::id()) {
            set_flash('error', 'قسط برای پرداخت پیدا نشد.');
            redirect('installments/panel');
        }
        try {
            $path = UploadHelper::storeImage($_FILES['receipt'] ?? [], 'payment_receipts/' . Auth::id());
            if (!$path) {
                throw new InvalidArgumentException('تصویر رسید پرداخت را انتخاب کنید.');
            }
            PaymentReceipt::submit((int) $installment['id'], Auth::id(), $_POST['amount'] ?? $installment['payable'], $path);
            set_flash('success', 'رسید پرداخت برای بررسی مدیریت ثبت شد.');
        } catch (Throwable $e) {
            if (!empty($path)) {
                UploadHelper::deleteRelative($path);
            }
            set_flash('error', $e->getMessage());
        }
        redirect('installments/panel');
    }

    public function receiptFile($id)
    {
        Auth::requireLogin();
        $receipt = PaymentReceipt::find((int) $id);
        if (!$receipt || empty($receipt['receipt_path'])) {
            http_response_code(404);
            echo 'فایل پیدا نشد.';
            return;
        }
        if (Auth::role() !== 'admin' && (int) $receipt['customer_id'] !== (int) Auth::id()) {
            http_response_code(403);
            echo 'دسترسی غیرمجاز';
            return;
        }
        $path = UploadHelper::absolutePath($receipt['receipt_path']);
        if (!$path) {
            http_response_code(404);
            echo 'فایل پیدا نشد.';
            return;
        }
        $mime = function_exists('mime_content_type') ? (mime_content_type($path) ?: 'application/octet-stream') : 'application/octet-stream';
        header('Content-Type: ' . $mime);
        header('Content-Disposition: inline; filename="' . basename($path) . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }

    public function approveReceipt($id)
    {
        $this->requireRole('admin');
        $this->onlyPost();
        try {
            PaymentReceipt::approve((int) $id, Auth::id(), $_POST['review_note'] ?? '', $_POST['approved_amount'] ?? null);
            set_flash('success', 'رسید پرداخت تأیید و فایل آن حذف شد.');
        } catch (Throwable $e) {
            set_flash('error', $e->getMessage());
        }
        redirect('review', ['tab' => 'receipts']);
    }

    public function rejectReceipt($id)
    {
        $this->requireRole('admin');
        $this->onlyPost();
        try {
            PaymentReceipt::reject((int) $id, Auth::id(), $_POST['review_note'] ?? '');
            set_flash('success', 'رسید پرداخت رد و فایل آن حذف شد.');
        } catch (Throwable $e) {
            set_flash('error', $e->getMessage());
        }
        redirect('review', ['tab' => 'receipts']);
    }

    public function correct($id)
    {
        $this->requireRole('admin');
        $this->onlyPost();
        $result = Payment::correct((int) $id, $_POST['correction_reason'] ?? '', Auth::id());
        set_flash($result['ok'] ? 'success' : 'error', $result['message']);
        redirect('payments', [
            'date_from' => $_POST['date_from'] ?? '',
            'date_to' => $_POST['date_to'] ?? '',
            'contract_number' => $_POST['contract_number'] ?? '',
            'customer' => $_POST['customer'] ?? '',
        ]);
    }

    public function callback()
    {
        require_once __DIR__ . '/../bootstrap.php';
        $trackId = to_english_digits($_GET['trackId'] ?? $_GET['trackid'] ?? '');
        if ($trackId === '') {
            set_flash('error', 'شناسه پیگیری پرداخت دریافت نشد.');
            redirect('installments/panel');
        }
        $settings = Settings::allKeyed();
        $client = new ZibalClient($settings['zibal_merchant'], (string) ($settings['zibal_test_mode'] ?? '0') === '1');
        $verify = $client->verify($trackId);
        if (!$verify['ok']) {
            set_flash('error', $verify['message']);
            redirect('installments/panel');
        }
        $group = Model::fetch('SELECT * FROM payment_groups WHERE gateway_track_id = ? LIMIT 1', [$trackId]);
        try {
            if ($group) {
                PaymentGroupService::completeGateway((int) $group['id'], $verify['amount_toman'], $verify['ref_id'], (int) $group['customer_id']);
                Notification::create((int) $group['customer_id'], 'پرداخت چندقسطی موفق شد', 'پرداخت آنلاین گروهی شما با موفقیت ثبت شد.', 'payment', url('installments/panel'));
                set_flash('success', 'پرداخت چندقسطی با موفقیت ثبت شد.');
            } else {
                $result = Payment::completeGateway($trackId, $verify['ref_id'], $verify['amount_toman']);
                set_flash($result['ok'] ? 'success' : 'error', $result['message']);
            }
        } catch (Throwable $e) {
            set_flash('error', $e instanceof InvalidArgumentException ? $e->getMessage() : 'ثبت نتیجه پرداخت انجام نشد.');
        }
        redirect('installments/panel');
    }
}
