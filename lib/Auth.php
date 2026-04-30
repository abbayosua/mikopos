<?php

namespace Miko;

class Auth
{
    public static function attempt(string $email, string $password): ?array
    {
        $user = Database::fetch(
            'SELECT u.*, t.name as tenant_name, t.slug as tenant_slug
             FROM users u
             JOIN tenants t ON t.id = u.tenant_id
             WHERE u.email = :email AND u.is_active = true',
            ['email' => $email]
        );

        if (!$user || !password_verify($password, $user['password'])) {
            return null;
        }

        Session::set('user_id', $user['id']);
        Session::set('tenant_id', $user['tenant_id']);
        Session::set('user_name', $user['name']);
        Session::set('user_role', $user['role']);
        Session::set('tenant_name', $user['tenant_name']);
        Session::set('tenant_slug', $user['tenant_slug']);

        return $user;
    }

    public static function check(): bool
    {
        return Session::has('user_id');
    }

    public static function user(): ?array
    {
        if (!self::check()) {
            return null;
        }

        return Database::fetch(
            'SELECT id, tenant_id, name, email, role, is_active, created_at
             FROM users WHERE id = :id',
            ['id' => Session::get('user_id')]
        );
    }

    public static function id(): ?int
    {
        return Session::get('user_id');
    }

    public static function tenantId(): ?int
    {
        return Session::get('tenant_id');
    }

    public static function tenantName(): ?string
    {
        return Session::get('tenant_name');
    }

    public static function userRole(): ?string
    {
        return Session::get('user_role');
    }

    public static function isAdmin(): bool
    {
        return self::userRole() === 'admin';
    }

    public static function storeId(): ?int
    {
        return Session::get('store_id');
    }

    public static function storeName(): ?string
    {
        return Session::get('store_name');
    }

    public static function hasStore(): bool
    {
        return Session::has('store_id');
    }

    public static function logout(): void
    {
        Session::destroy();
    }

    public static function requireAuth(): void
    {
        if (!self::check()) {
            $uri = $_SERVER['REQUEST_URI'] ?? '';
            if (str_starts_with($uri, '/api/')) {
                Response::error('Unauthorized', 401);
            }
            Response::redirect('/login');
        }
    }
}
