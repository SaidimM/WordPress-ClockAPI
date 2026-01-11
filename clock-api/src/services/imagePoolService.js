import { fetchUnsplashImages } from './unsplashService.js';

// In-memory image pool
let imagePool = [];
let lastFetchTime = null;
let isFetching = false;

const POOL_CONFIG = {
  targetSize: 300,        // Increased from 200 to 300
  fetchBatchSize: 30,     // Fetch 30 images per batch (Unsplash limit)
  minPoolSize: 100,       // Increased from 50 to 100
  maxPoolSize: 400        // Increased from 250 to 400
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

      // BYPASS CACHE to get fresh images on every refresh
      const result = await fetchUnsplashImages(POOL_CONFIG.fetchBatchSize, query, true);

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

    // Replace pool with fresh images on each refresh
    // This ensures users see new images instead of the same ones forever
    const newIds = new Set(newImages.map(img => img.id));
    const uniqueNewImages = newImages.filter((img, index, self) =>
      self.findIndex(i => i.id === img.id) === index
    );

    // Count how many are actually new vs what we had before
    const previousIds = new Set(imagePool.map(img => img.id));
    const trulyNewCount = uniqueNewImages.filter(img => !previousIds.has(img.id)).length;

    // Replace the entire pool with fresh images
    imagePool = uniqueNewImages;

    // Trim pool if it exceeds max size
    if (imagePool.length > POOL_CONFIG.maxPoolSize) {
      imagePool = imagePool.slice(0, POOL_CONFIG.maxPoolSize);
    }

    lastFetchTime = new Date();

    console.log(`✓ Image pool refreshed: ${imagePool.length} total images (${trulyNewCount} new, pool replaced)`);

    return {
      success: true,
      totalImages: imagePool.length,
      newImages: trulyNewCount,
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
 * Fisher-Yates shuffle algorithm for proper randomization
 */
function fisherYatesShuffle(array) {
  const shuffled = [...array];
  for (let i = shuffled.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1));
    [shuffled[i], shuffled[j]] = [shuffled[j], shuffled[i]];
  }
  return shuffled;
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

  // If pool is smaller than requested count, return all (shuffled)
  if (imagePool.length <= count) {
    return {
      success: true,
      data: fisherYatesShuffle(imagePool),
      poolSize: imagePool.length
    };
  }

  // Get random selection without duplicates using proper shuffle
  const shuffled = fisherYatesShuffle(imagePool);
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

  // Refresh if last fetch was more than 1 hour ago
  if (lastFetchTime) {
    const hoursSinceLastFetch = (Date.now() - lastFetchTime.getTime()) / (1000 * 60 * 60);
    return hoursSinceLastFetch > 1;
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
