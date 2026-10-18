<?php

declare(strict_types=1);

namespace Pimcore\Bundle\LoginTrackerBundle\Tests\Service;

use PHPUnit\Framework\TestCase;
use Pimcore\Bundle\LoginTrackerBundle\Repository\LoginRecordRepository;
use Pimcore\Bundle\LoginTrackerBundle\Repository\SettingsRepository;
use Pimcore\Bundle\LoginTrackerBundle\Service\RetentionService;
use Pimcore\Bundle\LoginTrackerBundle\Service\SettingsService;

final class RetentionServiceTest extends TestCase
{
    public function testPurgeExpiredRecordsWithConfiguredDays(): void
    {
        $settingsRepo = $this->createStub(SettingsRepository::class);
        $settingsRepo->method('getAllAsKeyValue')->willReturn([
            SettingsService::SETTING_RETENTION_DAYS => '30',
        ]);
        $settingsService = new SettingsService($settingsRepo);

        $recordRepo = $this->createMock(LoginRecordRepository::class);
        $recordRepo->expects($this->once())
            ->method('deleteOlderThan')
            ->with($this->callback(function (\DateTimeInterface $dt) {
                // Should be approx 30 days ago
                $diff = (new \DateTimeImmutable())->diff($dt)->days;
                return $diff >= 29 && $diff <= 31;
            }))
            ->willReturn(42);

        $service = new RetentionService($recordRepo, $settingsService);
        $deleted = $service->purgeExpiredRecords();

        $this->assertSame(42, $deleted);
    }

    public function testPurgeWithZeroDaysDoesNotDelete(): void
    {
        $settingsRepo = $this->createStub(SettingsRepository::class);
        $settingsRepo->method('getAllAsKeyValue')->willReturn([
            SettingsService::SETTING_RETENTION_DAYS => '0',
        ]);
        $settingsService = new SettingsService($settingsRepo);

        $recordRepo = $this->createMock(LoginRecordRepository::class);
        $recordRepo->expects($this->never())->method('deleteOlderThan');

        $service = new RetentionService($recordRepo, $settingsService);
        $deleted = $service->purgeExpiredRecords();

        $this->assertSame(0, $deleted);
    }
}
