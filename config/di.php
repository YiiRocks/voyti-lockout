<?php

declare(strict_types=1);

use YiiRocks\Voyti\Lockout\LockoutConfig;
use Yiisoft\Translator\CategorySource;
use Yiisoft\Translator\Message\Php\MessageSource;
use Yiisoft\Translator\SimpleMessageFormatter;

/** @var array $params */

return [
    // Package configuration, built once from the host's `yiirocks/voyti.lockout` params array.
    LockoutConfig::class => static fn() => new LockoutConfig(
        loginMaxAttempts: $params['yiirocks/voyti']['lockout']['loginMaxAttempts'] ?? 5,
        loginWindowSeconds: $params['yiirocks/voyti']['lockout']['loginWindowSeconds'] ?? 900,
        registrationMaxAttempts: $params['yiirocks/voyti']['lockout']['registrationMaxAttempts'] ?? 10,
        registrationWindowSeconds: $params['yiirocks/voyti']['lockout']['registrationWindowSeconds'] ?? 60,
    ),

    // Translation category source for this package's message files.
    'yiirocks/voyti-lockout.translator' => [
        'definition' => static fn() => new CategorySource(
            'voyti-lockout',
            new MessageSource(dirname(__DIR__) . '/resources/messages'),
            new SimpleMessageFormatter(),
        ),
        'tags' => ['translation.categorySource'],
    ],
];
