<?php

declare(strict_types=1);

namespace Pimcore\Bundle\LoginTrackerBundle\Repository;

use Doctrine\DBAL\Connection;

class SettingsRepository
{
    public const string TABLE_NAME = 'plugin_login_tracker_settings';

    public function __construct(
        private readonly Connection $connection
    ) {
    }

    /**
     * @return array<string, string|null>
     */
    public function getAllAsKeyValue(): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT config_key, config_value FROM ' . self::TABLE_NAME
        );

        $result = [];
        foreach ($rows as $row) {
            $result[$row['config_key']] = $row['config_value'];
        }

        return $result;
    }

    public function get(string $key): ?string
    {
        $val = $this->connection->fetchOne(
            'SELECT config_value FROM ' . self::TABLE_NAME . ' WHERE config_key = ?',
            [$key]
        );

        return $val !== false ? (string) $val : null;
    }

    public function set(string $key, ?string $value): void
    {
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $exists = (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM ' . self::TABLE_NAME . ' WHERE config_key = ?',
            [$key]
        );

        if ($exists > 0) {
            $this->connection->executeStatement(
                'UPDATE ' . self::TABLE_NAME . ' SET config_value = ?, updated_at = ? WHERE config_key = ?',
                [$value, $now, $key]
            );
        } else {
            $this->connection->executeStatement(
                'INSERT INTO ' . self::TABLE_NAME . ' (config_key, config_value, updated_at) VALUES (?, ?, ?)',
                [$key, $value, $now]
            );
        }
    }

    /**
     * @param array<string, string|null> $settings
     */
    public function setMultiple(array $settings): void
    {
        foreach ($settings as $key => $value) {
            $this->set($key, $value);
        }
    }
}
