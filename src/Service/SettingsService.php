<?php

declare(strict_types=1);

namespace Pimcore\Bundle\LoginTrackerBundle\Service;

use Pimcore\Bundle\LoginTrackerBundle\Repository\SettingsRepository;
use Psr\Log\LoggerInterface;

class SettingsService
{
    public const string SETTING_TRACKING_ENABLED = 'tracking_enabled';
    public const string SETTING_TRACK_SUCCESSFUL = 'track_successful';
    public const string SETTING_TRACK_FAILED = 'track_failed';
    public const string SETTING_IP_TRACKING_MODE = 'ip_tracking_mode';
    public const string SETTING_RECORD_USER_AGENT = 'record_user_agent';
    public const string SETTING_RETENTION_DAYS = 'retention_days';
    public const string SETTING_DEFAULT_DATE_RANGE = 'default_date_range';
    public const string SETTING_DISPLAY_TIMEZONE = 'display_timezone';
    public const string SETTING_ALERT_FAILED_THRESHOLD = 'alert_failed_threshold';
    public const string SETTING_ALERT_WINDOW_MINUTES = 'alert_window_minutes';
    public const string SETTING_ALERT_IP_THRESHOLD = 'alert_ip_threshold';
    public const string SETTING_ALERT_MULTI_FAILURE_SUCCESS = 'alert_multi_failure_success';

    public const string IP_MODE_FULL = 'full';
    public const string IP_MODE_ANONYMIZED = 'anonymized';
    public const string IP_MODE_DISABLED = 'disabled';

    private const array DEFAULTS = [
        self::SETTING_TRACKING_ENABLED => 'true',
        self::SETTING_TRACK_SUCCESSFUL => 'true',
        self::SETTING_TRACK_FAILED => 'true',
        self::SETTING_IP_TRACKING_MODE => self::IP_MODE_FULL,
        self::SETTING_RECORD_USER_AGENT => 'true',
        self::SETTING_RETENTION_DAYS => '90',
        self::SETTING_DEFAULT_DATE_RANGE => '7_days',
        self::SETTING_DISPLAY_TIMEZONE => 'UTC',
        self::SETTING_ALERT_FAILED_THRESHOLD => '5',
        self::SETTING_ALERT_WINDOW_MINUTES => '15',
        self::SETTING_ALERT_IP_THRESHOLD => '10',
        self::SETTING_ALERT_MULTI_FAILURE_SUCCESS => 'true',
    ];

    private ?array $cache = null;

    public function __construct(
        private readonly SettingsRepository $repository,
        private readonly ?LoggerInterface $logger = null
    ) {
    }

    public function isTrackingEnabled(): bool
    {
        return $this->getBool(self::SETTING_TRACKING_ENABLED, true);
    }

    public function isTrackSuccessfulEnabled(): bool
    {
        return $this->getBool(self::SETTING_TRACK_SUCCESSFUL, true);
    }

    public function isTrackFailedEnabled(): bool
    {
        return $this->getBool(self::SETTING_TRACK_FAILED, true);
    }

    public function getIpTrackingMode(): string
    {
        $mode = $this->getString(self::SETTING_IP_TRACKING_MODE, self::IP_MODE_FULL);
        if (!in_array($mode, [self::IP_MODE_FULL, self::IP_MODE_ANONYMIZED, self::IP_MODE_DISABLED], true)) {
            return self::IP_MODE_FULL;
        }

        return $mode;
    }

    public function isRecordUserAgentEnabled(): bool
    {
        return $this->getBool(self::SETTING_RECORD_USER_AGENT, true);
    }

    public function getRetentionDays(): int
    {
        return max(0, $this->getInt(self::SETTING_RETENTION_DAYS, 90));
    }

    public function getDefaultDateRange(): string
    {
        $range = $this->getString(self::SETTING_DEFAULT_DATE_RANGE, '7_days');
        if (!in_array($range, ['24_hours', '7_days', '30_days', '90_days'], true)) {
            return '7_days';
        }

        return $range;
    }

    public function getDisplayTimezone(): string
    {
        $tz = $this->getString(self::SETTING_DISPLAY_TIMEZONE, 'UTC');
        try {
            new \DateTimeZone($tz);
            return $tz;
        } catch (\Exception) {
            return 'UTC';
        }
    }

    public function getAlertFailedThreshold(): int
    {
        return max(1, $this->getInt(self::SETTING_ALERT_FAILED_THRESHOLD, 5));
    }

    public function getAlertWindowMinutes(): int
    {
        return max(1, $this->getInt(self::SETTING_ALERT_WINDOW_MINUTES, 15));
    }

    public function getAlertIpThreshold(): int
    {
        return max(1, $this->getInt(self::SETTING_ALERT_IP_THRESHOLD, 10));
    }

    public function isAlertMultiFailureSuccessEnabled(): bool
    {
        return $this->getBool(self::SETTING_ALERT_MULTI_FAILURE_SUCCESS, true);
    }

    public function getAllSettings(): array
    {
        $raw = $this->getRawSettings();

        return [
            self::SETTING_TRACKING_ENABLED => filter_var($raw[self::SETTING_TRACKING_ENABLED] ?? 'true', FILTER_VALIDATE_BOOLEAN),
            self::SETTING_TRACK_SUCCESSFUL => filter_var($raw[self::SETTING_TRACK_SUCCESSFUL] ?? 'true', FILTER_VALIDATE_BOOLEAN),
            self::SETTING_TRACK_FAILED => filter_var($raw[self::SETTING_TRACK_FAILED] ?? 'true', FILTER_VALIDATE_BOOLEAN),
            self::SETTING_IP_TRACKING_MODE => $raw[self::SETTING_IP_TRACKING_MODE] ?? self::IP_MODE_FULL,
            self::SETTING_RECORD_USER_AGENT => filter_var($raw[self::SETTING_RECORD_USER_AGENT] ?? 'true', FILTER_VALIDATE_BOOLEAN),
            self::SETTING_RETENTION_DAYS => (int) ($raw[self::SETTING_RETENTION_DAYS] ?? 90),
            self::SETTING_DEFAULT_DATE_RANGE => $raw[self::SETTING_DEFAULT_DATE_RANGE] ?? '7_days',
            self::SETTING_DISPLAY_TIMEZONE => $raw[self::SETTING_DISPLAY_TIMEZONE] ?? 'UTC',
            self::SETTING_ALERT_FAILED_THRESHOLD => (int) ($raw[self::SETTING_ALERT_FAILED_THRESHOLD] ?? 5),
            self::SETTING_ALERT_WINDOW_MINUTES => (int) ($raw[self::SETTING_ALERT_WINDOW_MINUTES] ?? 15),
            self::SETTING_ALERT_IP_THRESHOLD => (int) ($raw[self::SETTING_ALERT_IP_THRESHOLD] ?? 10),
            self::SETTING_ALERT_MULTI_FAILURE_SUCCESS => filter_var($raw[self::SETTING_ALERT_MULTI_FAILURE_SUCCESS] ?? 'true', FILTER_VALIDATE_BOOLEAN),
        ];
    }

    public function updateSettings(array $settings): void
    {
        $toPersist = [];

        if (array_key_exists(self::SETTING_TRACKING_ENABLED, $settings)) {
            $toPersist[self::SETTING_TRACKING_ENABLED] = filter_var($settings[self::SETTING_TRACKING_ENABLED], FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false';
        }
        if (array_key_exists(self::SETTING_TRACK_SUCCESSFUL, $settings)) {
            $toPersist[self::SETTING_TRACK_SUCCESSFUL] = filter_var($settings[self::SETTING_TRACK_SUCCESSFUL], FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false';
        }
        if (array_key_exists(self::SETTING_TRACK_FAILED, $settings)) {
            $toPersist[self::SETTING_TRACK_FAILED] = filter_var($settings[self::SETTING_TRACK_FAILED], FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false';
        }
        if (array_key_exists(self::SETTING_IP_TRACKING_MODE, $settings)) {
            $val = (string) $settings[self::SETTING_IP_TRACKING_MODE];
            if (in_array($val, [self::IP_MODE_FULL, self::IP_MODE_ANONYMIZED, self::IP_MODE_DISABLED], true)) {
                $toPersist[self::SETTING_IP_TRACKING_MODE] = $val;
            }
        }
        if (array_key_exists(self::SETTING_RECORD_USER_AGENT, $settings)) {
            $toPersist[self::SETTING_RECORD_USER_AGENT] = filter_var($settings[self::SETTING_RECORD_USER_AGENT], FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false';
        }
        if (array_key_exists(self::SETTING_RETENTION_DAYS, $settings)) {
            $toPersist[self::SETTING_RETENTION_DAYS] = (string) max(0, min(3650, (int) $settings[self::SETTING_RETENTION_DAYS]));
        }
        if (array_key_exists(self::SETTING_DEFAULT_DATE_RANGE, $settings)) {
            $val = (string) $settings[self::SETTING_DEFAULT_DATE_RANGE];
            if (in_array($val, ['24_hours', '7_days', '30_days', '90_days'], true)) {
                $toPersist[self::SETTING_DEFAULT_DATE_RANGE] = $val;
            }
        }
        if (array_key_exists(self::SETTING_DISPLAY_TIMEZONE, $settings)) {
            $tz = (string) $settings[self::SETTING_DISPLAY_TIMEZONE];
            try {
                new \DateTimeZone($tz);
                $toPersist[self::SETTING_DISPLAY_TIMEZONE] = $tz;
            } catch (\Exception) {
                // Ignore invalid timezone
            }
        }
        if (array_key_exists(self::SETTING_ALERT_FAILED_THRESHOLD, $settings)) {
            $toPersist[self::SETTING_ALERT_FAILED_THRESHOLD] = (string) max(1, min(100, (int) $settings[self::SETTING_ALERT_FAILED_THRESHOLD]));
        }
        if (array_key_exists(self::SETTING_ALERT_WINDOW_MINUTES, $settings)) {
            $toPersist[self::SETTING_ALERT_WINDOW_MINUTES] = (string) max(1, min(1440, (int) $settings[self::SETTING_ALERT_WINDOW_MINUTES]));
        }
        if (array_key_exists(self::SETTING_ALERT_IP_THRESHOLD, $settings)) {
            $toPersist[self::SETTING_ALERT_IP_THRESHOLD] = (string) max(1, min(500, (int) $settings[self::SETTING_ALERT_IP_THRESHOLD]));
        }
        if (array_key_exists(self::SETTING_ALERT_MULTI_FAILURE_SUCCESS, $settings)) {
            $toPersist[self::SETTING_ALERT_MULTI_FAILURE_SUCCESS] = filter_var($settings[self::SETTING_ALERT_MULTI_FAILURE_SUCCESS], FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false';
        }

        if (!empty($toPersist)) {
            $this->repository->setMultiple($toPersist);
            $this->cache = null; // Invalidate cache
        }
    }

    private function getBool(string $key, bool $default): bool
    {
        $raw = $this->getRawSettings();
        if (!isset($raw[$key])) {
            return $default;
        }

        return filter_var($raw[$key], FILTER_VALIDATE_BOOLEAN);
    }

    private function getInt(string $key, int $default): int
    {
        $raw = $this->getRawSettings();
        if (!isset($raw[$key])) {
            return $default;
        }

        return (int) $raw[$key];
    }

    private function getString(string $key, string $default): string
    {
        $raw = $this->getRawSettings();

        return (string) ($raw[$key] ?? $default);
    }

    private function getRawSettings(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        try {
            $fromDb = $this->repository->getAllAsKeyValue();
            $this->cache = array_merge(self::DEFAULTS, $fromDb);
        } catch (\Throwable $e) {
            $this->logger?->warning('Failed to load login tracker settings from database: ' . $e->getMessage());
            $this->cache = self::DEFAULTS;
        }

        return $this->cache;
    }
}
