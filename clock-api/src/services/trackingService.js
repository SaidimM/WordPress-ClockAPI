import { getDatabase } from '../database/db.js';

/**
 * Track image view
 */
export function trackImageView(data) {
  try {
    const db = getDatabase();
    const stmt = db.prepare(`
      INSERT INTO image_views (image_id, photographer, photographer_url, user_agent, ip_address, platform)
      VALUES (?, ?, ?, ?, ?, ?)
    `);

    const result = stmt.run(
      data.imageId || null,
      data.photographer || null,
      data.photographerUrl || null,
      data.userAgent || null,
      data.ipAddress || null,
      data.platform || null
    );

    return {
      success: true,
      id: result.lastInsertRowid
    };
  } catch (error) {
    console.error('Error tracking view:', error);
    throw error;
  }
}

/**
 * Track image download
 */
export function trackImageDownload(data) {
  try {
    const db = getDatabase();
    const stmt = db.prepare(`
      INSERT INTO image_downloads (image_id, photographer, photographer_url, user_agent, ip_address, platform)
      VALUES (?, ?, ?, ?, ?, ?)
    `);

    const result = stmt.run(
      data.imageId || null,
      data.photographer || null,
      data.photographerUrl || null,
      data.userAgent || null,
      data.ipAddress || null,
      data.platform || null
    );

    return {
      success: true,
      id: result.lastInsertRowid
    };
  } catch (error) {
    console.error('Error tracking download:', error);
    throw error;
  }
}

/**
 * Get statistics
 */
export function getStatistics(options = {}) {
  try {
    const db = getDatabase();
    const { days = 30 } = options;

    // Total views
    const totalViews = db.prepare('SELECT COUNT(*) as count FROM image_views').get().count;

    // Total downloads
    const totalDownloads = db.prepare('SELECT COUNT(*) as count FROM image_downloads').get().count;

    // Views in last N days
    const recentViews = db.prepare(`
      SELECT COUNT(*) as count
      FROM image_views
      WHERE created_at >= datetime('now', '-${days} days')
    `).get().count;

    // Downloads in last N days
    const recentDownloads = db.prepare(`
      SELECT COUNT(*) as count
      FROM image_downloads
      WHERE created_at >= datetime('now', '-${days} days')
    `).get().count;

    // Most viewed images
    const mostViewedImages = db.prepare(`
      SELECT
        image_id,
        photographer,
        COUNT(*) as view_count
      FROM image_views
      WHERE image_id IS NOT NULL
      GROUP BY image_id
      ORDER BY view_count DESC
      LIMIT 10
    `).all();

    // Most downloaded images
    const mostDownloadedImages = db.prepare(`
      SELECT
        image_id,
        photographer,
        COUNT(*) as download_count
      FROM image_downloads
      WHERE image_id IS NOT NULL
      GROUP BY image_id
      ORDER BY download_count DESC
      LIMIT 10
    `).all();

    // Platform breakdown
    const platformStats = db.prepare(`
      SELECT
        platform,
        COUNT(*) as count
      FROM (
        SELECT platform FROM image_views
        UNION ALL
        SELECT platform FROM image_downloads
      )
      WHERE platform IS NOT NULL
      GROUP BY platform
      ORDER BY count DESC
    `).all();

    // Daily activity (last 7 days)
    const dailyActivity = db.prepare(`
      SELECT
        DATE(created_at) as date,
        SUM(CASE WHEN type = 'view' THEN 1 ELSE 0 END) as views,
        SUM(CASE WHEN type = 'download' THEN 1 ELSE 0 END) as downloads
      FROM (
        SELECT created_at, 'view' as type FROM image_views
        UNION ALL
        SELECT created_at, 'download' as type FROM image_downloads
      )
      WHERE created_at >= datetime('now', '-7 days')
      GROUP BY DATE(created_at)
      ORDER BY date DESC
    `).all();

    return {
      success: true,
      period: `Last ${days} days`,
      totals: {
        totalViews,
        totalDownloads,
        recentViews,
        recentDownloads
      },
      topImages: {
        mostViewed: mostViewedImages,
        mostDownloaded: mostDownloadedImages
      },
      platforms: platformStats,
      dailyActivity
    };
  } catch (error) {
    console.error('Error getting statistics:', error);
    throw error;
  }
}

/**
 * Get simple stats summary
 */
export function getStatsSummary() {
  try {
    const db = getDatabase();

    const totalViews = db.prepare('SELECT COUNT(*) as count FROM image_views').get().count;
    const totalDownloads = db.prepare('SELECT COUNT(*) as count FROM image_downloads').get().count;

    return {
      success: true,
      totalViews,
      totalDownloads
    };
  } catch (error) {
    console.error('Error getting stats summary:', error);
    throw error;
  }
}
