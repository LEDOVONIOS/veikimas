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