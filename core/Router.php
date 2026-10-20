<?php

class Router
{
    public function dispatch()
    {
        $requestStartedAt = microtime(true);
        $route = trim($_GET['route'] ?? '', '/');
        if ($route === '') {
            $route = Auth::check() ? 'dashboard' : 'auth/login';
        }
        $route = $this->resolveSafeActionAlias($route);
        if ($route === 'ecommerce' || strpos($route, 'ecommerce/') === 0) {
            set_flash('info', 'فروشگاه داخلی بازنشسته شده است. سوابق پیشین حفظ شده و فروش جدید از فروشگاه متصل انجام می‌شود.');
            redirect(Auth::check() ? 'dashboard' : 'auth/login');
        }
        RequestTelemetry::setRoute($route);
        if ($route === 'health/live' || $route === 'health/ready') {
            RequestTelemetry::recordSpan('route.health', $requestStartedAt, ['route' => $route]);
            $health = new HealthController();
            $health->respond($route === 'health/live');
            return;
        }
        // The update screen creates a session-bound confirmation code while it
        // renders, and the install action consumes that code before starting the
        // long-running backup and file swap. Keep this one operator session open
        // for those two requests so shared-host session handlers do not need to
        // reopen it after Router has closed it.
        $needsWritableConfirmationSession = $route === 'updates'
            || $route === 'updates/index'
            || strpos($route, 'updates/install/') === 0;
        if (strpos($route, 'auth/') !== 0 && !$needsWritableConfirmationSession) {
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
            } catch (HttpException $e) {
                throw $e;
            } catch (Throwable $e) {
                ErrorHandler::log('plugin_route', $e, 500);
                ErrorHandler::abort(500);
            }
        }
        $parts = array_values(array_filter(explode('/', $route), 'strlen'));
        $controllerPart = $parts[0] ?? 'dashboard';
        $action = $parts[1] ?? 'index';
        $params = array_slice($parts, 2);

        $controllerTarget = $this->controllerTarget($controllerPart);
        if ($controllerTarget === null) {
            $this->notFound();
            return;
        }
        [$controllerName, $controllerFile] = $controllerTarget;
        $actionName = preg_replace('/[^a-zA-Z0-9_]/', '', $action);

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

    /** Resolve only ordinary route slugs; never let route data add path separators. */
    protected function controllerTarget($controllerPart)
    {
        $controllerPart = (string) $controllerPart;
        if (!preg_match('/\A[a-zA-Z0-9_-]+\z/', $controllerPart)) {
            return null;
        }

        $controllerName = str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $controllerPart))) . 'Controller';
        return [$controllerName, __DIR__ . '/../controllers/' . $controllerName . '.php'];
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
