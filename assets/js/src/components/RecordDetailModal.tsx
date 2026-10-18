import React from 'react';
import {
  Modal,
  Descriptions,
  Tag,
  Badge,
  Typography,
  Divider,
  Space,
  Button,
} from 'antd';
import {
  CheckCircleOutlined,
  CloseCircleOutlined,
  UserOutlined,
  GlobalOutlined,
  LaptopOutlined,
  LockOutlined,
} from '@ant-design/icons';
import { LoginRecordData } from '../types';

const { Text } = Typography;

interface RecordDetailModalProps {
  record: LoginRecordData | null;
  open: boolean;
  onClose: () => void;
  onFilterUser?: (username: string) => void;
  onFilterIp?: (ip: string) => void;
}

export const RecordDetailModal: React.FC<RecordDetailModalProps> = ({
  record,
  open,
  onClose,
  onFilterUser,
  onFilterIp,
}) => {
  if (!record) return null;

  return (
    <Modal
      title={
        <Space>
          {record.is_success ? (
            <CheckCircleOutlined style={{ color: '#52c41a', fontSize: 20 }} />
          ) : (
            <CloseCircleOutlined style={{ color: '#ff4d4f', fontSize: 20 }} />
          )}
          <span>
            Login Event #{record.id} — {record.is_success ? 'Successful Login' : 'Failed Login Attempt'}
          </span>
        </Space>
      }
      open={open}
      onCancel={onClose}
      footer={[
        <Button key="close" type="primary" onClick={onClose}>
          Close
        </Button>,
      ]}
      width={720}
    >
      <div style={{ marginTop: 16 }}>
        <Descriptions title="Account & Identity" bordered size="small" column={2}>
          <Descriptions.Item label="Username">
            <Space>
              <Text strong>{record.username}</Text>
              {onFilterUser && (
                <Button type="link" size="small" onClick={() => onFilterUser(record.username)}>
                  Filter by user
                </Button>
              )}
            </Space>
          </Descriptions.Item>
          <Descriptions.Item label="Display Name">
            {record.display_name}
          </Descriptions.Item>
          <Descriptions.Item label="Pimcore User ID">
            {record.user_id ? <Tag color="blue">#{record.user_id}</Tag> : <Text type="secondary">Not resolved / Non-existent</Text>}
          </Descriptions.Item>
          <Descriptions.Item label="Result Status">
            {record.is_success ? (
              <Tag color="success">Successful</Tag>
            ) : (
              <Tag color="error">Failed</Tag>
            )}
          </Descriptions.Item>
        </Descriptions>

        <Divider style={{ margin: '16px 0' }} />

        <Descriptions title="Authentication & Security" bordered size="small" column={2}>
          <Descriptions.Item label="Timestamp (Configured)">
            {record.formatted_time}
          </Descriptions.Item>
          <Descriptions.Item label="UTC Time">
            {record.login_time}
          </Descriptions.Item>
          <Descriptions.Item label="Auth Method">
            <Tag color="purple">{record.auth_method_label}</Tag>
          </Descriptions.Item>
          <Descriptions.Item label="Firewall">
            {record.firewall || 'pimcore_studio'}
          </Descriptions.Item>
          {!record.is_success && (
            <>
              <Descriptions.Item label="Failure Reason" span={2}>
                <Text type="danger" strong>
                  {record.failure_reason || 'Authentication failed'}
                </Text>
              </Descriptions.Item>
              <Descriptions.Item label="Failure Type" span={2}>
                <Tag color="volcano">{record.failure_type || 'unknown'}</Tag>
              </Descriptions.Item>
            </>
          )}
        </Descriptions>

        <Divider style={{ margin: '16px 0' }} />

        <Descriptions title="Network & Device Context" bordered size="small" column={2}>
          <Descriptions.Item label="Source IP">
            <Space>
              <code>{record.ip_address || 'Not recorded'}</code>
              {record.ip_address && onFilterIp && (
                <Button type="link" size="small" onClick={() => onFilterIp(record.ip_address!)}>
                  Filter by IP
                </Button>
              )}
            </Space>
          </Descriptions.Item>
          <Descriptions.Item label="Device Type">
            <Tag>{record.device_type}</Tag>
          </Descriptions.Item>
          <Descriptions.Item label="Browser">
            {record.browser}
          </Descriptions.Item>
          <Descriptions.Item label="Operating System">
            {record.os}
          </Descriptions.Item>
          {record.user_agent && (
            <Descriptions.Item label="User-Agent String" span={2}>
              <Text code style={{ fontSize: 11, wordBreak: 'break-all' }}>
                {record.user_agent}
              </Text>
            </Descriptions.Item>
          )}
          {record.route && (
            <Descriptions.Item label="Route" span={2}>
              <Text code>{record.route}</Text>
            </Descriptions.Item>
          )}
        </Descriptions>

        {record.details && Object.keys(record.details).length > 0 && (
          <>
            <Divider style={{ margin: '16px 0' }} />
            <Descriptions title="Additional Details" bordered size="small" column={1}>
              <Descriptions.Item label="Context">
                <pre style={{ fontSize: 11, background: '#f5f5f5', padding: 8, borderRadius: 4 }}>
                  {JSON.stringify(record.details, null, 2)}
                </pre>
              </Descriptions.Item>
            </Descriptions>
          </>
        )}
      </div>
    </Modal>
  );
};
