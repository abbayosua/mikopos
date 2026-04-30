<?php

namespace Miko;

class Auth
{
    private static ?array $jwtPayload = null;
    private static ?int $requestStoreId = null;

    public static function setRequestStoreId(?int $id): void
    {
        self::$requestStoreId = $id;
    }

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
        if (Session::has('user_id')) return true;
        if (self::resolveJwt()) return true;
        return false;
    }

    public static function id(): ?int
    {
        if (Session::has('user_id')) return Session::get('user_id');
        if (self::resolveJwt()) return self::$jwtPayload['user_id'];
        return null;
    }

    public static function tenantId(): ?int
    {
        if (Session::has('tenant_id')) return Session::get('tenant_id');
        if (self::resolveJwt()) return self::$jwtPayload['tenant_id'];
        return null;
    }

    public static function tenantName(): ?string
    {
        if (Session::has('tenant_name')) return Session::get('tenant_name');
        if (self::resolveJwt()) return self::$jwtPayload['tenant_name'];
        return null;
    }

    public static function userRole(): ?string
    {
        if (Session::has('user_role')) return Session::get('user_role');
        if (self::resolveJwt()) return self::$jwtPayload['role'];
        return null;
    }

    public static function storeId(): ?int
    {
        if (self::$requestStoreId !== null) return self::$requestStoreId;
        if (Session::has('store_id')) return Session::get('store_id');
        if (self::resolveJwt()) return self::$jwtPayload['store_id'] ?? null;
        return null;
    }

    public static function storeName(): ?string
    {
        if (Session::has('store_name')) return Session::get('store_name');
        if (self::resolveJwt()) return self::$jwtPayload['store_name'] ?? null;
        return null;
    }

    public static function hasStore(): bool
    {
        return self::storeId() !== null;
    }

    public static function isAdmin(): bool
    {
        return self::userRole() === 'admin';
    }

    public static function user(): ?array
    {
        $uid = self::id();
        if (!$uid) return null;

        return Database::fetch(
            'SELECT id, tenant_id, name, email, role, is_active, created_at
             FROM users WHERE id = :id',
            ['id' => $uid]
        );
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

    private static function resolveJwt(): bool
    {
        if (self::$jwtPayload !== null) return true;

        $token = JWTAuth::getTokenFromHeaders();
        if (!$token) return false;

        $payload = JWTAuth::decode($token);
        if (!$payload) return false;

        self::$jwtPayload = $payload;
        return true;
    }
}
