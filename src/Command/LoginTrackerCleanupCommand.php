<?php

declare(strict_types=1);

namespace Pimcore\Bundle\LoginTrackerBundle\Command;

use Pimcore\Bundle\LoginTrackerBundle\Service\RetentionService;
use Pimcore\Bundle\LoginTrackerBundle\Service\SettingsService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'login-tracker:cleanup',
    description: 'Purges old login records based on configured retention period'
)]
final class LoginTrackerCleanupCommand extends Command
{
    public function __construct(
        private readonly RetentionService $retentionService,
        private readonly SettingsService $settingsService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'days',
                'd',
                InputOption::VALUE_OPTIONAL,
                'Override retention days (0 to keep all)',
                null
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Pimcore Login Tracker: Retention Cleanup');

        $daysOption = $input->getOption('days');
        $days = $daysOption !== null ? (int) $daysOption : $this->settingsService->getRetentionDays();

        if ($days <= 0) {
            $io->note('Retention is configured to keep all records indefinitely (0 days). No records purged.');
            return Command::SUCCESS;
        }

        $io->text(sprintf('Purging login records older than %d days...', $days));
        $deleted = $this->retentionService->purgeExpiredRecords($days);

        $io->success(sprintf('Retention cleanup complete. %d records purged successfully.', $deleted));

        return Command::SUCCESS;
    }
}
