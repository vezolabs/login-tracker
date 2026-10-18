# Security Architecture & Data Protection

VezoLabs Login Tracker is designed with security and privacy as fundamental requirements.

## 1. Zero Credential Exposure

- Passwords, 2FA tokens, session identifiers, and authorization headers are never logged.
- Failed authentication reasons use generalized, safe failure descriptions (e.g., `Invalid credentials`, `Account locked`) to prevent credential harvesting or enumeration.

## 2. Non-Blocking Event Capture

- The authentication subscriber (`LoginEventSubscriber`) wraps event processing and persistence in comprehensive exception boundaries.
- If database connectivity drops or table schema is undergoing maintenance, legitimate user authentication is never interrupted or blocked.

## 3. CSV Injection Neutralization

When exporting logs to CSV, all fields are sanitized against Formula Injection (CSV Command Injection).
Any field beginning with risky calculation operators (`=`, `+`, `-`, `@`, `\t`, `\r`, `%`) is prepended with a single quote (`'`), ensuring spreadsheet applications (Microsoft Excel, LibreOffice, Google Sheets) evaluate them purely as string literals.

## 4. Privacy & Data Minimization

- **IP Anonymization**: When enabled, client IP addresses are masked before persistent storage.
- **Role-Based Sensitive Masking**: Even if full IP recording is enabled, users lacking `plugin_login_tracker_view_sensitive` will see masked IPs in both the UI and CSV exports.
- **Configurable Retention**: The plugin automatically enforces maximum retention limits, permanently purging records past the expiration threshold.

## 5. Security Insights Rules

The Insights Engine evaluates behavioral patterns without imposing arbitrary lockouts:
- **Repeated Failures**: Flags users or IPs exceeding the configured failure threshold in the specified time window.
- **Multiple Failures Followed by Success**: Detects potential brute-force or credential guessing where multiple consecutive failed attempts are immediately followed by a successful login.
- **High-Risk IP Activity**: Aggregates fail/success ratios across client IP addresses to identify distributed credential stuffing.
