<?php

namespace Miko;

class Request
{
    public static function method(): string
    {
        return $_SERVER['REQUEST_METHOD'];
    }

    public static function uri(): string
    {
        $uri = $_SERVER['REQUEST_URI'];
        $uri = parse_url($uri, PHP_URL_PATH);
        return rtrim($uri, '/') ?: '/';
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_GET[$key] ?? $default;
    }

    public static function post(string $key, mixed $default = null): mixed
    {
        return $_POST[$key] ?? $default;
    }

    public static function all(): array
    {
        if (self::method() === 'POST' || self::method() === 'PUT') {
            $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

            if (str_contains($contentType, 'application/json')) {
                $input = json_decode(file_get_contents('php://input'), true);
                return $input ?? [];
            }

            if (!empty($_POST)) {
                return $_POST;
            }

            $raw = file_get_contents('php://input');
            if (!empty($raw)) {
                $input = json_decode($raw, true);
                if (is_array($input)) {
                    return $input;
                }
                parse_str($raw, $parsed);
                return $parsed;
            }

            return [];
        }
        return $_GET;
    }

    public static function input(string $key, mixed $default = null): mixed
    {
        $data = self::all();
        return $data[$key] ?? $default;
    }

    public static function has(string $key): bool
    {
        return isset($_REQUEST[$key]);
    }

    public static function file(string $key): ?array
    {
        return $_FILES[$key] ?? null;
    }

    public static function isAjax(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    public static function isGet(): bool
    {
        return self::method() === 'GET';
    }

    public static function isPost(): bool
    {
        return self::method() === 'POST';
    }

    public static function validate(array $rules): array
    {
        $errors = [];
        $data = self::all();

        foreach ($rules as $field => $ruleSet) {
            $ruleList = is_array($ruleSet) ? $ruleSet : explode('|', $ruleSet);

            foreach ($ruleList as $rule) {
                if ($rule === 'required' && empty($data[$field])) {
                    $errors[$field][] = "{$field} is required";
                }

                if (str_starts_with($rule, 'min:') && isset($data[$field])) {
                    $min = explode(':', $rule)[1];
                    if (strlen($data[$field]) < $min) {
                        $errors[$field][] = "{$field} must be at least {$min} characters";
                    }
                }

                if (str_starts_with($rule, 'max:') && isset($data[$field])) {
                    $max = explode(':', $rule)[1];
                    if (strlen($data[$field]) > $max) {
                        $errors[$field][] = "{$field} must not exceed {$max} characters";
                    }
                }

                if ($rule === 'email' && isset($data[$field]) && !filter_var($data[$field], FILTER_VALIDATE_EMAIL)) {
                    $errors[$field][] = "{$field} must be a valid email";
                }

                if ($rule === 'numeric' && isset($data[$field]) && !is_numeric($data[$field])) {
                    $errors[$field][] = "{$field} must be numeric";
                }
            }
        }

        return $errors;
    }
}
