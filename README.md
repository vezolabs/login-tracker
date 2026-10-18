# VezoLabs Login Tracker for Pimcore 12 Studio

[![CI](https://github.com/vezolabs/login-tracker/actions/workflows/ci.yml/badge.svg)](https://github.com/vezolabs/login-tracker/actions/workflows/ci.yml)
[![Software License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/php-%3E%3D%208.2-777bb4.svg)](https://php.net)
[![Pimcore](https://img.shields.io/badge/pimcore-12%20Studio-6c2e91.svg)](https://pimcore.com)
[![Packagist Version](https://img.shields.io/packagist/v/vezolabs/pimcore-login-tracker-bundle.svg)](https://packagist.org/packages/vezolabs/pimcore-login-tracker-bundle)

**VezoLabs Login Tracker** is an enterprise-grade security and authentication monitoring extension built for **Pimcore 12 Studio**. It enables administrators to monitor, analyze, and audit all backend authentication attempts in real time through an intuitive, non-technical Studio interface.

Technical administrators install the package once via Composer. From that point forward, all routine operations, dashboard analytics, audit inquiries, security alerts, and privacy configurations are handled directly through the **Pimcore Studio UI** (accessible via **System → Login Tracker**).

---

## Screenshots & Interface

<!-- Visual placeholders: Drop screenshots into docs/images/ -->

| Overview Dashboard | Login Activity Table |
| :---: | :---: |
| ![Overview Dashboard](docs/images/overview-dashboard.png) | ![Login Activity Table](docs/images/activity-table.png) |

| Security Insights | Settings & Retention |
| :---: | :---: |
| ![Security Insights](docs/images/security-insights.png) | ![Settings Page](docs/images/settings-page.png) |

---

## Video Walkthrough

<!-- Video walkthrough placeholder -->

> A video walkthrough covering installation, configuration, and practical usage will be linked here.
> 
> [![Video Walkthrough](https://img.shields.io/badge/YouTube-Video%20Guide%20(Coming%20Soon)-red?logo=youtube)](https://vezolabs.com)

---

## Key Features

- **Real-Time Authentication Monitoring**: Tracks both successful logins and failed attempts across Pimcore Studio authentication firewalls with non-blocking server-side event capture.
- **Intuitive Studio UI**: Built with React 18, TypeScript, and Module Federation. Natively integrated into Pimcore 12 Studio navigation with zero legacy ExtJS dependencies.
- **Security Insights & Anomaly Detection**: Transparent behavioral rules highlight repeated failed logins, credential stuffing patterns (repeated failures followed by a success), and high-risk client IP clusters.
- **Per-User Login History**: Quick-inspection modal showing last successful login, recent failed attempts, and complete chronological history for any selected user account.
- **Privacy & Compliance**: Configurable IP recording with optional subnet anonymization (masking the last octet of IPv4 and interface ID of IPv6) and role-based sensitive masking.
- **Zero Credential Exposure**: Passwords, 2FA tokens, session identifiers, and secret headers are strictly excluded from audit logs and failure reasons.
- **Streaming CSV Export**: Filter-aware, memory-efficient streaming CSV export equipped with spreadsheet formula injection protection (`=`, `+`, `-`, `@`, `\t`, `\r`, `%`).
- **Automated Data Retention**: Automatic log pruning via Pimcore's scheduled maintenance task (`Pimcore\Maintenance\TaskInterface`) and Symfony console command (`login-tracker:cleanup`).
- **Granular Permissions**: Role-based access control protecting dashboard viewing, sensitive data inspection, CSV exports, and settings management.

---

## System Requirements & Compatibility

| Requirement | Supported Version |
|---|---|
| **Pimcore** | Pimcore 12 with Studio UI (`^2025.0` or `^2026.0`) |
| **PHP** | `^8.2`, `^8.3`, `^8.4`, `^8.5` |
| **Symfony** | `^6.4`, `^7.0`, `^8.0` |
| **Database** | MariaDB 10.6+ or MySQL 8.0+ |

---

## Legacy Pimcore Support & Customization

Need this functionality for an earlier Pimcore version, or require custom integrations?

- **Pimcore 6, Pimcore 10, or Pimcore 11**: Dedicated builds and backports tailored for classic Pimcore architectures.
- **Custom Enterprise Integrations**: Bespoke SIEM / Syslog forwarding, Webhook notifications, IP blocklist automation, and SAML/SSO audit consolidation.

👉 **[Contact VezoLabs for Customization & Legacy Support](https://vezolabs.com/contact)**

---

## Installation

### Quick One-Line Install

Run the following command in your Pimcore project root:

```bash
composer require vezolabs/pimcore-login-tracker-bundle && \
bin/console pimcore:bundle:install PimcoreLoginTrackerBundle && \
bin/console assets:install public && \
bin/console cache:clear
```

---

### Step-by-Step Installation

#### 1. Require Package via Composer

```bash
composer require vezolabs/pimcore-login-tracker-bundle
```

#### 2. Enable Bundle

If not auto-registered by Symfony Flex, add the bundle to `config/bundles.php`:

```php
return [
    // ...
    Pimcore\Bundle\LoginTrackerBundle\PimcoreLoginTrackerBundle::class => ['all' => true],
];
```

#### 3. Run Bundle Installer & Migrations

```bash
bin/console pimcore:bundle:install PimcoreLoginTrackerBundle
```

#### 4. Publish Frontend Assets & Clear Cache

```bash
bin/console assets:install public
bin/console cache:clear
```

#### 5. Verify Installation

```bash
bin/console pimcore:bundle:list
bin/console login-tracker:cleanup --dry-run
```

---

## Quick Start Guide

1. Log into Pimcore Studio (`/pimcore-studio/`) with administrator credentials.
2. In the left navigation dock, click **System** → **Login Tracker**.
3. The **Overview Dashboard** provides immediate visibility into recent login attempts, failure counts, and active unique users.
4. Open the **Activity** tab to filter audit logs by date range, username, login outcome, authentication provider, or IP address.
5. Review the **Security Insights** tab for automated anomaly indicators and unusual activity alerts.
6. Configure retention schedules and privacy thresholds under **Settings**.

---

## CLI Commands

### Retention Cleanup

Purge login history older than the configured retention threshold (default: 90 days):

```bash
# Preview records eligible for deletion without deleting
bin/console login-tracker:cleanup --dry-run

# Execute immediate purge
bin/console login-tracker:cleanup
```

---

## Documentation

Detailed documentation is available in the [`docs/`](docs/) directory:

- [Installation & Activation](docs/installation.md)
- [Configuration Reference](docs/configuration.md)
- [Security Architecture & Hardening](docs/security.md)
- [Troubleshooting & Diagnostics](docs/troubleshooting.md)

---

## Contributing

Contributions are welcome. Please read [CONTRIBUTING.md](CONTRIBUTING.md) for coding standards and test execution.

To run the automated PHPUnit test suite:

```bash
vendor/bin/phpunit -c phpunit.xml.dist
```

---

## License

This project is licensed under the [MIT License](LICENSE).

---

## Commercial Support & Consulting

VezoLabs provides professional consulting, enterprise deployment, and custom Pimcore development.

- Website: [https://vezolabs.com](https://vezolabs.com)
- Inquiries: [https://vezolabs.com/contact](https://vezolabs.com/contact)
