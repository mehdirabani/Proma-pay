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
        ];
        $this->render('payments/index', [
            'title' => 'گزارش پرداخت‌ها',
            'payments' => Payment::logs($filters),
        ]);
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
        $base = rtrim($settings['callback_base_url'] ?: ((isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . dirname($_SERVER['SCRIPT_NAME'])), '/');
        $callback = $base . '/index.php?route=payments/callback';
        $client = new ZibalClient($settings['zibal_merchant']);
        $request = $client->request($amount, $callback, 'پرداخت قسط قرارداد ' . $installment['contract_number']);
        if (!$request['ok']) {
            set_flash('error', $request['message']);
            redirect('portal/installments');
        }
        Payment::createPendingGateway($installment['id'], $installment['contract_id'], Auth::id(), $amount, $request['track_id']);
        redirect_raw($request['start_url']);
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
        $client = new ZibalClient($settings['zibal_merchant']);
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
