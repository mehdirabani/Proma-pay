<?php

class CustomersController extends Controller
{
    public function index()
    {
        $this->requireRole('admin');
        $result = User::customerSummariesPaginated($_GET['q'] ?? null, $_GET['status'] ?? null, $_GET['page'] ?? 1, 24);
        $customers = $result['items'];
        $ids = array_column($customers, 'id');
        foreach ($ids as $customerId) {
            User::syncAutomaticMedals((int) $customerId);
            if (class_exists('Medal')) {
                Medal::evaluateCustomer((int) $customerId, Auth::id());
            }
        }
        $medals = User::medalsForUsers($ids);
        $timelines = Payment::recentForCustomers($ids, 3);
        $verifiedDocuments = IdentityDocument::verifiedForUsers($ids);
        foreach ($customers as &$customer) {
            $total = max(1, (int) ($customer['installment_count'] ?? 0));
            $paidScore = ((int) ($customer['paid_installments'] ?? 0) / $total) * 100;
            $penalty = min(70, ((int) ($customer['overdue_installments'] ?? 0)) * 9);
            $customer['good_score'] = max(0, min(100, (int) ceil($paidScore - $penalty)));
            $customer['medals'] = $medals[(int) $customer['id']] ?? [];
            $customer['identity_verified'] = !empty($verifiedDocuments[(int) $customer['id']]);
            $customer['payment_timeline'] = $timelines[(int) $customer['id']] ?? [];
            $customer['payment_trend'] = Payment::monthlyTrendForCustomer((int) $customer['id']);
        }
        unset($customer);
        $this->render('customers/index', [
            'title' => 'مدیریت مشتریان',
            'customers' => $customers,
            'pagination' => $result,
        ], is_ajax_request() ? null : 'app');
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
                'father_name' => $_POST['father_name'] ?? '',
                'issued_from' => $_POST['issued_from'] ?? '',
                'national_id' => $_POST['national_id'] ?? '',
                'mobile' => $_POST['mobile'] ?? '',
                'secondary_phone' => $_POST['secondary_phone'] ?? '',
                'email' => '',
                'password' => '',
                'status' => $_POST['status'] ?? 'active',
                'address' => $_POST['address'] ?? '',
            ]);
            set_flash('success', 'مشتری با موفقیت ثبت شد.');
        } catch (Throwable $e) {
            set_flash('error', $e instanceof InvalidArgumentException ? $e->getMessage() : 'ثبت مشتری انجام نشد. کد ملی یا موبایل را بررسی کنید.');
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
        try {
            User::updateUser((int) $id, [
                'role' => 'customer',
                'full_name' => $_POST['full_name'] ?? '',
                'father_name' => $_POST['father_name'] ?? '',
                'issued_from' => $_POST['issued_from'] ?? '',
                'national_id' => $_POST['national_id'] ?? '',
                'mobile' => $_POST['mobile'] ?? '',
                'secondary_phone' => $_POST['secondary_phone'] ?? '',
                'email' => '',
                'status' => $_POST['status'] ?? 'active',
                'address' => $_POST['address'] ?? '',
            ]);
            set_flash('success', 'اطلاعات مشتری به‌روزرسانی شد.');
        } catch (Throwable $e) {
            set_flash('error', $e instanceof InvalidArgumentException ? $e->getMessage() : 'ویرایش مشتری انجام نشد.');
        }
        redirect('customers');
    }

    public function merge()
    {
        $this->requireRole('admin');
        $this->onlyPost();
        try {
            User::mergeCustomers($_POST['keep_customer_id'] ?? 0, $_POST['merge_customer_id'] ?? 0, Auth::id());
            set_flash('success', 'ادغام مشتریان با حفظ اطلاعات انجام شد.');
        } catch (Throwable $e) {
            set_flash('error', $e instanceof InvalidArgumentException ? $e->getMessage() : 'ادغام مشتریان انجام نشد.');
        }
        redirect('customers');
    }

    public function delete($id)
    {
        $this->requireRole('admin');
        $this->onlyPost();
        if (!ConfirmationCode::verify('customer_delete_' . (int) $id, $_POST['confirm_text'] ?? '')) {
            set_flash('error', 'عدد تأیید حذف مشتری درست وارد نشده است.');
            redirect('customers');
        }
        $activeContracts = Model::fetch("SELECT COUNT(*) AS total FROM contracts WHERE customer_id = ? AND status = 'active'", [(int) $id]);
        if ((int) ($activeContracts['total'] ?? 0) > 0) {
            set_flash('error', 'این مشتری دارای قرارداد فعال است و امکان حذف او وجود ندارد.');
            redirect('customers');
        }
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
            'medals' => User::medalsForUsers([(int) $id])[(int) $id] ?? [],
            'medalDefinitions' => class_exists('Medal') ? Medal::definitions() : [],
        ]);
    }

    public function medalStore($id)
    {
        $this->requireRole('admin');
        $this->onlyPost();
        $customer = User::find((int) $id);
        if (!$customer || $customer['role'] !== 'customer') {
            set_flash('error', 'مدال فقط برای مشتری قابل ثبت است.');
            redirect('customers');
        }
        if (trim((string) ($_POST['title'] ?? '')) === '') {
            set_flash('error', 'عنوان مدال الزامی است.');
            redirect('customers');
        }
        User::addMedal((int) $id, $_POST['title'], $_POST['description'] ?? '', $_POST['points'] ?? 0);
        set_flash('success', 'مدال مشتری ثبت شد.');
        redirect('customers');
    }

    public function medalUpdate($id)
    {
        $this->requireRole('admin');
        $this->onlyPost();
        if (trim((string) ($_POST['title'] ?? '')) === '') {
            set_flash('error', 'عنوان مدال الزامی است.');
            redirect('customers');
        }
        User::updateMedal((int) $id, $_POST);
        set_flash('success', 'مدال به‌روزرسانی شد.');
        redirect('customers');
    }

    public function medalDelete($id)
    {
        $this->requireRole('admin');
        $this->onlyPost();
        User::deleteMedal((int) $id);
        set_flash('success', 'مدال حذف شد.');
        redirect('customers');
    }
}
