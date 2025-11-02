-- Image Views Table
CREATE TABLE IF NOT EXISTS image_views (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    image_id TEXT NOT NULL,
    photographer TEXT,
    photographer_url TEXT,
    user_agent TEXT,
    ip_address TEXT,
    platform TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_views_image_id ON image_views(image_id);
CREATE INDEX IF NOT EXISTS idx_views_created_at ON image_views(created_at);
CREATE INDEX IF NOT EXISTS idx_views_platform ON image_views(platform);

-- Image Downloads Table
CREATE TABLE IF NOT EXISTS image_downloads (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    image_id TEXT NOT NULL,
    photographer TEXT,
    photographer_url TEXT,
    user_agent TEXT,
    ip_address TEXT,
    platform TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_downloads_image_id ON image_downloads(image_id);
CREATE INDEX IF NOT EXISTS idx_downloads_created_at ON image_downloads(created_at);
CREATE INDEX IF NOT EXISTS idx_downloads_platform ON image_downloads(platform);

-- API Usage Statistics
CREATE TABLE IF NOT EXISTS api_usage (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    endpoint TEXT NOT NULL,
    method TEXT NOT NULL,
    status_code INTEGER,
    response_time_ms INTEGER,
    ip_address TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_usage_endpoint ON api_usage(endpoint);
CREATE INDEX IF NOT EXISTS idx_usage_created_at ON api_usage(created_at);

-- Cached Images Table (for local image storage)
CREATE TABLE IF NOT EXISTS cached_images (
    id TEXT PRIMARY KEY,
    filename TEXT NOT NULL,
    file_size INTEGER,
    file_path TEXT NOT NULL,
    quality TEXT DEFAULT 'raw',
    photographer TEXT,
    photographer_url TEXT,
    unsplash_url TEXT,
    unsplash_id TEXT,
    width INTEGER,
    height INTEGER,
    color TEXT,
    description TEXT,
    download_url TEXT,
    download_location TEXT,
    cached_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    view_count INTEGER DEFAULT 0,
    last_viewed_at DATETIME
);

CREATE INDEX IF NOT EXISTS idx_cached_at ON cached_images(cached_at DESC);
CREATE INDEX IF NOT EXISTS idx_view_count ON cached_images(view_count DESC);
CREATE INDEX IF NOT EXISTS idx_file_size ON cached_images(file_size);
