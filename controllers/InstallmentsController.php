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
        Installment::createCustom((int) $_POST['contract_id'], $dueDate, $_POST['base_amount']);
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
        Payment::record((int) $id, $installment['contract_id'], Auth::id(), $_POST['amount'] ?? 0, 'manual', 'paid', null, null, $_POST['description'] ?? 'پرداخت دستی');
        Notification::create($installment['customer_id'], 'پرداخت جدید ثبت شد', 'یک پرداخت برای قسط شما ثبت شد.', 'payment', url('portal/installments'));
        set_flash('success', 'پرداخت دستی ثبت شد.');
        redirect('installments');
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
