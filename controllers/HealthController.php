<?php

class HealthController
{
    public function respond($liveOnly)
    {
        $ready = true;

        if (!$liveOnly) {
            $ready = $this->databaseReady() && $this->storageReady() && $this->coreReady();
        }

        if (!headers_sent()) {
            http_response_code($ready ? 200 : 503);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('X-Request-Id: ' . ErrorHandler::requestId());
        }

        echo json_encode([
            'ok' => $ready,
            'status' => $ready ? 'ok' : 'unavailable',
            'service' => 'proma-pay',
            'request_id' => ErrorHandler::requestId(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    protected function databaseReady()
    {
        try {
            return (bool) Model::fetch('SELECT 1 AS ready');
        } catch (Throwable $e) {
            ErrorHandler::log('health_ready_database', $e, 503);
            return false;
        }
    }

    protected function storageReady()
    {
        $storage = dirname(__DIR__) . '/storage';
        return is_dir($storage) && is_readable($storage) && is_writable($storage);
    }

    protected function coreReady()
    {
        return is_file(dirname(__DIR__) . '/config/version.php')
            && is_file(dirname(__DIR__) . '/core/Router.php')
            && is_file(dirname(__DIR__) . '/helpers/functions.php');
    }
}
