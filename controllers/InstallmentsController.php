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
        Installment::createCustom((int) $_POST['contract_id'], $dueDate, $_POST['base_amount'], $_POST['notes'] ?? '', $_POST['guarantee_serial'] ?? '');
        set_flash('success', 'قسط سفارشی ثبت شد.');
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
        $installment = Installment::find((int) $id);
        if (!$installment) {
            set_flash('error', 'قسط پیدا نشد.');
            redirect('installments');
        }
        if (normalize_money($_POST['amount'] ?? 0) <= 0) {
            set_flash('error', 'مبلغ پرداخت معتبر نیست.');
            redirect($this->redirectRoute());
        }
        $paymentDate = parse_jalali_date($_POST['payment_date'] ?? '') ?: date('Y-m-d');
        Payment::record((int) $id, $installment['contract_id'], Auth::id(), $_POST['amount'] ?? 0, 'manual', 'paid', null, null, $_POST['description'] ?? 'پرداخت دستی', $paymentDate, 'installment', $_POST['payment_time'] ?? null);
        Notification::create($installment['customer_id'], 'پرداخت جدید ثبت شد', 'یک پرداخت برای قسط شما ثبت شد.', 'payment', url('portal/installments'));
        set_flash('success', 'پرداخت دستی ثبت شد.');
        redirect($this->redirectRoute());
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
        Installment::markPaid((int) $id, Auth::id());
        set_flash('success', 'قسط تسویه شد.');
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
        $allowedStatuses = ['pending', 'partial', 'paid', 'overdue', 'referred', 'corrected'];
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
