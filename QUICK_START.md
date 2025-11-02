# Quick Start Guide

## Running From Scratch

### Option 1: Automated Deployment (Recommended)

```bash
./deploy.sh
```

**What it does:**
- Installs Docker (if needed)
- Configures China mirrors automatically
- Creates .env file (or reuses existing)
- Sets up SSL certificates
- Starts all services

**When to use**: First-time deployment or full reconfiguration

---

### Option 2: Manual Docker Compose (Current Setup)

```bash
# Start all services including cert-updater
docker-compose --profile tencent-ssl up -d

# Wait for cert-updater to download certificates (~10 seconds)
docker logs -f wordpress-cert-updater

# Verify all services are running
docker ps
```

**Prerequisites:**
- .env file must exist with all required variables
- Docker and Docker Compose installed

**When to use**: Re-deploying on configured server

---

## Starting Fresh (Clean Slate)

If you want to completely reset and start from scratch:

```bash
# 1. Stop all services
docker-compose --profile tencent-ssl down

# 2. Remove all volumes (WARNING: Deletes all data!)
docker volume rm wordpress-clockapi_mysql_data \
                 wordpress-clockapi_wordpress_data \
                 wordpress-clockapi_clock_api_data

# 3. Remove certificates (will be re-downloaded)
rm -f certs/*.pem certs/*.csr

# 4. Start fresh
docker-compose --profile tencent-ssl up -d
```

**What will happen:**
1. MySQL will create fresh database
2. WordPress will show installation wizard
3. Clock API will create new SQLite database
4. cert-updater will download SSL certificates from Tencent Cloud
5. nginx will serve HTTPS with trusted certificates

---

## Common Scenarios

### Scenario 1: Server restart (after reboot)

```bash
# Services should auto-start (restart: always)
docker ps  # Verify all running

# If not, manually start:
docker-compose --profile tencent-ssl up -d
```

**Expected**: All services come up cleanly ✅

---

### Scenario 2: Re-deploy after code changes

```bash
# Rebuild and restart specific service
docker-compose build clock-api
docker-compose up -d clock-api

# Or rebuild everything
docker-compose --profile tencent-ssl build
docker-compose --profile tencent-ssl up -d
```

**Expected**: Works smoothly, data persists ✅

---

### Scenario 3: Fresh deployment on new server

```bash
# 1. Clone repository
git clone <repo-url>
cd WordPress-ClockAPI

# 2. Run deployment script
./deploy.sh

# 3. Follow prompts:
#    - Enter domain name
#    - Enter API keys
#    - Choose SSL option 3 (Tencent Cloud)
#    - Enter Tencent credentials

# 4. Complete WordPress setup
#    Visit: https://yourdomain.com
```

**Expected**: Fully automated, should work ✅

---

## Troubleshooting From Scratch

### Issue: nginx fails to start - "no such file or directory"

**Cause**: Missing SSL certificates

**Fix:**
```bash
# Option 1: Start cert-updater to download certificates
docker-compose --profile tencent-ssl up -d cert-updater
docker logs -f wordpress-cert-updater
# Wait for "✅ Nginx 重新加载成功"

# Option 2: Create temporary self-signed certificates
openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
  -keyout certs/privkey.pem \
  -out certs/fullchain.pem \
  -subj "/C=CN/ST=State/L=City/O=Org/CN=yourdomain.com"

# Then start nginx
docker-compose up -d nginx
```

---

### Issue: Clock API build hangs in China

**Cause**: Blocked or slow npm/node mirrors

**Status**: ✅ FIXED - Dockerfile now uses Chinese mirrors

**Verification:**
```bash
# Check Dockerfile has these lines:
grep "registry.npmmirror.com" clock-api/Dockerfile
grep "npmmirror.com/mirrors/node" clock-api/Dockerfile
```

Should see:
```
ENV npm_config_registry=https://registry.npmmirror.com
    NODEJS_ORG_MIRROR=https://npmmirror.com/mirrors/node
```

---

### Issue: cert-updater not starting

**Cause**: Forgot `--profile tencent-ssl` flag

**Fix:**
```bash
# Always use the profile flag
docker-compose --profile tencent-ssl up -d
```

---

### Issue: deploy.sh shows error about saidim.conf

**Error message:**
```
sed: can't read nginx/conf.d/saidim.conf: No such file or directory
```

**Impact**: Harmless warning, deployment continues

**Fix (optional):**
Edit deploy.sh and remove line 461:
```bash
# Remove this line:
sed -i "s/saidim\.com/$DOMAIN_NAME/g" nginx/conf.d/saidim.conf
```

---

## What Persists vs What Resets

### Persists (Docker volumes):
- ✅ MySQL database (wordpress_data)
- ✅ WordPress installation (wordpress_data)
- ✅ WordPress uploads (wordpress_data)
- ✅ Clock API SQLite database (clock_api_data)
- ✅ Clock API cached images (clock_api_data)

### Resets on container restart:
- ❌ In-memory cache (Clock API node-cache)
- ❌ Container logs (unless using log driver)

### Persists on host:
- ✅ SSL certificates (./certs/)
- ✅ nginx config (./nginx/conf.d/)
- ✅ Environment variables (.env)
- ✅ Custom plugins/themes (./wordpress-custom/)

---

## Expected Behavior From Scratch

### With existing .env and certs/:
```bash
docker-compose --profile tencent-ssl up -d
```
**Result:**
- All services start successfully ✅
- WordPress ready (needs initial setup if first time)
- Clock API healthy ✅
- HTTPS working with valid certificates ✅
- **Time to ready**: ~30 seconds

### Without certs/:
```bash
docker-compose --profile tencent-ssl up -d
```
**Result:**
- MySQL starts ✅
- WordPress starts ✅
- Clock API starts ✅
- nginx may fail initially ⚠️
- cert-updater downloads certificates (~10 seconds)
- nginx automatically reloads ✅
- **Time to ready**: ~45 seconds

### First-time WordPress:
- MySQL creates database
- WordPress shows 5-minute installation wizard
- After setup: Site is live
- Manual step: Install/activate plugins

---

## Validation Checklist

After starting from scratch, verify:

```bash
# 1. All containers running
docker ps
# Should show: mysql, wordpress, clock-api, nginx, cert-updater

# 2. MySQL healthy
docker exec wordpress-mysql mysqladmin ping -h localhost -u root -p<password>
# Should show: mysqld is alive

# 3. Clock API responsive
curl http://localhost:3000/api/v1/health
# Should return: {"status":"ok"}

# 4. HTTPS working
curl -I https://yourdomain.com
# Should return: HTTP/2 200 (or 302 redirect to WordPress setup)

# 5. SSL certificate valid
openssl s_client -connect yourdomain.com:443 -servername yourdomain.com < /dev/null 2>/dev/null | grep "Verify return code"
# Should return: Verify return code: 0 (ok)

# 6. cert-updater active
docker logs --tail 20 wordpress-cert-updater
# Should show: ✅ Nginx 重新加载成功
```

All checks pass = Deployment successful ✅

---

## TL;DR - Will It Work From Scratch?

**Short answer: YES ✅**

**Conditions for smooth operation:**
1. Use `--profile tencent-ssl` flag
2. .env file exists with proper credentials
3. Internet connectivity to Tencent Cloud
4. Docker and Docker Compose installed

**Recommended command:**
```bash
docker-compose --profile tencent-ssl up -d
```

**Expected outcome:**
- All services start within 45 seconds
- HTTPS working with valid TrustAsia certificate
- WordPress ready for initial setup
- Clock API healthy and responding

**Only manual step needed:**
- Complete WordPress installation wizard (first time only)
