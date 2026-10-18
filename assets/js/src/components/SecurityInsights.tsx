import React, { useEffect, useState } from 'react';
import {
  Card,
  Alert,
  Space,
  Tag,
  Typography,
  Button,
  Empty,
  Spin,
  Row,
  Col,
  Divider,
} from 'antd';
import {
  SafetyCertificateOutlined,
  WarningOutlined,
  CloseCircleOutlined,
  InfoCircleOutlined,
  ReloadOutlined,
  ArrowRightOutlined,
  CheckCircleOutlined,
} from '@ant-design/icons';
import { loginTrackerApi } from '../services/api';
import { SecurityInsightItem } from '../types';

const { Title, Text, Paragraph } = Typography;

interface SecurityInsightsProps {
  onFilterActivityByUser?: (username: string) => void;
  onFilterActivityByIp?: (ip: string) => void;
}

export const SecurityInsights: React.FC<SecurityInsightsProps> = ({
  onFilterActivityByUser,
  onFilterActivityByIp,
}) => {
  const [loading, setLoading] = useState<boolean>(true);
  const [insights, setInsights] = useState<SecurityInsightItem[]>([]);
  const [totalAlerts, setTotalAlerts] = useState<number>(0);
  const [hasCritical, setHasCritical] = useState<boolean>(false);
  const [disclaimer, setDisclaimer] = useState<string>('');

  const fetchInsights = async () => {
    setLoading(true);
    try {
      const res = await loginTrackerApi.getInsights();
      setInsights(res.insights || []);
      setTotalAlerts(res.total_alerts || 0);
      setHasCritical(res.has_critical || false);
      setDisclaimer(res.disclaimer || '');
    } catch (e) {
      console.error('Failed to load insights', e);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchInsights();
  }, []);

  const getSeverityBadge = (severity: string) => {
    switch (severity) {
      case 'danger':
        return <Tag color="error" icon={<CloseCircleOutlined />}>High Priority / Critical</Tag>;
      case 'warning':
        return <Tag color="warning" icon={<WarningOutlined />}>Warning</Tag>;
      default:
        return <Tag color="processing" icon={<InfoCircleOutlined />}>Notice</Tag>;
    }
  };

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 20 }}>
      {/* Header & Disclaimer */}
      <Alert
        message="Analytical Security Monitoring Indicators"
        description={
          <div>
            <Text type="secondary">
              {disclaimer ||
                'These indicators are analytical monitoring signals to assist administrators in spotting anomalous authentication behavior. They are not proof of malice, and no accounts or IPs are blocked automatically.'}
            </Text>
          </div>
        }
        type="info"
        showIcon
        icon={<SafetyCertificateOutlined style={{ fontSize: 22 }} />}
        action={
          <Button icon={<ReloadOutlined />} onClick={fetchInsights}>
            Recalculate
          </Button>
        }
      />

      {loading ? (
        <div style={{ textAlign: 'center', padding: '60px 0' }}>
          <Spin size="large" tip="Evaluating authentication security rules..." />
        </div>
      ) : insights.length === 0 ? (
        <Card>
          <Empty
            image={Empty.PRESENTED_IMAGE_SIMPLE}
            description={
              <div>
                <Title level={4} style={{ color: '#52c41a' }}>
                  <CheckCircleOutlined style={{ marginRight: 8 }} />
                  No Anomalous Activity Detected
                </Title>
                <Text type="secondary">
                  All recent authentication activity conforms to normal patterns. No repeated failed attempts, credential guessing bursts, or suspicious IP volumes were observed within the configured detection windows.
                </Text>
              </div>
            }
          />
        </Card>
      ) : (
        <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
          {insights.map((item) => (
            <Card
              key={item.id}
              bordered
              style={{
                borderLeft:
                  item.severity === 'danger'
                    ? '4px solid #ff4d4f'
                    : item.severity === 'warning'
                    ? '4px solid #fa8c16'
                    : '4px solid #1890ff',
              }}
            >
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', flexWrap: 'wrap', gap: 12 }}>
                <Space direction="vertical" size={4} style={{ flex: 1 }}>
                  <Space size={8}>
                    {getSeverityBadge(item.severity)}
                    <Title level={5} style={{ margin: 0 }}>
                      {item.title}
                    </Title>
                  </Space>
                  <Paragraph style={{ margin: '8px 0', fontSize: 14 }}>
                    {item.description}
                  </Paragraph>
                </Space>

                <Space>
                  {item.target_username && onFilterActivityByUser && (
                    <Button
                      type="primary"
                      ghost
                      size="small"
                      icon={<ArrowRightOutlined />}
                      onClick={() => onFilterActivityByUser(item.target_username!)}
                    >
                      Filter logs for {item.target_username}
                    </Button>
                  )}
                  {item.target_ip && onFilterActivityByIp && (
                    <Button
                      type="default"
                      size="small"
                      icon={<ArrowRightOutlined />}
                      onClick={() => onFilterActivityByIp(item.target_ip!)}
                    >
                      Filter logs for IP
                    </Button>
                  )}
                </Space>
              </div>

              <Divider style={{ margin: '12px 0' }} />

              <Row gutter={[16, 8]}>
                <Col xs={24} md={12}>
                  <Text strong style={{ fontSize: 12, color: '#595959' }}>Why was this flagged?</Text>
                  <div>
                    <Text type="secondary" style={{ fontSize: 13 }}>
                      {item.explanation}
                    </Text>
                  </div>
                </Col>
                <Col xs={24} md={12}>
                  <Text strong style={{ fontSize: 12, color: '#595959' }}>Suggested Administrator Action:</Text>
                  <div>
                    <Text type="secondary" style={{ fontSize: 13 }}>
                      {item.suggested_action}
                    </Text>
                  </div>
                </Col>
              </Row>

              <div style={{ marginTop: 12, fontSize: 11, color: '#8c8c8c' }}>
                First observed: {item.first_seen} | Last observed: {item.last_seen} | Window: {item.window_minutes} mins
              </div>
            </Card>
          ))}
        </div>
      )}
    </div>
  );
};
