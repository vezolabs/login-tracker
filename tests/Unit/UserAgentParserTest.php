<?php

declare(strict_types=1);

namespace Pimcore\Bundle\LoginTrackerBundle\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Pimcore\Bundle\LoginTrackerBundle\Service\UserAgentParser;

final class UserAgentParserTest extends TestCase
{
    public function testParseEmptyOrNullUserAgent(): void
    {
        $result = UserAgentParser::parse(null);
        $this->assertSame('Unknown Browser', $result['browser']);
        $this->assertSame('Unknown OS', $result['os']);
        $this->assertSame('desktop', $result['device_type']);

        $result2 = UserAgentParser::parse('');
        $this->assertSame('Unknown Browser', $result2['browser']);
    }

    public function testDetectChromeOnMacOS(): void
    {
        $ua = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36';
        $result = UserAgentParser::parse($ua);

        $this->assertSame('Chrome 124', $result['browser']);
        $this->assertSame('macOS', $result['os']);
        $this->assertSame('desktop', $result['device_type']);
    }

    public function testDetectFirefoxOnWindows(): void
    {
        $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:125.0) Gecko/20100101 Firefox/125.0';
        $result = UserAgentParser::parse($ua);

        $this->assertSame('Firefox 125', $result['browser']);
        $this->assertSame('Windows 10/11', $result['os']);
        $this->assertSame('desktop', $result['device_type']);
    }

    public function testDetectSafariOnIPhone(): void
    {
        $ua = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_4_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.4.1 Mobile/15E148 Safari/604.1';
        $result = UserAgentParser::parse($ua);

        $this->assertSame('Safari 17', $result['browser']);
        $this->assertSame('iOS 17', $result['os']);
        $this->assertSame('mobile', $result['device_type']);
    }

    public function testDetectTablet(): void
    {
        $ua = 'Mozilla/5.0 (iPad; CPU OS 16_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.6 Mobile/15E148 Safari/604.1';
        $result = UserAgentParser::parse($ua);

        $this->assertSame('tablet', $result['device_type']);
    }

    public function testDetectApiClient(): void
    {
        $ua = 'curl/7.88.1';
        $result = UserAgentParser::parse($ua);

        $this->assertSame('API Client / Script', $result['browser']);
        $this->assertSame('bot', $result['device_type']);
    }
}
