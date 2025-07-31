import { ResponseTimeRecord, Incident, WebsiteStatus } from '@/lib/types';
import fs from 'fs/promises';
import path from 'path';

export class HistoricalDataStore {
  private responseTimesFile: string;
  private incidentsFile: string;
  private responseTimeCache: Map<string, ResponseTimeRecord[]> = new Map();
  private incidentCache: Map<string, Incident[]> = new Map();
  private saveInterval: NodeJS.Timeout | null = null;

  constructor() {
    const dataDir = path.join(process.cwd(), 'data');
    this.responseTimesFile = path.join(dataDir, 'response-times.json');
    this.incidentsFile = path.join(dataDir, 'incidents.json');
    
    this.loadFromFiles();
    
    // Auto-save every minute
    this.saveInterval = setInterval(() => {
      this.saveToFiles();
    }, 60000);
  }

  // Response Time Methods
  async addResponseTime(monitorId: string, record: Omit<ResponseTimeRecord, 'id'>): Promise<void> {
    const id = `rt_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
    const fullRecord: ResponseTimeRecord = { ...record, id };
    
    if (!this.responseTimeCache.has(monitorId)) {
      this.responseTimeCache.set(monitorId, []);
    }
    
    const records = this.responseTimeCache.get(monitorId)!;
    records.push(fullRecord);
    
    // Keep only last 365 days of data
    const oneYearAgo = new Date();
    oneYearAgo.setDate(oneYearAgo.getDate() - 365);
    const filtered = records.filter(r => new Date(r.timestamp) > oneYearAgo);
    this.responseTimeCache.set(monitorId, filtered);
  }

  getResponseTimes(monitorId: string, since: Date): ResponseTimeRecord[] {
    const records = this.responseTimeCache.get(monitorId) || [];
    return records.filter(r => new Date(r.timestamp) >= since);
  }

  // Incident Methods
  async startIncident(monitorId: string, status: WebsiteStatus, rootCause: string): Promise<Incident> {
    const incident: Incident = {
      id: `inc_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`,
      monitorId,
      status,
      startTime: new Date(),
      endTime: null,
      duration: null,
      rootCause,
      resolved: false,
    };
    
    if (!this.incidentCache.has(monitorId)) {
      this.incidentCache.set(monitorId, []);
    }
    
    this.incidentCache.get(monitorId)!.push(incident);
    await this.saveToFiles();
    
    return incident;
  }

  async endIncident(monitorId: string, incidentId: string): Promise<void> {
    const incidents = this.incidentCache.get(monitorId);
    if (!incidents) return;
    
    const incident = incidents.find(i => i.id === incidentId);
    if (!incident || incident.resolved) return;
    
    incident.endTime = new Date();
    incident.duration = incident.endTime.getTime() - new Date(incident.startTime).getTime();
    incident.resolved = true;
    
    await this.saveToFiles();
  }

  getActiveIncident(monitorId: string): Incident | null {
    const incidents = this.incidentCache.get(monitorId) || [];
    return incidents.find(i => !i.resolved) || null;
  }

  getIncidents(monitorId: string, since?: Date): Incident[] {
    const incidents = this.incidentCache.get(monitorId) || [];
    if (!since) return incidents;
    return incidents.filter(i => new Date(i.startTime) >= since);
  }

  // Uptime Calculation Methods
  calculateUptimeStats(monitorId: string, days: number) {
    const since = new Date();
    since.setDate(since.getDate() - days);
    
    const records = this.getResponseTimes(monitorId, since);
    const incidents = this.getIncidents(monitorId, since);
    
    const totalChecks = records.length;
    const successfulChecks = records.filter(r => r.status === 'up').length;
    const uptimePercentage = totalChecks > 0 ? (successfulChecks / totalChecks) * 100 : 100;
    
    const totalDowntime = incidents
      .filter(i => i.resolved && i.duration)
      .reduce((sum, i) => sum + (i.duration || 0), 0);
    
    // Add ongoing incident downtime
    const activeIncident = incidents.find(i => !i.resolved);
    const ongoingDowntime = activeIncident 
      ? Date.now() - new Date(activeIncident.startTime).getTime()
      : 0;
    
    return {
      period: days === 7 ? '7d' : days === 30 ? '30d' : '365d',
      uptimePercentage: Math.round(uptimePercentage * 100) / 100,
      totalChecks,
      successfulChecks,
      totalIncidents: incidents.length,
      totalDowntime: totalDowntime + ongoingDowntime,
    };
  }

  // Response Time Statistics
  calculateResponseTimeStats(monitorId: string, hours: number) {
    const since = new Date();
    since.setHours(since.getHours() - hours);
    
    const records = this.getResponseTimes(monitorId, since)
      .filter(r => r.status === 'up') // Only count successful responses
      .sort((a, b) => new Date(a.timestamp).getTime() - new Date(b.timestamp).getTime());
    
    if (records.length === 0) {
      return {
        period: hours === 24 ? '24h' : hours === 168 ? '7d' : '30d',
        average: 0,
        min: 0,
        max: 0,
        p95: 0,
        p99: 0,
        dataPoints: [],
      };
    }
    
    const responseTimes = records.map(r => r.responseTime);
    const sorted = [...responseTimes].sort((a, b) => a - b);
    
    const average = responseTimes.reduce((sum, t) => sum + t, 0) / responseTimes.length;
    const min = sorted[0];
    const max = sorted[sorted.length - 1];
    const p95Index = Math.floor(sorted.length * 0.95);
    const p99Index = Math.floor(sorted.length * 0.99);
    
    // Create data points for chart (max 100 points)
    const interval = Math.max(1, Math.floor(records.length / 100));
    const dataPoints = records
      .filter((_, index) => index % interval === 0)
      .map(r => ({
        timestamp: new Date(r.timestamp),
        value: r.responseTime,
      }));
    
    return {
      period: hours === 24 ? '24h' : hours === 168 ? '7d' : '30d',
      average: Math.round(average),
      min,
      max,
      p95: sorted[p95Index] || max,
      p99: sorted[p99Index] || max,
      dataPoints,
    };
  }

  // Persistence Methods
  private async loadFromFiles(): Promise<void> {
    try {
      // Load response times
      const rtData = await fs.readFile(this.responseTimesFile, 'utf-8');
      const rtParsed = JSON.parse(rtData);
      
      this.responseTimeCache.clear();
      for (const [monitorId, records] of Object.entries(rtParsed)) {
        this.responseTimeCache.set(monitorId, (records as any[]).map(r => ({
          ...r,
          timestamp: new Date(r.timestamp),
        })));
      }
    } catch (error) {
      console.log('No response time data found, starting fresh');
    }
    
    try {
      // Load incidents
      const incData = await fs.readFile(this.incidentsFile, 'utf-8');
      const incParsed = JSON.parse(incData);
      
      this.incidentCache.clear();
      for (const [monitorId, incidents] of Object.entries(incParsed)) {
        this.incidentCache.set(monitorId, (incidents as any[]).map(i => ({
          ...i,
          startTime: new Date(i.startTime),
          endTime: i.endTime ? new Date(i.endTime) : null,
        })));
      }
    } catch (error) {
      console.log('No incident data found, starting fresh');
    }
  }

  private async saveToFiles(): Promise<void> {
    try {
      const dir = path.dirname(this.responseTimesFile);
      await fs.mkdir(dir, { recursive: true });
      
      // Save response times
      const rtData: Record<string, ResponseTimeRecord[]> = {};
      for (const [monitorId, records] of this.responseTimeCache) {
        rtData[monitorId] = records;
      }
      await fs.writeFile(this.responseTimesFile, JSON.stringify(rtData, null, 2));
      
      // Save incidents
      const incData: Record<string, Incident[]> = {};
      for (const [monitorId, incidents] of this.incidentCache) {
        incData[monitorId] = incidents;
      }
      await fs.writeFile(this.incidentsFile, JSON.stringify(incData, null, 2));
    } catch (error) {
      console.error('Failed to save historical data:', error);
    }
  }

  cleanup(): void {
    if (this.saveInterval) {
      clearInterval(this.saveInterval);
      this.saveInterval = null;
    }
    this.saveToFiles();
  }
}

// Singleton instance
let historicalStore: HistoricalDataStore | null = null;

export function getHistoricalStore(): HistoricalDataStore {
  if (!historicalStore) {
    historicalStore = new HistoricalDataStore();
  }
  return historicalStore;
}