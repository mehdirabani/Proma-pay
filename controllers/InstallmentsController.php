<?php

class InstallmentsController extends Controller
{
    public function index()
    {
        $this->requireRole('admin');
        $this->render('installments/index', [
            'title' => 'مدیریت اقساط',
            'installments' => Installment::all(['search' => $_GET['q'] ?? null, 'status' => $_GET['status'] ?? null]),
            'contracts' => Contract::all(),
            'defaultDueDate' => jdate(FinanceHelper::addMonths(date('Y-m-d'), 1)),
        ]);
    }

    public function store()
    {
        $this->requireRole('admin');
        $this->onlyPost();
        $redirectTo = ($_POST['redirect_to'] ?? '') === 'contracts' ? 'contracts' : 'installments';
        $dueDate = parse_jalali_date($_POST['due_date'] ?? '');
        if (!$dueDate) {
            set_flash('error', 'تاریخ سررسید معتبر نیست.');
            redirect($redirectTo);
        }
        if (trim((string) ($_POST['notes'] ?? '')) === '') {
            set_flash('error', 'توضیحات قسط سفارشی الزامی است.');
            redirect($redirectTo);
        }
        Installment::createCustom((int) $_POST['contract_id'], $dueDate, $_POST['base_amount'], $_POST['notes'] ?? '');
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
            redirect(($_POST['redirect_to'] ?? '') === 'overdue' ? 'overdue' : 'installments');
        }
        $paymentDate = parse_jalali_date($_POST['payment_date'] ?? '') ?: date('Y-m-d');
        Payment::record((int) $id, $installment['contract_id'], Auth::id(), $_POST['amount'] ?? 0, 'manual', 'paid', null, null, $_POST['description'] ?? 'پرداخت دستی', $paymentDate, 'installment', $_POST['payment_time'] ?? null);
        Notification::create($installment['customer_id'], 'پرداخت جدید ثبت شد', 'یک پرداخت برای قسط شما ثبت شد.', 'payment', url('portal/installments'));
        set_flash('success', 'پرداخت دستی ثبت شد.');
        redirect(($_POST['redirect_to'] ?? '') === 'overdue' ? 'overdue' : 'installments');
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
}
