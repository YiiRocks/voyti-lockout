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
 * Listens for the cancellable {@see BeforeRegisterEvent} and, once the request's IP address has any
 * failures recorded by {@see RecordFailedRegistrationAttemptListener} within the configured window,
 * delays the registration by an exponentially growing wait - this guards against automated
 * account-creation spam. `BeforeRegisterEvent` carries no server params directly, so the IP is read
 * off the not-yet-persisted `User`, which `RegisterService` always sets before dispatching this
 * event. There is no attempt count that locks the IP out for good, per OWASP/NIST guidance against a
 * lockout control that itself becomes a denial-of-service against the legitimate user.
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
        $retryAfterSeconds = $this->store->getRetryAfterSeconds(
            LockoutKeyHelper::registration($ip),
            $this->config->registrationBaseDelaySeconds,
            $this->config->registrationMaxDelaySeconds,
        );

        if ($retryAfterSeconds > 0) {
            throw new ActionPreventedException(
                $this->translator->translate(
                    'voyti-lockout.registration.too_many_attempts',
                    ['retryAfterSeconds' => $retryAfterSeconds],
                    category: 'voyti-lockout',
                ),
                ['email'],
            );
        }
    }
}
