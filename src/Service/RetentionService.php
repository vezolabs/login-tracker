<?php

declare(strict_types=1);

namespace Pimcore\Bundle\LoginTrackerBundle\Service;

use Pimcore\Bundle\LoginTrackerBundle\Repository\LoginRecordRepository;
use Psr\Log\LoggerInterface;

class RetentionService
{
    public function __construct(
        private readonly LoginRecordRepository $repository,
        private readonly SettingsService $settingsService,
        private readonly ?LoggerInterface $logger = null
    ) {
    }

    /**
     * Purges expired login records according to configured retention days.
     *
     * @param int|null $overrideDays Optional manual override for days to keep
     * @return int Number of deleted records
     */
    public function purgeExpiredRecords(?int $overrideDays = null): int
    {
        $days = $overrideDays !== null ? $overrideDays : $this->settingsService->getRetentionDays();

        if ($days <= 0) {
            $this->logger?->info('[LoginTracker] Retention is set to keep forever (0 days). No records purged.');
            return 0;
        }

        $threshold = new \DateTimeImmutable("-{$days} days");
        $deleted = $this->repository->deleteOlderThan($threshold);

        $this->logger?->info(sprintf(
            '[LoginTracker] Retention purge executed: %d records older than %s (%d days) deleted.',
            $deleted,
            $threshold->format('Y-m-d H:i:s'),
            $days
        ));

        return $deleted;
    }
}
