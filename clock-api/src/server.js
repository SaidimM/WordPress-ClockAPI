import express from 'express';
import cors from 'cors';
import helmet from 'helmet';
import compression from 'compression';
import morgan from 'morgan';
import { config, validateConfig } from './utils/config.js';
import { initDatabase } from './database/db.js';
import { errorHandler, notFoundHandler } from './middleware/errorHandler.js';
import routes from './routes/index.js';
import { initScheduler } from './services/scheduler.js';

// Validate configuration
try {
  validateConfig();
} catch (error) {
  console.error('❌ Configuration error:', error.message);
  process.exit(1);
}

// Initialize database
try {
  initDatabase();
} catch (error) {
  console.error('❌ Database initialization failed:', error.message);
  process.exit(1);
}

// Create Express app
const app = express();

// Trust proxy (important for Railway/Fly.io/etc)
app.set('trust proxy', 1);

// Security middleware
app.use(helmet({
  crossOriginResourcePolicy: { policy: "cross-origin" }
}));

// CORS configuration
const corsOptions = {
  origin: (origin, callback) => {
    // Allow requests with no origin (mobile apps, Postman, etc)
    if (!origin) return callback(null, true);

    if (config.cors.origins.includes('*')) {
      return callback(null, true);
    }

    if (config.cors.origins.includes(origin)) {
      return callback(null, true);
    }

    callback(new Error('Not allowed by CORS'));
  },
  credentials: true
};

app.use(cors(corsOptions));

// Compression
app.use(compression());

// Body parser
app.use(express.json({ limit: '10mb' }));
app.use(express.urlencoded({ extended: true, limit: '10mb' }));

// Logging
if (config.nodeEnv === 'development') {
  app.use(morgan('dev'));
} else {
  app.use(morgan('combined'));
}

// API routes
app.use('/api/v1', routes);

// Root endpoint
app.get('/', (req, res) => {
  res.json({
    success: true,
    name: 'Clock API',
    version: '1.0.0',
    description: 'Production-ready API for clock app with Unsplash integration',
    endpoints: {
      health: 'GET /api/v1/health',
      images: 'GET /api/v1/images',
      trackView: 'POST /api/v1/track/view',
      trackDownload: 'POST /api/v1/track/download',
      statistics: 'GET /api/v1/statistics'
    },
    documentation: 'See README.md for full API documentation'
  });
});

// 404 handler
app.use(notFoundHandler);

// Error handler
app.use(errorHandler);

// Start server
const server = app.listen(config.port, () => {
  console.log('');
  console.log('╔════════════════════════════════════════════════════════╗');
  console.log('║                                                        ║');
  console.log('║              🕐  Clock API Server                      ║');
  console.log('║                                                        ║');
  console.log('╚════════════════════════════════════════════════════════╝');
  console.log('');
  console.log(`✓ Server running on port ${config.port}`);
  console.log(`✓ Environment: ${config.nodeEnv}`);
  console.log(`✓ API available at: http://localhost:${config.port}/api/v1`);
  console.log('');
  console.log('Available endpoints:');
  console.log(`  GET  /api/v1/health`);
  console.log(`  GET  /api/v1/images`);
  console.log(`  POST /api/v1/track/view`);
  console.log(`  POST /api/v1/track/download`);
  console.log(`  GET  /api/v1/statistics`);
  console.log('');

  // Initialize scheduler for periodic tasks
  initScheduler();
});

// Graceful shutdown
process.on('SIGTERM', () => {
  console.log('SIGTERM signal received: closing HTTP server');
  server.close(() => {
    console.log('HTTP server closed');
    process.exit(0);
  });
});

process.on('SIGINT', () => {
  console.log('\nSIGINT signal received: closing HTTP server');
  server.close(() => {
    console.log('HTTP server closed');
    process.exit(0);
  });
});

export default app;
