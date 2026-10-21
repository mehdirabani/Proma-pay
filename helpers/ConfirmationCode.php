<?php

class ConfirmationCode
{
    protected const SESSION_KEY = '_confirmation_codes';

    public static function code($key)
    {
        $key = self::normalizeKey($key);
        if (empty($_SESSION[self::SESSION_KEY][$key])) {
            $releaseAfterWrite = class_exists('Auth', false) && Auth::sessionWasReleased();
            if ($releaseAfterWrite && !Auth::ensureSessionWritable()) {
                throw new RuntimeException('کد تأیید در نشست کاربری قابل ذخیره نیست.');
            }
            $_SESSION[self::SESSION_KEY][$key] = (string) random_int(10000, 99999);
            $code = $_SESSION[self::SESSION_KEY][$key];
            if ($releaseAfterWrite) {
                Auth::commitSessionWrite();
            }
            return $code;
        }
        return $_SESSION[self::SESSION_KEY][$key];
    }

    public static function verify($key, $value)
    {
        $key = self::normalizeKey($key);
        $expected = $_SESSION[self::SESSION_KEY][$key] ?? null;
        $actual = trim(to_english_digits((string) $value));
        if ($expected && hash_equals((string) $expected, $actual)) {
            $releaseAfterWrite = class_exists('Auth', false) && Auth::sessionWasReleased();
            if ($releaseAfterWrite && !Auth::ensureSessionWritable()) {
                return false;
            }
            unset($_SESSION[self::SESSION_KEY][$key]);
            if ($releaseAfterWrite) {
                Auth::commitSessionWrite();
            }
            return true;
        }
        return false;
    }

    public static function hint($key)
    {
        return to_persian_digits(self::code($key));
    }

    protected static function normalizeKey($key)
    {
        return preg_replace('/[^a-z0-9_.:-]/i', '_', (string) $key) ?: 'default';
    }
}
