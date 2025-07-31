export type WebsiteStatus = 'up' | 'down' | 'client_error' | 'ssl_error';

export interface SSLInfo {
  valid: boolean;
  daysRemaining?: number;
  issuer?: string;
  validFrom?: Date;
  validTo?: Date;
  error?: string;
}

export interface MonitorEntry {
  id: string;
  url: string;
  email: string;
  lastStatus: WebsiteStatus | null;
  lastChecked: Date | null;
  lastResponseTime: number | null;
  lastStatusCode: number | null;
  lastError: string | null;
  sslInfo: SSLInfo | null;
  createdAt: Date;
  monthlyMonitoringEnabled?: boolean;
}

export interface CheckResult {
  status: WebsiteStatus;
  statusCode: number | null;
  responseTime: number;
  error: string | null;
  sslInfo: SSLInfo | null;
}

export interface EmailNotification {
  to: string;
  subject: string;
  websiteUrl: string;
  previousStatus: WebsiteStatus | null;
  currentStatus: WebsiteStatus;
  statusCode: number | null;
  error: string | null;
  timestamp: Date;
  responseTime: number;
  sslInfo: SSLInfo | null;
}

// New types for historical data
export interface ResponseTimeRecord {
  id: string;
  monitorId: string;
  timestamp: Date;
  responseTime: number;
  status: WebsiteStatus;
  statusCode: number | null;
}

export interface Incident {
  id: string;
  monitorId: string;
  status: WebsiteStatus;
  startTime: Date;
  endTime: Date | null;
  duration: number | null; // in milliseconds
  rootCause: string;
  resolved: boolean;
}

export interface UptimeStats {
  period: '7d' | '30d' | '365d';
  uptimePercentage: number;
  totalChecks: number;
  successfulChecks: number;
  totalIncidents: number;
  totalDowntime: number; // in milliseconds
}

export interface ResponseTimeStats {
  period: '24h' | '7d' | '30d';
  average: number;
  min: number;
  max: number;
  p95: number;
  p99: number;
  dataPoints: Array<{
    timestamp: Date;
    value: number;
  }>;
}

export interface MonitorDashboardData {
  monitor: MonitorEntry;
  currentStatus: WebsiteStatus;
  lastChecked: Date;
  uptimeStats: {
    last7Days: UptimeStats;
    last30Days: UptimeStats;
    last365Days: UptimeStats;
  };
  responseTimeStats: {
    last24Hours: ResponseTimeStats;
    last7Days: ResponseTimeStats;
    last30Days: ResponseTimeStats;
  };
  recentIncidents: Incident[];
  sslCertificate: {
    expiryDate: Date | null;
    daysUntilExpiry: number | null;
    issuer: string | null;
  };
}