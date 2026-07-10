<?php

class Controller
{
    public function render($view, array $data = [], $layout = 'app')
    {
        extract($data, EXTR_SKIP);
        $viewFile = __DIR__ . '/../views/' . $view . '.php';
        if (!is_file($viewFile)) {
            http_response_code(500);
            echo 'نمای مورد نظر پیدا نشد.';
            exit;
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
            http_response_code(405);
            echo 'روش درخواست معتبر نیست.';
            exit;
        }
        Csrf::verify();
    }
}
