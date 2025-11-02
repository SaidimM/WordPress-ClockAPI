# API Rate Limiting Documentation

## Overview

This document describes the rate limiting implementation for the Professional World Clock APIs. Rate limiting protects your APIs from abuse, prevents excessive bandwidth consumption, and ensures fair usage.

## What is Rate Limiting?

Rate limiting controls how many requests a client can make to your API within a specific time window. When a client exceeds the limit, they receive an HTTP 429 (Too Many Requests) error.

## Benefits

- **Bandwidth Protection**: Prevents excessive requests from consuming your hosting bandwidth
- **API Quota Management**: Protects your Unsplash API quota from being exhausted
- **DDoS Prevention**: Mitigates distributed denial-of-service attacks
- **Cost Control**: Prevents unexpected hosting costs from traffic spikes
- **Fair Usage**: Ensures all users get equal access to the API

## Active Rate Limits

### WordPress REST API

**Endpoint**: `/wp-json/pwc/v1/unsplash-images`

- **Limit**: 60 requests per minute
- **Window**: 60 seconds
- **Identifier**: `unsplash_api`
- **Protection**: Unsplash API proxy endpoint
- **Headers**: Returns `X-RateLimit-*` headers

**Configuration Location**: `wordpress/wp-content/plugins/custom-clock/custom-clock.php:251`

### Node.js API Endpoints

#### 1. General Endpoints
**Endpoints**: `/api/v1/images`, `/api/v1/images/download`

- **Limit**: 100 requests per 15 minutes
- **Configurable**: Via environment variables
- **Bypass**: Authenticated requests with valid API key
- **Use Case**: Public image fetching

**Configuration**:
- File: `clock-api/src/middleware/rateLimiter.js`
- Environment: `RATE_LIMIT_WINDOW_MS` and `RATE_LIMIT_MAX_REQUESTS`

#### 2. Tracking Endpoints
**Endpoints**: `/api/v1/track/view`, `/api/v1/track/download`

- **Limit**: 30 requests per minute
- **Use Case**: Analytics tracking
- **Reason**: Prevents flooding analytics database

#### 3. Statistics Endpoint
**Endpoint**: `/api/v1/statistics`

- **Limit**: 10 requests per minute
- **Reason**: Database query overhead
- **Bypass**: Optional API key authentication

#### 4. Admin Endpoints
**Endpoints**: `/api/v1/images/cache-*`, `/api/v1/images/:imageId` (DELETE)

- **Limit**: 5 requests per minute
- **Authentication**: Required (API key)
- **Use Case**: Administrative operations
- **Endpoints**:
  - `POST /api/v1/images/clear-cache`
  - `POST /api/v1/images/refresh-cache`
  - `GET /api/v1/images/cache-info`
  - `GET /api/v1/images/cache-stats`
  - `DELETE /api/v1/images/:imageId`

## IP Detection

Rate limiting tracks requests by client IP address. The system properly handles:

### Detection Priority (in order)

1. **X-Forwarded-For** - First IP in comma-separated list (for proxies)
2. **X-Real-IP** - Real client IP from nginx
3. **HTTP_CLIENT_IP** - Shared internet/ISP IP
4. **REMOTE_ADDR** - Direct connection IP

### Example Scenarios

- **Direct connection**: Uses `$_SERVER['REMOTE_ADDR']` or `req.ip`
- **Behind nginx**: Reads `X-Real-IP` header
- **Behind load balancer**: Extracts from `X-Forwarded-For`
- **Multiple proxies**: Takes first IP from `X-Forwarded-For` chain

## Rate Limit Headers

All API responses include rate limit information in standard headers:

```
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 45
X-RateLimit-Reset: 1699123456
```

### Header Descriptions

- **X-RateLimit-Limit**: Maximum requests allowed in the window
- **X-RateLimit-Remaining**: Requests remaining in current window
- **X-RateLimit-Reset**: Unix timestamp when the limit resets

## Rate Limit Exceeded Response

When a client exceeds the rate limit, they receive:

**HTTP Status**: 429 Too Many Requests

**Response Body** (WordPress):
```json
{
  "error": "Rate limit exceeded",
  "message": "Too many requests. Please try again later.",
  "limit": 60,
  "remaining": 0,
  "reset": 1699123456,
  "resetTime": "2023-11-04 15:30:56"
}
```

**Response Body** (Node.js):
```json
{
  "success": false,
  "error": "Too many requests",
  "message": "Rate limit exceeded, please try again later",
  "retryAfter": 60,
  "limit": 100,
  "windowSeconds": 900
}
```

## Logging

Rate limit violations are automatically logged for monitoring:

### WordPress Logs
```
[PWC RATE LIMIT] IP: 192.168.1.100, Endpoint: unsplash_api, Limit: 60/60s, Current: 61
```

**Location**: PHP error log (check with hosting provider)

### Node.js Logs
```
[RATE LIMIT] 2024-01-01T12:00:00.000Z - IP: 192.168.1.100 - Endpoint: /api/v1/images - Limit: 100 requests per 900000ms
```

**Location**: Docker container logs
- View with: `docker logs clock-api`
- Or: `docker logs clock-api -f` (follow mode)

## Configuration

### WordPress Plugin

**File**: `wordpress/wp-content/plugins/custom-clock/custom-clock.php`

**Line 251**: Modify rate limit parameters:

```php
$rate_limit = PWC_Rate_Limiter::check($client_ip, 60, 60, 'unsplash_api');
//                                                 ^^  ^^
//                                       max requests  window (seconds)
```

### Node.js API

**File**: `clock-api/src/middleware/rateLimiter.js`

Modify the rate limiter constants:

```javascript
export const generalLimiter = rateLimit({
  windowMs: 15 * 60 * 1000, // 15 minutes
  max: 100, // 100 requests per window
  // ...
});
```

**Environment Variables** (`.env`):

```bash
RATE_LIMIT_WINDOW_MS=900000    # 15 minutes in milliseconds
RATE_LIMIT_MAX_REQUESTS=100     # Maximum requests per window
```

### Apply Changes

After modifying configuration:

1. **WordPress**: No restart needed (PHP reloads automatically)
2. **Node.js API**: Restart container
   ```bash
   docker restart clock-api
   ```

## For Mobile App Development

### Best Practices

1. **Implement Exponential Backoff**
   ```javascript
   async function fetchWithRetry(url, maxRetries = 3) {
     for (let i = 0; i < maxRetries; i++) {
       const response = await fetch(url);

       if (response.status === 429) {
         const retryAfter = response.headers.get('X-RateLimit-Reset');
         const waitTime = Math.pow(2, i) * 1000; // Exponential backoff
         await new Promise(resolve => setTimeout(resolve, waitTime));
         continue;
       }

       return response;
     }
     throw new Error('Max retries exceeded');
   }
   ```

2. **Monitor Rate Limit Headers**
   ```javascript
   const response = await fetch('/api/v1/images');
   const remaining = response.headers.get('X-RateLimit-Remaining');

   if (remaining < 10) {
     console.warn('Approaching rate limit!');
   }
   ```

3. **Use API Key Authentication**
   - Authenticated requests bypass the general rate limiter
   - Include `X-API-Key` header in requests
   - Store API key securely in your app

4. **Implement Client-Side Caching**
   ```javascript
   // Cache images locally to reduce API calls
   const cachedImages = localStorage.getItem('images');
   if (cachedImages && !isExpired(cachedImages)) {
     return JSON.parse(cachedImages);
   }
   ```

5. **Respect Reset Times**
   ```javascript
   const resetTime = response.headers.get('X-RateLimit-Reset');
   const waitUntil = new Date(resetTime * 1000);
   console.log(`Rate limit resets at: ${waitUntil.toLocaleString()}`);
   ```

### API Key Setup

1. Set API key in Node.js environment:
   ```bash
   API_KEY=your_secure_api_key_here
   ```

2. Include in requests:
   ```javascript
   fetch('/api/v1/images', {
     headers: {
       'X-API-Key': 'your_secure_api_key_here'
     }
   })
   ```

## Monitoring & Troubleshooting

### View WordPress Rate Limit Logs

Check your hosting provider's PHP error log:
- cPanel: `/home/username/public_html/error_log`
- Plesk: `/var/www/vhosts/domain.com/logs/error_log`

### View Node.js Rate Limit Logs

```bash
# View recent logs
docker logs clock-api --tail 100

# Follow logs in real-time
docker logs clock-api -f

# Search for rate limit violations
docker logs clock-api 2>&1 | grep "RATE LIMIT"
```

### Common Issues

**Issue**: Getting 429 errors unexpectedly
- **Solution**: Check if you're behind a proxy/VPN with shared IP
- **Solution**: Clear rate limit cache (see below)

**Issue**: Rate limit not working
- **Solution**: Verify `trust proxy` setting in Node.js (line 32 in server.js)
- **Solution**: Check nginx is forwarding real IP headers

**Issue**: Different users sharing same rate limit
- **Solution**: Verify proxy headers are being forwarded correctly
- **Solution**: Check IP detection logic

### Clear Rate Limits

**WordPress** (PHP):
```php
// Clear specific IP rate limit
PWC_Rate_Limiter::clear('192.168.1.100', 'unsplash_api');
```

**Node.js** (restart container):
```bash
docker restart clock-api
```

## Security Considerations

1. **Do not disable rate limiting** in production
2. **Use strong API keys** for authentication
3. **Monitor logs regularly** for unusual activity
4. **Adjust limits** based on legitimate traffic patterns
5. **Consider Redis** for distributed rate limiting in production

## Future Enhancements

Potential improvements for high-traffic scenarios:

- **Redis-based storage**: For distributed rate limiting across multiple servers
- **Whitelisting**: Allow specific IPs to bypass rate limits
- **Dynamic rate limits**: Adjust limits based on user authentication level
- **Per-user rate limits**: Track by user ID instead of IP
- **Rate limit analytics**: Dashboard showing top violators and patterns

## Testing Rate Limits

### Test WordPress Endpoint

```bash
# Make rapid requests to trigger rate limit
for i in {1..65}; do
  curl -w "\nStatus: %{http_code}\n" \
    "https://saidim.com/wp-json/pwc/v1/unsplash-images"
  echo "Request $i"
done
```

### Test Node.js Endpoint

```bash
# Test general endpoint (100 req/15min)
for i in {1..105}; do
  curl -w "\nStatus: %{http_code}\n" \
    "https://saidim.com/api/v1/images?count=1"
  echo "Request $i"
done
```

### Verify Headers

```bash
curl -I "https://saidim.com/api/v1/images" | grep -i ratelimit
```

Expected output:
```
X-RateLimit-Limit: 100
X-RateLimit-Remaining: 99
X-RateLimit-Reset: 1699123456
```

## Support

For questions or issues with rate limiting:

1. Check logs for detailed error messages
2. Review this documentation
3. Verify configuration files
4. Test with curl commands above

## Summary

Rate limiting is now active on all API endpoints with sensible defaults:

- ✅ WordPress API: 60 req/min
- ✅ Node.js General: 100 req/15min
- ✅ Node.js Tracking: 30 req/min
- ✅ Node.js Stats: 10 req/min
- ✅ Node.js Admin: 5 req/min
- ✅ IP detection handles proxies
- ✅ Comprehensive logging
- ✅ Standard headers
- ✅ Mobile app friendly

Your APIs are now protected from abuse while remaining accessible for legitimate use!
