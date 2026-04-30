<?php

namespace Miko;

class Router
{
    private static ?\Bramus\Router\Router $router = null;

    public static function init(): \Bramus\Router\Router
    {
        if (self::$router === null) {
            self::$router = new \Bramus\Router\Router();

            self::$router->before('GET|POST|PUT|DELETE', '/api/.*', function () {
                header('Content-Type: application/json');
            });
        }

        return self::$router;
    }

    public static function get(string $pattern, callable $handler): void
    {
        self::init()->get($pattern, $handler);
    }

    public static function post(string $pattern, callable $handler): void
    {
        self::init()->post($pattern, $handler);
    }

    public static function put(string $pattern, callable $handler): void
    {
        self::init()->put($pattern, $handler);
    }

    public static function delete(string $pattern, callable $handler): void
    {
        self::init()->delete($pattern, $handler);
    }

    public static function match(string $method, string $pattern, callable $handler): void
    {
        self::init()->match($method, $pattern, $handler);
    }

    public static function run(): void
    {
        self::init()->run();
    }

    public static function apiResource(string $base, string $controllerClass): void
    {
        $base = rtrim($base, '/');

        self::get("{$base}", function () use ($controllerClass) {
            $ctrl = new $controllerClass();
            $ctrl->index();
        });

        self::get("{$base}/(\d+)", function (int $id) use ($controllerClass) {
            $ctrl = new $controllerClass();
            $ctrl->show($id);
        });

        self::post("{$base}", function () use ($controllerClass) {
            $ctrl = new $controllerClass();
            $ctrl->store();
        });

        self::put("{$base}/(\d+)", function (int $id) use ($controllerClass) {
            $ctrl = new $controllerClass();
            $ctrl->update($id);
        });

        self::delete("{$base}/(\d+)", function (int $id) use ($controllerClass) {
            $ctrl = new $controllerClass();
            $ctrl->destroy($id);
        });
    }
}
