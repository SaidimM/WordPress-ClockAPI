import { fetchUnsplashImages, triggerUnsplashDownload } from '../services/unsplashService.js';
import { getRandomImagesFromPool, getPoolStats, refreshImagePool } from '../services/imagePoolService.js';
import { asyncHandler } from '../middleware/errorHandler.js';

/**
 * Get images (from pool with fallback to Unsplash)
 * GET /api/v1/images
 */
export const getImages = asyncHandler(async (req, res) => {
  const count = Math.min(parseInt(req.query.count) || 10, 30); // Max 30 images

  // Try to get images from pool first
  const poolResult = getRandomImagesFromPool(count);

  if (poolResult.success && poolResult.data.length > 0) {
    console.log(`✓ Serving ${poolResult.data.length} images from pool (${poolResult.poolSize} total in pool)`);
    return res.json({
      success: true,
      count: poolResult.data.length,
      source: 'pool',
      poolSize: poolResult.poolSize,
      images: poolResult.data
    });
  }

  // Fallback to direct Unsplash fetch if pool is empty
  console.log('Pool is empty, fetching directly from Unsplash...');
  const query = req.query.query || 'nature,landscape';
  const result = await fetchUnsplashImages(count, query);

  res.json({
    success: true,
    count: result.data.length,
    source: 'unsplash',
    images: result.data
  });
});

/**
 * Get cache statistics
 * GET /api/v1/images/cache-stats
 */
export const getCacheStatistics = asyncHandler(async (req, res) => {
  const stats = getCacheStats();

  res.json({
    success: true,
    cache: stats
  });
});

/**
 * Clear cache
 * POST /api/v1/images/clear-cache
 */
export const clearImageCache = asyncHandler(async (req, res) => {
  clearCache();

  res.json({
    success: true,
    message: 'Cache cleared successfully'
  });
});

/**
 * Trigger Unsplash download tracking
 * POST /api/v1/images/unsplash-download
 */
export const triggerUnsplashDownloadTracking = asyncHandler(async (req, res) => {
  const { downloadLocation, unsplashId } = req.body;

  // If we have downloadLocation, use it directly
  if (downloadLocation) {
    const result = await triggerUnsplashDownload(downloadLocation);
    return res.json({
      success: result.success,
      message: result.success ? 'Download tracked with Unsplash' : result.message
    });
  }

  // If we have unsplashId, construct the download endpoint
  if (unsplashId) {
    // Unsplash API endpoint format: /photos/:id/download
    const constructedDownloadLocation = `https://api.unsplash.com/photos/${unsplashId}/download`;
    const result = await triggerUnsplashDownload(constructedDownloadLocation);
    return res.json({
      success: result.success,
      message: result.success ? 'Download tracked with Unsplash (via photo ID)' : result.message
    });
  }

  return res.status(400).json({
    success: false,
    message: 'Either downloadLocation or unsplashId is required'
  });
});

/**
 * Download image proxy - streams image directly to client
 * GET /api/v1/images/download
 */
export const downloadImage = asyncHandler(async (req, res) => {
  const { url, filename } = req.query;

  if (!url) {
    return res.status(400).json({
      success: false,
      message: 'url parameter is required'
    });
  }

  try {
    // Fetch the image from the source URL
    const response = await fetch(url);

    if (!response.ok) {
      throw new Error(`Failed to fetch image: ${response.statusText}`);
    }

    // Get content type from the source
    const contentType = response.headers.get('content-type') || 'image/jpeg';

    // Set headers to trigger download
    res.setHeader('Content-Type', contentType);
    res.setHeader('Content-Disposition', `attachment; filename="${filename || 'image.jpg'}"`);

    // Handle content length if available
    const contentLength = response.headers.get('content-length');
    if (contentLength) {
      res.setHeader('Content-Length', contentLength);
    }

    // Stream the image data directly to the client
    // Convert web stream to Node.js stream
    const reader = response.body.getReader();

    const pump = async () => {
      while (true) {
        const { done, value } = await reader.read();

        if (done) {
          res.end();
          break;
        }

        // Write chunk to response
        if (!res.write(value)) {
          // If write buffer is full, wait for drain event
          await new Promise(resolve => res.once('drain', resolve));
        }
      }
    };

    await pump();

  } catch (error) {
    console.error('Download proxy error:', error);

    // If headers haven't been sent yet, send error response
    if (!res.headersSent) {
      res.status(500).json({
        success: false,
        message: 'Failed to download image'
      });
    } else {
      // If already streaming, just end the response
      res.end();
    }
  }
});

/**
 * Get image pool statistics
 * GET /api/v1/images/pool-stats
 */
export const getImagePoolStats = asyncHandler(async (req, res) => {
  const stats = getPoolStats();

  res.json({
    success: true,
    pool: stats
  });
});

/**
 * Manually refresh image pool
 * POST /api/v1/images/refresh-pool
 */
export const refreshPool = asyncHandler(async (req, res) => {
  console.log('Manual pool refresh triggered');

  const query = req.query.query || req.body.query || 'nature,landscape';
  const result = await refreshImagePool(query);

  res.json({
    success: result.success,
    message: result.success ? 'Image pool refreshed successfully' : 'Pool refresh failed',
    totalImages: result.totalImages,
    newImages: result.newImages,
    lastFetchTime: result.lastFetchTime
  });
});
