<?php

declare(strict_types=1);

namespace Pimcore\Bundle\LoginTrackerBundle\Tests\Service;

use PHPUnit\Framework\TestCase;
use Pimcore\Bundle\LoginTrackerBundle\Repository\SettingsRepository;
use Pimcore\Bundle\LoginTrackerBundle\Service\SettingsService;

final class SettingsServiceTest extends TestCase
{
    public function testDefaultSettingsFallback(): void
    {
        $repo = $this->createMock(SettingsRepository::class);
        $repo->expects($this->once())
            ->method('getAllAsKeyValue')
            ->willReturn([]);

        $service = new SettingsService($repo);

        $this->assertTrue($service->isTrackingEnabled());
        $this->assertTrue($service->isTrackSuccessfulEnabled());
        $this->assertTrue($service->isTrackFailedEnabled());
        $this->assertSame('full', $service->getIpTrackingMode());
        $this->assertTrue($service->isRecordUserAgentEnabled());
        $this->assertSame(90, $service->getRetentionDays());
        $this->assertSame('7_days', $service->getDefaultDateRange());
        $this->assertSame('UTC', $service->getDisplayTimezone());
        $this->assertSame(5, $service->getAlertFailedThreshold());
        $this->assertSame(15, $service->getAlertWindowMinutes());
        $this->assertSame(10, $service->getAlertIpThreshold());
        $this->assertTrue($service->isAlertMultiFailureSuccessEnabled());
    }

    public function testUpdateSettingsPersistsAndCaches(): void
    {
        $storage = [];
        $repo = $this->createStub(SettingsRepository::class);
        $repo->method('setMultiple')
            ->willReturnCallback(function (array $settings) use (&$storage) {
                $storage = array_merge($storage, $settings);
            });
        $repo->method('getAllAsKeyValue')
            ->willReturnCallback(function () use (&$storage) {
                return $storage;
            });

        $service = new SettingsService($repo);

        $service->updateSettings([
            SettingsService::SETTING_TRACKING_ENABLED => false,
            SettingsService::SETTING_IP_TRACKING_MODE => 'anonymized',
            SettingsService::SETTING_RETENTION_DAYS => 30,
            SettingsService::SETTING_DISPLAY_TIMEZONE => 'Europe/Vienna',
            SettingsService::SETTING_ALERT_FAILED_THRESHOLD => 3,
        ]);

        $this->assertFalse($service->isTrackingEnabled());
        $this->assertSame('anonymized', $service->getIpTrackingMode());
        $this->assertSame(30, $service->getRetentionDays());
        $this->assertSame('Europe/Vienna', $service->getDisplayTimezone());
        $this->assertSame(3, $service->getAlertFailedThreshold());
    }

    public function testInvalidTimezoneSafelyFallsBack(): void
    {
        $repo = $this->createStub(SettingsRepository::class);
        $repo->method('getAllAsKeyValue')
            ->willReturn([
                SettingsService::SETTING_DISPLAY_TIMEZONE => 'Invalid/NonExistent_Zone',
            ]);

        $service = new SettingsService($repo);
        $this->assertSame('UTC', $service->getDisplayTimezone());
    }
}
