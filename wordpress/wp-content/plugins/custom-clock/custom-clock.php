<?php
/**
 * Plugin Name: Professional World Clock
 * Plugin URI: https://saidim.com
 * Description: A professional, feature-rich clock page with timezone support, UTC time, and real-time statistics
 * Version: 2.0.0
 * Author: Saidim.com
 * Author URI: https://saidim.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: professional-world-clock
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access not permitted.');
}

/**
 * Rate Limiter class for API protection
 */
class PWC_Rate_Limiter {
    /**
     * Transient prefix for rate limit storage
     */
    const TRANSIENT_PREFIX = 'pwc_rate_limit_';

    /**
     * Check if IP has exceeded rate limit
     *
     * @param string $ip Client IP address
     * @param int $max_requests Maximum requests allowed
     * @param int $window_seconds Time window in seconds
     * @param string $identifier Unique identifier for different endpoints
     * @return array ['allowed' => bool, 'remaining' => int, 'reset' => int]
     */
    public static function check($ip, $max_requests = 60, $window_seconds = 60, $identifier = 'general') {
        $key = self::TRANSIENT_PREFIX . $identifier . '_' . md5($ip);
        $data = get_transient($key);

        $current_time = time();

        // Initialize or reset if window expired
        if ($data === false || !isset($data['reset']) || $current_time >= $data['reset']) {
            $data = array(
                'count' => 0,
                'reset' => $current_time + $window_seconds
            );
        }

        // Increment counter
        $data['count']++;

        // Calculate remaining
        $remaining = max(0, $max_requests - $data['count']);
        $allowed = $data['count'] <= $max_requests;

        // Save to transient (expires when window resets)
        $expiration = $data['reset'] - $current_time;
        set_transient($key, $data, $expiration);

        // Log rate limit violations
        if (!$allowed) {
            error_log(sprintf(
                '[PWC RATE LIMIT] IP: %s, Endpoint: %s, Limit: %d/%ds, Current: %d',
                $ip,
                $identifier,
                $max_requests,
                $window_seconds,
                $data['count']
            ));
        }

        return array(
            'allowed' => $allowed,
            'remaining' => $remaining,
            'reset' => $data['reset'],
            'limit' => $max_requests,
            'current' => $data['count']
        );
    }

    /**
     * Get client IP address (handles proxies)
     *
     * @return string Client IP address
     */
    public static function get_client_ip() {
        // Check for shared internet/ISP IP
        if (!empty($_SERVER['HTTP_CLIENT_IP']) && self::validate_ip($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        }

        // Check for IPs passing through proxies
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            // Can contain multiple IPs, get the first one
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($ips[0]);
            if (self::validate_ip($ip)) {
                return $ip;
            }
        }

        // Check for nginx proxy
        if (!empty($_SERVER['HTTP_X_REAL_IP']) && self::validate_ip($_SERVER['HTTP_X_REAL_IP'])) {
            return $_SERVER['HTTP_X_REAL_IP'];
        }

        // Fallback to remote address
        if (!empty($_SERVER['REMOTE_ADDR']) && self::validate_ip($_SERVER['REMOTE_ADDR'])) {
            return $_SERVER['REMOTE_ADDR'];
        }

        return 'unknown';
    }

    /**
     * Validate IP address
     *
     * @param string $ip IP address to validate
     * @return bool
     */
    private static function validate_ip($ip) {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6) !== false;
    }

    /**
     * Clear rate limit for specific IP (useful for testing/whitelisting)
     *
     * @param string $ip Client IP address
     * @param string $identifier Endpoint identifier
     */
    public static function clear($ip, $identifier = 'general') {
        $key = self::TRANSIENT_PREFIX . $identifier . '_' . md5($ip);
        delete_transient($key);
    }
}

/**
 * Main plugin class
 */
class ProfessionalWorldClockPlugin {

    /**
     * Plugin version
     */
    const VERSION = '2.0.0';

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('init', array($this, 'add_rewrite_rule'));
        add_action('template_redirect', array($this, 'handle_clock_page'));
        add_action('wp_head', array($this, 'add_security_headers'));

        // REST API endpoint for secure Unsplash proxy
        add_action('rest_api_init', array($this, 'register_rest_routes'));

        // Activation and deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        // Admin hooks
        if (is_admin()) {
            add_action('admin_menu', array($this, 'add_admin_menu'));
            add_action('admin_init', array($this, 'register_settings'));
        }
    }

    /**
     * Add rewrite rule for clock page
     */
    public function add_rewrite_rule() {
        add_rewrite_rule('^clock/?$', 'index.php?custom_clock=1', 'top');
        add_rewrite_tag('%custom_clock%', '([^&]+)');
    }

    /**
     * Handle clock page display
     */
    public function handle_clock_page() {
        if (get_query_var('custom_clock')) {
            // Check if template file exists
            $template_path = plugin_dir_path(__FILE__) . 'clock-template.php';
            
            if (!file_exists($template_path)) {
                wp_die(
                    esc_html__('Clock template file not found.', 'professional-world-clock'),
                    esc_html__('Template Error', 'professional-world-clock'),
                    array('response' => 500)
                );
            }
            
            // Include template
            include $template_path;
            exit;
        }
    }

    /**
     * Add security headers
     */
    public function add_security_headers() {
        if (get_query_var('custom_clock')) {
            header('X-Content-Type-Options: nosniff');
            header('X-Frame-Options: SAMEORIGIN');
            header('Referrer-Policy: strict-origin-when-cross-origin');
        }
    }

    /**
     * Register REST API routes
     */
    public function register_rest_routes() {
        register_rest_route('pwc/v1', '/unsplash-images', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_unsplash_images'),
            'permission_callback' => '__return_true', // Public endpoint
        ));
    }

    /**
     * Get Unsplash images via secure proxy
     */
    public function get_unsplash_images($request) {
        // Apply rate limiting: 60 requests per minute per IP
        $client_ip = PWC_Rate_Limiter::get_client_ip();
        $rate_limit = PWC_Rate_Limiter::check($client_ip, 60, 60, 'unsplash_api');

        // Add rate limit headers
        header('X-RateLimit-Limit: ' . $rate_limit['limit']);
        header('X-RateLimit-Remaining: ' . $rate_limit['remaining']);
        header('X-RateLimit-Reset: ' . $rate_limit['reset']);

        // Block if rate limit exceeded
        if (!$rate_limit['allowed']) {
            return new WP_REST_Response(array(
                'error' => 'Rate limit exceeded',
                'message' => 'Too many requests. Please try again later.',
                'limit' => $rate_limit['limit'],
                'remaining' => $rate_limit['remaining'],
                'reset' => $rate_limit['reset'],
                'resetTime' => date('Y-m-d H:i:s', $rate_limit['reset'])
            ), 429);
        }

        // Allow cache clearing with ?clear_cache=1
        $clear_cache = $request->get_param('clear_cache');

        // Check cache first
        $cache_key = 'pwc_unsplash_images';
        $cached_images = get_transient($cache_key);

        if ($cached_images !== false && !$clear_cache) {
            return new WP_REST_Response($cached_images, 200);
        }

        // Get API key from options
        $options = get_option('pwc_options', array());
        $api_key = isset($options['unsplash_api_key']) ? $options['unsplash_api_key'] : '';

        if (empty($api_key)) {
            return new WP_REST_Response(array(
                'error' => 'API key not configured',
                'fallback' => true
            ), 200); // Return 200 so frontend can use fallback
        }

        // Fetch from Unsplash API
        $response = wp_remote_get(
            'https://api.unsplash.com/photos/random?count=10&query=nature,landscape&orientation=landscape',
            array(
                'headers' => array(
                    'Authorization' => 'Client-ID ' . $api_key,
                    'Accept-Version' => 'v1'
                ),
                'timeout' => 15
            )
        );

        if (is_wp_error($response)) {
            $error_msg = $response->get_error_message();
            error_log('PWC: Unsplash API error: ' . $error_msg);
            return new WP_REST_Response(array(
                'error' => 'Failed to fetch images: ' . $error_msg,
                'fallback' => true
            ), 200);
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        // Log the raw response for debugging
        error_log('PWC: Unsplash API status: ' . $status_code);
        error_log('PWC: Unsplash API response: ' . substr($body, 0, 500));

        $data = json_decode($body, true);

        // Check for Unsplash API errors
        if (isset($data['errors']) || $status_code !== 200) {
            $error_message = isset($data['errors']) ? implode(', ', $data['errors']) : 'HTTP ' . $status_code;
            error_log('PWC: Unsplash returned error: ' . $error_message);
            return new WP_REST_Response(array(
                'error' => 'Unsplash API error: ' . $error_message,
                'fallback' => true,
                'debug' => array(
                    'status' => $status_code,
                    'response' => $data
                )
            ), 200);
        }

        if (!is_array($data) || empty($data)) {
            error_log('PWC: Invalid or empty response from Unsplash');
            return new WP_REST_Response(array(
                'error' => 'Invalid response from Unsplash',
                'fallback' => true
            ), 200);
        }

        // Transform data to our format - use high resolution images
        $images = array_map(function($photo) {
            // Use 'full' for display (high quality) and 'raw' for downloads
            $base_url = isset($photo['urls']['full']) ? $photo['urls']['full'] : (isset($photo['urls']['regular']) ? $photo['urls']['regular'] : '');

            // Add high resolution parameters: 4K width (3840px) and maximum quality
            $display_url = $base_url . '&w=3840&q=100';

            return array(
                'url' => $display_url,
                'downloadUrl' => isset($photo['urls']['raw']) ? $photo['urls']['raw'] : $base_url,
                'photographer' => isset($photo['user']['name']) ? $photo['user']['name'] : 'Unknown',
                'photographerUrl' => isset($photo['user']['links']['html']) ? $photo['user']['links']['html'] : 'https://unsplash.com'
            );
        }, $data);

        // Cache for 1 hour
        set_transient($cache_key, $images, HOUR_IN_SECONDS);

        error_log('PWC: Successfully fetched and cached ' . count($images) . ' images');

        return new WP_REST_Response($images, 200);
    }

    /**
     * Plugin activation
     */
    public function activate() {
        // Add rewrite rules
        $this->add_rewrite_rule();
        flush_rewrite_rules();
        
        // Set default options
        $default_options = array(
            'version' => self::VERSION,
            'activated_time' => current_time('mysql')
        );
        add_option('pwc_options', $default_options);
        
        // Log activation
        if (function_exists('error_log')) {
            error_log('Professional World Clock Plugin activated - Version ' . self::VERSION);
        }
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        flush_rewrite_rules();
        
        // Log deactivation
        if (function_exists('error_log')) {
            error_log('Professional World Clock Plugin deactivated');
        }
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_options_page(
            'World Clock Settings',
            'World Clock',
            'manage_options',
            'professional-world-clock',
            array($this, 'render_settings_page')
        );
    }

    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('pwc_settings', 'pwc_options', array($this, 'sanitize_options'));

        add_settings_section(
            'pwc_api_section',
            'Unsplash API Configuration',
            array($this, 'render_api_section_info'),
            'professional-world-clock'
        );

        add_settings_field(
            'unsplash_api_key',
            'Unsplash Access Key',
            array($this, 'render_api_key_field'),
            'professional-world-clock',
            'pwc_api_section'
        );
    }

    /**
     * Sanitize options
     */
    public function sanitize_options($options) {
        if (isset($options['unsplash_api_key'])) {
            $options['unsplash_api_key'] = sanitize_text_field($options['unsplash_api_key']);
        }
        return $options;
    }

    /**
     * Render API section info
     */
    public function render_api_section_info() {
        echo '<p>Configure your Unsplash API key to enable dynamic background images. Get your free API key at <a href="https://unsplash.com/developers" target="_blank">Unsplash Developers</a>.</p>';
    }

    /**
     * Render API key field
     */
    public function render_api_key_field() {
        $options = get_option('pwc_options', array());
        $api_key = isset($options['unsplash_api_key']) ? $options['unsplash_api_key'] : '';
        $masked_key = !empty($api_key) ? substr($api_key, 0, 8) . '...' . substr($api_key, -4) : '';
        ?>
        <input type="password"
               name="pwc_options[unsplash_api_key]"
               value="<?php echo esc_attr($api_key); ?>"
               class="regular-text"
               placeholder="Enter your Unsplash Access Key"
        />
        <?php if (!empty($masked_key)): ?>
        <p class="description">Current key: <?php echo esc_html($masked_key); ?></p>
        <?php endif; ?>
        <p class="description">Your API key is stored securely and never exposed to the browser. To download images with specific topics, go to the Image Gallery tab.</p>
        <?php
    }

    /**
     * Get Clock API URL
     */
    private function get_clock_api_url() {
        return get_site_url() . '/api/clock';
    }

    /**
     * Call Clock API
     */
    private function call_clock_api($endpoint, $method = 'GET', $data = null, $use_api_key = false) {
        $url = $this->get_clock_api_url() . $endpoint;

        $headers = array(
            'Content-Type' => 'application/json'
        );

        // Add API key for protected endpoints
        if ($use_api_key) {
            $headers['X-API-Key'] = 'clock_api_secure_key_2025';
        }

        $args = array(
            'method' => $method,
            'timeout' => 10,
            'headers' => $headers
        );

        if ($data && $method === 'POST') {
            $args['body'] = json_encode($data);
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            return array('error' => $response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        return json_decode($body, true);
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'professional-world-clock'));
        }

        $site_url = get_site_url();
        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'settings';

        // Handle cache clear action
        if (isset($_POST['clear_cache']) && check_admin_referer('pwc_clear_cache')) {
            $this->call_clock_api('/images/clear-cache', 'POST', null, true);
            echo '<div class="notice notice-success"><p>Cache cleared successfully!</p></div>';
        }
        ?>
        <div class="wrap">
            <h1>World Clock & API Management</h1>

            <h2 class="nav-tab-wrapper">
                <a href="?page=professional-world-clock&tab=settings" class="nav-tab <?php echo $active_tab === 'settings' ? 'nav-tab-active' : ''; ?>">Settings</a>
                <a href="?page=professional-world-clock&tab=gallery" class="nav-tab <?php echo $active_tab === 'gallery' ? 'nav-tab-active' : ''; ?>">Image Gallery</a>
                <a href="?page=professional-world-clock&tab=statistics" class="nav-tab <?php echo $active_tab === 'statistics' ? 'nav-tab-active' : ''; ?>">Statistics</a>
                <a href="?page=professional-world-clock&tab=rate-limits" class="nav-tab <?php echo $active_tab === 'rate-limits' ? 'nav-tab-active' : ''; ?>">Rate Limits</a>
                <a href="?page=professional-world-clock&tab=health" class="nav-tab <?php echo $active_tab === 'health' ? 'nav-tab-active' : ''; ?>">Health</a>
            </h2>

            <?php
            switch ($active_tab) {
                case 'gallery':
                    $this->render_gallery_tab();
                    break;
                case 'statistics':
                    $this->render_statistics_tab();
                    break;
                case 'health':
                    $this->render_health_tab();
                    break;
                case 'rate-limits':
                    $this->render_rate_limits_tab();
                    break;
                case 'settings':
                default:
                    $this->render_settings_tab($site_url);
                    break;
            }
            ?>
        </div>
        <?php
    }

    /**
     * Render Settings Tab
     */
    private function render_settings_tab($site_url) {
        ?>
        <form method="post" action="options.php">
            <?php
            settings_fields('pwc_settings');
            do_settings_sections('professional-world-clock');
            submit_button('Save Settings');
            ?>
        </form>

        <div class="card" style="max-width: 800px; margin-top: 20px;">
            <h2>Clock Page Information</h2>
            <p>Your professional world clock is available at:</p>
            <p><a href="<?php echo esc_url($site_url . '/clock'); ?>" target="_blank" style="font-size: 18px; font-weight: bold; color: #2271b1;">
                <?php echo esc_url($site_url . '/clock'); ?>
            </a></p>

            <h3 style="margin-top: 30px;">Clock API Endpoints (for Mobile/Desktop Apps):</h3>
            <table class="widefat" style="max-width: 700px; margin-top: 10px;">
                <thead>
                    <tr>
                        <th>Endpoint</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><code><?php echo esc_html($site_url); ?>/api/clock/health</code></td>
                        <td>Health check</td>
                    </tr>
                    <tr>
                        <td><code><?php echo esc_html($site_url); ?>/api/clock/images?count=10</code></td>
                        <td>Get random images</td>
                    </tr>
                    <tr>
                        <td><code><?php echo esc_html($site_url); ?>/api/clock/track/view</code></td>
                        <td>Track image view (POST)</td>
                    </tr>
                    <tr>
                        <td><code><?php echo esc_html($site_url); ?>/api/clock/track/download</code></td>
                        <td>Track download (POST)</td>
                    </tr>
                    <tr>
                        <td><code><?php echo esc_html($site_url); ?>/api/clock/statistics</code></td>
                        <td>Get usage statistics</td>
                    </tr>
                </tbody>
            </table>

            <h3 style="margin-top: 30px;">Features:</h3>
            <ul style="list-style: disc; margin-left: 20px;">
                <li>Real-time digital clock</li>
                <li>Dynamic background slideshow from Unsplash</li>
                <li>Professional Node.js API backend</li>
                <li>Image view and download tracking</li>
                <li>Analytics and statistics</li>
                <li>Fully responsive design</li>
                <li>Secure API key storage</li>
            </ul>

            <h3 style="margin-top: 30px;">Plugin Information:</h3>
            <p><strong>Version:</strong> <?php echo esc_html(self::VERSION); ?></p>
            <p><strong>Status:</strong> <span style="color: green; font-weight: bold;">Active</span></p>
        </div>
        <?php
    }

    /**
     * Render Statistics Tab
     */
    private function render_statistics_tab() {
        $stats = $this->call_clock_api('/statistics?detailed=true&days=30');

        if (isset($stats['error'])) {
            echo '<div class="notice notice-error"><p>Error fetching statistics: ' . esc_html($stats['error']) . '</p></div>';
            return;
        }
        ?>
        <div class="card" style="max-width: 1200px; margin-top: 20px;">
            <h2>Usage Statistics (Last 30 Days)</h2>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin: 20px 0;">
                <div style="background: #f0f6fc; padding: 20px; border-radius: 8px; border-left: 4px solid #2271b1;">
                    <h3 style="margin: 0 0 10px 0; color: #1d2327;">Total Views</h3>
                    <p style="font-size: 32px; font-weight: bold; margin: 0; color: #2271b1;">
                        <?php echo number_format($stats['totals']['totalViews'] ?? 0); ?>
                    </p>
                    <p style="margin: 5px 0 0 0; color: #50575e;">
                        Recent: <?php echo number_format($stats['totals']['recentViews'] ?? 0); ?>
                    </p>
                </div>

                <div style="background: #f6f0fc; padding: 20px; border-radius: 8px; border-left: 4px solid #9b51e0;">
                    <h3 style="margin: 0 0 10px 0; color: #1d2327;">Total Downloads</h3>
                    <p style="font-size: 32px; font-weight: bold; margin: 0; color: #9b51e0;">
                        <?php echo number_format($stats['totals']['totalDownloads'] ?? 0); ?>
                    </p>
                    <p style="margin: 5px 0 0 0; color: #50575e;">
                        Recent: <?php echo number_format($stats['totals']['recentDownloads'] ?? 0); ?>
                    </p>
                </div>
            </div>

            <?php if (!empty($stats['topImages']['mostViewed'])): ?>
            <h3 style="margin-top: 30px;">Top Viewed Images</h3>
            <table class="widefat">
                <thead>
                    <tr>
                        <th>Image ID</th>
                        <th>Photographer</th>
                        <th>View Count</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_slice($stats['topImages']['mostViewed'], 0, 10) as $image): ?>
                    <tr>
                        <td><code><?php echo esc_html($image['image_id']); ?></code></td>
                        <td><?php echo esc_html($image['photographer'] ?? 'Unknown'); ?></td>
                        <td><strong><?php echo number_format($image['view_count']); ?></strong></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>

            <?php if (!empty($stats['platforms'])): ?>
            <h3 style="margin-top: 30px;">Platform Breakdown</h3>
            <table class="widefat" style="max-width: 500px;">
                <thead>
                    <tr>
                        <th>Platform</th>
                        <th>Count</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stats['platforms'] as $platform): ?>
                    <tr>
                        <td><?php echo esc_html(ucfirst($platform['platform'] ?? 'unknown')); ?></td>
                        <td><strong><?php echo number_format($platform['count']); ?></strong></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render Gallery Tab
     */
    private function render_gallery_tab() {
        // Handle manual cache refresh action
        if (isset($_POST['refresh_cache']) && check_admin_referer('pwc_refresh_cache')) {
            $query = isset($_POST['cache_query']) ? sanitize_text_field($_POST['cache_query']) : 'nature,landscape';

            // Save the keywords for future use
            $options = get_option('pwc_options', array());
            $options['saved_keywords'] = $query;
            update_option('pwc_options', $options);

            $result = $this->call_clock_api('/images/refresh-cache?query=' . urlencode($query), 'POST', null, true);

            if (isset($result['success']) && $result['success']) {
                echo '<div class="notice notice-success"><p>Cache refreshed successfully! Downloaded: ' . esc_html($result['downloaded'] ?? 0) . ', Failed: ' . esc_html($result['failed'] ?? 0) . '</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>Failed to refresh cache: ' . esc_html($result['message'] ?? 'Unknown error') . '</p></div>';
            }
        }

        $cache_info = $this->call_clock_api('/images/cache-info', 'GET', null, true);

        if (isset($cache_info['error'])) {
            echo '<div class="notice notice-error"><p>Error fetching cache info: ' . esc_html($cache_info['error']) . '</p></div>';
            return;
        }

        $cache = $cache_info['cache'] ?? array();
        $site_url = get_site_url();

        // Get saved keywords or use default
        $options = get_option('pwc_options', array());
        $saved_keywords = isset($options['saved_keywords']) ? $options['saved_keywords'] : 'nature,landscape';

        // Get all images
        $images_response = $this->call_clock_api('/images?count=30');
        $images = $images_response['images'] ?? array();
        ?>
        <div class="card" style="max-width: 1400px; margin-top: 20px;">
            <h2>Cached Image Gallery</h2>

            <!-- Manual Cache Refresh -->
            <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #2271b1;">
                <h3 style="margin-top: 0;">Download New Images</h3>
                <p>Manually download and cache new images from Unsplash. This will fetch up to 10 new images based on your search topics.</p>
                <form method="post" style="display: flex; gap: 10px; align-items: flex-start; flex-wrap: wrap;">
                    <?php wp_nonce_field('pwc_refresh_cache'); ?>
                    <div style="flex: 1; min-width: 250px;">
                        <label for="cache_query" style="display: block; margin-bottom: 5px; font-weight: 600;">Search Topics:</label>
                        <input type="text"
                               id="cache_query"
                               name="cache_query"
                               value="<?php echo esc_attr($saved_keywords); ?>"
                               placeholder="ocean,sunset,mountains"
                               style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;"
                        />
                        <p style="margin: 5px 0 0 0; color: #666; font-size: 12px;">
                            Comma-separated topics (e.g., "ocean,sunset", "mountains,forest")
                        </p>
                    </div>
                    <div style="padding-top: 27px;">
                        <button type="submit" name="refresh_cache" class="button button-primary" style="height: 38px;">
                            <span class="dashicons dashicons-download" style="vertical-align: middle; margin-right: 5px;"></span>
                            Download Images
                        </button>
                    </div>
                </form>
            </div>

            <!-- Cache Statistics -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 20px 0; padding: 20px; background: #f8f9fa; border-radius: 8px;">
                <div>
                    <strong style="color: #666;">Total Images:</strong>
                    <p style="font-size: 24px; margin: 5px 0; font-weight: bold; color: #2271b1;">
                        <?php echo number_format($cache['imageCount'] ?? 0); ?>
                    </p>
                </div>
                <div>
                    <strong style="color: #666;">Total Size:</strong>
                    <p style="font-size: 24px; margin: 5px 0; font-weight: bold; color: #2271b1;">
                        <?php echo number_format(($cache['totalSize'] ?? 0) / 1024 / 1024, 2); ?> MB
                    </p>
                </div>
                <div>
                    <strong style="color: #666;">Average Size:</strong>
                    <p style="font-size: 24px; margin: 5px 0; font-weight: bold; color: #2271b1;">
                        <?php echo number_format(($cache['avgSize'] ?? 0) / 1024 / 1024, 2); ?> MB
                    </p>
                </div>
                <div>
                    <strong style="color: #666;">Storage Used:</strong>
                    <p style="font-size: 24px; margin: 5px 0; font-weight: bold; color: #2271b1;">
                        <?php echo number_format($cache['usagePercent'] ?? 0, 1); ?>%
                    </p>
                </div>
            </div>

            <!-- Image Gallery Grid -->
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; margin-top: 30px;">
                <?php if (!empty($images)): ?>
                    <?php foreach ($images as $image): ?>
                        <div style="border: 1px solid #ddd; border-radius: 8px; overflow: hidden; background: #fff; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                            <div style="position: relative; padding-bottom: 60%; overflow: hidden; background: #f0f0f0;">
                                <img src="<?php echo esc_url($site_url . $image['url']); ?>"
                                     alt="<?php echo esc_attr($image['description'] ?? 'Image'); ?>"
                                     style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: cover;"
                                     loading="lazy">
                            </div>
                            <div style="padding: 15px;">
                                <p style="margin: 0 0 8px 0; font-weight: 600; color: #1d2327;">
                                    <?php echo esc_html($image['photographer'] ?? 'Unknown'); ?>
                                </p>
                                <?php if (!empty($image['description'])): ?>
                                <p style="margin: 0 0 8px 0; color: #50575e; font-size: 13px;">
                                    <?php echo esc_html($image['description']); ?>
                                </p>
                                <?php endif; ?>
                                <p style="margin: 0 0 8px 0; color: #666; font-size: 12px;">
                                    <strong>Dimensions:</strong> <?php echo esc_html($image['width'] ?? 0); ?> × <?php echo esc_html($image['height'] ?? 0); ?>
                                </p>
                                <p style="margin: 0 0 8px 0; color: #666; font-size: 12px;">
                                    <strong>Downloads:</strong> <?php echo number_format($image['downloadCount'] ?? 0); ?>
                                </p>
                                <p style="margin: 0 0 12px 0; color: #666; font-size: 12px;">
                                    <strong>ID:</strong> <code style="background: #f3f4f6; padding: 2px 6px; border-radius: 3px; font-size: 11px;"><?php echo esc_html(substr($image['id'] ?? '', 0, 12)); ?>...</code>
                                </p>
                                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                    <a href="<?php echo esc_url($site_url . $image['url']); ?>"
                                       target="_blank"
                                       class="button button-small"
                                       style="flex: 1; text-align: center;">
                                        View Full
                                    </a>
                                    <?php if (!empty($image['photographerUrl'])): ?>
                                    <a href="<?php echo esc_url($image['photographerUrl']); ?>"
                                       target="_blank"
                                       class="button button-small"
                                       style="flex: 1; text-align: center;">
                                        Photographer
                                    </a>
                                    <?php endif; ?>
                                </div>
                                <button onclick="deleteImage('<?php echo esc_js($image['id']); ?>', '<?php echo esc_js($image['photographer']); ?>')"
                                        class="button button-small button-link-delete"
                                        style="width: 100%; margin-top: 8px; color: #b32d2e;">
                                    Delete Image
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="grid-column: 1 / -1; text-align: center; padding: 40px; color: #666;">
                        <p>No cached images found. Images will be downloaded automatically by the scheduled task.</p>
                    </div>
                <?php endif; ?>
            </div>

            <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd;">
                <h3>Cache Information</h3>
                <p><strong>Oldest Image:</strong> <?php echo esc_html($cache['oldestImage'] ?? 'N/A'); ?></p>
                <p><strong>Newest Image:</strong> <?php echo esc_html($cache['newestImage'] ?? 'N/A'); ?></p>
                <p><strong>Max Storage:</strong> <?php echo number_format(($cache['maxStorage'] ?? 0) / 1024 / 1024 / 1024, 2); ?> GB</p>
            </div>
        </div>

        <script>
        function deleteImage(imageId, photographer) {
            // Confirm deletion
            if (!confirm('Are you sure you want to delete this image by ' + photographer + '?\n\nThis action cannot be undone.')) {
                return;
            }

            // Show loading state
            const button = event.target;
            const originalText = button.textContent;
            button.disabled = true;
            button.textContent = 'Deleting...';

            // Call API to delete image
            fetch('<?php echo esc_js($this->get_clock_api_url()); ?>/images/' + imageId, {
                method: 'DELETE',
                headers: {
                    'X-API-Key': 'clock_api_secure_key_2025'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success message
                    alert('Image deleted successfully!');
                    // Reload page to refresh gallery
                    window.location.reload();
                } else {
                    // Show error
                    alert('Failed to delete image: ' + (data.message || 'Unknown error'));
                    // Restore button
                    button.disabled = false;
                    button.textContent = originalText;
                }
            })
            .catch(error => {
                alert('Error deleting image: ' + error.message);
                // Restore button
                button.disabled = false;
                button.textContent = originalText;
            });
        }
        </script>
        <?php
    }

    /**
     * Render Health Tab
     */
    private function render_health_tab() {
        $health = $this->call_clock_api('/health');
        $is_healthy = isset($health['success']) && $health['success'];
        ?>
        <div class="card" style="max-width: 800px; margin-top: 20px;">
            <h2>Clock API Health Status</h2>

            <div style="padding: 20px; background: <?php echo $is_healthy ? '#f0f9f4' : '#fef2f2'; ?>; border-radius: 8px; margin: 20px 0; border-left: 4px solid <?php echo $is_healthy ? '#10b981' : '#ef4444'; ?>;">
                <h3 style="margin: 0 0 10px 0;">
                    Status: <span style="color: <?php echo $is_healthy ? '#10b981' : '#ef4444'; ?>; font-weight: bold;">
                        <?php echo $is_healthy ? 'HEALTHY' : 'UNHEALTHY'; ?>
                    </span>
                </h3>
                <?php if ($is_healthy): ?>
                    <p style="margin: 0;">API is running smoothly and responding to requests.</p>
                    <p style="margin: 5px 0 0 0; color: #6b7280;">Last checked: <?php echo esc_html($health['timestamp'] ?? ''); ?></p>
                <?php else: ?>
                    <p style="margin: 0; color: #dc2626;">API is not responding. Please check the Docker container.</p>
                <?php endif; ?>
            </div>

            <h3>API Endpoint</h3>
            <p><code style="background: #f3f4f6; padding: 5px 10px; border-radius: 4px;">
                <?php echo esc_html($this->get_clock_api_url()); ?>
            </code></p>

            <h3 style="margin-top: 30px;">Cache Management</h3>
            <form method="post">
                <?php wp_nonce_field('pwc_clear_cache'); ?>
                <button type="submit" name="clear_cache" class="button button-secondary">
                    Clear Image Cache
                </button>
                <p class="description">Clear the cached Unsplash images. New images will be fetched on next request.</p>
            </form>

            <h3 style="margin-top: 30px;">Quick Actions</h3>
            <p>
                <a href="?page=professional-world-clock&tab=health" class="button button-secondary">Refresh Status</a>
                <a href="<?php echo esc_url($this->get_clock_api_url() . '/statistics'); ?>" target="_blank" class="button button-secondary">View Raw Stats (JSON)</a>
            </p>
        </div>
        <?php
    }

    /**
     * Render Rate Limits Tab
     */
    private function render_rate_limits_tab() {
        ?>
        <div class="card" style="max-width: 1200px; margin-top: 20px;">
            <h2>API Rate Limiting Configuration</h2>

            <div style="background: #f0f6fc; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #2271b1;">
                <h3 style="margin-top: 0;">What is Rate Limiting?</h3>
                <p>Rate limiting protects your APIs from abuse by limiting the number of requests a client can make within a specific time window. This prevents:</p>
                <ul style="margin-left: 20px;">
                    <li><strong>Bandwidth Abuse:</strong> Excessive requests consuming your hosting bandwidth</li>
                    <li><strong>API Quota Exhaustion:</strong> Unsplash API has limits - rate limiting prevents hitting those limits</li>
                    <li><strong>DDoS Attacks:</strong> Protects against distributed denial-of-service attacks</li>
                    <li><strong>Cost Control:</strong> Prevents unexpected hosting costs from traffic spikes</li>
                </ul>
            </div>

            <h3>Active Rate Limits</h3>

            <table class="widefat" style="margin-top: 15px;">
                <thead>
                    <tr>
                        <th style="width: 25%;">Endpoint</th>
                        <th style="width: 15%;">Limit</th>
                        <th style="width: 15%;">Window</th>
                        <th style="width: 45%;">Description</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>WordPress API</strong><br><code>/wp-json/pwc/v1/unsplash-images</code></td>
                        <td><span style="color: #2271b1; font-weight: bold;">60 requests</span></td>
                        <td>1 minute</td>
                        <td>Protects Unsplash API proxy endpoint from excessive requests</td>
                    </tr>
                    <tr style="background: #f9f9f9;">
                        <td><strong>Node.js API - General</strong><br><code>/api/v1/images</code></td>
                        <td><span style="color: #2271b1; font-weight: bold;">100 requests</span></td>
                        <td>15 minutes</td>
                        <td>General endpoint for fetching images. Bypassed with valid API key.</td>
                    </tr>
                    <tr>
                        <td><strong>Node.js API - Tracking</strong><br><code>/api/v1/track/*</code></td>
                        <td><span style="color: #9b51e0; font-weight: bold;">30 requests</span></td>
                        <td>1 minute</td>
                        <td>Tracking endpoints for view/download analytics</td>
                    </tr>
                    <tr style="background: #f9f9f9;">
                        <td><strong>Node.js API - Statistics</strong><br><code>/api/v1/statistics</code></td>
                        <td><span style="color: #dc2626; font-weight: bold;">10 requests</span></td>
                        <td>1 minute</td>
                        <td>Stats endpoint - limited due to database query overhead</td>
                    </tr>
                    <tr>
                        <td><strong>Node.js API - Admin</strong><br><code>/api/v1/images/*</code> (protected)</td>
                        <td><span style="color: #dc2626; font-weight: bold;">5 requests</span></td>
                        <td>1 minute</td>
                        <td>Admin endpoints (cache clear, refresh, delete). Requires API key.</td>
                    </tr>
                </tbody>
            </table>

            <h3 style="margin-top: 30px;">Rate Limit Headers</h3>
            <p>All API responses include rate limit information in headers:</p>
            <div style="background: #f3f4f6; padding: 15px; border-radius: 4px; font-family: monospace; margin-top: 10px;">
                <div>X-RateLimit-Limit: 60</div>
                <div>X-RateLimit-Remaining: 45</div>
                <div>X-RateLimit-Reset: 1699123456</div>
            </div>

            <h3 style="margin-top: 30px;">IP Detection</h3>
            <p>Rate limiting tracks requests by IP address. The system properly handles:</p>
            <ul style="margin-left: 20px;">
                <li><strong>Direct connections:</strong> Uses REMOTE_ADDR</li>
                <li><strong>Nginx proxy:</strong> Reads X-Real-IP and X-Forwarded-For headers</li>
                <li><strong>Load balancers:</strong> Extracts real client IP from proxy headers</li>
            </ul>

            <h3 style="margin-top: 30px;">Current IP Address</h3>
            <div style="background: #f0f9f4; padding: 15px; border-radius: 8px; border-left: 4px solid #10b981; margin-top: 10px;">
                <p style="margin: 0;"><strong>Your IP:</strong> <code style="background: #fff; padding: 5px 10px; border-radius: 3px; font-size: 14px;"><?php echo esc_html(PWC_Rate_Limiter::get_client_ip()); ?></code></p>
                <p style="margin: 10px 0 0 0; color: #666; font-size: 13px;">This is the IP address that will be tracked for rate limiting.</p>
            </div>

            <h3 style="margin-top: 30px;">Rate Limit Violations</h3>
            <p>When rate limits are exceeded:</p>
            <ul style="margin-left: 20px;">
                <li>Client receives HTTP 429 (Too Many Requests) status</li>
                <li>Response includes details about the limit and when it resets</li>
                <li>Violation is logged to error log with timestamp and IP</li>
                <li>Headers indicate when client can retry</li>
            </ul>

            <div style="background: #fff3cd; padding: 15px; border-radius: 8px; border-left: 4px solid #ffc107; margin-top: 20px;">
                <h4 style="margin-top: 0;">For Your Mobile App</h4>
                <p>When developing your mobile app:</p>
                <ol style="margin-left: 20px;">
                    <li><strong>Implement exponential backoff:</strong> If you receive a 429 response, wait and retry with increasing delays</li>
                    <li><strong>Read rate limit headers:</strong> Monitor X-RateLimit-Remaining to know when you're approaching limits</li>
                    <li><strong>Use API key authentication:</strong> Authenticated requests bypass the general rate limiter</li>
                    <li><strong>Cache responses:</strong> Reduce API calls by caching images locally</li>
                </ol>
            </div>

            <h3 style="margin-top: 30px;">Monitoring</h3>
            <p>Rate limit violations are logged to:</p>
            <ul style="margin-left: 20px;">
                <li><strong>WordPress:</strong> PHP error log (check with hosting provider)</li>
                <li><strong>Node.js API:</strong> Docker container logs (view with <code>docker logs clock-api</code>)</li>
            </ul>

            <div style="margin-top: 30px; padding: 20px; background: #f8f9fa; border-radius: 8px;">
                <h4 style="margin-top: 0;">Need to Adjust Limits?</h4>
                <p>Rate limits are configured in:</p>
                <ul style="margin-left: 20px;">
                    <li><strong>WordPress Plugin:</strong> <code>custom-clock.php</code> - Line 251 (60 req/min for Unsplash endpoint)</li>
                    <li><strong>Node.js API:</strong> <code>clock-api/src/middleware/rateLimiter.js</code></li>
                    <li><strong>Environment:</strong> <code>clock-api/.env</code> - Set RATE_LIMIT_WINDOW_MS and RATE_LIMIT_MAX_REQUESTS</li>
                </ul>
                <p style="margin-bottom: 0;"><strong>Note:</strong> After changing limits, restart the Node.js API with <code>docker restart clock-api</code></p>
            </div>
        </div>
        <?php
    }
}

// Initialize plugin
function professional_world_clock_init() {
    return ProfessionalWorldClockPlugin::get_instance();
}

// Start the plugin
add_action('plugins_loaded', 'professional_world_clock_init');
