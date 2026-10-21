<?php

namespace Proma\Plugins\Repair\Controllers;

use Proma\Plugins\Repair\Support\RouteCatalog;

final class RouteController
{
    public function dispatch(...$parameters): void
    {
        try {
            $match = RouteCatalog::resolve(
                (string) ($_GET['route'] ?? ''),
                (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')
            );
            if ($match === null) {
                \ErrorHandler::abort(404);
            }
            $route = $match['route'];
            if (($route['auth'] ?? true) && !\Auth::check()) {
                \Auth::requireLogin();
            }
            if (!empty($route['permission'])) {
                \PluginManager::requirePermission($route['permission']);
            }
            [$class, $method] = explode('@', $route['handler'], 2);
            call_user_func_array([new $class(), $method], $match['parameters']);
        } catch (\HttpException $e) {
            // Old Core routers swallow plugin HttpExceptions and fall through to
            // a 404. Finish the response here with its actual HTTP status.
            \ErrorHandler::respond($e->status(), $e->getMessage(), $e->details(), $e->headers());
        }
    }
}
