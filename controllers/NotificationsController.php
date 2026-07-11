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
}
