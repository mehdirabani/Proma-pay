<?php

class ProfileController extends Controller
{
    public function index()
    {
        Auth::requireLogin();
        User::ensureProfileColumns();
        $this->render('profile/index', [
            'title' => 'پروفایل من',
            'user' => User::find(Auth::id()),
            'latestRequest' => ProfileRequest::latestForUser(Auth::id()),
            'avatars' => ['avatar-1', 'avatar-2', 'avatar-3', 'avatar-4', 'avatar-5', 'avatar-6'],
        ]);
    }

    public function update()
    {
        Auth::requireLogin();
        $this->onlyPost();
        $payload = [
            'full_name' => trim($_POST['full_name'] ?? ''),
            'mobile' => to_english_digits($_POST['mobile'] ?? ''),
            'secondary_phone' => to_english_digits($_POST['secondary_phone'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'address' => trim($_POST['address'] ?? ''),
            'avatar_key' => in_array($_POST['avatar_key'] ?? '', ['avatar-1', 'avatar-2', 'avatar-3', 'avatar-4', 'avatar-5', 'avatar-6'], true) ? $_POST['avatar_key'] : null,
            'password' => $_POST['password'] ?? '',
        ];
        if ($payload['full_name'] === '' || $payload['mobile'] === '') {
            set_flash('error', 'نام و موبایل الزامی است.');
            redirect('profile');
        }
        if ($payload['password'] !== '' && mb_strlen($payload['password'], 'UTF-8') < 8) {
            set_flash('error', 'رمز عبور تازه باید حداقل هشت کاراکتر باشد.');
            redirect('profile');
        }
        if (Auth::role() === 'admin') {
            User::applyProfileData(Auth::id(), $payload);
            set_flash('success', 'پروفایل به‌روزرسانی شد.');
        } else {
            ProfileRequest::createRequest(Auth::id(), $payload);
            set_flash('success', 'درخواست ویرایش پروفایل برای تایید مدیریت ثبت شد.');
        }
        redirect('profile');
    }

    public function approve($id)
    {
        $this->requireRole('admin');
        $this->onlyPost();
        ProfileRequest::approve((int) $id, Auth::id());
        set_flash('success', 'درخواست پروفایل تایید شد.');
        redirect('users');
    }

    public function reject($id)
    {
        $this->requireRole('admin');
        $this->onlyPost();
        ProfileRequest::reject((int) $id, Auth::id(), $_POST['review_notes'] ?? '');
        set_flash('success', 'درخواست پروفایل رد شد.');
        redirect('users');
    }
}
