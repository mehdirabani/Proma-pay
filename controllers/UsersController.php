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
        $availableRoles = array_keys(PluginManager::availableRoles());
        $assignableRoles = array_keys(PluginManager::assignableRoles());
        // Customer accounts belong to Customers, not the internal user directory.
        $allowedRoles = array_values(array_diff($assignableRoles, ['customer']));
        if (Auth::role() === 'admin') {
            foreach (Model::fetchAll('SELECT DISTINCT role FROM users WHERE role IS NOT NULL') as $assigned) {
                $assignedRole = (string) ($assigned['role'] ?? '');
                if ($assignedRole !== '' && $assignedRole !== 'customer' && !in_array($assignedRole, $allowedRoles, true)) {
                    $allowedRoles[] = $assignedRole;
                }
            }
        }
        $role = in_array($role, $allowedRoles, true) ? $role : null;
        $status = in_array($status, ['active', 'inactive'], true) ? $status : null;
        $options = ['roles' => $allowedRoles];
        if (Auth::role() !== 'admin') {
            $options['department'] = $currentUser['department'] ?? '';
            $options['roles'] = $allowedRoles;
            if ($role === 'customer') {
                $role = null;
            }
        } elseif (isset($_GET['department']) && $_GET['department'] !== '') {
            $options['department'] = $_GET['department'];
        }
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
            'activeRoles' => $availableRoles,
            'creatableRoles' => array_values(array_filter($assignableRoles, static fn ($candidate) => $candidate !== 'customer')),
            'departments' => app_config('departments', []),
            'canManageUsers' => Auth::role() === 'admin',
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
            ErrorHandler::abort(422, implode(' ', $validator->errors()));
        }
        if (!PluginManager::roleAssignable($_POST['role'] ?? '') || ($_POST['role'] ?? '') === 'customer') {
            ErrorHandler::abort(422, 'نقش انتخاب‌شده فعال یا معتبر نیست.');
        }
        try {
            User::create([
                'role' => $_POST['role'],
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
            if ($e instanceof InvalidArgumentException) {
                ErrorHandler::abort(422, $e->getMessage());
            }
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
            ErrorHandler::abort(422, implode(' ', $validator->errors()));
        }
        $requestedRole = (string) ($_POST['role'] ?? '');
        if ($requestedRole !== $user['role'] && !PluginManager::roleAssignable($requestedRole)) {
            ErrorHandler::abort(422, 'نقش انتخاب‌شده فعال یا معتبر نیست.');
        }
        try {
            $role = $requestedRole !== '' ? $requestedRole : $user['role'];
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
            if ($e instanceof InvalidArgumentException) {
                ErrorHandler::abort(422, $e->getMessage());
            }
            set_flash('error', 'ویرایش کاربر انجام نشد.');
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
