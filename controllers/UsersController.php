<?php

class UsersController extends Controller
{
    public function index()
    {
        $this->requireRole(['admin', 'operator', 'lawyer']);
        $role = $_GET['role'] ?? null;
        $status = $_GET['status'] ?? null;
        $allowedRoles = ['admin', 'operator', 'lawyer', 'customer'];
        $role = in_array($role, $allowedRoles, true) ? $role : null;
        $status = in_array($status, ['active', 'inactive'], true) ? $status : null;
        $users = User::all($role, $_GET['q'] ?? null, $status);
        foreach ($users as $candidate) {
            if (($candidate['role'] ?? '') === 'customer') {
                User::syncAutomaticMedals((int) $candidate['id']);
            }
        }
        $medals = User::medalsForUsers(array_column($users, 'id'));
        foreach ($users as &$user) {
            $user['medals'] = $medals[(int) $user['id']] ?? [];
        }
        unset($user);
        $this->render('users/index', [
            'title' => 'مدیریت کاربران',
            'users' => $users,
            'roles' => $allowedRoles,
            'canManageUsers' => Auth::role() === 'admin',
            'profileRequests' => Auth::role() === 'admin' ? ProfileRequest::pending() : [],
        ]);
    }

    public function store()
    {
        $this->requireRole('admin');
        $this->onlyPost();
        $validator = (new Validator($_POST))
            ->required('full_name', 'نام کامل')
            ->required('username', 'نام کاربری')
            ->required('password', 'رمز عبور')
            ->mobile('mobile', 'موبایل')
            ->email('email', 'ایمیل');
        if (!$validator->passes()) {
            set_flash('error', implode(' ', $validator->errors()));
            redirect('users');
        }
        try {
            User::create([
                'role' => in_array($_POST['role'] ?? '', ['admin', 'operator', 'lawyer'], true) ? $_POST['role'] : 'operator',
                'username' => $_POST['username'] ?? '',
                'full_name' => $_POST['full_name'] ?? '',
                'father_name' => $_POST['father_name'] ?? '',
                'issued_from' => $_POST['issued_from'] ?? '',
                'national_id' => $_POST['national_id'] ?? '',
                'mobile' => $_POST['mobile'] ?? '',
                'secondary_phone' => '',
                'email' => $_POST['email'] ?? '',
                'password' => $_POST['password'] ?? '',
                'status' => $_POST['status'] ?? 'active',
                'address' => $_POST['address'] ?? '',
                'avatar_key' => in_array($_POST['avatar_key'] ?? '', ['avatar-1', 'avatar-2', 'avatar-3', 'avatar-4', 'avatar-5', 'avatar-6'], true) ? $_POST['avatar_key'] : null,
            ]);
            set_flash('success', 'کاربر با موفقیت ثبت شد.');
        } catch (Throwable $e) {
            set_flash('error', 'ثبت کاربر انجام نشد. داده‌های تکراری را بررسی کنید.');
        }
        redirect('users');
    }

    public function update($id)
    {
        $this->requireRole('admin');
        $this->onlyPost();
        $user = User::find((int) $id);
        if (!$user) {
            set_flash('error', 'کاربر پیدا نشد.');
            redirect('users');
        }
        $validator = (new Validator($_POST))->required('full_name', 'نام کامل')->mobile('mobile', 'موبایل')->email('email', 'ایمیل');
        if (!$validator->passes()) {
            set_flash('error', implode(' ', $validator->errors()));
            redirect('users');
        }
        try {
            $role = in_array($_POST['role'] ?? '', ['admin', 'operator', 'lawyer', 'customer'], true) ? $_POST['role'] : $user['role'];
            User::updateUser((int) $id, [
                'role' => $role,
                'username' => $_POST['username'] ?? '',
                'full_name' => $_POST['full_name'] ?? '',
                'father_name' => $_POST['father_name'] ?? '',
                'issued_from' => $_POST['issued_from'] ?? '',
                'national_id' => $_POST['national_id'] ?? '',
                'mobile' => $_POST['mobile'] ?? '',
                'secondary_phone' => '',
                'email' => $_POST['email'] ?? '',
                'password' => $_POST['password'] ?? '',
                'status' => $_POST['status'] ?? 'active',
                'address' => $_POST['address'] ?? '',
                'avatar_key' => in_array($_POST['avatar_key'] ?? '', ['avatar-1', 'avatar-2', 'avatar-3', 'avatar-4', 'avatar-5', 'avatar-6'], true) ? $_POST['avatar_key'] : null,
            ]);
            set_flash('success', 'اطلاعات کاربر به‌روزرسانی شد.');
        } catch (Throwable $e) {
            set_flash('error', 'ویرایش کاربر انجام نشد.');
        }
        redirect('users');
    }

    public function delete($id)
    {
        $this->requireRole('admin');
        $this->onlyPost();
        $user = User::find((int) $id);
        if (!$user) {
            set_flash('error', 'کاربر پیدا نشد.');
            redirect('users');
        }
        if ((int) $id === (int) Auth::id()) {
            set_flash('error', 'حذف حساب مدیر فعلی مجاز نیست.');
            redirect('users');
        }
        if ($user['role'] === 'admin' && User::countAdmins() <= 1) {
            set_flash('error', 'حذف آخرین مدیر سامانه مجاز نیست.');
            redirect('users');
        }
        User::deleteUser((int) $id);
        set_flash('success', 'کاربر حذف شد.');
        redirect('users');
    }

    public function medalStore($id)
    {
        $this->requireRole('admin');
        $this->onlyPost();
        $user = User::find((int) $id);
        if (!$user || $user['role'] !== 'customer') {
            set_flash('error', 'مدال فقط برای مشتری قابل ثبت است.');
            redirect('users');
        }
        if (trim($_POST['title'] ?? '') === '') {
            set_flash('error', 'عنوان مدال الزامی است.');
            redirect('users');
        }
        User::addMedal((int) $id, $_POST['title'], $_POST['description'] ?? '', $_POST['points'] ?? 0);
        set_flash('success', 'مدال مشتری ثبت شد.');
        redirect('users', ['role' => 'customer']);
    }

    public function medalDelete($id)
    {
        $this->requireRole('admin');
        $this->onlyPost();
        User::deleteMedal((int) $id);
        set_flash('success', 'مدال حذف شد.');
        redirect('users', ['role' => 'customer']);
    }
}
