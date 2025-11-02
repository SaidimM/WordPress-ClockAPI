import NodeCache from 'node-cache';
import { config } from '../utils/config.js';

// Initialize cache with TTL
const cache = new NodeCache({ stdTTL: config.cache.ttl });

/**
 * Fetch random images from Unsplash API
 */
export async function fetchUnsplashImages(count = 10, query = 'nature,landscape') {
  try {
    const cacheKey = `unsplash_${count}_${query}`;

    // Check cache first
    const cachedData = cache.get(cacheKey);
    if (cachedData) {
      console.log('✓ Returning cached Unsplash images');
      return {
        success: true,
        data: cachedData,
        cached: true
      };
    }

    // Fetch from Unsplash API
    const url = `${config.unsplash.apiUrl}/photos/random?count=${count}&query=${encodeURIComponent(query)}&orientation=landscape`;

    const response = await fetch(url, {
      headers: {
        'Authorization': `Client-ID ${config.unsplash.accessKey}`,
        'Accept-Version': 'v1'
      },
      // Increase timeout to 5 minutes for slow connections
      signal: AbortSignal.timeout(300000)
    });

    if (!response.ok) {
      const errorData = await response.json().catch(() => ({}));
      throw new Error(
        `Unsplash API error: ${response.status} - ${errorData.errors?.join(', ') || response.statusText}`
      );
    }

    const data = await response.json();

    // Transform data to our format
    const images = data.map(photo => ({
      id: photo.id,
      url: `${photo.urls.full}&w=3840&q=100`, // 4K quality
      downloadUrl: photo.urls.raw,
      thumbnail: photo.urls.thumb,
      photographer: photo.user.name,
      photographerUrl: `${photo.user.links.html}?utm_source=saidim_clock&utm_medium=referral`,
      unsplashUrl: `https://unsplash.com?utm_source=saidim_clock&utm_medium=referral`,
      downloadLocation: photo.links.download_location, // For Unsplash download tracking
      description: photo.description || photo.alt_description,
      color: photo.color,
      width: photo.width,
      height: photo.height
    }));

    // Cache the results
    cache.set(cacheKey, images);

    console.log(`✓ Fetched ${images.length} images from Unsplash API`);

    return {
      success: true,
      data: images,
      cached: false
    };
  } catch (error) {
    console.error('Unsplash API error:', error.message);
    throw error;
  }
}

/**
 * Get cache statistics
 */
export function getCacheStats() {
  const stats = cache.getStats();
  return {
    keys: cache.keys().length,
    hits: stats.hits,
    misses: stats.misses,
    hitRate: stats.hits / (stats.hits + stats.misses) || 0
  };
}

/**
 * Clear cache manually
 */
export function clearCache() {
  cache.flushAll();
  console.log('✓ Cache cleared');
}

/**
 * Trigger Unsplash download tracking endpoint
 * Required by Unsplash API Guidelines for production access
 */
export async function triggerUnsplashDownload(downloadLocation) {
  try {
    if (!downloadLocation) {
      console.warn('⚠ No download location provided for Unsplash tracking');
      return { success: false, message: 'No download location' };
    }

    const response = await fetch(downloadLocation, {
      headers: {
        'Authorization': `Client-ID ${config.unsplash.accessKey}`,
        'Accept-Version': 'v1'
      },
      // Increase timeout to 2 minutes for slow connections
      signal: AbortSignal.timeout(120000)
    });

    if (!response.ok) {
      throw new Error(`Unsplash download tracking failed: ${response.status}`);
    }

    console.log('✓ Unsplash download tracked successfully');
    return { success: true };
  } catch (error) {
    console.error('Unsplash download tracking error:', error.message);
    // Don't throw - this shouldn't block the user's download
    return { success: false, message: error.message };
  }
}
