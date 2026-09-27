<div class="wrap">

    <h1>Brolexy WooCommerce Sync Settings</h1>

    

    <div class="brolexy-admin-container">

        <div class="brolexy-notice" id="brolexy-notice" style="display:none;"></div>

        

        <form method="post" action="options.php">

            <?php settings_fields('brolexy_sync_options'); ?>

            

            <table class="form-table">

                <tr>

                    <th colspan="2"><h2>API Configuration</h2></th>

                </tr>

                

                <tr>

                    <th scope="row">

                        <label for="brolexy_api_mode">API Mode</label>

                    </th>

                    <td>

                        <select name="brolexy_api_mode" id="brolexy_api_mode" class="regular-text">

                            <option value="test" <?php selected($api_mode, 'test'); ?>>Test Mode (dev.brolexy.com)</option>

                            <option value="live" <?php selected($api_mode, 'live'); ?>>Live Mode (app.brolexy.com)</option>

                        </select>

                        <p class="description">

                            <strong>Test Mode:</strong> Use for testing with test API keys (dev.brolexy.com)<br>

                            <strong>Live Mode:</strong> Use for production with live API keys (app.brolexy.com)

                        </p>

                    </td>

                </tr>

                

                <tr>

                    <th colspan="2"><h2>API Credentials</h2></th>

                </tr>

                

                <tr>

                    <th scope="row">

                        <label for="brolexy_public_key">Public Key</label>

                    </th>

                    <td>

                        <input type="text" 

                               id="brolexy_public_key" 

                               name="brolexy_public_key" 

                               value="<?php echo esc_attr($public_key); ?>" 

                               class="regular-text" 

                               required>

                        <p class="description">Your Brolexy API public key</p>

                    </td>

                </tr>

                

                <tr>

                    <th scope="row">

                        <label for="brolexy_private_key">Private Key</label>

                    </th>

                    <td>

                        <input type="password" 

                               id="brolexy_private_key" 

                               name="brolexy_private_key" 

                               value="<?php echo esc_attr($private_key); ?>" 

                               class="regular-text" 

                               required>

                        <p class="description">Your Brolexy API private key (used for signature generation)</p>

                    </td>

                </tr>

                

                <tr>

                    <th scope="row">

                        <label for="brolexy_account_id">Account ID</label>

                    </th>

                    <td>

                        <input type="number" 

                               id="brolexy_account_id" 

                               name="brolexy_account_id" 

                               value="<?php echo esc_attr($account_id); ?>" 

                               class="small-text">

                        <p class="description">Your Brolexy account ID for order fulfillment (optional - only needed if required by API)</p>

                    </td>

                </tr>

                

                <tr>

                    <th scope="row"></th>

                    <td>

                        <button type="button" id="test-connection" class="button button-secondary">

                            Test Connection

                        </button>

                        <span id="connection-status"></span>

                    </td>

                </tr>

                

                <tr>

                    <th colspan="2"><h2>Pricing Settings</h2></th>

                </tr>

                

                <tr>

                    <th scope="row">

                        <label for="brolexy_profit_margin">Profit Margin</label>

                    </th>

                    <td>

                        <input type="number" 

                               id="brolexy_profit_margin" 

                               name="brolexy_profit_margin" 

                               value="<?php echo esc_attr($profit_margin); ?>" 

                               step="0.01" 

                               class="small-text" 

                               required>

                        

                        <select name="brolexy_profit_margin_type" id="brolexy_profit_margin_type">

                            <option value="percentage" <?php selected($profit_margin_type, 'percentage'); ?>>Percentage (%)</option>

                            <option value="fixed" <?php selected($profit_margin_type, 'fixed'); ?>>Fixed Amount (€)</option>

                        </select>

                        

                        <p class="description">

                            Global profit margin for all products. Can be overridden per product.<br>

                            Example: 20% on a €10 product = €12 selling price

                        </p>

                    </td>

                </tr>

                

                <tr>

                    <th colspan="2"><h2>Sync Settings</h2></th>

                </tr>

                

                <tr>

                    <th scope="row">

                        <label for="brolexy_sync_interval">Sync Interval</label>

                    </th>

                    <td>

                        <select name="brolexy_sync_interval" id="brolexy_sync_interval">

                            <option value="everyfiveminutes" <?php selected($sync_interval, 'everyfiveminutes'); ?>>Every 5 Minutes</option>

                            <option value="thirtyminutes" <?php selected($sync_interval, 'thirtyminutes'); ?>>Every 30 Minutes</option>

                            <option value="twicehourly" <?php selected($sync_interval, 'twicehourly'); ?>>Twice Hourly</option>

                            <option value="hourly" <?php selected($sync_interval, 'hourly'); ?>>Hourly</option>

                            <option value="sixhours" <?php selected($sync_interval, 'sixhours'); ?>>Every 6 Hours</option>

                            <option value="twicedaily" <?php selected($sync_interval, 'twicedaily'); ?>>Twice Daily</option>

                            <option value="daily" <?php selected($sync_interval, 'daily'); ?>>Daily</option>

                            <option value="never" <?php selected($sync_interval, 'never'); ?>>Never (Manual Only)</option>

                        </select>

                        <p class="description">How often to automatically sync stock and prices for imported products. Uses batch processing with 5-second delays for safety.</p>

                    </td>

                </tr>

                

                <tr>

                    <th colspan="2"><h2>Order Settings</h2></th>

                </tr>

                

                <tr>

                    <th scope="row">

                        <label for="brolexy_auto_fulfill">Auto-Fulfill Orders</label>

                    </th>

                    <td>

                        <label>

                            <input type="checkbox" 

                                   id="brolexy_auto_fulfill" 

                                   name="brolexy_auto_fulfill" 

                                   value="1" 

                                   <?php checked($auto_fulfill, '1'); ?>>

                            Automatically purchase from Brolexy when WooCommerce order is placed

                        </label>

                        <p class="description">Recommended: Enable for automatic order fulfillment</p>

                    </td>

                </tr>

                

                <tr>

                    <th colspan="2"><h2>Product Settings</h2></th>

                </tr>

                

                <tr>

                    <th scope="row">

                        <label for="brolexy_product_prefix">Product Name Prefix</label>

                    </th>

                    <td>

                        <input type="text" 

                               id="brolexy_product_prefix" 

                               name="brolexy_product_prefix" 

                               value="<?php echo esc_attr($product_prefix); ?>" 

                               class="regular-text">

                        <p class="description">Optional prefix for product names (e.g., "[Digital] ")</p>

                    </td>

                </tr>

                

                <tr>

                    <th scope="row">

                        <label for="brolexy_default_category">Default Category</label>

                    </th>

                    <td>

                        <?php

                        $categories = get_terms([

                            'taxonomy' => 'product_cat',

                            'hide_empty' => false

                        ]);

                        ?>

                        <select name="brolexy_default_category" id="brolexy_default_category">

                            <option value="">-- Select Category --</option>

                            <?php foreach ($categories as $category): ?>

                                <option value="<?php echo esc_attr($category->term_id); ?>" 

                                        <?php selected($default_category, $category->term_id); ?>>

                                    <?php echo esc_html($category->name); ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                        <p class="description">Category to assign to synced products</p>

                    </td>

                </tr>

            </table>

            

            <?php submit_button('Save Settings'); ?>

        </form>

        

        <hr>

        

        <h2>Manual Sync</h2>

        <p>Manually trigger sync operations for all imported products (syncs both stock and prices)</p>

        

        <p>

            <button type="button" id="sync-products" class="button button-primary">

                Sync Products Now

            </button>

            <span id="sync-status"></span>

        </p>

        

        <div class="notice notice-info" style="margin-top: 15px;">

            <p><strong>Note:</strong> Manual syncs are instant (no delays). Automatic syncs use batch processing with 5-second pauses between batches of 20 products to prevent API rate limits.</p>

        </div>

        

        <div id="sync-results" style="display:none; margin-top: 20px; padding: 15px; background: #f0f0f1; border-left: 4px solid #2271b1;">

            <h3>Sync Results</h3>

            <div id="sync-results-content"></div>

        </div>

        

        <hr>

        

        <h2>System Information</h2>

        <table class="widefat">

            <tr>

                <td><strong>Plugin Version:</strong></td>

                <td><?php echo BROLEXY_SYNC_VERSION; ?></td>

            </tr>

            <tr>

                <td><strong>API Mode:</strong></td>

                <td>

                    <?php 

                    if ($api_mode === 'live') {

                        echo '<span style="color: green; font-weight: bold;">LIVE (app.brolexy.com)</span>';

                    } else {

                        echo '<span style="color: orange; font-weight: bold;">TEST (dev.brolexy.com)</span>';

                    }

                    ?>

                </td>

            </tr>

            <tr>

                <td><strong>Next Sync:</strong></td>

                <td>

                    <?php

                    $next_sync = wp_next_scheduled('brolexy_sync_products_cron');

                    echo $next_sync ? date('Y-m-d H:i:s', $next_sync) : 'Not scheduled';

                    ?>

                </td>

            </tr>

            <tr>

                <td><strong>Imported Products:</strong></td>

                <td>

                    <?php

                    global $wpdb;

                    $count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_brolexy_product_id'");

                    echo (int)$count;

                    ?>

                </td>

            </tr>

        </table>

    </div>

</div>



<style>

.brolexy-admin-container {

    max-width: 1200px;

}



.brolexy-notice {

    padding: 12px;

    margin: 20px 0;

    border-left: 4px solid;

}



.brolexy-notice.success {

    background: #d4edda;

    border-color: #28a745;

    color: #155724;

}



.brolexy-notice.error {

    background: #f8d7da;

    border-color: #dc3545;

    color: #721c24;

}



#connection-status,

#sync-status {

    margin-left: 10px;

    font-weight: bold;

}



.loading {

    display: inline-block;

    width: 16px;

    height: 16px;

    border: 2px solid #f3f3f3;

    border-top: 2px solid #3498db;

    border-radius: 50%;

    animation: spin 1s linear infinite;

    vertical-align: middle;

    margin-left: 10px;

}



@keyframes spin {

    0% { transform: rotate(0deg); }

    100% { transform: rotate(360deg); }

}

</style>



<script>

jQuery(document).ready(function($) {

    // API Mode Change Warning

    $('#brolexy_api_mode').on('change', function() {

        var mode = $(this).val();

        var modeText = mode === 'live' ? 'LIVE' : 'TEST';

        

        alert('⚠️ API Mode changed to ' + modeText + '\n\nMake sure you are using the correct API keys for this mode!\n\nTest Mode: dev.brolexy.com\nLive Mode: app.brolexy.com');

    });

    

    // Test Connection

    $('#test-connection').on('click', function() {

        var button = $(this);

        var status = $('#connection-status');

        

        // Check if credentials are filled

        var publicKey = $('#brolexy_public_key').val();

        var privateKey = $('#brolexy_private_key').val();

        

        if (!publicKey || !privateKey) {

            status.html('<span style="color: red;">✗ Please enter API credentials first</span>');

            showNotice('Please save your API credentials before testing connection', 'error');

            return;

        }

        

        button.prop('disabled', true);

        status.html('<span class="loading"></span>');

        

        $.ajax({

            url: broleyxAjax.ajaxurl,

            type: 'POST',

            data: {

                action: 'brolexy_test_connection',

                nonce: broleyxAjax.nonce

            },

            success: function(response) {

                if (response.success) {

                    status.html('<span style="color: green;">✓ Connected! Found ' + response.data.accounts.length + ' account(s)</span>');

                    showNotice('Connection successful!', 'success');

                } else {

                    status.html('<span style="color: red;">✗ Failed</span>');

                    showNotice('Connection failed: ' + response.data.message, 'error');

                }

            },

            error: function(xhr, status, error) {

                $('#connection-status').html('<span style="color: red;">✗ Error</span>');

                showNotice('Connection error: ' + error, 'error');

                console.log('Ajax error:', xhr.responseText);

            },

            complete: function() {

                button.prop('disabled', false);

            }

        });

    });

    

    // Sync Products (both stock and prices)

    $('#sync-products').on('click', function() {

        triggerSync('products', $(this));

    });

    

    function triggerSync(type, button) {

        var status = $('#sync-status');

        var resultsDiv = $('#sync-results');

        var resultsContent = $('#sync-results-content');

        

        button.prop('disabled', true);

        

        status.html('<span class="loading"></span> Syncing products...');

        resultsDiv.hide();

        

        $.ajax({

            url: broleyxAjax.ajaxurl,

            type: 'POST',

            data: {

                action: 'brolexy_manual_sync',

                nonce: broleyxAjax.nonce,

                sync_type: 'products'

            },

            success: function(response) {

                if (response.success) {

                    status.html('<span style="color: green;">✓ Complete</span>');

                    

                    var message = 'Synced ' + (response.data.synced || 0) + ' new product(s), updated ' + (response.data.updated || 0) + ' product(s)';

                    

                    

                    resultsContent.html(message);

                    resultsDiv.show();

                    showNotice('Sync completed!', 'success');

                } else {

                    status.html('<span style="color: red;">✗ Failed</span>');

                    showNotice('Sync failed: ' + response.data.message, 'error');

                }

            },

            error: function() {

                status.html('<span style="color: red;">✗ Error</span>');

                showNotice('Sync error', 'error');

            },

            complete: function() {

                button.prop('disabled', false);

            }

        });

    }

    

    function showNotice(message, type) {

        var notice = $('#brolexy-notice');

        notice.removeClass('success error')

              .addClass(type)

              .html(message)

              .fadeIn();

        

        setTimeout(function() {

            notice.fadeOut();

        }, 5000);

    }

});

</script>