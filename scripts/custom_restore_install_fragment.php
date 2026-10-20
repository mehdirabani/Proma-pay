<?php

// This fragment is injected into the user-specific custom installer only.
$customRestorePayloadPath = __DIR__ . '/storage/custom-install/restore-payload.zip';
if (is_file($customRestorePayloadPath)) {
    $message = '';
    if (is_post()) {
        $db = [
            'host' => trim($_POST['host'] ?? 'localhost'),
            'database' => trim($_POST['database'] ?? ''),
            'username' => trim($_POST['username'] ?? ''),
            'password' => (string) ($_POST['password'] ?? ''),
            'charset' => trim($_POST['charset'] ?? 'utf8mb4'),
        ];
        try {
            $pdo = installer_pdo($db);
            ensure_installer_writable();
            $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
            if ($tables) {
                throw new RuntimeException('Custom restore requires a new empty database.');
            }
            custom_restore_embedded_backup($pdo, $customRestorePayloadPath);
            write_database_config($db);
            if (file_put_contents(__DIR__ . '/installed.lock', 'installed=' . date('c') . PHP_EOL) === false) {
                throw new RuntimeException('Cannot write installed.lock after custom restore.');
            }
            @unlink($customRestorePayloadPath);
            @unlink(__FILE__);
            echo installer_page('نصب و بازیابی کامل شد', '<div class="notice success">اطلاعات بکاپ بازیابی شد. کاربران، تنظیمات و فایل‌های قبلی حفظ شده‌اند.</div><a class="btn" href="index.php?route=auth/login">ورود به سامانه</a>');
            exit;
        } catch (Throwable $e) {
            installer_log_error($e);
            $message = '<div class="notice error">نصب یا بازیابی انجام نشد. اتصال دیتابیس، خالی‌بودن آن و دسترسی نوشتن فایل‌ها را بررسی کنید. علت در storage/logs/install.log ثبت شده است.</div>';
        }
    }
    echo installer_page('اتصال دیتابیس و بازیابی بکاپ', $message . '
        <div class="notice">این بسته اطلاعات بکاپ را پس از اتصال به یک پایگاه دادهٔ خالی بازیابی می‌کند. حساب‌های قبلی حفظ می‌شوند و مدیر جدید ساخته نمی‌شود.</div>
        <form method="post" class="install-form">
            <label>میزبان پایگاه داده<input name="host" value="localhost" required dir="ltr"></label>
            <label>نام پایگاه داده<input name="database" required dir="ltr"></label>
            <label>نام کاربری پایگاه داده<input name="username" required dir="ltr"></label>
            <label>رمز عبور پایگاه داده<input name="password" type="password" dir="ltr"></label>
            <label>کدبندی<input name="charset" value="utf8mb4" required dir="ltr"></label>
            <button class="btn" type="submit">اتصال، نصب و بازیابی خودکار</button>
        </form>');
    exit;
}

function custom_restore_embedded_backup(PDO $pdo, $payloadPath)
{
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('ZipArchive extension is required for the custom restore.');
    }
    @set_time_limit(0);
    $zip = new ZipArchive();
    if ($zip->open($payloadPath, ZipArchive::CHECKCONS) !== true) {
        throw new RuntimeException('Embedded restore payload is not a valid ZIP archive.');
    }
    try {
        $manifestRaw = $zip->getFromName('restore-manifest.json');
        $manifest = is_string($manifestRaw) ? json_decode($manifestRaw, true) : null;
        if (!is_array($manifest) || ($manifest['app'] ?? '') !== 'Proma Pay' || ($manifest['source_version'] ?? '') !== '2.2.10') {
            throw new RuntimeException('Embedded restore manifest is invalid or does not match the supplied backup.');
        }
        $sqlBytes = (int) ($manifest['database_size'] ?? 0);
        if ($sqlBytes <= 0 || $sqlBytes > 64 * 1024 * 1024 || empty($manifest['database_sha256'])) {
            throw new RuntimeException('Embedded database dump size or checksum is invalid.');
        }
        $sql = $zip->getFromName('database.sql', $sqlBytes + 1);
        if (!is_string($sql) || strlen($sql) !== $sqlBytes || !hash_equals((string) $manifest['database_sha256'], hash('sha256', $sql))) {
            throw new RuntimeException('Embedded database dump checksum validation failed.');
        }
        custom_restore_validate_backup_sql($sql);

        $expectedFiles = [];
        foreach (($manifest['files'] ?? []) as $file) {
            if (!is_array($file) || empty($file['path']) || empty($file['sha256'])) {
                throw new RuntimeException('Embedded file inventory is invalid.');
            }
            $path = custom_restore_safe_path((string) $file['path']);
            if (!custom_restore_allowed_path($path)) {
                throw new RuntimeException('Embedded backup contains a file outside the restore allowlist.');
            }
            $expectedFiles[$path] = strtolower((string) $file['sha256']);
        }
        if (count($expectedFiles) !== count($manifest['files'] ?? [])) {
            throw new RuntimeException('Embedded file inventory contains duplicate paths.');
        }
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            if ($name === 'restore-manifest.json' || $name === 'database.sql' || substr($name, -1) === '/') {
                continue;
            }
            $name = custom_restore_safe_path($name);
            if (!isset($expectedFiles[$name])) {
                throw new RuntimeException('Embedded restore payload has an unlisted file.');
            }
        }
        foreach ($expectedFiles as $path => $expectedHash) {
            $content = $zip->getFromName($path);
            if (!is_string($content) || !hash_equals($expectedHash, hash('sha256', $content))) {
                throw new RuntimeException('Embedded user file checksum validation failed: ' . $path);
            }
        }

        custom_restore_copy_files($zip, $expectedFiles);
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        try {
            foreach (custom_restore_split_sql($sql) as $statement) {
                custom_restore_execute_statement($pdo, $statement);
            }
        } finally {
            $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        }

        custom_restore_apply_release_migrations($pdo);
    } finally {
        $zip->close();
    }
}

function custom_restore_validate_backup_sql($sql)
{
    $statements = [];
    $createdTables = [];
    foreach (custom_restore_split_sql($sql) as $statement) {
        $statement = trim($statement);
        if (preg_match('/^SET\s+FOREIGN_KEY_CHECKS\s*=\s*[01]$/i', $statement)) {
            $statements[] = ['type' => 'set', 'table' => null];
        } elseif (preg_match('/^DROP\s+TABLE\s+IF\s+EXISTS\s+`((?:``|[^`])+)`$/i', $statement, $m)) {
            $statements[] = ['type' => 'drop', 'table' => str_replace('``', '`', $m[1])];
        } elseif (preg_match('/^CREATE\s+TABLE\s+`((?:``|[^`])+)`\s*\(/i', $statement, $m)) {
            $table = str_replace('``', '`', $m[1]);
            $createdTables[$table] = true;
            $statements[] = ['type' => 'create', 'table' => $table];
        } elseif (preg_match('/^INSERT\s+INTO\s+`((?:``|[^`])+)`\s*\(/i', $statement, $m)) {
            $statements[] = ['type' => 'insert', 'table' => str_replace('``', '`', $m[1])];
        } else {
            throw new RuntimeException('Database dump contains an unsupported SQL statement.');
        }
    }
    foreach ($statements as $statement) {
        if ($statement['table'] !== null && !isset($createdTables[$statement['table']])) {
            throw new RuntimeException('Database dump references a table without defining it.');
        }
    }
    foreach (['users', 'contracts', 'installments'] as $requiredTable) {
        if (!isset($createdTables[$requiredTable])) {
            throw new RuntimeException('Database dump is missing a required Proma Pay table.');
        }
    }
}

function custom_restore_execute_statement(PDO $pdo, $statement)
{
    $result = $pdo->query((string) $statement);
    if (!$result instanceof PDOStatement) {
        return;
    }
    try {
        do {
            if ($result->columnCount() > 0) {
                $result->fetchAll(PDO::FETCH_ASSOC);
            }
            try {
                $hasMoreRows = $result->nextRowset();
            } catch (Throwable $e) {
                $hasMoreRows = false;
            }
        } while ($hasMoreRows);
    } finally {
        $result->closeCursor();
    }
}

function custom_restore_split_sql($sql)
{
    $sql = preg_replace('/^\xEF\xBB\xBF/', '', (string) $sql);
    $buffer = '';
    $quote = null;
    $length = strlen($sql);
    for ($i = 0; $i < $length; $i++) {
        $char = $sql[$i];
        $next = $i + 1 < $length ? $sql[$i + 1] : '';
        $afterNext = $i + 2 < $length ? $sql[$i + 2] : '';
        if ($quote !== null) {
            $buffer .= $char;
            if ($quote !== '`' && $char === '\\' && $next !== '') {
                $buffer .= $next;
                $i++;
                continue;
            }
            if ($char === $quote) {
                if ($next === $quote) {
                    $buffer .= $next;
                    $i++;
                    continue;
                }
                $quote = null;
            }
            continue;
        }
        if ($char === '-' && $next === '-' && ($afterNext === '' || preg_match('/\s/', $afterNext))) {
            while ($i < $length && $sql[$i] !== "\n") {
                $i++;
            }
            $buffer .= "\n";
            continue;
        }
        if ($char === '#') {
            while ($i < $length && $sql[$i] !== "\n") {
                $i++;
            }
            $buffer .= "\n";
            continue;
        }
        if ($char === '/' && $next === '*') {
            $i += 2;
            while ($i + 1 < $length && !($sql[$i] === '*' && $sql[$i + 1] === '/')) {
                $i++;
            }
            $i++;
            $buffer .= ' ';
            continue;
        }
        if ($char === "'" || $char === '"' || $char === '`') {
            $quote = $char;
            $buffer .= $char;
            continue;
        }
        if ($char === ';') {
            $statement = trim($buffer);
            if ($statement !== '') {
                yield $statement;
            }
            $buffer = '';
            continue;
        }
        $buffer .= $char;
    }
    $tail = trim($buffer);
    if ($tail !== '') {
        yield $tail;
    }
}

function custom_restore_apply_release_migrations(PDO $pdo)
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS migrations (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        migration_name VARCHAR(191) NOT NULL,
        batch INT UNSIGNED NOT NULL DEFAULT 1,
        source_type VARCHAR(40) NOT NULL DEFAULT 'core',
        source_id VARCHAR(191) NULL,
        status VARCHAR(40) NOT NULL DEFAULT 'success',
        started_at DATETIME NULL,
        finished_at DATETIME NULL,
        executed_by BIGINT UNSIGNED NULL,
        error_message TEXT NULL,
        UNIQUE KEY uniq_migrations_migration_name (migration_name),
        KEY idx_migrations_status (status),
        KEY idx_migrations_started_at (started_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $names = [
        '2026_10_03_contract_guarantee_amount.sql',
        '2026_10_05_contract_sales_attribution.sql',
    ];
    foreach ($names as $name) {
        $path = __DIR__ . '/database/migrations/' . $name;
        $sql = is_file($path) ? file_get_contents($path) : false;
        if (!is_string($sql) || trim($sql) === '') {
            throw new RuntimeException('Required version migration is missing: ' . $name);
        }
        foreach (custom_restore_split_sql($sql) as $statement) {
            custom_restore_execute_statement($pdo, $statement);
        }
        $check = $pdo->prepare('SELECT id FROM migrations WHERE migration_name = ? LIMIT 1');
        $check->execute(['database/migrations/' . $name]);
        $id = (int) ($check->fetchColumn() ?: 0);
        $check->closeCursor();
        if ($id > 0) {
            $update = $pdo->prepare("UPDATE migrations SET status = 'success', finished_at = NOW(), error_message = NULL WHERE id = ?");
            $update->execute([$id]);
        } else {
            $insert = $pdo->prepare("INSERT INTO migrations (migration_name, batch, source_type, source_id, status, started_at, finished_at) VALUES (?, 1, 'update', ?, 'success', NOW(), NOW())");
            $insert->execute(['database/migrations/' . $name, 'database/migrations/' . $name]);
        }
    }
}

function custom_restore_safe_path($path)
{
    $path = str_replace('\\', '/', trim((string) $path));
    if ($path === '' || strpos($path, "\0") !== false || substr($path, 0, 1) === '/' || preg_match('/^[a-z]:/i', $path)) {
        throw new RuntimeException('Embedded backup path is invalid.');
    }
    foreach (explode('/', $path) as $part) {
        if ($part === '' || $part === '.' || $part === '..') {
            throw new RuntimeException('Embedded backup path is invalid.');
        }
    }
    return $path;
}

function custom_restore_allowed_path($path)
{
    foreach (['uploads/', 'storage/uploads/', 'storage/secure_uploads/', 'storage/private/', 'storage/contract-templates/'] as $prefix) {
        if (strpos($path, $prefix) === 0) {
            return true;
        }
    }
    return false;
}

function custom_restore_copy_files(ZipArchive $zip, array $expectedFiles)
{
    $root = realpath(__DIR__);
    if ($root === false) {
        throw new RuntimeException('Application root cannot be resolved.');
    }
    foreach ($expectedFiles as $relative => $expectedHash) {
        $relative = custom_restore_safe_path($relative);
        if (!custom_restore_allowed_path($relative)) {
            throw new RuntimeException('Embedded backup file path is outside the restore allowlist.');
        }
        $parts = explode('/', $relative);
        $destination = $root;
        foreach ($parts as $index => $part) {
            $destination .= DIRECTORY_SEPARATOR . $part;
            if ($index < count($parts) - 1) {
                if (is_link($destination)) {
                    throw new RuntimeException('Refusing to restore into a symbolic link.');
                }
                if (!is_dir($destination) && !mkdir($destination, 0755, true) && !is_dir($destination)) {
                    throw new RuntimeException('Cannot create data directory for restored files.');
                }
                $resolved = realpath($destination);
                if (!$resolved || ($resolved !== $root && strpos($resolved, $root . DIRECTORY_SEPARATOR) !== 0)) {
                    throw new RuntimeException('Restored file path escaped the application root.');
                }
            }
        }
        if (is_link($destination)) {
            throw new RuntimeException('Refusing to overwrite a symbolic link with backup data.');
        }
        $input = $zip->getStream($relative);
        if (!$input) {
            throw new RuntimeException('A listed backup file is missing from the payload.');
        }
        $output = @fopen($destination, 'wb');
        if (!$output) {
            fclose($input);
            throw new RuntimeException('Cannot write a restored user file.');
        }
        $hash = hash_init('sha256');
        $ok = true;
        while (!feof($input)) {
            $chunk = fread($input, 8192);
            if ($chunk === false) {
                $ok = false;
                break;
            }
            if ($chunk !== '') {
                hash_update($hash, $chunk);
                if (fwrite($output, $chunk) !== strlen($chunk)) {
                    $ok = false;
                    break;
                }
            }
        }
        fclose($input);
        fclose($output);
        if (!$ok || !hash_equals($expectedHash, hash_final($hash))) {
            @unlink($destination);
            throw new RuntimeException('A restored user file failed checksum validation.');
        }
    }
}
