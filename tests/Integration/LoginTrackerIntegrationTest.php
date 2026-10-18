<?php

declare(strict_types=1);

namespace Pimcore\Bundle\LoginTrackerBundle\Tests\Integration;

use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use Pimcore\Bundle\LoginTrackerBundle\Entity\LoginRecord;
use Pimcore\Bundle\LoginTrackerBundle\Repository\LoginRecordRepository;
use Pimcore\Bundle\LoginTrackerBundle\Repository\SettingsRepository;
use Pimcore\Bundle\LoginTrackerBundle\Service\RetentionService;
use Pimcore\Bundle\LoginTrackerBundle\Service\SecurityInsightsService;
use Pimcore\Bundle\LoginTrackerBundle\Service\SettingsService;

final class LoginTrackerIntegrationTest extends TestCase
{
    private ?\Doctrine\DBAL\Connection $connection = null;
    private ?LoginRecordRepository $repository = null;
    private ?SettingsRepository $settingsRepository = null;

    protected function setUp(): void
    {
        try {
            $params = [
                'driver' => 'pdo_mysql',
                'host' => 'db',
                'port' => 3306,
                'user' => 'pimcore',
                'password' => 'pimcore',
                'dbname' => 'pimcore',
            ];
            $this->connection = DriverManager::getConnection($params);
            $this->repository = new LoginRecordRepository($this->connection);
            $this->settingsRepository = new SettingsRepository($this->connection);
        } catch (\Throwable $e) {
            $this->markTestSkipped('Database connection unavailable: ' . $e->getMessage());
        }
    }

    protected function tearDown(): void
    {
        if ($this->connection !== null) {
            // Clean up any test records
            $this->connection->executeStatement(
                "DELETE FROM " . LoginRecordRepository::TABLE_NAME . " WHERE username LIKE 'int_test_%'"
            );
        }
    }

    public function testSaveAndRetrieveLoginRecords(): void
    {
        $record = new LoginRecord();
        $record->setLoginTime(new \DateTimeImmutable('2026-10-18 10:00:00'));
        $record->setUsername('int_test_user');
        $record->setDisplayName('Integration Test User');
        $record->setStatus(LoginRecord::STATUS_SUCCESS);
        $record->setAuthMethod(LoginRecord::AUTH_METHOD_PASSWORD);
        $record->setAuthMethodLabel('Password / Credentials');
        $record->setIpAddress('198.51.100.99');
        $record->setBrowser('Chrome 124');
        $record->setOs('macOS');
        $record->setDeviceType('desktop');
        $record->setFirewall('pimcore_studio');
        $record->setRoute('pimcore_studio_api_login');

        $this->repository->save($record);
        $this->assertNotNull($record->getId());

        $fetched = $this->repository->findById($record->getId());
        $this->assertNotNull($fetched);
        $this->assertSame('int_test_user', $fetched->getUsername());
        $this->assertSame(LoginRecord::STATUS_SUCCESS, $fetched->getStatus());
        $this->assertSame('198.51.100.99', $fetched->getIpAddress());
    }

    public function testFindPaginatedWithFilters(): void
    {
        // Insert a success and a failure
        $rec1 = new LoginRecord();
        $rec1->setLoginTime(new \DateTimeImmutable('2026-10-18 09:00:00'));
        $rec1->setUsername('int_test_filter_user');
        $rec1->setStatus(LoginRecord::STATUS_SUCCESS);
        $this->repository->save($rec1);

        $rec2 = new LoginRecord();
        $rec2->setLoginTime(new \DateTimeImmutable('2026-10-18 09:30:00'));
        $rec2->setUsername('int_test_filter_user');
        $rec2->setStatus(LoginRecord::STATUS_FAILED);
        $rec2->setFailureReason('Invalid username or password');
        $this->repository->save($rec2);

        // Filter by username and status=failed
        $result = $this->repository->findPaginated([
            'username' => 'int_test_filter_user',
            'status' => 'failed',
        ]);

        $this->assertSame(1, $result['total']);
        $this->assertCount(1, $result['items']);
        $this->assertSame('int_test_filter_user', $result['items'][0]->getUsername());
        $this->assertSame(LoginRecord::STATUS_FAILED, $result['items'][0]->getStatus());
    }

    public function testDashboardMetricsAggregation(): void
    {
        $rec = new LoginRecord();
        $rec->setLoginTime(new \DateTimeImmutable());
        $rec->setUsername('int_test_metrics');
        $rec->setStatus(LoginRecord::STATUS_SUCCESS);
        $this->repository->save($rec);

        $metrics = $this->repository->getDashboardMetrics();
        $this->assertGreaterThanOrEqual(1, $metrics['total_attempts']);
        $this->assertGreaterThanOrEqual(1, $metrics['successful_logins']);
        $this->assertGreaterThanOrEqual(1, $metrics['unique_users']);
    }

    public function testSettingsPersistenceInDatabase(): void
    {
        $this->settingsRepository->set('test_key', 'test_value_123');
        $val = $this->settingsRepository->get('test_key');
        $this->assertSame('test_value_123', $val);

        $this->settingsRepository->set('test_key', 'updated_value_456');
        $valUpdated = $this->settingsRepository->get('test_key');
        $this->assertSame('updated_value_456', $valUpdated);
    }

    public function testRetentionPurgeInDatabase(): void
    {
        // Insert old record
        $oldRecord = new LoginRecord();
        $oldRecord->setLoginTime(new \DateTimeImmutable('-100 days'));
        $oldRecord->setUsername('int_test_old');
        $oldRecord->setStatus(LoginRecord::STATUS_SUCCESS);
        $this->repository->save($oldRecord);

        $deleted = $this->repository->deleteOlderThan(new \DateTimeImmutable('-90 days'));
        $this->assertGreaterThanOrEqual(1, $deleted);

        $check = $this->repository->findById($oldRecord->getId());
        $this->assertNull($check);
    }
}
