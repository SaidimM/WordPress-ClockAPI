import dotenv from 'dotenv';
import { fileURLToPath } from 'url';
import { dirname, join } from 'path';

const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);

dotenv.config({ path: join(__dirname, '../../.env') });

export const config = {
  port: process.env.PORT || 3000,
  nodeEnv: process.env.NODE_ENV || 'development',

  unsplash: {
    accessKey: process.env.UNSPLASH_ACCESS_KEY || '',
    apiUrl: 'https://api.unsplash.com'
  },

  security: {
    apiKey: process.env.API_KEY || ''
  },

  cache: {
    ttl: parseInt(process.env.CACHE_TTL) || 3600 // 1 hour default
  },

  rateLimit: {
    windowMs: parseInt(process.env.RATE_LIMIT_WINDOW_MS) || 15 * 60 * 1000, // 15 minutes
    maxRequests: parseInt(process.env.RATE_LIMIT_MAX_REQUESTS) || 100
  },

  database: {
    path: process.env.DATABASE_PATH || './data/clock.db'
  },

  cors: {
    origins: process.env.ALLOWED_ORIGINS
      ? process.env.ALLOWED_ORIGINS.split(',').map(o => o.trim())
      : ['*']
  }
};

// Validate required configuration
export function validateConfig() {
  const errors = [];

  if (!config.unsplash.accessKey) {
    errors.push('UNSPLASH_ACCESS_KEY is required');
  }

  if (config.nodeEnv === 'production' && !config.security.apiKey) {
    console.warn('⚠️  Warning: API_KEY not set in production mode');
  }

  if (errors.length > 0) {
    throw new Error(`Configuration errors:\n${errors.join('\n')}`);
  }
}
