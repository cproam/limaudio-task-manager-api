<?php

namespace App\Security;

use App\Http\Request;
use App\Http\Response;
use App\Security\Jwt;

class Auth
{
    private static function getAuthorizationHeader(): ?string {
        if (!empty($_SERVER['HTTP_AUTHORIZATION'])) return $_SERVER['HTTP_AUTHORIZATION'];
        if (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) return $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        if (function_exists('getallheaders')) {
            foreach (getallheaders() as $k => $v) {
                if (strcasecmp($k, 'Authorization') === 0) return $v;
            }
        }
        return null;
    }

    public static function requireBearer(Request $req): bool
    {
        $auth = self::getAuthorizationHeader() ?? ($req->headers['Authorization'] ?? '');
        if (!preg_match('/^\s*Bearer\s+(.+)\s*$/i', $auth, $m)) {
            header('WWW-Authenticate: Bearer realm="api", error="invalid_request"');
            Response::json(['error' => 'Unauthorized'], 401);
            return false;
        }

        $token = trim($m[1]);
        $claims = Jwt::verify($token);
        if ($claims) {
            $GLOBALS['auth_user'] = $claims;
            return true;
        }

        header('WWW-Authenticate: Bearer realm="api", error="invalid_token", error_description="Invalid or expired token"');
        Response::json(['error' => 'Unauthorized'], 401);
        return false;
    }
}
