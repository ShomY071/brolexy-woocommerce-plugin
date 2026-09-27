<?php

// Get imported products

global $wpdb;

$imported_products = $wpdb->get_results("

    SELECT p.ID, p.post_title, pm1.meta_value as brolexy_id, pm2.meta_value as cost_price, pm3.meta_value as stock

    FROM {$wpdb->posts} p

    INNER JOIN {$wpdb->postmeta} pm1 ON p.ID = pm1.post_id AND pm1.meta_key = '_brolexy_product_id'

    LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_brolexy_cost_price'

    LEFT JOIN {$wpdb->postmeta} pm3 ON p.ID = pm3.post_id AND pm3.meta_key = '_stock'

    WHERE p.post_type = 'product' AND p.post_status = 'publish'

    ORDER BY p.post_date DESC

", ARRAY_A);

?>



<div class="wrap">

    <h1>Import Products from Brolexy</h1>

    

    <div class="brolexy-notice" id="brolexy-notice" style="display:none;"></div>

    

    <div class="brolexy-import-container">

        

        <!-- Search & Filter Section -->

        <div class="brolexy-search-box">

            <h2>Search Brolexy Products</h2>

            <p>Search and import individual products from Brolexy. Only products you import will be synced.</p>

            

            <div class="search-controls">

                <input type="text" 

                       id="product-search-input" 

                       class="regular-text" 

                       placeholder="Search by name, category, or region...">

                

                <select id="category-filter" style="min-width: 150px;">

                    <option value="">All Categories</option>

                </select>

                

                <select id="region-filter" style="min-width: 150px;">

                    <option value="">All Regions</option>

                </select>

                

                <button type="button" id="search-products-btn" class="button button-primary">

                    <span class="dashicons dashicons-search"></span> Search

                </button>

                

                <button type="button" id="clear-search-btn" class="button">

                    Clear

                </button>

            </div>
            
            <div style="margin-top: 10px;">
                <label>
                    <input type="checkbox" id="hide-out-of-stock" checked>
                    Hide out of stock products
                </label>
            </div>

            

            <div id="search-status" style="margin-top: 10px;"></div>

        </div>

        

        <!-- Search Results -->

        <div id="search-results" class="brolexy-results-section" style="display:none;">

            <h3>Search Results <span id="results-count"></span></h3>

            <div id="products-grid" class="products-grid">

                <!-- Products will be loaded here -->

            </div>

        </div>

        

        <hr style="margin: 40px 0;">

        

        <!-- Imported Products List -->

        <div class="brolexy-imported-section">

            <h2>Imported Products (<?php echo count($imported_products); ?>)</h2>

            <p>These products are automatically synced for stock and price updates.</p>

            

            <?php if (empty($imported_products)): ?>

                <div class="notice notice-info">

                    <p>No products imported yet. Use the search above to import products from Brolexy.</p>

                </div>

            <?php else: ?>

                <table class="wp-list-table widefat fixed striped">

                    <thead>

                        <tr>

                            <th style="width: 10%;">Brolexy ID</th>

                            <th style="width: 40%;">Product Name</th>

                            <th style="width: 15%;">Cost Price</th>

                            <th style="width: 15%;">Stock</th>

                            <th style="width: 10%;">Status</th>

                            <th style="width: 10%;">Actions</th>

                        </tr>

                    </thead>

                    <tbody>

                        <?php foreach ($imported_products as $product): ?>

                            <tr data-product-id="<?php echo esc_attr($product['ID']); ?>">

                                <td><?php echo esc_html($product['brolexy_id']); ?></td>

                                <td>

                                    <strong>

                                        <a href="<?php echo get_edit_post_link($product['ID']); ?>" target="_blank">

                                            <?php echo esc_html($product['post_title']); ?>

                                        </a>

                                    </strong>

                                </td>

                                <td>€<?php echo esc_html($product['cost_price']); ?></td>

                                <td>

                                    <?php 

                                    $stock = intval($product['stock']);

                                    if ($stock > 0) {

                                        echo '<span class="stock-badge in-stock">' . $stock . ' in stock</span>';

                                    } else {

                                        echo '<span class="stock-badge out-of-stock">Out of stock</span>';

                                    }

                                    ?>

                                </td>

                                <td>

                                    <span class="status-badge active">Active</span>

                                </td>

                                <td>

                                    <button type="button" 

                                            class="button button-small delete-product-btn" 

                                            data-product-id="<?php echo esc_attr($product['ID']); ?>"

                                            data-product-name="<?php echo esc_attr($product['post_title']); ?>">

                                        <span class="dashicons dashicons-trash"></span>

                                    </button>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            <?php endif; ?>

        </div>

        

    </div>

</div>



<style>

.brolexy-import-container {

    max-width: 1400px;

}



.brolexy-search-box {

    background: #fff;

    padding: 20px;

    border: 1px solid #ccc;

    border-radius: 5px;

    margin-bottom: 30px;

}



.search-controls {

    display: flex;

    gap: 10px;

    margin-top: 15px;

    flex-wrap: wrap;

}



.search-controls input[type="text"] {

    flex: 1;

    min-width: 300px;

}



.products-grid {

    display: grid;

    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));

    gap: 20px;

    margin-top: 20px;

}



.product-card {

    background: #fff;

    border: 2px solid #ddd;

    border-radius: 5px;

    padding: 15px;

    transition: all 0.2s;

}



.product-card:hover {

    border-color: #2271b1;

    box-shadow: 0 2px 8px rgba(0,0,0,0.1);

}



.product-card-header {

    margin-bottom: 12px;

}



.product-card-title {

    font-size: 16px;

    font-weight: bold;

    margin: 0 0 10px 0;

    color: #1d2327;

}



.product-badges {

    display: flex;

    gap: 5px;

    flex-wrap: wrap;

    margin-bottom: 10px;

}



.badge {

    display: inline-block;

    padding: 3px 8px;

    border-radius: 3px;

    font-size: 11px;

    font-weight: bold;

}



.badge-category {

    background: #e3f2fd;

    color: #1976d2;

}



.badge-region {

    background: #f3e5f5;

    color: #7b1fa2;

}



.badge-price {

    background: #fff3e0;

    color: #e65100;

}



.stock-badge {

    padding: 3px 8px;

    border-radius: 3px;

    font-size: 11px;

    font-weight: bold;

}



.stock-badge.in-stock {

    background: #e8f5e9;

    color: #2e7d32;

}



.stock-badge.out-of-stock {

    background: #ffebee;

    color: #c62828;

}



.product-card-body {

    margin: 12px 0;

    font-size: 13px;

    color: #666;

}



.product-card-footer {

    display: flex;

    justify-content: space-between;

    align-items: center;

    padding-top: 12px;

    border-top: 1px solid #eee;

}



.import-product-btn {

    width: 100%;

}



.status-badge {

    padding: 3px 8px;

    border-radius: 3px;

    font-size: 11px;

    font-weight: bold;

}



.status-badge.active {

    background: #e8f5e9;

    color: #2e7d32;

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



.loading-spinner {

    display: inline-block;

    width: 16px;

    height: 16px;

    border: 2px solid #f3f3f3;

    border-top: 2px solid #2271b1;

    border-radius: 50%;

    animation: spin 1s linear infinite;

    vertical-align: middle;

}



@keyframes spin {

    0% { transform: rotate(0deg); }

    100% { transform: rotate(360deg); }

}



.button .dashicons {

    line-height: 26px;

    font-size: 16px;

}



.delete-product-btn {

    color: #dc3545;

}



.delete-product-btn:hover {

    color: #a02622;

}

</style>



<script>

jQuery(document).ready(function($) {

    let categories = [];

    let regions = [];
    
    // Store original search results for re-filtering
    let lastSearchResults = [];

    

    // Search Products

    $('#search-products-btn').on('click', function() {

        searchProducts();

    });

    

    // Enter key on search

    $('#product-search-input').on('keypress', function(e) {

        if (e.which === 13) {

            searchProducts();

        }

    });

    

    // Clear search

    $('#clear-search-btn').on('click', function() {

        $('#product-search-input').val('');

        $('#category-filter').val('');

        $('#region-filter').val('');

        $('#hide-out-of-stock').prop('checked', true); // Reset to default (checked)

        $('#search-results').hide();

        $('#search-status').html('');

    });
    
    // Re-filter results when hide out of stock checkbox changes
    $('#hide-out-of-stock').on('change', function() {
        // If we have stored search results, re-display them with new filter
        if (lastSearchResults.length > 0) {
            displayResults(lastSearchResults);
            // Update count
            const visibleCount = $('#products-grid .product-card').length;
            const totalCount = lastSearchResults.length;
            const hideOutOfStock = $('#hide-out-of-stock').is(':checked');
            const countText = hideOutOfStock && visibleCount < totalCount
                ? `Found ${visibleCount} product(s) (${totalCount - visibleCount} out of stock hidden)`
                : `Found ${visibleCount} product(s)`;
            $('#search-status').html('<span style="color: green;">✓ ' + countText + '</span>');
            $('#results-count').text('(' + visibleCount + ')');
        }
    });

    

    function searchProducts() {

        const searchTerm = $('#product-search-input').val();

        const category = $('#category-filter').val();

        const region = $('#region-filter').val();

        

        $('#search-status').html('<span class="loading-spinner"></span> Searching...');

        $('#search-results').hide();

        

        $.ajax({

            url: broleyxAjax.ajaxurl,

            type: 'POST',

            data: {

                action: 'brolexy_search_products',

                nonce: broleyxAjax.nonce,

                search: searchTerm,

                category: category,

                region: region

            },

            success: function(response) {

                if (response.success) {
                    
                    // Store original results for re-filtering
                    lastSearchResults = response.data.products;

                    displayResults(response.data.products);

                    updateFilters(response.data.categories, response.data.regions);
                    
                    // Update count after filtering
                    const visibleCount = $('#products-grid .product-card').length;
                    const totalCount = response.data.total;
                    const hideOutOfStock = $('#hide-out-of-stock').is(':checked');
                    const countText = hideOutOfStock && visibleCount < totalCount
                        ? `Found ${visibleCount} product(s) (${totalCount - visibleCount} out of stock hidden)`
                        : `Found ${visibleCount} product(s)`;
                    $('#search-status').html('<span style="color: green;">✓ ' + countText + '</span>');

                } else {

                    $('#search-status').html('<span style="color: red;">✗ ' + response.data.message + '</span>');

                }

            },

            error: function(xhr, status, error) {
                let errorMsg = 'Search failed';
                if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                    errorMsg = xhr.responseJSON.data.message;
                } else if (status === 'timeout') {
                    errorMsg = 'Request timed out. The API may be slow or there are too many products. Please try again.';
                } else if (error) {
                    errorMsg = error;
                }
                $('#search-status').html('<span style="color: red;">✗ ' + errorMsg + '</span>');
            },
            timeout: 90000 // 90 seconds timeout for AJAX (longer than API timeout)

        });

    }

    

    function updateFilters(cats, regs) {

        if (categories.length === 0 && cats.length > 0) {

            categories = cats;

            const catFilter = $('#category-filter');

            cats.forEach(function(cat) {

                catFilter.append('<option value="' + cat + '">' + cat + '</option>');

            });

        }

        

        if (regions.length === 0 && regs.length > 0) {

            regions = regs;

            const regFilter = $('#region-filter');

            regs.forEach(function(reg) {

                regFilter.append('<option value="' + reg + '">' + reg + '</option>');

            });

        }

    }

    

    function displayResults(products) {

        const grid = $('#products-grid');

        grid.empty();
        
        // Filter out out-of-stock products if checkbox is checked
        const hideOutOfStock = $('#hide-out-of-stock').is(':checked');
        if (hideOutOfStock) {
            products = products.filter(function(product) {
                return product.inStock > 0;
            });
        }

        if (products.length === 0) {

            const message = hideOutOfStock 
                ? '<p>No products found. Try different search terms or filters, or uncheck "Hide out of stock products".</p>'
                : '<p>No products found. Try different search terms or filters.</p>';
            grid.html(message);

            $('#search-results').show();

            return;

        }

        

        products.forEach(function(product) {

            const stockBadge = product.inStock > 0 

                ? '<span class="stock-badge in-stock">' + product.inStock + ' in stock</span>'

                : '<span class="stock-badge out-of-stock">Out of stock</span>';

            

            const card = `

                <div class="product-card">

                    <div class="product-card-header">

                        <h4 class="product-card-title">${product.name}</h4>

                        <div class="product-badges">

                            <span class="badge badge-category">${product.category}</span>

                            <span class="badge badge-region">${product.region}</span>

                            <span class="badge badge-price">${product.price} ${product.priceCurrency}</span>

                        </div>

                    </div>

                    <div class="product-card-body">

                        <div>${stockBadge}</div>

                        <div style="margin-top: 5px; font-size: 12px; color: #999;">

                            Brolexy ID: ${product.productId}

                        </div>

                    </div>

                    <div class="product-card-footer">

                        <button type="button" 

                                class="button button-primary import-product-btn" 

                                data-product-id="${product.productId}"

                                data-product-name="${product.name}">

                            <span class="dashicons dashicons-download"></span> Import Product

                        </button>

                    </div>

                </div>

            `;

            

            grid.append(card);

        });

        

        $('#results-count').text('(' + products.length + ')');

        $('#search-results').show();

    }

    

    // Import Product

    $(document).on('click', '.import-product-btn', function() {

        const button = $(this);

        const productId = button.data('product-id');

        const productName = button.data('product-name');

        

        if (!confirm('Import "' + productName + '" to your store?')) {

            return;

        }

        

        button.prop('disabled', true).html('<span class="loading-spinner"></span> Importing...');

        

        $.ajax({

            url: broleyxAjax.ajaxurl,

            type: 'POST',

            data: {

                action: 'brolexy_import_product',

                nonce: broleyxAjax.nonce,

                product_id: productId

            },

            success: function(response) {

                if (response.success) {

                    showNotice('Product imported successfully!', 'success');
                    
                    // Remove the product card from search results
                    button.closest('.product-card').fadeOut(300, function() {
                        $(this).remove();
                        
                        // Update results count
                        const remaining = $('.product-card').length;
                        $('#results-count').text('(' + remaining + ')');
                        
                        if (remaining === 0) {
                            $('#products-grid').html('<p>No products found. Try different search terms or filters.</p>');
                        }
                    });
                    
                    // Add product to imported products table dynamically
                    addProductToTable(response.data);
                    
                    // Update count in heading
                    updateImportedCount();

                } else {

                    showNotice('Import failed: ' + response.data.message, 'error');

                    button.prop('disabled', false).html('<span class="dashicons dashicons-download"></span> Import Product');

                }

            },

            error: function() {

                showNotice('Import error', 'error');

                button.prop('disabled', false).html('<span class="dashicons dashicons-download"></span> Import Product');

            }

        });

    });

    

    // Delete Product

    $(document).on('click', '.delete-product-btn', function() {

        const button = $(this);

        const productId = button.data('product-id');

        const productName = button.data('product-name');

        

        if (!confirm('Delete "' + productName + '" from your store?\n\nThis will permanently remove the product and all its data.')) {

            return;

        }

        

        button.prop('disabled', true);

        

        $.ajax({

            url: broleyxAjax.ajaxurl,

            type: 'POST',

            data: {

                action: 'brolexy_delete_imported_product',

                nonce: broleyxAjax.nonce,

                product_id: productId

            },

            success: function(response) {

                if (response.success) {

                    showNotice('Product deleted successfully!', 'success');

                    $('tr[data-product-id="' + productId + '"]').fadeOut(300, function() {

                        $(this).remove();

                        // Update count

                        const remaining = $('tbody tr').length;

                        $('h2').first().text('Imported Products (' + remaining + ')');

                    });

                } else {

                    showNotice('Delete failed: ' + response.data.message, 'error');

                    button.prop('disabled', false);

                }

            },

            error: function() {

                showNotice('Delete error', 'error');

                button.prop('disabled', false);

            }

        });

    });

    

    function showNotice(message, type) {

        const notice = $('#brolexy-notice');

        notice.removeClass('success error')

              .addClass(type)

              .html(message)

              .fadeIn();

        

        setTimeout(function() {

            notice.fadeOut();

        }, 5000);

    }
    
    function addProductToTable(productData) {
        const tbody = $('.brolexy-imported-section tbody');
        const table = $('.brolexy-imported-section table');
        const emptyNotice = $('.brolexy-imported-section .notice.notice-info');
        
        // Remove empty notice if exists
        if (emptyNotice.length) {
            emptyNotice.remove();
            // Create table if it doesn't exist
            if (!table.length) {
                const tableHtml = `
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th style="width: 10%;">Brolexy ID</th>
                                <th style="width: 40%;">Product Name</th>
                                <th style="width: 15%;">Cost Price</th>
                                <th style="width: 15%;">Stock</th>
                                <th style="width: 10%;">Status</th>
                                <th style="width: 10%;">Actions</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                `;
                $('.brolexy-imported-section').append(tableHtml);
            }
        }
        
        const stockBadge = productData.stock > 0 
            ? '<span class="stock-badge in-stock">' + productData.stock + ' in stock</span>'
            : '<span class="stock-badge out-of-stock">Out of stock</span>';
        
        const row = `
            <tr data-product-id="${productData.product_id}">
                <td>${productData.brolexy_id || ''}</td>
                <td>
                    <strong>
                        <a href="${productData.edit_link}" target="_blank">${productData.product_name}</a>
                    </strong>
                </td>
                <td>€${productData.cost_price || '0.00'}</td>
                <td>${stockBadge}</td>
                <td><span class="status-badge active">Active</span></td>
                <td>
                    <button type="button" 
                            class="button button-small delete-product-btn" 
                            data-product-id="${productData.product_id}"
                            data-product-name="${productData.product_name}">
                        <span class="dashicons dashicons-trash"></span>
                    </button>
                </td>
            </tr>
        `;
        
        $('.brolexy-imported-section tbody').append(row);
    }
    
    function updateImportedCount() {
        const count = $('.brolexy-imported-section tbody tr').length;
        $('.brolexy-imported-section h2').text('Imported Products (' + count + ')');
    }

});

</script>