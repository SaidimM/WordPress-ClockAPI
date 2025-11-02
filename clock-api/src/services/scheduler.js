import cron from 'node-cron';
import { downloadAndCacheImages } from './imageCacheService.js';

/**
 * Initialize scheduled tasks
 */
export function initScheduler() {
  console.log('Initializing scheduler...');

  // Run image cache refresh every 12 hours at minute 0
  // Cron pattern: "0 */12 * * *" means "at minute 0 past every 12th hour"
  const task = cron.schedule('0 */12 * * *', async () => {
    console.log('🔄 Running scheduled image cache refresh...');
    try {
      const result = await downloadAndCacheImages();
      if (result.success) {
        console.log(`✓ Scheduled refresh completed: ${result.downloaded} images downloaded, ${result.failed} failed`);
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

  console.log('✓ Scheduler initialized: Image cache refresh every 12 hours');

  // Optional: Run initial cache refresh on startup if cache is empty
  // Uncomment the following lines if you want to auto-populate cache on startup
  /*
  setTimeout(async () => {
    const { getCacheStats } = await import('./imageCacheService.js');
    const stats = getCacheStats();
    if (stats.imageCount === 0) {
      console.log('Cache is empty, running initial image download...');
      await downloadAndCacheImages();
    }
  }, 5000);
  */

  return task;
}
