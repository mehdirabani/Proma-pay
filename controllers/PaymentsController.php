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
            redirect('portal/installments');
        }
        $amount = normalize_money($_POST['amount'] ?? $installment['payable']);
        if ($amount <= 0 || $amount > $installment['payable']) {
            set_flash('error', 'مبلغ پرداخت معتبر نیست.');
            redirect('portal/installments');
        }
        $settings = Settings::allKeyed();
        if ((string) ($settings['zibal_enabled'] ?? '1') !== '1') {
            set_flash('error', 'پرداخت آنلاین در حال حاضر غیرفعال است.');
            redirect('portal/installments');
        }
        $zibalTestMode = (string) ($settings['zibal_test_mode'] ?? '0') === '1';
        if (!$zibalTestMode && trim((string) ($settings['zibal_merchant'] ?? '')) === '') {
            set_flash('error', 'مرچنت زیبال برای پرداخت آنلاین تنظیم نشده است.');
            redirect('portal/installments');
        }
        $base = rtrim($settings['callback_base_url'] ?: detected_base_url(), '/');
        $callback = $base . '/index.php?route=payments/callback';
        $client = new ZibalClient($settings['zibal_merchant'], $zibalTestMode);
        $request = $client->request($amount, $callback, 'پرداخت قسط قرارداد ' . $installment['contract_number']);
        if (!$request['ok']) {
            set_flash('error', $request['message']);
            redirect('portal/installments');
        }
        Payment::createPendingGateway($installment['id'], $installment['contract_id'], Auth::id(), $amount, $request['track_id']);
        redirect_raw($request['start_url']);
    }

    public function cardTransfer()
    {
        $this->requireRole('customer');
        $this->onlyPost();
        if ((string) Settings::get('card_transfer_enabled', '1') !== '1') {
            set_flash('error', 'پرداخت کارت به کارت در حال حاضر فعال نیست.');
            redirect('portal/installments');
        }
        $installment = Installment::find((int) ($_POST['installment_id'] ?? 0));
        if (!$installment || (int) $installment['customer_id'] !== (int) Auth::id()) {
            set_flash('error', 'قسط برای پرداخت پیدا نشد.');
            redirect('portal/installments');
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
        redirect('portal/installments');
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
            redirect('portal/installments');
        }
        $settings = Settings::allKeyed();
        $client = new ZibalClient($settings['zibal_merchant'], (string) ($settings['zibal_test_mode'] ?? '0') === '1');
        $verify = $client->verify($trackId);
        if (!$verify['ok']) {
            set_flash('error', $verify['message']);
            redirect('portal/installments');
        }
        $result = Payment::completeGateway($trackId, $verify['ref_id'], $verify['amount_toman']);
        set_flash($result['ok'] ? 'success' : 'error', $result['message']);
        redirect('portal/installments');
    }
}
