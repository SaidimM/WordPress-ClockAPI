# Deployment Guide

This guide covers deploying the Clock API to various platforms.

## Quick Deploy Options

### Option 1: Railway (Recommended - Easiest)

Railway offers the simplest deployment with automatic configuration.

**Cost:** ~$5/month (includes 500 GB bandwidth)

**Steps:**

1. Install Railway CLI:
   ```bash
   npm install -g @railway/cli
   ```

2. Login:
   ```bash
   railway login
   ```

3. Initialize project:
   ```bash
   cd clock-api
   railway init
   ```

4. Set environment variables:
   ```bash
   railway variables set UNSPLASH_ACCESS_KEY=your_unsplash_key_here
   railway variables set NODE_ENV=production
   railway variables set API_KEY=your_secure_api_key
   ```

5. Deploy:
   ```bash
   railway up
   ```

6. Get your URL:
   ```bash
   railway domain
   ```

**Volume for persistent database:**
```bash
railway volume create data
railway volume attach data /app/data
```

---

### Option 2: Fly.io (Best for scaling)

Fly.io offers global edge deployment with automatic scaling.

**Cost:** Free tier available, then $1.94/month for 256MB RAM

**Steps:**

1. Install Fly CLI:
   ```bash
   curl -L https://fly.io/install.sh | sh
   ```

2. Login:
   ```bash
   fly auth login
   ```

3. Launch app:
   ```bash
   cd clock-api
   fly launch
   ```

   Answer the prompts:
   - App name: clock-api (or your choice)
   - Region: Choose closest to your users
   - PostgreSQL: No (we use SQLite)
   - Redis: No (we use node-cache)

4. Set secrets:
   ```bash
   fly secrets set UNSPLASH_ACCESS_KEY=your_key_here
   fly secrets set API_KEY=your_api_key
   ```

5. Create volume for database:
   ```bash
   fly volumes create clock_data --size 1
   ```

6. Update fly.toml to mount volume:
   ```toml
   [mounts]
     source = "clock_data"
     destination = "/app/data"
   ```

7. Deploy:
   ```bash
   fly deploy
   ```

8. Check status:
   ```bash
   fly status
   fly logs
   ```

---

### Option 3: Render

**Cost:** Free tier available (spins down after inactivity), $7/month for always-on

**Steps:**

1. Push code to GitHub

2. Go to [render.com](https://render.com)

3. Create New > Web Service

4. Connect your GitHub repository

5. Configure:
   - Name: clock-api
   - Environment: Node
   - Build Command: `npm install`
   - Start Command: `node src/server.js`

6. Add environment variables:
   - `UNSPLASH_ACCESS_KEY`
   - `NODE_ENV=production`
   - `API_KEY`

7. Click "Create Web Service"

8. Add disk for database:
   - Go to your service settings
   - Add Disk: `/app/data`

---

### Option 4: DigitalOcean App Platform

**Cost:** $5/month

**Steps:**

1. Push code to GitHub

2. Go to [DigitalOcean Apps](https://cloud.digitalocean.com/apps)

3. Create App > GitHub

4. Select repository

5. Configure:
   - Name: clock-api
   - Build Command: `npm install`
   - Run Command: `npm start`

6. Add environment variables in Settings

7. Deploy

---

### Option 5: Docker + VPS (Most control)

Deploy to any VPS (AWS EC2, DigitalOcean Droplet, Linode, etc.)

**Cost:** $4-10/month

**Steps:**

1. Build Docker image:
   ```bash
   docker build -t clock-api .
   ```

2. Test locally:
   ```bash
   docker run -p 3000:3000 \
     -e UNSPLASH_ACCESS_KEY=your_key \
     -e NODE_ENV=production \
     -v $(pwd)/data:/app/data \
     clock-api
   ```

3. Push to Docker Hub:
   ```bash
   docker tag clock-api your-username/clock-api
   docker push your-username/clock-api
   ```

4. On your VPS:
   ```bash
   docker pull your-username/clock-api
   docker run -d \
     --name clock-api \
     --restart unless-stopped \
     -p 3000:3000 \
     -e UNSPLASH_ACCESS_KEY=your_key \
     -e NODE_ENV=production \
     -v /var/lib/clock-api/data:/app/data \
     your-username/clock-api
   ```

5. Set up nginx as reverse proxy:
   ```nginx
   server {
       listen 80;
       server_name api.yourdomain.com;

       location / {
           proxy_pass http://localhost:3000;
           proxy_http_version 1.1;
           proxy_set_header Upgrade $http_upgrade;
           proxy_set_header Connection 'upgrade';
           proxy_set_header Host $host;
           proxy_cache_bypass $http_upgrade;
           proxy_set_header X-Real-IP $remote_addr;
           proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
       }
   }
   ```

6. Get SSL certificate:
   ```bash
   sudo certbot --nginx -d api.yourdomain.com
   ```

---

## Environment Variables Reference

Required:
- `UNSPLASH_ACCESS_KEY` - Your Unsplash API key
- `NODE_ENV` - Set to "production"

Optional:
- `PORT` - Server port (default: 3000)
- `API_KEY` - For protecting admin endpoints
- `CACHE_TTL` - Cache duration in seconds (default: 3600)
- `RATE_LIMIT_WINDOW_MS` - Rate limit window (default: 900000)
- `RATE_LIMIT_MAX_REQUESTS` - Max requests per window (default: 100)
- `DATABASE_PATH` - SQLite database path (default: ./data/clock.db)
- `ALLOWED_ORIGINS` - CORS origins (default: *)

---

## Post-Deployment Checklist

1. **Test health endpoint:**
   ```bash
   curl https://your-api-url.com/api/v1/health
   ```

2. **Test images endpoint:**
   ```bash
   curl https://your-api-url.com/api/v1/images?count=5
   ```

3. **Test tracking:**
   ```bash
   curl -X POST https://your-api-url.com/api/v1/track/view \
     -H "Content-Type: application/json" \
     -d '{"imageId":"test123","photographer":"Test User"}'
   ```

4. **Check statistics:**
   ```bash
   curl https://your-api-url.com/api/v1/statistics
   ```

5. **Monitor logs** (platform-specific):
   - Railway: `railway logs`
   - Fly.io: `fly logs`
   - Render: Check dashboard
   - Docker: `docker logs clock-api`

6. **Set up monitoring:**
   - Add uptime monitoring (UptimeRobot, Pingdom)
   - Set up error alerting
   - Monitor Unsplash API usage

---

## Database Backup

Since we use SQLite, backup is simple:

**On Railway/Render/DO:**
```bash
# Via SSH or their CLI
cp /app/data/clock.db /app/data/backup-$(date +%Y%m%d).db
```

**On Fly.io:**
```bash
fly ssh console
cp /app/data/clock.db /app/data/backup.db
fly ssh sftp get /app/data/backup.db ./
```

**Automated backups (cron job on VPS):**
```bash
0 0 * * * docker exec clock-api cp /app/data/clock.db /app/data/backup-$(date +\%Y\%m\%d).db
```

---

## Scaling Considerations

### Horizontal Scaling (Multiple Instances)

If you need multiple instances, switch from SQLite to PostgreSQL:

1. Install PostgreSQL client:
   ```bash
   npm install pg
   ```

2. Update database code to use PostgreSQL

3. Use managed PostgreSQL:
   - Railway: Add PostgreSQL plugin
   - Fly.io: `fly postgres create`
   - Render: Add PostgreSQL database

### Caching at Scale

For multiple instances, use Redis instead of node-cache:

1. Install Redis client:
   ```bash
   npm install redis
   ```

2. Update caching service to use Redis

3. Use managed Redis:
   - Railway: Add Redis plugin
   - Fly.io: Upstash Redis
   - Render: Add Redis

---

## Troubleshooting

**502 Bad Gateway:**
- Check if app is running: `railway logs` or `fly logs`
- Verify PORT environment variable matches
- Check health endpoint

**Database errors:**
- Ensure `/app/data` directory is writable
- Check if volume is mounted correctly
- Initialize database: `railway run npm run init-db`

**Unsplash API errors:**
- Verify UNSPLASH_ACCESS_KEY is set correctly
- Check Unsplash rate limits (50 req/hour free tier)
- Increase CACHE_TTL to reduce API calls

**High response times:**
- Check if cache is working: `curl https://your-api/api/v1/images/cache-stats`
- Increase cache TTL
- Add CDN (Cloudflare)

---

## Cost Comparison

| Platform | Free Tier | Paid Tier | Best For |
|----------|-----------|-----------|----------|
| Railway | No | $5/mo | Simplicity |
| Fly.io | Yes (limited) | ~$2/mo | Global reach |
| Render | Yes (sleeps) | $7/mo | Quick start |
| DigitalOcean | No | $5/mo | Reliability |
| VPS | No | $4-10/mo | Full control |

---

## Next Steps

After deployment:

1. Update your mobile/desktop apps with the API URL
2. Set up monitoring and alerts
3. Create backups schedule
4. Document your API endpoints for your team
5. Consider adding authentication for statistics endpoint
6. Set up CI/CD for automatic deployments

Need help? Check the main README.md or create an issue on GitHub.
