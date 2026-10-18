# Installation & Activation Guide

## Requirements

- **PHP**: `^8.2`, `^8.3`, `^8.4`, `^8.5`
- **Pimcore**: `2025.x` or `2026.x` (Pimcore 12)
- **Pimcore Studio**: `pimcore/studio-ui-bundle` and `pimcore/studio-backend-bundle`
- **Database**: MariaDB 10.6+ or MySQL 8.0+

## 1. Quick One-Line Install

Run the following command in your Pimcore project root:

```bash
composer require vezolabs/pimcore-login-tracker-bundle && \
bin/console pimcore:bundle:install PimcoreLoginTrackerBundle && \
bin/console assets:install public && \
bin/console cache:clear
```

## 2. Step-by-Step Installation

### Step 1: Install Package via Composer

```bash
composer require vezolabs/pimcore-login-tracker-bundle
```

### Step 2: Register Bundle

If Symfony Flex is active, the bundle is automatically registered in `config/bundles.php`. If not, add it manually:

```php
return [
    // ...
    Pimcore\Bundle\LoginTrackerBundle\PimcoreLoginTrackerBundle::class => ['all' => true],
];
```

### Step 3: Run Database Migrations & Permission Setup

Execute the Pimcore bundle installer to create the database tables (`plugin_login_tracker_records`, `plugin_login_tracker_settings`) and default permission definitions:

```bash
bin/console pimcore:bundle:install PimcoreLoginTrackerBundle
```

### Step 4: Publish Frontend Assets

Publish the Module Federation bundle to Pimcore's public directory:

```bash
bin/console assets:install public --symlink --relative
bin/console cache:clear
```

### Step 5: Verify Permissions

Assign the Login Tracker permissions to your admin roles or users in **Settings → Users / Roles**:
- `plugin_login_tracker_view`: Access Login Tracker dashboard and activity table.
- `plugin_login_tracker_view_sensitive`: View unmasked IP addresses and detailed user agents.
- `plugin_login_tracker_export`: Export audit logs as CSV.
- `plugin_login_tracker_settings`: Modify tracking configuration and retention rules.
