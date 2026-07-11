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
            'identityDocuments' => IdentityDocument::forUser(Auth::id()),
            'identityVerified' => IdentityDocument::isVerified(Auth::id()),
            'avatars' => avatar_options(),
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
            'avatar_key' => normalize_avatar_key($_POST['avatar_key'] ?? 'avatar-1'),
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

    public function uploadIdentity()
    {
        Auth::requireLogin();
        $this->onlyPost();
        try {
            $note = trim((string) ($_POST['identity_note'] ?? ''));
            $uploaded = 0;
            foreach (IdentityDocument::types() as $type => $label) {
                if (empty($_FILES[$type]) || (int) ($_FILES[$type]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                    continue;
                }
                $path = UploadHelper::storeImage($_FILES[$type], 'identity/' . Auth::id());
                if ($path) {
                    IdentityDocument::createPending(Auth::id(), $type, $path, $note);
                    $uploaded++;
                }
            }
            if ($uploaded === 0) {
                throw new InvalidArgumentException('حداقل یک تصویر مدرک هویتی انتخاب کنید.');
            }
            set_flash('success', 'مدارک هویتی برای بررسی مدیریت ثبت شد.');
        } catch (Throwable $e) {
            set_flash('error', $e->getMessage());
        }
        redirect('profile');
    }

    public function identityFile($id)
    {
        Auth::requireLogin();
        $document = IdentityDocument::find((int) $id);
        if (!$document || ($document['status'] === 'rejected' && empty($document['file_path']))) {
            http_response_code(404);
            echo 'فایل پیدا نشد.';
            return;
        }
        if (Auth::role() !== 'admin' && (int) $document['user_id'] !== (int) Auth::id()) {
            http_response_code(403);
            echo 'دسترسی غیرمجاز';
            return;
        }
        $path = UploadHelper::absolutePath($document['file_path']);
        if (!$path) {
            http_response_code(404);
            echo 'فایل پیدا نشد.';
            return;
        }
        $mime = function_exists('mime_content_type') ? (mime_content_type($path) ?: 'application/octet-stream') : 'application/octet-stream';
        header('Content-Type: ' . $mime);
        header('Content-Disposition: inline; filename="' . basename($path) . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
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

    public function identityApprove($id)
    {
        $this->requireRole('admin');
        $this->onlyPost();
        try {
            IdentityDocument::approve((int) $id, Auth::id(), $_POST['review_note'] ?? '');
            set_flash('success', 'مدرک هویتی تأیید شد.');
        } catch (Throwable $e) {
            set_flash('error', $e->getMessage());
        }
        redirect('review', ['tab' => 'identity']);
    }

    public function identityReject($id)
    {
        $this->requireRole('admin');
        $this->onlyPost();
        try {
            IdentityDocument::reject((int) $id, Auth::id(), $_POST['review_note'] ?? '');
            set_flash('success', 'مدرک هویتی رد شد.');
        } catch (Throwable $e) {
            set_flash('error', $e->getMessage());
        }
        redirect('review', ['tab' => 'identity']);
    }
}
