#!/bin/bash

#######################################
# WordPress + Clock API Deployment Script
# Automates deployment on fresh Ubuntu/Debian server
#######################################

set -e  # Exit on any error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Logging functions
log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

print_header() {
    echo -e "\n${BLUE}========================================${NC}"
    echo -e "${BLUE}$1${NC}"
    echo -e "${BLUE}========================================${NC}\n"
}

# Check if running as root
if [[ $EUID -eq 0 ]]; then
   log_error "This script should not be run as root. Run as a regular user with sudo privileges."
   exit 1
fi

print_header "WordPress + Clock API Deployment"

# Detect if running in China
detect_china_region() {
    # Check if we're in China by testing connectivity to common Chinese services
    if curl -s --connect-timeout 3 --max-time 5 https://www.baidu.com > /dev/null 2>&1; then
        if ! curl -s --connect-timeout 3 --max-time 5 https://www.google.com > /dev/null 2>&1; then
            return 0  # Likely in China
        fi
    fi
    return 1  # Not in China
}

# Interactive configuration
configure_deployment() {
    print_header "Step 1: Configuration"

    echo "Let's configure your deployment..."
    echo ""

    # Detect region
    if detect_china_region; then
        log_info "Detected China mainland region - will use Chinese mirrors"
        USE_CHINA_MIRRORS=true
    else
        read -p "Are you deploying in China mainland? (y/N): " CHINA_DEPLOY
        if [[ "$CHINA_DEPLOY" =~ ^[Yy]$ ]]; then
            USE_CHINA_MIRRORS=true
            log_info "Will use Chinese mirrors for faster downloads"
        else
            USE_CHINA_MIRRORS=false
        fi
    fi

    # Domain configuration
    read -p "Enter your domain name (e.g., example.com): " DOMAIN_NAME
    if [ -z "$DOMAIN_NAME" ]; then
        log_error "Domain name is required!"
        exit 1
    fi

    # Database credentials
    log_info "Configuring database credentials..."
    read -p "MySQL root password (leave empty for auto-generated): " MYSQL_ROOT_PASS
    if [ -z "$MYSQL_ROOT_PASS" ]; then
        MYSQL_ROOT_PASS=$(openssl rand -base64 24)
        log_info "Generated MySQL root password: $MYSQL_ROOT_PASS"
    fi

    read -p "WordPress database password (leave empty for auto-generated): " WP_DB_PASS
    if [ -z "$WP_DB_PASS" ]; then
        WP_DB_PASS=$(openssl rand -base64 24)
        log_info "Generated WordPress DB password: $WP_DB_PASS"
    fi

    # Unsplash API keys
    log_warning "You need Unsplash API credentials from https://unsplash.com/developers"
    read -p "Unsplash Access Key: " UNSPLASH_ACCESS
    read -p "Unsplash Secret Key: " UNSPLASH_SECRET

    # API secret
    read -p "API Secret Key (leave empty for auto-generated): " API_SECRET
    if [ -z "$API_SECRET" ]; then
        API_SECRET=$(openssl rand -base64 32)
        log_info "Generated API secret key"
    fi

    # SSL certificate option
    echo ""
    log_info "SSL Certificate options:"
    echo "1) Use existing certificates (I have .pem files)"
    echo "2) Generate with Let's Encrypt (Recommended - automatic & free)"
    echo "3) Use Tencent Cloud SSL (with cert-updater - auto-renewal)"
    echo "4) Skip SSL setup (configure manually later)"
    read -p "Choose option [1-4]: " SSL_OPTION

    if [ "$SSL_OPTION" = "1" ]; then
        read -p "Path to fullchain.pem: " CERT_PATH
        read -p "Path to privkey.pem: " KEY_PATH
    elif [ "$SSL_OPTION" = "3" ]; then
        log_info "Tencent Cloud SSL Certificate requires API credentials"
        log_info "Get them from: https://console.cloud.tencent.com/cam/capi"
        read -p "Tencent Cloud Secret ID: " TENCENT_SECRET_ID
        read -p "Tencent Cloud Secret Key: " TENCENT_SECRET_KEY
    fi

    log_success "Configuration complete!"
}

# Configure Docker mirrors for China
configure_docker_mirror() {
    if [ "$USE_CHINA_MIRRORS" = true ]; then
        log_info "Configuring Docker registry mirrors for China..."

        sudo mkdir -p /etc/docker

        cat | sudo tee /etc/docker/daemon.json > /dev/null <<EOF
{
  "registry-mirrors": [
    "https://docker.mirrors.ustc.edu.cn",
    "https://hub-mirror.c.163.com",
    "https://mirror.ccs.tencentyun.com"
  ],
  "log-driver": "json-file",
  "log-opts": {
    "max-size": "10m",
    "max-file": "3"
  }
}
EOF

        # Restart Docker to apply changes
        if command -v docker &> /dev/null; then
            sudo systemctl daemon-reload
            sudo systemctl restart docker
            log_success "Docker mirrors configured"
        fi
    fi
}

# Install Docker
install_docker() {
    print_header "Step 2: Installing Docker"

    if command -v docker &> /dev/null; then
        log_info "Docker is already installed"
        docker --version
    else
        log_info "Installing Docker..."

        if [ "$USE_CHINA_MIRRORS" = true ]; then
            # Use Tencent Cloud mirror for Docker installation in China
            log_info "Using Tencent Cloud mirror for Docker installation..."

            # Install prerequisites
            sudo apt-get update
            sudo apt-get install -y ca-certificates curl

            # Setup Docker GPG key (modern method)
            sudo install -m 0755 -d /etc/apt/keyrings
            sudo curl -fsSL https://mirrors.cloud.tencent.com/docker-ce/linux/ubuntu/gpg -o /etc/apt/keyrings/docker.asc
            sudo chmod a+r /etc/apt/keyrings/docker.asc

            # Add Tencent Cloud Docker repository
            echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] https://mirrors.cloud.tencent.com/docker-ce/linux/ubuntu/ \
  $(. /etc/os-release && echo "$VERSION_CODENAME") stable" | sudo tee /etc/apt/sources.list.d/docker.list > /dev/null

            sudo apt-get update
            sudo apt-get install -y docker-ce docker-ce-cli containerd.io
        else
            # Use official Docker installation script
            curl -fsSL https://get.docker.com -o get-docker.sh
            sudo sh get-docker.sh
            rm get-docker.sh
        fi

        sudo usermod -aG docker $USER
        log_success "Docker installed successfully"
    fi

    # Configure mirrors after installation
    configure_docker_mirror
}

# Install Docker Compose
install_docker_compose() {
    print_header "Step 3: Installing Docker Compose"

    if command -v docker-compose &> /dev/null; then
        log_info "Docker Compose is already installed"
        docker-compose --version
    else
        log_info "Installing Docker Compose..."

        if [ "$USE_CHINA_MIRRORS" = true ]; then
            # Use Tencent Cloud mirror for Docker Compose in China
            log_info "Using Tencent Cloud mirror for Docker Compose..."
            COMPOSE_VERSION="v2.24.0"
            sudo curl -L "https://mirrors.cloud.tencent.com/docker-toolbox/linux/compose/${COMPOSE_VERSION}/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose 2>/dev/null || \
            sudo curl -L "https://github.com/docker/compose/releases/download/${COMPOSE_VERSION}/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
        else
            # Use official GitHub release
            sudo curl -L "https://github.com/docker/compose/releases/latest/download/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
        fi

        sudo chmod +x /usr/local/bin/docker-compose
        log_success "Docker Compose installed successfully"
    fi
}

# Create .env file
create_env_file() {
    print_header "Step 4: Creating Environment Configuration"

    log_info "Creating .env file..."

    cat > .env << EOF
# MySQL Configuration
MYSQL_ROOT_PASSWORD=${MYSQL_ROOT_PASS}
MYSQL_DATABASE=wordpress
MYSQL_USER=wordpress_user
MYSQL_PASSWORD=${WP_DB_PASS}

# WordPress Configuration
WORDPRESS_DB_HOST=mysql:3306
WORDPRESS_DB_NAME=wordpress
WORDPRESS_DB_USER=wordpress_user
WORDPRESS_DB_PASSWORD=${WP_DB_PASS}

# Clock API Configuration
UNSPLASH_ACCESS_KEY=${UNSPLASH_ACCESS}
UNSPLASH_SECRET_KEY=${UNSPLASH_SECRET}
CLOCK_API_PORT=3000
NODE_ENV=production

# API Security
API_SECRET_KEY=${API_SECRET}
EOF

    # Add Tencent Cloud credentials if using cert-updater (Option 3)
    if [ "$SSL_OPTION" = "3" ]; then
        cat >> .env << EOF

# Tencent Cloud SSL Certificate (for cert-updater)
TENCENT_SECRET_ID=${TENCENT_SECRET_ID}
TENCENT_SECRET_KEY=${TENCENT_SECRET_KEY}
CERT_CHECK_INTERVAL_DAYS=30
CERT_UPDATE_THRESHOLD_DAYS=60
EOF
    fi

    chmod 600 .env
    log_success ".env file created"
}

# Download WordPress
download_wordpress() {
    print_header "Step 5: Downloading WordPress Core"

    if [ -f "wordpress/wp-load.php" ]; then
        log_warning "WordPress core files already exist, skipping download"
        return
    fi

    log_info "Downloading WordPress..."
    cd wordpress

    # Try WP-CLI first
    if command -v wp &> /dev/null; then
        wp core download --skip-content
    else
        # Fallback to direct download
        if [ "$USE_CHINA_MIRRORS" = true ]; then
            log_info "Using Chinese mirror for WordPress..."
            wget -q https://cn.wordpress.org/latest-zh_CN.tar.gz -O wordpress.tar.gz
        else
            wget -q https://wordpress.org/latest.tar.gz -O wordpress.tar.gz
        fi

        tar -xzf wordpress.tar.gz --strip-components=1
        rm wordpress.tar.gz
    fi

    cd ..
    log_success "WordPress downloaded"
}

# Setup SSL certificates
setup_ssl() {
    print_header "Step 6: Setting Up SSL Certificates"

    mkdir -p certs

    if [ "$SSL_OPTION" = "1" ]; then
        log_info "Copying existing certificates..."
        sudo cp "$CERT_PATH" certs/fullchain.pem
        sudo cp "$KEY_PATH" certs/privkey.pem
        sudo chown $USER:$USER certs/*.pem
        log_success "Certificates copied"

    elif [ "$SSL_OPTION" = "2" ]; then
        log_info "Setting up Let's Encrypt..."

        # Install certbot
        if ! command -v certbot &> /dev/null; then
            log_info "Installing certbot..."
            sudo apt update
            sudo apt install -y certbot
        fi

        # Stop any service on port 80/443
        sudo systemctl stop nginx 2>/dev/null || true
        sudo systemctl stop apache2 2>/dev/null || true
        docker-compose down 2>/dev/null || true

        log_info "Obtaining certificate for $DOMAIN_NAME..."
        sudo certbot certonly --standalone -d $DOMAIN_NAME -d www.$DOMAIN_NAME --non-interactive --agree-tos -m admin@$DOMAIN_NAME

        # Copy certificates
        sudo cp /etc/letsencrypt/live/$DOMAIN_NAME/fullchain.pem certs/
        sudo cp /etc/letsencrypt/live/$DOMAIN_NAME/privkey.pem certs/
        sudo chown $USER:$USER certs/*.pem

        log_success "Let's Encrypt certificates obtained"

    elif [ "$SSL_OPTION" = "3" ]; then
        log_info "Setting up Tencent Cloud SSL with cert-updater..."

        # Create self-signed certificate temporarily
        # cert-updater will replace it with real certificate on first run
        log_info "Creating temporary self-signed certificate..."
        openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
            -keyout certs/privkey.pem \
            -out certs/fullchain.pem \
            -subj "/C=CN/ST=State/L=City/O=Organization/CN=$DOMAIN_NAME"

        log_success "Temporary certificate created"
        log_info "cert-updater will automatically download and install Tencent Cloud SSL certificate"

    else
        log_warning "SSL setup skipped. You'll need to configure certificates manually."
        # Create self-signed certificate for testing
        log_info "Creating self-signed certificate for testing..."
        openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
            -keyout certs/privkey.pem \
            -out certs/fullchain.pem \
            -subj "/C=US/ST=State/L=City/O=Organization/CN=$DOMAIN_NAME"
        log_warning "Self-signed certificate created. Replace with proper SSL certificate for production!"
    fi
}

# Update nginx configuration
update_nginx_config() {
    print_header "Step 7: Updating Nginx Configuration"

    log_info "Updating domain in nginx configuration..."

    # Update WordPress nginx config
    sed -i "s/saidim\.com/$DOMAIN_NAME/g" nginx/conf.d/wordpress.conf

    # Update Clock API nginx config
    sed -i "s/saidim\.com/$DOMAIN_NAME/g" nginx/conf.d/saidim.conf

    # Update cert-updater domain in docker-compose.yml
    sed -i "s/DOMAIN: \"saidim\.com\"/DOMAIN: \"$DOMAIN_NAME\"/g" docker-compose.yml

    log_success "Nginx configuration updated"
}

# Configure npm mirror for China
configure_npm_mirror() {
    if [ "$USE_CHINA_MIRRORS" = true ]; then
        log_info "Configuring npm to use Taobao mirror..."
        npm config set registry https://registry.npmmirror.com
        log_success "npm mirror configured"
    fi
}

# Install Node.js dependencies
install_node_dependencies() {
    print_header "Step 8: Installing Node.js Dependencies"

    # Check if Node.js is installed
    if ! command -v node &> /dev/null; then
        log_warning "Node.js is not installed. Installing Node.js..."

        if [ "$USE_CHINA_MIRRORS" = true ]; then
            # Use Tencent mirror for Node.js in China
            log_info "Using Tencent mirror for Node.js..."
            curl -fsSL https://mirrors.cloud.tencent.com/nodejs-release/v18.19.0/node-v18.19.0-linux-x64.tar.xz -o node.tar.xz
            sudo tar -xJf node.tar.xz -C /usr/local --strip-components=1
            rm node.tar.xz
        else
            # Use NodeSource for standard installation
            curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
            sudo apt-get install -y nodejs
        fi

        log_success "Node.js installed successfully"
    fi

    # Configure npm mirror
    configure_npm_mirror

    if [ ! -d "clock-api/node_modules" ]; then
        log_info "Installing npm packages..."
        cd clock-api
        npm install --production
        cd ..
        log_success "Node.js dependencies installed"
    else
        log_warning "Node modules already exist, skipping installation"
    fi
}

# Set permissions
set_permissions() {
    print_header "Step 9: Setting File Permissions"

    log_info "Setting permissions..."

    # WordPress permissions
    sudo chown -R www-data:www-data wordpress/ 2>/dev/null || chown -R 33:33 wordpress/
    sudo chmod -R 755 wordpress/

    # Clock API permissions
    mkdir -p clock-api/data/images
    chown -R $USER:$USER clock-api/data/
    chmod -R 755 clock-api/data/

    log_success "Permissions set"
}

# Start Docker services
start_services() {
    print_header "Step 10: Starting Docker Services"

    log_info "Starting all services with Docker Compose..."
    docker-compose down 2>/dev/null || true
    docker-compose up -d

    log_info "Waiting for services to be ready..."
    sleep 10

    log_success "All services started"
}

# Verify deployment
verify_deployment() {
    print_header "Step 11: Verifying Deployment"

    log_info "Checking container status..."
    docker-compose ps

    echo ""
    log_info "Checking service health..."

    # Check MySQL
    if docker-compose exec -T mysql mysqladmin ping -h localhost -u root -p$MYSQL_ROOT_PASS 2>/dev/null | grep -q "alive"; then
        log_success "MySQL is running"
    else
        log_warning "MySQL might not be ready yet"
    fi

    # Check Clock API
    sleep 5
    if curl -s http://localhost:3000/health > /dev/null 2>&1; then
        log_success "Clock API is running"
    else
        log_warning "Clock API might not be ready yet"
    fi

    # Check Nginx
    if docker-compose exec -T nginx nginx -t > /dev/null 2>&1; then
        log_success "Nginx configuration is valid"
    else
        log_warning "Nginx configuration might have issues"
    fi
}

# Save credentials
save_credentials() {
    print_header "Step 12: Saving Credentials"

    CREDS_FILE="deployment-credentials.txt"

    cat > $CREDS_FILE << EOF
========================================
DEPLOYMENT CREDENTIALS
========================================
Generated: $(date)

Domain: $DOMAIN_NAME
URLs:
  - WordPress: https://$DOMAIN_NAME
  - WordPress Admin: https://$DOMAIN_NAME/wp-admin
  - Clock API: https://$DOMAIN_NAME/api/clock

Database:
  - MySQL Root Password: $MYSQL_ROOT_PASS
  - WordPress DB User: wordpress_user
  - WordPress DB Password: $WP_DB_PASS
  - WordPress DB Name: wordpress

API Keys:
  - Unsplash Access Key: $UNSPLASH_ACCESS
  - Unsplash Secret Key: $UNSPLASH_SECRET
  - API Secret Key: $API_SECRET
EOF

    # Add Tencent Cloud credentials if Option 3 was selected
    if [ "$SSL_OPTION" = "3" ]; then
        cat >> $CREDS_FILE << EOF

Tencent Cloud SSL:
  - Secret ID: $TENCENT_SECRET_ID
  - Secret Key: $TENCENT_SECRET_KEY
  - cert-updater will auto-renew certificates every 30 days
EOF
    fi

    cat >> $CREDS_FILE << EOF

Docker Commands:
  - View logs: docker-compose logs -f
  - Restart services: docker-compose restart
  - Stop services: docker-compose down
  - Start services: docker-compose up -d

IMPORTANT: Store these credentials securely and delete this file!
========================================
EOF

    chmod 600 $CREDS_FILE
    log_success "Credentials saved to $CREDS_FILE"
}

# Final instructions
print_final_instructions() {
    print_header "Deployment Complete!"

    echo -e "${GREEN}✓ All services are running!${NC}"
    echo ""
    echo "Next steps:"
    echo ""
    echo "1. Complete WordPress installation:"
    echo "   Visit: https://$DOMAIN_NAME"
    echo ""
    echo "2. WordPress Admin:"
    echo "   URL: https://$DOMAIN_NAME/wp-admin"
    echo ""
    echo "3. Activate the Custom Clock plugin:"
    echo "   Go to Plugins → Activate 'Custom Clock'"
    echo ""
    echo "4. Create a page with clock shortcode:"
    echo "   Add shortcode: [custom_clock]"
    echo ""
    echo "5. Test Clock API:"
    echo "   curl https://$DOMAIN_NAME/api/clock"
    echo ""
    echo "6. View logs:"
    echo "   docker-compose logs -f"
    echo ""
    echo -e "${YELLOW}SECURITY REMINDERS:${NC}"
    echo "- Change WordPress admin password after first login"
    echo "- Review and secure deployment-credentials.txt"
    echo "- Set up automatic SSL renewal if using Let's Encrypt"
    echo "- Configure firewall (ufw) if not already done"
    echo ""
    echo -e "${GREEN}Credentials saved in: deployment-credentials.txt${NC}"
    echo ""

    if [ "$SSL_OPTION" = "2" ]; then
        echo -e "${BLUE}SSL Certificate Auto-Renewal:${NC}"
        echo "Add this to crontab for automatic renewal:"
        echo "0 0 * * * certbot renew --quiet && docker-compose restart nginx"
        echo ""
    elif [ "$SSL_OPTION" = "3" ]; then
        echo -e "${BLUE}Tencent Cloud SSL Certificate:${NC}"
        echo "cert-updater service will automatically:"
        echo "- Check for certificate updates every 30 days"
        echo "- Download and install new certificates from Tencent Cloud"
        echo "- Reload nginx automatically"
        echo "Monitor cert-updater logs: docker-compose logs -f cert-updater"
        echo ""
    fi
}

# Main deployment flow
main() {
    configure_deployment
    install_docker
    install_docker_compose
    create_env_file
    download_wordpress
    setup_ssl
    update_nginx_config
    install_node_dependencies
    set_permissions
    start_services
    verify_deployment
    save_credentials
    print_final_instructions

    log_success "Deployment completed successfully!"
}

# Run main function
main
