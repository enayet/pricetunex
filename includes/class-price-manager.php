<?php
/**
 * Price Manager - Core logic for price calculations and adjustments
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
     * Apply price rules to products
     *
     * @param array $rule_data Rule configuration.
     * @return array Result array with success status and details.
     */
    public function apply_rules( $rule_data ) {
        try {
            // Validate rule data
            $validation = $this->validate_rule_data( $rule_data );
            if ( ! $validation['valid'] ) {
                return array(
                    'success' => false,
                    'message' => $validation['message'],
                );
            }

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
            $changes_made = array();
            $products_updated = 0;

            foreach ( $products as $product_data ) {
                $product = $product_data['product'];
                $change_result = $this->apply_price_change( $product, $rule_data );
                
                if ( $change_result['success'] ) {
                    $changes_made[] = $change_result['change_data'];
                    $products_updated++;
                }
            }

            // Log the action
            $this->log_price_action( 'apply', $rule_data, $products_updated );

            return array(
                'success'          => true,
                'products_updated' => $products_updated,
                'changes_made'     => $changes_made,
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
            // Validate rule data
            $validation = $this->validate_rule_data( $rule_data );
            if ( ! $validation['valid'] ) {
                return array(
                    'success' => false,
                    'message' => $validation['message'],
                );
            }

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

                // Restore prices based on product type
                if ( $product->is_type( 'variable' ) ) {
                    // Handle variable product variations
                    if ( isset( $backup_info['variations'] ) ) {
                        foreach ( $backup_info['variations'] as $variation_id => $variation_backup ) {
                            $variation = wc_get_product( $variation_id );
                            if ( $variation ) {
                                $this->restore_product_price( $variation, $variation_backup );
                            }
                        }
                    }
                } else {
                    // Handle simple products
                    $this->restore_product_price( $product, $backup_info );
                }

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
     * Validate rule data
     *
     * @param array $rule_data Rule configuration to validate.
     * @return array Validation result.
     */
    private function validate_rule_data( $rule_data ) {
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
     * Apply price change to a single product
     *
     * @param WC_Product $product Product to update.
     * @param array      $rule_data Rule configuration.
     * @return array Change result.
     */
    private function apply_price_change( $product, $rule_data ) {
        try {
            $old_price = $product->get_regular_price();
            
            // Skip if no price set
            if ( empty( $old_price ) ) {
                return array( 'success' => false );
            }

            // Calculate new price
            $new_price = $this->calculate_new_price( floatval( $old_price ), $rule_data );
            
            // Apply psychological pricing if enabled
            if ( ! empty( $rule_data['apply_rounding'] ) ) {
                $new_price = $this->apply_psychological_pricing( $new_price, $rule_data );
            }

            // Ensure price is not negative
            $new_price = max( 0, $new_price );

            // Handle different product types
            if ( $product->is_type( 'variable' ) ) {
                return $this->update_variable_product_prices( $product, $rule_data );
            } else {
                return $this->update_simple_product_price( $product, $new_price, $old_price );
            }

        } catch ( Exception $e ) {
            return array( 'success' => false );
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
                // This could be extended to allow custom decimal endings
                return floor( $price ) + 0.99;
            
            default:
                return $price;
        }
    }

    /**
     * Update simple product price
     *
     * @param WC_Product $product Product to update.
     * @param float      $new_price New price.
     * @param float      $old_price Original price.
     * @return array Update result.
     */
    private function update_simple_product_price( $product, $new_price, $old_price ) {
        try {
            // Set new regular price
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

            return array(
                'success'     => true,
                'change_data' => array(
                    'product_id'   => $product->get_id(),
                    'product_name' => $product->get_name(),
                    'old_price'    => wc_price( $old_price ),
                    'new_price'    => wc_price( $new_price ),
                    'change_type'  => $new_price > $old_price ? 'increase' : 'decrease',
                    'change_amount' => wc_price( abs( $new_price - $old_price ) ),
                ),
            );

        } catch ( Exception $e ) {
            return array( 'success' => false );
        }
    }

    /**
     * Update variable product prices
     *
     * @param WC_Product_Variable $product Variable product to update.
     * @param array               $rule_data Rule configuration.
     * @return array Update result.
     */
    private function update_variable_product_prices( $product, $rule_data ) {
        try {
            $variations = $product->get_children();
            $updated_variations = 0;
            $changes_made = array();

            foreach ( $variations as $variation_id ) {
                $variation = wc_get_product( $variation_id );
                
                if ( ! $variation ) {
                    continue;
                }

                $old_price = $variation->get_regular_price();
                
                if ( empty( $old_price ) ) {
                    continue;
                }

                // Calculate new price for variation
                $new_price = $this->calculate_new_price( floatval( $old_price ), $rule_data );
                
                // Apply psychological pricing if enabled
                if ( ! empty( $rule_data['apply_rounding'] ) ) {
                    $new_price = $this->apply_psychological_pricing( $new_price, $rule_data );
                }

                // Ensure price is not negative
                $new_price = max( 0, $new_price );

                // Update variation price
                $variation->set_regular_price( $new_price );
                
                // Handle sale price
                $sale_price = $variation->get_sale_price();
                if ( ! empty( $sale_price ) && floatval( $sale_price ) >= $new_price ) {
                    $variation->set_sale_price( '' );
                }
                
                $variation->save();
                $updated_variations++;

                $changes_made[] = array(
                    'product_id'   => $variation->get_id(),
                    'product_name' => $product->get_name() . ' - ' . $variation->get_name(),
                    'old_price'    => wc_price( $old_price ),
                    'new_price'    => wc_price( $new_price ),
                    'change_type'  => $new_price > $old_price ? 'increase' : 'decrease',
                    'change_amount' => wc_price( abs( $new_price - $old_price ) ),
                );
            }

            // Update parent product to sync prices
            $product->sync_meta_data();
            $product->save();

            // Clear product cache
            wc_delete_product_transients( $product->get_id() );

            return array(
                'success'     => $updated_variations > 0,
                'change_data' => $changes_made,
            );

        } catch ( Exception $e ) {
            return array( 'success' => false );
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
        
        if ( empty( $current_price ) ) {
            return null;
        }

        $current_price = floatval( $current_price );
        $new_price = $this->calculate_new_price( $current_price, $rule_data );
        
        // Apply psychological pricing if enabled
        if ( ! empty( $rule_data['apply_rounding'] ) ) {
            $new_price = $this->apply_psychological_pricing( $new_price, $rule_data );
        }

        $new_price = max( 0, $new_price );

        return array(
            'name'          => $product->get_name(),
            'old_price'     => wc_price( $current_price ),
            'new_price'     => wc_price( $new_price ),
            'change_type'   => $new_price > $current_price ? 'increase' : 'decrease',
            'change_amount' => wc_price( abs( $new_price - $current_price ) ),
        );
    }

    /**
     * Backup product prices before changes
     *
     * @param array $products Array of products to backup.
     */
    private function backup_product_prices( $products ) {
        $backup_data = array();

        foreach ( $products as $product_data ) {
            $product = $product_data['product'];
            $product_id = $product->get_id();

            if ( $product->is_type( 'variable' ) ) {
                // Backup variable product and its variations
                $backup_data[ $product_id ] = array(
                    'regular_price' => $product->get_regular_price(),
                    'sale_price'    => $product->get_sale_price(),
                    'variations'    => array(),
                );

                $variations = $product->get_children();
                foreach ( $variations as $variation_id ) {
                    $variation = wc_get_product( $variation_id );
                    if ( $variation ) {
                        $backup_data[ $product_id ]['variations'][ $variation_id ] = array(
                            'regular_price' => $variation->get_regular_price(),
                            'sale_price'    => $variation->get_sale_price(),
                        );
                    }
                }
            } else {
                // Backup simple product
                $backup_data[ $product_id ] = array(
                    'regular_price' => $product->get_regular_price(),
                    'sale_price'    => $product->get_sale_price(),
                );
            }
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
     * Log price action
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
            'rule_data'     => $rule_data,
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
     * Get action description
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

        // Add psychological pricing note
        if ( ! empty( $rule_data['apply_rounding'] ) ) {
            $rounding_type = isset( $rule_data['rounding_type'] ) ? $rule_data['rounding_type'] : '0.99';
            $description .= ' ' . sprintf(
                /* translators: %s: Rounding type */
                esc_html__( 'with %s rounding', 'pricetunex' ),
                $rounding_type
            );
        }

        $description .= sprintf(
            /* translators: %d: Number of products */
            esc_html__( '. Affected %d products.', 'pricetunex' ),
            $products_count
        );

        return $description;
    }
}