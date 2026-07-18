<?php

class InstallmentsController extends Controller
{
    public function index()
    {
        $this->requireRole('admin');
        $filters = $this->filters();
        $result = Installment::filtered($filters);
        $this->render('installments/index', [
            'title' => 'مدیریت اقساط',
            'installments' => $result['items'],
            'filters' => $filters,
            'pagination' => $result,
            'contracts' => [],
            'defaultDueDate' => jdate(FinanceHelper::addMonths(date('Y-m-d'), 1)),
        ], is_ajax_request() ? null : 'app');
    }

    public function panel()
    {
        $this->requireRole('customer');
        $customerInstallments = Installment::all(['customer_id' => Auth::id()]);
        $installmentGroups = [];
        foreach ($customerInstallments as $item) {
            if ((float) ($item['payable'] ?? 0) <= 0) {
                continue;
            }
            $contractId = (int) ($item['contract_id'] ?? 0);
            if (!isset($installmentGroups[$contractId])) {
                $installmentGroups[$contractId] = ['contract_id' => $contractId, 'contract_number' => $item['contract_number'] ?? '', 'items' => [], 'total' => 0];
            }
            $installmentGroups[$contractId]['items'][] = $item;
            $installmentGroups[$contractId]['total'] += (float) ($item['payable'] ?? 0);
        }
        $this->render('installments/index', [
            'title' => 'پنل اقساط من',
            'installments' => $customerInstallments,
            'installmentGroups' => array_values($installmentGroups),
            'contracts' => [],
            'customerMode' => true,
            'installmentsRoute' => 'installments/panel',
        ]);
    }

    public function store()
    {
        $this->requireRole('admin');
        $this->onlyPost();
        $redirectTo = $this->redirectRoute();
        $dueDate = parse_jalali_date($_POST['due_date'] ?? '');
        if (!$dueDate) {
            set_flash('error', 'تاریخ سررسید معتبر نیست.');
            redirect($redirectTo);
        }
        if (trim((string) ($_POST['notes'] ?? '')) === '') {
            set_flash('error', 'توضیحات قسط سفارشی الزامی است.');
            redirect($redirectTo);
        }
        if ((int) ($_POST['contract_id'] ?? 0) <= 0) {
            set_flash('error', 'قرارداد معتبر انتخاب نشده است.');
            redirect($redirectTo);
        }
        try {
            Installment::createCustom((int) $_POST['contract_id'], $dueDate, $_POST['base_amount'], $_POST['notes'] ?? '', $_POST['guarantee_serial'] ?? '');
            set_flash('success', 'قسط سفارشی ثبت شد.');
        } catch (Throwable $e) {
            set_flash('error', $e instanceof InvalidArgumentException ? $e->getMessage() : 'ثبت قسط انجام نشد.');
        }
        redirect($redirectTo);
    }

    public function adjust($id)
    {
        $this->requireRole('admin');
        $this->onlyPost();
        Installment::adjust((int) $id, $_POST['manual_penalty_adjustment'] ?? 0, $_POST['manual_reward_adjustment'] ?? 0);
        set_flash('success', 'جریمه و پاداش قسط به‌روزرسانی شد.');
        redirect('installments');
    }

    public function payment($id)
    {
        $this->requireRole('admin');
        $this->onlyPost();
        $redirectTo = $this->redirectRoute();
        $requestUuid = PaymentRequest::normalizeUuid($_POST['payment_request_uuid'] ?? '');
        try {
            $installment = Installment::find((int) $id);
            if (!$installment) {
                set_flash('error', 'قسط پیدا نشد.');
                redirect('installments');
            }
            if (($installment['status'] ?? '') === 'cancelled') {
                throw new InvalidArgumentException('قسط لغو شده قابل پرداخت نیست.');
            }
            $amount = normalize_money($_POST['amount'] ?? 0);
            if ($amount <= 0) {
                throw new InvalidArgumentException('مبلغ پرداخت معتبر نیست.');
            }
            $paymentDate = parse_jalali_date($_POST['payment_date'] ?? '') ?: date('Y-m-d');
            $paymentTime = normalize_time($_POST['payment_time'] ?? null) ?: date('H:i');
            $preview = FinanceHelper::paymentPreview($installment, Payment::forInstallment((int) $id), Settings::allKeyed(), $amount, $paymentDate);
            $maxPayable = normalize_money($preview['payable_on_payment_date'] ?? 0);
            if ($maxPayable > 0 && $amount > $maxPayable) {
                throw new InvalidArgumentException('مبلغ پرداخت از مبلغ قابل پرداخت این قسط بیشتر است.');
            }
            $requestHash = PaymentRequest::hash([
                'installment_id' => (int) $id,
                'amount' => $amount,
                'payment_date' => $paymentDate,
                'payment_time' => $paymentTime,
                'description' => trim((string) ($_POST['description'] ?? 'پرداخت دستی')),
                'actor_user_id' => Auth::id(),
            ]);
            $requestState = PaymentRequest::begin($requestUuid, Auth::id(), (int) $id, $requestHash);
            if ($requestState['status'] === 'completed') {
                set_flash('success', 'این پرداخت قبلاً ثبت شده بود و از ثبت تکراری جلوگیری شد.');
                redirect($redirectTo);
            }
            $paymentId = Payment::record((int) $id, $installment['contract_id'], Auth::id(), $amount, 'manual', 'paid', null, null, $_POST['description'] ?? 'پرداخت دستی', $paymentDate, 'installment', $paymentTime);
            try {
                PaymentRequest::complete($requestUuid, $paymentId);
            } catch (Throwable $requestError) {
                if (class_exists('PluginRegistry')) {
                    PluginRegistry::logRuntimeError('payment_request.complete', $requestError);
                }
            }
            if (class_exists('SystemOutbox')) {
                try {
                    SystemOutbox::safeEnqueueNotification($installment['customer_id'], 'پرداخت جدید ثبت شد', 'یک پرداخت برای قسط شما ثبت شد.', 'payment', url('installments/panel'), 'payment', $paymentId);
                    SystemOutbox::processPending(10, 'payment', $paymentId);
                } catch (Throwable $outboxError) {
                    if (class_exists('PluginRegistry')) {
                        PluginRegistry::logRuntimeError('payment.manual.outbox', $outboxError);
                    }
                }
            }
            set_flash('success', 'پرداخت دستی ثبت شد.');
        } catch (Throwable $e) {
            PaymentRequest::fail($requestUuid, $e);
            set_flash('error', $e instanceof InvalidArgumentException ? $e->getMessage() : 'ثبت پرداخت انجام نشد. اگر مبلغ از حساب مشتری کم شده، گزارش پرداخت را بررسی کنید.');
        }
        redirect($redirectTo);
    }

    public function previewPayment()
    {
        $this->requireRole('admin');
        $installment = Installment::find((int) ($_GET['installment_id'] ?? 0));
        if (!$installment) {
            $this->json(['ok' => false, 'message' => 'قسط پیدا نشد.'], 404);
        }
        $paymentDate = parse_jalali_date($_GET['payment_date'] ?? '') ?: date('Y-m-d');
        $preview = FinanceHelper::paymentPreview(
            $installment,
            Payment::forInstallment((int) $installment['id']),
            Settings::allKeyed(),
            $_GET['payment_amount'] ?? 0,
            $paymentDate
        );
        $preview['formatted'] = [
            'base_amount' => money_toman($preview['base_amount']),
            'paid_amount' => money_toman($preview['paid_amount']),
            'remaining_before_payment' => money_toman($preview['remaining_before_payment']),
            'calculated_penalty' => money_toman($preview['calculated_penalty']),
            'calculated_penalty_html' => penalty_display_html($preview),
            'calculated_reward' => money_toman($preview['calculated_reward']),
            'payable_on_payment_date' => money_toman($preview['payable_on_payment_date']),
            'remaining_after_payment' => money_toman($preview['remaining_after_payment']),
        ];
        $preview['status_label'] = status_label($preview['final_status']);
        $this->json(['ok' => true, 'preview' => $preview]);
    }

    public function markPaid($id)
    {
        $this->requireRole('admin');
        $this->onlyPost();
        if (!Installment::markPaid((int) $id, Auth::id())) {
            set_flash('error', 'قسط لغو شده یا نامعتبر است.');
        } else {
            set_flash('success', 'قسط تسویه شد.');
        }
        redirect('installments');
    }

    protected function redirectRoute()
    {
        $target = $_POST['redirect_to'] ?? '';
        if ($target === 'overdue') {
            return 'overdue';
        }
        if ($target === 'contract' && !empty($_POST['contract_id'])) {
            return 'contracts/show/' . (int) $_POST['contract_id'];
        }
        if ($target === 'contracts') {
            return 'contracts';
        }
        return 'installments';
    }

    protected function filters()
    {
        $status = $_GET['status'] ?? '';
        $allowedStatuses = ['pending', 'partial', 'paid', 'overdue', 'referred', 'corrected', 'cancelled'];
        $paymentState = $_GET['payment_state'] ?? '';
        $allowedStates = ['paid', 'unpaid', 'overdue', 'custom'];
        return [
            'search' => trim((string) ($_GET['q'] ?? '')),
            'customer_name' => trim((string) ($_GET['customer_name'] ?? '')),
            'contract_number' => trim((string) ($_GET['contract_number'] ?? '')),
            'mobile' => trim((string) ($_GET['mobile'] ?? '')),
            'national_id' => trim((string) ($_GET['national_id'] ?? '')),
            'status' => in_array($status, $allowedStatuses, true) ? $status : '',
            'due_from' => parse_jalali_date($_GET['due_from'] ?? '') ?: '',
            'due_to' => parse_jalali_date($_GET['due_to'] ?? '') ?: '',
            'amount_min' => trim((string) ($_GET['amount_min'] ?? '')),
            'amount_max' => trim((string) ($_GET['amount_max'] ?? '')),
            'payment_state' => in_array($paymentState, $allowedStates, true) ? $paymentState : '',
            'page' => max(1, (int) to_english_digits($_GET['page'] ?? 1)),
            'per_page' => 20,
        ];
    }
}
