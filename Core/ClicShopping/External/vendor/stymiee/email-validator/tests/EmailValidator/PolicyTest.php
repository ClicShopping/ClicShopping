<?php

namespace EmailValidator\Tests;

use EmailValidator\Policy;
use PHPUnit\Framework\TestCase;

class PolicyTest extends TestCase
{
    public function testDefaultValues(): void
    {
        $policy = new Policy();

        self::assertTrue($policy->validateMxRecord());
        self::assertFalse($policy->checkBannedListedEmail());
        self::assertFalse($policy->checkDisposableEmail());
        self::assertFalse($policy->checkFreeEmail());
        self::assertFalse($policy->checkDisposableLocalListOnly());
        self::assertFalse($policy->checkFreeLocalListOnly());
        self::assertIsArray($policy->getBannedList());
        self::assertEmpty($policy->getBannedList());
        self::assertIsArray($policy->getDisposableList());
        self::assertEmpty($policy->getDisposableList());
        self::assertIsArray($policy->getFreeList());
        self::assertEmpty($policy->getFreeList());
    }

    public function booleanConfigDataProvider(): array
    {
        return [
            [true, true],
            [false, false],
            [1, true],
            [0, false],
            ['true', true],
            ['test', true],
        ];
    }

    /**
     * @dataProvider booleanConfigDataProvider
     * @param $config
     * @param bool $setting
     */
    public function testNonDefaultBooleanSetting($config, bool $setting): void
    {
        $policy = new Policy(['checkMxRecords' => $config]);
        self::assertEquals($setting, $policy->validateMxRecord());
    }
}
