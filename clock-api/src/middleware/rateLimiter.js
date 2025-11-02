import rateLimit from 'express-rate-limit';
import { config } from '../utils/config.js';

/**
 * Custom key generator that properly extracts client IP
 * Handles proxies and load balancers correctly
 */
const getClientIp = (req) => {
  // Check X-Forwarded-For header (for proxies)
  const forwardedFor = req.headers['x-forwarded-for'];
  if (forwardedFor) {
    // X-Forwarded-For can contain multiple IPs, take the first one
    const ips = forwardedFor.split(',').map(ip => ip.trim());
    return ips[0];
  }

  // Check X-Real-IP header (nginx)
  if (req.headers['x-real-ip']) {
    return req.headers['x-real-ip'];
  }

  // Fallback to connection remote address
  return req.ip || req.connection?.remoteAddress || 'unknown';
};

/**
 * Custom handler for rate limit violations
 * Logs violations for monitoring
 */
const rateLimitHandler = (req, res, options) => {
  const clientIp = getClientIp(req);
  const endpoint = req.path;
  const timestamp = new Date().toISOString();

  // Log rate limit violation
  console.warn(`[RATE LIMIT] ${timestamp} - IP: ${clientIp} - Endpoint: ${endpoint} - Limit: ${options.max} requests per ${options.windowMs}ms`);

  // Send response
  res.status(429).json({
    success: false,
    error: 'Too many requests',
    message: options.message?.message || 'Rate limit exceeded, please try again later',
    retryAfter: Math.ceil(options.windowMs / 1000),
    limit: options.max,
    windowSeconds: Math.ceil(options.windowMs / 1000)
  });
};

/**
 * Rate limiter for general API endpoints
 * 100 requests per 15 minutes by default
 */
export const generalLimiter = rateLimit({
  windowMs: config.rateLimit.windowMs,
  max: config.rateLimit.maxRequests,
  message: {
    success: false,
    error: 'Too many requests',
    message: `Too many requests from this IP, please try again later`
  },
  standardHeaders: true, // Return rate limit info in `RateLimit-*` headers
  legacyHeaders: false,  // Disable `X-RateLimit-*` headers
  keyGenerator: getClientIp,
  handler: rateLimitHandler,
  // Skip rate limiting for authenticated requests with valid API keys
  skip: (req) => req.authenticated === true,
  // Store in memory (for production, consider Redis)
  skipFailedRequests: false,
  skipSuccessfulRequests: false
});

/**
 * Stricter rate limiter for tracking endpoints
 * 30 requests per minute
 */
export const trackingLimiter = rateLimit({
  windowMs: 60 * 1000, // 1 minute
  max: 30, // 30 requests per minute
  message: {
    success: false,
    error: 'Too many tracking requests',
    message: 'Rate limit exceeded for tracking endpoints'
  },
  standardHeaders: true,
  legacyHeaders: false,
  keyGenerator: getClientIp,
  handler: rateLimitHandler
});

/**
 * Very strict rate limiter for stats endpoint
 * 10 requests per minute
 */
export const statsLimiter = rateLimit({
  windowMs: 60 * 1000, // 1 minute
  max: 10, // 10 requests per minute
  message: {
    success: false,
    error: 'Too many statistics requests',
    message: 'Rate limit exceeded for statistics endpoint'
  },
  standardHeaders: true,
  legacyHeaders: false,
  keyGenerator: getClientIp,
  handler: rateLimitHandler
});

/**
 * Extremely strict rate limiter for admin endpoints
 * 5 requests per minute
 */
export const adminLimiter = rateLimit({
  windowMs: 60 * 1000, // 1 minute
  max: 5, // 5 requests per minute
  message: {
    success: false,
    error: 'Too many admin requests',
    message: 'Rate limit exceeded for admin endpoints'
  },
  standardHeaders: true,
  legacyHeaders: false,
  keyGenerator: getClientIp,
  handler: rateLimitHandler
});
