<?php

declare(strict_types=1);

namespace Pimcore\Bundle\LoginTrackerBundle\Entity;

final class LoginRecord
{
    public const string STATUS_SUCCESS = 'success';
    public const string STATUS_FAILED = 'failed';

    public const string AUTH_METHOD_PASSWORD = 'password';
    public const string AUTH_METHOD_TOKEN = 'token';
    public const string AUTH_METHOD_SSO = 'sso';
    public const string AUTH_METHOD_OTHER = 'other';

    public const string FAILURE_TYPE_INVALID_CREDENTIALS = 'invalid_credentials';
    public const string FAILURE_TYPE_USER_NOT_FOUND = 'user_not_found';
    public const string FAILURE_TYPE_ACCOUNT_DISABLED = 'account_disabled';
    public const string FAILURE_TYPE_TOKEN_INVALID = 'token_invalid';
    public const string FAILURE_TYPE_UNKNOWN = 'unknown';

    private ?int $id = null;
    private \DateTimeInterface $loginTime;
    private string $username;
    private ?int $userId = null;
    private ?string $displayName = null;
    private string $status;
    private string $authMethod = self::AUTH_METHOD_PASSWORD;
    private string $authMethodLabel = 'Password / Credentials';
    private ?string $ipAddress = null;
    private ?string $rawIp = null;
    private ?string $userAgent = null;
    private ?string $browser = null;
    private ?string $os = null;
    private ?string $deviceType = 'desktop';
    private ?string $failureReason = null;
    private ?string $failureType = null;
    private ?string $firewall = null;
    private ?string $route = null;
    private ?array $details = null;

    public function __construct()
    {
        $this->loginTime = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getLoginTime(): \DateTimeInterface
    {
        return $this->loginTime;
    }

    public function setLoginTime(\DateTimeInterface $loginTime): self
    {
        $this->loginTime = $loginTime;
        return $this;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function setUsername(string $username): self
    {
        $this->username = $username;
        return $this;
    }

    public function getUserId(): ?int
    {
        return $this->userId;
    }

    public function setUserId(?int $userId): self
    {
        $this->userId = $userId;
        return $this;
    }

    public function getDisplayName(): ?string
    {
        return $this->displayName;
    }

    public function setDisplayName(?string $displayName): self
    {
        $this->displayName = $displayName;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function isSuccess(): bool
    {
        return $this->status === self::STATUS_SUCCESS;
    }

    public function getAuthMethod(): string
    {
        return $this->authMethod;
    }

    public function setAuthMethod(string $authMethod): self
    {
        $this->authMethod = $authMethod;
        return $this;
    }

    public function getAuthMethodLabel(): string
    {
        return $this->authMethodLabel;
    }

    public function setAuthMethodLabel(string $authMethodLabel): self
    {
        $this->authMethodLabel = $authMethodLabel;
        return $this;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(?string $ipAddress): self
    {
        $this->ipAddress = $ipAddress;
        return $this;
    }

    public function getRawIp(): ?string
    {
        return $this->rawIp;
    }

    public function setRawIp(?string $rawIp): self
    {
        $this->rawIp = $rawIp;
        return $this;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): self
    {
        $this->userAgent = $userAgent;
        return $this;
    }

    public function getBrowser(): ?string
    {
        return $this->browser;
    }

    public function setBrowser(?string $browser): self
    {
        $this->browser = $browser;
        return $this;
    }

    public function getOs(): ?string
    {
        return $this->os;
    }

    public function setOs(?string $os): self
    {
        $this->os = $os;
        return $this;
    }

    public function getDeviceType(): ?string
    {
        return $this->deviceType;
    }

    public function setDeviceType(?string $deviceType): self
    {
        $this->deviceType = $deviceType;
        return $this;
    }

    public function getFailureReason(): ?string
    {
        return $this->failureReason;
    }

    public function setFailureReason(?string $failureReason): self
    {
        $this->failureReason = $failureReason;
        return $this;
    }

    public function getFailureType(): ?string
    {
        return $this->failureType;
    }

    public function setFailureType(?string $failureType): self
    {
        $this->failureType = $failureType;
        return $this;
    }

    public function getFirewall(): ?string
    {
        return $this->firewall;
    }

    public function setFirewall(?string $firewall): self
    {
        $this->firewall = $firewall;
        return $this;
    }

    public function getRoute(): ?string
    {
        return $this->route;
    }

    public function setRoute(?string $route): self
    {
        $this->route = $route;
        return $this;
    }

    public function getDetails(): ?array
    {
        return $this->details;
    }

    public function setDetails(?array $details): self
    {
        $this->details = $details;
        return $this;
    }

    /**
     * Converts the entity to array for API serialization, respecting sensitivity permissions.
     */
    public function toArray(bool $canViewSensitive = false, ?string $timezone = 'UTC'): array
    {
        $tz = new \DateTimeZone($timezone ?: 'UTC');
        $displayTime = \DateTimeImmutable::createFromInterface($this->loginTime)->setTimezone($tz);

        $ip = $this->ipAddress;
        if (!$canViewSensitive && $ip !== null) {
            $ip = self::maskIpAddress($ip);
        }

        return [
            'id' => $this->id,
            'login_time' => $this->loginTime->format(\DateTimeInterface::ATOM),
            'formatted_time' => $displayTime->format('Y-m-d H:i:s'),
            'timestamp' => $this->loginTime->getTimestamp(),
            'username' => $this->username,
            'user_id' => $this->userId,
            'display_name' => $this->displayName ?: $this->username,
            'status' => $this->status,
            'is_success' => $this->isSuccess(),
            'auth_method' => $this->authMethod,
            'auth_method_label' => $this->authMethodLabel,
            'ip_address' => $ip,
            'has_ip' => $ip !== null,
            'browser' => $this->browser ?: 'Unknown Browser',
            'os' => $this->os ?: 'Unknown OS',
            'device_type' => $this->deviceType ?: 'desktop',
            'user_agent' => $canViewSensitive ? $this->userAgent : null,
            'failure_reason' => $this->failureReason,
            'failure_type' => $this->failureType,
            'firewall' => $this->firewall,
            'route' => $this->route,
            'details' => $canViewSensitive ? ($this->details ?? []) : null,
        ];
    }

    public static function maskIpAddress(string $ip): string
    {
        if (str_contains($ip, ':')) {
            // IPv6: keep first 2 segments, mask the rest
            $parts = explode(':', $ip);
            return count($parts) >= 2 ? $parts[0] . ':' . $parts[1] . ':****:****' : '****:****';
        }

        // IPv4: mask last octet or last two octets
        $parts = explode('.', $ip);
        if (count($parts) === 4) {
            return $parts[0] . '.' . $parts[1] . '.' . $parts[2] . '.***';
        }

        return '***.***.***';
    }
}
