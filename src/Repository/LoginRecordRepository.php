<?php

declare(strict_types=1);

namespace Pimcore\Bundle\LoginTrackerBundle\Repository;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Pimcore\Bundle\LoginTrackerBundle\Entity\LoginRecord;

class LoginRecordRepository
{
    public const string TABLE_NAME = 'plugin_login_tracker_records';

    public function __construct(
        private readonly Connection $connection
    ) {
    }

    public function save(LoginRecord $record): void
    {
        $data = [
            'login_time' => $record->getLoginTime()->format('Y-m-d H:i:s'),
            'username' => mb_substr($record->getUsername(), 0, 190),
            'user_id' => $record->getUserId(),
            'display_name' => $record->getDisplayName() ? mb_substr($record->getDisplayName(), 0, 190) : null,
            'status' => $record->getStatus(),
            'auth_method' => $record->getAuthMethod(),
            'auth_method_label' => mb_substr($record->getAuthMethodLabel(), 0, 100),
            'ip_address' => $record->getIpAddress(),
            'raw_ip' => $record->getRawIp(),
            'user_agent' => $record->getUserAgent(),
            'browser' => $record->getBrowser() ? mb_substr($record->getBrowser(), 0, 100) : null,
            'os' => $record->getOs() ? mb_substr($record->getOs(), 0, 100) : null,
            'device_type' => $record->getDeviceType() ? mb_substr($record->getDeviceType(), 0, 50) : null,
            'failure_reason' => $record->getFailureReason() ? mb_substr($record->getFailureReason(), 0, 255) : null,
            'failure_type' => $record->getFailureType() ? mb_substr($record->getFailureType(), 0, 50) : null,
            'firewall' => $record->getFirewall() ? mb_substr($record->getFirewall(), 0, 50) : null,
            'route' => $record->getRoute() ? mb_substr($record->getRoute(), 0, 100) : null,
            'details' => $record->getDetails() ? json_encode($record->getDetails(), JSON_THROW_ON_ERROR) : null,
        ];

        $this->connection->insert(self::TABLE_NAME, $data);
        $lastId = (int) $this->connection->lastInsertId();
        $record->setId($lastId);
    }

    public function findById(int $id): ?LoginRecord
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM ' . self::TABLE_NAME . ' WHERE id = ?',
            [$id]
        );

        return $row ? $this->hydrateRow($row) : null;
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{items: LoginRecord[], total: int, page: int, limit: int, pages: int}
     */
    public function findPaginated(
        array $filters = [],
        int $page = 1,
        int $limit = 25,
        string $sortBy = 'login_time',
        string $sortOrder = 'DESC'
    ): array {
        $page = max(1, $page);
        $limit = max(1, min(200, $limit));
        $offset = ($page - 1) * $limit;

        $allowedSortFields = [
            'id' => 'id',
            'login_time' => 'login_time',
            'username' => 'username',
            'status' => 'status',
            'auth_method' => 'auth_method',
            'ip_address' => 'ip_address',
        ];

        $sortColumn = $allowedSortFields[$sortBy] ?? 'login_time';
        $order = strtoupper($sortOrder) === 'ASC' ? 'ASC' : 'DESC';

        // Count query
        $countQb = $this->connection->createQueryBuilder();
        $countQb->select('COUNT(*)')->from(self::TABLE_NAME, 'l');
        $this->applyFilters($countQb, $filters);
        $total = (int) $countQb->executeQuery()->fetchOne();

        // Data query
        $dataQb = $this->connection->createQueryBuilder();
        $dataQb->select('l.*')
            ->from(self::TABLE_NAME, 'l')
            ->orderBy('l.' . $sortColumn, $order)
            ->setFirstResult($offset)
            ->setMaxResults($limit);
        $this->applyFilters($dataQb, $filters);

        $rows = $dataQb->executeQuery()->fetchAllAssociative();
        $items = array_map([$this, 'hydrateRow'], $rows);

        $pages = $total > 0 ? (int) ceil($total / $limit) : 1;

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'pages' => $pages,
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return \Traversable<LoginRecord>
     */
    public function getRecordsCursor(array $filters = [], int $batchSize = 500): \Traversable
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select('l.*')
            ->from(self::TABLE_NAME, 'l')
            ->orderBy('l.login_time', 'DESC');
        $this->applyFilters($qb, $filters);

        $offset = 0;
        do {
            $batchQb = clone $qb;
            $batchQb->setFirstResult($offset)->setMaxResults($batchSize);
            $rows = $batchQb->executeQuery()->fetchAllAssociative();

            foreach ($rows as $row) {
                yield $this->hydrateRow($row);
            }

            $count = count($rows);
            $offset += $count;
        } while ($count === $batchSize);
    }

    /**
     * Aggregate dashboard summary metrics.
     */
    public function getDashboardMetrics(?\DateTimeInterface $from = null, ?\DateTimeInterface $to = null): array
    {
        $qb = $this->connection->createQueryBuilder();
        $qb->select(
            'COUNT(*) as total_attempts',
            "SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as successful_logins",
            "SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_logins",
            'COUNT(DISTINCT username) as unique_users'
        )->from(self::TABLE_NAME);

        if ($from !== null) {
            $qb->andWhere('login_time >= :from')->setParameter('from', $from->format('Y-m-d H:i:s'));
        }
        if ($to !== null) {
            $qb->andWhere('login_time <= :to')->setParameter('to', $to->format('Y-m-d H:i:s'));
        }

        $row = $qb->executeQuery()->fetchAssociative() ?: [];

        $total = (int) ($row['total_attempts'] ?? 0);
        $success = (int) ($row['successful_logins'] ?? 0);
        $failed = (int) ($row['failed_logins'] ?? 0);
        $unique = (int) ($row['unique_users'] ?? 0);
        $successRate = $total > 0 ? round(($success / $total) * 100, 1) : 100.0;

        // Most recent activity
        $lastRow = $this->connection->fetchAssociative(
            'SELECT login_time, username, display_name, status, auth_method_label, ip_address FROM ' . self::TABLE_NAME . ' ORDER BY login_time DESC LIMIT 1'
        );

        // Recent failed in last 24h
        $last24h = (new \DateTimeImmutable('-24 hours'))->format('Y-m-d H:i:s');
        $failedLast24h = (int) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM " . self::TABLE_NAME . " WHERE status = 'failed' AND login_time >= ?",
            [$last24h]
        );

        return [
            'total_attempts' => $total,
            'successful_logins' => $success,
            'failed_logins' => $failed,
            'success_rate' => $successRate,
            'unique_users' => $unique,
            'failed_last_24h' => $failedLast24h,
            'most_recent' => $lastRow ? [
                'login_time' => $lastRow['login_time'],
                'username' => $lastRow['username'],
                'display_name' => $lastRow['display_name'] ?: $lastRow['username'],
                'status' => $lastRow['status'],
                'auth_method_label' => $lastRow['auth_method_label'],
                'ip_address' => $lastRow['ip_address'],
            ] : null,
        ];
    }

    /**
     * Timeline data grouped by day or hour for chart rendering.
     */
    public function getActivityTimeline(?\DateTimeInterface $from = null, ?\DateTimeInterface $to = null): array
    {
        $from = $from ?: new \DateTimeImmutable('-7 days');
        $to = $to ?: new \DateTimeImmutable();

        $diffDays = $to->diff($from)->days;
        $groupByHour = $diffDays <= 2;

        $format = $groupByHour ? '%Y-%m-%d %H:00:00' : '%Y-%m-%d';
        $sql = "SELECT 
                    DATE_FORMAT(login_time, '{$format}') as bucket,
                    SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as success_count,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_count,
                    COUNT(*) as total_count
                FROM " . self::TABLE_NAME . "
                WHERE login_time >= :from AND login_time <= :to
                GROUP BY bucket
                ORDER BY bucket ASC";

        $rows = $this->connection->fetchAllAssociative($sql, [
            'from' => $from->format('Y-m-d H:i:s'),
            'to' => $to->format('Y-m-d H:i:s'),
        ]);

        return array_map(static function (array $r): array {
            return [
                'bucket' => (string) $r['bucket'],
                'success' => (int) $r['success_count'],
                'failed' => (int) $r['failed_count'],
                'total' => (int) $r['total_count'],
            ];
        }, $rows);
    }

    /**
     * User-specific login history summary.
     */
    public function getUserSummary(string $username): array
    {
        $stats = $this->connection->fetchAssociative(
            "SELECT 
                COUNT(*) as total_logins,
                SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as successful_logins,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_logins
             FROM " . self::TABLE_NAME . " WHERE username = ?",
            [$username]
        ) ?: [];

        $lastSuccess = $this->connection->fetchAssociative(
            "SELECT * FROM " . self::TABLE_NAME . " WHERE username = ? AND status = 'success' ORDER BY login_time DESC LIMIT 1",
            [$username]
        );

        $lastFailed = $this->connection->fetchAssociative(
            "SELECT * FROM " . self::TABLE_NAME . " WHERE username = ? AND status = 'failed' ORDER BY login_time DESC LIMIT 1",
            [$username]
        );

        return [
            'username' => $username,
            'total_logins' => (int) ($stats['total_logins'] ?? 0),
            'successful_logins' => (int) ($stats['successful_logins'] ?? 0),
            'failed_logins' => (int) ($stats['failed_logins'] ?? 0),
            'last_success' => $lastSuccess ? $this->hydrateRow($lastSuccess) : null,
            'last_failed' => $lastFailed ? $this->hydrateRow($lastFailed) : null,
        ];
    }

    /**
     * Retrieve recent history for a specific user.
     * @return LoginRecord[]
     */
    public function findUserHistory(string $username, int $limit = 50): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT * FROM ' . self::TABLE_NAME . ' WHERE username = ? ORDER BY login_time DESC LIMIT ' . max(1, min(500, $limit)),
            [$username]
        );

        return array_map([$this, 'hydrateRow'], $rows);
    }

    /**
     * Detect repeated failed login attempts for users within a time window.
     */
    public function findRepeatedFailures(int $threshold = 5, int $windowMinutes = 15): array
    {
        $since = (new \DateTimeImmutable("-{$windowMinutes} minutes"))->format('Y-m-d H:i:s');

        $sql = "SELECT 
                    username, 
                    COUNT(*) as fail_count, 
                    MAX(login_time) as last_attempt, 
                    MIN(login_time) as first_attempt,
                    GROUP_CONCAT(DISTINCT ip_address SEPARATOR ', ') as ips,
                    GROUP_CONCAT(DISTINCT failure_reason SEPARATOR '; ') as reasons
                FROM " . self::TABLE_NAME . "
                WHERE status = 'failed' AND login_time >= :since
                GROUP BY username
                HAVING fail_count >= :threshold
                ORDER BY fail_count DESC";

        return $this->connection->fetchAllAssociative($sql, [
            'since' => $since,
            'threshold' => $threshold,
        ]);
    }

    /**
     * Detect multiple failed logins followed by a successful login.
     */
    public function findFailuresFollowedBySuccess(int $windowMinutes = 60, int $minFailures = 3): array
    {
        $since = (new \DateTimeImmutable("-{$windowMinutes} minutes"))->format('Y-m-d H:i:s');

        // Look for recent successful logins that had multiple failures shortly before
        $sql = "SELECT s.id as success_id, s.username, s.login_time as success_time, s.ip_address as success_ip,
                       COUNT(f.id) as preceding_failures,
                       MIN(f.login_time) as first_failure_time,
                       MAX(f.login_time) as last_failure_time
                FROM " . self::TABLE_NAME . " s
                JOIN " . self::TABLE_NAME . " f 
                  ON f.username = s.username 
                 AND f.status = 'failed' 
                 AND f.login_time < s.login_time 
                 AND f.login_time >= :since
                WHERE s.status = 'success' AND s.login_time >= :since
                GROUP BY s.id, s.username, s.login_time, s.ip_address
                HAVING preceding_failures >= :min_failures
                ORDER BY s.login_time DESC";

        return $this->connection->fetchAllAssociative($sql, [
            'since' => $since,
            'min_failures' => $minFailures,
        ]);
    }

    /**
     * Detect repeated failed attempts originating from the same IP address.
     */
    public function findSuspiciousIps(int $threshold = 10, int $windowMinutes = 15): array
    {
        $since = (new \DateTimeImmutable("-{$windowMinutes} minutes"))->format('Y-m-d H:i:s');

        $sql = "SELECT 
                    ip_address, 
                    COUNT(*) as fail_count,
                    COUNT(DISTINCT username) as targeted_users_count,
                    GROUP_CONCAT(DISTINCT username SEPARATOR ', ') as targeted_users,
                    MAX(login_time) as last_attempt,
                    MIN(login_time) as first_attempt
                FROM " . self::TABLE_NAME . "
                WHERE status = 'failed' AND ip_address IS NOT NULL AND ip_address != '' AND login_time >= :since
                GROUP BY ip_address
                HAVING fail_count >= :threshold
                ORDER BY fail_count DESC";

        return $this->connection->fetchAllAssociative($sql, [
            'since' => $since,
            'threshold' => $threshold,
        ]);
    }

    /**
     * Detect unusually high login activity across the system.
     */
    public function findUnusualBursts(int $threshold = 20, int $windowMinutes = 15): array
    {
        $since = (new \DateTimeImmutable("-{$windowMinutes} minutes"))->format('Y-m-d H:i:s');

        $totalInWindow = (int) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM " . self::TABLE_NAME . " WHERE login_time >= ?",
            [$since]
        );

        if ($totalInWindow >= $threshold) {
            return [
                'window_minutes' => $windowMinutes,
                'total_attempts' => $totalInWindow,
                'threshold' => $threshold,
                'since' => $since,
            ];
        }

        return [];
    }

    /**
     * Cleanup records older than retention threshold.
     */
    public function deleteOlderThan(\DateTimeInterface $threshold): int
    {
        return (int) $this->connection->executeStatement(
            'DELETE FROM ' . self::TABLE_NAME . ' WHERE login_time < ?',
            [$threshold->format('Y-m-d H:i:s')]
        );
    }

    public function countTotal(): int
    {
        return (int) $this->connection->fetchOne('SELECT COUNT(*) FROM ' . self::TABLE_NAME);
    }

    /**
     * Returns distinct usernames for auto-complete and user search.
     * @return string[]
     */
    public function getDistinctUsernames(int $limit = 100): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT DISTINCT username FROM ' . self::TABLE_NAME . ' ORDER BY username ASC LIMIT ' . max(1, min(500, $limit))
        );

        return array_column($rows, 'username');
    }

    private function applyFilters(QueryBuilder $qb, array $filters): void
    {
        if (!empty($filters['date_from'])) {
            $qb->andWhere('l.login_time >= :date_from')
               ->setParameter('date_from', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $qb->andWhere('l.login_time <= :date_to')
               ->setParameter('date_to', $filters['date_to']);
        }

        if (!empty($filters['username'])) {
            $qb->andWhere('l.username LIKE :username')
               ->setParameter('username', '%' . $filters['username'] . '%');
        }

        if (!empty($filters['user_id'])) {
            $qb->andWhere('l.user_id = :user_id')
               ->setParameter('user_id', (int) $filters['user_id']);
        }

        if (!empty($filters['status']) && in_array($filters['status'], ['success', 'failed'], true)) {
            $qb->andWhere('l.status = :status')
               ->setParameter('status', $filters['status']);
        }

        if (!empty($filters['auth_method'])) {
            $qb->andWhere('l.auth_method = :auth_method')
               ->setParameter('auth_method', $filters['auth_method']);
        }

        if (!empty($filters['ip_address'])) {
            $qb->andWhere('(l.ip_address LIKE :ip OR l.raw_ip LIKE :ip)')
               ->setParameter('ip', '%' . $filters['ip_address'] . '%');
        }

        if (!empty($filters['search'])) {
            $term = '%' . $filters['search'] . '%';
            $qb->andWhere('(l.username LIKE :search OR l.display_name LIKE :search OR l.ip_address LIKE :search OR l.failure_reason LIKE :search)')
               ->setParameter('search', $term);
        }
    }

    private function hydrateRow(array $row): LoginRecord
    {
        $record = new LoginRecord();
        $record->setId((int) $row['id']);
        $record->setLoginTime(new \DateTimeImmutable($row['login_time']));
        $record->setUsername((string) $row['username']);
        $record->setUserId($row['user_id'] !== null ? (int) $row['user_id'] : null);
        $record->setDisplayName($row['display_name'] ? (string) $row['display_name'] : null);
        $record->setStatus((string) $row['status']);
        $record->setAuthMethod((string) $row['auth_method']);
        $record->setAuthMethodLabel((string) $row['auth_method_label']);
        $record->setIpAddress($row['ip_address'] ? (string) $row['ip_address'] : null);
        $record->setRawIp($row['raw_ip'] ? (string) $row['raw_ip'] : null);
        $record->setUserAgent($row['user_agent'] ? (string) $row['user_agent'] : null);
        $record->setBrowser($row['browser'] ? (string) $row['browser'] : null);
        $record->setOs($row['os'] ? (string) $row['os'] : null);
        $record->setDeviceType($row['device_type'] ? (string) $row['device_type'] : 'desktop');
        $record->setFailureReason($row['failure_reason'] ? (string) $row['failure_reason'] : null);
        $record->setFailureType($row['failure_type'] ? (string) $row['failure_type'] : null);
        $record->setFirewall($row['firewall'] ? (string) $row['firewall'] : null);
        $record->setRoute($row['route'] ? (string) $row['route'] : null);

        if (!empty($row['details'])) {
            $details = json_decode((string) $row['details'], true);
            $record->setDetails(is_array($details) ? $details : null);
        }

        return $record;
    }
}
