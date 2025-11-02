import express from 'express';
import { getImages, getCacheStatistics, clearImageCache, triggerUnsplashDownloadTracking, downloadImage, refreshImageCache, getImageCacheInfo, deleteImage } from '../controllers/imagesController.js';
import { trackView, trackDownload, getStats } from '../controllers/trackingController.js';
import { optionalApiKey, authenticateApiKey } from '../middleware/auth.js';
import { generalLimiter, trackingLimiter, statsLimiter, adminLimiter } from '../middleware/rateLimiter.js';

const router = express.Router();

// Debug middleware - log all requests
router.use((req, res, next) => {
  console.log(`[${new Date().toISOString()}] ${req.method} ${req.path} - params:`, req.params);
  next();
});

// Health check endpoint
router.get('/health', (req, res) => {
  res.json({
    success: true,
    status: 'healthy',
    timestamp: new Date().toISOString()
  });
});

// Images routes
router.get('/images', generalLimiter, optionalApiKey, getImages);
router.get('/images/download', generalLimiter, downloadImage);
router.get('/images/cache-stats', adminLimiter, authenticateApiKey, getCacheStatistics);
router.get('/images/cache-info', adminLimiter, authenticateApiKey, getImageCacheInfo);
router.post('/images/clear-cache', adminLimiter, authenticateApiKey, clearImageCache);
router.post('/images/refresh-cache', adminLimiter, authenticateApiKey, refreshImageCache);
router.post('/images/unsplash-download', trackingLimiter, triggerUnsplashDownloadTracking);

// Dynamic routes (must come after specific routes)
router.delete('/images/:imageId', adminLimiter, authenticateApiKey, (req, res, next) => {
  console.log('DELETE route matched for /images/:imageId');
  console.log('Route params:', req.params);
  next();
}, deleteImage);

// Tracking routes
router.post('/track/view', trackingLimiter, trackView);
router.post('/track/download', trackingLimiter, trackDownload);

// Statistics route (can be protected with authenticateApiKey if desired)
router.get('/statistics', statsLimiter, optionalApiKey, getStats);

export default router;
