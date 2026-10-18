<?php

declare(strict_types=1);

namespace Pimcore\Bundle\LoginTrackerBundle\Tests\Service;

use PHPUnit\Framework\TestCase;
use Pimcore\Bundle\LoginTrackerBundle\Entity\LoginRecord;
use Pimcore\Bundle\LoginTrackerBundle\Repository\LoginRecordRepository;
use Pimcore\Bundle\LoginTrackerBundle\Service\CsvExportService;

final class CsvExportServiceTest extends TestCase
{
    public function testSanitizeForCsvPreventsFormulaInjection(): void
    {
        // Formula injection trigger characters
        $this->assertSame("'=cmd|' /C calc'!A0", CsvExportService::sanitizeForCsv("=cmd|' /C calc'!A0"));
        $this->assertSame("'+12345", CsvExportService::sanitizeForCsv("+12345"));
        $this->assertSame("'-5+5", CsvExportService::sanitizeForCsv("-5+5"));
        $this->assertSame("'@SUM(A1:A10)", CsvExportService::sanitizeForCsv("@SUM(A1:A10)"));
        $this->assertSame("'%0A", CsvExportService::sanitizeForCsv("%0A"));
        $this->assertSame("'\ttab", CsvExportService::sanitizeForCsv("\ttab"));
        $this->assertSame("'\rcarriage", CsvExportService::sanitizeForCsv("\rcarriage"));

        // Safe values remain unmodified
        $this->assertSame("normal_username", CsvExportService::sanitizeForCsv("normal_username"));
        $this->assertSame("192.168.1.1", CsvExportService::sanitizeForCsv("192.168.1.1"));
        $this->assertSame("", CsvExportService::sanitizeForCsv(""));
        $this->assertSame("", CsvExportService::sanitizeForCsv(null));
    }

    public function testCreateStreamedExportOutput(): void
    {
        $mockRecord = new LoginRecord();
        $mockRecord->setId(101);
        $mockRecord->setLoginTime(new \DateTimeImmutable('2026-10-18 12:00:00'));
        $mockRecord->setUsername('john.doe');
        $mockRecord->setDisplayName('John Doe');
        $mockRecord->setStatus(LoginRecord::STATUS_SUCCESS);
        $mockRecord->setAuthMethod(LoginRecord::AUTH_METHOD_PASSWORD);
        $mockRecord->setAuthMethodLabel('Password / Credentials');
        $mockRecord->setIpAddress('192.168.1.50');
        $mockRecord->setBrowser('Chrome 124');
        $mockRecord->setOs('macOS');
        $mockRecord->setDeviceType('desktop');
        $mockRecord->setRoute('pimcore_studio_api_login');

        $repo = $this->createMock(LoginRecordRepository::class);
        $repo->expects($this->once())
            ->method('getRecordsCursor')
            ->willReturn(new \ArrayIterator([$mockRecord]));

        $service = new CsvExportService($repo);
        $response = $service->createStreamedExport([], false, 'UTC');

        $this->assertSame('text/csv; charset=UTF-8', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment; filename="login-activity-', (string) $response->headers->get('Content-Disposition'));

        // Capture streamed output
        ob_start();
        $response->sendContent();
        $output = ob_get_clean();

        $this->assertNotEmpty($output);
        $this->assertStringContainsString('Date & Time (UTC)', $output);
        $this->assertStringContainsString('john.doe', $output);
        $this->assertStringContainsString('John Doe', $output);
        $this->assertStringContainsString('Successful', $output);
        // With sensitive view false, IP is masked
        $this->assertStringContainsString('192.168.1.***', $output);
    }
}
