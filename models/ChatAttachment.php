<?php

class ChatAttachment extends Model
{
    protected static $schemaReady = false;
    protected static $cleanupRan = false;

    public static function ensureSchema()
    {
        if (self::$schemaReady) {
            return;
        }
        self::fetch('SELECT id FROM chat_attachments LIMIT 1');
        self::$schemaReady = true;
        self::cleanupExpired();
    }

    public static function create($messageId, $filePath, $fileType = 'image')
    {
        self::ensureSchema();
        self::execute(
            'INSERT INTO chat_attachments (message_id, file_path, file_type, status, created_at) VALUES (?, ?, ?, ?, NOW())',
            [(int) $messageId, $filePath, $fileType, 'pending']
        );
        $attachmentId = (int) self::lastInsertId();
        if (class_exists('FileRecord')) {
            try {
                FileRecord::relatePath($filePath, 'chat_attachment', $attachmentId, 'chat_attachment');
                FileRecord::relatePath($filePath, 'chat_message', (int) $messageId, 'chat_attachment');
            } catch (Throwable $e) {
                ErrorHandler::log('chat_attachment_file_relation', $e, 500);
            }
        }
    }

    public static function pending()
    {
        self::ensureSchema();
        return self::fetchAll(
            "SELECT a.*, m.body, m.sender_id, m.receiver_id, m.channel_id,
             s.full_name AS sender_name, COALESCE(r.full_name, ch.title, 'کانال عمومی') AS receiver_name
             FROM chat_attachments a
             JOIN messages m ON m.id = a.message_id
             JOIN users s ON s.id = m.sender_id
             LEFT JOIN users r ON r.id = m.receiver_id
             LEFT JOIN chat_channels ch ON ch.id = m.channel_id
             WHERE a.status = 'pending'
             ORDER BY a.id DESC"
        );
    }

    public static function find($id)
    {
        self::ensureSchema();
        return self::fetch(
            "SELECT a.*, m.sender_id, m.receiver_id, m.channel_id
             FROM chat_attachments a
             JOIN messages m ON m.id = a.message_id
             WHERE a.id = ?",
            [(int) $id]
        );
    }

    public static function approve($id, $adminId, $note = '')
    {
        self::review($id, $adminId, 'approved', $note);
    }

    public static function reject($id, $adminId, $note = '')
    {
        self::review($id, $adminId, 'rejected', $note);
    }

    protected static function review($id, $adminId, $status, $note = '')
    {
        self::ensureSchema();
        $attachment = self::find($id);
        if (!$attachment || $attachment['status'] !== 'pending') {
            throw new InvalidArgumentException('پیوست قابل بررسی نیست.');
        }
        UploadHelper::deleteRelative($attachment['file_path']);
        self::execute(
            'UPDATE chat_attachments
             SET status = ?, file_path = "", reviewed_by = ?, review_note = ?, reviewed_at = NOW(), deleted_at = NOW()
             WHERE id = ?',
            [$status, (int) $adminId, trim((string) $note) ?: null, (int) $id]
        );
    }

    public static function cleanupExpired($days = null)
    {
        if (self::$cleanupRan) {
            return 0;
        }
        self::$cleanupRan = true;
        self::ensureSchema();
        $days = $days === null ? (int) Settings::get('chat_file_auto_delete_days', '7') : (int) $days;
        $days = max(1, min(365, $days));
        $rows = self::fetchAll(
            'SELECT id, file_path FROM chat_attachments
             WHERE file_path IS NOT NULL AND file_path != "" AND created_at < DATE_SUB(NOW(), INTERVAL ? DAY)',
            [$days]
        );
        foreach ($rows as $row) {
            UploadHelper::deleteRelative($row['file_path']);
            self::execute(
                'UPDATE chat_attachments SET file_path = "", deleted_at = COALESCE(deleted_at, NOW()) WHERE id = ?',
                [(int) $row['id']]
            );
        }
        return count($rows);
    }
}
