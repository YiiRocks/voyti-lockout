<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Lockout\Tests;

use YiiRocks\Voyti\Lockout\LockoutKeyHelper;

final class LockoutKeyHelperTest extends TestCase
{
    public function testKeysContainNoPsr16ReservedCharactersEvenForAnIpv6Address(): void
    {
        self::assertFalse(strpbrk(LockoutKeyHelper::login('2001:db8::1'), '{}()/\@:'));
        self::assertFalse(strpbrk(LockoutKeyHelper::registration('2001:db8::1'), '{}()/\@:'));
    }

    public function testLoginKeyIsPrefixedBeforeTheHashedIp(): void
    {
        self::assertSame(
            'voyti-lockout-login-' . hash('sha256', '203.0.113.1'),
            LockoutKeyHelper::login('203.0.113.1'),
        );
    }

    public function testLoginKeyIsStableAndDistinctFromRegistrationForTheSameIp(): void
    {
        $loginKey = LockoutKeyHelper::login('203.0.113.1');

        self::assertSame($loginKey, LockoutKeyHelper::login('203.0.113.1'));
        self::assertNotSame($loginKey, LockoutKeyHelper::registration('203.0.113.1'));
    }

    public function testRegistrationKeyIsPrefixedBeforeTheHashedIp(): void
    {
        self::assertSame(
            'voyti-lockout-registration-' . hash('sha256', '203.0.113.1'),
            LockoutKeyHelper::registration('203.0.113.1'),
        );
    }
}
