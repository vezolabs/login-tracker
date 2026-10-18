import React, { useEffect, useState } from 'react';
import {
  Table,
  Card,
  Input,
  Select,
  Button,
  Space,
  Tag,
  Typography,
  Tooltip,
  DatePicker,
  Row,
  Col,
  message,
} from 'antd';
import {
  SearchOutlined,
  ReloadOutlined,
  DownloadOutlined,
  EyeOutlined,
  CheckCircleOutlined,
  CloseCircleOutlined,
  UserOutlined,
  GlobalOutlined,
  FilterOutlined,
  ClearOutlined,
} from '@ant-design/icons';
import type { ColumnsType } from 'antd/es/table';
import { loginTrackerApi } from '../services/api';
import { LoginRecordData } from '../types';
import { RecordDetailModal } from './RecordDetailModal';

const { Text } = Typography;
const { Option } = Select;
const { RangePicker } = DatePicker;

interface LoginActivityTableProps {
  initialUsername?: string;
  initialIp?: string;
  onSelectUser?: (username: string) => void;
}

export const LoginActivityTable: React.FC<LoginActivityTableProps> = ({
  initialUsername,
  initialIp,
  onSelectUser,
}) => {
  const [loading, setLoading] = useState<boolean>(false);
  const [data, setData] = useState<LoginRecordData[]>([]);
  const [total, setTotal] = useState<number>(0);
  const [page, setPage] = useState<number>(1);
  const [pageSize, setPageSize] = useState<number>(20);
  const [sortBy, setSortBy] = useState<string>('login_time');
  const [sortOrder, setSortOrder] = useState<string>('DESC');
  const [timezone, setTimezone] = useState<string>('UTC');
  const [canViewSensitive, setCanViewSensitive] = useState<boolean>(false);

  // Filters
  const [search, setSearch] = useState<string>('');
  const [username, setUsername] = useState<string>(initialUsername || '');
  const [status, setStatus] = useState<string>('');
  const [authMethod, setAuthMethod] = useState<string>('');
  const [ipAddress, setIpAddress] = useState<string>(initialIp || '');
  const [dateRange, setDateRange] = useState<[string | null, string | null]>([null, null]);

  // Modal
  const [selectedRecord, setSelectedRecord] = useState<LoginRecordData | null>(null);
  const [modalOpen, setModalOpen] = useState<boolean>(false);

  // Sync props if changed
  useEffect(() => {
    if (initialUsername !== undefined) setUsername(initialUsername);
    if (initialIp !== undefined) setIpAddress(initialIp);
  }, [initialUsername, initialIp]);

  const fetchData = async (
    targetPage = page,
    targetLimit = pageSize,
    targetSortBy = sortBy,
    targetSortOrder = sortOrder
  ) => {
    setLoading(true);
    try {
      const params: Record<string, any> = {
        page: targetPage,
        limit: targetLimit,
        sortBy: targetSortBy,
        sortOrder: targetSortOrder,
      };

      if (search.trim()) params.search = search.trim();
      if (username.trim()) params.username = username.trim();
      if (status) params.status = status;
      if (authMethod) params.auth_method = authMethod;
      if (ipAddress.trim()) params.ip_address = ipAddress.trim();
      if (dateRange[0]) params.date_from = dateRange[0];
      if (dateRange[1]) params.date_to = dateRange[1];

      const res = await loginTrackerApi.getRecords(params);
      setData(res.items);
      setTotal(res.total);
      setTimezone(res.timezone);
      setCanViewSensitive(res.can_view_sensitive);
    } catch (e) {
      console.error('Failed to load login records', e);
      message.error('Failed to load login records.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchData(1, pageSize, sortBy, sortOrder);
  }, [page, pageSize, sortBy, sortOrder, status, authMethod]);

  const handleSearch = () => {
    setPage(1);
    fetchData(1, pageSize, sortBy, sortOrder);
  };

  const handleReset = () => {
    setSearch('');
    setUsername('');
    setStatus('');
    setAuthMethod('');
    setIpAddress('');
    setDateRange([null, null]);
    setPage(1);
    // Reload without filters
    setLoading(true);
    loginTrackerApi
      .getRecords({ page: 1, limit: pageSize, sortBy, sortOrder })
      .then((res) => {
        setData(res.items);
        setTotal(res.total);
      })
      .finally(() => setLoading(false));
  };

  const handleExport = () => {
    const params: Record<string, any> = {};
    if (search.trim()) params.search = search.trim();
    if (username.trim()) params.username = username.trim();
    if (status) params.status = status;
    if (authMethod) params.auth_method = authMethod;
    if (ipAddress.trim()) params.ip_address = ipAddress.trim();
    if (dateRange[0]) params.date_from = dateRange[0];
    if (dateRange[1]) params.date_to = dateRange[1];

    const exportUrl = loginTrackerApi.getExportUrl(params);
    window.open(exportUrl, '_blank');
  };

  const handleTableChange = (pagination: any, filters: any, sorter: any) => {
    const newPage = pagination.current || 1;
    const newPageSize = pagination.pageSize || 20;
    let newSortBy = sortBy;
    let newSortOrder = sortOrder;

    if (sorter && sorter.field) {
      newSortBy = String(sorter.field);
      newSortOrder = sorter.order === 'ascend' ? 'ASC' : 'DESC';
    }

    setPage(newPage);
    setPageSize(newPageSize);
    setSortBy(newSortBy);
    setSortOrder(newSortOrder);
    fetchData(newPage, newPageSize, newSortBy, newSortOrder);
  };

  const columns: ColumnsType<LoginRecordData> = [
    {
      title: `Time (${timezone})`,
      dataIndex: 'formatted_time',
      key: 'login_time',
      sorter: true,
      width: 170,
      render: (val: string, record) => (
        <Tooltip title={`UTC: ${record.login_time}`}>
          <Text style={{ fontSize: 13 }}>{val}</Text>
        </Tooltip>
      ),
    },
    {
      title: 'User',
      dataIndex: 'username',
      key: 'username',
      sorter: true,
      width: 180,
      render: (val: string, record) => (
        <Space direction="vertical" size={0}>
          <Space size={4}>
            <UserOutlined style={{ color: '#1890ff', fontSize: 12 }} />
            <Text
              strong
              style={{ cursor: onSelectUser ? 'pointer' : 'default', color: onSelectUser ? '#1890ff' : 'inherit' }}
              onClick={() => onSelectUser && onSelectUser(val)}
            >
              {val}
            </Text>
          </Space>
          {record.display_name && record.display_name !== val && (
            <Text type="secondary" style={{ fontSize: 11 }}>
              {record.display_name}
            </Text>
          )}
        </Space>
      ),
    },
    {
      title: 'Result',
      dataIndex: 'status',
      key: 'status',
      sorter: true,
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
      width: 150,
      render: (ip: string | null) =>
        ip ? (
          <Space size={4}>
            <GlobalOutlined style={{ color: '#8c8c8c', fontSize: 12 }} />
            <code style={{ fontSize: 12 }}>{ip}</code>
          </Space>
        ) : (
          <Text type="secondary">—</Text>
        ),
    },
    {
      title: 'Device & Client',
      key: 'device',
      width: 180,
      render: (_: any, record) => (
        <Space size={4} wrap>
          <Tag style={{ fontSize: 11 }}>{record.browser}</Tag>
          <Tag style={{ fontSize: 11 }}>{record.os}</Tag>
        </Space>
      ),
    },
    {
      title: 'Failure Reason',
      dataIndex: 'failure_reason',
      key: 'failure_reason',
      ellipsis: true,
      render: (reason: string | null, record) =>
        !record.is_success ? (
          <Tooltip title={reason || 'Authentication failed'}>
            <Text type="danger" style={{ fontSize: 12 }}>
              {reason || 'Authentication failed'}
            </Text>
          </Tooltip>
        ) : (
          <Text type="secondary">—</Text>
        ),
    },
    {
      title: 'Action',
      key: 'action',
      width: 100,
      render: (_: any, record) => (
        <Button
          type="link"
          size="small"
          icon={<EyeOutlined />}
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
    <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
      {/* Filter Toolbar */}
      <Card size="small" style={{ background: '#fafafa', border: '1px solid #f0f0f0' }}>
        <Row gutter={[12, 12]} align="middle">
          <Col xs={24} sm={12} md={6}>
            <Input
              placeholder="Search user, name, reason..."
              prefix={<SearchOutlined />}
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              onPressEnter={handleSearch}
              allowClear
            />
          </Col>

          <Col xs={12} sm={6} md={4}>
            <Select
              placeholder="Status"
              value={status || undefined}
              onChange={(val) => setStatus(val || '')}
              style={{ width: '100%' }}
              allowClear
            >
              <Option value="success">Successful</Option>
              <Option value="failed">Failed</Option>
            </Select>
          </Col>

          <Col xs={12} sm={6} md={4}>
            <Select
              placeholder="Auth Method"
              value={authMethod || undefined}
              onChange={(val) => setAuthMethod(val || '')}
              style={{ width: '100%' }}
              allowClear
            >
              <Option value="password">Password</Option>
              <Option value="token">Studio Token</Option>
              <Option value="sso">Single Sign-On (SAML)</Option>
              <Option value="other">Other</Option>
            </Select>
          </Col>

          <Col xs={24} sm={12} md={5}>
            <Input
              placeholder="Filter by IP address"
              prefix={<GlobalOutlined />}
              value={ipAddress}
              onChange={(e) => setIpAddress(e.target.value)}
              onPressEnter={handleSearch}
              allowClear
            />
          </Col>

          <Col xs={24} sm={12} md={5}>
            <Space style={{ width: '100%', justifyContent: 'flex-end' }}>
              <Button type="primary" icon={<FilterOutlined />} onClick={handleSearch}>
                Apply
              </Button>
              <Button icon={<ClearOutlined />} onClick={handleReset}>
                Reset
              </Button>
              <Tooltip title="Export current filtered view to CSV">
                <Button icon={<DownloadOutlined />} onClick={handleExport}>
                  CSV
                </Button>
              </Tooltip>
              <Button icon={<ReloadOutlined />} onClick={() => fetchData()} />
            </Space>
          </Col>
        </Row>
      </Card>

      {/* Main Table */}
      <Card bodyStyle={{ padding: 0 }}>
        <Table
          columns={columns}
          dataSource={data}
          rowKey="id"
          loading={loading}
          pagination={{
            current: page,
            pageSize,
            total,
            showSizeChanger: true,
            pageSizeOptions: ['10', '20', '50', '100'],
            showTotal: (totalCount) => `Total ${totalCount} login events`,
          }}
          onChange={handleTableChange}
          size="middle"
        />
      </Card>

      {/* Detail Modal */}
      <RecordDetailModal
        record={selectedRecord}
        open={modalOpen}
        onClose={() => setModalOpen(false)}
        onFilterUser={(u) => {
          setModalOpen(false);
          setUsername(u);
          setSearch('');
          fetchData();
        }}
        onFilterIp={(ip) => {
          setModalOpen(false);
          setIpAddress(ip);
          fetchData();
        }}
      />
    </div>
  );
};
