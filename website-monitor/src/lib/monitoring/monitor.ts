import axios from 'axios';
import sslChecker from 'ssl-checker';
import { CheckResult, SSLInfo, WebsiteStatus } from '@/lib/types';

export class MonitoringService {
  private readonly timeout: number = 30000; // 30 seconds timeout

  async checkWebsite(url: string): Promise<CheckResult> {
    const startTime = Date.now();
    
    try {
      // Parse URL to check if it's HTTPS
      const urlObject = new URL(url);
      const isHttps = urlObject.protocol === 'https:';
      
      // Perform HTTP request
      const response = await axios.get(url, {
        timeout: this.timeout,
        validateStatus: () => true, // Accept any status code
        maxRedirects: 5,
      });
      
      const responseTime = Date.now() - startTime;
      const statusCode = response.status;
      
      // Check SSL certificate if HTTPS
      let sslInfo: SSLInfo | null = null;
      if (isHttps) {
        sslInfo = await this.checkSSL(urlObject.hostname, parseInt(urlObject.port || '443'));
      }
      
      // Determine website status based on response
      let status: WebsiteStatus;
      if (statusCode >= 200 && statusCode < 300) {
        status = sslInfo && !sslInfo.valid ? 'ssl_error' : 'up';
      } else if (statusCode >= 400 && statusCode < 500) {
        status = 'client_error';
      } else {
        status = 'down';
      }
      
      return {
        status,
        statusCode,
        responseTime,
        error: null,
        sslInfo,
      };
    } catch (error) {
      const responseTime = Date.now() - startTime;
      
      // Handle different types of errors
      let errorMessage = 'Unknown error occurred';
      if (axios.isAxiosError(error)) {
        if (error.code === 'ECONNABORTED') {
          errorMessage = 'Request timeout';
        } else if (error.code === 'ENOTFOUND') {
          errorMessage = 'DNS lookup failed';
        } else if (error.code === 'ECONNREFUSED') {
          errorMessage = 'Connection refused';
        } else if (error.message) {
          errorMessage = error.message;
        }
      } else if (error instanceof Error) {
        errorMessage = error.message;
      }
      
      return {
        status: 'down',
        statusCode: null,
        responseTime,
        error: errorMessage,
        sslInfo: null,
      };
    }
  }

  private async checkSSL(hostname: string, port: number = 443): Promise<SSLInfo> {
    try {
      const sslDetails = await sslChecker(hostname, {
        method: 'GET',
        port,
      });
      
      const now = new Date();
      const validTo = new Date(sslDetails.validTo);
      const daysRemaining = Math.floor((validTo.getTime() - now.getTime()) / (1000 * 60 * 60 * 24));
      
      return {
        valid: sslDetails.valid && daysRemaining > 0,
        daysRemaining,
        issuer: sslDetails.issuer,
        validFrom: new Date(sslDetails.validFrom),
        validTo,
        error: !sslDetails.valid ? 'Invalid SSL certificate' : 
               daysRemaining <= 0 ? 'SSL certificate expired' : undefined,
      };
    } catch (error) {
      return {
        valid: false,
        error: error instanceof Error ? error.message : 'SSL check failed',
      };
    }
  }
}