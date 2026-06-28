<?php

class NotificationsController extends Controller
{
    public function read()
    {
        Auth::requireLogin();
        $this->onlyPost();
        Notification::markAllRead(Auth::id());
        set_flash('success', 'اعلان‌ها خوانده شد.');
        redirect('dashboard');
    }
}
