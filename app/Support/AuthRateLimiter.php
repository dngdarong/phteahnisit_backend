<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class AuthRateLimiter
{
    public static function loginKey(Request $request): string
    {
        return self::buildKey('auth-login', $request->input('email'), $request);
    }

    public static function loginCacheKey(Request $request): string
    {
        return self::cacheKey('auth-login', $request);
    }

    public static function registerKey(Request $request): string
    {
        return self::buildKey('auth-register', $request->input('email'), $request);
    }

    public static function registerCacheKey(Request $request): string
    {
        return self::cacheKey('auth-register', $request);
    }

    private static function buildKey(string $prefix, mixed $email, Request $request): string
    {
        $normalizedEmail = Str::of((string) $email)->trim()->lower()->toString();
        $ip = $request->ip() ?? 'unknown';

        return sprintf('%s|%s|%s', $prefix, $normalizedEmail !== '' ? $normalizedEmail : 'unknown', $ip);
    }

    private static function cacheKey(string $limiterName, Request $request): string
    {
        return md5($limiterName.self::buildKey($limiterName, $request->input('email'), $request));
    }
}
