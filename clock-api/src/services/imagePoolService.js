import { fetchUnsplashImages } from './unsplashService.js';

// In-memory image pool
let imagePool = [];
let lastFetchTime = null;
let isFetching = false;

const POOL_CONFIG = {
  targetSize: 100,        // Target pool size
  fetchBatchSize: 30,     // Fetch 30 images per batch (Unsplash limit)
  minPoolSize: 20,        // Minimum pool size before refetch
  maxPoolSize: 150        // Maximum pool size
};

/**
 * Fetch and populate the image pool
 */
export async function refreshImagePool(query = 'nature,landscape') {
  if (isFetching) {
    console.log('Image pool refresh already in progress, skipping...');
    return { success: false, message: 'Refresh in progress' };
  }

  isFetching = true;
  console.log('🔄 Refreshing image pool...');

  try {
    const newImages = [];
    const batchCount = Math.ceil(POOL_CONFIG.targetSize / POOL_CONFIG.fetchBatchSize);

    // Fetch multiple batches to reach target size
    for (let i = 0; i < batchCount; i++) {
      console.log(`Fetching batch ${i + 1}/${batchCount}...`);

      const result = await fetchUnsplashImages(POOL_CONFIG.fetchBatchSize, query);

      if (result.success && result.data) {
        newImages.push(...result.data);
        console.log(`✓ Batch ${i + 1}: ${result.data.length} images fetched`);
      } else {
        console.log(`✗ Batch ${i + 1} failed`);
      }

      // Small delay between batches to avoid rate limiting
      if (i < batchCount - 1) {
        await new Promise(resolve => setTimeout(resolve, 1000));
      }
    }

    // Update pool (keep existing + add new, remove duplicates)
    const existingIds = new Set(imagePool.map(img => img.id));
    const uniqueNewImages = newImages.filter(img => !existingIds.has(img.id));

    imagePool = [...imagePool, ...uniqueNewImages];

    // Trim pool if it exceeds max size (keep most recent)
    if (imagePool.length > POOL_CONFIG.maxPoolSize) {
      imagePool = imagePool.slice(-POOL_CONFIG.maxPoolSize);
    }

    lastFetchTime = new Date();

    console.log(`✓ Image pool refreshed: ${imagePool.length} total images (${uniqueNewImages.length} new)`);

    return {
      success: true,
      totalImages: imagePool.length,
      newImages: uniqueNewImages.length,
      lastFetchTime
    };

  } catch (error) {
    console.error('Image pool refresh failed:', error);
    return {
      success: false,
      error: error.message
    };
  } finally {
    isFetching = false;
  }
}

/**
 * Get random images from the pool
 */
export function getRandomImagesFromPool(count = 10) {
  if (imagePool.length === 0) {
    return {
      success: false,
      message: 'Image pool is empty',
      data: []
    };
  }

  // If pool is smaller than requested count, return all
  if (imagePool.length <= count) {
    return {
      success: true,
      data: [...imagePool],
      poolSize: imagePool.length
    };
  }

  // Get random selection without duplicates
  const shuffled = [...imagePool].sort(() => Math.random() - 0.5);
  const selected = shuffled.slice(0, count);

  return {
    success: true,
    data: selected,
    poolSize: imagePool.length
  };
}

/**
 * Get pool statistics
 */
export function getPoolStats() {
  return {
    poolSize: imagePool.length,
    lastFetchTime,
    isFetching,
    config: POOL_CONFIG
  };
}

/**
 * Check if pool needs refresh
 */
export function needsRefresh() {
  // Refresh if pool is below minimum size
  if (imagePool.length < POOL_CONFIG.minPoolSize) {
    return true;
  }

  // Refresh if last fetch was more than 12 hours ago
  if (lastFetchTime) {
    const hoursSinceLastFetch = (Date.now() - lastFetchTime.getTime()) / (1000 * 60 * 60);
    return hoursSinceLastFetch > 12;
  }

  // Refresh if never fetched
  return lastFetchTime === null;
}

/**
 * Initialize pool on startup
 */
export async function initializePool() {
  console.log('Initializing image pool...');

  if (imagePool.length === 0) {
    console.log('Pool is empty, fetching initial images...');
    await refreshImagePool();
  } else {
    console.log(`Pool already has ${imagePool.length} images`);
  }
}
