import axios from 'axios';
import {
  DashboardMetrics,
  TimelineItem,
  LoginRecordData,
  UserSummaryData,
  UserInfo,
  SecurityInsightItem,
  PluginSettings,
  Capabilities,
} from '../types';

const API_BASE = '/pimcore-studio/api/login-tracker';

export const loginTrackerApi = {
  getOverview: async (
    range?: string,
    from?: string,
    to?: string
  ): Promise<{
    metrics: DashboardMetrics;
    timeline: TimelineItem[];
    range: string;
    timezone: string;
    tracking_enabled: boolean;
  }> => {
    const res = await axios.get(`${API_BASE}/overview`, {
      params: { range, from, to },
    });
    return res.data;
  },

  getRecords: async (
    params: Record<string, any>
  ): Promise<{
    items: LoginRecordData[];
    total: number;
    page: number;
    limit: number;
    pages: number;
    timezone: string;
    can_view_sensitive: boolean;
  }> => {
    const res = await axios.get(`${API_BASE}/records`, { params });
    return res.data;
  },

  getRecordDetail: async (
    id: number
  ): Promise<{
    record: LoginRecordData;
    raw_ip: string | null;
    user_agent: string | null;
    details: Record<string, any> | null;
  }> => {
    const res = await axios.get(`${API_BASE}/records/${id}`);
    return res.data;
  },

  getUserHistory: async (
    username: string
  ): Promise<{
    username: string;
    user_info: UserInfo | null;
    summary: UserSummaryData;
    history: LoginRecordData[];
  }> => {
    const res = await axios.get(`${API_BASE}/user-history`, {
      params: { username },
    });
    return res.data;
  },

  getInsights: async (): Promise<{
    total_alerts: number;
    has_critical: boolean;
    has_warnings: boolean;
    insights: SecurityInsightItem[];
    disclaimer: string;
  }> => {
    const res = await axios.get(`${API_BASE}/insights`);
    return res.data;
  },

  getSettings: async (): Promise<{
    settings: PluginSettings;
    capabilities: Capabilities;
    total_stored_records: number;
  }> => {
    const res = await axios.get(`${API_BASE}/settings`);
    return res.data;
  },

  updateSettings: async (
    settings: Partial<PluginSettings>
  ): Promise<{
    message: string;
    settings: PluginSettings;
  }> => {
    const res = await axios.post(`${API_BASE}/settings`, settings);
    return res.data;
  },

  triggerCleanup: async (
    days?: number
  ): Promise<{
    message: string;
    deleted_count: number;
    remaining_count: number;
  }> => {
    const res = await axios.post(`${API_BASE}/cleanup`, { days });
    return res.data;
  },

  getExportUrl: (filters: Record<string, any>): string => {
    const query = new URLSearchParams();
    Object.entries(filters).forEach(([k, v]) => {
      if (v !== undefined && v !== null && v !== '') {
        query.append(k, String(v));
      }
    });
    return `${API_BASE}/export?${query.toString()}`;
  },

  getUsersList: async (): Promise<string[]> => {
    const res = await axios.get(`${API_BASE}/users-list`);
    return res.data.users || [];
  },
};
