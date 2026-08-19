<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Lockout\Listener;

use YiiRocks\Voyti\Event\Auth\BeforeRegisterEvent;
use YiiRocks\Voyti\Exception\ActionPreventedException;
use YiiRocks\Voyti\Lockout\FailedAttemptsStore;
use YiiRocks\Voyti\Lockout\LockoutConfig;
use YiiRocks\Voyti\Lockout\LockoutKeyHelper;
use Yiisoft\Translator\TranslatorInterface;

/**
 * Listens for the cancellable {@see BeforeRegisterEvent} and blocks the registration once the
 * request's IP address has {@see LockoutConfig::$registrationMaxAttempts} or more failures recorded
 * by {@see RecordFailedRegistrationAttemptListener} within the configured window - this guards
 * against automated account-creation spam. `BeforeRegisterEvent` carries no server params directly,
 * so the IP is read off the not-yet-persisted `User`, which `RegisterService` always sets before
 * dispatching this event.
 */
final readonly class BlockLockedOutRegistrationListener
{
    private const string FALLBACK_IP = '127.0.0.1';

    public function __construct(
        private FailedAttemptsStore $store,
        private LockoutConfig $config,
        private TranslatorInterface $translator,
    ) {}

    public function onBeforeRegister(BeforeRegisterEvent $event): void
    {
        $ip = $event->getUser()->getRegistrationIp() ?? self::FALLBACK_IP;
        $attempts = $this->store->getAttemptCount(LockoutKeyHelper::registration($ip));

        if ($attempts >= $this->config->registrationMaxAttempts) {
            throw new ActionPreventedException(
                $this->translator->translate(
                    'voyti-lockout.registration.too_many_attempts',
                    category: 'voyti-lockout',
                ),
                ['email'],
            );
        }
    }
}
