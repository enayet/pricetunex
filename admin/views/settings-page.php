<?php
/**
 * Admin settings page template - UPDATED WITH TARGET PRICE TYPE
 *
 * @package PriceTuneX
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Get categories and tags for dropdowns
$categories = $this->get_product_categories(); // Now returns hierarchical with proper IDs
$tags = $this->get_product_tags(); // Now includes counts
$product_types = $this->get_product_types();
?>

<div class="wrap pricetunex-admin">
    <h1><?php echo esc_html__( 'PriceTuneX – Smart Price Manager', 'pricetunex' ); ?></h1>
    <p class="description">
        <?php echo esc_html__( 'Bulk adjust product prices with smart rules and psychological pricing.', 'pricetunex' ); ?>
    </p>

    <div class="pricetunex-tabs">
        <nav class="nav-tab-wrapper">
            <a href="#price-rules" class="nav-tab nav-tab-active" data-tab="price-rules">
                <?php echo esc_html__( 'Price Rules', 'pricetunex' ); ?>
            </a>
            <a href="#settings" class="nav-tab" data-tab="settings">
                <?php echo esc_html__( 'Settings', 'pricetunex' ); ?>
            </a>
            <a href="#logs" class="nav-tab" data-tab="logs">
                <?php echo esc_html__( 'Activity Logs', 'pricetunex' ); ?>
            </a>
        </nav>

        <!-- Price Rules Tab -->
        <div id="price-rules" class="tab-content active">
            <div class="pricetunex-grid">
                <div class="pricetunex-card main-card">
                    <h2><?php echo esc_html__( 'Define Price Rules', 'pricetunex' ); ?></h2>
                    
                    <form id="pricetunex-rules-form" class="pricetunex-form">
                        <?php wp_nonce_field( 'pricetunex_admin_nonce', 'pricetunex_nonce' ); ?>
                        
                        <!-- Rule Type -->
                        <div class="form-group">
                            <label for="rule_type"><?php echo esc_html__( 'Adjustment Type', 'pricetunex' ); ?></label>
                            <select id="rule_type" name="rule_type" class="widefat">
                                <option value="percentage"><?php echo esc_html__( 'Percentage (%)', 'pricetunex' ); ?></option>
                                <option value="fixed"><?php echo esc_html__( 'Fixed Amount', 'pricetunex' ); ?></option>
                            </select>
                            <p class="description">
                                <?php echo esc_html__( 'Choose whether to adjust prices by percentage or fixed amount.', 'pricetunex' ); ?>
                            </p>
                        </div>

                        <!-- Rule Value -->
                        <div class="form-group">
                            <label for="rule_value"><?php echo esc_html__( 'Adjustment Value', 'pricetunex' ); ?></label>
                            <input type="number" id="rule_value" name="rule_value" step="0.01" class="widefat" placeholder="10" />
                            <p class="description">
                                <span class="percentage-desc"><?php echo esc_html__( 'Enter percentage (e.g., 10 for +10%, -5 for -5%).', 'pricetunex' ); ?></span>
                                <span class="fixed-desc" style="display:none;"><?php echo esc_html__( 'Enter amount (e.g., 5 for +$5, -2 for -$2).', 'pricetunex' ); ?></span>
                            </p>
                        </div>

                        <!-- NEW: Target Price Type -->
                        <div class="form-group">
                            <label for="target_price_type"><?php echo esc_html__( 'Target Price Type', 'pricetunex' ); ?></label>
                            <select id="target_price_type" name="target_price_type" class="widefat">
                                <option value="smart"><?php echo esc_html__( 'Smart Selection (Recommended)', 'pricetunex' ); ?></option>
                                <option value="regular_only"><?php echo esc_html__( 'Regular Price Only', 'pricetunex' ); ?></option>
                                <option value="sale_only"><?php echo esc_html__( 'Sale Price Only', 'pricetunex' ); ?></option>
                                <option value="both_prices"><?php echo esc_html__( 'Both Regular & Sale Prices', 'pricetunex' ); ?></option>
                            </select>
                            <p class="description" id="target-price-description">
                                <?php echo esc_html__( 'Updates the price customers actually see (sale price if active, otherwise regular price).', 'pricetunex' ); ?>
                            </p>
                        </div>

                        <!-- Target Scope -->
                        <div class="form-group">
                            <label for="target_scope"><?php echo esc_html__( 'Apply To', 'pricetunex' ); ?></label>
                            <select id="target_scope" name="target_scope" class="widefat">
                                <option value="all"><?php echo esc_html__( 'All Products', 'pricetunex' ); ?></option>
                                <option value="categories"><?php echo esc_html__( 'Specific Categories', 'pricetunex' ); ?></option>
                                <option value="tags"><?php echo esc_html__( 'Specific Tags', 'pricetunex' ); ?></option>
                                <option value="product_types"><?php echo esc_html__( 'Product Types', 'pricetunex' ); ?></option>
                                <option value="price_range"><?php echo esc_html__( 'Price Range', 'pricetunex' ); ?></option>
                            </select>
                        </div>

                        <!-- Categories Selection -->
                        <div class="form-group scope-field" id="categories-field" style="display:none;">
                            <label for="categories"><?php echo esc_html__( 'Select Categories', 'pricetunex' ); ?></label>
                            <select id="categories" name="categories[]" multiple class="widefat" size="5">
                                <?php foreach ( $categories as $id => $name ) : ?>
                                    <option value="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $name ); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php echo esc_html__( 'Hold Ctrl/Cmd to select multiple categories.', 'pricetunex' ); ?></p>
                        </div>

                        <!-- Tags Selection -->
                        <div class="form-group scope-field" id="tags-field" style="display:none;">
                            <label for="tags"><?php echo esc_html__( 'Select Tags', 'pricetunex' ); ?></label>
                            <select id="tags" name="tags[]" multiple class="widefat" size="5">
                                <?php foreach ( $tags as $id => $name ) : ?>
                                    <option value="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $name ); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php echo esc_html__( 'Hold Ctrl/Cmd to select multiple tags.', 'pricetunex' ); ?></p>
                        </div>

                        <!-- Product Types Selection -->
                        <div class="form-group scope-field" id="product_types-field" style="display:none;">
                            <label for="product_types"><?php echo esc_html__( 'Select Product Types', 'pricetunex' ); ?></label>
                            <div class="checkbox-group">
                                <?php foreach ( $product_types as $type => $label ) : ?>
                                    <label class="checkbox-label">
                                        <input type="checkbox" name="product_types[]" value="<?php echo esc_attr( $type ); ?>" />
                                        <?php echo esc_html( $label ); ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Price Range -->
                        <div class="form-group scope-field" id="price_range-field" style="display:none;">
                            <label><?php echo esc_html__( 'Price Range', 'pricetunex' ); ?></label>
                            <div class="price-range-inputs">
                                <input type="number" id="price_min" name="price_min" step="0.01" placeholder="<?php echo esc_attr__( 'Min Price', 'pricetunex' ); ?>" />
                                <span class="range-separator"><?php echo esc_html__( 'to', 'pricetunex' ); ?></span>
                                <input type="number" id="price_max" name="price_max" step="0.01" placeholder="<?php echo esc_attr__( 'Max Price', 'pricetunex' ); ?>" />
                            </div>
                            <p class="description"><?php echo esc_html__( 'Leave empty for no limit on that side.', 'pricetunex' ); ?></p>
                        </div>

                        <!-- Psychological Pricing -->
                        <div class="form-group">
                            <h3><?php echo esc_html__( 'Psychological Pricing', 'pricetunex' ); ?></h3>
                            <label class="checkbox-label">
                                <input type="checkbox" id="apply_rounding" name="apply_rounding" />
                                <?php echo esc_html__( 'Apply smart rounding after adjustment', 'pricetunex' ); ?>
                            </label>
                        </div>

                        <!-- Rounding Options -->
                        <div class="form-group" id="rounding-options" style="display:none;">
                            <label for="rounding_type"><?php echo esc_html__( 'Rounding Type', 'pricetunex' ); ?></label>
                            <select id="rounding_type" name="rounding_type" class="widefat">
                                <option value="0.99"><?php echo esc_html__( 'End in .99 (e.g., $19.99)', 'pricetunex' ); ?></option>
                                <option value="0.95"><?php echo esc_html__( 'End in .95 (e.g., $19.95)', 'pricetunex' ); ?></option>
                                <option value="0.00"><?php echo esc_html__( 'Round to whole number (e.g., $20)', 'pricetunex' ); ?></option>
                                <option value="custom"><?php echo esc_html__( 'Custom ending', 'pricetunex' ); ?></option>
                            </select>
                        </div>
                        
                        <!-- Custom Ending Field -->
                        <div class="form-group" id="custom-ending-field" style="display:none;">
                            <label for="custom_ending"><?php echo esc_html__( 'Custom Ending Value', 'pricetunex' ); ?></label>
                            <input type="number" id="custom_ending" name="custom_ending" step="0.01" min="0" max="0.99" class="widefat" placeholder="0.89" />
                            <p class="description">
                                <?php echo esc_html__( 'Enter a decimal value between 0.00 and 0.99 (e.g., 0.89 for prices ending in .89)', 'pricetunex' ); ?>
                            </p>
                        </div>                        

                        <!-- Action Buttons -->
                        <div class="form-actions">
                            <button type="button" id="preview-rules" class="button button-secondary">
                                <span class="dashicons dashicons-visibility"></span>
                                <?php echo esc_html__( 'Preview Changes', 'pricetunex' ); ?>
                            </button>
                            <button type="button" id="apply-rules" class="button button-primary">
                                <span class="dashicons dashicons-yes-alt"></span>
                                <?php echo esc_html__( 'Apply Rules', 'pricetunex' ); ?>
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Preview & Actions Sidebar -->
                <div class="pricetunex-sidebar">
                    <!-- Preview Results -->
                    <div class="pricetunex-card preview-card">
                        <h3><?php echo esc_html__( 'Preview Results', 'pricetunex' ); ?></h3>
                        <div id="preview-results" class="preview-content">
                            <p class="no-preview"><?php echo esc_html__( 'Click "Preview Changes" to see affected products.', 'pricetunex' ); ?></p>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="pricetunex-card actions-card">
                        <h3><?php echo esc_html__( 'Quick Actions', 'pricetunex' ); ?></h3>
                        <div class="quick-actions">
                            <button type="button" id="undo-last" class="button button-secondary full-width">
                                <span class="dashicons dashicons-undo"></span>
                                <?php echo esc_html__( 'Undo Last Changes', 'pricetunex' ); ?>
                            </button>
                            <p class="description"><?php echo esc_html__( 'Restore prices to their previous values.', 'pricetunex' ); ?></p>
                        </div>
                    </div>

                    <!-- Statistics -->
                    <div class="pricetunex-card stats-card">
                        <h3><?php echo esc_html__( 'Statistics', 'pricetunex' ); ?></h3>
                        <div class="stats-content">
                            <div class="stat-item">
                                <span class="stat-label"><?php echo esc_html__( 'Total Products:', 'pricetunex' ); ?></span>
                                <span class="stat-value" id="total-products">-</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-label"><?php echo esc_html__( 'Last Update:', 'pricetunex' ); ?></span>
                                <span class="stat-value" id="last-update">
                                    <?php
                                    $last_log = get_option( 'pricetunex_last_update', '' );
                                    echo $last_log ? esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $last_log ) ) : esc_html__( 'Never', 'pricetunex' );
                                    ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Settings Tab -->
        <div id="settings" class="tab-content">
            <div class="pricetunex-card">
                <h2><?php echo esc_html__( 'Plugin Settings', 'pricetunex' ); ?></h2>
                
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                    <input type="hidden" name="action" value="pricetunex_save_settings" />
                    <?php wp_nonce_field( 'pricetunex_save_settings', 'pricetunex_settings_nonce' ); ?>

                    <table class="form-table">
                        <tbody>
                            <tr>
                                <th scope="row"><?php echo esc_html__( 'Enable Logging', 'pricetunex' ); ?></th>
                                <td>
                                    <fieldset>
                                        <label>
                                            <input type="checkbox" name="enable_logging" <?php checked( pricetunex_get_setting( 'enable_logging', true ) ); ?> />
                                            <?php echo esc_html__( 'Log all price changes for audit trail', 'pricetunex' ); ?>
                                        </label>
                                        <p class="description">
                                            <?php echo esc_html__( 'Keep a record of all price changes made by the plugin.', 'pricetunex' ); ?>
                                        </p>
                                    </fieldset>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row"><?php echo esc_html__( 'Maximum Log Entries', 'pricetunex' ); ?></th>
                                <td>
                                    <input type="number" name="max_log_entries" value="<?php echo esc_attr( pricetunex_get_setting( 'max_log_entries', 1000 ) ); ?>" min="100" max="10000" class="regular-text" />
                                    <p class="description">
                                        <?php echo esc_html__( 'Maximum number of log entries to keep. Older entries will be automatically removed.', 'pricetunex' ); ?>
                                    </p>
                                </td>
                            </tr>

                            <tr>
                                <th scope="row"><?php echo esc_html__( 'Backup Prices', 'pricetunex' ); ?></th>
                                <td>
                                    <fieldset>
                                        <label>
                                            <input type="checkbox" name="backup_prices" <?php checked( pricetunex_get_setting( 'backup_prices', true ) ); ?> />
                                            <?php echo esc_html__( 'Store original prices before changes', 'pricetunex' ); ?>
                                        </label>
                                        <p class="description">
                                            <?php echo esc_html__( 'Enable the undo feature by backing up original prices.', 'pricetunex' ); ?>
                                        </p>
                                    </fieldset>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <?php submit_button( esc_html__( 'Save Settings', 'pricetunex' ), 'primary', 'submit', false ); ?>
                </form>
            </div>

            <!-- Plugin Information -->
            <div class="pricetunex-card">
                <h3><?php echo esc_html__( 'Plugin Information', 'pricetunex' ); ?></h3>
                <table class="widefat">
                    <tbody>
                        <tr>
                            <td><strong><?php echo esc_html__( 'Version:', 'pricetunex' ); ?></strong></td>
                            <td><?php echo esc_html( PRICETUNEX_VERSION ); ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php echo esc_html__( 'WooCommerce Version:', 'pricetunex' ); ?></strong></td>
                            <td><?php echo defined( 'WC_VERSION' ) ? esc_html( WC_VERSION ) : esc_html__( 'Not detected', 'pricetunex' ); ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php echo esc_html__( 'WordPress Version:', 'pricetunex' ); ?></strong></td>
                            <td><?php echo esc_html( get_bloginfo( 'version' ) ); ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php echo esc_html__( 'PHP Version:', 'pricetunex' ); ?></strong></td>
                            <td><?php echo esc_html( PHP_VERSION ); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Activity Logs Tab -->
        <div id="logs" class="tab-content">
            <div class="pricetunex-card">
                <h2><?php echo esc_html__( 'Activity Logs', 'pricetunex' ); ?></h2>
                <p class="description">
                    <?php echo esc_html__( 'Track all price changes made by PriceTuneX.', 'pricetunex' ); ?>
                </p>

                <div class="logs-controls">
                    <button type="button" id="refresh-logs" class="button">
                        <span class="dashicons dashicons-update"></span>
                        <?php echo esc_html__( 'Refresh Logs', 'pricetunex' ); ?>
                    </button>
                    <button type="button" id="clear-logs" class="button button-secondary">
                        <span class="dashicons dashicons-trash"></span>
                        <?php echo esc_html__( 'Clear All Logs', 'pricetunex' ); ?>
                    </button>
                </div>

                <div id="logs-container" class="logs-container">
                    <div class="logs-loading">
                        <span class="spinner is-active"></span>
                        <p><?php echo esc_html__( 'Loading logs...', 'pricetunex' ); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div id="pricetunex-loading" class="pricetunex-loading" style="display: none;">
        <div class="loading-content">
            <span class="spinner is-active"></span>
            <p id="loading-message"><?php echo esc_html__( 'Processing...', 'pricetunex' ); ?></p>
        </div>
    </div>

    <!-- Modal for confirmations -->
    <div id="pricetunex-modal" class="pricetunex-modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modal-title"><?php echo esc_html__( 'Confirmation', 'pricetunex' ); ?></h3>
                <span class="modal-close">&times;</span>
            </div>
            <div class="modal-body">
                <p id="modal-message"></p>
            </div>
            <div class="modal-footer">
                <button type="button" id="modal-cancel" class="button button-secondary">
                    <?php echo esc_html__( 'Cancel', 'pricetunex' ); ?>
                </button>
                <button type="button" id="modal-confirm" class="button button-primary">
                    <?php echo esc_html__( 'Confirm', 'pricetunex' ); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<script type="text/template" id="preview-template">
    <div class="preview-summary">
        <h4><?php echo esc_html__( 'Products Affected:', 'pricetunex' ); ?> <span class="count">{{ data.count }}</span></h4>
        <div class="preview-list">
            <# _.each(data.products, function(product) { #>
                <div class="preview-item">
                    <strong>{{ product.name }}</strong><br>
                    
                    <# if (product.is_both_prices) { #>
                        <!-- Both Prices Mode - Show detailed breakdown -->
                        <div class="both-prices-preview">
                            <div class="price-line">
                                <span class="price-label">Regular:</span>
                                <span class="price-change">
                                    {{ product.regular_price.old }} → {{ product.regular_price.new }}
                                    <span class="change-amount {{ product.regular_price.change_type }}">{{ product.regular_price.change_amount }}</span>
                                </span>
                            </div>
                            <# if (product.sale_price) { #>
                                <div class="price-line">
                                    <span class="price-label">Sale:</span>
                                    <span class="price-change">
                                        {{ product.sale_price.old }} → {{ product.sale_price.new }}
                                        <span class="change-amount {{ product.sale_price.change_type }}">{{ product.sale_price.change_amount }}</span>
                                    </span>
                                </div>
                            <# } #>
                        </div>
                    <# } else { #>
                        <!-- Single Price Mode -->
                        <span class="price-change">
                            {{ product.old_price }} → {{ product.new_price }}
                            <span class="change-amount {{ product.change_type }}">{{ product.change_amount }}</span>
                        </span>
                    <# } #>
                    
                    <# if (product.price_type_updated) { #>
                        <div class="price-type-label">
                            <small><em>{{ product.price_type_updated }}</em></small>
                        </div>
                    <# } #>
                </div>
            <# }); #>
        </div>
    </div>
</script>

<script type="text/template" id="log-template">
    <div class="log-entry">
        <div class="log-header">
            <span class="log-date">{{ data.date }}</span>
            <span class="log-action {{ data.action_type }}">{{ data.action }}</span>
        </div>
        <div class="log-details">
            <p>{{ data.description }}</p>
            <# if (data.products_count) { #>
                <span class="products-count"><?php echo esc_html__( 'Products affected:', 'pricetunex' ); ?> {{ data.products_count }}</span>
            <# } #>
        </div>
    </div>
</script>