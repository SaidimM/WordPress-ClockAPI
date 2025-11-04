# WordPress-ClockAPI

A production-ready Docker-based system that provides a web-based clock with dynamic backgrounds and a RESTful API for serving high-quality wallpaper images to clock applications across platforms.

## Quick Overview

**What it does:**
- Displays a full-screen digital clock with beautiful Unsplash nature/landscape backgrounds
- Provides API endpoints for mobile and desktop clock apps to fetch wallpaper images
- Automatically downloads, caches, and serves high-quality images
- Tracks analytics (views, downloads, usage patterns)
- Hides Unsplash API keys from clients via proxy pattern

**Tech Stack:**
- WordPress 6.x with custom plugin
- Node.js 18 Express API
- MySQL 8.0 (WordPress database)
- SQLite (Clock API database)
- Nginx (reverse proxy + SSL termination)
- Docker Compose

---

## Architecture

```
┌─────────────────────────────────────┐
│  Nginx (Port 443 - HTTPS)           │
│  - SSL/TLS termination              │
│  - Reverse proxy                    │
│  - Static file serving              │
└──────────┬──────────────┬───────────┘
           │              │
    ┌──────▼──────┐  ┌───▼───────────────┐
    │ WordPress   │  │  Clock API        │
    │ (Apache/PHP)│  │  (Node.js/Express)│
    │ Port 80     │  │  Port 3000        │
    └──────┬──────┘  └───┬───────────────┘
           │             │
    ┌──────▼──────┐  ┌───▼───────────┐
    │  MySQL 8.0  │  │ SQLite + Cache│
    └─────────────┘  └───────────────┘
```

### Request Flow

**Web Clock Display:**
```
Browser → Nginx → WordPress → /clock page → Loads images via AJAX → Clock API
```

**Mobile/Desktop App:**
```
App → Nginx → Clock API → Cached Images (local filesystem)
                             ↓ (if cache empty)
                        Unsplash API
```

---

## Key Components

### 1. Custom WordPress Plugin

**Location:** `wordpress/plugins/custom-clock/custom-clock.php`

**Features:**
- **Clock Display Route:** Creates `/clock` endpoint with full-screen clock UI
- **REST API Proxy:** `/wp-json/pwc/v1/unsplash-images` - securely proxies Unsplash API
- **Rate Limiting:** 60 requests/minute per IP with detailed logging
- **Admin Dashboard:** WordPress Admin → Settings → World Clock
  - Settings tab: Unsplash API key configuration
  - Image Gallery: View/manage cached images
  - Statistics: View/download analytics
  - Rate Limits: Monitor API protection
  - Health: API health status

**Rate Limiter Class:**
- `PWC_Rate_Limiter` class in plugin
- IP-based tracking using WordPress transients
- Proxy-aware IP detection (X-Forwarded-For, X-Real-IP)
- Returns HTTP 429 when exceeded

**Template:**
- `clock-template.php` - Modern responsive UI with Orbitron font
- Smooth background transitions with zoom/pan effects
- Photographer attribution
- Fallback gradient backgrounds

### 2. Clock API (Node.js)

**Location:** `clock-api/`

**Structure:**
```
clock-api/
├── src/
│   ├── server.js                      # Main entry point
│   ├── controllers/
│   │   ├── imagesController.js        # Image fetching & caching
│   │   └── trackingController.js      # Analytics tracking
│   ├── services/
│   │   ├── unsplashService.js         # Unsplash API integration
│   │   ├── imageCacheService.js       # Local image caching
│   │   └── scheduler.js               # Automated tasks (every 12h)
│   ├── middleware/
│   │   ├── errorHandler.js            # Global error handling
│   │   ├── rateLimiter.js             # Rate limiting
│   │   └── auth.js                    # API key authentication
│   ├── routes/index.js                # API route definitions
│   ├── database/
│   │   ├── db.js                      # SQLite connection
│   │   ├── init.js                    # Database initialization
│   │   └── schema.sql                 # Database schema
│   └── utils/config.js                # Configuration
├── data/
│   ├── clock.db                       # SQLite database
│   └── images/                        # Cached images
└── Dockerfile                         # Multi-stage build
```

**API Endpoints:**

| Endpoint | Method | Purpose | Rate Limit |
|----------|--------|---------|------------|
| `/api/v1/health` | GET | Health check | 100/15min |
| `/api/v1/images` | GET | Get random images | 100/15min |
| `/api/v1/track/view` | POST | Track image view | 30/min |
| `/api/v1/track/download` | POST | Track download | 30/min |
| `/api/v1/statistics` | GET | Usage statistics | 10/min |
| `/api/v1/images/refresh-cache` | POST | Manual cache refresh | 5/min (auth) |
| `/api/v1/images/cache-info` | GET | Cache statistics | 5/min (auth) |
| `/api/v1/images/:imageId` | DELETE | Delete cached image | 5/min (auth) |

**Scheduled Tasks:**
- Runs every 12 hours (cron: `0 */12 * * *`)
- Downloads fresh images from Unsplash
- Maintains image pool
- Cleans up old images

**Database Tables (SQLite):**
- `image_views` - Track when images are displayed
- `image_downloads` - Track image downloads
- `cached_images` - Local image metadata
- `api_usage` - API metrics (planned)

### 3. Nginx Configuration

**Location:** `nginx/conf.d/wordpress.conf`

**Key Features:**
- HTTP → HTTPS redirect
- TLS 1.2/1.3 with optimized ciphers
- Security headers (HSTS, X-Frame-Options, CSP, etc.)
- Reverse proxy rules:
  - `/api/clock/*` → Clock API (port 3000)
  - `/cache/images/*` → Volume-mounted cache directory
  - `/*` → WordPress (port 80)
- CORS headers for API endpoints

### 4. Docker Compose Services

**Location:** `docker-compose.yml`

| Service | Container Name | Purpose |
|---------|---------------|---------|
| wordpress | wordpress-app | WordPress installation |
| mysql | wordpress-mysql | MySQL 8.0 database |
| clock-api | wordpress-clock-api | Node.js Clock API |
| nginx | wordpress-nginx | Reverse proxy + SSL |
| cert-updater | wordpress-cert-updater | Automated SSL updates (Tencent) |

**Volumes:**
- `mysql_data` - MySQL database persistence
- `wordpress_data` - WordPress files and uploads
- `clock_api_data` - SQLite DB + cached images

**Networks:**
- `wordpress-network` - Bridge network for inter-container communication

---

## Important File Locations

### Configuration Files

| File | Purpose |
|------|---------|
| `.env` | Environment variables (API keys, domains, DB credentials) |
| `docker-compose.yml` | Container orchestration |
| `nginx/conf.d/wordpress.conf` | Nginx routing and SSL configuration |
| `wordpress/plugins/custom-clock/custom-clock.php` | Main plugin file (2000+ lines) |
| `clock-api/src/utils/config.js` | Clock API configuration |

### Key Source Files

| File | Purpose |
|------|---------|
| `clock-api/src/server.js` | Clock API entry point |
| `clock-api/src/services/imageCacheService.js` | Image download and caching logic |
| `clock-api/src/services/scheduler.js` | Automated image refresh tasks |
| `wordpress/plugins/custom-clock/clock-template.php` | Clock UI template |
| `wordpress/plugins/custom-clock/admin/tabs/image-gallery.php` | Admin gallery UI |

### Deployment Files

| File | Purpose |
|------|---------|
| `deploy.sh` | Automated deployment script (757 lines) |
| `DEPLOYMENT_CHINA.md` | China-specific deployment guide |
| `cert-updater/update_cert.py` | SSL certificate automation |

---

## How It Works

### Image Caching Flow

1. **Initial Request:**
   - Client requests images from Clock API
   - Clock API checks local cache (SQLite + filesystem)
   - If cache empty → fetches from Unsplash API
   - Downloads high-res images (3840px width)
   - Stores in `clock-api/data/images/`
   - Returns image URLs to client

2. **Subsequent Requests:**
   - Clock API returns cached images
   - Nginx serves images directly from filesystem
   - No Unsplash API calls needed

3. **Scheduled Refresh:**
   - Every 12 hours, scheduler runs
   - Downloads new images from Unsplash
   - Maintains fresh image pool
   - Old images remain until manually deleted

### Rate Limiting Flow

**WordPress Plugin:**
- Checks IP address (proxy-aware)
- Looks up request count in WordPress transients
- If exceeded → returns HTTP 429
- If under limit → increments counter

**Clock API:**
- express-rate-limit middleware
- Multiple tiers based on endpoint sensitivity
- API key holders bypass limits
- Returns HTTP 429 with retry-after header

### Authentication Flow

**Admin Endpoints (Clock API):**
- Requires `X-API-Key` header
- Validated against `CLOCK_API_KEY` environment variable
- Grants access to admin endpoints
- Bypasses rate limits

---

## Common Tasks

### View Logs

```bash
# All containers
docker-compose logs -f

# Specific service
docker-compose logs -f clock-api
docker-compose logs -f wordpress
docker-compose logs -f nginx
```

### Restart Services

```bash
# All services
docker-compose restart

# Specific service
docker-compose restart clock-api
docker-compose restart nginx
```

### Access Containers

```bash
# Clock API
docker-compose exec clock-api sh

# WordPress
docker-compose exec wordpress bash

# MySQL
docker-compose exec mysql mysql -u root -p
```

### Check Service Status

```bash
docker-compose ps
```

### Update Code

**WordPress Plugin:**
```bash
# Plugin is volume-mounted, edit directly:
nano wordpress/plugins/custom-clock/custom-clock.php

# No restart needed for PHP changes
```

**Clock API:**
```bash
# Edit code
nano clock-api/src/server.js

# Rebuild and restart
docker-compose up -d --build clock-api
```

### Database Access

**MySQL (WordPress):**
```bash
docker-compose exec mysql mysql -u wordpress -p
# Password from .env file
```

**SQLite (Clock API):**
```bash
docker-compose exec clock-api sh
cd /app/data
sqlite3 clock.db
```

### Clear Image Cache

**Via API:**
```bash
curl -X DELETE https://yourdomain.com/api/clock/images/IMAGE_ID \
  -H "X-API-Key: your-api-key"
```

**Via Filesystem:**
```bash
docker-compose exec clock-api sh
rm /app/data/images/*
```

**Via WordPress Admin:**
- Navigate to Settings → World Clock → Image Gallery
- Use delete buttons for individual images

---

## Deployment

### Quick Start (Automated)

```bash
./deploy.sh
```

This script handles:
- Docker installation
- SSL certificate setup
- Domain configuration
- Environment file creation
- Service deployment
- Health checks

### Manual Deployment

1. **Create `.env` file:**
```bash
cp .env.example .env
nano .env
# Fill in: DOMAIN, API keys, database credentials
```

2. **Deploy services:**
```bash
docker-compose --profile tencent-ssl up -d
```

3. **Verify deployment:**
```bash
docker-compose ps
curl https://yourdomain.com/api/clock/health
```

### China Mainland Deployment

- Use Chinese Docker image mirrors
- Configure Chinese npm registry
- Follow `DEPLOYMENT_CHINA.md` guide
- Use Tencent Cloud SSL if needed

---

## Security Features

### Multi-layer Rate Limiting
- WordPress plugin: 60 req/min per IP
- Clock API: Tiered limits per endpoint type
- Nginx: Connection limits (configurable)

### Authentication
- API key protection for admin endpoints
- WordPress admin for plugin settings
- Environment-based credential management

### Security Headers
- HSTS (HTTP Strict Transport Security)
- X-Frame-Options (clickjacking prevention)
- X-Content-Type-Options (MIME sniffing prevention)
- Content Security Policy
- Helmet.js in Clock API

### Container Security
- Non-root users in containers
- Read-only volumes where possible
- Network isolation
- Health checks for failure detection

---

## Performance Optimizations

### Multi-level Caching
1. **WordPress Transients:** 1-hour cache for Unsplash API responses
2. **Node.js Memory Cache:** In-memory caching for frequently accessed data
3. **Filesystem Cache:** Permanent local storage of images
4. **Nginx Static Serving:** Direct file serving bypassing application layers

### Image Optimization
- High-resolution downloads (3840px)
- Efficient storage management
- Lazy loading in gallery views
- Progressive image loading

### Database Optimization
- SQLite WAL mode
- Indexed queries
- Parameterized statements (SQL injection prevention)
- Automatic cleanup of old records

---

## Monitoring and Health

### Health Check Endpoints

**Clock API:**
```bash
curl https://yourdomain.com/api/clock/health
```

**WordPress:**
```bash
curl https://yourdomain.com/
```

### WordPress Admin Dashboard

Access at: `https://yourdomain.com/wp-admin/options-general.php?page=professional-world-clock`

Tabs:
- **Health:** API connectivity, cache status, system info
- **Statistics:** View counts, download counts, analytics
- **Rate Limits:** Monitor rate limit violations
- **Image Gallery:** Visual cache management

### Docker Health Checks

```bash
# View health status
docker-compose ps

# Detailed health info
docker inspect wordpress-clock-api | grep -A 10 Health
```

---

## Troubleshooting

### Clock API won't start

**Check logs:**
```bash
docker-compose logs clock-api
```

**Common issues:**
- Missing `.env` file or variables
- Port 3000 already in use
- SQLite permissions (should be handled by Dockerfile)

**Solution:**
```bash
# Recreate container
docker-compose up -d --force-recreate clock-api
```

### Images not loading

**Check cache:**
```bash
docker-compose exec clock-api ls -la /app/data/images/
```

**Check Unsplash API key:**
```bash
# In WordPress admin or .env file
docker-compose exec wordpress wp option get pwc_unsplash_api_key
```

**Manual cache refresh:**
```bash
curl -X POST https://yourdomain.com/api/clock/images/refresh-cache \
  -H "X-API-Key: your-api-key"
```

### Rate limit issues

**Check WordPress rate limit logs:**
```bash
docker-compose logs wordpress | grep "Rate limit"
```

**Reset rate limits:**
```bash
# WordPress transients expire automatically
# Or use WordPress CLI to clear all transients
docker-compose exec wordpress wp transient delete --all
```

### SSL certificate issues

**Check certificate expiration:**
```bash
echo | openssl s_client -connect yourdomain.com:443 2>/dev/null | openssl x509 -noout -dates
```

**Manual certificate update:**
```bash
docker-compose --profile tencent-ssl restart cert-updater
docker-compose logs cert-updater
```

### Nginx configuration errors

**Test configuration:**
```bash
docker-compose exec nginx nginx -t
```

**Reload without downtime:**
```bash
docker-compose exec nginx nginx -s reload
```

---

## Development

### Local Development Setup

1. **Use self-signed SSL or HTTP:**
```bash
# Edit nginx config to remove SSL or use HTTP
nano nginx/conf.d/wordpress.conf
```

2. **Adjust environment:**
```bash
# .env
ENVIRONMENT=development
DEBUG=true
```

3. **Enable hot reload (Clock API):**
```bash
# Add nodemon to package.json
# Mount code as volume in docker-compose.yml
```

### Testing API Endpoints

```bash
# Health check
curl https://yourdomain.com/api/clock/health

# Get images
curl https://yourdomain.com/api/clock/images?count=5

# Track view
curl -X POST https://yourdomain.com/api/clock/track/view \
  -H "Content-Type: application/json" \
  -d '{"imageId": "abc123", "platform": "web"}'

# Get statistics
curl https://yourdomain.com/api/clock/statistics
```

---

## Project Status

**Current Version:** 2.0.0 (WordPress Plugin)

**Recent Updates:**
- Fixed deployment script for from-scratch installations
- Fixed nginx image serving with proper volume mounting
- Improved SQLite permissions handling
- Added comprehensive documentation
- Optimized for China mainland deployment

**Stability:** Production-ready

---

## Use Cases

1. **Web Clock Display** - Full-screen clock for office displays, kiosks, digital signage
2. **Mobile Clock Apps** - Backend API for iOS/Android clock applications
3. **Desktop Clock Apps** - API for Windows/macOS screensaver/clock apps
4. **Wallpaper Service** - Curated high-quality image delivery service
5. **Analytics Platform** - Track image popularity and usage patterns

---

## Quick Reference

### URLs

- Web Clock: `https://yourdomain.com/clock`
- WordPress Admin: `https://yourdomain.com/wp-admin`
- Clock Settings: `https://yourdomain.com/wp-admin/options-general.php?page=professional-world-clock`
- API Base: `https://yourdomain.com/api/clock/`
- Health Check: `https://yourdomain.com/api/clock/health`

### Environment Variables

Key variables in `.env`:
- `DOMAIN` - Your domain name
- `UNSPLASH_ACCESS_KEY` - Unsplash API key
- `CLOCK_API_KEY` - Clock API admin key
- `MYSQL_ROOT_PASSWORD` - MySQL root password
- `MYSQL_PASSWORD` - WordPress database password

### Default Ports

- Nginx: 443 (HTTPS), 80 (HTTP redirect)
- WordPress: 80 (internal)
- Clock API: 3000 (internal)
- MySQL: 3306 (internal)

---

**Last Updated:** 2025-11-04
