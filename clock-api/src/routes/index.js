import express from 'express';
import { getImages, triggerUnsplashDownloadTracking, downloadImage, getImagePoolStats, refreshPool } from '../controllers/imagesController.js';
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
router.get('/images/pool-stats', adminLimiter, authenticateApiKey, getImagePoolStats);
router.post('/images/refresh-pool', adminLimiter, authenticateApiKey, refreshPool);
router.post('/images/unsplash-download', trackingLimiter, triggerUnsplashDownloadTracking);

// Tracking routes
router.post('/track/view', trackingLimiter, trackView);
router.post('/track/download', trackingLimiter, trackDownload);

// Statistics route (can be protected with authenticateApiKey if desired)
router.get('/statistics', statsLimiter, optionalApiKey, getStats);

export default router;
