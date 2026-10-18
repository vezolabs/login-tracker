<?php

declare(strict_types=1);

namespace Pimcore\Bundle\LoginTrackerBundle\Service;

final class UserAgentParser
{
    /**
     * @return array{browser: string, os: string, device_type: string}
     */
    public static function parse(?string $ua): array
    {
        if (empty($ua)) {
            return [
                'browser' => 'Unknown Browser',
                'os' => 'Unknown OS',
                'device_type' => 'desktop',
            ];
        }

        $browser = self::detectBrowser($ua);
        $os = self::detectOs($ua);
        $deviceType = self::detectDeviceType($ua);

        return [
            'browser' => $browser,
            'os' => $os,
            'device_type' => $deviceType,
        ];
    }

    private static function detectBrowser(string $ua): string
    {
        if (preg_match('/Edg(?:e|A|iOS)?\/([0-9.]+)/i', $ua, $m)) {
            return 'Edge ' . self::majorVersion($m[1]);
        }
        if (preg_match('/OPR\/([0-9.]+)/i', $ua, $m) || preg_match('/Opera\/([0-9.]+)/i', $ua, $m)) {
            return 'Opera ' . self::majorVersion($m[1]);
        }
        if (preg_match('/Vivaldi\/([0-9.]+)/i', $ua, $m)) {
            return 'Vivaldi ' . self::majorVersion($m[1]);
        }
        if (preg_match('/Brave/i', $ua)) {
            return 'Brave';
        }
        if (preg_match('/Firefox\/([0-9.]+)/i', $ua, $m)) {
            return 'Firefox ' . self::majorVersion($m[1]);
        }
        if (preg_match('/Chrome\/([0-9.]+)/i', $ua, $m) && !str_contains($ua, 'Chromium')) {
            return 'Chrome ' . self::majorVersion($m[1]);
        }
        if (preg_match('/Version\/([0-9.]+).*Safari/i', $ua, $m)) {
            return 'Safari ' . self::majorVersion($m[1]);
        }
        if (preg_match('/MSIE\s([0-9.]+)/i', $ua, $m) || preg_match('/Trident\/.*rv:([0-9.]+)/i', $ua, $m)) {
            return 'Internet Explorer ' . self::majorVersion($m[1]);
        }
        if (preg_match('/curl|wget|PostmanRuntime|insomnia|python|got/i', $ua)) {
            return 'API Client / Script';
        }

        return 'Other Browser';
    }

    private static function detectOs(string $ua): string
    {
        if (preg_match('/iPhone OS\s*([0-9_]+)/i', $ua, $m)) {
            return 'iOS ' . str_replace('_', '.', self::majorVersion($m[1]));
        }
        if (preg_match('/iPad.*OS\s*([0-9_]+)/i', $ua, $m)) {
            return 'iPadOS ' . str_replace('_', '.', self::majorVersion($m[1]));
        }
        if (preg_match('/Android\s*([0-9.]+)/i', $ua, $m)) {
            return 'Android ' . self::majorVersion($m[1]);
        }
        if (preg_match('/Windows NT 10.0/i', $ua)) {
            return 'Windows 10/11';
        }
        if (preg_match('/Windows NT 6.3/i', $ua)) {
            return 'Windows 8.1';
        }
        if (preg_match('/Windows NT 6.1/i', $ua)) {
            return 'Windows 7';
        }
        if (preg_match('/Macintosh|Mac OS X/i', $ua)) {
            return 'macOS';
        }
        if (preg_match('/Ubuntu/i', $ua)) {
            return 'Ubuntu Linux';
        }
        if (preg_match('/Linux/i', $ua)) {
            return 'Linux';
        }
        if (preg_match('/CrOS/i', $ua)) {
            return 'ChromeOS';
        }

        return 'Unknown OS';
    }

    private static function detectDeviceType(string $ua): string
    {
        if (preg_match('/bot|crawl|spider|slurp|facebookexternalhit|curl|wget/i', $ua)) {
            return 'bot';
        }
        if (preg_match('/iPad|Tablet|PlayBook/i', $ua)) {
            return 'tablet';
        }
        if (preg_match('/Mobile|iPhone|iPod|Android.*Mobile|webOS|BlackBerry|IEMobile|Opera Mini/i', $ua)) {
            return 'mobile';
        }

        return 'desktop';
    }

    private static function majorVersion(string $ver): string
    {
        $normalized = str_replace('_', '.', $ver);
        $parts = explode('.', $normalized);

        return $parts[0] ?? $ver;
    }
}
