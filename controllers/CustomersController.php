<?php

class CustomersController extends Controller
{
    public function index()
    {
        $this->requireRole('admin');
        $customers = User::customerSummaries($_GET['q'] ?? null, $_GET['status'] ?? null);
        $ids = array_column($customers, 'id');
        $medals = User::medalsForUsers($ids);
        $timelines = Payment::recentForCustomers($ids, 3);
        foreach ($customers as &$customer) {
            $total = max(1, (int) ($customer['installment_count'] ?? 0));
            $paidScore = ((int) ($customer['paid_installments'] ?? 0) / $total) * 100;
            $penalty = min(70, ((int) ($customer['overdue_installments'] ?? 0)) * 9);
            $customer['good_score'] = max(0, min(100, (int) ceil($paidScore - $penalty)));
            $customer['medals'] = $medals[(int) $customer['id']] ?? [];
            $customer['payment_timeline'] = $timelines[(int) $customer['id']] ?? [];
            $customer['payment_trend'] = Payment::monthlyTrendForCustomer((int) $customer['id']);
        }
        unset($customer);
        $this->render('customers/index', [
            'title' => 'مدیریت مشتریان',
            'customers' => $customers,
        ]);
    }

    public function store()
    {
        $this->requireRole('admin');
        $this->onlyPost();
        $validator = (new Validator($_POST))
            ->required('full_name', 'نام کامل')
            ->required('national_id', 'کد ملی')
            ->required('mobile', 'موبایل')
            ->nationalId('national_id', 'کد ملی')
            ->mobile('mobile', 'موبایل');
        if (!$validator->passes()) {
            set_flash('error', implode(' ', $validator->errors()));
            redirect('customers');
        }
        try {
            User::create([
                'role' => 'customer',
                'username' => null,
                'full_name' => $_POST['full_name'] ?? '',
                'national_id' => $_POST['national_id'] ?? '',
                'mobile' => $_POST['mobile'] ?? '',
                'secondary_phone' => $_POST['secondary_phone'] ?? '',
                'email' => '',
                'password' => bin2hex(random_bytes(8)),
                'status' => $_POST['status'] ?? 'active',
            ]);
            set_flash('success', 'مشتری با موفقیت ثبت شد.');
        } catch (Throwable $e) {
            set_flash('error', 'ثبت مشتری انجام نشد. کد ملی یا موبایل را بررسی کنید.');
        }
        redirect('customers');
    }

    public function update($id)
    {
        $this->requireRole('admin');
        $this->onlyPost();
        $validator = (new Validator($_POST))->required('full_name', 'نام کامل')->nationalId('national_id', 'کد ملی')->mobile('mobile', 'موبایل');
        if (!$validator->passes()) {
            set_flash('error', implode(' ', $validator->errors()));
            redirect('customers');
        }
        User::updateUser((int) $id, [
            'role' => 'customer',
            'full_name' => $_POST['full_name'] ?? '',
            'national_id' => $_POST['national_id'] ?? '',
            'mobile' => $_POST['mobile'] ?? '',
            'secondary_phone' => $_POST['secondary_phone'] ?? '',
            'email' => '',
            'status' => $_POST['status'] ?? 'active',
        ]);
        set_flash('success', 'اطلاعات مشتری به‌روزرسانی شد.');
        redirect('customers');
    }

    public function delete($id)
    {
        $this->requireRole('admin');
        $this->onlyPost();
        User::deleteUser((int) $id);
        set_flash('success', 'مشتری حذف شد.');
        redirect('customers');
    }

    public function show($id)
    {
        $this->requireRole('admin');
        $customer = User::find((int) $id);
        if (!$customer || $customer['role'] !== 'customer') {
            set_flash('error', 'مشتری پیدا نشد.');
            redirect('customers');
        }
        $this->render('customers/show', [
            'title' => 'پرونده مشتری',
            'customer' => $customer,
            'contracts' => Contract::all(['customer_id' => $id]),
            'installments' => Installment::all(['customer_id' => $id]),
            'payments' => Payment::logs(['customer' => $customer['national_id']]),
            'paymentTimeline' => Payment::recentForCustomer((int) $id),
        ]);
    }
}
