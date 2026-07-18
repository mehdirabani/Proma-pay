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
        $_POST['gateway_id'] = 'zibal';
        $_POST['_legacy_gateway'] = 'zibal';
        return $this->gateway();
    }

    public function gateway()
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
        try {
            $registry = PaymentGatewayRegistry::boot();
            $gateway = ($_POST['_legacy_gateway'] ?? '') === 'zibal'
                ? $registry->get('zibal')
                : $registry->resolveCustomerGateway($_POST['gateway_id'] ?? '', false);
            if (!$gateway || !$gateway->isEnabled()) {
                throw new InvalidArgumentException('درگاه پرداخت انتخاب‌شده فعال یا تنظیم نشده است.');
            }
            $user = User::find((int) Auth::id());
            $result = $gateway->createPayment([
                'type' => 'single',
                'installment_id' => (int) $installment['id'],
                'installment_number' => (int) $installment['installment_number'],
                'contract_id' => (int) $installment['contract_id'],
                'contract_number' => (string) $installment['contract_number'],
                'customer_id' => (int) Auth::id(),
                'customer_name' => (string) ($installment['customer_name'] ?? ''),
                'customer_mobile' => (string) ($user['mobile'] ?? ''),
                'customer_email' => (string) ($user['email'] ?? ''),
                'amount_toman' => (int) $amount,
                'description' => 'پرداخت قسط قرارداد ' . $installment['contract_number'],
                'idempotency_key' => $this->paymentIdempotencyKey($_POST['idempotency_key'] ?? '', 'single', (int) $installment['id']),
            ]);
            if (empty($result['redirect_url'])) {
                throw new RuntimeException('نشانی انتقال درگاه دریافت نشد.');
            }
            redirect_raw($result['redirect_url']);
        } catch (Throwable $e) {
            set_flash('error', $e instanceof InvalidArgumentException || $e instanceof RuntimeException ? $e->getMessage() : 'اتصال به درگاه پرداخت انجام نشد.');
            redirect('installments/panel');
        }
    }

    public function zibalGroup()
    {
        $_POST['gateway_id'] = 'zibal';
        $_POST['_legacy_gateway'] = 'zibal';
        return $this->gatewayGroup();
    }

    public function gatewayGroup()
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
        try {
            $outstanding = Model::fetch(
                "SELECT COALESCE(SUM(GREATEST(base_amount - paid_amount, 0)), 0) AS total FROM installments WHERE contract_id = ? AND status NOT IN ('paid', 'cancelled')",
                [$contractId]
            );
            if ($amount > (int) round((float) ($outstanding['total'] ?? 0))) {
                throw new InvalidArgumentException('مبلغ پرداخت از کل بدهی قابل تخصیص این قرارداد بیشتر است.');
            }
            $registry = PaymentGatewayRegistry::boot();
            $gateway = ($_POST['_legacy_gateway'] ?? '') === 'zibal'
                ? $registry->get('zibal')
                : $registry->resolveCustomerGateway($_POST['gateway_id'] ?? '', true);
            if (!$gateway || !$gateway->isEnabled() || !$gateway->supportsPaymentGroups()) {
                throw new InvalidArgumentException('درگاه پرداخت انتخاب‌شده فعال یا تنظیم نشده است.');
            }
            $user = User::find((int) Auth::id());
            $result = $gateway->createPayment([
                'type' => 'group',
                'contract_id' => $contractId,
                'contract_number' => (string) ($installments[0]['contract_number'] ?? $contractId),
                'installment_ids' => $ids,
                'customer_id' => (int) Auth::id(),
                'customer_name' => (string) ($installments[0]['customer_name'] ?? ''),
                'customer_mobile' => (string) ($user['mobile'] ?? ''),
                'customer_email' => (string) ($user['email'] ?? ''),
                'amount_toman' => (int) $amount,
                'description' => 'پرداخت چندقسطی قرارداد ' . ($installments[0]['contract_number'] ?? $contractId),
                'idempotency_key' => $this->paymentIdempotencyKey($_POST['idempotency_key'] ?? '', 'group', $contractId),
            ]);
            if (empty($result['redirect_url'])) {
                throw new RuntimeException('نشانی انتقال درگاه دریافت نشد.');
            }
            redirect_raw($result['redirect_url']);
        } catch (Throwable $e) {
            set_flash('error', $e instanceof InvalidArgumentException || $e instanceof RuntimeException ? $e->getMessage() : 'ذخیره پرداخت گروهی آنلاین انجام نشد.');
            redirect('installments/panel');
        }
    }

    protected function paymentIdempotencyKey($provided, $type, $scopeId)
    {
        $provided = strtolower(trim((string) $provided));
        if (!preg_match('/^[a-f0-9]{32,64}$/', $provided)) {
            $provided = bin2hex(random_bytes(24));
        }
        return 'gateway:' . preg_replace('/[^a-z]/', '', (string) $type) . ':' . (int) Auth::id() . ':' . (int) $scopeId . ':' . $provided;
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
            $completed = false;
            $paymentRecordId = 0;
            if ($group) {
                $completedGroup = PaymentGroupService::completeGateway((int) $group['id'], $verify['amount_toman'], $verify['ref_id'], (int) $group['customer_id']);
                if (class_exists('SystemOutbox')) {
                    SystemOutbox::safeEnqueueNotification((int) $group['customer_id'], 'پرداخت چندقسطی موفق شد', 'پرداخت آنلاین گروهی شما با موفقیت ثبت شد.', 'payment', url('installments/panel'), 'payment_group', (int) ($completedGroup['id'] ?? $group['id']));
                    SystemOutbox::processPending(10, 'payment_group', (int) ($completedGroup['id'] ?? $group['id']));
                }
                set_flash('success', 'پرداخت چندقسطی با موفقیت ثبت شد.');
                $completed = true;
                $paymentRecordId = (int) ($completedGroup['id'] ?? $group['id']);
            } else {
                $result = Payment::completeGateway($trackId, $verify['ref_id'], $verify['amount_toman']);
                set_flash($result['ok'] ? 'success' : 'error', $result['message']);
                $completed = !empty($result['ok']);
                $paymentRecordId = (int) ($result['payment_id'] ?? 0);
            }
            if ($completed) {
                try {
                    if (class_exists('AuditLog')) {
                        \AuditLog::record('payment_gateway', (int) $verify['code'] === 101 ? 'zarinpal_recovered' : 'zarinpal_verified', 'zarinpal_transaction', $paymentRecordId, [
                            'actor_type' => 'gateway',
                            'customer_id' => (int) ($group['customer_id'] ?? 0),
                            'contract_id' => (int) ($group['contract_id'] ?? 0),
                            'new_values' => [
                                'status' => 'paid',
                                'ref_id' => substr((string) $verify['ref_id'], 0, 100),
                                'verify_code' => (int) $verify['code'],
                            ],
                        ]);
                    }
                } catch (Throwable $auditError) {
                    if (class_exists('PluginRegistry')) {
                        PluginRegistry::logRuntimeError('payment_gateway.audit', $auditError);
                    }
                }
            }
        } catch (Throwable $e) {
            set_flash('error', $e instanceof InvalidArgumentException ? $e->getMessage() : 'ثبت نتیجه پرداخت انجام نشد.');
        }
        redirect('installments/panel');
    }
}
