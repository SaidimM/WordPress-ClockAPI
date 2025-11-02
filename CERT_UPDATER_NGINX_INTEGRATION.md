# SSL Certificate Auto-Renewal Integration Guide

This document describes the integration between the cert-updater service and nginx for automatic SSL certificate management using Tencent Cloud SSL certificates.

## Overview

The cert-updater service automatically downloads and renews SSL certificates from Tencent Cloud, manages certificate files, and reloads nginx to apply updated certificates without manual intervention.

## Architecture

```
Tencent Cloud SSL Service
         ↓
   cert-updater (Python script)
         ↓
   Certificate Files (/certs/)
         ↓
   nginx (mounts /certs/ as read-only)
         ↓
   HTTPS Traffic (saidim.com)
```

## Components

### 1. cert-updater Service

**Container**: `wordpress-cert-updater`
**Image**: Custom build from `./cert-updater/Dockerfile`
**Profile**: `tencent-ssl` (optional service)

**Purpose**:
- Periodically checks certificate age
- Downloads latest certificates from Tencent Cloud when needed
- Reloads nginx container to apply new certificates
- Runs as a background daemon

**Key Files**:
- `/cert-updater/update_cert.py` - Main Python script
- `/cert-updater/requirements.txt` - Dependencies (tencentcloud-sdk-python, requests, schedule)
- `/cert-updater/Dockerfile` - Container definition

### 2. Certificate Storage

**Host Path**: `/home/ubuntu/WordPress-ClockAPI/certs/`
**Container Mount**: `/certs/` (cert-updater), `/etc/ssl/certs/` (nginx)

**Certificate Files**:
```
certs/
├── fullchain.pem      # Full certificate chain (required by nginx)
├── privkey.pem        # Private key (required by nginx)
├── saidim.com.csr     # Certificate signing request (downloaded from Tencent)
└── saidim.com.pem     # Certificate file (downloaded from Tencent)
```

**File Naming Convention**:
- cert-updater downloads: `{domain}.pem`, `{domain}.csr`
- nginx expects: `fullchain.pem`, `privkey.pem`
- cert-updater copies `{domain}.pem` → `fullchain.pem` automatically

## Configuration

### docker-compose.yml Configuration

```yaml
services:
  nginx:
    image: nginx:latest
    container_name: wordpress-nginx
    volumes:
      - ./certs:/etc/ssl/certs:ro  # Read-only mount
    ports:
      - "80:80"
      - "443:443"
    networks:
      - wordpress-network

  cert-updater:
    build: ./cert-updater
    container_name: wordpress-cert-updater
    restart: always
    environment:
      TENCENT_SECRET_ID: ${TENCENT_SECRET_ID:-}
      TENCENT_SECRET_KEY: ${TENCENT_SECRET_KEY:-}
      DOMAIN: ${DOMAIN_NAME:-example.com}
      SSL_DEST_DIR: "/certs"
      CERT_CHECK_INTERVAL_DAYS: ${CERT_CHECK_INTERVAL_DAYS:-30}
      CERT_UPDATE_THRESHOLD_DAYS: ${CERT_UPDATE_THRESHOLD_DAYS:-60}
    volumes:
      - ./certs:/certs  # Read-write for certificate updates
      - /var/run/docker.sock:/var/run/docker.sock:ro  # Docker socket for nginx reload
    depends_on:
      - nginx
    networks:
      - wordpress-network
    profiles:
      - tencent-ssl  # Optional service
```

### nginx Configuration

**File**: `/home/ubuntu/WordPress-ClockAPI/nginx/conf.d/wordpress.conf`

```nginx
# HTTPS server
server {
    listen 443 ssl;
    http2 on;
    server_name saidim.com www.saidim.com;

    # SSL configuration (using cert-updater managed certificates)
    ssl_certificate /etc/ssl/certs/fullchain.pem;
    ssl_certificate_key /etc/ssl/certs/privkey.pem;

    # SSL optimization
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_prefer_server_ciphers on;
    ssl_ciphers 'ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384:ECDHE-ECDSA-CHACHA20-POLY1305:ECDHE-RSA-CHACHA20-POLY1305:ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256';
    ssl_session_cache shared:SSL:10m;
    ssl_session_timeout 10m;

    # Security headers
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
    add_header X-Frame-Options SAMEORIGIN always;
    add_header X-Content-Type-Options nosniff always;
    add_header X-XSS-Protection "1; mode=block" always;

    # ... rest of configuration
}
```

**Critical Requirements**:
- Use `fullchain.pem` not `{domain}_bundle.crt`
- Use `privkey.pem` not `{domain}.key`
- Mount path must be `/etc/ssl/certs/` in nginx container
- Mount as read-only (`:ro`) for security

## Environment Variables

**Required**:
- `TENCENT_SECRET_ID` - Tencent Cloud API Secret ID
- `TENCENT_SECRET_KEY` - Tencent Cloud API Secret Key
- `DOMAIN_NAME` - Domain name (e.g., saidim.com)

**Optional**:
- `CERT_CHECK_INTERVAL_DAYS` - How often to check certificate age (default: 30 days)
- `CERT_UPDATE_THRESHOLD_DAYS` - Update if certificate older than this (default: 60 days)

**Configuration in .env**:
```bash
TENCENT_SECRET_ID=your_secret_id_here
TENCENT_SECRET_KEY=your_secret_key_here
DOMAIN_NAME=saidim.com
CERT_CHECK_INTERVAL_DAYS=30
CERT_UPDATE_THRESHOLD_DAYS=60
```

## How It Works

### Automatic Renewal Workflow

1. **Initialization** (on container start):
   ```
   cert-updater starts
   ↓
   Immediate certificate check
   ↓
   Download if needed
   ↓
   Reload nginx
   ↓
   Enter scheduled loop
   ```

2. **Scheduled Checks** (every CERT_CHECK_INTERVAL_DAYS):
   ```
   Check certificate age
   ↓
   If age > CERT_UPDATE_THRESHOLD_DAYS:
     ↓
     Query Tencent Cloud SSL API
     ↓
     Find latest certificate for domain
     ↓
     Download certificate files
     ↓
     Copy {domain}.pem → fullchain.pem
     ↓
     Copy {domain}.csr → privkey.pem
     ↓
     Reload nginx container via Docker API
     ↓
     Log success
   ```

3. **nginx Reload**:
   ```python
   docker_client = docker.from_env()
   nginx_container = docker_client.containers.get('wordpress-nginx')
   nginx_container.exec_run("nginx -s reload")
   ```

### Certificate Validation

**Check certificate details**:
```bash
# View certificate information
openssl x509 -in certs/fullchain.pem -noout -text

# Check issuer
openssl x509 -in certs/fullchain.pem -noout -issuer
# Output: issuer=C = CN, O = TrustAsia Technologies, Inc., CN = TrustAsia DV TLS RSA CA 2025

# Check expiration date
openssl x509 -in certs/fullchain.pem -noout -dates
# Output: notAfter=Dec 28 23:59:59 2025 GMT

# Check certificate age (days until expiration)
openssl x509 -in certs/fullchain.pem -noout -enddate | cut -d= -f2 | xargs -I{} date -d {} +%s | awk -v now=$(date +%s) '{print int(($1-now)/86400)}'
```

## Deployment

### Starting cert-updater

**With docker-compose profiles**:
```bash
# Start all services including cert-updater
docker-compose --profile tencent-ssl up -d

# Or start cert-updater specifically
docker-compose --profile tencent-ssl up -d cert-updater
```

**Verify it's running**:
```bash
docker ps | grep cert-updater
# Output: wordpress-cert-updater   Up 2 minutes
```

**Check logs**:
```bash
docker logs -f wordpress-cert-updater
```

**Expected log output**:
```
2025-11-02 16:26:08,123 - INFO - 开始检查证书...
2025-11-02 16:26:08,456 - INFO - 当前证书年龄: 3 天
2025-11-02 16:26:08,789 - INFO - 证书年龄未超过 60 天,跳过更新
2025-11-02 16:26:10,428 - INFO - 找到最新证书 ID: Ro2LyEke, 域名: saidim.com
2025-11-02 16:26:10,654 - INFO - 复制证书: saidim.com.pem -> fullchain.pem
2025-11-02 16:26:10,741 - INFO - ✅ Nginx 重新加载成功
```

### Manual Certificate Update

If you need to force a certificate update immediately:

```bash
# Method 1: Remove existing certificates to trigger download
rm -f certs/fullchain.pem certs/privkey.pem
docker restart wordpress-cert-updater

# Method 2: Execute update script directly
docker exec wordpress-cert-updater python /app/update_cert.py

# Method 3: Restart cert-updater (runs check on startup)
docker restart wordpress-cert-updater
```

## Troubleshooting

### Issue 1: nginx shows ERR_CERT_AUTHORITY_INVALID

**Symptoms**:
- Browser shows "Your connection is not private"
- Certificate error in browser

**Diagnosis**:
```bash
# Check if nginx is using the correct certificate files
docker exec wordpress-nginx ls -la /etc/ssl/certs/
# Should show: fullchain.pem, privkey.pem

# Verify certificate issuer
openssl x509 -in certs/fullchain.pem -noout -issuer
# Should show: TrustAsia or another trusted CA, NOT self-signed
```

**Solutions**:
1. Verify nginx configuration uses correct paths:
   ```nginx
   ssl_certificate /etc/ssl/certs/fullchain.pem;
   ssl_certificate_key /etc/ssl/certs/privkey.pem;
   ```

2. Remove self-signed certificates if present:
   ```bash
   # Check for self-signed certs
   openssl x509 -in certs/fullchain.pem -noout -issuer
   # If issuer == subject (self-signed), remove and restart
   rm -f certs/fullchain.pem certs/privkey.pem
   docker restart wordpress-cert-updater
   ```

3. Force certificate download:
   ```bash
   docker restart wordpress-cert-updater
   docker logs -f wordpress-cert-updater
   ```

### Issue 2: cert-updater not downloading certificates

**Symptoms**:
- cert-updater logs show "证书年龄未超过 X 天,跳过更新"
- No new certificates downloaded

**Diagnosis**:
```bash
# Check certificate age
openssl x509 -in certs/fullchain.pem -noout -enddate

# Check cert-updater threshold
docker exec wordpress-cert-updater env | grep THRESHOLD
```

**Solutions**:
1. If certificate exists but is wrong (e.g., self-signed), remove it:
   ```bash
   rm -f certs/fullchain.pem certs/privkey.pem
   docker restart wordpress-cert-updater
   ```

2. Lower the threshold temporarily:
   ```bash
   # Edit docker-compose.yml
   CERT_UPDATE_THRESHOLD_DAYS: 0

   docker-compose --profile tencent-ssl up -d cert-updater
   ```

3. Check Tencent Cloud credentials:
   ```bash
   docker exec wordpress-cert-updater env | grep TENCENT
   # Verify TENCENT_SECRET_ID and TENCENT_SECRET_KEY are set
   ```

### Issue 3: nginx reload fails

**Symptoms**:
- cert-updater logs show error reloading nginx
- nginx container not responding to reload command

**Diagnosis**:
```bash
# Test nginx configuration
docker exec wordpress-nginx nginx -t

# Check nginx container status
docker ps | grep nginx

# Check nginx logs
docker logs wordpress-nginx
```

**Solutions**:
1. Fix nginx configuration errors:
   ```bash
   # Test configuration
   docker exec wordpress-nginx nginx -t
   # Fix any errors reported
   ```

2. Verify cert-updater has Docker socket access:
   ```bash
   # Check mount in docker-compose.yml
   volumes:
     - /var/run/docker.sock:/var/run/docker.sock:ro
   ```

3. Manual nginx reload:
   ```bash
   docker exec wordpress-nginx nginx -s reload
   ```

### Issue 4: Permission errors

**Symptoms**:
- "Permission denied" errors in cert-updater logs
- Cannot write to /certs/ directory

**Solutions**:
1. Check directory permissions:
   ```bash
   ls -la certs/
   # Should be readable/writable
   ```

2. Fix permissions:
   ```bash
   chmod 755 certs/
   chmod 644 certs/*.pem
   ```

3. Verify volume mount:
   ```bash
   docker inspect wordpress-cert-updater | grep -A5 Mounts
   # Should show ./certs:/certs (read-write)
   ```

## Security Considerations

1. **Read-only mounts for nginx**:
   - nginx mounts certs as read-only (`:ro`)
   - Only cert-updater has write access
   - Prevents accidental modification

2. **Docker socket access**:
   - cert-updater needs Docker socket to reload nginx
   - Mounted as read-only (`:ro`)
   - Limited to container reload operations

3. **Credential management**:
   - Store Tencent Cloud credentials in `.env` file
   - Add `.env` to `.gitignore`
   - Never commit credentials to version control

4. **Certificate files**:
   - Private keys stored with restricted permissions (644)
   - No public access to /certs/ directory
   - Regular automatic renewal reduces exposure window

## Monitoring

### Check Certificate Status

```bash
# Quick status check
openssl x509 -in certs/fullchain.pem -noout -issuer -dates

# Days until expiration
echo $(( ($(date -d "$(openssl x509 -in certs/fullchain.pem -noout -enddate | cut -d= -f2)" +%s) - $(date +%s)) / 86400 )) days

# Full certificate details
openssl x509 -in certs/fullchain.pem -noout -text
```

### Monitor cert-updater Logs

```bash
# Follow logs in real-time
docker logs -f wordpress-cert-updater

# Last 50 lines
docker logs --tail 50 wordpress-cert-updater

# Logs with timestamps
docker logs -t wordpress-cert-updater
```

### Verify HTTPS Service

```bash
# Test HTTPS connection
curl -sI https://saidim.com

# Check SSL/TLS details
openssl s_client -connect saidim.com:443 -servername saidim.com < /dev/null

# Verify HTTP/2 support
curl -sI --http2 https://saidim.com | grep "HTTP/2"
```

## Backup and Recovery

### Backup Certificates

```bash
# Create backup
tar -czf certs-backup-$(date +%Y%m%d).tar.gz certs/

# Restore from backup
tar -xzf certs-backup-20251102.tar.gz
docker restart wordpress-nginx
```

### Recovery Procedure

If certificates are lost or corrupted:

1. Stop nginx and cert-updater:
   ```bash
   docker stop wordpress-nginx wordpress-cert-updater
   ```

2. Clean certificate directory:
   ```bash
   rm -f certs/*.pem certs/*.csr
   ```

3. Restart cert-updater (will download fresh certificates):
   ```bash
   docker start wordpress-cert-updater
   docker logs -f wordpress-cert-updater
   # Wait for "✅ Nginx 重新加载成功"
   ```

4. Start nginx:
   ```bash
   docker start wordpress-nginx
   ```

## Reference

### Certificate Authority Information

**Current CA**: TrustAsia DV TLS RSA CA 2025
- Issuer: TrustAsia Technologies, Inc. (China)
- Root: DigiCert Global Root G2
- Validation: Domain Validation (DV)
- Supported: All major browsers

### File Paths Reference

| Component | Host Path | Container Path | Permission |
|-----------|-----------|----------------|------------|
| cert-updater | `./certs` | `/certs` | rw |
| nginx | `./certs` | `/etc/ssl/certs` | ro |
| nginx config | `./nginx/conf.d` | `/etc/nginx/conf.d` | ro |

### API References

- [Tencent Cloud SSL Certificates API](https://cloud.tencent.com/document/product/400/41700)
- [Docker Python SDK](https://docker-py.readthedocs.io/)
- [OpenSSL x509 Command](https://www.openssl.org/docs/man1.1.1/man1/x509.html)

---

**Document Version**: 1.0
**Last Updated**: 2025-11-03
**Maintainer**: WordPress-ClockAPI Project
