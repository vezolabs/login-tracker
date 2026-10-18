<?php

declare(strict_types=1);

namespace Pimcore\Bundle\LoginTrackerBundle\Service;

use Pimcore\Bundle\LoginTrackerBundle\Entity\LoginRecord;
use Pimcore\Bundle\LoginTrackerBundle\Repository\LoginRecordRepository;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CsvExportService
{
    public function __construct(
        private readonly LoginRecordRepository $repository
    ) {
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function createStreamedExport(
        array $filters = [],
        bool $canViewSensitive = false,
        string $timezone = 'UTC'
    ): StreamedResponse {
        $tz = new \DateTimeZone($timezone);
        $filename = 'login-activity-' . (new \DateTimeImmutable())->format('Y-m-d-His') . '.csv';

        $response = new StreamedResponse(function () use ($filters, $canViewSensitive, $tz) {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            // UTF-8 BOM for proper spreadsheet opening
            fwrite($handle, "\xEF\xBB\xBF");

            // CSV Headers
            $headers = [
                'ID',
                'Date & Time (' . $tz->getName() . ')',
                'UTC Timestamp',
                'Username',
                'Display Name',
                'Result',
                'Authentication Method',
                'Source IP Address',
                'Browser',
                'Operating System',
                'Device Type',
                'Failure Reason',
                'Failure Category',
                'Route',
            ];

            if ($canViewSensitive) {
                $headers[] = 'Full User Agent';
            }

            fputcsv($handle, $headers);

            // Fetch and stream in batches
            $cursor = $this->repository->getRecordsCursor($filters, 500);
            foreach ($cursor as $record) {
                /** @var LoginRecord $record */
                $displayTime = \DateTimeImmutable::createFromInterface($record->getLoginTime())->setTimezone($tz);
                $ip = $record->getIpAddress();
                if (!$canViewSensitive && $ip !== null) {
                    $ip = LoginRecord::maskIpAddress($ip);
                }

                $row = [
                    $record->getId(),
                    $displayTime->format('Y-m-d H:i:s'),
                    $record->getLoginTime()->format('Y-m-d H:i:s'),
                    self::sanitizeForCsv($record->getUsername()),
                    self::sanitizeForCsv($record->getDisplayName() ?: $record->getUsername()),
                    $record->isSuccess() ? 'Successful' : 'Failed',
                    self::sanitizeForCsv($record->getAuthMethodLabel()),
                    self::sanitizeForCsv($ip ?: 'N/A'),
                    self::sanitizeForCsv($record->getBrowser() ?: 'Unknown'),
                    self::sanitizeForCsv($record->getOs() ?: 'Unknown'),
                    self::sanitizeForCsv($record->getDeviceType() ?: 'desktop'),
                    self::sanitizeForCsv($record->getFailureReason() ?: ''),
                    self::sanitizeForCsv($record->getFailureType() ?: ''),
                    self::sanitizeForCsv($record->getRoute() ?: ''),
                ];

                if ($canViewSensitive) {
                    $row[] = self::sanitizeForCsv($record->getUserAgent() ?: '');
                }

                fputcsv($handle, $row);
            }

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        return $response;
    }

    /**
     * Prevents CSV Formula Injection by prepending unsafe values with a single quote.
     */
    public static function sanitizeForCsv(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        // Formula injection trigger characters
        $firstChar = $value[0];
        if (in_array($firstChar, ['=', '+', '-', '@', "\t", "\r", '%'], true)) {
            return "'" . $value;
        }

        return $value;
    }
}
