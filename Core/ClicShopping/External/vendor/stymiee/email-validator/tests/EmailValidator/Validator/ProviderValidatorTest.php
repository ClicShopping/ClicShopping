<?php

namespace EmailValidator\Tests\Validator;

use EmailValidator\Validator\AProviderValidator;
use EmailValidator\Validator\FreeEmailValidator;
use EmailValidator\EmailAddress;
use EmailValidator\Policy;
use PHPUnit\Framework\TestCase;

class ProviderValidatorTest extends TestCase
{
    public function testGetListLocalOnly(): void
    {
        $domains = ['example.com', 'test.com'];
        $provider = new FreeEmailValidator(new Policy());
        self::assertEquals($domains, $provider->getList(true, $domains));
    }

    public function testGetExternalListJson(): void
    {
        $provider = new FreeEmailValidator(new Policy());
        $reflectionMethod = new \ReflectionMethod($provider, 'getExternalList');
        $reflectionMethod->setAccessible(true);

        $domains = ['example.com', 'test.com'];
        self::assertEquals($domains, $reflectionMethod->invoke($provider, json_encode($domains), 'json'));
    }

    public function testGetExternalListTxt(): void
    {
        $provider = new FreeEmailValidator(new Policy());
        $reflectionMethod = new \ReflectionMethod($provider, 'getExternalList');
        $reflectionMethod->setAccessible(true);

        $domains = ['example.com', 'test.com'];
        self::assertEquals($domains, $reflectionMethod->invoke($provider, implode("\r\n", $domains), 'txt'));
    }

    public function testGetExternalListInvalidJson(): void
    {
        $provider = new FreeEmailValidator(new Policy());
        $reflectionMethod = new \ReflectionMethod($provider, 'getExternalList');
        $reflectionMethod->setAccessible(true);

        self::assertEquals([], $reflectionMethod->invoke($provider, 'invalid json', 'json'));
    }

    public function testGetExternalListMixedTypes(): void
    {
        $provider = new FreeEmailValidator(new Policy());
        $reflectionMethod = new \ReflectionMethod($provider, 'getExternalList');
        $reflectionMethod->setAccessible(true);

        $mixedData = ['example.com', 123, true, null, 'test.com'];
        $expected = ['example.com', 'test.com'];
        self::assertEquals($expected, $reflectionMethod->invoke($provider, json_encode($mixedData), 'json'));
    }

    public function testGetExternalListEmptyContent(): void
    {
        $provider = new FreeEmailValidator(new Policy());
        $reflectionMethod = new \ReflectionMethod($provider, 'getExternalList');
        $reflectionMethod->setAccessible(true);

        self::assertEquals([], $reflectionMethod->invoke($provider, '', 'txt'));
        self::assertEquals([], $reflectionMethod->invoke($provider, '', 'json'));
    }
}
