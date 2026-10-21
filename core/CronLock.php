<?php

class CronLock
{
    public static function acquire($name)
    {
        $name = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $name);
        if ($name === '') {
            throw new InvalidArgumentException('Cron lock name is invalid.');
        }
        $directory = dirname(__DIR__) . '/storage/cache/locks';
        if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
            throw new RuntimeException('Cron lock directory is unavailable.');
        }
        if (!is_writable($directory)) {
            throw new RuntimeException('Cron lock directory is not writable.');
        }
        $handle = fopen($directory . '/' . $name . '.lock', 'c+');
        if ($handle === false) {
            throw new RuntimeException('Cron lock file could not be opened.');
        }
        if (!flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);
            return false;
        }
        ftruncate($handle, 0);
        fwrite($handle, json_encode(['pid' => getmypid(), 'started_at' => date(DATE_ATOM)], JSON_UNESCAPED_SLASHES));
        fflush($handle);
        return $handle;
    }

    public static function release($handle)
    {
        if (!is_resource($handle)) {
            return;
        }
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}
