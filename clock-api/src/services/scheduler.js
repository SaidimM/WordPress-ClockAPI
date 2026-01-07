import cron from 'node-cron';
import { refreshImagePool, initializePool } from './imagePoolService.js';

/**
 * Initialize scheduled tasks
 */
export function initScheduler() {
  console.log('Initializing scheduler...');

  // Initialize image pool on startup
  setTimeout(async () => {
    await initializePool();
  }, 2000);

  // Run image pool refresh every hour at minute 0
  // Cron pattern: "0 * * * *" means "at minute 0 past every hour"
  const task = cron.schedule('0 * * * *', async () => {
    console.log('🔄 Running scheduled image pool refresh...');
    try {
      const result = await refreshImagePool();
      if (result.success) {
        console.log(`✓ Scheduled refresh completed: ${result.totalImages} total images (${result.newImages} new)`);
      } else {
        console.log(`✗ Scheduled refresh failed: ${result.error || result.message}`);
      }
    } catch (error) {
      console.error('Scheduled refresh error:', error);
    }
  }, {
    scheduled: true,
    timezone: "UTC"
  });

  console.log('✓ Scheduler initialized: Image pool refresh every hour');

  return task;
}
