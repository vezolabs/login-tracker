<?php

declare(strict_types=1);

namespace Pimcore\Bundle\LoginTrackerBundle\Installer;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Pimcore\Bundle\LoginTrackerBundle\PimcoreLoginTrackerBundle;
use Pimcore\Bundle\LoginTrackerBundle\Repository\LoginRecordRepository;
use Pimcore\Bundle\LoginTrackerBundle\Repository\SettingsRepository;
use Pimcore\Extension\Bundle\Installer\AbstractInstaller;

final class LoginTrackerInstaller extends AbstractInstaller
{
    public function __construct(
        private readonly Connection $connection
    ) {
        parent::__construct();
    }

    public function install(): void
    {
        $schemaManager = $this->connection->createSchemaManager();

        // 1. Create Records Table
        if (!$schemaManager->tablesExist([LoginRecordRepository::TABLE_NAME])) {
            $schema = new Schema([], [], $schemaManager->createSchemaConfig());
            $table = $schema->createTable(LoginRecordRepository::TABLE_NAME);

            $table->addColumn('id', 'integer', ['autoincrement' => true]);
            $table->addColumn('login_time', 'datetime');
            $table->addColumn('username', 'string', ['length' => 190]);
            $table->addColumn('user_id', 'integer', ['notnull' => false]);
            $table->addColumn('display_name', 'string', ['length' => 190, 'notnull' => false]);
            $table->addColumn('status', 'string', ['length' => 20]);
            $table->addColumn('auth_method', 'string', ['length' => 50, 'default' => 'password']);
            $table->addColumn('auth_method_label', 'string', ['length' => 100, 'default' => 'Password / Credentials']);
            $table->addColumn('ip_address', 'string', ['length' => 45, 'notnull' => false]);
            $table->addColumn('raw_ip', 'string', ['length' => 45, 'notnull' => false]);
            $table->addColumn('user_agent', 'text', ['notnull' => false]);
            $table->addColumn('browser', 'string', ['length' => 100, 'notnull' => false]);
            $table->addColumn('os', 'string', ['length' => 100, 'notnull' => false]);
            $table->addColumn('device_type', 'string', ['length' => 50, 'default' => 'desktop']);
            $table->addColumn('failure_reason', 'string', ['length' => 255, 'notnull' => false]);
            $table->addColumn('failure_type', 'string', ['length' => 50, 'notnull' => false]);
            $table->addColumn('firewall', 'string', ['length' => 50, 'notnull' => false]);
            $table->addColumn('route', 'string', ['length' => 100, 'notnull' => false]);
            $table->addColumn('details', 'text', ['notnull' => false]);

            $table->setPrimaryKey(['id']);
            $table->addIndex(['login_time'], 'idx_lt_login_time');
            $table->addIndex(['username'], 'idx_lt_username');
            $table->addIndex(['user_id'], 'idx_lt_user_id');
            $table->addIndex(['status'], 'idx_lt_status');
            $table->addIndex(['auth_method'], 'idx_lt_auth_method');
            $table->addIndex(['ip_address'], 'idx_lt_ip_address');
            $table->addIndex(['login_time', 'status'], 'idx_lt_time_status');

            foreach ($schema->toSql($this->connection->getDatabasePlatform()) as $sql) {
                $this->connection->executeStatement($sql);
            }
        }

        // 2. Create Settings Table
        if (!$schemaManager->tablesExist([SettingsRepository::TABLE_NAME])) {
            $schema = new Schema([], [], $schemaManager->createSchemaConfig());
            $table = $schema->createTable(SettingsRepository::TABLE_NAME);

            $table->addColumn('id', 'integer', ['autoincrement' => true]);
            $table->addColumn('config_key', 'string', ['length' => 100]);
            $table->addColumn('config_value', 'text', ['notnull' => false]);
            $table->addColumn('updated_at', 'datetime');

            $table->setPrimaryKey(['id']);
            $table->addUniqueIndex(['config_key'], 'uniq_lt_config_key');

            foreach ($schema->toSql($this->connection->getDatabasePlatform()) as $sql) {
                $this->connection->executeStatement($sql);
            }

            // Seed default settings
            $this->seedDefaultSettings();
        }

        // 3. Register Permissions in Pimcore
        $this->registerPermissions();
    }

    public function uninstall(): void
    {
        $schemaManager = $this->connection->createSchemaManager();
        $tables = [
            LoginRecordRepository::TABLE_NAME,
            SettingsRepository::TABLE_NAME,
        ];

        foreach ($tables as $table) {
            if ($schemaManager->tablesExist([$table])) {
                $this->connection->executeStatement('DROP TABLE ' . $table);
            }
        }

        // Remove registered permissions
        $this->connection->executeStatement(
            'DELETE FROM users_permission_definitions WHERE `key` IN (?, ?, ?, ?)',
            [
                PimcoreLoginTrackerBundle::PERMISSION_VIEW,
                PimcoreLoginTrackerBundle::PERMISSION_VIEW_SENSITIVE,
                PimcoreLoginTrackerBundle::PERMISSION_EXPORT,
                PimcoreLoginTrackerBundle::PERMISSION_SETTINGS,
            ]
        );
    }

    public function isInstalled(): bool
    {
        $schemaManager = $this->connection->createSchemaManager();

        return $schemaManager->tablesExist([LoginRecordRepository::TABLE_NAME]);
    }

    public function canBeInstalled(): bool
    {
        return !$this->isInstalled();
    }

    public function canBeUninstalled(): bool
    {
        return $this->isInstalled();
    }

    private function seedDefaultSettings(): void
    {
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $defaults = [
            'tracking_enabled' => 'true',
            'track_successful' => 'true',
            'track_failed' => 'true',
            'ip_tracking_mode' => 'full',
            'record_user_agent' => 'true',
            'retention_days' => '90',
            'default_date_range' => '7_days',
            'display_timezone' => 'UTC',
            'alert_failed_threshold' => '5',
            'alert_window_minutes' => '15',
            'alert_ip_threshold' => '10',
            'alert_multi_failure_success' => 'true',
        ];

        foreach ($defaults as $k => $v) {
            $this->connection->executeStatement(
                'INSERT IGNORE INTO ' . SettingsRepository::TABLE_NAME . ' (config_key, config_value, updated_at) VALUES (?, ?, ?)',
                [$k, $v, $now]
            );
        }
    }

    private function registerPermissions(): void
    {
        $permissions = [
            ['key' => PimcoreLoginTrackerBundle::PERMISSION_VIEW, 'category' => 'security'],
            ['key' => PimcoreLoginTrackerBundle::PERMISSION_VIEW_SENSITIVE, 'category' => 'security'],
            ['key' => PimcoreLoginTrackerBundle::PERMISSION_EXPORT, 'category' => 'security'],
            ['key' => PimcoreLoginTrackerBundle::PERMISSION_SETTINGS, 'category' => 'security'],
        ];

        foreach ($permissions as $perm) {
            $exists = (int) $this->connection->fetchOne(
                'SELECT COUNT(*) FROM users_permission_definitions WHERE `key` = ?',
                [$perm['key']]
            );
            if ($exists === 0) {
                $this->connection->executeStatement(
                    'INSERT INTO users_permission_definitions (`key`, `category`) VALUES (?, ?)',
                    [$perm['key'], $perm['category']]
                );
            }
        }
    }
}
