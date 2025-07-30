import cron from 'node-cron';
import { getMonitorOrchestrator } from '@/lib/monitoring/monitorOrchestrator';

export class MonitorScheduler {
  private task: cron.ScheduledTask | null = null;

  start(): void {
    // Schedule monitoring checks every minute
    this.task = cron.schedule('* * * * *', async () => {
      console.log('Running scheduled monitor check...');
      
      try {
        const orchestrator = getMonitorOrchestrator();
        await orchestrator.checkAllMonitors();
      } catch (error) {
        console.error('Error during scheduled monitor check:', error);
      }
    });

    console.log('Monitor scheduler started - checking every minute');
  }

  stop(): void {
    if (this.task) {
      this.task.stop();
      this.task = null;
      console.log('Monitor scheduler stopped');
    }
  }

  isRunning(): boolean {
    return this.task !== null;
  }
}

// Singleton instance
let schedulerInstance: MonitorScheduler | null = null;

export function getMonitorScheduler(): MonitorScheduler {
  if (!schedulerInstance) {
    schedulerInstance = new MonitorScheduler();
  }
  return schedulerInstance;
}