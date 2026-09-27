<?php

/**

 * Plugin Name: Brolexy WooCommerce Sync

 * Plugin URI: https://g4meshop.com

 * Description: Sync products from Brolexy API to WooCommerce with profit margins and automatic fulfillment

 * Version: 1.0.0

 * Author: G4me Shop

 * Author URI: https://g4meshop.com

 * Text Domain: brolexy-woo-sync

 * Requires at least: 5.8

 * Requires PHP: 7.4

 * WC requires at least: 5.0

 * WC tested up to: 8.0

 */



if (!defined('ABSPATH')) {

    exit;

}



// Check if WooCommerce is active

if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {

    add_action('admin_notices', function() {

        echo '<div class="error"><p>Brolexy WooCommerce Sync requires WooCommerce to be installed and active.</p></div>';

    });

    return;

}



define('BROLEXY_SYNC_VERSION', '1.0.0');

define('BROLEXY_SYNC_PLUGIN_DIR', plugin_dir_path(__FILE__));

define('BROLEXY_SYNC_PLUGIN_URL', plugin_dir_url(__FILE__));



class BrolexyWooSync {

    

    private static $instance = null;

    private $api_base_url;

    

    public static function getInstance() {

        if (self::$instance == null) {

            self::$instance = new BrolexyWooSync();

        }

        return self::$instance;

    }

    

    private function __construct() {

        // Admin menu

        add_action('admin_menu', [$this, 'addAdminMenu']);

        add_action('admin_init', [$this, 'registerSettings']);

        

        // AJAX handlers

        add_action('wp_ajax_brolexy_test_connection', [$this, 'ajaxTestConnection']);

        add_action('wp_ajax_brolexy_sync_products', [$this, 'ajaxSyncProducts']);

        add_action('wp_ajax_brolexy_manual_sync', [$this, 'ajaxManualSync']);

        add_action('wp_ajax_brolexy_search_products', [$this, 'ajaxSearchProducts']);

        add_action('wp_ajax_brolexy_import_product', [$this, 'ajaxImportProduct']);

        add_action('wp_ajax_brolexy_delete_imported_product', [$this, 'ajaxDeleteImportedProduct']);

        

        // Cron schedules

        add_filter('cron_schedules', [$this, 'addCronSchedules']);

        // Single unified sync cron
        add_action('brolexy_sync_products_cron', [$this, 'cronSyncProducts']);

        

        // Order processing

        add_action('woocommerce_order_status_processing', [$this, 'processOrder'], 10, 1);

        add_action('woocommerce_order_status_completed', [$this, 'processOrder'], 10, 1);
        
        // Manual order processing
        add_filter('woocommerce_order_actions', [$this, 'addOrderActions']);
        add_action('woocommerce_order_action_brolexy_manual_fulfill', [$this, 'handleManualOrderAction']);
        add_action('woocommerce_order_action_brolexy_force_reprocess', [$this, 'handleForceReprocessAction']);
        
        // AJAX handler for manual order processing
        add_action('wp_ajax_brolexy_manual_process_order', [$this, 'ajaxManualProcessOrder']);

        

        // Product meta boxes

        add_action('add_meta_boxes', [$this, 'addProductMetaBox']);

        add_action('save_post', [$this, 'saveProductMeta']);

        

        // Activation/Deactivation

        register_activation_hook(__FILE__, [$this, 'activate']);

        register_deactivation_hook(__FILE__, [$this, 'deactivate']);

        

        // Admin scripts

        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminScripts']);

    }

    

    public function addAdminMenu() {

        add_menu_page(

            'Brolexy Sync',

            'Brolexy Sync',

            'manage_options',

            'brolexy-sync',

            [$this, 'renderAdminPage'],

            'dashicons-update',

            56

        );

        

        add_submenu_page(

            'brolexy-sync',

            'Settings',

            'Settings',

            'manage_options',

            'brolexy-sync',

            [$this, 'renderAdminPage']

        );

        

        add_submenu_page(

            'brolexy-sync',

            'Import Products',

            'Import Products',

            'manage_options',

            'brolexy-import',

            [$this, 'renderImportPage']

        );

        

        add_submenu_page(

            'brolexy-sync',

            'Sync Logs',

            'Sync Logs',

            'manage_options',

            'brolexy-logs',

            [$this, 'renderLogsPage']

        );

    }

    

    public function registerSettings() {

        register_setting('brolexy_sync_options', 'brolexy_api_mode');

        register_setting('brolexy_sync_options', 'brolexy_public_key');

        register_setting('brolexy_sync_options', 'brolexy_private_key');

        register_setting('brolexy_sync_options', 'brolexy_account_id');

        register_setting('brolexy_sync_options', 'brolexy_profit_margin');

        register_setting('brolexy_sync_options', 'brolexy_profit_margin_type');

        register_setting('brolexy_sync_options', 'brolexy_sync_interval', [
            'sanitize_callback' => [$this, 'sanitizeSyncInterval']
        ]);

        register_setting('brolexy_sync_options', 'brolexy_auto_fulfill');

        register_setting('brolexy_sync_options', 'brolexy_product_prefix');

        register_setting('brolexy_sync_options', 'brolexy_default_category');

    }
    
    /**
     * Sanitize sync interval and reschedule cron when changed
     */
    public function sanitizeSyncInterval($value) {
        // Reschedule cron with new interval
        wp_clear_scheduled_hook('brolexy_sync_products_cron');
        
        if (!empty($value) && $value !== 'never') {
            wp_schedule_event(time(), $value, 'brolexy_sync_products_cron');
        }
        
        return sanitize_text_field($value);
    }

    

    public function enqueueAdminScripts($hook) {

        if (strpos($hook, 'brolexy') === false) {

            return;

        }

        

        wp_enqueue_style('brolexy-admin-css', BROLEXY_SYNC_PLUGIN_URL . 'assets/admin.css', [], BROLEXY_SYNC_VERSION);

        wp_enqueue_script('brolexy-admin-js', BROLEXY_SYNC_PLUGIN_URL . 'assets/admin.js', ['jquery'], BROLEXY_SYNC_VERSION, true);

        wp_localize_script('brolexy-admin-js', 'broleyxAjax', [

            'ajaxurl' => admin_url('admin-ajax.php'),

            'nonce' => wp_create_nonce('brolexy_nonce')

        ]);

    }

    

    public function renderAdminPage() {

        $api_mode = get_option('brolexy_api_mode', 'test');

        $public_key = get_option('brolexy_public_key', '');

        $private_key = get_option('brolexy_private_key', '');

        $account_id = get_option('brolexy_account_id', '');

        $profit_margin = get_option('brolexy_profit_margin', '20');

        $profit_margin_type = get_option('brolexy_profit_margin_type', 'percentage');

        // Get unified sync interval (migrate from old settings if needed)
        $sync_interval = get_option('brolexy_sync_interval');
        if (empty($sync_interval)) {
            // Migrate from old separate settings
            $old_stock = get_option('brolexy_stock_sync_interval', 'daily');
            $old_price = get_option('brolexy_price_sync_interval', 'daily');
            // Use the more frequent one, or default to daily
            $sync_interval = 'daily';
            update_option('brolexy_sync_interval', $sync_interval);
        }

        $auto_fulfill = get_option('brolexy_auto_fulfill', '1');

        $product_prefix = get_option('brolexy_product_prefix', '');

        $default_category = get_option('brolexy_default_category', '');

        

        include BROLEXY_SYNC_PLUGIN_DIR . 'templates/admin-page.php';

    }

    

    public function renderImportPage() {

        include BROLEXY_SYNC_PLUGIN_DIR . 'templates/import-page.php';

    }

    

    public function renderLogsPage() {

        global $wpdb;

        $table_name = $wpdb->prefix . 'brolexy_logs';

        

        $logs = $wpdb->get_results(

            "SELECT * FROM $table_name ORDER BY created_at DESC LIMIT 100",

            ARRAY_A

        );

        

        include BROLEXY_SYNC_PLUGIN_DIR . 'templates/logs-page.php';

    }

    

    // API Methods

    private function generateSignature($message, $private_key, $use_binary = false) {

        // Remove any spaces or whitespace from the private key

        $private_key = preg_replace('/\s+/', '', $private_key);

        

        // Generate HMAC-SHA256, then base64 encode
        // API expects: base64(HmacSHA256(message)) where:
        // - message is plain string (timestamp, productID, quantity all as strings)
        // - private_key is plain string (not hex)
        // - GET requests: hex output (false), then base64 encoded (working)
        // - POST requests: binary output (true), then base64 encoded (may be required)

        $hash = hash_hmac('sha256', $message, $private_key, $use_binary);

        return base64_encode($hash);

    }

    

    private function makeApiRequest($endpoint, $method = 'GET', $body = null) {

        $public_key = get_option('brolexy_public_key');

        $private_key = get_option('brolexy_private_key');

        $api_mode = get_option('brolexy_api_mode', 'test');

        

        // Remove any spaces from keys

        $public_key = preg_replace('/\s+/', '', $public_key);

        $private_key = preg_replace('/\s+/', '', $private_key);

        // Set API base URL based on mode

        $api_base_url = $api_mode === 'live' 

            ? 'https://app.brolexy.com/api' 

            : 'https://dev.brolexy.com/api';

        

        if (empty($public_key) || empty($private_key)) {

            return new WP_Error('missing_credentials', 'API credentials not configured');

        }

        

        $time = time();

        

        // Generate signature message based on endpoint

        // API requires: plain strings in all cases for HMAC message, int for timestamp in header

        // For GET requests (accounts, products): just timestamp as STRING for signature

        // But send as INTEGER in header

        // For POST order: timestamp.productID.quantity (all as strings)

        // For GET order: timestamp.referenceID (all as strings)

        if ($endpoint === '/order' && $method === 'POST') {

            // Explicitly convert all to strings for HMAC message
            // API requires: timestamp + productID + quantity (no dots/separators)

            $message = (string)$time . (string)$body['productID'] . (string)$body['quantity'];

        } elseif (strpos($endpoint, '/get-order') !== false) {

            preg_match('/referenceID=([^&]+)/', $endpoint, $matches);

            $referenceID = $matches[1] ?? '';

            // Explicitly convert timestamp to string for HMAC message

            $message = (string)$time . '.' . $referenceID;

        } else {

            // For /accounts and /products endpoints - timestamp as string for HMAC

            $message = (string)$time;

        }

        

        // Use hex output for all requests (GET was working with hex, trying hex for POST too)
        // API manager said "plain strings" - this refers to message/key inputs, not output format
        $use_binary = false; // Use hex output for both GET and POST
        
        $signature = $this->generateSignature($message, $private_key, $use_binary);

        

        $args = [

            'method' => $method,

            'headers' => [

                'publicKey' => $public_key,

                'time' => $time, // Send as integer, not string

                'signature' => $signature,

                'Content-Type' => 'application/json'

            ],

            'timeout' => 60 // Increased timeout for large product lists

        ];

        

        if ($body !== null) {

            $args['body'] = json_encode($body);

        }

        

        $url = $api_base_url . $endpoint;

        

        // Log request details for debugging (disabled to reduce log clutter)
        // Uncomment below if you need to debug API requests
        /*
        $this->logInfo('API Request Debug', sprintf(

            'Method: %s, URL: %s, Time: %d (int), Message for HMAC: "%s", Private Key Length: %d, Signature: %s',

            $method,

            $url,

            $time,

            $message,

            strlen($private_key),

            $signature

        ));
        */

        

        $response = wp_remote_request($url, $args);

        

        if (is_wp_error($response)) {

            $this->logError('API Request Failed', $response->get_error_message());

            return $response;

        }

        

        $body_response = wp_remote_retrieve_body($response);

        $data = json_decode($body_response, true);

        

        $status_code = wp_remote_retrieve_response_code($response);

        

        if ($status_code !== 200) {

            $error_message = $data['message'] ?? $data['text'] ?? 'Unknown error';

            $this->logError('API Error', sprintf(

                'Status: %s, Error: %s, URL: %s, Response: %s',

                $status_code,

                $error_message,

                $url,

                $body_response

            ));

            return new WP_Error('api_error', $error_message);

        }

        

        return $data;

    }

    

    public function getAccounts() {

        return $this->makeApiRequest('/accounts');

    }

    

    public function getProducts() {

        return $this->makeApiRequest('/products');

    }

    

    public function submitOrder($account_id, $product_id, $quantity, $reference_id = null) {

        $body = [

            'accountID' => (int)$account_id,

            'productID' => (int)$product_id,

            'quantity' => (int)$quantity

        ];

        

        if ($reference_id) {

            $body['referenceID'] = $reference_id;

        }

        

        return $this->makeApiRequest('/order', 'POST', $body);

    }

    

    public function getOrder($reference_id) {

        return $this->makeApiRequest('/get-order?referenceID=' . $reference_id);

    }

    

    // Sync Methods

    public function syncProducts() {

        // Only sync imported products (products with _brolexy_product_id)

        global $wpdb;

        $imported_product_ids = $wpdb->get_col(

            "SELECT DISTINCT meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_brolexy_product_id'"

        );

        

        if (empty($imported_product_ids)) {

            $this->logInfo('Stock Sync', 'No imported products to sync');

            return ['success' => true, 'synced' => 0, 'updated' => 0, 'errors' => []];

        }

        

        $products = $this->getProducts();

        

        if (is_wp_error($products)) {

            $this->logError('Sync Failed', $products->get_error_message());

            return $products;

        }

        

        // Filter only imported products

        $products = array_filter($products, function($product) use ($imported_product_ids) {

            return in_array($product['productId'], $imported_product_ids);

        });

        

        $profit_margin = floatval(get_option('brolexy_profit_margin', 20));

        $profit_margin_type = get_option('brolexy_profit_margin_type', 'percentage');

        $product_prefix = get_option('brolexy_product_prefix', '');

        $default_category_id = get_option('brolexy_default_category', '');

        

        $synced_count = 0;

        $updated_count = 0;

        $errors = [];

        

        foreach ($products as $api_product) {

            $result = $this->syncSingleProduct($api_product, $profit_margin, $profit_margin_type, $product_prefix, $default_category_id);

            

            if (is_wp_error($result)) {

                $errors[] = $result->get_error_message();

            } else {

                if ($result['action'] === 'created') {

                    $synced_count++;

                } else {

                    $updated_count++;

                }

            }

        }

        

        $message = sprintf('Synced %d new products, updated %d products', $synced_count, $updated_count);

        if (!empty($errors)) {

            $message .= '. Errors: ' . implode(', ', $errors);

        }

        

        $this->logInfo('Product Sync Complete', $message);

        

        return [

            'success' => true,

            'synced' => $synced_count,

            'updated' => $updated_count,

            'errors' => $errors

        ];

    }

    

    private function syncSingleProduct($api_product, $profit_margin, $profit_margin_type, $product_prefix, $default_category_id) {

        // Check if product already exists

        $existing_product_id = $this->getProductIdByBroleyxId($api_product['productId']);

        

        // Check for custom product margin (only if product exists)

        $margin_to_use = $profit_margin;

        if ($existing_product_id) {

            $custom_margin = get_post_meta($existing_product_id, '_brolexy_custom_margin', true);

            if (!empty($custom_margin)) {

                $margin_to_use = floatval($custom_margin);

            }

        }

        

        // Calculate price with margin

        $cost_price = floatval($api_product['price']);

        if ($profit_margin_type === 'percentage') {

            $sell_price = $cost_price * (1 + ($margin_to_use / 100));

        } else {

            $sell_price = $cost_price + $margin_to_use;

        }

        

        if ($existing_product_id) {

            // For existing products, only update price and stock - preserve custom name, description, and category

            $product_id = $existing_product_id;

            $action = 'updated';

        } else {

            // For new products, set name and description from API

            $product_data = [

                'post_title' => $product_prefix . $api_product['name'],

                'post_content' => sprintf(

                    'Digital product code for %s (%s region). Instant delivery after purchase.',

                    $api_product['name'],

                    $api_product['region']

                ),

                'post_status' => 'publish',

                'post_type' => 'product'

            ];

            $product_id = wp_insert_post($product_data);

            $action = 'created';

        }

        

        if (is_wp_error($product_id)) {

            return $product_id;

        }

        

        // Update product meta

        update_post_meta($product_id, '_brolexy_product_id', $api_product['productId']);

        update_post_meta($product_id, '_brolexy_category', $api_product['category']);

        update_post_meta($product_id, '_brolexy_region', $api_product['region']);

        update_post_meta($product_id, '_brolexy_cost_price', $cost_price);

        update_post_meta($product_id, '_brolexy_currency', $api_product['priceCurrency']);

        update_post_meta($product_id, '_price', number_format($sell_price, 2, '.', ''));

        update_post_meta($product_id, '_regular_price', number_format($sell_price, 2, '.', ''));

        update_post_meta($product_id, '_stock', $api_product['inStock']);

        update_post_meta($product_id, '_manage_stock', 'yes');

        update_post_meta($product_id, '_stock_status', $api_product['inStock'] > 0 ? 'instock' : 'outofstock');

        update_post_meta($product_id, '_virtual', 'yes');

        update_post_meta($product_id, '_downloadable', 'no');

        update_post_meta($product_id, '_sold_individually', 'no');

        

        // Set category and tags only for new products (preserve custom categories for existing products)

        if ($action === 'created') {

            // Set category only for new products

            if (!empty($default_category_id)) {

                wp_set_object_terms($product_id, [(int)$default_category_id], 'product_cat');

            }

            

            // Add product tag only for new products

            wp_set_object_terms($product_id, [$api_product['category'], $api_product['region']], 'product_tag', true);

        }

        

        return ['success' => true, 'product_id' => $product_id, 'action' => $action];

    }

    

    private function getProductIdByBroleyxId($brolexy_id) {

        global $wpdb;

        $product_id = $wpdb->get_var($wpdb->prepare(

            "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_brolexy_product_id' AND meta_value = %s",

            $brolexy_id

        ));

        return $product_id;

    }

    

    public function syncStock() {

        // Only sync imported products

        global $wpdb;

        $imported_brolexy_ids = $wpdb->get_col(

            "SELECT DISTINCT meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_brolexy_product_id'"

        );

        

        if (empty($imported_brolexy_ids)) {

            return ['success' => true, 'updated' => 0];

        }

        

        $products = $this->getProducts();

        

        if (is_wp_error($products)) {

            return $products;

        }

        

        // Filter only imported products

        $products = array_filter($products, function($product) use ($imported_brolexy_ids) {

            return in_array($product['productId'], $imported_brolexy_ids);

        });

        

        $updated_count = 0;

        

        foreach ($products as $api_product) {

            $product_id = $this->getProductIdByBroleyxId($api_product['productId']);

            

            if ($product_id) {

                update_post_meta($product_id, '_stock', $api_product['inStock']);

                update_post_meta($product_id, '_stock_status', $api_product['inStock'] > 0 ? 'instock' : 'outofstock');

                $updated_count++;

            }

        }

        

        $this->logInfo('Stock Sync', "Updated stock for $updated_count products");

        

        return ['success' => true, 'updated' => $updated_count];

    }

    

    // Order Processing

    /**
     * Get Brolexy product ID for an order item. For variable products, checks variation first, then parent.
     * Returns array ['product_id' => id used for meta lookup, 'brolexy_product_id' => Brolexy API product ID or empty].
     */
    private function getBrolexyProductIdForItem($item) {
        $product_id = $item->get_product_id();
        $variation_id = method_exists($item, 'get_variation_id') ? $item->get_variation_id() : 0;
        $brolexy_product_id = '';
        $meta_product_id = $product_id;
        if ($variation_id > 0) {
            $variation_brolexy = get_post_meta($variation_id, '_brolexy_product_id', true);
            if (!empty($variation_brolexy)) {
                $brolexy_product_id = $variation_brolexy;
                $meta_product_id = $variation_id;
            }
        }
        if (empty($brolexy_product_id)) {
            $brolexy_product_id = get_post_meta($product_id, '_brolexy_product_id', true);
        }
        return ['product_id' => $meta_product_id, 'brolexy_product_id' => $brolexy_product_id];
    }

    public function processOrder($order_id, $force = false, $is_manual_request = false) {

        // Prevent timeout - increase limits // TODO: add it if its needed

        // Immediate logging to confirm function is called
        // is_manual_request = true when triggered from admin (Smart or Force) - skips price check
        $log_prefix = ($force || $is_manual_request) ? '[MANUAL] ' : '';
        $this->logInfo('Order Processing Started', $log_prefix . "Order #$order_id - Function called");

        

        $auto_fulfill = get_option('brolexy_auto_fulfill', '1');

        

        // Skip only if auto-fulfill is disabled AND this is not a manual/forced request
        if ($auto_fulfill !== '1' && !$force) {

            $this->logInfo('Order Processing Skipped', $log_prefix . "Order #$order_id - Auto-fulfill disabled");

            return;

        }

        

        $order = wc_get_order($order_id);

        

        if (!$order) {

            $this->logError('Order Processing Failed', "Order #$order_id - Order not found");

            return;

        }

        

        // Check if already fulfilled (unless forced or manual)
        // When manual "Smart - Skip Fulfilled" is used, do NOT skip the whole order here:
        // the order may be partially fulfilled (one item done, one not); we skip only per-item in the loop.
        if (!$force && !$is_manual_request && $order->get_meta('_brolexy_fulfilled')) {
            $this->logInfo('Order Processing Skipped', $log_prefix . "Order #$order_id - Already fulfilled");
            return;
        }
        
        // If forced, clear the fulfilled flag to allow reprocessing
        if ($force && $order->get_meta('_brolexy_fulfilled')) {
            $order->delete_meta_data('_brolexy_fulfilled');
            $order->add_order_note('Brolexy: Manual reprocessing initiated - cleared fulfilled flag');
            $this->logInfo('Order Reprocessing', $log_prefix . "Order #$order_id - Force reprocessing, cleared fulfilled flag");
        }

        

        // Check if order has any Brolexy products (include variation-level Brolexy products)
        $has_brolexy_products = false;
        foreach ($order->get_items() as $item_id => $item) {
            $resolved = $this->getBrolexyProductIdForItem($item);
            if (!empty($resolved['brolexy_product_id'])) {
                $has_brolexy_products = true;
                break;
            }
        }

        

        if (!$has_brolexy_products) {

            $this->logInfo('Order Processing Skipped', "Order #$order_id - No Brolexy products in order");

            return;

        }

        

        $this->logInfo('Order Processing', "Order #$order_id - Starting fulfillment process");

        

        $account_id = get_option('brolexy_account_id');

        

        $has_price_mismatch = false;

        $has_stock_issue = false;

        $price_check_details = [];
        
        // Skip price/stock verification when admin manually triggers (Smart or Force)
        $skip_price_check = $is_manual_request;
        
        $fresh_index = [];
        $fresh_products = [];

        // Only do price/stock verification for automatic processing
        if (!$skip_price_check) {
            // OPTIMIZATION: Collect only the Brolexy product IDs we need from this order (include variation-level)
            $needed_brolexy_ids = [];
            foreach ($order->get_items() as $item_id => $item) {
                $resolved = $this->getBrolexyProductIdForItem($item);
                if (!empty($resolved['brolexy_product_id'])) {
                    $needed_brolexy_ids[] = (int)$resolved['brolexy_product_id'];
                }
            }
            
            if (empty($needed_brolexy_ids)) {
                $this->logInfo('Order Processing Skipped', "Order #$order_id - No Brolexy products in order");
                return;
            }
            
            $needed_brolexy_ids = array_unique($needed_brolexy_ids);
            $this->logInfo('Order Processing', "Order #$order_id - Need to verify " . count($needed_brolexy_ids) . " product(s)");

            // Wrap API call in try-catch to prevent crashes

            try {

                $this->logInfo('Order Processing', "Order #$order_id - Fetching fresh product data from API");

                

                // SAFETY: Get fresh product data from API before fulfilling
                // Note: API returns all products, but we'll filter to only needed ones immediately

                $fresh_products = $this->getProducts();

            

            if (is_wp_error($fresh_products)) {

                $error_msg = $fresh_products->get_error_message();

                $order->add_order_note('Brolexy: Cannot fulfill - Failed to verify current prices from API: ' . $error_msg);

                $this->logError('Order Processing Failed', "Order #$order_id - API error: $error_msg");

                

                // Set to on-hold instead of leaving in processing

                $order->set_status('on-hold', 'Brolexy: Could not verify prices/stock from API');

                $order->save();

                return;

            }

            

            // OPTIMIZATION: Filter immediately to only keep products we need (reduces memory and processing)
            $fresh_products = array_filter($fresh_products, function($product) use ($needed_brolexy_ids) {
                return isset($product['productId']) && in_array((int)$product['productId'], $needed_brolexy_ids);
            });
            
                $this->logInfo('Order Processing', "Order #$order_id - Filtered to " . count($fresh_products) . " relevant product(s) from API");

                

            } catch (Exception $e) {

                $error_msg = $e->getMessage();

                $this->logError('Order Processing Exception', "Order #$order_id - Exception: $error_msg");

                $order->add_order_note('Brolexy: Error during fulfillment - ' . $error_msg);

                $order->set_status('on-hold', 'Brolexy: Error during processing');

                $order->save();

                return;

            }

            

            // Build index of fresh products (now only contains needed products)

            foreach ($fresh_products as $p) {

                if (isset($p['productId'])) {

                    $fresh_index[$p['productId']] = $p;

                }

            }

            

            $this->logInfo('Order Processing', "Order #$order_id - Starting price/stock verification");

            

            // First pass: Check all prices and stock before fulfilling anything

            foreach ($order->get_items() as $item_id => $item) {

            $product_id = $item->get_product_id();

            $brolexy_product_id = get_post_meta($product_id, '_brolexy_product_id', true);

            

            if (empty($brolexy_product_id)) {

                continue; // Not a Brolexy product

            }

            

            // Check if product exists in fresh API data

            if (!isset($fresh_index[$brolexy_product_id])) {

                $has_stock_issue = true;

                $price_check_details[] = sprintf(

                    '❌ %s - Product not found in Brolexy API (may be discontinued)',

                    $item->get_name()

                );

                continue;

            }

            

            $fresh_data = $fresh_index[$brolexy_product_id];

            $current_brolexy_price = floatval($fresh_data['price']);

            $current_stock = intval($fresh_data['inStock']);

            $quantity_ordered = $item->get_quantity();

            

            // Check stock availability

            if ($current_stock < $quantity_ordered) {

                $has_stock_issue = true;

                $price_check_details[] = sprintf(

                    '❌ %s - Insufficient stock (need %d, available %d)',

                    $item->get_name(),

                    $quantity_ordered,

                    $current_stock

                );

                continue;

            }

            

            // Calculate expected price with current margin

            $profit_margin = floatval(get_option('brolexy_profit_margin', 20));

            $profit_margin_type = get_option('brolexy_profit_margin_type', 'percentage');

            

            // Check for custom product margin

            $custom_margin = get_post_meta($product_id, '_brolexy_custom_margin', true);

            if (!empty($custom_margin)) {

                $profit_margin = floatval($custom_margin);

            }

            

            $expected_price = $current_brolexy_price;

            if ($profit_margin_type === 'percentage') {

                $expected_price = $current_brolexy_price * (1 + ($profit_margin / 100));

            } else {

                $expected_price = $current_brolexy_price + $profit_margin;

            }

            

            // Get the price customer actually paid (per unit)

            $price_paid = floatval($item->get_total()) / $quantity_ordered;

            

            // Allow 1% tolerance for rounding differences

            $tolerance = $expected_price * 0.01;

            $price_difference = $price_paid - $expected_price;

            

            if (abs($price_difference) > $tolerance) {

                $has_price_mismatch = true;

                

                if ($price_difference < 0) {

                    // Customer paid LESS than expected

                    $price_check_details[] = sprintf(

                        '❌ %s - Price too LOW (Customer paid: €%.2f, Expected: €%.2f, Brolexy cost: €%.2f) - YOU WILL LOSE MONEY!',

                        $item->get_name(),

                        $price_paid,

                        $expected_price,

                        $current_brolexy_price

                    );

                } else {

                    // Customer paid MORE than expected (unusual but log it)

                    $price_check_details[] = sprintf(

                        '⚠️ %s - Price higher than expected (Customer paid: €%.2f, Expected: €%.2f, Brolexy cost: €%.2f)',

                        $item->get_name(),

                        $price_paid,

                        $expected_price,

                        $current_brolexy_price

                    );

                }

            } else {

                // Price is OK

                $price_check_details[] = sprintf(

                    '✅ %s - Price OK (€%.2f, Brolexy cost: €%.2f, Stock: %d)',

                    $item->get_name(),

                    $price_paid,

                    $current_brolexy_price,

                    $current_stock

                );

            }

            } // End of price check loop
            
            $this->logInfo('Order Processing', "Order #$order_id - Verification complete. Price issues: " . ($has_price_mismatch ? 'YES' : 'NO') . ", Stock issues: " . ($has_stock_issue ? 'YES' : 'NO'));
        } else {
            // Manual processing - skip price verification
            $this->logInfo('Manual Processing', $log_prefix . "Order #$order_id - Skipping price/stock verification (manual processing)");
        }

        

        // If there are critical issues, DO NOT fulfill (skip check for manual processing)

        if (!$skip_price_check && ($has_price_mismatch || $has_stock_issue)) {

            $order->set_status('on-hold', 'Brolexy: Auto-fulfillment BLOCKED due to price/stock issues');

            

            $note_message = "⚠️ BROLEXY AUTO-FULFILLMENT BLOCKED\n\n";

            $note_message .= "Please review before manually processing:\n\n";

            $note_message .= implode("\n", $price_check_details);

            $note_message .= "\n\nACTION REQUIRED: ";

            

            if ($has_price_mismatch) {

                $note_message .= "Update product prices and retry, OR manually fulfill if price is acceptable.";

            } else {

                $note_message .= "Product out of stock or discontinued. Contact customer.";

            }

            

            $order->add_order_note($note_message);

            $order->save();

            

            $this->logError(

                'Order Fulfillment BLOCKED',

                sprintf(

                    "Order #%d blocked due to price/stock mismatch:\n%s",

                    $order_id,

                    implode("\n", $price_check_details)

                )

            );

            

            return; // STOP - Do not fulfill

        }

        

        // All checks passed - proceed with fulfillment
        if (!$skip_price_check) {
            $order->add_order_note("✅ Brolexy: Price and stock verification passed. Proceeding with fulfillment...\n\n" . implode("\n", $price_check_details));
        } else {
            $order->add_order_note("✅ Brolexy: Manual processing - Skipping price/stock verification. Proceeding with fulfillment...");
        }

        

        $this->logInfo('Order Processing', "Order #$order_id - Starting Brolexy order submission");

        

        $fulfilled_items = 0;

        $failed_items = 0;
        
        // Collect all pins for email delivery
        $all_pins_data = [];

        foreach ($order->get_items() as $item_id => $item) {

            $resolved = $this->getBrolexyProductIdForItem($item);
            $brolexy_product_id = $resolved['brolexy_product_id'];

            if (empty($brolexy_product_id)) {

                continue; // Not a Brolexy product

            }
            
            // Check if this item already has pins (already fulfilled)
            $existing_pins = wc_get_order_item_meta($item_id, '_brolexy_pins', true);
            if (!empty($existing_pins) && !$force) {
                // Item already fulfilled, skip unless forced
                $this->logInfo('Manual Processing - Item Skipped', $log_prefix . "Order #$order_id - Item '{$item->get_name()}' already has pins, skipping to avoid duplicate order");
                $order->add_order_note(sprintf(
                    'Brolexy: Item "%s" already fulfilled (has pins), skipped during manual processing',
                    $item->get_name()
                ));
                
                // Still collect existing pins for email
                $existing_pins_array = explode("\n", $existing_pins);
                $all_pins_data[] = [
                    'product_name' => $item->get_name(),
                    'quantity' => $item->get_quantity(),
                    'pins' => $existing_pins_array,
                    'brolexy_order_id' => wc_get_order_item_meta($item_id, '_brolexy_order_id', true),
                    'already_fulfilled' => true
                ];
                continue;
            }

            

            $quantity = $item->get_quantity();

            $reference_id = 'woo_' . $order_id . '_' . $item_id;

            

            $this->logInfo('Order Processing', "Order #$order_id - Submitting to Brolexy: Product ID $brolexy_product_id, Qty $quantity");

            

            try {

                // Submit order to Brolexy

                $result = $this->submitOrder($account_id, $brolexy_product_id, $quantity, $reference_id);

                

                if (is_wp_error($result)) {

                    $failed_items++;

                    $order->add_order_note(sprintf(

                        'Brolexy: Failed to fulfill item %s - %s',

                        $item->get_name(),

                        $result->get_error_message()

                    ));

                    $this->logError('Order Fulfillment Failed', "Order #$order_id, Item #$item_id: " . $result->get_error_message());

                    

                    // Set order to on-hold if any item fails

                    $order->set_status('on-hold', 'Brolexy: Fulfillment failed for one or more items');

                    $order->save();

                    continue;

                }

                

                // Save pins to order meta

                if (isset($result['pins']) && is_array($result['pins'])) {

                    $pins = array_map(function($pin_data) {

                        return $pin_data['pin'];

                    }, $result['pins']);

                    

                    wc_add_order_item_meta($item_id, '_brolexy_pins', implode("\n", $pins));

                    wc_add_order_item_meta($item_id, '_brolexy_order_id', $result['orderID']);

                    

                    // Add pins as visible order item meta

                    $item->add_meta_data('Product Keys', implode("\n", $pins), true);

                    $item->save();
                    
                    // Collect pins for email delivery
                    $all_pins_data[] = [
                        'product_name' => $item->get_name(),
                        'quantity' => $quantity,
                        'pins' => $pins,
                        'brolexy_order_id' => $result['orderID']
                    ];

                    $order->add_order_note(sprintf(

                        '✅ Brolexy: Successfully fulfilled %s x%d. Order ID: %s',

                        $item->get_name(),

                        $quantity,

                        $result['orderID']

                    ));

                    

                    $this->logInfo('Order Fulfilled', "Order #$order_id, Item: {$item->get_name()}, Brolexy Order: {$result['orderID']}");

                    $fulfilled_items++;

                } else {

                    $failed_items++;

                    $this->logError('Order Fulfillment Failed', "Order #$order_id, Item #$item_id: No pins in response");

                }

                

            } catch (Exception $e) {

                $failed_items++;

                $error_msg = $e->getMessage();

                $this->logError('Order Processing Exception', "Order #$order_id, Item #$item_id - Exception: $error_msg");

                $order->add_order_note("Brolexy: Error fulfilling {$item->get_name()} - $error_msg");

            }

        }

        

        // Only mark as fulfilled if we actually processed new items
        $has_new_fulfillments = $fulfilled_items > 0;
        
        if ($has_new_fulfillments) {
            $order->update_meta_data('_brolexy_fulfilled', true);
        }
        
        // If all items were fulfilled successfully (either newly or already), set order to completed
        // Only send email if we processed NEW items (to avoid duplicate emails)
        $total_brolexy_items = 0;
        $total_fulfilled = 0;
        foreach ($order->get_items() as $item_id => $item) {
            $resolved = $this->getBrolexyProductIdForItem($item);
            if (!empty($resolved['brolexy_product_id'])) {
                $total_brolexy_items++;
                $existing_pins = wc_get_order_item_meta($item_id, '_brolexy_pins', true);
                if (!empty($existing_pins)) {
                    $total_fulfilled++;
                }
            }
        }
        
        if ($total_fulfilled === $total_brolexy_items && $total_brolexy_items > 0) {
            // All Brolexy items already had pins - we skipped all, made no API calls this run
            if ($failed_items === 0) {
                $order->set_status('completed', 'Brolexy: All items fulfilled successfully');
            }
            $order->save();
            
            // Only send email if we processed NEW items (has_new_fulfillments)
            // This prevents duplicate emails when manually reprocessing already-fulfilled orders
            if ($has_new_fulfillments) {
                $this->sendPinsEmail($order, $all_pins_data);
                $this->logInfo('Order Completed', $log_prefix . "Order #$order_id - Set to completed and pins email sent");
            } else {
                $this->logInfo('Order Already Complete', $log_prefix . "Order #$order_id - All $total_brolexy_items Brolexy item(s) already had pins. No API call made.");
            }
        } else {
            $order->save();
            // When using "Skip Fulfilled", add a clear note when only some items were fulfilled/skipped
            if ($is_manual_request && ($fulfilled_items > 0 || $total_fulfilled > 0)) {
                $skipped_count = $total_fulfilled; // items that had pins and were skipped
                $note_parts = [];
                if ($fulfilled_items > 0) {
                    $note_parts[] = sprintf(_n('%d item(s) processed', '%d items processed', $fulfilled_items, 'brolexy-woo-sync'), $fulfilled_items);
                }
                if ($skipped_count > 0) {
                    $note_parts[] = sprintf(_n('%d item(s) already had pins (skipped)', '%d items already had pins (skipped)', $skipped_count, 'brolexy-woo-sync'), $skipped_count);
                }
                if ($failed_items > 0) {
                    $note_parts[] = sprintf(_n('%d item(s) failed', '%d items failed', $failed_items, 'brolexy-woo-sync'), $failed_items);
                }
                if (!empty($note_parts)) {
                    $order->add_order_note('Brolexy: ' . implode(', ', $note_parts) . '.');
                }
            }
        }

        $this->logInfo('Order Processing Complete', $log_prefix . "Order #$order_id - Fulfilled: $fulfilled_items items, Failed: $failed_items items");

    }
    
    /**
     * Add custom order actions to WooCommerce order page
     */
    public function addOrderActions($actions) {
        global $theorder;
        
        // Check if order has Brolexy products (include variation-level)
        $has_brolexy = false;
        foreach ($theorder->get_items() as $item) {
            $resolved = $this->getBrolexyProductIdForItem($item);
            if (!empty($resolved['brolexy_product_id'])) {
                $has_brolexy = true;
                break;
            }
        }
        
        if ($has_brolexy) {
            $actions['brolexy_manual_fulfill'] = __('Process with Brolexy (Smart - Skip Fulfilled)', 'brolexy-woo-sync');
            $actions['brolexy_force_reprocess'] = __('⚠️ Force Reprocess All Items', 'brolexy-woo-sync');
        }
        
        return $actions;
    }
    
    /**
     * Handle manual order action from WooCommerce order page
     * Smart processing: only processes items that aren't already fulfilled
     */
    public function handleManualOrderAction($order) {
        $order_id = $order->get_id();
        
        // Check if order has any unfulfilled items (use variation when present for Brolexy meta)
        $has_unfulfilled = false;
        foreach ($order->get_items() as $item_id => $item) {
            $resolved = $this->getBrolexyProductIdForItem($item);
            if (!empty($resolved['brolexy_product_id'])) {
                $existing_pins = wc_get_order_item_meta($item_id, '_brolexy_pins', true);
                if (empty($existing_pins)) {
                    $has_unfulfilled = true;
                    break;
                }
            }
        }
        
        if (!$has_unfulfilled) {
            // All items already fulfilled
            add_action('admin_notices', function() use ($order_id) {
                echo '<div class="notice notice-info is-dismissible"><p>';
                echo sprintf(__('Order #%d: All items are already fulfilled. No action needed. Use "Force Reprocess" if you need to reprocess everything.', 'brolexy-woo-sync'), $order_id);
                echo '</p></div>';
            });
            return;
        }
        
        // Process order WITHOUT force - will skip already fulfilled items; skip price check (manual)
        $this->processOrder($order_id, false, true);
        
        // Add admin notice
        add_action('admin_notices', function() use ($order_id) {
            echo '<div class="notice notice-success is-dismissible"><p>';
            echo sprintf(__('Order #%d is being processed with Brolexy. Only unfulfilled items will be processed. Check order notes and logs for details.', 'brolexy-woo-sync'), $order_id);
            echo '</p></div>';
        });
    }
    
    /**
     * AJAX handler for manual order processing
     */
    public function ajaxManualProcessOrder() {
        check_ajax_referer('brolexy_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized']);
        }
        
        $order_id = intval($_POST['order_id'] ?? 0);
        $force = isset($_POST['force']) && $_POST['force'] === 'true';
        
        if (!$order_id) {
            wp_send_json_error(['message' => 'Invalid order ID']);
        }
        
        $order = wc_get_order($order_id);
        
        if (!$order) {
            wp_send_json_error(['message' => 'Order not found']);
        }
        
        // Process order - use force only if explicitly requested; skip price check (manual)
        $this->processOrder($order_id, $force, true);
        
        $message = $force 
            ? 'Order processing initiated (FORCE MODE - will reprocess all items). Check order notes and logs for details.'
            : 'Order processing initiated. Only unfulfilled items will be processed. Check order notes and logs for details.';
        
        wp_send_json_success([
            'message' => $message,
            'order_id' => $order_id
        ]);
    }
    
    /**
     * Handle force reprocess action - reprocesses ALL items even if already fulfilled
     */
    public function handleForceReprocessAction($order) {
        $order_id = $order->get_id();
        
        // Clear fulfilled flag to allow reprocessing
        $order->delete_meta_data('_brolexy_fulfilled');
        $order->add_order_note('Brolexy: Force reprocessing initiated - will reprocess all items');
        $order->save();
        
        // Process with force flag; skip price check (manual)
        $this->processOrder($order_id, true, true);
        
        // Add admin notice
        add_action('admin_notices', function() use ($order_id) {
            echo '<div class="notice notice-warning is-dismissible"><p>';
            echo sprintf(__('⚠️ Order #%d: Force reprocessing initiated. ALL items will be reprocessed, which may create duplicate orders and charges. Check order notes and logs for details.', 'brolexy-woo-sync'), $order_id);
            echo '</p></div>';
        });
    }
    
    /**
     * Send product keys (pins) via email to customer
     */
    private function sendPinsEmail($order, $pins_data) {
        if (empty($pins_data)) {
            return;
        }
        
        $customer_email = $order->get_billing_email();
        if (empty($customer_email)) {
            $this->logError('Email Failed', "Order #{$order->get_id()} - No customer email address");
            return;
        }
        
        // Build email content
        $subject = sprintf('Your Product Keys - Order #%s', $order->get_order_number());
        
        $message = "Hello,\n\n";
        $message .= "Thank you for your order! Your product keys are ready.\n\n";
        $message .= "Order Details:\n";
        $message .= "Order Number: #" . $order->get_order_number() . "\n";
        $message .= "Order Date: " . $order->get_date_created()->date_i18n() . "\n\n";
        $message .= "Product Keys:\n";
        $message .= str_repeat("=", 50) . "\n\n";
        
        foreach ($pins_data as $item_data) {
            $message .= sprintf("Product: %s (Quantity: %d)\n", $item_data['product_name'], $item_data['quantity']);
            $message .= "Keys:\n";
            foreach ($item_data['pins'] as $pin) {
                $message .= "  • " . $pin . "\n";
            }
            $message .= "\n";
        }
        
        $message .= str_repeat("=", 50) . "\n\n";
        $message .= "If you have any questions, please contact us.\n\n";
        $message .= "Thank you for your purchase!";
        
        // Send email using WordPress mail function
        $headers = array('Content-Type: text/plain; charset=UTF-8');
        $sent = wp_mail($customer_email, $subject, $message, $headers);
        
        if ($sent) {
            $order->add_order_note('Product keys sent to customer via email: ' . $customer_email);
            $this->logInfo('Email Sent', "Order #{$order->get_id()} - Pins email sent to {$customer_email}");
        } else {
            $order->add_order_note('⚠️ Failed to send product keys email to: ' . $customer_email);
            $this->logError('Email Failed', "Order #{$order->get_id()} - Failed to send pins email to {$customer_email}");
        }
    }

    

    // Cron Methods

    public function addCronSchedules($schedules) {

        $schedules['thirtyminutes'] = [

            'interval' => 1800,

            'display' => __('Every 30 Minutes')

        ];

        $schedules['sixhours'] = [

            'interval' => 21600,

            'display' => __('Every 6 Hours')

        ];

        $schedules['twicehourly'] = [

            'interval' => 1800,

            'display' => __('Twice Hourly')

        ];

        $schedules['everyfiveminutes'] = [

            'interval' => 300,

            'display' => __('Every 5 Minutes')

        ];

        return $schedules;

    }

    

    /**
     * Unified cron handler for syncing both stock and prices
     */
    public function cronSyncProducts() {
        // Sync both stock and prices in one function
        $this->syncProducts(false); // false = automatic with delays
    }

    

    // Logging

    private function log($type, $title, $message) {

        global $wpdb;

        $table_name = $wpdb->prefix . 'brolexy_logs';

        

        $wpdb->insert(

            $table_name,

            [

                'log_type' => $type,

                'title' => $title,

                'message' => $message,

                'created_at' => current_time('mysql')

            ],

            ['%s', '%s', '%s', '%s']

        );

    }

    

    private function logInfo($title, $message) {

        $this->log('info', $title, $message);

    }

    

    private function logError($title, $message) {

        $this->log('error', $title, $message);

    }

    

    // AJAX Handlers

    public function ajaxTestConnection() {

        check_ajax_referer('brolexy_nonce', 'nonce');

        

        if (!current_user_can('manage_options')) {

            wp_send_json_error(['message' => 'Unauthorized']);

        }

        

        $accounts = $this->getAccounts();

        

        if (is_wp_error($accounts)) {

            wp_send_json_error(['message' => $accounts->get_error_message()]);

        }

        

        wp_send_json_success(['accounts' => $accounts]);

    }

    

    public function ajaxSyncProducts() {

        check_ajax_referer('brolexy_nonce', 'nonce');

        

        if (!current_user_can('manage_options')) {

            wp_send_json_error(['message' => 'Unauthorized']);

        }

        

        $result = $this->syncProducts();

        

        if (is_wp_error($result)) {

            wp_send_json_error(['message' => $result->get_error_message()]);

        }

        

        wp_send_json_success($result);

    }

    

    public function ajaxManualSync() {

        check_ajax_referer('brolexy_nonce', 'nonce');

        

        if (!current_user_can('manage_options')) {

            wp_send_json_error(['message' => 'Unauthorized']);

        }

        

        // Unified sync - syncs both stock and prices
        $result = $this->syncProducts(true); // true = manual, no delays

        

        if (is_wp_error($result)) {

            wp_send_json_error(['message' => $result->get_error_message()]);

        }

        

        wp_send_json_success($result);

    }

    

    public function ajaxSearchProducts() {

        check_ajax_referer('brolexy_nonce', 'nonce');

        

        if (!current_user_can('manage_options')) {

            wp_send_json_error(['message' => 'Unauthorized']);

        }

        

        $search_term = sanitize_text_field($_POST['search'] ?? '');

        $category = sanitize_text_field($_POST['category'] ?? '');

        $region = sanitize_text_field($_POST['region'] ?? '');

        

        $products = $this->getProducts();

        

        if (is_wp_error($products)) {

            wp_send_json_error(['message' => $products->get_error_message()]);

        }

        

        // Get already imported product IDs

        global $wpdb;

        $imported_ids = $wpdb->get_col(

            "SELECT DISTINCT meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_brolexy_product_id'"

        );

        

        // Filter products

        $filtered = array_filter($products, function($product) use ($search_term, $category, $region, $imported_ids) {

            // Skip already imported

            if (in_array($product['productId'], $imported_ids)) {

                return false;

            }

            

            $matches = true;

            

            if (!empty($search_term)) {

                $matches = $matches && (

                    stripos($product['name'], $search_term) !== false ||

                    stripos($product['category'], $search_term) !== false ||

                    stripos($product['region'], $search_term) !== false

                );

            }

            

            if (!empty($category)) {

                $matches = $matches && ($product['category'] === $category);

            }

            

            if (!empty($region)) {

                $matches = $matches && ($product['region'] === $region);

            }

            

            return $matches;

        });

        

        // Get unique categories and regions for filters

        $categories = array_values(array_unique(array_column($products, 'category')));

        $regions = array_values(array_unique(array_column($products, 'region')));

        sort($categories);

        sort($regions);

        

        wp_send_json_success([

            'products' => array_values($filtered),

            'categories' => $categories,

            'regions' => $regions,

            'total' => count($filtered)

        ]);

    }

    

    public function ajaxImportProduct() {

        check_ajax_referer('brolexy_nonce', 'nonce');

        

        if (!current_user_can('manage_options')) {

            wp_send_json_error(['message' => 'Unauthorized']);

        }

        

        $product_id = intval($_POST['product_id'] ?? 0);

        

        if (!$product_id) {

            wp_send_json_error(['message' => 'Invalid product ID']);

        }

        

        // Check if already imported

        $existing = $this->getProductIdByBroleyxId($product_id);

        if ($existing) {

            wp_send_json_error(['message' => 'Product already imported']);

        }

        

        // Get product details from API

        $products = $this->getProducts();

        

        if (is_wp_error($products)) {

            wp_send_json_error(['message' => $products->get_error_message()]);

        }

        

        $api_product = null;

        foreach ($products as $product) {

            if ($product['productId'] == $product_id) {

                $api_product = $product;

                break;

            }

        }

        

        if (!$api_product) {

            wp_send_json_error(['message' => 'Product not found in Brolexy']);

        }

        

        // Import the product (no delay for single manual import)

        $profit_margin = floatval(get_option('brolexy_profit_margin', 20));

        $profit_margin_type = get_option('brolexy_profit_margin_type', 'percentage');

        $product_prefix = get_option('brolexy_product_prefix', '');

        $default_category_id = get_option('brolexy_default_category', '');

        

        $result = $this->syncSingleProduct($api_product, $profit_margin, $profit_margin_type, $product_prefix, $default_category_id);

        

        if (is_wp_error($result)) {

            wp_send_json_error(['message' => $result->get_error_message()]);

        }

        

        $this->logInfo('Product Import', "Manually imported: {$api_product['name']}");

        

        // Get product details for the response
        $product = wc_get_product($result['product_id']);
        $cost_price = get_post_meta($result['product_id'], '_brolexy_cost_price', true);
        $stock = get_post_meta($result['product_id'], '_stock', true);
        $brolexy_id = get_post_meta($result['product_id'], '_brolexy_product_id', true);
        
        wp_send_json_success([

            'message' => 'Product imported successfully',

            'product_id' => $result['product_id'],

            'product_name' => $api_product['name'],
            
            'cost_price' => $cost_price ?: '0.00',
            
            'stock' => intval($stock),
            
            'brolexy_id' => $brolexy_id,
            
            'edit_link' => get_edit_post_link($result['product_id'], 'raw')

        ]);

    }

    

    public function ajaxDeleteImportedProduct() {

        check_ajax_referer('brolexy_nonce', 'nonce');

        

        if (!current_user_can('manage_options')) {

            wp_send_json_error(['message' => 'Unauthorized']);

        }

        

        $product_id = intval($_POST['product_id'] ?? 0);

        

        if (!$product_id) {

            wp_send_json_error(['message' => 'Invalid product ID']);

        }

        

        $product = wc_get_product($product_id);

        

        if (!$product) {

            wp_send_json_error(['message' => 'Product not found']);

        }

        

        $product_name = $product->get_name();

        

        // Delete the product

        $result = wp_delete_post($product_id, true);

        

        if (!$result) {

            wp_send_json_error(['message' => 'Failed to delete product']);

        }

        

        $this->logInfo('Product Deleted', "Deleted imported product: {$product_name}");

        

        wp_send_json_success(['message' => 'Product deleted successfully']);

    }

    

    // Product Meta Box

    public function addProductMetaBox() {

        add_meta_box(

            'brolexy_product_info',

            'Brolexy Product Info',

            [$this, 'renderProductMetaBox'],

            'product',

            'side',

            'default'

        );

    }

    

    public function renderProductMetaBox($post) {

        $brolexy_product_id = get_post_meta($post->ID, '_brolexy_product_id', true);

        

        if (empty($brolexy_product_id)) {

            echo '<p>This is not a Brolexy product.</p>';

            return;

        }

        

        $category = get_post_meta($post->ID, '_brolexy_category', true);

        $region = get_post_meta($post->ID, '_brolexy_region', true);

        $cost_price = get_post_meta($post->ID, '_brolexy_cost_price', true);

        $currency = get_post_meta($post->ID, '_brolexy_currency', true);

        

        echo '<p><strong>Brolexy ID:</strong> ' . esc_html($brolexy_product_id) . '</p>';

        echo '<p><strong>Category:</strong> ' . esc_html($category) . '</p>';

        echo '<p><strong>Region:</strong> ' . esc_html($region) . '</p>';

        echo '<p><strong>Cost Price:</strong> ' . esc_html($cost_price) . ' ' . esc_html($currency) . '</p>';

        

        wp_nonce_field('brolexy_product_meta', 'brolexy_product_meta_nonce');

        

        echo '<hr>';

        // Get global margin type to show user what format to use
        $global_margin_type = get_option('brolexy_profit_margin_type', 'percentage');
        $margin_type_label = $global_margin_type === 'percentage' ? '%' : '€';
        $margin_type_text = $global_margin_type === 'percentage' ? 'percentage' : 'fixed amount';
        
        echo '<p><label><strong>Custom Profit Margin:</strong></label><br>';
        echo '<input type="number" name="brolexy_custom_margin" value="' . esc_attr(get_post_meta($post->ID, '_brolexy_custom_margin', true)) . '" step="0.01" style="width: 100%;" placeholder="Leave empty for global margin">';
        echo '<span class="description" style="display: block; margin-top: 5px; font-size: 12px; color: #666;">';
        echo 'Uses ' . esc_html($margin_type_text) . ' format (same as global setting). ';
        echo 'Example: If global is ' . esc_html($margin_type_text) . ', enter ' . ($global_margin_type === 'percentage' ? '25' : '5.00') . ' for ' . ($global_margin_type === 'percentage' ? '25%' : '€5.00') . ' margin.';
        echo '</span></p>';

    }

    

    public function saveProductMeta($post_id) {

        if (!isset($_POST['brolexy_product_meta_nonce']) || !wp_verify_nonce($_POST['brolexy_product_meta_nonce'], 'brolexy_product_meta')) {

            return;

        }

        

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {

            return;

        }

        

        if (isset($_POST['brolexy_custom_margin'])) {

            update_post_meta($post_id, '_brolexy_custom_margin', sanitize_text_field($_POST['brolexy_custom_margin']));

        }

    }

    

    // Activation

    public function activate() {

        global $wpdb;

        $table_name = $wpdb->prefix . 'brolexy_logs';

        $charset_collate = $wpdb->get_charset_collate();

        

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (

            id bigint(20) NOT NULL AUTO_INCREMENT,

            log_type varchar(20) NOT NULL,

            title varchar(255) NOT NULL,

            message text NOT NULL,

            created_at datetime NOT NULL,

            PRIMARY KEY (id),

            KEY log_type (log_type),

            KEY created_at (created_at)

        ) $charset_collate;";

        

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        dbDelta($sql);

        

        // Schedule unified sync based on settings (default: daily)
        $sync_interval = get_option('brolexy_sync_interval', 'daily');
        
        if (!empty($sync_interval) && $sync_interval !== 'never') {
            if (!wp_next_scheduled('brolexy_sync_products_cron')) {
                wp_schedule_event(time(), $sync_interval, 'brolexy_sync_products_cron');
            }
        }

    }

    

    public function deactivate() {

        wp_clear_scheduled_hook('brolexy_sync_products_cron');

    }

}



// Initialize plugin

BrolexyWooSync::getInstance();