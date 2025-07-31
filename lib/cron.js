import cron from 'node-cron';
import { getEntries, updateEntry } from './storage.js';
import { checkSite } from './checker.js';
import { sendStatusChangeEmail } from './email.js';

function startScheduler() {
  return cron.schedule('* * * * *', async () => {
    const entries = getEntries();
    await Promise.all(
      entries.map(async (entry) => {
        const key = `${entry.url}|${entry.email}`;
        const result = await checkSite(entry.url);
        if (result.status !== entry.lastStatus) {
          await sendStatusChangeEmail({
            to: entry.email,
            url: entry.url,
            newStatus: result.status,
            oldStatus: entry.lastStatus,
            details: result,
          });
        }
        updateEntry(key, {
          ...result,
          lastStatus: result.status,
          lastChecked: new Date(),
        });
      })
    );
  });
}

if (!global.__monitorCron) {
  global.__monitorCron = startScheduler();
}

export const monitorCron = global.__monitorCron;