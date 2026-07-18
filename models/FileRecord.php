<?php

class FileRecord extends Model
{
    public const MAX_FILE_SIZE = 10485760;

    protected static $available = null;

    public static function isAvailable()
    {
        if (self::$available !== null) {
            return self::$available;
        }
        try {
            self::$available = (bool) self::fetch("SHOW TABLES LIKE 'files'");
        } catch (Throwable $e) {
            self::$available = false;
            ErrorHandler::log('file_registry_schema_check', $e, 500);
        }
        return self::$available;
    }

    public static function registerStored($relativePath, array $upload = [], array $context = [])
    {
        if (!self::isAvailable()) {
            return null;
        }

        $relativePath = self::normalizeStoragePath($relativePath);
        $absolutePath = self::absoluteStoragePath($relativePath);
        if ($relativePath === '' || !$absolutePath || !is_file($absolutePath)) {
            return null;
        }

        $existing = self::fetch('SELECT id FROM files WHERE storage_path = ? LIMIT 1', [$relativePath]);
        if ($existing) {
            return (int) $existing['id'];
        }

        $uploaderId = isset($context['uploader_user_id']) ? (int) $context['uploader_user_id'] : self::currentUserId();
        $uploaderRole = trim((string) ($context['uploader_role'] ?? self::currentUserRole()));
        $originalName = self::safeDisplayName($upload['name'] ?? basename($relativePath));
        $requestedDisplayName = trim((string) ($context['display_name'] ?? ''));
        $displayName = self::safeDisplayName($requestedDisplayName !== '' ? $requestedDisplayName : pathinfo($originalName, PATHINFO_FILENAME));
        $extension = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION));
        $metadata = array_merge([
            'source' => $context['source'] ?? 'upload',
            'original_name' => $originalName,
        ], is_array($context['metadata'] ?? null) ? $context['metadata'] : []);
        $visibility = self::visibility($context['visibility'] ?? (strpos($relativePath, 'storage/uploads/') === 0 ? 'public' : 'private'));
        $category = self::category($context['category'] ?? self::categoryFromPath($relativePath));

        self::execute(
            'INSERT INTO files
             (file_uuid, original_name, stored_name, display_name, extension, mime_type, size_bytes, storage_disk, storage_path,
              checksum_sha256, category, status, visibility, uploader_user_id, uploader_role, related_entity_type, related_entity_id,
              parent_file_id, version_number, description, tags_json, metadata_json, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())',
            [
                self::newUuid(),
                $originalName,
                basename($relativePath),
                $displayName,
                $extension,
                self::detectMime($absolutePath),
                (int) filesize($absolutePath),
                strpos($relativePath, 'storage/secure_uploads/') === 0 ? 'private' : 'public',
                $relativePath,
                hash_file('sha256', $absolutePath),
                $category,
                'active',
                $visibility,
                $uploaderId ?: null,
                $uploaderRole ?: null,
                self::entityType($context['related_entity_type'] ?? null),
                !empty($context['related_entity_id']) ? (int) $context['related_entity_id'] : null,
                !empty($context['parent_file_id']) ? (int) $context['parent_file_id'] : null,
                max(1, (int) ($context['version_number'] ?? 1)),
                self::safeDescription($context['description'] ?? ''),
                self::jsonValue($context['tags'] ?? []),
                self::jsonValue($metadata),
            ]
        );
        $fileId = (int) self::lastInsertId();
        if (!empty($context['related_entity_type']) && !empty($context['related_entity_id'])) {
            self::addRelation($fileId, $context['related_entity_type'], (int) $context['related_entity_id'], $context['relation_type'] ?? 'attachment', $uploaderId);
        }
        self::audit($fileId, 'registered', $uploaderId, ['storage_disk' => strpos($relativePath, 'storage/secure_uploads/') === 0 ? 'private' : 'public', 'category' => $category]);
        return $fileId;
    }

    public static function storeManagedUpload(array $upload, array $context = [])
    {
        self::validateManagedUpload($upload);
        $extension = strtolower(pathinfo((string) $upload['name'], PATHINFO_EXTENSION));
        $directory = self::secureUploadDirectory('file-manager');
        $relativePath = 'storage/secure_uploads/file-manager/' . date('Y') . '/' . date('m') . '/' . bin2hex(random_bytes(16)) . '.' . $extension;
        $destination = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        if (!move_uploaded_file($upload['tmp_name'], $destination)) {
            throw new RuntimeException('ذخیره فایل در فضای امن انجام نشد.');
        }
        $context['category'] = $context['category'] ?? 'general';
        $context['visibility'] = $context['visibility'] ?? 'private';
        $context['source'] = $context['source'] ?? 'file_manager';
        $fileId = self::registerStored($relativePath, $upload, $context);
        if (!$fileId) {
            throw new RuntimeException('ثبت فایل در فهرست مرکزی انجام نشد. ابتدا migration مدیریت فایل را نصب کنید.');
        }
        return self::find($fileId);
    }

    public static function find($id)
    {
        if (!self::isAvailable()) {
            return null;
        }
        return self::fetch(
            'SELECT f.*, u.full_name AS uploader_name
             FROM files f
             LEFT JOIN users u ON u.id = f.uploader_user_id
             WHERE f.id = ? LIMIT 1',
            [(int) $id]
        );
    }

    public static function findByUuid($uuid)
    {
        if (!self::isAvailable() || !preg_match('/^[a-f0-9]{32}$/i', (string) $uuid)) {
            return null;
        }
        return self::fetch(
            'SELECT f.*, u.full_name AS uploader_name
             FROM files f
             LEFT JOIN users u ON u.id = f.uploader_user_id
             WHERE f.file_uuid = ? LIMIT 1',
            [(string) $uuid]
        );
    }

    public static function page(array $filters = [])
    {
        if (!self::isAvailable()) {
            return ['items' => [], 'total' => 0, 'page' => 1, 'pages' => 1, 'per_page' => 25];
        }
        $where = ['1 = 1'];
        $params = [];
        $query = trim((string) ($filters['q'] ?? ''));
        if ($query !== '') {
            $where[] = '(f.display_name LIKE ? OR f.original_name LIKE ? OR f.extension LIKE ?)';
            $needle = '%' . $query . '%';
            array_push($params, $needle, $needle, $needle);
        }
        $category = trim((string) ($filters['category'] ?? ''));
        if ($category !== '') {
            $where[] = 'f.category = ?';
            $params[] = $category;
        }
        $status = trim((string) ($filters['status'] ?? ''));
        if (in_array($status, ['active', 'archived', 'deleted'], true)) {
            $where[] = 'f.status = ?';
            $params[] = $status;
        } else {
            $where[] = "f.status != 'deleted'";
        }
        $visibility = trim((string) ($filters['visibility'] ?? ''));
        if (in_array($visibility, ['private', 'internal', 'public'], true)) {
            $where[] = 'f.visibility = ?';
            $params[] = $visibility;
        }
        $uploaderId = (int) ($filters['uploader_user_id'] ?? 0);
        if ($uploaderId > 0) {
            $where[] = 'f.uploader_user_id = ?';
            $params[] = $uploaderId;
        }
        $perPage = max(10, min(100, (int) ($filters['per_page'] ?? 25)));
        $page = max(1, (int) ($filters['page'] ?? 1));
        $whereSql = implode(' AND ', $where);
        $total = (int) (self::fetch('SELECT COUNT(*) AS total FROM files f WHERE ' . $whereSql, $params)['total'] ?? 0);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($page, $pages);
        $offset = ($page - 1) * $perPage;
        $items = self::fetchAll(
            'SELECT f.*, u.full_name AS uploader_name,
                    (SELECT COUNT(*) FROM file_relations fr WHERE fr.file_id = f.id) AS relation_count
             FROM files f
             LEFT JOIN users u ON u.id = f.uploader_user_id
             WHERE ' . $whereSql . '
             ORDER BY f.created_at DESC, f.id DESC
             LIMIT ' . $perPage . ' OFFSET ' . $offset,
            $params
        );
        return compact('items', 'total', 'page', 'pages', 'perPage') + ['per_page' => $perPage];
    }

    public static function stats()
    {
        if (!self::isAvailable()) {
            return ['total' => 0, 'total_size' => 0, 'active' => 0, 'archived' => 0, 'deleted' => 0, 'recent' => 0];
        }
        return self::fetch(
            "SELECT COUNT(*) AS total, COALESCE(SUM(size_bytes), 0) AS total_size,
                    SUM(status = 'active') AS active, SUM(status = 'archived') AS archived,
                    SUM(status = 'deleted') AS deleted,
                    SUM(created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) AS recent
             FROM files"
        ) ?: ['total' => 0, 'total_size' => 0, 'active' => 0, 'archived' => 0, 'deleted' => 0, 'recent' => 0];
    }

    public static function categories()
    {
        if (!self::isAvailable()) {
            return [];
        }
        return self::fetchAll("SELECT category, COUNT(*) AS total FROM files GROUP BY category ORDER BY category ASC");
    }

    public static function managedExtensions()
    {
        return array_keys(self::allowedManagedExtensions());
    }

    public static function updateMetadata($uuid, array $data, $actorId)
    {
        $file = self::findByUuid($uuid);
        if (!$file) {
            throw new InvalidArgumentException('فایل انتخاب‌شده پیدا نشد.');
        }
        $oldValues = ['display_name' => $file['display_name'], 'category' => $file['category'], 'visibility' => $file['visibility']];
        self::execute(
            'UPDATE files SET display_name = ?, category = ?, description = ?, tags_json = ?, visibility = ?, updated_at = NOW() WHERE id = ?',
            [
                self::safeDisplayName($data['display_name'] ?? $file['display_name']),
                self::category($data['category'] ?? $file['category']),
                self::safeDescription($data['description'] ?? ''),
                self::jsonValue(self::tags($data['tags'] ?? '')),
                self::visibility($data['visibility'] ?? $file['visibility']),
                (int) $file['id'],
            ]
        );
        self::audit((int) $file['id'], 'metadata_updated', $actorId, ['old' => $oldValues]);
        return self::find((int) $file['id']);
    }

    public static function archive($uuid, $actorId, $reason = '')
    {
        return self::setStatus($uuid, 'archived', $actorId, $reason);
    }

    public static function restore($uuid, $actorId, $reason = '')
    {
        return self::setStatus($uuid, 'active', $actorId, $reason);
    }

    public static function softDelete($uuid, $actorId, $reason)
    {
        $reason = trim((string) $reason);
        if ($reason === '') {
            throw new InvalidArgumentException('علت حذف فایل الزامی است.');
        }
        return self::setStatus($uuid, 'deleted', $actorId, $reason);
    }

    public static function archiveByPath($relativePath, $reason = 'بایگانی در چرخه عملیاتی سیستم')
    {
        if (!self::isAvailable()) {
            return false;
        }
        $file = self::fetch('SELECT file_uuid FROM files WHERE storage_path = ? LIMIT 1', [self::normalizeStoragePath($relativePath)]);
        if (!$file) {
            return false;
        }
        try {
            self::archive((string) $file['file_uuid'], self::currentUserId(), $reason);
            return true;
        } catch (Throwable $e) {
            ErrorHandler::log('file_registry_archive_path', $e, 500);
            return false;
        }
    }

    public static function replace($uuid, array $upload, $actorId, $reason = '')
    {
        $current = self::findByUuid($uuid);
        if (!$current) {
            throw new InvalidArgumentException('نسخه اصلی فایل پیدا نشد.');
        }
        $new = self::storeManagedUpload($upload, [
            'uploader_user_id' => (int) $actorId,
            'uploader_role' => self::roleForUser($actorId),
            'display_name' => $current['display_name'],
            'category' => $current['category'],
            'visibility' => $current['visibility'],
            'description' => $current['description'],
            'tags' => json_decode((string) $current['tags_json'], true) ?: [],
            'parent_file_id' => (int) $current['id'],
            'version_number' => (int) $current['version_number'] + 1,
            'source' => 'file_replacement',
            'metadata' => ['replacement_reason' => self::safeDescription($reason)],
        ]);
        $relations = self::relations((int) $current['id']);
        foreach ($relations as $relation) {
            self::addRelation((int) $new['id'], $relation['entity_type'], (int) $relation['entity_id'], $relation['relation_type'], (int) $actorId);
        }
        self::setStatus($uuid, 'archived', $actorId, trim((string) $reason) ?: 'جایگزینی با نسخه جدید');
        self::audit((int) $new['id'], 'replaced_previous_version', $actorId, ['previous_file_id' => (int) $current['id']]);
        return $new;
    }

    public static function addRelation($fileId, $entityType, $entityId, $relationType = 'attachment', $actorId = null)
    {
        $entityType = self::entityType($entityType);
        $relationType = self::entityType($relationType);
        if (!$entityType || (int) $entityId <= 0) {
            throw new InvalidArgumentException('اطلاعات ارتباط فایل معتبر نیست.');
        }
        $exists = self::fetch(
            'SELECT id FROM file_relations WHERE file_id = ? AND entity_type = ? AND entity_id = ? AND relation_type = ? LIMIT 1',
            [(int) $fileId, $entityType, (int) $entityId, $relationType ?: 'attachment']
        );
        if (!$exists) {
            self::execute(
                'INSERT INTO file_relations (file_id, entity_type, entity_id, relation_type, created_by, created_at) VALUES (?, ?, ?, ?, ?, NOW())',
                [(int) $fileId, $entityType, (int) $entityId, $relationType ?: 'attachment', $actorId ? (int) $actorId : null]
            );
        }
    }

    public static function relatePath($relativePath, $entityType, $entityId, $relationType = 'attachment', $actorId = null)
    {
        if (!self::isAvailable()) {
            return false;
        }
        $file = self::fetch('SELECT id FROM files WHERE storage_path = ? LIMIT 1', [self::normalizeStoragePath($relativePath)]);
        if (!$file) {
            return false;
        }
        self::addRelation((int) $file['id'], $entityType, $entityId, $relationType, $actorId);
        return true;
    }

    public static function relations($fileId)
    {
        if (!self::isAvailable()) {
            return [];
        }
        return self::fetchAll('SELECT * FROM file_relations WHERE file_id = ? ORDER BY id ASC', [(int) $fileId]);
    }

    public static function backfillStorage($limit = 200)
    {
        if (!self::isAvailable()) {
            return 0;
        }
        $root = dirname(__DIR__);
        $storageRoot = $root . DIRECTORY_SEPARATOR . 'storage';
        if (!is_dir($storageRoot)) {
            return 0;
        }
        $registered = 0;
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($storageRoot, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $item) {
            if ($registered >= max(1, (int) $limit) || !$item->isFile() || $item->getFilename() === '.htaccess') {
                continue;
            }
            $fullPath = $item->getPathname();
            $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($fullPath, strlen($root) + 1));
            if (strpos($relative, 'storage/secure_uploads/') !== 0 && strpos($relative, 'storage/uploads/') !== 0) {
                continue;
            }
            if (self::fetch('SELECT id FROM files WHERE storage_path = ? LIMIT 1', [$relative])) {
                continue;
            }
            self::registerStored($relative, ['name' => $item->getFilename()], [
                'category' => self::categoryFromPath($relative),
                'source' => 'storage_backfill',
                'metadata' => ['legacy_file' => true],
            ]);
            $registered++;
        }
        return $registered;
    }

    public static function absoluteStoragePath($relativePath)
    {
        $relativePath = self::normalizeStoragePath($relativePath);
        if ($relativePath === '') {
            return null;
        }
        $root = realpath(dirname(__DIR__));
        if (!$root) {
            return null;
        }
        $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        $base = strpos($relativePath, 'storage/secure_uploads/') === 0
            ? $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'secure_uploads'
            : $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'uploads';
        $realBase = realpath($base);
        $realDirectory = realpath(dirname($path));
        if (!$realBase || !$realDirectory || strpos($realDirectory, $realBase) !== 0) {
            return null;
        }
        return is_file($path) ? $path : null;
    }

    protected static function setStatus($uuid, $status, $actorId, $reason = '')
    {
        $file = self::findByUuid($uuid);
        if (!$file) {
            throw new InvalidArgumentException('فایل انتخاب‌شده پیدا نشد.');
        }
        $status = in_array($status, ['active', 'archived', 'deleted'], true) ? $status : 'archived';
        if ($file['status'] === $status) {
            return $file;
        }
        self::execute(
            'UPDATE files SET status = ?, archived_at = ?, deleted_at = ?, deleted_by = ?, deletion_reason = ?, updated_at = NOW() WHERE id = ?',
            [
                $status,
                $status === 'archived' ? date('Y-m-d H:i:s') : null,
                $status === 'deleted' ? date('Y-m-d H:i:s') : null,
                $status === 'deleted' ? (int) $actorId : null,
                $status === 'deleted' ? self::safeDescription($reason) : null,
                (int) $file['id'],
            ]
        );
        self::audit((int) $file['id'], $status, $actorId, ['reason' => self::safeDescription($reason), 'previous_status' => $file['status']]);
        return self::find((int) $file['id']);
    }

    protected static function validateManagedUpload(array $upload)
    {
        if (empty($upload['tmp_name']) || (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            throw new InvalidArgumentException('فایل را انتخاب کنید.');
        }
        if ((int) ($upload['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK || !is_uploaded_file($upload['tmp_name'])) {
            throw new InvalidArgumentException('فایل به‌درستی بارگذاری نشد.');
        }
        if ((int) ($upload['size'] ?? 0) < 1 || (int) ($upload['size'] ?? 0) > self::MAX_FILE_SIZE) {
            throw new InvalidArgumentException('حجم فایل باید حداکثر ۱۰ مگابایت باشد.');
        }
        $name = (string) ($upload['name'] ?? '');
        if (strpos($name, "\0") !== false || preg_match('/\.(php\d*|phtml|phar|cgi|pl|sh|exe|dll|htaccess)$/i', $name)) {
            throw new InvalidArgumentException('این نوع فایل به‌دلیل ملاحظات امنیتی مجاز نیست.');
        }
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $allowed = self::allowedManagedExtensions();
        if (!array_key_exists($extension, $allowed)) {
            throw new InvalidArgumentException('فرمت فایل مجاز نیست.');
        }
        $mime = self::detectMime($upload['tmp_name']);
        if (!in_array($mime, $allowed[$extension], true)) {
            throw new InvalidArgumentException('نوع واقعی فایل با پسوند آن هم‌خوانی ندارد.');
        }
        if ($extension === 'zip') {
            self::inspectZip($upload['tmp_name']);
        }
    }

    protected static function inspectZip($path)
    {
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException('برای بررسی فایل ZIP، افزونه ZipArchive باید فعال باشد.');
        }
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new InvalidArgumentException('فایل ZIP معتبر نیست.');
        }
        try {
            if ($zip->numFiles > 1000) {
                throw new InvalidArgumentException('فایل ZIP بیش از حد فایل داخلی دارد.');
            }
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $entry = (string) $zip->getNameIndex($index);
                if (strpos($entry, '../') !== false || strpos($entry, '/..') !== false || preg_match('/\.(php\d*|phtml|phar|cgi|pl|sh|exe|dll)$/i', $entry)) {
                    throw new InvalidArgumentException('محتوای فایل ZIP مجاز نیست.');
                }
            }
        } finally {
            $zip->close();
        }
    }

    protected static function allowedManagedExtensions()
    {
        return [
            'jpg' => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png' => ['image/png'],
            'webp' => ['image/webp'],
            'gif' => ['image/gif'],
            'pdf' => ['application/pdf'],
            'doc' => ['application/msword', 'application/octet-stream'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'],
            'xls' => ['application/vnd.ms-excel', 'application/octet-stream'],
            'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream'],
            'csv' => ['text/plain', 'text/csv', 'application/vnd.ms-excel'],
            'txt' => ['text/plain'],
            'zip' => ['application/zip', 'application/x-zip-compressed'],
            'rar' => ['application/vnd.rar', 'application/x-rar-compressed', 'application/octet-stream'],
        ];
    }

    protected static function secureUploadDirectory($subdir)
    {
        $directory = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'secure_uploads' . DIRECTORY_SEPARATOR . $subdir . DIRECTORY_SEPARATOR . date('Y') . DIRECTORY_SEPARATOR . date('m');
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new RuntimeException('ساخت پوشه امن فایل امکان‌پذیر نیست.');
        }
        return $directory;
    }

    protected static function normalizeStoragePath($path)
    {
        $path = str_replace('\\', '/', trim((string) $path));
        $path = ltrim($path, '/');
        if (strpos($path, '..') !== false || (strpos($path, 'storage/secure_uploads/') !== 0 && strpos($path, 'storage/uploads/') !== 0)) {
            return '';
        }
        return $path;
    }

    protected static function detectMime($path)
    {
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $mime = finfo_file($finfo, $path);
                finfo_close($finfo);
                if ($mime) {
                    return $mime;
                }
            }
        }
        return function_exists('mime_content_type') ? (mime_content_type($path) ?: 'application/octet-stream') : 'application/octet-stream';
    }

    protected static function safeDisplayName($value)
    {
        $value = trim(preg_replace('/[\x00-\x1F<>:"\\|?*]+/u', ' ', (string) $value));
        $value = preg_replace('/\s+/u', ' ', $value);
        return mb_substr($value ?: 'file', 0, 190, 'UTF-8');
    }

    protected static function safeDescription($value)
    {
        return mb_substr(trim(strip_tags((string) $value)), 0, 2000, 'UTF-8') ?: null;
    }

    protected static function tags($value)
    {
        if (is_array($value)) {
            $tags = $value;
        } else {
            $tags = preg_split('/[,،]/u', (string) $value) ?: [];
        }
        $tags = array_map(function ($tag) {
            return mb_substr(trim(strip_tags((string) $tag)), 0, 40, 'UTF-8');
        }, $tags);
        return array_values(array_unique(array_filter($tags)));
    }

    protected static function category($value)
    {
        $value = preg_replace('/[^a-z0-9_-]/i', '_', trim((string) $value));
        return mb_substr($value ?: 'general', 0, 50, 'UTF-8');
    }

    protected static function categoryFromPath($path)
    {
        $parts = explode('/', self::normalizeStoragePath($path));
        $index = array_search('secure_uploads', $parts, true);
        if ($index === false) {
            $index = array_search('uploads', $parts, true);
        }
        return $index !== false && !empty($parts[$index + 1]) ? self::category($parts[$index + 1]) : 'general';
    }

    protected static function visibility($value)
    {
        return in_array($value, ['private', 'internal', 'public'], true) ? $value : 'private';
    }

    protected static function entityType($value)
    {
        $value = preg_replace('/[^a-z0-9_-]/i', '_', trim((string) $value));
        return $value === '' ? null : mb_substr($value, 0, 60, 'UTF-8');
    }

    protected static function jsonValue($value)
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    protected static function currentUserId()
    {
        return class_exists('Auth') && Auth::check() ? (int) Auth::id() : null;
    }

    protected static function currentUserRole()
    {
        return class_exists('Auth') && Auth::check() ? (string) Auth::role() : null;
    }

    protected static function roleForUser($userId)
    {
        $user = User::find((int) $userId);
        return $user['role'] ?? null;
    }

    protected static function newUuid()
    {
        return bin2hex(random_bytes(16));
    }

    protected static function audit($fileId, $action, $actorId, array $data = [])
    {
        try {
            self::execute(
                'INSERT INTO file_audit_logs (file_id, action, actor_user_id, details_json, created_at) VALUES (?, ?, ?, ?, NOW())',
                [(int) $fileId, trim((string) $action), $actorId ? (int) $actorId : null, self::jsonValue($data)]
            );
            if (class_exists('AuditLog')) {
                AuditLog::record('file', $action, 'file', (int) $fileId, ['actor_user_id' => $actorId, 'new_values' => $data]);
            }
        } catch (Throwable $e) {
            ErrorHandler::log('file_registry_audit', $e, 500);
        }
    }
}
