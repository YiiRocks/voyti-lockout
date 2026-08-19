<?php

declare(strict_types=1);

namespace YiiRocks\Voyti\Lockout\Tests;

use Yiisoft\Translator\CategorySource;
use Yiisoft\Translator\Message\Php\MessageSource;
use Yiisoft\Translator\SimpleMessageFormatter;
use Yiisoft\Translator\Translator;
use Yiisoft\Translator\TranslatorInterface;

abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    protected function createTranslator(string $locale = 'en'): TranslatorInterface
    {
        $translator = new Translator($locale, null, 'voyti-lockout');
        $translator->addCategorySources(
            new CategorySource(
                'voyti-lockout',
                new MessageSource(dirname(__DIR__) . '/resources/messages'),
                new SimpleMessageFormatter(),
            ),
        );

        return $translator;
    }
}
