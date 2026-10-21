<?php

class PluginHooks
{
    protected static $listeners = [];

    public static function listen($event, callable $listener, $priority = 10)
    {
        $event = trim((string) $event);
        if ($event === '') {
            return;
        }
        self::$listeners[$event][] = [
            'priority' => (int) $priority,
            'listener' => $listener,
        ];
        usort(self::$listeners[$event], static function ($left, $right) {
            return $left['priority'] <=> $right['priority'];
        });
    }

    public static function dispatch($event, array $payload = [], $critical = false)
    {
        $safePayload = self::sanitize($payload);
        foreach (self::$listeners[$event] ?? [] as $item) {
            try {
                call_user_func($item['listener'], $safePayload);
            } catch (Throwable $e) {
                if (class_exists('PluginRegistry')) {
                    PluginRegistry::logRuntimeError($event, $e);
                }
                if ($critical) {
                    throw $e;
                }
            }
        }
        return $safePayload;
    }

    public static function filter($hook, $value, array $context = [])
    {
        $payload = ['value' => $value, 'context' => $context];
        foreach (self::$listeners[$hook] ?? [] as $item) {
            try {
                $result = call_user_func($item['listener'], self::sanitize($payload));
                if ($result !== null) {
                    $payload['value'] = $result;
                }
            } catch (Throwable $e) {
                if (class_exists('PluginRegistry')) {
                    PluginRegistry::logRuntimeError($hook, $e);
                }
            }
        }
        return $payload['value'];
    }

    protected static function sanitize($value, $key = '')
    {
        $blocked = ['password', 'password_hash', 'token', 'secret', 'api_key', 'session', 'credential'];
        foreach ($blocked as $term) {
            if ($key !== '' && stripos($key, $term) !== false) {
                return '[redacted]';
            }
        }
        if (is_array($value)) {
            $result = [];
            foreach ($value as $childKey => $childValue) {
                $result[$childKey] = self::sanitize($childValue, (string) $childKey);
            }
            return $result;
        }
        return $value;
    }
}
