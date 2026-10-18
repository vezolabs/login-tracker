<?php

declare(strict_types=1);

namespace Pimcore\Bundle\LoginTrackerBundle\Tests\Service;

use PHPUnit\Framework\TestCase;
use Pimcore\Bundle\LoginTrackerBundle\Repository\LoginRecordRepository;
use Pimcore\Bundle\LoginTrackerBundle\Repository\SettingsRepository;
use Pimcore\Bundle\LoginTrackerBundle\Service\SecurityInsightsService;
use Pimcore\Bundle\LoginTrackerBundle\Service\SettingsService;

final class SecurityInsightsServiceTest extends TestCase
{
    public function testComputeInsightsWithRepeatedFailuresAndBruteForce(): void
    {
        $settingsRepo = $this->createStub(SettingsRepository::class);
        $settingsRepo->method('getAllAsKeyValue')->willReturn([
            SettingsService::SETTING_ALERT_FAILED_THRESHOLD => '5',
            SettingsService::SETTING_ALERT_WINDOW_MINUTES => '15',
            SettingsService::SETTING_ALERT_IP_THRESHOLD => '10',
            SettingsService::SETTING_ALERT_MULTI_FAILURE_SUCCESS => 'true',
        ]);
        $settingsService = new SettingsService($settingsRepo);

        $recordRepo = $this->createStub(LoginRecordRepository::class);

        // 1. Repeated failures
        $recordRepo->method('findRepeatedFailures')
            ->willReturn([
                [
                    'username' => 'admin',
                    'fail_count' => 8,
                    'ips' => '192.168.1.100, 192.168.1.101',
                    'first_attempt' => '2026-10-18 12:00:00',
                    'last_attempt' => '2026-10-18 12:10:00',
                ],
            ]);

        // 2. Failures followed by success
        $recordRepo->method('findFailuresFollowedBySuccess')
            ->willReturn([
                [
                    'success_id' => 99,
                    'username' => 'alice',
                    'preceding_failures' => 4,
                    'success_time' => '2026-10-18 12:20:00',
                    'first_failure_time' => '2026-10-18 12:05:00',
                    'success_ip' => '10.0.0.5',
                ],
            ]);

        // 3. Suspicious IP
        $recordRepo->method('findSuspiciousIps')
            ->willReturn([
                [
                    'ip_address' => '203.0.113.50',
                    'fail_count' => 15,
                    'targeted_users_count' => 3,
                    'targeted_users' => 'admin, root, test',
                    'first_attempt' => '2026-10-18 12:00:00',
                    'last_attempt' => '2026-10-18 12:14:00',
                ],
            ]);

        // 4. Bursts
        $recordRepo->method('findUnusualBursts')->willReturn([]);

        $service = new SecurityInsightsService($recordRepo, $settingsService);
        $result = $service->computeInsights(true, 'UTC');

        $this->assertSame(3, $result['total_alerts']);
        $this->assertTrue($result['has_critical']);
        $this->assertCount(3, $result['insights']);

        $types = array_column($result['insights'], 'type');
        $this->assertContains('repeated_failures', $types);
        $this->assertContains('fail_then_success', $types);
        $this->assertContains('suspicious_ip', $types);

        // Verify transparent explanations
        foreach ($result['insights'] as $insight) {
            $this->assertNotEmpty($insight['explanation']);
            $this->assertNotEmpty($insight['suggested_action']);
        }
    }

    public function testMasksIpAddressesWhenSensitivePermissionMissing(): void
    {
        $settingsRepo = $this->createStub(SettingsRepository::class);
        $settingsRepo->method('getAllAsKeyValue')->willReturn([]);
        $settingsService = new SettingsService($settingsRepo);

        $recordRepo = $this->createStub(LoginRecordRepository::class);
        $recordRepo->method('findRepeatedFailures')
            ->willReturn([
                [
                    'username' => 'bob',
                    'fail_count' => 6,
                    'ips' => '192.168.1.100',
                    'first_attempt' => '2026-10-18 12:00:00',
                    'last_attempt' => '2026-10-18 12:10:00',
                ],
            ]);
        $recordRepo->method('findFailuresFollowedBySuccess')->willReturn([]);
        $recordRepo->method('findSuspiciousIps')->willReturn([]);
        $recordRepo->method('findUnusualBursts')->willReturn([]);

        $service = new SecurityInsightsService($recordRepo, $settingsService);
        // Sensitive = false
        $result = $service->computeInsights(false, 'UTC');

        $this->assertStringContainsString('192.168.1.***', $result['insights'][0]['description']);
        $this->assertStringNotContainsString('192.168.1.100', $result['insights'][0]['description']);
    }
}
