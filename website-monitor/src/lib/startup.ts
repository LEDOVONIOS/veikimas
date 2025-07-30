import { getMonitorScheduler } from './cron/scheduler';

let isInitialized = false;

export function initializeApp() {
  if (isInitialized) {
    return;
  }

  // Start the monitoring scheduler
  const scheduler = getMonitorScheduler();
  scheduler.start();
  
  console.log('🚀 Website Monitor initialized');
  console.log('📊 Monitoring scheduler started');
  
  isInitialized = true;

  // Graceful shutdown
  process.on('SIGINT', () => {
    console.log('\n🛑 Shutting down Website Monitor...');
    scheduler.stop();
    process.exit(0);
  });

  process.on('SIGTERM', () => {
    console.log('\n🛑 Shutting down Website Monitor...');
    scheduler.stop();
    process.exit(0);
  });
}