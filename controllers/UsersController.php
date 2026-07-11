<?php

class UsersController extends Controller
{
    public function index()
    {
        Auth::requireLogin();
        if (!Auth::canViewUsers()) {
            http_response_code(403);
            $this->render('errors/403', ['title' => 'دسترسی غیرمجاز'], 'app');
            return;
        }
        $currentUser = Auth::user();
        $role = $_GET['role'] ?? null;
        $status = $_GET['status'] ?? null;
        $allowedRoles = ['admin', 'operator', 'lawyer'];
        $role = in_array($role, $allowedRoles, true) ? $role : null;
        $status = in_array($status, ['active', 'inactive'], true) ? $status : null;
        $options = [];
        if (Auth::role() !== 'admin') {
            $options['department'] = $currentUser['department'] ?? '';
            $options['roles'] = ['admin', 'operator', 'lawyer'];
            if ($role === 'customer') {
                $role = null;
            }
        } elseif (isset($_GET['department']) && $_GET['department'] !== '') {
            $options['department'] = $_GET['department'];
        }
        $options['roles'] = $options['roles'] ?? $allowedRoles;
        $options['per_page'] = 36;
        $options['page'] = $_GET['page'] ?? 1;
        $result = User::paginated($role, $_GET['q'] ?? null, $status, $options);
        $users = $result['items'];
        $verifiedDocuments = IdentityDocument::verifiedForUsers(array_column($users, 'id'));
        foreach ($users as &$user) {
            $user['identity_verified'] = !empty($verifiedDocuments[(int) $user['id']]);
        }
        unset($user);
        $this->render('users/index', [
            'title' => 'مدیریت کاربران',
            'users' => $users,
            'pagination' => $result,
            'roles' => $allowedRoles,
            'departments' => app_config('departments', []),
            'canManageUsers' => Auth::role() === 'admin',
            'profileRequests' => Auth::role() === 'admin' ? ProfileRequest::pending() : [],
            'identityRequests' => [],
            'socialLinks' => configured_social_links(Settings::allKeyed()),
        ], is_ajax_request() ? null : 'app');
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
                'department' => array_key_exists($_POST['department'] ?? '', app_config('departments', [])) ? $_POST['department'] : null,
                'is_department_manager' => !empty($_POST['is_department_manager']) ? 1 : 0,
                'avatar_key' => normalize_avatar_key($_POST['avatar_key'] ?? 'avatar-1'),
            ]);
            set_flash('success', 'کاربر با موفقیت ثبت شد.');
        } catch (Throwable $e) {
            set_flash('error', $e instanceof InvalidArgumentException ? $e->getMessage() : 'ثبت کاربر انجام نشد. داده‌های تکراری را بررسی کنید.');
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
            $role = in_array($_POST['role'] ?? '', ['admin', 'operator', 'lawyer'], true) ? $_POST['role'] : $user['role'];
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
                'department' => array_key_exists($_POST['department'] ?? '', app_config('departments', [])) ? $_POST['department'] : null,
                'is_department_manager' => !empty($_POST['is_department_manager']) ? 1 : 0,
                'avatar_key' => normalize_avatar_key($_POST['avatar_key'] ?? 'avatar-1'),
            ]);
            set_flash('success', 'اطلاعات کاربر به‌روزرسانی شد.');
        } catch (Throwable $e) {
            set_flash('error', $e instanceof InvalidArgumentException ? $e->getMessage() : 'ویرایش کاربر انجام نشد.');
        }
        redirect('users');
    }

    public function delete($id)
    {
        $this->requireRole('admin');
        $this->onlyPost();
        if (!ConfirmationCode::verify('user_delete_' . (int) $id, $_POST['confirm_text'] ?? '')) {
            set_flash('error', 'عدد تأیید حذف کاربر درست وارد نشده است.');
            redirect('users');
        }
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

    public function search()
    {
        Auth::requireLogin();
        $query = trim((string) ($_GET['q'] ?? ''));
        $roles = array_filter(array_map('trim', explode(',', (string) ($_GET['roles'] ?? ''))));
        if (Auth::role() === 'customer') {
            $items = array_map(function ($unit) {
                return [
                    'id' => (int) $unit['id'],
                    'full_name' => $unit['full_name'],
                    'mobile' => '',
                    'secondary_phone' => '',
                    'national_id' => '',
                    'role' => $unit['role'] ?? '',
                    'role_label' => department_label($unit['department'] ?? ''),
                    'department' => $unit['department'] ?? '',
                    'department_label' => department_label($unit['department'] ?? ''),
                    'status' => 'active',
                ];
            }, Chat::customerUnits(Auth::id()));
            $this->json(['ok' => true, 'items' => $items]);
        }
        $items = array_map(function ($user) {
            return [
                'id' => (int) $user['id'],
                'full_name' => $user['full_name'] ?? '',
                'mobile' => $user['mobile'] ?? '',
                'secondary_phone' => $user['secondary_phone'] ?? '',
                'national_id' => $user['national_id'] ?? '',
                'role' => $user['role'] ?? '',
                'role_label' => role_label($user['role'] ?? ''),
                'department' => $user['department'] ?? '',
                'department_label' => department_label($user['department'] ?? ''),
                'status' => $user['status'] ?? '',
            ];
        }, User::searchUsers($query, $roles, 12));
        $this->json(['ok' => true, 'items' => $items]);
    }

    public function medalStore($id)
    {
        $this->requireRole('admin');
        $this->onlyPost();
        redirect('customers');
    }

    public function medalDelete($id)
    {
        $this->requireRole('admin');
        $this->onlyPost();
        redirect('customers');
    }
}
