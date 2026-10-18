# Pimcore 12 Login Tracker Plugin

A production-ready, secure, and intuitive **Login Tracker Plugin** designed specifically for **Pimcore 12 Studio**. It empowers administrators and security teams to monitor authentication events, analyze login trends, detect anomalous access patterns, and configure data privacy/retention policies—all from a native, non-technical interface inside Pimcore Studio.

---

## 🌟 Key Features

### 1. Overview Dashboard
- **Summary Metric Cards**: Live metrics for Total Login Attempts, Successful Logins, Failed Attempts, Success Rate percentage, Unique Active Users, and Failures in the past 24 hours.
- **Visual Activity Chart**: Responsive timeline visualization displaying successful logins vs failed attempts by day or hour.
- **Date Range Selector**: Presets for Last 24 Hours, Last 7 Days, Last 30 Days, Last 90 Days, and custom ranges.
- **Friendly Empty State**: Helpful guidance immediately after installation before events are captured.

### 2. Login Activity Table
- **Searchable, Sortable, Paginated Table**: Full audit trail of every authentication attempt.
- **Contextual Fields**:
  - Date & time formatted in the configured administrator timezone (with UTC tooltip).
  - Username and display name (from Pimcore user directory).
  - Outcome indicator (Green "Successful" / Red "Failed").
  - Authentication method badge (Password, Studio Token, Single Sign-On SAML, API Client).
  - Source IP address (subject to privacy mode and permission masking).
  - Safe, human-understandable failure reasons (e.g. "Invalid username or password", "User account does not exist", "Account is locked or disabled").
  - Client device, browser, and operating system tags.
- **Multi-Field Filtering**: Filter simultaneously by date range, username, outcome status, authentication method, and IP address.
- **Event Detail Inspector**: Modal dialog providing complete contextual audit details (route, firewall, user identifiers, device details).
- **Credentials Protection**: Passwords, tokens, cookies, and secret headers are strictly filtered out and never stored.

### 3. User Login History
- **Targeted User Audits**: Select or search for any user account to review their complete authentication track record.
- **User Profile Banner**: Shows active/disabled status, administrator privileges, full name, and email.
- **Lifetime Aggregates**: Total attempts, successful logins, and failed attempts.
- **Quick Comparison**: Side-by-side cards showing the latest successful login vs the most recent failed attempt.
- **Chronological History**: Dedicated timeline table for the selected user.

### 4. Security Insights (Analytical Indicators)
- **Repeated Failed Attempts**: Flags accounts targeted by consecutive failed attempts within a configurable time window (potential brute-force or credential guessing).
- **Failures Followed by Success**: Highlights situations where multiple failed attempts were directly followed by a successful login (credential stuffing success or forgotten password recovery).
- **Suspicious Source IPs**: Detects IP addresses generating high failure volumes across multiple accounts (dictionary attacks / automated scanners).
- **Activity Volume Bursts**: Detects unusually high authentication traffic spikes across the system.
- **Explainable & Actionable**: Every insight details *why* it was flagged and provides *suggested administrator actions*.
- **Direct Drill-down**: Clickable shortcuts to filter the main Activity Table directly for the flagged user or IP.
- *Note: These are analytical monitoring indicators to assist administrators, not proof of malice. No accounts or IPs are blocked automatically without explicit configuration.*

### 5. Settings & Privacy Configuration
- **No Source Code Editing**: Everything is configurable directly through the Studio UI.
- **Activity Scope**: Master toggle to enable/disable tracking, with independent toggles for successful logins and failed attempts.
- **IP Address Privacy (GDPR Compliant)**:
  - *Full IP*: Stores complete client IP (e.g. `192.168.1.50`).
  - *Anonymized IP*: Masks the host portion (e.g. `192.168.1.***` for IPv4; `2001:db8:****:****` for IPv6).
  - *Disabled*: No IP addresses are recorded or stored.
- **Data Retention & Auto-Purge**: Configurable retention periods (30, 60, 90, 180, 365 days, or Keep Forever). Expired records are automatically removed via Pimcore maintenance.
- **Manual Retention Tool**: "Purge Expired Records Now" button with confirmation dialog and live record counters.
- **Display Timezone**: Select preferred timezone for UI display and CSV exports.
- **Threshold Sensitivity**: Configure failure counts and time windows for security alert detection.

### 6. CSV Export
- **Export Filtered Activity**: Downloads login records matching the currently applied search and filters.
- **CSV Formula Injection Protection**: Values starting with dangerous characters (`=`, `+`, `-`, `@`, `\t`, `\r`, `%`) are automatically sanitized.
- **Memory-Efficient Streaming**: Uses batched database cursors and chunked streaming to handle large datasets without memory exhaustion.
- **Permission Enforcement**: Users lacking sensitive data viewing privileges have IP addresses masked in exports.

---

## 📋 Prerequisites

- **PHP**: `8.2` or higher (fully tested on PHP `8.5`)
- **Pimcore**: `2025.x` or `2026.x` (Pimcore 12)
- **Symfony**: `6.4`, `7.x`, or `8.x`
- **Pimcore Studio**: `pimcore/studio-ui-bundle` and `pimcore/studio-backend-bundle`
- **Database**: MariaDB `10.11+` or MySQL `8.0+`

---

## 🚀 Installation

### Step 1: Install via Composer

Add the bundle to your Pimcore installation:

```bash
composer require vezolabs/pimcore-login-tracker-bundle
```

*For local path development in a monorepo:*
```json
"repositories": [
  {
    "type": "path",
    "url": "./login-tracker"
  }
]
```

### Step 2: Enable the Bundle

If not auto-registered, add the bundle to `config/bundles.php`:

```php
return [
    // ...
    Pimcore\Bundle\LoginTrackerBundle\PimcoreLoginTrackerBundle::class => ['all' => true],
];
```

### Step 3: Install Bundle Schema and Assets

Run the Pimcore bundle installer:

```bash
# Inside docker or your environment:
bin/console pimcore:bundle:install PimcoreLoginTrackerBundle
bin/console assets:install --symlink --relative
bin/console cache:clear
```

This creates the database tables (`plugin_login_tracker_records`, `plugin_login_tracker_settings`), registers the permission definitions in Pimcore, and seeds the default configuration.

---

## 🔒 Permissions & Access Control

The plugin registers 4 granular permissions in `users_permission_definitions`:

| Permission Key | Description | Default Access |
|---|---|---|
| `plugin_login_tracker_view` | View the Login Tracker navigation entry, overview dashboard, records table, user history, and insights | Administrators & Authorized Roles |
| `plugin_login_tracker_view_sensitive` | View unmasked client IP addresses, raw user-agent headers, and low-level request details | Security Administrators |
| `plugin_login_tracker_export` | Export filtered login records to CSV | Compliance / Audit Roles |
| `plugin_login_tracker_settings` | Edit tracking preferences, privacy modes, alert thresholds, and execute manual retention purge | Super Administrators |

Permissions are strictly enforced on backend REST API endpoints, not just hidden in the frontend UI. Unauthenticated or unauthorized requests receive HTTP 401/403 responses.

To grant permissions to a Pimcore user or role:
1. Navigate to **System > User & Roles** in Pimcore Studio.
2. Open the user or role editor and select the **Permissions** tab.
3. Check the desired `plugin_login_tracker_*` permissions and save.

---

## ⏰ Automated Retention Cleanup

Expired records are purged according to the configured retention period (default: 90 days). Cleanup can be triggered in two supported ways:

### 1. Pimcore Maintenance (Recommended)
The bundle registers a `CleanupMaintenanceTask` tagged with `pimcore.maintenance.task`. It runs automatically during your regular Pimcore maintenance cron:

```bash
bin/console pimcore:maintenance
```

To run only the login tracker cleanup job:
```bash
bin/console pimcore:maintenance --job=login_tracker_cleanup
```

### 2. Standalone Symfony Console Command
You can run or schedule the dedicated cleanup command:

```bash
# Uses configured retention days:
bin/console login-tracker:cleanup

# Override retention days:
bin/console login-tracker:cleanup --days=30
```

---

## 🛠️ Configuration Reference

All settings can be configured via **System > Login Tracker > Settings**:

| Setting | Type | Default | Description |
|---|---|---|---|
| `tracking_enabled` | bool | `true` | Master switch to enable or pause all login tracking |
| `track_successful` | bool | `true` | Whether to record successful authentication events |
| `track_failed` | bool | `true` | Whether to record failed login attempts |
| `ip_tracking_mode` | string | `full` | `full` (complete IP), `anonymized` (last octet zeroed), or `disabled` |
| `record_user_agent` | bool | `true` | Whether to capture browser, OS, and device information |
| `retention_days` | int | `90` | Days to keep records before automatic deletion (0 = keep forever) |
| `default_date_range` | string | `7_days` | Default overview date range (`24_hours`, `7_days`, `30_days`, `90_days`) |
| `display_timezone` | string | `UTC` | Timezone used to format timestamps in UI and CSV export |
| `alert_failed_threshold` | int | `5` | Number of failures per account before generating a security alert |
| `alert_window_minutes` | int | `15` | Time window in minutes for counting consecutive failures |
| `alert_ip_threshold` | int | `10` | Number of failures per IP address before generating an alert |
| `alert_multi_failure_success` | bool | `true` | Flag accounts with multiple failures followed by a successful login |

---

## 🧪 Testing

The bundle includes automated tests covering event capture, deduplication, privacy masking, repository filtering, formula injection defense, permissions, retention, and live database persistence:

```bash
# Inside docker container or PHP environment:
vendor/bin/phpunit -c login-tracker/phpunit.xml.dist
```

### Test Coverage Highlights:
- `LoginEventSubscriberTest`: Successful & failed event capture, deduplication attribute check, user resolution, non-blocking resilience when DB connection fails.
- `CsvExportServiceTest`: Neutralization of CSV formula injection prefixes (`=`, `+`, `-`, `@`, `\t`, `\r`, `%`), streaming generator.
- `SettingsServiceTest`: Validation, fallback defaults, and cache invalidation.
- `SecurityInsightsServiceTest`: Algorithmic rule detection for repeated failures, credential stuffing, and IP flooding.
- `RetentionServiceTest`: Retention calculation and purge execution.
- `AuthorizationAndApiTest`: 401/403 access control enforcement for unauthenticated and unauthorized users across all API endpoints.
- `LoginTrackerIntegrationTest`: End-to-end database queries, sorting, pagination, and cleanup against live MariaDB/MySQL.

---

## 🗑️ Uninstallation

To cleanly uninstall the plugin:

```bash
bin/console pimcore:bundle:uninstall PimcoreLoginTrackerBundle
bin/console cache:clear
```

This will safely drop the bundle database tables (`plugin_login_tracker_records`, `plugin_login_tracker_settings`) and clean up permission definitions.

---

## ℹ️ Technical Notes & Limitations

- **Authentication Lifecycle**: Uses Symfony Security 7 events (`LoginSuccessEvent` and `LoginFailureEvent`) on the `pimcore_studio` firewall. Subsequent page requests using existing sessions do not create duplicate events.
- **Fail-Safe Operation**: Event capture is wrapped in try-catch blocks. If database persistence encounters an error, the failure is logged, and the user's login is **never** blocked.
- **SSO / External Authenticators**: When SAML / Microsoft Entra ID / SSO plugins are active, successful and failed logins through those authenticators are captured with the `sso` authentication method.
- **API Token Logins**: Studio Admin token logins (`pimcore_studio_api_token_login`) are classified under the `token` method.

---

## 📄 License

GPL-3.0-or-later. Built for the Pimcore Community.
