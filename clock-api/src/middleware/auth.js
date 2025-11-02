import { config } from '../utils/config.js';

/**
 * API Key Authentication Middleware
 * Validates API key for protected endpoints
 */
export function authenticateApiKey(req, res, next) {
  // Skip authentication if API_KEY is not configured
  if (!config.security.apiKey) {
    return next();
  }

  const apiKey = req.headers['x-api-key'] || req.query.apiKey;

  if (!apiKey) {
    return res.status(401).json({
      success: false,
      error: 'API key is required',
      message: 'Please provide an API key in X-API-Key header or apiKey query parameter'
    });
  }

  if (apiKey !== config.security.apiKey) {
    return res.status(403).json({
      success: false,
      error: 'Invalid API key',
      message: 'The provided API key is invalid'
    });
  }

  next();
}

/**
 * Optional API Key Authentication
 * Allows requests to proceed even without valid API key
 */
export function optionalApiKey(req, res, next) {
  if (!config.security.apiKey) {
    req.authenticated = false;
    return next();
  }

  const apiKey = req.headers['x-api-key'] || req.query.apiKey;
  req.authenticated = apiKey === config.security.apiKey;

  next();
}
