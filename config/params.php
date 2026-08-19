<?php

declare(strict_types=1);

return [
    'yiirocks/voyti' => [
        'lockout' => [
            // 5 failures per 15-minute sliding window, per IP - OWASP Authentication Cheat Sheet /
            // NIST SP 800-63B-4 range for login brute-force protection.
            'loginMaxAttempts' => 5,
            'loginWindowSeconds' => 900,
            // 10 failures per 1-minute sliding window, per IP - guards against automated
            // account-creation spam without penalizing a legitimate user correcting a couple of typos.
            'registrationMaxAttempts' => 10,
            'registrationWindowSeconds' => 60,
        ],
    ],
];
