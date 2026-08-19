<?php

declare(strict_types=1);

use YiiRocks\Voyti\Event\Auth\BeforeLoginEvent;
use YiiRocks\Voyti\Event\Auth\BeforeRegisterEvent;
use YiiRocks\Voyti\Event\Auth\FailedLoginEvent;
use YiiRocks\Voyti\Event\Auth\RegisterFormValidationFailedEvent;
use YiiRocks\Voyti\Lockout\Listener;

return [
    FailedLoginEvent::class => [
        [Listener\RecordFailedLoginAttemptListener::class, 'onFailedLogin'],
    ],
    BeforeLoginEvent::class => [
        [Listener\BlockLockedOutLoginListener::class, 'onBeforeLogin'],
    ],
    RegisterFormValidationFailedEvent::class => [
        [Listener\RecordFailedRegistrationAttemptListener::class, 'onRegisterFormValidationFailed'],
    ],
    BeforeRegisterEvent::class => [
        [Listener\BlockLockedOutRegistrationListener::class, 'onBeforeRegister'],
    ],
];
