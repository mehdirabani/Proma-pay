<?php

class Router
{
    public function dispatch()
    {
        $requestStartedAt = microtime(true);
        $route = trim($_GET['route'] ?? '', '/');
        if ($route === '') {
            $route = Auth::check() ? 'dashboard' : (ecommerce_is_enabled() ? 'ecommerce/landing' : 'auth/login');
        }
        $route = $this->resolveSafeActionAlias($route);
        RequestTelemetry::setRoute($route);
        if ($route === 'health/live' || $route === 'health/ready') {
            RequestTelemetry::recordSpan('route.health', $requestStartedAt, ['route' => $route]);
            $health = new HealthController();
            $health->respond($route === 'health/live');
            return;
        }
        if (strpos($route, 'auth/') !== 0) {
            Auth::releaseSessionLock();
        }
        // The financial preview is a tiny core-only JSON calculation. Avoid booting
        // all optional plugins (and their queries) for each keystroke.
        if (class_exists('PluginManager') && $route !== 'contracts/preview') {
            try {
                if (PluginManager::boot()->dispatchRoute($route)) {
                    RequestTelemetry::recordSpan('route.plugin', $requestStartedAt, ['route' => $route]);
                    return;
                }
            } catch (Throwable $e) {
                ErrorHandler::log('plugin_route', $e, 500);
            }
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
        RequestTelemetry::recordSpan('route.core', $requestStartedAt, ['controller' => $controllerName, 'action' => $actionName]);
    }

    protected function resolveSafeActionAlias($route)
    {
        $aliases = [
            'users/retire' => 'users/delete',
            'customers/retire' => 'customers/delete',
            'customers/retireMedal' => 'customers/medalDelete',
            'users/retireMedal' => 'users/medalDelete',
            'imports/retire' => 'imports/delete',
            'calendar/retire' => 'calendar/delete',
            'file-manager/retire' => 'file-manager/delete',
            'backup/retireArchive' => 'backup/delete',
            'backup/clearLogs' => 'backup/deleteLogs',
            'updates/retirePackage' => 'updates/delete',
            'install-package/retirePackage' => 'install-package/delete',
            'settings/retireContractTemplate' => 'settings/deleteContractTemplate',
            'contracts/retireLegalLog' => 'contracts/deleteLegalLog',
            'ai/retireLog' => 'ai/delete',
            'legal/retireCase' => 'legal/delete',
            'plugins/retireFromHost' => 'plugins/deleteFromHost',
            'notifications/retire' => 'notifications/delete',
            'plugin/accounting/rules/retire' => 'plugin/accounting/rules/delete',
        ];
        foreach ($aliases as $safeRoute => $legacyRoute) {
            if ($route === $safeRoute || strpos($route, $safeRoute . '/') === 0) {
                return $legacyRoute . substr($route, strlen($safeRoute));
            }
        }
        return $route;
    }

    protected function notFound()
    {
        ErrorHandler::respond(404);
    }
}
