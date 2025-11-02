import { fetchUnsplashImages, getCacheStats, clearCache, triggerUnsplashDownload } from '../services/unsplashService.js';
import { getCachedImages, getCacheStats as getImageCacheStats, downloadAndCacheImages, cleanupOldImages, deleteCachedImage } from '../services/imageCacheService.js';
import { asyncHandler } from '../middleware/errorHandler.js';

/**
 * Get images (prioritize cached local images)
 * GET /api/v1/images
 */
export const getImages = asyncHandler(async (req, res) => {
  const count = Math.min(parseInt(req.query.count) || 10, 30); // Max 30 images

  // Try to get cached images first
  const cachedImages = getCachedImages(count);

  if (cachedImages && cachedImages.length > 0) {
    console.log(`✓ Serving ${cachedImages.length} cached images from local storage`);
    return res.json({
      success: true,
      count: cachedImages.length,
      cached: true,
      images: cachedImages
    });
  }

  // Fallback to Unsplash if no cached images
  console.log('No cached images available, fetching from Unsplash...');
  const query = req.query.query || 'nature,landscape';
  const result = await fetchUnsplashImages(count, query);

  res.json({
    success: true,
    count: result.data.length,
    cached: false,
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
 * Refresh image cache - download new images
 * POST /api/v1/images/refresh-cache
 * Query params: ?query=ocean,sunset (optional)
 */
export const refreshImageCache = asyncHandler(async (req, res) => {
  console.log('Manual cache refresh triggered');

  // Get search query from query params or body
  const query = req.query.query || req.body.query || 'nature,landscape';

  const result = await downloadAndCacheImages(query);

  res.json({
    success: result.success,
    message: result.success ? 'Image cache refreshed successfully' : 'Cache refresh failed',
    query: query,
    downloaded: result.downloaded,
    failed: result.failed
  });
});

/**
 * Get image cache statistics
 * GET /api/v1/images/cache-info
 */
export const getImageCacheInfo = asyncHandler(async (req, res) => {
  const stats = getImageCacheStats();

  res.json({
    success: true,
    cache: stats
  });
});

/**
 * Delete a cached image
 * DELETE /api/v1/images/:imageId
 */
export const deleteImage = asyncHandler(async (req, res) => {
  console.log('DELETE /images/:imageId called');
  console.log('Request method:', req.method);
  console.log('Request params:', req.params);
  console.log('Request URL:', req.url);
  console.log('Request path:', req.path);

  const { imageId } = req.params;

  console.log('Extracted imageId:', imageId);

  if (!imageId) {
    console.log('ERROR: Image ID is missing');
    return res.status(400).json({
      success: false,
      message: 'Image ID is required'
    });
  }

  console.log('Attempting to delete image:', imageId);
  const result = deleteCachedImage(imageId);
  console.log('Delete result:', result);

  if (!result.success) {
    console.log('Delete failed:', result.message);
    return res.status(404).json({
      success: false,
      message: result.message
    });
  }

  console.log('Delete successful');
  res.json({
    success: true,
    message: result.message,
    freedSpace: result.freedSpace
  });
});
