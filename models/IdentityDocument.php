<?php

class IdentityDocument extends Model
{
    protected static $schemaReady = false;

    public static function ensureSchema()
    {
        if (self::$schemaReady) {
            return;
        }
        self::execute(
            "CREATE TABLE IF NOT EXISTS identity_documents (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT UNSIGNED NOT NULL,
                document_type VARCHAR(40) NOT NULL,
                file_path VARCHAR(255) NOT NULL,
                status VARCHAR(30) NOT NULL DEFAULT 'pending',
                reviewed_by BIGINT UNSIGNED NULL,
                review_note TEXT NULL,
                uploaded_at DATETIME NOT NULL,
                reviewed_at DATETIME NULL,
                INDEX idx_identity_documents_user (user_id),
                INDEX idx_identity_documents_status (status),
                CONSTRAINT fk_identity_documents_user
                    FOREIGN KEY (user_id) REFERENCES users(id)
                    ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        self::$schemaReady = true;
    }

    public static function types()
    {
        return [
            'national_card' => 'تصویر کارت ملی',
            'birth_certificate' => 'تصویر شناسنامه',
        ];
    }

    public static function typeLabel($type)
    {
        $types = self::types();
        return $types[$type] ?? 'مدرک هویتی';
    }

    public static function createPending($userId, $type, $filePath, $note = '')
    {
        self::ensureSchema();
        if (!array_key_exists($type, self::types())) {
            throw new InvalidArgumentException('نوع مدرک معتبر نیست.');
        }
        self::execute(
            'INSERT INTO identity_documents (user_id, document_type, file_path, status, review_note, uploaded_at)
             VALUES (?, ?, ?, ?, ?, NOW())',
            [(int) $userId, $type, $filePath, 'pending', trim((string) $note) ?: null]
        );
        $documentId = (int) self::lastInsertId();
        if (class_exists('FileRecord')) {
            try {
                FileRecord::relatePath($filePath, 'identity_document', $documentId, 'identity_document', (int) $userId);
                FileRecord::relatePath($filePath, 'user', (int) $userId, 'identity_document', (int) $userId);
            } catch (Throwable $e) {
                ErrorHandler::log('identity_document_file_relation', $e, 500);
            }
        }
        Notification::create((int) $userId, 'مدرک هویتی دریافت شد', 'مدرک هویتی شما ثبت شد و در صف بررسی قرار گرفت.', 'identity', url('profile'));
        foreach (User::all('admin', null, 'active') as $admin) {
            Notification::create(
                (int) $admin['id'],
                'مدرک هویتی جدید',
                'یک مدرک هویتی برای بررسی مدیریت ارسال شد.',
                'identity',
                url('review', ['tab' => 'identity'])
            );
        }
    }

    public static function forUser($userId)
    {
        self::ensureSchema();
        return self::fetchAll(
            'SELECT d.*, r.full_name AS reviewer_name
             FROM identity_documents d
             LEFT JOIN users r ON r.id = d.reviewed_by
             WHERE d.user_id = ?
             ORDER BY d.id DESC',
            [(int) $userId]
        );
    }

    public static function pending()
    {
        self::ensureSchema();
        return self::fetchAll(
            "SELECT d.*, u.full_name, u.mobile, u.role
             FROM identity_documents d
             JOIN users u ON u.id = d.user_id
             WHERE d.status = 'pending'
            ORDER BY d.id DESC"
        );
    }

    public static function pendingCount()
    {
        self::ensureSchema();
        $row = self::fetch("SELECT COUNT(*) AS total FROM identity_documents WHERE status = 'pending'");
        return (int) ($row['total'] ?? 0);
    }

    public static function find($id)
    {
        self::ensureSchema();
        return self::fetch('SELECT * FROM identity_documents WHERE id = ?', [(int) $id]);
    }

    public static function approve($id, $adminId, $note = '')
    {
        self::ensureSchema();
        $document = self::find($id);
        if (!$document || $document['status'] !== 'pending') {
            throw new InvalidArgumentException('مدرک قابل تأیید نیست.');
        }
        self::begin();
        try {
            $oldDocs = self::fetchAll(
                "SELECT * FROM identity_documents
                 WHERE user_id = ? AND document_type = ? AND status = 'approved' AND id != ?",
                [(int) $document['user_id'], $document['document_type'], (int) $id]
            );
            self::execute(
                "UPDATE identity_documents
                 SET status = 'approved', reviewed_by = ?, review_note = ?, reviewed_at = NOW()
                 WHERE id = ?",
                [(int) $adminId, trim((string) $note) ?: null, (int) $id]
            );
            foreach ($oldDocs as $old) {
                UploadHelper::deleteRelative($old['file_path']);
                self::execute('DELETE FROM identity_documents WHERE id = ?', [(int) $old['id']]);
            }
            Notification::create((int) $document['user_id'], 'مدرک هویتی تأیید شد', 'مدرک «' . self::typeLabel($document['document_type']) . '» تأیید شد.', 'identity', url('profile'));
            self::commit();
            try {
                Chat::botMessage((int) $document['user_id'], 'مدرک هویتی شما تایید شد.', url('profile'));
            } catch (Throwable $ignored) {
            }
        } catch (Throwable $e) {
            self::rollBack();
            throw $e;
        }
    }

    public static function reject($id, $adminId, $note = '')
    {
        self::ensureSchema();
        $document = self::find($id);
        if (!$document || $document['status'] !== 'pending') {
            throw new InvalidArgumentException('مدرک قابل رد نیست.');
        }
        UploadHelper::deleteRelative($document['file_path']);
        self::execute(
            "UPDATE identity_documents
             SET status = 'rejected', file_path = '', reviewed_by = ?, review_note = ?, reviewed_at = NOW()
             WHERE id = ?",
            [(int) $adminId, trim((string) $note) ?: null, (int) $id]
        );
        Notification::create((int) $document['user_id'], 'مدرک هویتی رد شد', 'مدرک «' . self::typeLabel($document['document_type']) . '» تأیید نشد.', 'identity', url('profile'));
        try {
            Chat::botMessage((int) $document['user_id'], 'مدرک هویتی شما تایید نشد. لطفا از بخش پروفایل بررسی کنید.', url('profile'));
        } catch (Throwable $ignored) {
        }
    }

    public static function verifiedForUsers(array $userIds)
    {
        self::ensureSchema();
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds))));
        if (!$userIds) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $rows = self::fetchAll(
            "SELECT user_id, COUNT(DISTINCT document_type) AS approved_count
             FROM identity_documents
             WHERE status = 'approved' AND document_type IN ('national_card', 'birth_certificate') AND user_id IN ({$placeholders})
             GROUP BY user_id",
            $userIds
        );
        $verified = [];
        foreach ($rows as $row) {
            $verified[(int) $row['user_id']] = (int) $row['approved_count'] >= 2;
        }
        return $verified;
    }

    public static function isVerified($userId)
    {
        $map = self::verifiedForUsers([(int) $userId]);
        return !empty($map[(int) $userId]);
    }
}
