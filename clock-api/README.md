# Clock API

Production-ready Node.js API for cross-platform clock applications with Unsplash integration, download tracking, and analytics.

## Features

- **Unsplash Integration**: Secure API key proxy for fetching high-quality background images
- **Download Tracking**: Track image views and downloads with detailed statistics
- **In-Memory Caching**: Redis-like caching with configurable TTL (default 1 hour)
- **Rate Limiting**: Protect endpoints from abuse
- **Platform Detection**: Automatic detection of client platform (iOS, Android, Windows, macOS, etc.)
- **Analytics**: Comprehensive statistics and insights
- **Security**: Helmet.js, CORS, API key authentication
- **SQLite Database**: Lightweight, zero-configuration database
- **Production Ready**: Logging, error handling, graceful shutdown

## Architecture

```
Mobile/Desktop Apps (iOS, Android, macOS, Windows)
              ↓
        Clock API (Node.js)
              ↓
      ├─→ Unsplash API (proxied, API key hidden)
      ├─→ SQLite Database (tracking data)
      └─→ Node-Cache (in-memory caching)
```

## Quick Start

### 1. Install Dependencies

```bash
cd clock-api
npm install
```

### 2. Configuration

Copy `.env.example` to `.env` and configure:

```bash
cp .env.example .env
```

Edit `.env`:

```env
PORT=3000
NODE_ENV=development

# Required: Get your key from https://unsplash.com/developers
UNSPLASH_ACCESS_KEY=your_unsplash_access_key_here

# Optional: For protecting admin endpoints
API_KEY=your_secure_api_key_for_client_apps

# Cache TTL in seconds (default: 3600 = 1 hour)
CACHE_TTL=3600

# Rate limiting
RATE_LIMIT_WINDOW_MS=900000
RATE_LIMIT_MAX_REQUESTS=100

# Database path
DATABASE_PATH=./data/clock.db

# CORS: comma-separated origins or * for all
ALLOWED_ORIGINS=*
```

### 3. Initialize Database

```bash
npm run init-db
```

### 4. Start Server

```bash
# Development (with auto-reload)
npm run dev

# Production
npm start
```

Server will start at `http://localhost:3000`

## API Endpoints

### 1. Health Check

Check if API is running.

```bash
GET /api/v1/health
```

**Response:**
```json
{
  "success": true,
  "status": "healthy",
  "timestamp": "2025-10-13T10:30:00.000Z"
}
```

### 2. Get Images

Fetch random images from Unsplash (proxied).

```bash
GET /api/v1/images?count=10&query=nature,landscape
```

**Query Parameters:**
- `count` (optional): Number of images (1-30, default: 10)
- `query` (optional): Search query (default: "nature,landscape")

**Response:**
```json
{
  "success": true,
  "count": 10,
  "cached": false,
  "images": [
    {
      "id": "abc123",
      "url": "https://images.unsplash.com/photo-...",
      "downloadUrl": "https://images.unsplash.com/photo-...",
      "thumbnail": "https://images.unsplash.com/photo-...",
      "photographer": "John Doe",
      "photographerUrl": "https://unsplash.com/@johndoe",
      "description": "Beautiful landscape",
      "color": "#4A90E2",
      "width": 3840,
      "height": 2160
    }
  ]
}
```

### 3. Track Image View

Track when an image is displayed to a user.

```bash
POST /api/v1/track/view
Content-Type: application/json

{
  "imageId": "abc123",
  "photographer": "John Doe",
  "photographerUrl": "https://unsplash.com/@johndoe"
}
```

**Optional Headers:**
- `X-Platform`: Platform identifier (ios, android, windows, macos, etc.)

**Response:**
```json
{
  "success": true,
  "message": "View tracked successfully",
  "id": 1
}
```

### 4. Track Image Download

Track when a user downloads an image.

```bash
POST /api/v1/track/download
Content-Type: application/json

{
  "imageId": "abc123",
  "photographer": "John Doe",
  "photographerUrl": "https://unsplash.com/@johndoe"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Download tracked successfully",
  "id": 1
}
```

### 5. Get Statistics

Get usage statistics and analytics.

```bash
# Simple summary
GET /api/v1/statistics

# Detailed statistics
GET /api/v1/statistics?detailed=true&days=30
```

**Query Parameters:**
- `detailed` (optional): Return detailed stats (default: false)
- `days` (optional): Number of days to analyze (default: 30)

**Simple Response:**
```json
{
  "success": true,
  "totalViews": 1523,
  "totalDownloads": 342
}
```

**Detailed Response:**
```json
{
  "success": true,
  "period": "Last 30 days",
  "totals": {
    "totalViews": 1523,
    "totalDownloads": 342,
    "recentViews": 456,
    "recentDownloads": 89
  },
  "topImages": {
    "mostViewed": [
      {
        "image_id": "abc123",
        "photographer": "John Doe",
        "view_count": 45
      }
    ],
    "mostDownloaded": [
      {
        "image_id": "xyz789",
        "photographer": "Jane Smith",
        "download_count": 23
      }
    ]
  },
  "platforms": [
    { "platform": "ios", "count": 345 },
    { "platform": "android", "count": 298 }
  ],
  "dailyActivity": [
    {
      "date": "2025-10-13",
      "views": 67,
      "downloads": 12
    }
  ]
}
```

### 6. Cache Management (Protected)

**Get Cache Statistics:**
```bash
GET /api/v1/images/cache-stats
X-API-Key: your_api_key
```

**Clear Cache:**
```bash
POST /api/v1/images/clear-cache
X-API-Key: your_api_key
```

## Client Integration Examples

### iOS/macOS (Swift)

```swift
import Foundation

class ClockAPI {
    let baseURL = "https://your-api.com/api/v1"

    func fetchImages() async throws -> [ClockImage] {
        let url = URL(string: "\(baseURL)/images?count=10")!
        let (data, _) = try await URLSession.shared.data(from: url)
        let response = try JSONDecoder().decode(ImagesResponse.self, from: data)
        return response.images
    }

    func trackView(imageId: String, photographer: String) async {
        var request = URLRequest(url: URL(string: "\(baseURL)/track/view")!)
        request.httpMethod = "POST"
        request.setValue("application/json", forHTTPHeaderField: "Content-Type")
        request.setValue("ios", forHTTPHeaderField: "X-Platform")

        let body = ["imageId": imageId, "photographer": photographer]
        request.httpBody = try? JSONSerialization.data(withJSONObject: body)

        _ = try? await URLSession.shared.data(for: request)
    }
}
```

### Android (Kotlin)

```kotlin
import retrofit2.http.*

interface ClockAPI {
    @GET("images")
    suspend fun getImages(
        @Query("count") count: Int = 10
    ): ImagesResponse

    @POST("track/view")
    suspend fun trackView(
        @Body request: TrackRequest,
        @Header("X-Platform") platform: String = "android"
    ): TrackResponse

    @POST("track/download")
    suspend fun trackDownload(@Body request: TrackRequest): TrackResponse
}
```

### Windows (C#)

```csharp
using System.Net.Http;
using System.Text.Json;

public class ClockAPI
{
    private readonly HttpClient _client;
    private const string BaseUrl = "https://your-api.com/api/v1";

    public async Task<ImagesResponse> GetImages(int count = 10)
    {
        var response = await _client.GetAsync($"{BaseUrl}/images?count={count}");
        var json = await response.Content.ReadAsStringAsync();
        return JsonSerializer.Deserialize<ImagesResponse>(json);
    }

    public async Task TrackView(string imageId, string photographer)
    {
        var request = new { imageId, photographer };
        var content = new StringContent(
            JsonSerializer.Serialize(request),
            System.Text.Encoding.UTF8,
            "application/json"
        );

        _client.DefaultRequestHeaders.Add("X-Platform", "windows");
        await _client.PostAsync($"{BaseUrl}/track/view", content);
    }
}
```

## Deployment

### Railway

1. Install Railway CLI:
   ```bash
   npm install -g @railway/cli
   ```

2. Login and deploy:
   ```bash
   railway login
   railway init
   railway up
   ```

3. Set environment variables:
   ```bash
   railway variables set UNSPLASH_ACCESS_KEY=your_key_here
   railway variables set NODE_ENV=production
   ```

### Fly.io

1. Install Fly CLI:
   ```bash
   curl -L https://fly.io/install.sh | sh
   ```

2. Deploy:
   ```bash
   fly launch
   fly deploy
   ```

3. Set secrets:
   ```bash
   fly secrets set UNSPLASH_ACCESS_KEY=your_key_here
   ```

### Docker

```bash
docker build -t clock-api .
docker run -p 3000:3000 -e UNSPLASH_ACCESS_KEY=your_key clock-api
```

## Rate Limits

- **General API**: 100 requests per 15 minutes per IP
- **Tracking endpoints**: 30 requests per minute per IP
- **Statistics endpoint**: 10 requests per minute per IP
- Authenticated requests (with API key) bypass rate limits

## Security

- API key never exposed to clients
- Helmet.js security headers
- CORS configuration
- Rate limiting
- Input validation
- SQL injection prevention (parameterized queries)

## Database

SQLite database with three tables:
- `image_views`: Track image displays
- `image_downloads`: Track image downloads
- `api_usage`: API usage metrics (planned)

Database location: `./data/clock.db`

## Performance

- In-memory caching reduces Unsplash API calls
- SQLite with WAL mode for concurrent reads
- Compression enabled
- Response times: ~20-50ms (cached), ~200-500ms (Unsplash API)

## Monitoring

Check logs for:
- API requests (Morgan logging)
- Cache hits/misses
- Database operations
- Errors and warnings

## Troubleshooting

**Database errors:**
```bash
npm run init-db
```

**Cache issues:**
```bash
curl -X POST http://localhost:3000/api/v1/images/clear-cache \
  -H "X-API-Key: your_api_key"
```

**Unsplash rate limit:**
- Free tier: 50 requests/hour
- Solution: Increase CACHE_TTL to 7200 (2 hours) or more

## License

MIT

## Support

For issues and questions, create an issue on GitHub or contact support@saidim.com
