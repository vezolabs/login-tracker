import React, { useEffect, useState } from 'react';
import {
  Card,
  Row,
  Col,
  Statistic,
  Radio,
  Typography,
  Spin,
  Alert,
  Empty,
  Tag,
  Space,
  Button,
} from 'antd';
import {
  CheckCircleOutlined,
  CloseCircleOutlined,
  UserOutlined,
  AuditOutlined,
  ClockCircleOutlined,
  ReloadOutlined,
  SafetyCertificateOutlined,
  WarningOutlined,
} from '@ant-design/icons';
import { loginTrackerApi } from '../services/api';
import { DashboardMetrics, TimelineItem } from '../types';

const { Title, Text } = Typography;

interface OverviewDashboardProps {
  onNavigateToActivity?: () => void;
  onNavigateToUser?: (username: string) => void;
}

export const OverviewDashboard: React.FC<OverviewDashboardProps> = ({
  onNavigateToActivity,
  onNavigateToUser,
}) => {
  const [loading, setLoading] = useState<boolean>(true);
  const [metrics, setMetrics] = useState<DashboardMetrics | null>(null);
  const [timeline, setTimeline] = useState<TimelineItem[]>([]);
  const [range, setRange] = useState<string>('7_days');
  const [timezone, setTimezone] = useState<string>('UTC');
  const [trackingEnabled, setTrackingEnabled] = useState<boolean>(true);

  const fetchOverview = async (selectedRange = range) => {
    setLoading(true);
    try {
      const data = await loginTrackerApi.getOverview(selectedRange);
      setMetrics(data.metrics);
      setTimeline(data.timeline);
      setTimezone(data.timezone);
      setTrackingEnabled(data.tracking_enabled);
    } catch (e) {
      console.error('Failed to load overview data', e);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchOverview(range);
  }, [range]);

  const maxTotal = Math.max(...timeline.map((t) => t.total), 1);

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 20 }}>
      {/* Top Banner / Range selector */}
      <div
        style={{
          display: 'flex',
          justifyContent: 'space-between',
          alignItems: 'center',
          flexWrap: 'wrap',
          gap: 12,
          padding: '12px 16px',
          background: '#fafafa',
          borderRadius: 8,
          border: '1px solid #f0f0f0',
        }}
      >
        <Space align="center">
          <SafetyCertificateOutlined style={{ fontSize: 20, color: '#1890ff' }} />
          <div>
            <Text strong style={{ fontSize: 16 }}>Authentication Overview</Text>
            <div>
              <Text orientation="left" type="secondary" style={{ fontSize: 12 }}>
                Display timezone: <Tag color="blue">{timezone}</Tag>
                {!trackingEnabled && (
                  <Tag color="warning" icon={<WarningOutlined />}>Tracking Currently Disabled</Tag>
                )}
              </Text>
            </div>
          </div>
        </Space>

        <Space>
          <Radio.Group
            value={range}
            onChange={(e) => setRange(e.target.value)}
            optionType="button"
            buttonStyle="solid"
          >
            <Radio.Button value="24_hours">Last 24 Hours</Radio.Button>
            <Radio.Button value="7_days">Last 7 Days</Radio.Button>
            <Radio.Button value="30_days">Last 30 Days</Radio.Button>
            <Radio.Button value="90_days">Last 90 Days</Radio.Button>
          </Radio.Group>
          <Button icon={<ReloadOutlined />} onClick={() => fetchOverview(range)}>
            Refresh
          </Button>
        </Space>
      </div>

      {loading ? (
        <div style={{ textAlign: 'center', padding: '60px 0' }}>
          <Spin size="large" tip="Loading login activity metrics..." />
        </div>
      ) : metrics && metrics.total_attempts === 0 ? (
        <Card>
          <Empty
            image={Empty.PRESENTED_IMAGE_SIMPLE}
            description={
              <div>
                <Title level={4}>No Login Activity Recorded Yet</Title>
                <Text type="secondary">
                  Login Tracker is installed and ready. Whenever users authenticate into Pimcore Studio or fail authentication, their activity will be captured and displayed here.
                </Text>
              </div>
            }
          >
            <Button type="primary" onClick={() => fetchOverview(range)}>
              Check for New Events
            </Button>
          </Empty>
        </Card>
      ) : (
        <>
          {/* Key Metric Cards */}
          <Row gutter={[16, 16]}>
            <Col xs={24} sm={12} lg={4}>
              <Card bordered hoverable style={{ borderTop: '3px solid #1890ff' }}>
                <Statistic
                  title={<Text strong>Total Attempts</Text>}
                  value={metrics?.total_attempts || 0}
                  prefix={<AuditOutlined style={{ color: '#1890ff' }} />}
                />
                <Text type="secondary" style={{ fontSize: 11 }}>In selected time window</Text>
              </Card>
            </Col>

            <Col xs={24} sm={12} lg={4}>
              <Card bordered hoverable style={{ borderTop: '3px solid #52c41a' }}>
                <Statistic
                  title={<Text strong>Successful Logins</Text>}
                  value={metrics?.successful_logins || 0}
                  valueStyle={{ color: '#3f8600' }}
                  prefix={<CheckCircleOutlined />}
                  suffix={
                    <span style={{ fontSize: 12, color: '#8c8c8c' }}>
                      ({metrics?.success_rate}%)
                    </span>
                  }
                />
                <Text type="secondary" style={{ fontSize: 11 }}>Authenticated sessions</Text>
              </Card>
            </Col>

            <Col xs={24} sm={12} lg={4}>
              <Card bordered hoverable style={{ borderTop: '3px solid #ff4d4f' }}>
                <Statistic
                  title={<Text strong>Failed Attempts</Text>}
                  value={metrics?.failed_logins || 0}
                  valueStyle={{ color: '#cf1322' }}
                  prefix={<CloseCircleOutlined />}
                  suffix={
                    <span style={{ fontSize: 12, color: '#8c8c8c' }}>
                      ({metrics && metrics.total_attempts > 0 ? (100 - metrics.success_rate).toFixed(1) : 0}%)
                    </span>
                  }
                />
                <Text type="secondary" style={{ fontSize: 11 }}>Rejected logins</Text>
              </Card>
            </Col>

            <Col xs={24} sm={12} lg={4}>
              <Card bordered hoverable style={{ borderTop: '3px solid #722ed1' }}>
                <Statistic
                  title={<Text strong>Unique Users</Text>}
                  value={metrics?.unique_users || 0}
                  prefix={<UserOutlined style={{ color: '#722ed1' }} />}
                />
                <Text type="secondary" style={{ fontSize: 11 }}>Distinct accounts active</Text>
              </Card>
            </Col>

            <Col xs={24} sm={12} lg={4}>
              <Card bordered hoverable style={{ borderTop: '3px solid #fa8c16' }}>
                <Statistic
                  title={<Text strong>Recent Failures</Text>}
                  value={metrics?.failed_last_24h || 0}
                  valueStyle={{ color: (metrics?.failed_last_24h || 0) > 0 ? '#fa8c16' : '#595959' }}
                  prefix={<ClockCircleOutlined />}
                />
                <Text type="secondary" style={{ fontSize: 11 }}>Past 24 hours</Text>
              </Card>
            </Col>

            <Col xs={24} sm={12} lg={4}>
              <Card bordered hoverable style={{ borderTop: '3px solid #13c2c2' }}>
                <Text strong style={{ display: 'block', marginBottom: 4 }}>Most Recent</Text>
                {metrics?.most_recent ? (
                  <div>
                    <Space orientation="vertical" size={2}>
                      <Space orientation="horizontal" size={4}>
                        <Tag color={metrics.most_recent.status === 'success' ? 'success' : 'error'}>
                          {metrics.most_recent.status === 'success' ? 'Success' : 'Failed'}
                        </Tag>
                        <Text strong style={{ fontSize: 13 }}>{metrics.most_recent.username}</Text>
                      </Space>
                      <Text type="secondary" style={{ fontSize: 11 }}>
                        {metrics.most_recent.login_time}
                      </Text>
                    </Space>
                  </div>
                ) : (
                  <Text type="secondary" style={{ fontSize: 12 }}>No activity</Text>
                )}
              </Card>
            </Col>
          </Row>

          {/* Visual Activity Timeline Chart */}
          <Card
            title={
              <Space>
                <AuditOutlined />
                <span>Authentication Activity Timeline</span>
              </Space>
            }
            extra={
              <Space>
                <Badge color="#52c41a" text="Successful Logins" />
                <Badge color="#ff4d4f" text="Failed Attempts" />
              </Space>
            }
          >
            {timeline.length === 0 ? (
              <Empty description="No timeline data points for selected date range" />
            ) : (
              <div style={{ overflowX: 'auto', paddingBottom: 8 }}>
                <div style={{ minWidth: 600, display: 'flex', flexDirection: 'column', gap: 12 }}>
                  {/* SVG Bar Chart */}
                  <svg width="100%" height="220" viewBox={`0 0 ${timeline.length * 50 + 60} 220`}>
                    {/* Grid lines */}
                    <line x1="40" y1="20" x2={timeline.length * 50 + 40} y2="20" stroke="#f0f0f0" strokeDasharray="3 3" />
                    <line x1="40" y1="70" x2={timeline.length * 50 + 40} y2="70" stroke="#f0f0f0" strokeDasharray="3 3" />
                    <line x1="40" y1="120" x2={timeline.length * 50 + 40} y2="120" stroke="#f0f0f0" strokeDasharray="3 3" />
                    <line x1="40" y1="170" x2={timeline.length * 50 + 40} y2="170" stroke="#d9d9d9" />

                    {/* Bars */}
                    {timeline.map((item, index) => {
                      const x = 50 + index * 50;
                      const maxBarHeight = 140;
                      const successHeight = (item.success / maxTotal) * maxBarHeight;
                      const failedHeight = (item.failed / maxTotal) * maxBarHeight;

                      const label = item.bucket.length > 10 ? item.bucket.substring(11, 16) : item.bucket.substring(5);

                      return (
                        <g key={item.bucket}>
                          {/* Success Bar */}
                          {item.success > 0 && (
                            <rect
                              x={x}
                              y={170 - successHeight}
                              width={14}
                              height={successHeight}
                              fill="#52c41a"
                              rx={2}
                            >
                              <title>{`${item.bucket}: ${item.success} successful logins`}</title>
                            </rect>
                          )}

                          {/* Failed Bar */}
                          {item.failed > 0 && (
                            <rect
                              x={x + 16}
                              y={170 - failedHeight}
                              width={14}
                              height={failedHeight}
                              fill="#ff4d4f"
                              rx={2}
                            >
                              <title>{`${item.bucket}: ${item.failed} failed attempts`}</title>
                            </rect>
                          )}

                          {/* Bucket Label */}
                          <text
                            x={x + 15}
                            y={192}
                            textAnchor="middle"
                            fontSize="10"
                            fill="#8c8c8c"
                          >
                            {label}
                          </text>

                          {/* Value top label if count > 0 */}
                          {(item.success > 0 || item.failed > 0) && (
                            <text
                              x={x + 15}
                              y={170 - Math.max(successHeight, failedHeight) - 6}
                              textAnchor="middle"
                              fontSize="10"
                              fill="#595959"
                              fontWeight="bold"
                            >
                              {item.total}
                            </text>
                          )}
                        </g>
                      );
                    })}
                  </svg>

                  <div style={{ textAlign: 'center', fontSize: 12, color: '#8c8c8c' }}>
                    Hover over any bar to view detailed attempt counts for that timeframe.
                  </div>
                </div>
              </div>
            )}
          </Card>
        </>
      )}
    </div>
  );
};
