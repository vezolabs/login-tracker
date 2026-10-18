import React, { useState } from 'react';
import { Tabs, Typography, Space, Badge } from 'antd';
import {
  DashboardOutlined,
  TableOutlined,
  UserOutlined,
  SafetyCertificateOutlined,
  SettingOutlined,
  SafetyOutlined,
} from '@ant-design/icons';
import { OverviewDashboard } from '../components/OverviewDashboard';
import { LoginActivityTable } from '../components/LoginActivityTable';
import { UserLoginHistory } from '../components/UserLoginHistory';
import { SecurityInsights } from '../components/SecurityInsights';
import { SettingsPage } from '../components/SettingsPage';

const { Title, Text } = Typography;

export const LoginTrackerMainPage: React.FC = () => {
  const [activeTab, setActiveTab] = useState<string>('overview');
  const [activityFilterUser, setActivityFilterUser] = useState<string>('');
  const [activityFilterIp, setActivityFilterIp] = useState<string>('');
  const [historySelectedUser, setHistorySelectedUser] = useState<string>('');

  const handleNavigateToUserHistory = (username: string) => {
    setHistorySelectedUser(username);
    setActiveTab('user-history');
  };

  const handleFilterActivityByUser = (username: string) => {
    setActivityFilterUser(username);
    setActivityFilterIp('');
    setActiveTab('activity');
  };

  const handleFilterActivityByIp = (ip: string) => {
    setActivityFilterIp(ip);
    setActivityFilterUser('');
    setActiveTab('activity');
  };

  const items = [
    {
      key: 'overview',
      label: (
        <Space size={6}>
          <DashboardOutlined />
          <span>Overview</span>
        </Space>
      ),
      children: (
        <OverviewDashboard
          onNavigateToActivity={() => setActiveTab('activity')}
          onNavigateToUser={handleNavigateToUserHistory}
        />
      ),
    },
    {
      key: 'activity',
      label: (
        <Space size={6}>
          <TableOutlined />
          <span>Login Activity</span>
        </Space>
      ),
      children: (
        <LoginActivityTable
          initialUsername={activityFilterUser}
          initialIp={activityFilterIp}
          onSelectUser={handleNavigateToUserHistory}
        />
      ),
    },
    {
      key: 'user-history',
      label: (
        <Space size={6}>
          <UserOutlined />
          <span>User Login History</span>
        </Space>
      ),
      children: <UserLoginHistory initialUsername={historySelectedUser} />,
    },
    {
      key: 'insights',
      label: (
        <Space size={6}>
          <SafetyCertificateOutlined />
          <span>Security Insights</span>
        </Space>
      ),
      children: (
        <SecurityInsights
          onFilterActivityByUser={handleFilterActivityByUser}
          onFilterActivityByIp={handleFilterActivityByIp}
        />
      ),
    },
    {
      key: 'settings',
      label: (
        <Space size={6}>
          <SettingOutlined />
          <span>Settings</span>
        </Space>
      ),
      children: <SettingsPage />,
    },
  ];

  return (
    <div style={{ padding: 24, background: '#fff', minHeight: '100vh' }}>
      {/* Page Header */}
      <div style={{ marginBottom: 20 }}>
        <Space align="center" size={12}>
          <div
            style={{
              width: 40,
              height: 40,
              borderRadius: 8,
              background: '#e6f7ff',
              display: 'flex',
              alignItems: 'center',
              justifyContent: 'center',
              color: '#1890ff',
              fontSize: 22,
            }}
          >
            <SafetyOutlined />
          </div>
          <div>
            <Title level={3} style={{ margin: 0 }}>
              Login Tracker
            </Title>
            <Text type="secondary">
              Real-time authentication monitoring, security audit trails, and login analytics for Pimcore Studio
            </Text>
          </div>
        </Space>
      </div>

      {/* Main Tabs */}
      <Tabs
        activeKey={activeTab}
        onChange={setActiveTab}
        items={items}
        size="large"
        destroyInactiveTabPane={false}
      />
    </div>
  );
};
