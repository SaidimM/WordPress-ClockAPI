import { trackImageView, trackImageDownload, getStatistics, getStatsSummary } from '../services/trackingService.js';
import { asyncHandler } from '../middleware/errorHandler.js';

/**
 * Extract client information from request
 */
function getClientInfo(req) {
  return {
    userAgent: req.headers['user-agent'],
    ipAddress: req.ip || req.headers['x-forwarded-for'] || req.socket.remoteAddress,
    platform: req.headers['x-platform'] || detectPlatform(req.headers['user-agent'])
  };
}

/**
 * Detect platform from user agent
 */
function detectPlatform(userAgent) {
  if (!userAgent) return 'unknown';

  const ua = userAgent.toLowerCase();

  if (ua.includes('android')) return 'android';
  if (ua.includes('iphone') || ua.includes('ipad') || ua.includes('ipod')) return 'ios';
  if (ua.includes('mac os x')) return 'macos';
  if (ua.includes('windows')) return 'windows';
  if (ua.includes('linux')) return 'linux';
  if (ua.includes('electron')) return 'electron';

  return 'web';
}

/**
 * Track image view
 * POST /api/v1/track/view
 */
export const trackView = asyncHandler(async (req, res) => {
  const { imageId, photographer, photographerUrl } = req.body;
  const clientInfo = getClientInfo(req);

  const result = trackImageView({
    imageId,
    photographer,
    photographerUrl,
    ...clientInfo
  });

  res.json({
    success: true,
    message: 'View tracked successfully',
    id: result.id
  });
});

/**
 * Track image download
 * POST /api/v1/track/download
 */
export const trackDownload = asyncHandler(async (req, res) => {
  const { imageId, photographer, photographerUrl } = req.body;
  const clientInfo = getClientInfo(req);

  const result = trackImageDownload({
    imageId,
    photographer,
    photographerUrl,
    ...clientInfo
  });

  res.json({
    success: true,
    message: 'Download tracked successfully',
    id: result.id
  });
});

/**
 * Get statistics
 * GET /api/v1/statistics
 */
export const getStats = asyncHandler(async (req, res) => {
  const days = parseInt(req.query.days) || 30;
  const detailed = req.query.detailed === 'true';

  const stats = detailed ? getStatistics({ days }) : getStatsSummary();

  res.json(stats);
});
