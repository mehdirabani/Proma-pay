<?php

class NotificationsController extends Controller
{
    public function index()
    {
        Auth::requireLogin();
        $webPushEnabled = WebPushService::enabled();
        $webPushPublicKey = '';
        $webPushSubscribed = false;
        if ($webPushEnabled) {
            try { $webPushPublicKey = WebPushService::publicKey(); } catch (Throwable $e) {}
            try { $webPushSubscribed = WebPushService::hasSubscription((int) Auth::id()); } catch (Throwable $e) {}
        }
        $this->render('notifications/index', [
            'title' => 'اعلان‌های من',
            'notifications' => Notification::forUser(Auth::id(), 100),
            'unreadCount' => Notification::unreadCount(Auth::id()),
            'broadcastAnnouncements' => Auth::role() === 'admin' ? SystemAnnouncement::recent(25) : [],
            'broadcastRoles' => Auth::role() === 'admin' ? Model::fetchAll("SELECT DISTINCT role FROM users WHERE status='active' AND role IS NOT NULL ORDER BY role") : [],
            'webPushEnabled' => $webPushEnabled,
            'webPushPublicKey' => $webPushPublicKey,
            'webPushSubscribed' => $webPushSubscribed,
        ]);
    }

    public function subscribe()
    {
        Auth::requireLogin();
        $this->onlyPost();
        if (!WebPushService::enabled() || !WebPushService::keysReady()) {
            $this->json(['ok' => false, 'message' => 'اعلان مرورگر در تنظیمات سامانه فعال نیست.'], 409);
        }
        try {
            WebPushService::saveSubscription(
                (int) Auth::id(),
                (string) ($_POST['endpoint'] ?? ''),
                (string) ($_POST['p256dh'] ?? ''),
                (string) ($_POST['auth'] ?? ''),
                (string) ($_SERVER['HTTP_USER_AGENT'] ?? '')
            );
            $this->json(['ok' => true, 'message' => 'اعلان این دستگاه فعال شد.']);
        } catch (InvalidArgumentException $e) {
            $this->json(['ok' => false, 'message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            if (class_exists('PluginRegistry')) PluginRegistry::logRuntimeError('web_push.subscribe', $e);
            $this->json(['ok' => false, 'message' => 'ثبت دستگاه برای دریافت اعلان انجام نشد.'], 500);
        }
    }

    public function unsubscribe()
    {
        Auth::requireLogin();
        $this->onlyPost();
        $affected = WebPushService::removeSubscription((int) Auth::id(), (string) ($_POST['endpoint'] ?? ''));
        $this->json(['ok' => true, 'removed' => $affected > 0, 'message' => 'اعلان این دستگاه غیرفعال شد.']);
    }

    public function sendBroadcast()
    {
        $this->requireRole('admin');
        $this->onlyPost();
        try {
            $audience = (string) ($_POST['audience'] ?? 'all');
            $roles = $audience === 'roles' && is_array($_POST['roles'] ?? null) ? $_POST['roles'] : [];
            if (!in_array($audience, ['all', 'roles'], true) || ($audience === 'roles' && !$roles)) throw new InvalidArgumentException('برای ارسال نقش‌محور دست‌کم یک نقش را انتخاب کنید.');
            $expires = trim((string) ($_POST['expires_at'] ?? ''));
            $expiresAt = null;
            if ($expires !== '') {
                $time = strtotime($expires);
                if ($time === false || $time <= time()) throw new InvalidArgumentException('زمان انقضای اعلان باید در آینده باشد.');
                $expiresAt = date('Y-m-d H:i:s', $time);
            }
            $id = SystemAnnouncement::create((string) ($_POST['title'] ?? ''), (string) ($_POST['body'] ?? ''), $roles, (string) ($_POST['local_url'] ?? ''), (int) Auth::id(), $expiresAt);
            $announcement = Model::fetch('SELECT COUNT(*) AS total FROM system_announcement_recipients WHERE announcement_id = ?', [$id]);
            set_flash('success', 'اعلان سراسری برای ' . to_persian_digits((int) ($announcement['total'] ?? 0)) . ' کاربر ارسال شد.');
        } catch (Throwable $e) {
            set_flash('error', $e->getMessage());
        }
        redirect('notifications');
    }

    public function feed()
    {
        Auth::requireLogin();
        $this->json([
            'ok' => true,
            'feed' => Notification::feed(Auth::id(), 6),
        ]);
    }

    public function read()
    {
        Auth::requireLogin();
        $this->onlyPost();
        Notification::markAllRead(Auth::id());
        if (strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest') {
            $this->json(['ok' => true, 'feed' => Notification::feed(Auth::id(), 6)]);
        }
        set_flash('success', 'اعلان‌ها خوانده شد.');
        redirect('notifications');
    }

    public function open($id)
    {
        Auth::requireLogin();
        $item = Notification::findForUser((int) $id, Auth::id());
        if (!$item) {
            ErrorHandler::abort(404);
        }
        Notification::markRead((int) $id, Auth::id(), true);
        $target = trim((string) ($item['url'] ?? ''));
        if ($target !== '' && strpos($target, app_base_url() . '/index.php') === 0) {
            redirect_raw($target);
        }
        redirect('notifications');
    }

    public function archive($id)
    {
        Auth::requireLogin();
        $this->onlyPost();
        Notification::archive((int) $id, Auth::id());
        set_flash('success', 'اعلان بایگانی شد.');
        redirect('notifications');
    }

    public function delete($id)
    {
        Auth::requireLogin();
        $this->onlyPost();
        Notification::deleteForUser((int) $id, Auth::id());
        set_flash('success', 'اعلان از فهرست شما حذف شد.');
        redirect('notifications');
    }

    public function bulkDelete()
    {
        Auth::requireLogin();
        $this->onlyPost();
        $ids = $_POST['notification_ids'] ?? [];
        if (!is_array($ids)) {
            $ids = [];
        }
        $affected = Notification::deleteManyForUser($ids, Auth::id());
        set_flash($affected > 0 ? 'success' : 'warning', $affected > 0
            ? to_persian_digits($affected) . ' اعلان از فهرست شما حذف شد.'
            : 'اعلانی برای حذف انتخاب نشده بود.');
        redirect('notifications');
    }
}
