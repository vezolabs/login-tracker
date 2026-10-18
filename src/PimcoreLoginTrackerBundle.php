<?php

declare(strict_types=1);

namespace Pimcore\Bundle\LoginTrackerBundle;

use Pimcore\Bundle\LoginTrackerBundle\Installer\LoginTrackerInstaller;
use Pimcore\Extension\Bundle\AbstractPimcoreBundle;
use Pimcore\Extension\Bundle\Installer\InstallerInterface;

final class PimcoreLoginTrackerBundle extends AbstractPimcoreBundle
{
    public const string BUNDLE_NAME = 'PimcoreLoginTrackerBundle';

    public const string PERMISSION_VIEW = 'plugin_login_tracker_view';
    public const string PERMISSION_VIEW_SENSITIVE = 'plugin_login_tracker_view_sensitive';
    public const string PERMISSION_EXPORT = 'plugin_login_tracker_export';
    public const string PERMISSION_SETTINGS = 'plugin_login_tracker_settings';

    public function getNiceName(): string
    {
        return 'Pimcore Login Tracker';
    }

    public function getDescription(): string
    {
        return 'Monitor, analyze, and secure login activity directly inside Pimcore 12 Studio';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function getInstaller(): ?InstallerInterface
    {
        return $this->container?->get(LoginTrackerInstaller::class);
    }

    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
