# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-10-18

### Added
- Native Pimcore 12 Studio UI plugin using React 18, TypeScript, Ant Design 5, and Rsbuild Module Federation.
- Real-time login monitoring dashboard with total attempts, success/failure counts, unique active users, and activity trends.
- Comprehensive login activity table with search, status filtering, date range selector, username filtering, IP search, and detail modal.
- Per-user login history modal with latest successful login, recent failures, and chronological attempts.
- Security insights engine detecting repeated failed logins, credential stuffing attempts, and suspicious IP activity.
- Complete settings manager supporting tracking toggle, outcome selection, IP privacy masking, User-Agent recording, retention period, and alert thresholds.
- Streaming CSV export with spreadsheet formula-injection protection (`=`, `+`, `-`, `@`, `\t`, `\r`, `%`) and permission-aware IP masking.
- Automated retention cleanup via Pimcore maintenance task (`Pimcore\Maintenance\TaskInterface`) and console command (`login-tracker:cleanup`).
- Granular Pimcore permission system (`plugin_login_tracker_view`, `plugin_login_tracker_view_sensitive`, `plugin_login_tracker_export`, `plugin_login_tracker_settings`).
- Complete automated test suite covering unit, security, subscriber, and live DB integration tests.
