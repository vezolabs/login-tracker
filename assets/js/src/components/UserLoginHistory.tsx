import React, { useEffect, useState } from 'react';
import {
  Card,
  Select,
  Row,
  Col,
  Statistic,
  Table,
  Tag,
  Typography,
  Space,
  Empty,
  Spin,
  Avatar,
  Divider,
  Button,
} from 'antd';
import {
  UserOutlined,
  CheckCircleOutlined,
  CloseCircleOutlined,
  ClockCircleOutlined,
  GlobalOutlined,
  AuditOutlined,
  ReloadOutlined,
} from '@ant-design/icons';
import type { ColumnsType } from 'antd/es/table';
import { loginTrackerApi } from '../services/api';
import { LoginRecordData, UserInfo, UserSummaryData } from '../types';
import { RecordDetailModal } from './RecordDetailModal';

const { Title, Text } = Typography;
const { Option } = Select;

interface UserLoginHistoryProps {
  initialUsername?: string;
}

export const UserLoginHistory: React.FC<UserLoginHistoryProps> = ({ initialUsername }) => {
  const [usersList, setUsersList] = useState<string[]>([]);
  const [selectedUser, setSelectedUser] = useState<string>(initialUsername || '');
  const [loading, setLoading] = useState<boolean>(false);
  const [summary, setSummary] = useState<UserSummaryData | null>(null);
  const [userInfo, setUserInfo] = useState<UserInfo | null>(null);
  const [history, setHistory] = useState<LoginRecordData[]>([]);

  // Detail Modal
  const [selectedRecord, setSelectedRecord] = useState<LoginRecordData | null>(null);
  const [modalOpen, setModalOpen] = useState<boolean>(false);

  useEffect(() => {
    loginTrackerApi.getUsersList().then((users) => {
      setUsersList(users);
      if (!selectedUser && users.length > 0) {
        setSelectedUser(users[0]);
      }
    });
  }, []);

  useEffect(() => {
    if (initialUsername) {
      setSelectedUser(initialUsername);
    }
  }, [initialUsername]);

  const loadUserHistory = async (username: string) => {
    if (!username) return;
    setLoading(true);
    try {
      const res = await loginTrackerApi.getUserHistory(username);
      setSummary(res.summary);
      setUserInfo(res.user_info);
      setHistory(res.history);
    } catch (e) {
      console.error('Failed to load user history', e);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    if (selectedUser) {
      loadUserHistory(selectedUser);
    }
  }, [selectedUser]);

  const columns: ColumnsType<LoginRecordData> = [
    {
      title: 'Date & Time',
      dataIndex: 'formatted_time',
      key: 'login_time',
      width: 180,
    },
    {
      title: 'Result',
      dataIndex: 'status',
      key: 'status',
      width: 120,
      render: (val: string, record) =>
        record.is_success ? (
          <Tag icon={<CheckCircleOutlined />} color="success">
            Successful
          </Tag>
        ) : (
          <Tag icon={<CloseCircleOutlined />} color="error">
            Failed
          </Tag>
        ),
    },
    {
      title: 'Auth Method',
      dataIndex: 'auth_method_label',
      key: 'auth_method',
      width: 160,
      render: (label: string) => <Tag color="purple">{label}</Tag>,
    },
    {
      title: 'Source IP',
      dataIndex: 'ip_address',
      key: 'ip_address',
      width: 140,
      render: (ip: string | null) =>
        ip ? (
          <code>{ip}</code>
        ) : (
          <Text type="secondary">—</Text>
        ),
    },
    {
      title: 'Browser & OS',
      key: 'client',
      width: 180,
      render: (_: any, record) => (
        <Space size={4}>
          <Tag>{record.browser}</Tag>
          <Tag>{record.os}</Tag>
        </Space>
      ),
    },
    {
      title: 'Failure Reason',
      dataIndex: 'failure_reason',
      key: 'failure_reason',
      render: (reason: string | null, record) =>
        !record.is_success ? (
          <Text type="danger" style={{ fontSize: 12 }}>
            {reason || 'Authentication failed'}
          </Text>
        ) : (
          <Text type="secondary">—</Text>
        ),
    },
    {
      title: 'Action',
      key: 'action',
      width: 90,
      render: (_: any, record) => (
        <Button
          type="link"
          size="small"
          onClick={() => {
            setSelectedRecord(record);
            setModalOpen(true);
          }}
        >
          Details
        </Button>
      ),
    },
  ];

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 20 }}>
      {/* User Selector Bar */}
      <Card size="small" style={{ background: '#fafafa', border: '1px solid #f0f0f0' }}>
        <Row gutter={[16, 16]} align="middle">
          <Col xs={24} md={12}>
            <Space align="center" wrap>
              <UserOutlined style={{ fontSize: 18, color: '#1890ff' }} />
              <Text strong>Select User Account:</Text>
              <Select
                showSearch
                style={{ width: 280 }}
                placeholder="Search or select username"
                value={selectedUser || undefined}
                onChange={(val) => setSelectedUser(val)}
                optionFilterProp="children"
              >
                {usersList.map((u) => (
                  <Option key={u} value={u}>
                    {u}
                  </Option>
                ))}
              </Select>
            </Space>
          </Col>
          <Col xs={24} md={12} style={{ textAlign: 'right' }}>
            <Button
              icon={<ReloadOutlined />}
              onClick={() => selectedUser && loadUserHistory(selectedUser)}
            >
              Refresh History
            </Button>
          </Col>
        </Row>
      </Card>

      {!selectedUser ? (
        <Card>
          <Empty description="Please select a user account to inspect login history." />
        </Card>
      ) : loading ? (
        <div style={{ textAlign: 'center', padding: '60px 0' }}>
          <Spin size="large" tip="Loading user activity..." />
        </div>
      ) : (
        <>
          {/* User Profile & Summary Card */}
          <Card bordered>
            <Row gutter={[24, 24]} align="middle">
              <Col xs={24} md={8}>
                <Space align="start" size={16}>
                  <Avatar size={54} icon={<UserOutlined />} style={{ backgroundColor: '#1890ff' }} />
                  <div>
                    <Space size={8}>
                      <Title level={4} style={{ margin: 0 }}>
                        {selectedUser}
                      </Title>
                      {userInfo?.is_admin && <Tag color="gold">Administrator</Tag>}
                      {userInfo && !userInfo.is_active && <Tag color="red">Disabled Account</Tag>}
                    </Space>
                    {userInfo?.first_name || userInfo?.last_name ? (
                      <div>
                        <Text type="secondary">
                          {userInfo.first_name} {userInfo.last_name}
                        </Text>
                      </div>
                    ) : null}
                    {userInfo?.email && (
                      <div>
                        <Text type="secondary" style={{ fontSize: 12 }}>
                          {userInfo.email}
                        </Text>
                      </div>
                    )}
                  </div>
                </Space>
              </Col>

              <Col xs={24} md={16}>
                <Row gutter={[16, 16]}>
                  <Col span={8}>
                    <Statistic
                      title="Total Attempts"
                      value={summary?.total_logins || 0}
                      prefix={<AuditOutlined />}
                    />
                  </Col>
                  <Col span={8}>
                    <Statistic
                      title="Successful Logins"
                      value={summary?.successful_logins || 0}
                      valueStyle={{ color: '#3f8600' }}
                      prefix={<CheckCircleOutlined />}
                    />
                  </Col>
                  <Col span={8}>
                    <Statistic
                      title="Failed Logins"
                      value={summary?.failed_logins || 0}
                      valueStyle={{ color: '#cf1322' }}
                      prefix={<CloseCircleOutlined />}
                    />
                  </Col>
                </Row>
              </Col>
            </Row>

            <Divider style={{ margin: '16px 0' }} />

            {/* Last Success & Last Failure Quick Insights */}
            <Row gutter={[16, 16]}>
              <Col xs={24} md={12}>
                <div style={{ padding: 12, background: '#f6ffed', border: '1px solid #b7eb8f', borderRadius: 6 }}>
                  <Space direction="vertical" size={2}>
                    <Space size={4}>
                      <CheckCircleOutlined style={{ color: '#52c41a' }} />
                      <Text strong>Latest Successful Login:</Text>
                    </Space>
                    {summary?.last_success ? (
                      <Text style={{ fontSize: 13 }}>
                        {summary.last_success.formatted_time} (via {summary.last_success.auth_method_label} from{' '}
                        <code>{summary.last_success.ip_address || 'Unknown IP'}</code>)
                      </Text>
                    ) : (
                      <Text type="secondary">No recorded successful login</Text>
                    )}
                  </Space>
                </div>
              </Col>

              <Col xs={24} md={12}>
                <div style={{ padding: 12, background: '#fff1f0', border: '1px solid #ffa39e', borderRadius: 6 }}>
                  <Space direction="vertical" size={2}>
                    <Space size={4}>
                      <CloseCircleOutlined style={{ color: '#ff4d4f' }} />
                      <Text strong>Most Recent Failed Attempt:</Text>
                    </Space>
                    {summary?.last_failed ? (
                      <Text style={{ fontSize: 13 }}>
                        {summary.last_failed.formatted_time} — Reason:{' '}
                        <Text type="danger">{summary.last_failed.failure_reason || 'Failed'}</Text>
                      </Text>
                    ) : (
                      <Text type="secondary">No failed attempts recorded</Text>
                    )}
                  </Space>
                </div>
              </Col>
            </Row>
          </Card>

          {/* Chronological History Table */}
          <Card
            title={
              <Space>
                <ClockCircleOutlined />
                <span>Recent Chronological Activity for {selectedUser}</span>
              </Space>
            }
          >
            <Table
              columns={columns}
              dataSource={history}
              rowKey="id"
              pagination={{ pageSize: 15 }}
              size="middle"
            />
          </Card>
        </>
      )}

      {/* Record Detail Modal */}
      <RecordDetailModal
        record={selectedRecord}
        open={modalOpen}
        onClose={() => setModalOpen(false)}
      />
    </div>
  );
};
