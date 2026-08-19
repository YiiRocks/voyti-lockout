<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Lockout;

/**
 * Builds {@see FailedAttemptsStore} cache keys, scoped per action so login and registration
 * counters never collide. Keys are scoped by IP address rather than by account identifier: IP
 * scoping still catches an attacker hammering one or many accounts, without letting that same
 * attacker lock a legitimate user out of their own account by deliberately failing its login from
 * elsewhere. The IP is hashed rather than embedded verbatim: PSR-16 reserves `{}()/\@:` in cache
 * keys, and a raw IPv6 address contains `:`.
 */
final class LockoutKeyHelper
{
    public static function login(string $ip): string
    {
        return 'voyti-lockout-login-' . hash('sha256', $ip);
    }

    public static function registration(string $ip): string
    {
        return 'voyti-lockout-registration-' . hash('sha256', $ip);
    }
}
