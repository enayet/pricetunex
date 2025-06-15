<?php
/**
 * Price Manager - Core logic for price calculations and adjustments - COMPLETE VERSION
 *
 * @package PriceTuneX
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Price Manager Class
 */
class Pricetunex_Price_Manager {

    /**
     * Product query helper
     *
     * @var Pricetunex_Product_Query
     */
    private $product_query;

    /**
     * Constructor
     */
    public function __construct() {
        $this->product_query = new Pricetunex_Product_Query();
    }

    /**
     * Apply price rules to products - SIMPLIFIED AND FIXED
     *
     * @param array $rule_data Rule configuration.
     * @return array Result array with success status and details.
     */
    public function apply_rules( $rule_data ) {
        try {
            // Get products based on rules
            $products = $this->product_query->get_products_by_rules( $rule_data );
            
            if ( empty( $products ) ) {
                return array(
                    'success' => false,
                    'message' => esc_html__( 'No products found matching the specified criteria.', 'pricetunex' ),
                );
            }

            // Backup original prices if enabled
            $backup_enabled = pricetunex_get_setting( 'backup_prices', true );
            if ( $backup_enabled ) {
                $this->backup_product_prices( $products );
            }

            // Apply price changes
            $products_updated = 0;

            foreach ( $products as $product_data ) {
                $product = $product_data['product'];
                
                if ( $this->apply_price_change_to_product( $product, $rule_data ) ) {
                    $products_updated++;
                }
            }

            // Log the action
            $this->log_price_action( 'apply', $rule_data, $products_updated );

            return array(
                'success'          => true,
                'products_updated' => $products_updated,
                'message'          => sprintf(
                    /* translators: %d: Number of products updated */
                    esc_html__( 'Successfully updated prices for %d products.', 'pricetunex' ),
                    $products_updated
                ),
            );

        } catch ( Exception $e ) {
            // Log error
            error_log( 'PriceTuneX Error: ' . $e->getMessage() );
            
            return array(
                'success' => false,
                'message' => esc_html__( 'An error occurred while applying price changes.', 'pricetunex' ),
            );
        }
    }

    /**
     * Preview price rules without applying changes
     *
     * @param array $rule_data Rule configuration.
     * @return array Preview result with affected products.
     */
    public function preview_rules( $rule_data ) {
        try {
            // Get products based on rules
            $products = $this->product_query->get_products_by_rules( $rule_data );
            
            if ( empty( $products ) ) {
                return array(
                    'success' => false,
                    'message' => esc_html__( 'No products found matching the specified criteria.', 'pricetunex' ),
                );
            }

            // Generate preview data (limit to first 10 for performance)
            $preview_products = array_slice( $products, 0, 10 );
            $preview_data = array();

            foreach ( $preview_products as $product_data ) {
                $product = $product_data['product'];
                $preview_item = $this->calculate_price_preview( $product, $rule_data );
                
                if ( $preview_item ) {
                    $preview_data[] = $preview_item;
                }
            }

            return array(
                'success'            => true,
                'products_affected'  => count( $products ),
                'preview_data'       => array(
                    'count'    => count( $products ),
                    'products' => $preview_data,
                ),
                'message'            => sprintf(
                    /* translators: %d: Number of products that would be affected */
                    esc_html__( 'Preview shows %d products would be affected.', 'pricetunex' ),
                    count( $products )
                ),
            );

        } catch ( Exception $e ) {
            return array(
                'success' => false,
                'message' => esc_html__( 'An error occurred while generating preview.', 'pricetunex' ),
            );
        }
    }

    /**
     * Undo the last price changes
     *
     * @return array Result array with success status.
     */
    public function undo_last_changes() {
        try {
            // Get backup data
            $backup_data = get_option( 'pricetunex_price_backup', array() );
            
            if ( empty( $backup_data ) ) {
                return array(
                    'success' => false,
                    'message' => esc_html__( 'No backup data found. Cannot undo changes.', 'pricetunex' ),
                );
            }

            $products_restored = 0;

            foreach ( $backup_data as $product_id => $backup_info ) {
                $product = wc_get_product( $product_id );
                
                if ( ! $product ) {
                    continue;
                }

                // Restore prices
                $this->restore_product_price( $product, $backup_info );
                $products_restored++;
            }

            // Clear backup data after successful restore
            delete_option( 'pricetunex_price_backup' );

            // Log the undo action
            $this->log_price_action( 'undo', array(), $products_restored );

            return array(
                'success'           => true,
                'products_restored' => $products_restored,
                'message'           => sprintf(
                    /* translators: %d: Number of products restored */
                    esc_html__( 'Successfully restored prices for %d products.', 'pricetunex' ),
                    $products_restored
                ),
            );

        } catch ( Exception $e ) {
            return array(
                'success' => false,
                'message' => esc_html__( 'An error occurred while undoing changes.', 'pricetunex' ),
            );
        }
    }

    /**
     * SIMPLIFIED: Apply price change to a single product
     *
     * @param WC_Product $product Product to update.
     * @param array      $rule_data Rule configuration.
     * @return bool Success status.
     */
    private function apply_price_change_to_product( $product, $rule_data ) {
        try {
            $old_price = $product->get_regular_price();
            
            // Skip if no price set
            if ( empty( $old_price ) || ! is_numeric( $old_price ) ) {
                return false;
            }

            // Calculate new price
            $new_price = $this->calculate_new_price( floatval( $old_price ), $rule_data );
            
            // Apply psychological pricing if enabled
            if ( ! empty( $rule_data['apply_rounding'] ) ) {
                $new_price = $this->apply_psychological_pricing( $new_price, $rule_data );
            }

            // Ensure price is not negative
            $new_price = max( 0, $new_price );

            // Update the product
            $product->set_regular_price( $new_price );
            
            // Also update sale price if it exists and is higher than new regular price
            $sale_price = $product->get_sale_price();
            if ( ! empty( $sale_price ) && floatval( $sale_price ) >= $new_price ) {
                $product->set_sale_price( '' );
            }
            
            // Save the product
            $product->save();

            // Clear product cache
            wc_delete_product_transients( $product->get_id() );

            return true;

        } catch ( Exception $e ) {
            error_log( 'PriceTuneX Error updating product ' . $product->get_id() . ': ' . $e->getMessage() );
            return false;
        }
    }

    /**
     * Calculate new price based on rule
     *
     * @param float $current_price Current product price.
     * @param array $rule_data Rule configuration.
     * @return float New calculated price.
     */
    private function calculate_new_price( $current_price, $rule_data ) {
        $rule_type = $rule_data['rule_type'];
        $rule_value = floatval( $rule_data['rule_value'] );

        if ( 'percentage' === $rule_type ) {
            // Calculate percentage change
            $change_amount = $current_price * ( $rule_value / 100 );
            return $current_price + $change_amount;
        } else {
            // Fixed amount change
            return $current_price + $rule_value;
        }
    }

    /**
     * Apply psychological pricing
     *
     * @param float $price Price to round.
     * @param array $rule_data Rule configuration.
     * @return float Rounded price.
     */
    private function apply_psychological_pricing( $price, $rule_data ) {
        $rounding_type = isset( $rule_data['rounding_type'] ) ? $rule_data['rounding_type'] : '0.99';

        switch ( $rounding_type ) {
            case '0.99':
                return floor( $price ) + 0.99;
            
            case '0.95':
                return floor( $price ) + 0.95;
            
            case '0.00':
                return round( $price );
            
            case 'custom':
                // For custom rounding, default to .99 for now
                return floor( $price ) + 0.99;
            
            default:
                return $price;
        }
    }

    /**
     * Calculate price preview for a product
     *
     * @param WC_Product $product Product to preview.
     * @param array      $rule_data Rule configuration.
     * @return array|null Preview data or null if no price.
     */
    private function calculate_price_preview( $product, $rule_data ) {
        $current_price = $product->get_regular_price();
        
        if ( empty( $current_price ) || ! is_numeric( $current_price ) ) {
            return null;
        }

        $current_price = floatval( $current_price );
        $new_price = $this->calculate_new_price( $current_price, $rule_data );
        
        // Apply psychological pricing if enabled
        if ( ! empty( $rule_data['apply_rounding'] ) ) {
            $new_price = $this->apply_psychological_pricing( $new_price, $rule_data );
        }

        $new_price = max( 0, $new_price );

        // Get product name with variation details if applicable
        $product_name = $product->get_name();
        if ( $product->is_type( 'variation' ) ) {
            $parent = wc_get_product( $product->get_parent_id() );
            if ( $parent ) {
                $product_name = $parent->get_name() . ' - ' . $product->get_name();
            }
        }

        return array(
            'name'          => $product_name,
            'old_price'     => wc_price( $current_price ),
            'new_price'     => wc_price( $new_price ),
            'change_type'   => $new_price > $current_price ? 'increase' : 'decrease',
            'change_amount' => wc_price( abs( $new_price - $current_price ) ),
        );
    }

    /**
     * SIMPLIFIED: Backup product prices before changes
     *
     * @param array $products Array of products to backup.
     */
    private function backup_product_prices( $products ) {
        $backup_data = array();

        foreach ( $products as $product_data ) {
            $product = $product_data['product'];
            $product_id = $product->get_id();

            // Simple backup - just store the regular and sale prices
            $backup_data[ $product_id ] = array(
                'regular_price' => $product->get_regular_price(),
                'sale_price'    => $product->get_sale_price(),
            );
        }

        // Store backup data
        update_option( 'pricetunex_price_backup', $backup_data );
    }

    /**
     * Restore product price from backup
     *
     * @param WC_Product $product Product to restore.
     * @param array      $backup_info Backup data.
     */
    private function restore_product_price( $product, $backup_info ) {
        try {
            if ( isset( $backup_info['regular_price'] ) ) {
                $product->set_regular_price( $backup_info['regular_price'] );
            }
            
            if ( isset( $backup_info['sale_price'] ) ) {
                $product->set_sale_price( $backup_info['sale_price'] );
            }
            
            $product->save();

            // Clear product cache
            wc_delete_product_transients( $product->get_id() );

        } catch ( Exception $e ) {
            // Log error but continue with other products
            error_log( 'PriceTuneX: Failed to restore product ' . $product->get_id() . ' - ' . $e->getMessage() );
        }
    }

    /**
     * SIMPLIFIED: Log price action
     *
     * @param string $action Action type (apply, undo, preview).
     * @param array  $rule_data Rule configuration.
     * @param int    $products_count Number of products affected.
     */
    private function log_price_action( $action, $rule_data, $products_count ) {
        // Check if logging is enabled
        if ( ! pricetunex_get_setting( 'enable_logging', true ) ) {
            return;
        }

        $logs = get_option( 'pricetunex_activity_logs', array() );

        // Create log entry
        $log_entry = array(
            'timestamp'      => time(),
            'date'          => date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ),
            'action'        => $this->get_action_label( $action ),
            'action_type'   => $action,
            'description'   => $this->get_action_description( $action, $rule_data, $products_count ),
            'products_count' => $products_count,
            'user_id'       => get_current_user_id(),
        );

        // Add to beginning of logs array
        array_unshift( $logs, $log_entry );

        // Limit log entries
        $max_entries = pricetunex_get_setting( 'max_log_entries', 1000 );
        $logs = array_slice( $logs, 0, $max_entries );

        // Save logs
        update_option( 'pricetunex_activity_logs', $logs );

        // Update last update timestamp
        update_option( 'pricetunex_last_update', time() );
    }

    /**
     * Get action label for display
     *
     * @param string $action Action type.
     * @return string Action label.
     */
    private function get_action_label( $action ) {
        switch ( $action ) {
            case 'apply':
                return esc_html__( 'Applied Rules', 'pricetunex' );
            case 'undo':
                return esc_html__( 'Undone Changes', 'pricetunex' );
            case 'preview':
                return esc_html__( 'Previewed Rules', 'pricetunex' );
            default:
                return esc_html__( 'Unknown Action', 'pricetunex' );
        }
    }

    /**
     * SIMPLIFIED: Get action description
     *
     * @param string $action Action type.
     * @param array  $rule_data Rule configuration.
     * @param int    $products_count Number of products affected.
     * @return string Action description.
     */
    private function get_action_description( $action, $rule_data, $products_count ) {
        if ( 'undo' === $action ) {
            return sprintf(
                /* translators: %d: Number of products */
                esc_html__( 'Restored original prices for %d products.', 'pricetunex' ),
                $products_count
            );
        }

        if ( empty( $rule_data ) ) {
            return esc_html__( 'No rule data available.', 'pricetunex' );
        }

        $rule_type = isset( $rule_data['rule_type'] ) ? $rule_data['rule_type'] : 'unknown';
        $rule_value = isset( $rule_data['rule_value'] ) ? $rule_data['rule_value'] : 0;
        $target_scope = isset( $rule_data['target_scope'] ) ? $rule_data['target_scope'] : 'all';

        $description = '';

        // Rule description
        if ( 'percentage' === $rule_type ) {
            $description = sprintf(
                /* translators: %s: Percentage value */
                esc_html__( 'Applied %s%% price adjustment', 'pricetunex' ),
                $rule_value > 0 ? '+' . $rule_value : $rule_value
            );
        } else {
            $description = sprintf(
                /* translators: %s: Price amount */
                esc_html__( 'Applied %s price adjustment', 'pricetunex' ),
                $rule_value > 0 ? '+' . wc_price( $rule_value ) : wc_price( $rule_value )
            );
        }

        // Target scope
        if ( 'all' !== $target_scope ) {
            $description .= ' ' . sprintf(
                /* translators: %s: Target scope */
                esc_html__( 'to %s products', 'pricetunex' ),
                str_replace( '_', ' ', $target_scope )
            );
        } else {
            $description .= ' ' . esc_html__( 'to all products', 'pricetunex' );
        }

        $description .= sprintf(
            /* translators: %d: Number of products */
            esc_html__( '. Affected %d products.', 'pricetunex' ),
            $products_count
        );

        return $description;
    }

    /**
     * Get products statistics for dashboard
     *
     * @return array Statistics data.
     */
    public function get_statistics() {
        return $this->product_query->get_product_statistics();
    }

    /**
     * Validate rule data before processing
     *
     * @param array $rule_data Rule configuration.
     * @return array Validation result with 'valid' and 'message' keys.
     */
    public function validate_rule_data( $rule_data ) {
        // Check if rule value is set
        if ( ! isset( $rule_data['rule_value'] ) || empty( $rule_data['rule_value'] ) ) {
            return array(
                'valid'   => false,
                'message' => esc_html__( 'Rule value is required.', 'pricetunex' ),
            );
        }

        // Check if rule value is numeric
        if ( ! is_numeric( $rule_data['rule_value'] ) ) {
            return array(
                'valid'   => false,
                'message' => esc_html__( 'Rule value must be a number.', 'pricetunex' ),
            );
        }

        // Check rule type
        $valid_types = array( 'percentage', 'fixed' );
        if ( ! isset( $rule_data['rule_type'] ) || ! in_array( $rule_data['rule_type'], $valid_types, true ) ) {
            return array(
                'valid'   => false,
                'message' => esc_html__( 'Invalid rule type.', 'pricetunex' ),
            );
        }

        // Validate percentage range
        if ( 'percentage' === $rule_data['rule_type'] ) {
            $percentage = floatval( $rule_data['rule_value'] );
            if ( $percentage < -100 || $percentage > 1000 ) {
                return array(
                    'valid'   => false,
                    'message' => esc_html__( 'Percentage must be between -100% and 1000%.', 'pricetunex' ),
                );
            }
        }

        // Validate target scope specific requirements
        if ( isset( $rule_data['target_scope'] ) ) {
            $scope = $rule_data['target_scope'];
            
            if ( 'categories' === $scope && empty( $rule_data['categories'] ) ) {
                return array(
                    'valid'   => false,
                    'message' => esc_html__( 'Please select at least one category.', 'pricetunex' ),
                );
            }
            
            if ( 'tags' === $scope && empty( $rule_data['tags'] ) ) {
                return array(
                    'valid'   => false,
                    'message' => esc_html__( 'Please select at least one tag.', 'pricetunex' ),
                );
            }
            
            if ( 'product_types' === $scope && empty( $rule_data['product_types'] ) ) {
                return array(
                    'valid'   => false,
                    'message' => esc_html__( 'Please select at least one product type.', 'pricetunex' ),
                );
            }
        }

        return array(
            'valid'   => true,
            'message' => '',
        );
    }

    /**
     * Get backup data for undo functionality
     *
     * @return array Backup data.
     */
    public function get_backup_data() {
        return get_option( 'pricetunex_price_backup', array() );
    }

    /**
     * Check if backup data exists
     *
     * @return bool True if backup exists.
     */
    public function has_backup_data() {
        $backup = $this->get_backup_data();
        return ! empty( $backup );
    }

    /**
     * Clear backup data
     *
     * @return bool True on success.
     */
    public function clear_backup_data() {
        return delete_option( 'pricetunex_price_backup' );
    }

    /**
     * Get activity logs
     *
     * @param int $limit Number of logs to retrieve.
     * @return array Array of log entries.
     */
    public function get_activity_logs( $limit = 100 ) {
        $logs = get_option( 'pricetunex_activity_logs', array() );
        
        if ( $limit > 0 ) {
            $logs = array_slice( $logs, 0, $limit );
        }
        
        return $logs;
    }

    /**
     * Clear activity logs
     *
     * @return bool True on success.
     */
    public function clear_activity_logs() {
        return delete_option( 'pricetunex_activity_logs' );
    }

    /**
     * Get total count of products that would be affected by rules
     *
     * @param array $rule_data Rule configuration.
     * @return int Number of products affected.
     */
    public function get_affected_products_count( $rule_data ) {
        return $this->product_query->get_products_count_by_rules( $rule_data );
    }

    /**
     * Check if rules are valid for preview/apply
     *
     * @param array $rule_data Rule configuration.
     * @return bool True if rules are valid.
     */
    public function are_rules_valid( $rule_data ) {
        $validation = $this->validate_rule_data( $rule_data );
        return $validation['valid'];
    }

    /**
     * Get formatted rule summary for display
     *
     * @param array $rule_data Rule configuration.
     * @return string Formatted rule summary.
     */
    public function get_rule_summary( $rule_data ) {
        if ( empty( $rule_data ) ) {
            return esc_html__( 'No rules defined', 'pricetunex' );
        }

        $rule_type = isset( $rule_data['rule_type'] ) ? $rule_data['rule_type'] : 'percentage';
        $rule_value = isset( $rule_data['rule_value'] ) ? $rule_data['rule_value'] : 0;
        $target_scope = isset( $rule_data['target_scope'] ) ? $rule_data['target_scope'] : 'all';

        $summary = '';

        // Rule description
        if ( 'percentage' === $rule_type ) {
            $summary = sprintf(
                /* translators: %s: Percentage value */
                esc_html__( '%s%% price adjustment', 'pricetunex' ),
                $rule_value > 0 ? '+' . $rule_value : $rule_value
            );
        } else {
            $summary = sprintf(
                /* translators: %s: Price amount */
                esc_html__( '%s price adjustment', 'pricetunex' ),
                $rule_value > 0 ? '+' . wc_price( $rule_value ) : wc_price( $rule_value )
            );
        }

        // Target scope
        if ( 'all' !== $target_scope ) {
            $summary .= ' ' . sprintf(
                /* translators: %s: Target scope */
                esc_html__( 'for %s', 'pricetunex' ),
                str_replace( '_', ' ', $target_scope )
            );
        }

        return $summary;
    }
}