# Multi-stage build for smaller image size
FROM node:18-alpine AS builder

WORKDIR /app

# Use Chinese mirrors for Alpine packages (faster in China)
RUN sed -i 's/dl-cdn.alpinelinux.org/mirrors.cloud.tencent.com/g' /etc/apk/repositories

# Install build dependencies for native modules (better-sqlite3)
# These are only needed during build, not in final image
RUN apk add --no-cache \
    python3 \
    make \
    g++ && \
    npm install -g node-gyp

# Copy package files
COPY package*.json ./

# Configure npm for China + node-gyp to use Chinese mirror for Node headers
ENV npm_config_registry=https://registry.npmmirror.com \
    NODEJS_ORG_MIRROR=https://npmmirror.com/mirrors/node \
    NVM_NODEJS_ORG_MIRROR=https://npmmirror.com/mirrors/node

# Download all dependencies without running install scripts (skip package-lock for speed in China)
RUN npm install --production --ignore-scripts --no-package-lock --loglevel=error

# Manually build better-sqlite3 using node-gyp with Chinese mirror
RUN cd node_modules/better-sqlite3 && \
    node-gyp rebuild --dist-url=https://npmmirror.com/mirrors/node

# Production stage
FROM node:18-alpine

WORKDIR /app

# Install dumb-init for proper signal handling
RUN apk add --no-cache dumb-init

# Create non-root user
RUN addgroup -g 1001 -S nodejs && \
    adduser -S nodejs -u 1001

# Copy dependencies from builder
COPY --from=builder /app/node_modules ./node_modules

# Copy application code
COPY --chown=nodejs:nodejs . .

# Create data directory for SQLite database
RUN mkdir -p /app/data && chown nodejs:nodejs /app/data

# Switch to non-root user
USER nodejs

# Expose port
EXPOSE 3000

# Health check
HEALTHCHECK --interval=30s --timeout=3s --start-period=40s --retries=3 \
  CMD node -e "require('http').get('http://localhost:3000/api/v1/health', (r) => {process.exit(r.statusCode === 200 ? 0 : 1)})"

# Use dumb-init to handle signals properly
ENTRYPOINT ["dumb-init", "--"]

# Start application
CMD ["node", "src/server.js"]
