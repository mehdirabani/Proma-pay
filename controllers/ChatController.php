<?php

class ChatController extends Controller
{
    public function index()
    {
        Auth::requireLogin();
        $contacts = Chat::contactsFor(Auth::id());
        $channelSlug = trim((string) ($_GET['channel'] ?? ''));
        $contactId = isset($_GET['contact']) ? (int) $_GET['contact'] : 0;
        $selectedChannel = null;

        if ($channelSlug !== '') {
            $selectedChannel = Chat::channelBySlug($channelSlug);
            if (!$selectedChannel) {
                $channelSlug = '';
            }
        }
        if (!$selectedChannel && !$contactId && $contacts) {
            $firstContact = $contacts[0];
            if (($firstContact['kind'] ?? '') === 'channel') {
                $channelSlug = $firstContact['slug'];
                $selectedChannel = Chat::channelBySlug($channelSlug);
            } else {
                $contactId = (int) $firstContact['id'];
            }
        }
        if (!$selectedChannel && $contactId && !Chat::allowed(Auth::id(), $contactId)) {
            $contactId = 0;
            foreach ($contacts as $contact) {
                if (($contact['kind'] ?? '') === 'channel') {
                    $channelSlug = $contact['slug'];
                    $selectedChannel = Chat::channelBySlug($channelSlug);
                    break;
                }
                if (Chat::allowed(Auth::id(), (int) $contact['id'])) {
                    $contactId = (int) $contact['id'];
                    break;
                }
            }
        }
        if ($contactId) {
            Chat::markRead(Auth::id(), $contactId);
        }
        $selectedContact = null;
        if (!$selectedChannel && $contactId) {
            foreach ($contacts as $contact) {
                if (($contact['kind'] ?? '') !== 'channel' && (int) $contact['id'] === (int) $contactId) {
                    $selectedContact = $contact;
                    break;
                }
            }
            if (!$selectedContact && Chat::isBotUser($contactId)) {
                $selectedContact = Chat::botContact(Auth::id());
            }
        }
        $messages = $selectedChannel
            ? Chat::channelMessages((int) $selectedChannel['id'])
            : ($contactId ? Chat::messages(Auth::id(), $contactId) : []);
        $this->render('chat/index', [
            'title' => 'گفت‌وگو',
            'contacts' => $contacts,
            'contactId' => $contactId,
            'selectedContact' => $selectedContact,
            'selectedChannel' => $selectedChannel,
            'channelSlug' => $channelSlug,
            'canSendChannel' => $selectedChannel ? Chat::canSendToChannel(Auth::id(), $selectedChannel) : false,
            'messages' => $messages,
            'pendingAttachments' => Auth::role() === 'admin' ? ChatAttachment::pending() : [],
        ]);
    }

    public function send()
    {
        Auth::requireLogin();
        $this->onlyPost();
        $body = trim($_POST['body'] ?? '');
        $attachmentPath = null;
        try {
            if (!empty($_FILES['attachment']) && (int) ($_FILES['attachment']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $attachmentPath = UploadHelper::storeImage($_FILES['attachment'], 'chat/' . Auth::id());
            }
        } catch (Throwable $e) {
            $this->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
        if ($body === '' && !$attachmentPath) {
            $this->json(['ok' => false, 'message' => 'متن پیام یا تصویر را وارد کنید.'], 422);
        }
        try {
            $channelId = (int) ($_POST['channel_id'] ?? 0);
            if ($channelId > 0) {
                $id = Chat::sendToChannel(Auth::id(), $channelId, $body, $attachmentPath);
            } else {
                $id = Chat::send(Auth::id(), (int) ($_POST['receiver_id'] ?? 0), $body, $attachmentPath);
            }
            $this->json(['ok' => true, 'id' => $id]);
        } catch (Throwable $e) {
            if ($attachmentPath) {
                UploadHelper::deleteRelative($attachmentPath);
            }
            $this->json(['ok' => false, 'message' => 'ارسال پیام مجاز نیست.'], 403);
        }
    }

    public function fetch()
    {
        Auth::requireLogin();
        $channelSlug = trim((string) ($_GET['channel'] ?? ''));
        $contactId = (int) ($_GET['contact'] ?? 0);
        $after = (int) ($_GET['after'] ?? 0);
        if ($channelSlug !== '') {
            $channel = Chat::channelBySlug($channelSlug);
            if (!$channel) {
                $this->json(['ok' => false, 'message' => 'کانال پیدا نشد.'], 404);
            }
            $this->json([
                'ok' => true,
                'messages' => Chat::channelMessages((int) $channel['id'], $after),
                'unread' => Chat::unreadCount(Auth::id()),
            ]);
        }
        if ($contactId && !Chat::allowed(Auth::id(), $contactId)) {
            $this->json(['ok' => false, 'message' => 'دسترسی به این گفت‌وگو مجاز نیست.'], 403);
        }
        if ($contactId) {
            Chat::markRead(Auth::id(), $contactId);
        }
        if (!$contactId) {
            $this->json(['ok' => true, 'messages' => [], 'unread' => Chat::unreadCount(Auth::id())]);
        }
        $this->json(['ok' => true, 'messages' => Chat::messages(Auth::id(), $contactId, $after), 'unread' => Chat::unreadCount(Auth::id())]);
    }

    public function attachment($id)
    {
        Auth::requireLogin();
        $attachment = ChatAttachment::find((int) $id);
        if (!$attachment || empty($attachment['file_path'])) {
            http_response_code(404);
            echo 'فایل بررسی و حذف شد';
            return;
        }
        $userId = (int) Auth::id();
        $channelId = (int) ($attachment['channel_id'] ?? 0);
        if ($channelId > 0) {
            $channel = Chat::channelById($channelId);
            if (!$channel || !Chat::canViewChannel($userId, $channel)) {
                http_response_code(403);
                echo 'دسترسی غیرمجاز';
                return;
            }
        } elseif (Auth::role() !== 'admin' && $userId !== (int) $attachment['sender_id'] && $userId !== (int) $attachment['receiver_id']) {
            http_response_code(403);
            echo 'دسترسی غیرمجاز';
            return;
        }
        $path = UploadHelper::absolutePath($attachment['file_path']);
        if (!$path) {
            http_response_code(404);
            echo 'فایل بررسی و حذف شد';
            return;
        }
        $mime = function_exists('mime_content_type') ? (mime_content_type($path) ?: 'application/octet-stream') : 'application/octet-stream';
        header('Content-Type: ' . $mime);
        header('Content-Disposition: inline; filename="' . basename($path) . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }

    public function approveAttachment($id)
    {
        $this->requireRole('admin');
        $this->onlyPost();
        try {
            ChatAttachment::approve((int) $id, Auth::id(), $_POST['review_note'] ?? '');
            set_flash('success', 'پیوست چت تأیید و فایل از سرور حذف شد.');
        } catch (Throwable $e) {
            set_flash('error', $e->getMessage());
        }
        redirect('chat');
    }

    public function rejectAttachment($id)
    {
        $this->requireRole('admin');
        $this->onlyPost();
        try {
            ChatAttachment::reject((int) $id, Auth::id(), $_POST['review_note'] ?? '');
            set_flash('success', 'پیوست چت رد و فایل از سرور حذف شد.');
        } catch (Throwable $e) {
            set_flash('error', $e->getMessage());
        }
        redirect('chat');
    }
}
