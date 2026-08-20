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
        loginMinRetentionSeconds: $params['yiirocks/voyti']['lockout']['loginMinRetentionSeconds'] ?? 900,
        loginBaseDelaySeconds: $params['yiirocks/voyti']['lockout']['loginBaseDelaySeconds'] ?? 1,
        loginMaxDelaySeconds: $params['yiirocks/voyti']['lockout']['loginMaxDelaySeconds'] ?? 3600,
        registrationMinRetentionSeconds: $params['yiirocks/voyti']['lockout']['registrationMinRetentionSeconds'] ?? 60,
        registrationBaseDelaySeconds: $params['yiirocks/voyti']['lockout']['registrationBaseDelaySeconds'] ?? 1,
        registrationMaxDelaySeconds: $params['yiirocks/voyti']['lockout']['registrationMaxDelaySeconds'] ?? 600,
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
