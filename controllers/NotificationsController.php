<?php

class NotificationsController extends Controller
{
    public function index()
    {
        Auth::requireLogin();
        $this->render('notifications/index', [
            'title' => 'اعلان‌های من',
            'notifications' => Notification::forUser(Auth::id(), 100),
            'unreadCount' => Notification::unreadCount(Auth::id()),
        ]);
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
}
