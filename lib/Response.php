<?php

namespace Miko;

class Response
{
    public static function json(mixed $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    public static function success(mixed $data = null, string $message = 'Success'): void
    {
        self::json([
            'success' => true,
            'message' => $message,
            'data'    => $data,
        ]);
    }

    public static function error(string $message = 'Error', int $status = 400, mixed $errors = null): void
    {
        $response = [
            'success' => false,
            'message' => $message,
        ];
        if ($errors !== null) {
            $response['errors'] = $errors;
        }
        self::json($response, $status);
    }

    public static function redirect(string $url): void
    {
        header("Location: {$url}");
        exit;
    }

    public static function view(string $view, array $data = []): void
    {
        extract($data);

        $viewPath = __DIR__ . '/../views/' . $view . '.php';

        if (!file_exists($viewPath)) {
            throw new \RuntimeException("View not found: {$view}");
        }

        require $viewPath;
    }

    public static function layout(string $view, array $data = [], string $layout = 'main'): void
    {
        $data['_view'] = $view;
        $data['_layout'] = $layout;

        extract($data);

        $layoutPath = __DIR__ . '/../views/layouts/' . $layout . '.php';

        if (!file_exists($layoutPath)) {
            throw new \RuntimeException("Layout not found: {$layout}");
        }

        require $layoutPath;
    }

    public static function page(string $title, string $view, array $data = []): void
    {
        $data['title'] = $title;
        $data['content'] = function () use ($view, $data) {
            extract($data);
            $viewPath = __DIR__ . '/../views/' . $view . '.php';
            if (file_exists($viewPath)) {
                require $viewPath;
            }
        };

        $layoutPath = __DIR__ . '/../views/layouts/main.php';
        extract($data);
        require $layoutPath;
    }

    public static function notFound(): void
    {
        http_response_code(404);
        self::page('404 - Not Found', 'errors/404');
    }
}
