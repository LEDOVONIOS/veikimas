import nodemailer from 'nodemailer';
import { EmailNotification, WebsiteStatus } from '@/lib/types';

export class EmailService {
  private transporter: nodemailer.Transporter;

  constructor() {
    // Initialize with Gmail SMTP by default
    // Can be configured with environment variables
    this.transporter = nodemailer.createTransporter({
      service: process.env.EMAIL_SERVICE || 'gmail',
      auth: {
        user: process.env.EMAIL_USER,
        pass: process.env.EMAIL_PASSWORD,
      },
    });
  }

  async sendStatusChangeNotification(notification: EmailNotification): Promise<void> {
    const subject = this.generateSubject(notification);
    const html = this.generateEmailHtml(notification);

    try {
      await this.transporter.sendMail({
        from: process.env.EMAIL_FROM || process.env.EMAIL_USER,
        to: notification.to,
        subject,
        html,
      });
      console.log(`Email sent to ${notification.to} for ${notification.websiteUrl}`);
    } catch (error) {
      console.error('Failed to send email:', error);
      throw new Error('Failed to send email notification');
    }
  }

  private generateSubject(notification: EmailNotification): string {
    const { websiteUrl, currentStatus, previousStatus } = notification;
    
    if (currentStatus === 'up' && previousStatus !== 'up' && previousStatus !== null) {
      return `✅ [RECOVERED] ${websiteUrl} is back online`;
    }
    
    switch (currentStatus) {
      case 'down':
        return `❌ [DOWN] ${websiteUrl} is not responding`;
      case 'client_error':
        return `⚠️ [CLIENT ERROR] ${websiteUrl} returned client error`;
      case 'ssl_error':
        return `🔒 [SSL ERROR] ${websiteUrl} has SSL certificate issues`;
      default:
        return `✅ [UP] ${websiteUrl} is online`;
    }
  }

  private generateEmailHtml(notification: EmailNotification): string {
    const statusEmoji = this.getStatusEmoji(notification.currentStatus);
    const statusColor = this.getStatusColor(notification.currentStatus);
    
    return `
      <!DOCTYPE html>
      <html>
      <head>
        <style>
          body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
          }
          .header {
            background-color: ${statusColor};
            color: white;
            padding: 20px;
            border-radius: 10px 10px 0 0;
            text-align: center;
          }
          .content {
            background-color: #f4f4f4;
            padding: 20px;
            border-radius: 0 0 10px 10px;
          }
          .status-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            background-color: ${statusColor};
            color: white;
            font-weight: bold;
            margin: 10px 0;
          }
          .details {
            background-color: white;
            padding: 15px;
            border-radius: 5px;
            margin-top: 15px;
          }
          .detail-row {
            margin: 10px 0;
            display: flex;
            justify-content: space-between;
          }
          .label {
            font-weight: bold;
            color: #666;
          }
          .value {
            color: #333;
          }
          .footer {
            margin-top: 20px;
            text-align: center;
            color: #666;
            font-size: 12px;
          }
        </style>
      </head>
      <body>
        <div class="header">
          <h1>${statusEmoji} Website Status Alert</h1>
          <h2>${notification.websiteUrl}</h2>
        </div>
        
        <div class="content">
          <p><strong>Status Change Detected:</strong></p>
          <div>
            Previous Status: <span class="status-badge" style="background-color: ${this.getStatusColor(notification.previousStatus || 'up')}">${this.formatStatus(notification.previousStatus)}</span>
            →
            Current Status: <span class="status-badge">${this.formatStatus(notification.currentStatus)}</span>
          </div>
          
          <div class="details">
            <h3>Check Details:</h3>
            <div class="detail-row">
              <span class="label">Timestamp:</span>
              <span class="value">${notification.timestamp.toLocaleString()}</span>
            </div>
            ${notification.statusCode ? `
            <div class="detail-row">
              <span class="label">HTTP Status Code:</span>
              <span class="value">${notification.statusCode}</span>
            </div>
            ` : ''}
            <div class="detail-row">
              <span class="label">Response Time:</span>
              <span class="value">${notification.responseTime}ms</span>
            </div>
            ${notification.error ? `
            <div class="detail-row">
              <span class="label">Error:</span>
              <span class="value" style="color: #d32f2f;">${notification.error}</span>
            </div>
            ` : ''}
            ${notification.sslInfo && notification.currentStatus === 'ssl_error' ? `
            <div class="detail-row">
              <span class="label">SSL Issue:</span>
              <span class="value" style="color: #d32f2f;">${notification.sslInfo.error || 'Invalid certificate'}</span>
            </div>
            ` : ''}
            ${notification.sslInfo && notification.sslInfo.valid && notification.sslInfo.daysRemaining !== undefined ? `
            <div class="detail-row">
              <span class="label">SSL Certificate Expires In:</span>
              <span class="value">${notification.sslInfo.daysRemaining} days</span>
            </div>
            ` : ''}
          </div>
          
          <div class="footer">
            <p>This is an automated notification from Website Monitor.</p>
            <p>You are receiving this because you subscribed to monitoring for ${notification.websiteUrl}</p>
          </div>
        </div>
      </body>
      </html>
    `;
  }

  private getStatusEmoji(status: WebsiteStatus): string {
    switch (status) {
      case 'up': return '✅';
      case 'down': return '❌';
      case 'client_error': return '⚠️';
      case 'ssl_error': return '🔒';
      default: return '❓';
    }
  }

  private getStatusColor(status: WebsiteStatus | null): string {
    switch (status) {
      case 'up': return '#4caf50';
      case 'down': return '#f44336';
      case 'client_error': return '#ff9800';
      case 'ssl_error': return '#9c27b0';
      default: return '#757575';
    }
  }

  private formatStatus(status: WebsiteStatus | null): string {
    if (!status) return 'Unknown';
    return status.toUpperCase().replace('_', ' ');
  }
}