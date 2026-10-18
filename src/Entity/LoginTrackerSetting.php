<?php

declare(strict_types=1);

namespace Pimcore\Bundle\LoginTrackerBundle\Entity;

final class LoginTrackerSetting
{
    private ?int $id = null;
    private string $configKey;
    private ?string $configValue = null;
    private \DateTimeInterface $updatedAt;

    public function __construct(string $configKey, ?string $configValue = null)
    {
        $this->configKey = $configKey;
        $this->configValue = $configValue;
        $this->updatedAt = new \DateTimeImmutable();
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

    public function getConfigKey(): string
    {
        return $this->configKey;
    }

    public function setConfigKey(string $configKey): self
    {
        $this->configKey = $configKey;
        return $this;
    }

    public function getConfigValue(): ?string
    {
        return $this->configValue;
    }

    public function setConfigValue(?string $configValue): self
    {
        $this->configValue = $configValue;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getUpdatedAt(): \DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }
}
