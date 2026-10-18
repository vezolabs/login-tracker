import React, { useEffect, useState } from 'react';
import {
  Card,
  Form,
  Switch,
  Radio,
  Select,
  InputNumber,
  Button,
  Typography,
  Space,
  Divider,
  Alert,
  Spin,
  message,
  Modal,
  Row,
  Col,
} from 'antd';
import {
  SaveOutlined,
  DeleteOutlined,
  QuestionCircleOutlined,
  CheckCircleOutlined,
  ExclamationCircleOutlined,
  LockOutlined,
} from '@ant-design/icons';
import { loginTrackerApi } from '../services/api';
import { PluginSettings, Capabilities } from '../types';

const { Title, Text, Paragraph } = Typography;
const { Option } = Select;

const COMMON_TIMEZONES = [
  'UTC',
  'Europe/London',
  'Europe/Berlin',
  'Europe/Vienna',
  'Europe/Paris',
  'Europe/Zurich',
  'America/New_York',
  'America/Chicago',
  'America/Denver',
  'America/Los_Angeles',
  'Asia/Kolkata',
  'Asia/Dubai',
  'Asia/Singapore',
  'Asia/Tokyo',
  'Australia/Sydney',
];

export const SettingsPage: React.FC = () => {
  const [form] = Form.useForm();
  const [loading, setLoading] = useState<boolean>(true);
  const [saving, setSaving] = useState<boolean>(false);
  const [cleaning, setCleaning] = useState<boolean>(false);
  const [totalRecords, setTotalRecords] = useState<number>(0);
  const [capabilities, setCapabilities] = useState<Capabilities>({
    can_view_sensitive: false,
    can_export: false,
    can_manage_settings: false,
  });

  const loadSettings = async () => {
    setLoading(true);
    try {
      const res = await loginTrackerApi.getSettings();
      form.setFieldsValue(res.settings);
      setTotalRecords(res.total_stored_records);
      setCapabilities(res.capabilities);
    } catch (e) {
      console.error('Failed to load settings', e);
      message.error('Failed to load settings.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadSettings();
  }, []);

  const handleSave = async (values: any) => {
    setSaving(true);
    try {
      const res = await loginTrackerApi.updateSettings(values);
      message.success(res.message || 'Settings saved successfully.');
      form.setFieldsValue(res.settings);
    } catch (e) {
      console.error('Failed to save settings', e);
      message.error('Failed to save settings. Please verify permissions.');
    } finally {
      setSaving(false);
    }
  };

  const handleRunCleanup = () => {
    Modal.confirm({
      title: 'Run Retention Cleanup Now?',
      icon: <ExclamationCircleOutlined />,
      content:
        'This will permanently delete all login records older than the configured retention threshold. Are you sure you want to proceed?',
      okText: 'Run Cleanup',
      okType: 'danger',
      cancelText: 'Cancel',
      onOk: async () => {
        setCleaning(true);
        try {
          const res = await loginTrackerApi.triggerCleanup();
          message.success(res.message);
          setTotalRecords(res.remaining_count);
        } catch (e) {
          console.error('Cleanup failed', e);
          message.error('Cleanup execution failed.');
        } finally {
          setCleaning(false);
        }
      },
    });
  };

  if (loading) {
    return (
      <div style={{ textAlign: 'center', padding: '60px 0' }}>
        <Spin size="large" tip="Loading plugin configuration..." />
      </div>
    );
  }

  const canEdit = capabilities.can_manage_settings;

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 20, maxWidth: 900 }}>
      {!canEdit && (
        <Alert
          message="Read-Only Mode"
          description="You do not have permission to modify plugin settings (plugin_login_tracker_settings). The current configuration is displayed in read-only mode."
          type="warning"
          showIcon
          icon={<LockOutlined />}
        />
      )}

      <Form
        form={form}
        layout="vertical"
        onFinish={handleSave}
        disabled={!canEdit}
      >
        {/* Core Tracking */}
        <Card title="Activity Capture & Scope" bordered>
          <Form.Item
            name="tracking_enabled"
            label={<Text strong>Enable Login Tracking</Text>}
            valuePropName="checked"
            extra="Master switch to turn all authentication tracking on or off."
          >
            <Switch checkedChildren="Active" unCheckedChildren="Disabled" />
          </Form.Item>

          <Divider style={{ margin: '16px 0' }} />

          <Row gutter={[24, 16]}>
            <Col xs={24} md={12}>
              <Form.Item
                name="track_successful"
                label={<Text strong>Record Successful Logins</Text>}
                valuePropName="checked"
                extra="Log successful logins to monitor user activity and session starts."
              >
                <Switch />
              </Form.Item>
            </Col>

            <Col xs={24} md={12}>
              <Form.Item
                name="track_failed"
                label={<Text strong>Record Failed Login Attempts</Text>}
                valuePropName="checked"
                extra="Capture failed attempts to detect password guessing and locked accounts."
              >
                <Switch />
              </Form.Item>
            </Col>
          </Row>
        </Card>

        {/* Privacy & Client Details */}
        <Card title="Privacy & Data Minimization" bordered style={{ marginTop: 20 }}>
          <Form.Item
            name="ip_tracking_mode"
            label={<Text strong>Source IP Address Recording</Text>}
            extra="Choose how IP addresses are recorded to balance security monitoring and privacy regulations (such as GDPR)."
          >
            <Radio.Group>
              <Space direction="vertical">
                <Radio value="full">
                  <Text strong>Full IP Address</Text>
                  <Text type="secondary" style={{ display: 'block', fontSize: 12 }}>
                    Stores complete IP (e.g. 192.168.1.42). Requires sensitive data viewing permission.
                  </Text>
                </Radio>
                <Radio value="anonymized">
                  <Text strong>Anonymized IP Address</Text>
                  <Text type="secondary" style={{ display: 'block', fontSize: 12 }}>
                    Zeroes the last octet (e.g. 192.168.1.***). Compliant with data privacy rules.
                  </Text>
                </Radio>
                <Radio value="disabled">
                  <Text strong>Do Not Record IP Addresses</Text>
                  <Text type="secondary" style={{ display: 'block', fontSize: 12 }}>
                    No IP addresses are captured or stored in the database.
                  </Text>
                </Radio>
              </Space>
            </Radio.Group>
          </Form.Item>

          <Divider style={{ margin: '16px 0' }} />

          <Form.Item
            name="record_user_agent"
            label={<Text strong>Record User-Agent & Device Information</Text>}
            valuePropName="checked"
            extra="Stores browser, operating system, and client device category."
          >
            <Switch />
          </Form.Item>
        </Card>

        {/* Retention & Display */}
        <Card title="Data Retention & Display Preferences" bordered style={{ marginTop: 20 }}>
          <Row gutter={[24, 16]}>
            <Col xs={24} md={12}>
              <Form.Item
                name="retention_days"
                label={<Text strong>Data Retention Period</Text>}
                extra="Automatically delete login records older than this duration."
              >
                <Select style={{ width: '100%' }}>
                  <Option value={30}>Keep for 30 Days</Option>
                  <Option value={60}>Keep for 60 Days</Option>
                  <Option value={90}>Keep for 90 Days (Recommended)</Option>
                  <Option value={180}>Keep for 180 Days (6 Months)</Option>
                  <Option value={365}>Keep for 365 Days (1 Year)</Option>
                  <Option value={0}>Keep Forever (No Automatic Cleanup)</Option>
                </Select>
              </Form.Item>
            </Col>

            <Col xs={24} md={12}>
              <Form.Item
                name="default_date_range"
                label={<Text strong>Dashboard Default Date Range</Text>}
                extra="Default time window loaded on the Overview dashboard."
              >
                <Select style={{ width: '100%' }}>
                  <Option value="24_hours">Last 24 Hours</Option>
                  <Option value="7_days">Last 7 Days</Option>
                  <Option value="30_days">Last 30 Days</Option>
                  <Option value="90_days">Last 90 Days</Option>
                </Select>
              </Form.Item>
            </Col>
          </Row>

          <Row gutter={[24, 16]}>
            <Col xs={24} md={12}>
              <Form.Item
                name="display_timezone"
                label={<Text strong>Display Timezone</Text>}
                extra="Timestamps will be formatted in this timezone for display."
              >
                <Select showSearch style={{ width: '100%' }}>
                  {COMMON_TIMEZONES.map((tz) => (
                    <Option key={tz} value={tz}>
                      {tz}
                    </Option>
                  ))}
                </Select>
              </Form.Item>
            </Col>
          </Row>
        </Card>

        {/* Security Alert Thresholds */}
        <Card title="Security Insight Thresholds" bordered style={{ marginTop: 20 }}>
          <Paragraph type="secondary" style={{ fontSize: 13 }}>
            Adjust the sensitivity for the analytical security indicators displayed on the Security Insights tab.
          </Paragraph>

          <Row gutter={[24, 16]}>
            <Col xs={24} sm={12} md={8}>
              <Form.Item
                name="alert_failed_threshold"
                label={<Text strong>Failed Logins Threshold</Text>}
                extra="Number of failures per account before flagging."
              >
                <InputNumber min={1} max={100} style={{ width: '100%' }} />
              </Form.Item>
            </Col>

            <Col xs={24} sm={12} md={8}>
              <Form.Item
                name="alert_window_minutes"
                label={<Text strong>Detection Window (Minutes)</Text>}
                extra="Time span for counting consecutive failures."
              >
                <InputNumber min={1} max={1440} style={{ width: '100%' }} />
              </Form.Item>
            </Col>

            <Col xs={24} sm={12} md={8}>
              <Form.Item
                name="alert_ip_threshold"
                label={<Text strong>IP Failures Threshold</Text>}
                extra="Number of failures per IP before flagging."
              >
                <InputNumber min={1} max={500} style={{ width: '100%' }} />
              </Form.Item>
            </Col>
          </Row>

          <Form.Item
            name="alert_multi_failure_success"
            label={<Text strong>Flag Failures Followed by Success</Text>}
            valuePropName="checked"
            extra="Detect when an account experiences multiple failed attempts followed immediately by a successful login (potential brute-force success)."
          >
            <Switch />
          </Form.Item>
        </Card>

        {canEdit && (
          <div style={{ marginTop: 24, textAlign: 'right' }}>
            <Button
              type="primary"
              size="large"
              htmlType="submit"
              icon={<SaveOutlined />}
              loading={saving}
            >
              Save Configuration
            </Button>
          </div>
        )}
      </Form>

      {/* Manual Cleanup Tool */}
      <Card
        title="Database Maintenance & Manual Retention"
        bordered
        style={{ marginTop: 20, borderTop: '3px solid #fa8c16' }}
      >
        <Row justify="space-between" align="middle" gutter={[16, 16]}>
          <Col xs={24} md={16}>
            <Title level={5} style={{ margin: 0 }}>
              Total Stored Records: {totalRecords.toLocaleString()}
            </Title>
            <Text type="secondary">
              Automated cleanup executes during standard Pimcore maintenance (<code>bin/console pimcore:maintenance</code>) or via <code>bin/console login-tracker:cleanup</code>. You can also trigger an immediate purge of expired records right now.
            </Text>
          </Col>
          <Col xs={24} md={8} style={{ textAlign: 'right' }}>
            <Button
              danger
              icon={<DeleteOutlined />}
              loading={cleaning}
              disabled={!canEdit}
              onClick={handleRunCleanup}
            >
              Purge Expired Records Now
            </Button>
          </Col>
        </Row>
      </Card>
    </div>
  );
};
