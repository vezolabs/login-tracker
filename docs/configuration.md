# Studio UI Configuration Reference

All Login Tracker settings can be managed directly in Pimcore Studio under **System → Login Tracker → Settings**.

## Core Settings

| Setting | Type | Default | Description |
|---|---|---|---|
| `tracking_enabled` | Boolean | `true` | Master switch for login event tracking. When disabled, no events are recorded. |
| `track_successful_logins` | Boolean | `true` | Record successful login attempts. |
| `track_failed_logins` | Boolean | `true` | Record failed authentication attempts. |
| `record_ip_addresses` | Boolean | `true` | Whether to store client IP addresses. |
| `anonymize_ip` | Boolean | `false` | When enabled, masks the last octet of IPv4 (`192.168.1.xxx`) or the interface ID of IPv6 for privacy compliance. |
| `record_user_agent` | Boolean | `true` | Captures browser, OS, and client type. |
| `retention_days` | Integer | `90` | How many days to keep login history before automatic purging. |
| `dashboard_default_days` | Integer | `30` | Default date range (in days) loaded on the Overview Dashboard. |
| `display_timezone` | String | `UTC` | Display timezone for logs and charts (e.g., `UTC`, `America/New_York`, `Europe/Berlin`). |
| `export_enabled` | Boolean | `true` | Allow authorized administrators to download CSV exports. |
| `alert_failed_attempts_threshold` | Integer | `5` | Number of failed logins within a time window that triggers repeated failure warnings. |
| `alert_time_window_minutes` | Integer | `15` | Monitoring time window in minutes for security insights. |

## Maintenance & Retention Cleanup

Expired logs are automatically purged by Pimcore's scheduled maintenance worker (`Pimcore\Maintenance\TaskInterface`).

To trigger retention cleanup manually from the CLI:

```bash
bin/console login-tracker:cleanup
```

To run retention cleanup via Pimcore's scheduled maintenance:

```bash
bin/console pimcore:maintenance --task=login_tracker_cleanup
```
