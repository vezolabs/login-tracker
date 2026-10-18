<?php

declare(strict_types=1);

namespace Pimcore\Bundle\LoginTrackerBundle\Service;

use Pimcore\Bundle\LoginTrackerBundle\Entity\LoginRecord;
use Pimcore\Bundle\LoginTrackerBundle\Repository\LoginRecordRepository;

class SecurityInsightsService
{
    public function __construct(
        private readonly LoginRecordRepository $repository,
        private readonly SettingsService $settingsService
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function computeInsights(bool $canViewSensitive = false, string $timezone = 'UTC'): array
    {
        $failedThreshold = $this->settingsService->getAlertFailedThreshold();
        $windowMinutes = $this->settingsService->getAlertWindowMinutes();
        $ipThreshold = $this->settingsService->getAlertIpThreshold();
        $multiFailSuccessEnabled = $this->settingsService->isAlertMultiFailureSuccessEnabled();

        $insights = [];

        // 1. Repeated Failed Login Attempts
        $repeatedFailures = $this->repository->findRepeatedFailures($failedThreshold, $windowMinutes);
        foreach ($repeatedFailures as $rf) {
            $username = (string) $rf['username'];
            $failCount = (int) $rf['fail_count'];
            $ips = (string) ($rf['ips'] ?? '');
            if (!$canViewSensitive && !empty($ips)) {
                $ips = implode(', ', array_map(static fn($ip) => LoginRecord::maskIpAddress(trim($ip)), explode(',', $ips)));
            }

            $insights[] = [
                'id' => 'repeated_failures_' . md5($username),
                'type' => 'repeated_failures',
                'severity' => $failCount >= ($failedThreshold * 2) ? 'danger' : 'warning',
                'title' => 'Repeated Failed Logins for ' . $username,
                'description' => sprintf(
                    'The account "%s" experienced %d failed login attempts within the last %d minutes from IP address(es): %s.',
                    $username,
                    $failCount,
                    $windowMinutes,
                    $ips ?: 'Unknown IP'
                ),
                'explanation' => 'Multiple consecutive failed attempts often indicate that a user forgot their password or an automated entity is attempting credential guessing.',
                'suggested_action' => 'Contact the user to verify if they are experiencing login trouble, or check if the source IP is recognized.',
                'target_username' => $username,
                'target_ip' => null,
                'count' => $failCount,
                'threshold' => $failedThreshold,
                'window_minutes' => $windowMinutes,
                'first_seen' => $this->formatTime((string) $rf['first_attempt'], $timezone),
                'last_seen' => $this->formatTime((string) $rf['last_attempt'], $timezone),
            ];
        }

        // 2. Multiple Failures Followed by Success
        if ($multiFailSuccessEnabled) {
            $multiFailSuccess = $this->repository->findFailuresFollowedBySuccess(60, 3);
            foreach ($multiFailSuccess as $mfs) {
                $username = (string) $mfs['username'];
                $preceding = (int) $mfs['preceding_failures'];
                $successIp = (string) ($mfs['success_ip'] ?? '');
                if (!$canViewSensitive && !empty($successIp)) {
                    $successIp = LoginRecord::maskIpAddress($successIp);
                }

                $insights[] = [
                    'id' => 'fail_then_success_' . (int) $mfs['success_id'],
                    'type' => 'fail_then_success',
                    'severity' => 'danger',
                    'title' => 'Login Succeeded After Failures for ' . $username,
                    'description' => sprintf(
                        'User "%s" logged in successfully after %d failed attempts in the preceding hour (source IP: %s).',
                        $username,
                        $preceding,
                        $successIp ?: 'Unknown IP'
                    ),
                    'explanation' => 'A successful login immediately following multiple failed attempts could indicate either a legitimate user who finally remembered their credentials, or a successful brute-force/credential-stuffing attack.',
                    'suggested_action' => 'Confirm with the user that they completed this login and that the source IP matches their normal location.',
                    'target_username' => $username,
                    'target_ip' => $mfs['success_ip'] ?? null,
                    'count' => $preceding,
                    'threshold' => 3,
                    'window_minutes' => 60,
                    'first_seen' => $this->formatTime((string) $mfs['first_failure_time'], $timezone),
                    'last_seen' => $this->formatTime((string) $mfs['success_time'], $timezone),
                ];
            }
        }

        // 3. Repeated Attempts from Same IP
        $suspiciousIps = $this->repository->findSuspiciousIps($ipThreshold, $windowMinutes);
        foreach ($suspiciousIps as $sip) {
            $rawIp = (string) $sip['ip_address'];
            $displayIp = $canViewSensitive ? $rawIp : LoginRecord::maskIpAddress($rawIp);
            $failCount = (int) $sip['fail_count'];
            $targetCount = (int) $sip['targeted_users_count'];
            $targets = (string) ($sip['targeted_users'] ?? '');

            $insights[] = [
                'id' => 'suspicious_ip_' . md5($rawIp),
                'type' => 'suspicious_ip',
                'severity' => $targetCount > 2 ? 'danger' : 'warning',
                'title' => 'High Volume of Failures from IP ' . $displayIp,
                'description' => sprintf(
                    'The IP address "%s" produced %d failed login attempts targeting %d user account(s) (%s) within the last %d minutes.',
                    $displayIp,
                    $failCount,
                    $targetCount,
                    $targets ?: 'various',
                    $windowMinutes
                ),
                'explanation' => 'An IP generating numerous failed attempts across multiple accounts is typical of dictionary attacks or distributed credential stuffing.',
                'suggested_action' => 'Inspect network perimeter firewalls, reverse proxy logs, or Cloudflare/WAF to consider rate limiting this IP.',
                'target_username' => null,
                'target_ip' => $canViewSensitive ? $rawIp : $displayIp,
                'count' => $failCount,
                'threshold' => $ipThreshold,
                'window_minutes' => $windowMinutes,
                'first_seen' => $this->formatTime((string) $sip['first_attempt'], $timezone),
                'last_seen' => $this->formatTime((string) $sip['last_attempt'], $timezone),
            ];
        }

        // 4. Activity burst
        $burst = $this->repository->findUnusualBursts(30, 15);
        if (!empty($burst)) {
            $insights[] = [
                'id' => 'activity_burst_' . time(),
                'type' => 'activity_burst',
                'severity' => 'info',
                'title' => 'High Login Volume Detected',
                'description' => sprintf(
                    'Overall system recorded %d login attempts within the last 15 minutes (threshold: %d).',
                    $burst['total_attempts'],
                    $burst['threshold']
                ),
                'explanation' => 'A sudden increase in overall authentication traffic could reflect office start-of-day rush or a scanning event.',
                'suggested_action' => 'Monitor the Login Activity table for anomalous patterns.',
                'target_username' => null,
                'target_ip' => null,
                'count' => $burst['total_attempts'],
                'threshold' => $burst['threshold'],
                'window_minutes' => 15,
                'first_seen' => $this->formatTime((string) $burst['since'], $timezone),
                'last_seen' => (new \DateTimeImmutable('now', new \DateTimeZone($timezone)))->format('Y-m-d H:i:s'),
            ];
        }

        return [
            'total_alerts' => count($insights),
            'has_critical' => count(array_filter($insights, static fn($i) => $i['severity'] === 'danger')) > 0,
            'has_warnings' => count(array_filter($insights, static fn($i) => $i['severity'] === 'warning')) > 0,
            'insights' => $insights,
            'disclaimer' => 'These indicators are analytical monitoring signals to assist administrators, not proof of malice. No accounts or IPs are automatically blocked.',
        ];
    }

    private function formatTime(string $timeStr, string $timezone): string
    {
        try {
            $dt = new \DateTimeImmutable($timeStr);
            $tz = new \DateTimeZone($timezone);
            return $dt->setTimezone($tz)->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return $timeStr;
        }
    }
}
