<?php

namespace App\Security;

use App\Support\Env;

class Jwt
{
    private static function b64url($data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function b64url_decode($data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        return base64_decode(strtr($data, '-_', '+/')) ?: '';
    }

    public static function sign(array $payload, int $ttlSeconds = 120): string
    {
        $header = ['alg' => 'HS256', 'typ' => 'JWT'];
        $now = time();
        $payload = array_merge(['iat' => $now, 'exp' => $now + $ttlSeconds], $payload);
        $secret = Env::get('AUTH_SECRET', 'changeme');
        $h = self::b64url(json_encode($header));
        $p = self::b64url(json_encode($payload));
        $sig = hash_hmac('sha256', "$h.$p", $secret, true);
        return "$h.$p." . self::b64url($sig);
    }

    public static function verify(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) return null;
        [$h, $p, $s] = $parts;
        $secret = Env::get('AUTH_SECRET', 'changeme');
        $calc = self::b64url(hash_hmac('sha256', "$h.$p", $secret, true));
        if (!hash_equals($calc, $s)) return null;
        $payload = json_decode(self::b64url_decode($p), true);
        if (!is_array($payload)) return null;
        if (isset($payload['exp']) && time() > (int)$payload['exp']) return null;
        return $payload;
    }
}
