export interface LoginRecordData {
  id: number;
  login_time: string;
  formatted_time: string;
  timestamp: number;
  username: string;
  user_id: number | null;
  display_name: string;
  status: 'success' | 'failed';
  is_success: boolean;
  auth_method: string;
  auth_method_label: string;
  ip_address: string | null;
  has_ip: boolean;
  browser: string;
  os: string;
  device_type: 'desktop' | 'mobile' | 'tablet' | 'bot';
  user_agent: string | null;
  failure_reason: string | null;
  failure_type: string | null;
  firewall: string | null;
  route: string | null;
  details: Record<string, any> | null;
}

export interface DashboardMetrics {
  total_attempts: number;
  successful_logins: number;
  failed_logins: number;
  success_rate: number;
  unique_users: number;
  failed_last_24h: number;
  most_recent: {
    login_time: string;
    username: string;
    display_name: string;
    status: string;
    auth_method_label: string;
    ip_address: string | null;
  } | null;
}

export interface TimelineItem {
  bucket: string;
  success: number;
  failed: number;
  total: number;
}

export interface UserSummaryData {
  total_logins: number;
  successful_logins: number;
  failed_logins: number;
  last_success: LoginRecordData | null;
  last_failed: LoginRecordData | null;
}

export interface UserInfo {
  id: number;
  username: string;
  first_name: string | null;
  last_name: string | null;
  email: string | null;
  is_admin: boolean;
  is_active: boolean;
}

export interface SecurityInsightItem {
  id: string;
  type: 'repeated_failures' | 'fail_then_success' | 'suspicious_ip' | 'activity_burst';
  severity: 'danger' | 'warning' | 'info';
  title: string;
  description: string;
  explanation: string;
  suggested_action: string;
  target_username: string | null;
  target_ip: string | null;
  count: number;
  threshold: number;
  window_minutes: number;
  first_seen: string;
  last_seen: string;
}

export interface PluginSettings {
  tracking_enabled: boolean;
  track_successful: boolean;
  track_failed: boolean;
  ip_tracking_mode: 'full' | 'anonymized' | 'disabled';
  record_user_agent: boolean;
  retention_days: number;
  default_date_range: '24_hours' | '7_days' | '30_days' | '90_days';
  display_timezone: string;
  alert_failed_threshold: number;
  alert_window_minutes: number;
  alert_ip_threshold: number;
  alert_multi_failure_success: boolean;
}

export interface Capabilities {
  can_view_sensitive: boolean;
  can_export: boolean;
  can_manage_settings: boolean;
}
