import { MonitorEntry } from '@/lib/types';
import fs from 'fs/promises';
import path from 'path';

export class MonitorStore {
  private monitors: Map<string, MonitorEntry> = new Map();
  private persistenceFile: string;
  private persistenceInterval: NodeJS.Timeout | null = null;

  constructor() {
    this.persistenceFile = path.join(process.cwd(), 'data', 'monitors.json');
    this.loadFromFile();
    
    // Auto-save every 30 seconds
    this.persistenceInterval = setInterval(() => {
      this.saveToFile();
    }, 30000);
  }

  async addMonitor(url: string, email: string): Promise<MonitorEntry> {
    const id = this.generateId(url, email);
    
    // Check if monitor already exists
    if (this.monitors.has(id)) {
      return this.monitors.get(id)!;
    }

    const monitor: MonitorEntry = {
      id,
      url,
      email,
      lastStatus: null,
      lastChecked: null,
      lastResponseTime: null,
      lastStatusCode: null,
      lastError: null,
      sslInfo: null,
      createdAt: new Date(),
    };

    this.monitors.set(id, monitor);
    await this.saveToFile();
    
    return monitor;
  }

  getMonitor(id: string): MonitorEntry | undefined {
    return this.monitors.get(id);
  }

  getMonitorByUrlAndEmail(url: string, email: string): MonitorEntry | undefined {
    const id = this.generateId(url, email);
    return this.monitors.get(id);
  }

  getAllMonitors(): MonitorEntry[] {
    return Array.from(this.monitors.values());
  }

  getMonitorsByEmail(email: string): MonitorEntry[] {
    return Array.from(this.monitors.values()).filter(m => m.email === email);
  }

  async updateMonitor(id: string, updates: Partial<MonitorEntry>): Promise<MonitorEntry | null> {
    const monitor = this.monitors.get(id);
    if (!monitor) {
      return null;
    }

    const updatedMonitor = {
      ...monitor,
      ...updates,
      id: monitor.id, // Ensure ID cannot be changed
    };

    this.monitors.set(id, updatedMonitor);
    await this.saveToFile();
    
    return updatedMonitor;
  }

  async removeMonitor(id: string): Promise<boolean> {
    const result = this.monitors.delete(id);
    if (result) {
      await this.saveToFile();
    }
    return result;
  }

  private generateId(url: string, email: string): string {
    // Create a unique ID based on URL and email
    return Buffer.from(`${url}:${email}`).toString('base64');
  }

  private async loadFromFile(): Promise<void> {
    try {
      const data = await fs.readFile(this.persistenceFile, 'utf-8');
      const parsed = JSON.parse(data);
      
      // Reconstruct the Map from the saved data
      this.monitors.clear();
      for (const [id, monitor] of Object.entries(parsed)) {
        // Convert date strings back to Date objects
        const monitorEntry = monitor as any;
        this.monitors.set(id, {
          ...monitorEntry,
          lastChecked: monitorEntry.lastChecked ? new Date(monitorEntry.lastChecked) : null,
          createdAt: new Date(monitorEntry.createdAt),
          sslInfo: monitorEntry.sslInfo ? {
            ...monitorEntry.sslInfo,
            validFrom: monitorEntry.sslInfo.validFrom ? new Date(monitorEntry.sslInfo.validFrom) : undefined,
            validTo: monitorEntry.sslInfo.validTo ? new Date(monitorEntry.sslInfo.validTo) : undefined,
          } : null,
        });
      }
      
      console.log(`Loaded ${this.monitors.size} monitors from file`);
    } catch (error) {
      // File doesn't exist or is invalid, start with empty store
      console.log('No existing monitor data found, starting fresh');
      this.monitors.clear();
    }
  }

  private async saveToFile(): Promise<void> {
    try {
      // Ensure directory exists
      const dir = path.dirname(this.persistenceFile);
      await fs.mkdir(dir, { recursive: true });
      
      // Convert Map to object for JSON serialization
      const data: Record<string, MonitorEntry> = {};
      for (const [id, monitor] of this.monitors) {
        data[id] = monitor;
      }
      
      await fs.writeFile(this.persistenceFile, JSON.stringify(data, null, 2));
    } catch (error) {
      console.error('Failed to save monitors to file:', error);
    }
  }

  cleanup(): void {
    if (this.persistenceInterval) {
      clearInterval(this.persistenceInterval);
      this.persistenceInterval = null;
    }
    this.saveToFile();
  }
}

// Singleton instance
let storeInstance: MonitorStore | null = null;

export function getMonitorStore(): MonitorStore {
  if (!storeInstance) {
    storeInstance = new MonitorStore();
  }
  return storeInstance;
}