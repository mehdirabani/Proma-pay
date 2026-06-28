<?php

class ChatController extends Controller
{
    public function index()
    {
        Auth::requireLogin();
        $contacts = Chat::contactsFor(Auth::id());
        $contactId = (int) ($_GET['contact'] ?? ($contacts[0]['id'] ?? 0));
        if ($contactId) {
            Chat::markRead(Auth::id(), $contactId);
        }
        $this->render('chat/index', [
            'title' => 'گفت‌وگو',
            'contacts' => $contacts,
            'contactId' => $contactId,
            'messages' => $contactId ? Chat::messages(Auth::id(), $contactId) : [],
        ]);
    }

    public function send()
    {
        Auth::requireLogin();
        $this->onlyPost();
        $body = trim($_POST['body'] ?? '');
        if ($body === '') {
            $this->json(['ok' => false, 'message' => 'متن پیام خالی است.'], 422);
        }
        try {
            $id = Chat::send(Auth::id(), (int) $_POST['receiver_id'], $body);
            $this->json(['ok' => true, 'id' => $id]);
        } catch (Throwable $e) {
            $this->json(['ok' => false, 'message' => 'ارسال پیام مجاز نیست.'], 403);
        }
    }

    public function fetch()
    {
        Auth::requireLogin();
        $contactId = (int) ($_GET['contact'] ?? 0);
        $after = (int) ($_GET['after'] ?? 0);
        if ($contactId) {
            Chat::markRead(Auth::id(), $contactId);
        }
        $this->json(['ok' => true, 'messages' => Chat::messages(Auth::id(), $contactId, $after), 'unread' => Chat::unreadCount(Auth::id())]);
    }
}
