<?php

class ContractsController extends Controller
{
    public function index()
    {
        $this->requireRole('admin');
        $today = date('Y-m-d');
        $this->render('contracts/index', [
            'title' => 'مدیریت قراردادها',
            'contracts' => Contract::all(['search' => $_GET['q'] ?? null]),
            'customers' => User::customers(),
            'operators' => User::all('operator'),
            'settings' => Settings::allKeyed(),
            'defaultStartDate' => jdate($today),
            'defaultFirstDueDate' => jdate(FinanceHelper::addMonths($today, 1)),
        ]);
    }

    public function store()
    {
        $this->requireRole('admin');
        $this->onlyPost();
        $customerId = $this->resolveCustomer();
        $startDate = parse_jalali_date($_POST['start_date'] ?? '') ?: date('Y-m-d');
        $firstDue = parse_jalali_date($_POST['first_due_date'] ?? '') ?: FinanceHelper::addMonths($startDate, 1);
        if (!$customerId || !$startDate || !$firstDue) {
            set_flash('error', 'اطلاعات مشتری و تاریخ‌های قرارداد باید کامل و معتبر باشد.');
            redirect('contracts');
        }
        try {
            Contract::createWithInstallments([
                'customer_id' => $customerId,
                'prefix' => $_POST['prefix'] ?? '',
                'principal_amount' => $_POST['principal_amount'] ?? 0,
                'down_payment_amount' => $_POST['down_payment_amount'] ?? 0,
                'monthly_interest_rate' => $_POST['monthly_interest_rate'] ?? 0,
                'interest_type' => ($_POST['interest_type'] ?? 'compound') === 'simple' ? 'simple' : 'compound',
                'months' => $_POST['months'] ?? 6,
                'start_date' => $startDate,
                'first_due_date' => $firstDue,
                'assigned_operator_id' => $_POST['assigned_operator_id'] ?? null,
                'notes' => $_POST['notes'] ?? '',
                'created_by' => Auth::id(),
            ], $_POST['guarantors'] ?? []);
            set_flash('success', 'قرارداد و اقساط آن با موفقیت ساخته شد.');
        } catch (Throwable $e) {
            set_flash('error', $e instanceof InvalidArgumentException ? $e->getMessage() : 'ثبت قرارداد انجام نشد. شماره قرارداد یا داده‌های ورودی را بررسی کنید.');
        }
        redirect('contracts');
    }

    public function update($id)
    {
        $this->requireRole('admin');
        $this->onlyPost();
        $startDate = parse_jalali_date($_POST['start_date'] ?? '');
        $firstDue = parse_jalali_date($_POST['first_due_date'] ?? '');
        if (!$startDate || !$firstDue) {
            set_flash('error', 'تاریخ‌های قرارداد معتبر نیست.');
            redirect('contracts');
        }
        try {
            Contract::updateContract((int) $id, [
                'customer_id' => (int) ($_POST['customer_id'] ?? 0),
                'principal_amount' => $_POST['principal_amount'] ?? 0,
                'down_payment_amount' => $_POST['down_payment_amount'] ?? 0,
                'monthly_interest_rate' => $_POST['monthly_interest_rate'] ?? 0,
                'interest_type' => ($_POST['interest_type'] ?? 'compound') === 'simple' ? 'simple' : 'compound',
                'months' => $_POST['months'] ?? 6,
                'start_date' => $startDate,
                'first_due_date' => $firstDue,
                'assigned_operator_id' => $_POST['assigned_operator_id'] ?? null,
                'notes' => $_POST['notes'] ?? '',
                'updated_by' => Auth::id(),
            ], $_POST['guarantors'] ?? []);
            set_flash('success', 'قرارداد به‌روزرسانی شد.');
        } catch (Throwable $e) {
            set_flash('error', $e instanceof InvalidArgumentException ? $e->getMessage() : 'ویرایش قرارداد انجام نشد. داده‌های ورودی را بررسی کنید.');
        }
        redirect('contracts');
    }

    public function preview()
    {
        $this->requireRole('admin');
        $preview = FinanceHelper::contractPreview(
            $_GET['principal_amount'] ?? 0,
            $_GET['down_payment_amount'] ?? 0,
            $_GET['months'] ?? 6,
            $_GET['monthly_interest_rate'] ?? 0,
            ($_GET['interest_type'] ?? 'compound') === 'simple' ? 'simple' : 'compound'
        );
        $preview['formatted'] = [
            'principal_amount' => money_toman($preview['principal_amount']),
            'down_payment_amount' => money_toman($preview['down_payment_amount']),
            'financed_amount' => money_toman($preview['financed_amount']),
            'installment_amount' => money_toman($preview['installment_amount']),
            'total_payable' => money_toman($preview['total_payable']),
        ];
        $this->json(['ok' => true, 'preview' => $preview]);
    }

    public function booklet($id)
    {
        Auth::requireLogin();
        $contract = Contract::find((int) $id);
        if (!$contract) {
            set_flash('error', 'قرارداد پیدا نشد.');
            redirect('contracts');
        }
        if (Auth::role() === 'customer' && (int) $contract['customer_id'] !== (int) Auth::id()) {
            http_response_code(403);
            $this->render('errors/403', ['title' => 'دسترسی غیرمجاز'], 'app');
            return;
        }
        if (Auth::role() !== 'admin' && Auth::role() !== 'customer') {
            http_response_code(403);
            $this->render('errors/403', ['title' => 'دسترسی غیرمجاز'], 'app');
            return;
        }
        $this->render('contracts/booklet', [
            'title' => 'دفترچه اقساط',
            'contract' => $contract,
            'guarantors' => Contract::guarantors((int) $id),
            'installments' => Installment::all(['contract_id' => (int) $id]),
        ], null);
    }

    public function delete($id)
    {
        $this->requireRole('admin');
        $this->onlyPost();
        if (trim($_POST['confirm_text'] ?? '') !== 'حذف قطعی') {
            set_flash('error', 'عبارت تأیید حذف درست وارد نشده است.');
            redirect('contracts');
        }
        Contract::deleteContract((int) $id);
        set_flash('success', 'قرارداد حذف شد.');
        redirect('contracts');
    }

    protected function resolveCustomer()
    {
        if (!empty($_POST['customer_id'])) {
            return (int) $_POST['customer_id'];
        }
        if (trim($_POST['new_customer_full_name'] ?? '') === '') {
            return null;
        }
        return User::create([
            'role' => 'customer',
            'username' => null,
            'full_name' => $_POST['new_customer_full_name'],
            'national_id' => $_POST['new_customer_national_id'] ?? '',
            'mobile' => $_POST['new_customer_mobile'] ?? '',
            'secondary_phone' => $_POST['new_customer_secondary_phone'] ?? '',
            'email' => '',
            'password' => bin2hex(random_bytes(8)),
            'status' => 'active',
        ]);
    }
}
