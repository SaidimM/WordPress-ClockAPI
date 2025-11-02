# WordPress + Clock API Integration

A Docker-based WordPress installation integrated with a custom Node.js Clock API service for dynamic time-based image displays.

## 🏗️ Architecture

- **WordPress**: Content management system with custom clock plugin
- **Clock API**: Node.js service for time-based image management (Unsplash integration)
- **Nginx**: Reverse proxy for SSL termination and routing
- **MySQL**: Database for WordPress
- **SQLite**: Database for Clock API tracking
- **Docker Compose**: Container orchestration

## 📋 Prerequisites

- Ubuntu/Debian server (or similar Linux distribution)
- Docker & Docker Compose installed
- Domain name pointed to your server
- SSL certificates (Let's Encrypt recommended)

### 🇨🇳 Deploying in China?

If deploying on **Tencent Cloud** or other China mainland servers, see the **[China Deployment Guide (中文)](./DEPLOYMENT_CHINA.md)** for:
- Optimized Chinese mirrors (Docker, npm, WordPress)
- Tencent Cloud specific instructions
- Faster downloads and installation
- Chinese language support

## 🚀 Deployment Guide

### Quick Deploy (Automated - Recommended)

For a fully automated deployment, use the deployment script:

```bash
# Clone the repository
git clone https://github.com/SaidimM/WordPress-ClockAPI.git
cd WordPress-ClockAPI

# Run the automated deployment script
./deploy.sh
```

The script will:
- ✅ Install Docker & Docker Compose (if needed)
- ✅ Configure environment variables interactively
- ✅ Download WordPress core files
- ✅ Set up SSL certificates (Let's Encrypt or existing)
- ✅ Update nginx configuration with your domain
- ✅ Install Node.js dependencies
- ✅ Set proper file permissions
- ✅ Start all Docker services
- ✅ Verify deployment
- ✅ Save credentials securely

**That's it!** The script handles everything automatically.

---

### Manual Deployment (Step-by-Step)

If you prefer manual control, follow these steps:

#### 1. Clone the Repository

```bash
git clone https://github.com/SaidimM/WordPress-ClockAPI.git
cd WordPress-ClockAPI
```

#### 2. Install Docker & Docker Compose

If not already installed:

```bash
# Install Docker
curl -fsSL https://get.docker.com -o get-docker.sh
sudo sh get-docker.sh
sudo usermod -aG docker $USER

# Install Docker Compose
sudo curl -L "https://github.com/docker/compose/releases/latest/download/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
sudo chmod +x /usr/local/bin/docker-compose

# Verify installation
docker --version
docker-compose --version
```

Log out and back in for group changes to take effect.

#### 3. Configure Environment Variables

Create `.env` file in the root directory:

```bash
cat > .env << 'EOF'
# MySQL Configuration
MYSQL_ROOT_PASSWORD=your_secure_root_password
MYSQL_DATABASE=wordpress
MYSQL_USER=wordpress_user
MYSQL_PASSWORD=your_secure_wordpress_password

# WordPress Configuration
WORDPRESS_DB_HOST=mysql:3306
WORDPRESS_DB_NAME=wordpress
WORDPRESS_DB_USER=wordpress_user
WORDPRESS_DB_PASSWORD=your_secure_wordpress_password

# Clock API Configuration
UNSPLASH_ACCESS_KEY=your_unsplash_access_key
UNSPLASH_SECRET_KEY=your_unsplash_secret_key
CLOCK_API_PORT=3000
NODE_ENV=production

# API Security
API_SECRET_KEY=your_api_secret_key_here
EOF
```

**Important:** Replace all placeholder values with secure credentials!

#### 4. Download WordPress Core Files

Since WordPress core files are excluded from git, download them:

```bash
# Download WordPress
cd wordpress
wget https://wordpress.org/latest.tar.gz
tar -xzf latest.tar.gz --strip-components=1
rm latest.tar.gz

# Or use WP-CLI
curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
chmod +x wp-cli.phar
./wp-cli.phar core download --skip-content

cd ..
```

#### 5. Set Up SSL Certificates

Place your SSL certificates in the `certs/` directory:

```bash
mkdir -p certs
# Copy your SSL certificate and key
cp /path/to/your/fullchain.pem certs/
cp /path/to/your/privkey.pem certs/
```

**Using Let's Encrypt (Recommended):**

```bash
sudo apt install certbot
sudo certbot certonly --standalone -d yourdomain.com -d www.yourdomain.com
sudo cp /etc/letsencrypt/live/yourdomain.com/fullchain.pem certs/
sudo cp /etc/letsencrypt/live/yourdomain.com/privkey.pem certs/
sudo chown -R $USER:$USER certs/
```

#### 6. Update Nginx Configuration

Edit `nginx/conf.d/wordpress.conf` and `nginx/conf.d/saidim.conf`:

```bash
# Update server_name to your domain
sed -i 's/saidim\.com/yourdomain.com/g' nginx/conf.d/wordpress.conf
sed -i 's/saidim\.com/yourdomain.com/g' nginx/conf.d/saidim.conf
```

#### 7. Install Node.js Dependencies

```bash
cd clock-api
npm install
cd ..
```

#### 8. Set File Permissions

```bash
# Set ownership for WordPress files
sudo chown -R www-data:www-data wordpress/
sudo chmod -R 755 wordpress/

# Set ownership for Clock API data
sudo chown -R $USER:$USER clock-api/data/
sudo chmod -R 755 clock-api/data/
```

#### 9. Start Docker Services

```bash
# Start all services
docker-compose up -d

# Check status
docker-compose ps

# View logs
docker-compose logs -f
```

#### 10. Complete WordPress Installation

1. Visit `https://yourdomain.com`
2. Complete the WordPress installation wizard:
   - Choose language
   - Set site title
   - Create admin account
   - Configure settings

#### 11. Configure WordPress

1. **Activate Custom Clock Plugin:**
   - Go to `wp-admin` → Plugins
   - Activate "Custom Clock" plugin

2. **Create Clock Page:**
   - Create a new page
   - Add the shortcode: `[custom_clock]`
   - Publish the page

3. **Set Permalinks:**
   - Go to Settings → Permalinks
   - Choose "Post name" structure
   - Save changes

#### 12. Verify Services

```bash
# Check all containers are running
docker-compose ps

# Test Clock API
curl https://yourdomain.com/api/clock

# Test WordPress
curl https://yourdomain.com

# Check logs
docker-compose logs wordpress
docker-compose logs clock-api
docker-compose logs nginx
```

## 📁 Project Structure

```
.
├── clock-api/              # Node.js Clock API service
│   ├── src/               # Source code
│   ├── data/              # SQLite database & image cache
│   └── package.json
├── wordpress/             # WordPress installation
│   └── wp-content/        # Custom themes & plugins only
│       └── plugins/
│           └── custom-clock/  # Custom clock plugin
├── nginx/                 # Nginx configuration
│   └── conf.d/
├── certs/                 # SSL certificates (not in git)
├── mysql/                 # MySQL data (not in git)
├── docker-compose.yml     # Docker services definition
├── .env                   # Environment variables (not in git)
└── README.md
```

## 🔧 Maintenance

### Update Services

```bash
# Pull latest changes
git pull origin master

# Rebuild and restart
docker-compose down
docker-compose up -d --build
```

### Backup Database

```bash
# Backup WordPress database
docker-compose exec mysql mysqldump -u wordpress_user -p wordpress > backup_$(date +%Y%m%d).sql

# Backup Clock API database
cp clock-api/data/tracking.db backup_tracking_$(date +%Y%m%d).db
```

### View Logs

```bash
# All services
docker-compose logs -f

# Specific service
docker-compose logs -f wordpress
docker-compose logs -f clock-api
docker-compose logs -f nginx
```

### Restart Services

```bash
# Restart all
docker-compose restart

# Restart specific service
docker-compose restart wordpress
docker-compose restart clock-api
```

### Stop Services

```bash
# Stop all services
docker-compose down

# Stop and remove volumes (WARNING: deletes data)
docker-compose down -v
```

## 🔐 Security Notes

1. **Change default credentials** in `.env` file
2. **Use strong passwords** for database and WordPress admin
3. **Keep SSL certificates updated** (auto-renew with Let's Encrypt)
4. **Regularly update** WordPress core, themes, and plugins
5. **Enable WordPress security plugins** (WordFence, etc.)
6. **Configure firewall** (ufw, iptables) to restrict ports
7. **Regular backups** of database and files

## 🐛 Troubleshooting

### Containers won't start

```bash
# Check logs
docker-compose logs

# Check port conflicts
sudo netstat -tulpn | grep -E ':(80|443|3306)'

# Remove and recreate
docker-compose down
docker-compose up -d
```

### WordPress database connection error

1. Check `.env` file credentials match `docker-compose.yml`
2. Verify MySQL container is running: `docker-compose ps`
3. Check MySQL logs: `docker-compose logs mysql`

### Clock API not responding

```bash
# Check if container is running
docker-compose ps clock-api

# Check logs
docker-compose logs clock-api

# Restart service
docker-compose restart clock-api
```

### Permission issues

```bash
# Fix WordPress permissions
sudo chown -R www-data:www-data wordpress/
sudo chmod -R 755 wordpress/

# Fix Clock API permissions
sudo chown -R $USER:$USER clock-api/data/
```

### SSL certificate issues

```bash
# Verify certificate files exist
ls -la certs/

# Check nginx configuration
docker-compose exec nginx nginx -t

# Restart nginx
docker-compose restart nginx
```

## 📝 API Documentation

### Clock API Endpoints

- `GET /api/clock` - Get current clock image
- `GET /api/track` - Track image views
- `GET /health` - Health check

See `clock-api/README.md` for detailed API documentation.

## 📄 License

This project is for personal/educational use.

## 🤝 Contributing

For deployment issues or questions, please open an issue on GitHub.

---

**Generated with [Claude Code](https://claude.com/claude-code)**
