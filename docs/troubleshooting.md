# Troubleshooting & Diagnostics

## 1. Login Events Are Not Being Recorded

1. **Verify Tracking Is Enabled**:
   Navigate to **System → Login Tracker → Settings** and ensure **Enable Login Tracking** is turned on.
2. **Verify Bundle Registration**:
   Run:
   ```bash
   bin/console pimcore:bundle:list
   ```
   Ensure `PimcoreLoginTrackerBundle` is listed with state `installed` and `enabled`.
3. **Verify Database Tables**:
   Confirm that tables `plugin_login_tracker_records` and `plugin_login_tracker_settings` exist in your database schema. If missing, reinstall the bundle:
   ```bash
   bin/console pimcore:bundle:install PimcoreLoginTrackerBundle
   ```

## 2. Studio UI Navigation Entry Does Not Appear

1. **Clear Application Cache**:
   ```bash
   bin/console cache:clear
   ```
2. **Re-publish Public Assets**:
   ```bash
   bin/console assets:install public --symlink --relative
   ```
3. **Check User Permissions**:
   Verify that your user or user role has the permission `plugin_login_tracker_view` enabled in **Settings → Users / Roles**.

## 3. CSV Export Fails or Times Out

1. **Export Permissions**:
   Verify that the administrator account has the `plugin_login_tracker_export` permission.
2. **Large Date Ranges**:
   Filter your export by a narrower date range or specific user to reduce stream volume. The exporter streams in chunks of 500 records to prevent memory exhaustion, but proxy timeouts may require pagination on massive datasets.

## 4. IP Addresses Show as Masked / Redacted

1. If you require full IP address auditing, ensure **Anonymize IP Addresses** is disabled in **Settings**.
2. Ensure your user account has the `plugin_login_tracker_view_sensitive` permission assigned.
