import { createWriteStream, unlinkSync, statSync, readdirSync } from 'fs';
import { join } from 'path';
import { pipeline } from 'stream/promises';
import { getDatabase } from '../database/db.js';
import { fetchUnsplashImages, triggerUnsplashDownload } from './unsplashService.js';
import crypto from 'crypto';

// Configuration
const CACHE_CONFIG = {
  maxStorageBytes: 1 * 1024 * 1024 * 1024, // 1 GB
  maxImages: 200,
  minImages: 30,
  cleanupThresholdRatio: 0.9, // Cleanup at 90%
  imageQuality: 'raw', // Download raw/original quality
  imagesPerFetch: 10
};

const IMAGES_DIR = '/app/data/images';

/**
 * Download and cache images from Unsplash
 * @param {string} query - Search query/topics (default: 'nature,landscape')
 */
export async function downloadAndCacheImages(query = 'nature,landscape') {
  console.log('Starting image download and caching process...');
  console.log(`Search topics: ${query}`);

  try {
    const db = getDatabase();

    // Fetch fresh images from Unsplash
    const result = await fetchUnsplashImages(CACHE_CONFIG.imagesPerFetch, query);

    if (!result.success || !result.data || result.data.length === 0) {
      console.log('No images fetched from Unsplash');
      return { success: false, message: 'No images available' };
    }

    console.log(`Fetched ${result.data.length} images from Unsplash`);

    let downloadedCount = 0;
    let failedCount = 0;

    // Download each image
    for (const image of result.data) {
      try {
        // Check if image already exists
        const existing = db.prepare('SELECT id FROM cached_images WHERE unsplash_id = ?').get(image.id);
        if (existing) {
          console.log(`Image ${image.id} already cached, skipping...`);
          continue;
        }

        // Generate unique filename
        const imageId = crypto.randomBytes(16).toString('hex');
        const filename = `${imageId}.jpg`;
        const filePath = join(IMAGES_DIR, filename);

        // Download the raw/original quality image
        const downloadUrl = image.downloadUrl || image.url;
        console.log(`Downloading image: ${image.id} (${image.width}x${image.height})`);

        const downloadStartTime = Date.now();
        let downloadedBytes = 0;

        const response = await fetch(downloadUrl, {
          // Increase timeout to 10 minutes for very slow downloads
          signal: AbortSignal.timeout(600000)
        });
        if (!response.ok) {
          throw new Error(`Failed to download: ${response.statusText}`);
        }

        // Get expected file size
        const contentLength = parseInt(response.headers.get('content-length') || '0');
        const expectedMB = (contentLength / 1024 / 1024).toFixed(2);
        console.log(`Expected size: ${expectedMB} MB`);

        // Stream download to file with progress tracking
        const fileStream = createWriteStream(filePath);
        const reader = response.body.getReader();

        let lastProgressTime = Date.now();
        let lastProgressBytes = 0;

        while (true) {
          const { done, value } = await reader.read();
          if (done) break;

          fileStream.write(value);
          downloadedBytes += value.length;

          // Log progress every 5 seconds
          const now = Date.now();
          if (now - lastProgressTime >= 5000) {
            const elapsedSeconds = (now - downloadStartTime) / 1000;
            const speedKBps = (downloadedBytes / 1024) / elapsedSeconds;
            const progressPercent = contentLength > 0 ? ((downloadedBytes / contentLength) * 100).toFixed(1) : '?';
            const downloadedMB = (downloadedBytes / 1024 / 1024).toFixed(2);

            console.log(`  Progress: ${progressPercent}% (${downloadedMB}/${expectedMB} MB) - Speed: ${speedKBps.toFixed(1)} KB/s`);
            lastProgressTime = now;
          }
        }

        fileStream.end();
        await new Promise((resolve, reject) => {
          fileStream.on('finish', resolve);
          fileStream.on('error', reject);
        });

        // Get final file size and calculate speed
        const stats = statSync(filePath);
        const fileSize = stats.size;
        const totalTimeSeconds = (Date.now() - downloadStartTime) / 1000;
        const avgSpeedKBps = (fileSize / 1024) / totalTimeSeconds;

        console.log(`Downloaded: ${filename} (${(fileSize / 1024 / 1024).toFixed(2)} MB) in ${totalTimeSeconds.toFixed(1)}s - Avg speed: ${avgSpeedKBps.toFixed(1)} KB/s`);

        // Trigger Unsplash download tracking
        if (image.downloadLocation) {
          try {
            await triggerUnsplashDownload(image.downloadLocation);
          } catch (error) {
            console.log('Unsplash tracking failed (non-blocking):', error.message);
          }
        }

        // Save to database
        db.prepare(`
          INSERT INTO cached_images (
            id, filename, file_size, file_path, quality,
            photographer, photographer_url, unsplash_url, unsplash_id,
            width, height, color, description, download_url, download_location
          ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        `).run(
          imageId,
          filename,
          fileSize,
          filePath,
          CACHE_CONFIG.imageQuality,
          image.photographer,
          image.photographerUrl,
          image.unsplashUrl,
          image.id,
          image.width,
          image.height,
          image.color,
          image.description,
          downloadUrl,
          image.downloadLocation
        );

        downloadedCount++;

      } catch (error) {
        console.error(`Failed to download image ${image.id}:`, error.message);
        failedCount++;
      }
    }

    console.log(`Download complete: ${downloadedCount} succeeded, ${failedCount} failed`);

    // Run cleanup after downloading
    await cleanupOldImages();

    return {
      success: true,
      downloaded: downloadedCount,
      failed: failedCount
    };

  } catch (error) {
    console.error('Image caching failed:', error);
    return {
      success: false,
      error: error.message
    };
  }
}

/**
 * Cleanup old images to maintain storage limits
 */
export async function cleanupOldImages() {
  console.log('Running image cleanup...');

  try {
    const db = getDatabase();

    // Get current storage usage
    const stats = getCacheStats();
    const totalSize = stats.totalSize;
    const imageCount = stats.imageCount;

    console.log(`Current cache: ${imageCount} images, ${(totalSize / 1024 / 1024).toFixed(2)} MB`);

    // Check if cleanup is needed
    const needsCleanup = (
      totalSize > (CACHE_CONFIG.maxStorageBytes * CACHE_CONFIG.cleanupThresholdRatio) ||
      imageCount > CACHE_CONFIG.maxImages
    );

    if (!needsCleanup) {
      console.log('No cleanup needed');
      return { success: true, deleted: 0 };
    }

    // Calculate how many images to keep
    const targetCount = Math.floor(CACHE_CONFIG.maxImages * 0.75); // Keep 75% of max
    const toDelete = imageCount - targetCount;

    if (toDelete <= 0) {
      console.log('No images to delete');
      return { success: true, deleted: 0 };
    }

    console.log(`Deleting ${toDelete} oldest images...`);

    // Get oldest images (but keep minimum)
    const imagesToDelete = db.prepare(`
      SELECT id, filename, file_path, file_size
      FROM cached_images
      ORDER BY cached_at ASC
      LIMIT ?
    `).all(toDelete);

    let deletedCount = 0;
    let freedSpace = 0;

    for (const image of imagesToDelete) {
      try {
        // Delete file
        unlinkSync(image.file_path);

        // Delete from database
        db.prepare('DELETE FROM cached_images WHERE id = ?').run(image.id);

        deletedCount++;
        freedSpace += image.file_size;

      } catch (error) {
        console.error(`Failed to delete image ${image.id}:`, error.message);
      }
    }

    console.log(`Cleanup complete: deleted ${deletedCount} images, freed ${(freedSpace / 1024 / 1024).toFixed(2)} MB`);

    return {
      success: true,
      deleted: deletedCount,
      freedSpace
    };

  } catch (error) {
    console.error('Cleanup failed:', error);
    return {
      success: false,
      error: error.message
    };
  }
}

/**
 * Get cache statistics
 */
export function getCacheStats() {
  try {
    const db = getDatabase();

    const stats = db.prepare(`
      SELECT
        COUNT(*) as imageCount,
        SUM(file_size) as totalSize,
        AVG(file_size) as avgSize,
        MIN(cached_at) as oldestImage,
        MAX(cached_at) as newestImage
      FROM cached_images
    `).get();

    return {
      imageCount: stats.imageCount || 0,
      totalSize: stats.totalSize || 0,
      avgSize: stats.avgSize || 0,
      oldestImage: stats.oldestImage,
      newestImage: stats.newestImage,
      maxStorage: CACHE_CONFIG.maxStorageBytes,
      usagePercent: ((stats.totalSize || 0) / CACHE_CONFIG.maxStorageBytes * 100).toFixed(2)
    };

  } catch (error) {
    console.error('Failed to get cache stats:', error);
    return {
      imageCount: 0,
      totalSize: 0,
      avgSize: 0,
      usagePercent: 0
    };
  }
}

/**
 * Get all cached images for serving
 */
export function getCachedImages(limit = 30) {
  try {
    const db = getDatabase();

    const images = db.prepare(`
      SELECT
        ci.id, ci.filename, ci.photographer, ci.photographer_url, ci.unsplash_url,
        ci.unsplash_id, ci.width, ci.height, ci.color, ci.description, ci.cached_at, ci.download_location,
        COALESCE(
          (SELECT COUNT(*) FROM image_downloads WHERE image_id = ci.id),
          0
        ) as download_count
      FROM cached_images ci
      ORDER BY ci.cached_at DESC
      LIMIT ?
    `).all(limit);

    return images.map(img => ({
      id: img.id,
      unsplashId: img.unsplash_id,
      url: `/cache/images/${img.filename}`,
      downloadUrl: `/cache/images/${img.filename}`,
      photographer: img.photographer,
      photographerUrl: img.photographer_url,
      unsplashUrl: img.unsplash_url,
      downloadLocation: img.download_location,
      width: img.width,
      height: img.height,
      color: img.color,
      description: img.description,
      downloadCount: img.download_count,
      cached: true
    }));

  } catch (error) {
    console.error('Failed to get cached images:', error);
    return [];
  }
}

/**
 * Delete a specific cached image
 * @param {string} imageId - The image ID to delete
 */
export function deleteCachedImage(imageId) {
  try {
    const db = getDatabase();

    // Get image details first
    const image = db.prepare('SELECT id, filename, file_path, file_size FROM cached_images WHERE id = ?').get(imageId);

    if (!image) {
      return {
        success: false,
        message: 'Image not found'
      };
    }

    // Delete the file
    try {
      unlinkSync(image.file_path);
      console.log(`Deleted file: ${image.filename}`);
    } catch (error) {
      console.error(`Failed to delete file ${image.filename}:`, error.message);
      // Continue with database deletion even if file deletion fails
    }

    // Delete from database (this will cascade to related records)
    db.prepare('DELETE FROM cached_images WHERE id = ?').run(imageId);

    console.log(`Successfully deleted image: ${imageId}`);

    return {
      success: true,
      message: 'Image deleted successfully',
      freedSpace: image.file_size
    };

  } catch (error) {
    console.error('Failed to delete image:', error);
    return {
      success: false,
      message: error.message
    };
  }
}
