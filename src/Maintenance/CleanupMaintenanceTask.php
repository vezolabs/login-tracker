<?php

declare(strict_types=1);

namespace Pimcore\Bundle\LoginTrackerBundle\Maintenance;

use Pimcore\Bundle\LoginTrackerBundle\Service\RetentionService;
use Pimcore\Maintenance\TaskInterface;
use Psr\Log\LoggerInterface;

final class CleanupMaintenanceTask implements TaskInterface
{
    public function __construct(
        private readonly RetentionService $retentionService,
        private readonly ?LoggerInterface $logger = null
    ) {
    }

    public function execute(): void
    {
        try {
            $deleted = $this->retentionService->purgeExpiredRecords();
            $this->logger?->info('[LoginTracker] Maintenance cleanup completed. Deleted: ' . $deleted);
        } catch (\Throwable $e) {
            $this->logger?->error('[LoginTracker] Maintenance cleanup failed: ' . $e->getMessage(), [
                'exception' => $e,
            ]);
        }
    }
}
