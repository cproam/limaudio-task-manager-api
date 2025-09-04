<?php

namespace App\Security;

use App\Http\Request;
use App\Http\Response;
use App\Support\Env;
use App\Security\Jwt;

class Auth
{
    public static function requireBearer(Request $req): bool
    {
        $auth = $req->headers['Authorization'] ?? '';
        if (!str_starts_with($auth, 'Bearer ')) {
            Response::json(['error' => 'Unauthorized'], 401, ['WWW-Authenticate' => 'Bearer']);
            return false;
        }
        $token = trim(substr($auth, 7));
        // Accept either static API_KEY or a signed JWT
        $key = Env::get('API_KEY');
        if ($key && hash_equals($key, $token)) {
            return true;
        }
        $claims = Jwt::verify($token);
        if ($claims) {
            // Optionally attach claims to request (via global)
            $GLOBALS['auth_user'] = $claims;
            return true;
        }
        Response::json(['error' => 'Forbidden'], 403);
        return false;
    }
}
