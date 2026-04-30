<?php

namespace Miko;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JWTAuth
{
    private static string $secret = '';

    private static function secret(): string
    {
        if (!self::$secret) {
            self::$secret = getenv('JWT_SECRET') ?: 'miko-pos-dev-secret-key-change-in-production';
        }
        return self::$secret;
    }

    public static function encode(array $payload): string
    {
        $payload['iat'] = time();
        $payload['exp'] = time() + 86400 * 7;
        return JWT::encode($payload, self::secret(), 'HS256');
    }

    public static function decode(string $token): ?array
    {
        try {
            $decoded = JWT::decode($token, new Key(self::secret(), 'HS256'));
            return (array) $decoded;
        } catch (\Exception $e) {
            return null;
        }
    }

    public static function getTokenFromHeaders(): ?string
    {
        $auth = $_SERVER['HTTP_AUTHORIZATION']
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
            ?? $_SERVER['Authorization']
            ?? '';

        if (preg_match('/Bearer\s+(.+)$/i', $auth, $m)) {
            return $m[1];
        }

        $headers = function_exists('apache_request_headers') ? apache_request_headers() : [];
        if (isset($headers['Authorization'])) {
            $auth = $headers['Authorization'];
        } elseif (isset($headers['authorization'])) {
            $auth = $headers['authorization'];
        }

        if (preg_match('/Bearer\s+(.+)$/i', $auth, $m)) {
            return $m[1];
        }

        return null;
    }

    public static function authenticateFromToken(): ?array
    {
        $token = self::getTokenFromHeaders();
        if (!$token) return null;

        $payload = self::decode($token);
        if (!$payload || !isset($payload['user_id'])) return null;

        return $payload;
    }
}
