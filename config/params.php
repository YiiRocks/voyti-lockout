<?php

declare(strict_types=1);

return [
    'yiirocks/voyti' => [
        'lockout' => [
            // A per-IP failed-attempt count is remembered for at least this long, even if its
            // computed delay is smaller - OWASP Authentication Cheat Sheet / NIST SP 800-63B-4 range
            // for login brute-force protection.
            'loginMinRetentionSeconds' => 900,
            // Every failure doubles the required wait, starting from a barely noticeable 1 second and
            // capped at 1 hour - OWASP's recommended exponential lockout. No hard ceiling on attempts
            // - per OWASP/NIST guidance against a lockout control that itself becomes a
            // denial-of-service against the user.
            'loginBaseDelaySeconds' => 1,
            'loginMaxDelaySeconds' => 3600,
            // A per-IP registration failure count is remembered for at least this long - looser than
            // login's since a mistyped email or a weak password is a common, legitimate reason to
            // fail more than once in quick succession.
            'registrationMinRetentionSeconds' => 60,
            // Same doubling delay, capped lower since registration abuse is lower stakes than an
            // account-takeover attempt.
            'registrationBaseDelaySeconds' => 1,
            'registrationMaxDelaySeconds' => 600,
        ],
    ],
];
