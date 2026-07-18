<?php

class Controller
{
    public function render($view, array $data = [], $layout = 'app')
    {
        extract($data, EXTR_SKIP);
        if (strpos((string) $view, 'plugin:') === 0) {
            $pluginView = substr((string) $view, 7);
            $parts = explode('/', trim($pluginView, '/'), 2);
            if (count($parts) !== 2) {
                ErrorHandler::abort(500);
            }
            $viewFile = PluginManager::viewFile($parts[0], $parts[1]);
        } else {
            $viewFile = __DIR__ . '/../views/' . $view . '.php';
        }
        if (!is_file($viewFile)) {
            ErrorHandler::abort(500);
        }
        ob_start();
        require $viewFile;
        $content = ob_get_clean();
        if ($layout === null) {
            echo $content;
            return;
        }
        require __DIR__ . '/../views/layouts/' . $layout . '.php';
    }

    protected function json(array $payload, $status = 200)
    {
        http_response_code($status);
        header('X-Request-Id: ' . ErrorHandler::requestId());
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }

    protected function requireRole($roles)
    {
        Auth::requireRole($roles);
    }

    protected function onlyPost()
    {
        if (!is_post()) {
            ErrorHandler::abort(405);
        }
        Csrf::verify();
    }
}
