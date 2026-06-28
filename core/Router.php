<?php

class Router
{
    public function dispatch()
    {
        $route = trim($_GET['route'] ?? 'dashboard', '/');
        if ($route === '') {
            $route = 'dashboard';
        }
        $parts = array_values(array_filter(explode('/', $route), 'strlen'));
        $controllerPart = $parts[0] ?? 'dashboard';
        $action = $parts[1] ?? 'index';
        $params = array_slice($parts, 2);

        $controllerName = str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $controllerPart))) . 'Controller';
        $actionName = preg_replace('/[^a-zA-Z0-9_]/', '', $action);
        $controllerFile = __DIR__ . '/../controllers/' . $controllerName . '.php';

        if (!is_file($controllerFile)) {
            $this->notFound();
            return;
        }
        require_once $controllerFile;
        if (!class_exists($controllerName)) {
            $this->notFound();
            return;
        }
        $controller = new $controllerName();
        if (!method_exists($controller, $actionName)) {
            $this->notFound();
            return;
        }
        call_user_func_array([$controller, $actionName], $params);
    }

    protected function notFound()
    {
        http_response_code(404);
        $controller = new Controller();
        $controller->render('errors/404', ['title' => 'صفحه پیدا نشد'], Auth::check() ? 'app' : 'auth');
    }
}
