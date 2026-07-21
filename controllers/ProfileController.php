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
        ];
        if ($payload['full_name'] === '' || $payload['mobile'] === '') {
            set_flash('error', 'نام و موبایل الزامی است.');
            redirect('profile');
        }
        if (Auth::role() === 'admin') {
            User::applyProfileData(Auth::id(), $payload);
            set_flash('success', 'پروفایل به‌روزرسانی شد.');
        } else {
            if (ProfileRequest::createRequest(Auth::id(), $payload)) {
                set_flash('success', 'درخواست ویرایش مشخصات برای تایید مدیریت ثبت شد.');
            } else {
                set_flash('info', 'تغییری در مشخصات قابل بررسی ثبت نشد.');
            }
        }
        redirect('profile');
    }

    public function updateAvatar()
    {
        Auth::requireLogin();
        $this->onlyPost();
        try {
            User::updateAvatar(Auth::id(), $_POST['avatar_key'] ?? '');
            set_flash('success', 'آواتار شما بدون نیاز به تایید مدیریت تغییر کرد.');
        } catch (Throwable $e) {
            set_flash('error', $e instanceof InvalidArgumentException ? $e->getMessage() : 'تغییر آواتار انجام نشد.');
        }
        redirect('profile');
    }

    public function uploadAvatar()
    {
        Auth::requireLogin();
        $this->onlyPost();
        try {
            $path = UploadHelper::storeAvatar($_FILES['avatar'] ?? [], Auth::id());
            if (!$path) {
                throw new InvalidArgumentException('یک تصویر برای آواتار انتخاب کنید.');
            }
            User::updateUploadedAvatar(Auth::id(), $path);
            set_flash('success', 'تصویر پروفایل شما بلافاصله تغییر کرد.');
        } catch (Throwable $e) {
            set_flash('error', $e instanceof InvalidArgumentException ? $e->getMessage() : 'بارگذاری آواتار انجام نشد.');
        }
        redirect('profile');
    }

    public function removeAvatar()
    {
        Auth::requireLogin();
        $this->onlyPost();
        try {
            User::removeUploadedAvatar(Auth::id());
            set_flash('success', 'تصویر بارگذاری‌شده حذف شد و آواتار پیش‌فرض نمایش داده می‌شود.');
        } catch (Throwable $e) {
            set_flash('error', $e instanceof InvalidArgumentException ? $e->getMessage() : 'حذف آواتار انجام نشد.');
        }
        redirect('profile');
    }

    public function avatarFile($id)
    {
        Auth::requireLogin();
        $user = User::find((int) $id);
        $path = $user ? UploadHelper::absolutePath($user['avatar_path'] ?? '') : null;
        if (!$path) {
            ErrorHandler::abort(404);
        }
        $mime = function_exists('mime_content_type') ? (mime_content_type($path) ?: 'application/octet-stream') : 'application/octet-stream';
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            ErrorHandler::abort(404);
        }
        $etag = '"' . hash_file('sha256', $path) . '"';
        if (trim((string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag) {
            http_response_code(304);
            exit;
        }
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($path));
        header('Content-Disposition: inline; filename="avatar.' . pathinfo($path, PATHINFO_EXTENSION) . '"');
        header('Cache-Control: private, max-age=86400');
        header('ETag: ' . $etag);
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        exit;
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
        $result = ProfileRequest::approveFields((int) $id, Auth::id(), (array) ($_POST['approved_fields'] ?? []), $_POST['review_notes'] ?? '');
        if (!empty($result['ok'])) {
            set_flash('success', ($result['status'] ?? '') === 'partial' ? 'فیلدهای انتخاب‌شده تایید و سایر فیلدها رد شدند.' : (($result['status'] ?? '') === 'approved' ? 'درخواست اصلاح مشخصات تایید و اعمال شد.' : 'درخواست اصلاح مشخصات رد شد.'));
        } else {
            set_flash('error', 'درخواست در انتظار بررسی پیدا نشد.');
        }
        redirect('profile-reviews');
    }

    public function reject($id)
    {
        $this->requireRole('admin');
        $this->onlyPost();
        if (ProfileRequest::reject((int) $id, Auth::id(), $_POST['review_notes'] ?? '')) {
            set_flash('success', 'درخواست اصلاح مشخصات رد شد.');
        } else {
            set_flash('error', 'درخواست در انتظار بررسی پیدا نشد.');
        }
        redirect('profile-reviews');
    }

    public function respond($id)
    {
        Auth::requireLogin();
        $this->onlyPost();
        try {
            if (!ProfileRequest::respond((int) $id, Auth::id(), $_POST['customer_response'] ?? '')) {
                throw new InvalidArgumentException('درخواست قابل پاسخ‌گویی پیدا نشد.');
            }
            set_flash('success', 'پاسخ شما برای مدیریت ثبت شد.');
        } catch (Throwable $e) {
            set_flash('error', $e instanceof InvalidArgumentException ? $e->getMessage() : 'ثبت پاسخ انجام نشد.');
        }
        redirect('profile');
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
