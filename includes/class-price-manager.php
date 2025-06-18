<?php
/**
 * Price Manager - Core logic for price calculations and adjustments - UPDATED WITH TARGET PRICE TYPE
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
     * Apply price rules to products - UPDATED WITH TARGET PRICE TYPE
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
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log( 'PriceTuneX Error: ' . $e->getMessage() );
            
            return array(
                'success' => false,
                'message' => esc_html__( 'An error occurred while applying price changes.', 'pricetunex' ),
            );
        }
    }

    /**
     * UPDATED: Apply price change to a single product with target price type support
     *
     * @param WC_Product $product Product to update.
     * @param array      $rule_data Rule configuration.
     * @return bool Success status.
     */
    private function apply_price_change_to_product( $product, $rule_data ) {
        try {
            $target_price_type = isset( $rule_data['target_price_type'] ) ? $rule_data['target_price_type'] : 'smart';
            
            // Get current prices
            $regular_price = $product->get_regular_price();
            $sale_price = $product->get_sale_price();
            
            // Skip if no regular price set
            if ( empty( $regular_price ) || ! is_numeric( $regular_price ) ) {
                return false;
            }
            
            $regular_price = floatval( $regular_price );
            $sale_price = ! empty( $sale_price ) ? floatval( $sale_price ) : 0;
            
            // Determine which price(s) to update based on target type
            switch ( $target_price_type ) {
                case 'smart':
                    return $this->apply_smart_price_update( $product, $rule_data, $regular_price, $sale_price );
                    
                case 'regular_only':
                    return $this->apply_regular_price_update( $product, $rule_data, $regular_price );
                    
                case 'sale_only':
                    return $this->apply_sale_price_update( $product, $rule_data, $sale_price );
                    
                case 'both_prices':
                    return $this->apply_both_prices_update( $product, $rule_data, $regular_price, $sale_price );
                    
                default:
                    // Fallback to smart mode
                    return $this->apply_smart_price_update( $product, $rule_data, $regular_price, $sale_price );
            }

        } catch ( Exception $e ) {
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
            error_log( 'PriceTuneX Error updating product ' . $product->get_id() . ': ' . $e->getMessage() );
            return false;
        }
    }

    /**
     * Apply smart price update - updates the price customers actually see
     *
     * @param WC_Product $product Product to update.
     * @param array      $rule_data Rule configuration.
     * @param float      $regular_price Current regular price.
     * @param float      $sale_price Current sale price.
     * @return bool Success status.
     */
    private function apply_smart_price_update( $product, $rule_data, $regular_price, $sale_price ) {
        if ( $sale_price > 0 ) {
            // Product has active sale - update sale price (what customers see)
            $new_sale_price = $this->calculate_new_price( $sale_price, $rule_data );
            
            // Apply psychological pricing if enabled
            if ( ! empty( $rule_data['apply_rounding'] ) ) {
                $new_sale_price = $this->apply_psychological_pricing( $new_sale_price, $rule_data );
            }
            
            // Ensure sale price doesn't exceed regular price
            $new_sale_price = max( 0, min( $new_sale_price, $regular_price ) );
            
            $product->set_sale_price( $new_sale_price );
        } else {
            // No sale price - update regular price
            $new_regular_price = $this->calculate_new_price( $regular_price, $rule_data );
            
            // Apply psychological pricing if enabled
            if ( ! empty( $rule_data['apply_rounding'] ) ) {
                $new_regular_price = $this->apply_psychological_pricing( $new_regular_price, $rule_data );
            }
            
            $new_regular_price = max( 0, $new_regular_price );
            $product->set_regular_price( $new_regular_price );
        }
        
        $product->save();
        wc_delete_product_transients( $product->get_id() );
        
        return true;
    }

    /**
     * Apply regular price only update
     *
     * @param WC_Product $product Product to update.
     * @param array      $rule_data Rule configuration.
     * @param float      $regular_price Current regular price.
     * @return bool Success status.
     */
    private function apply_regular_price_update( $product, $rule_data, $regular_price ) {
        $new_regular_price = $this->calculate_new_price( $regular_price, $rule_data );
        
        // Apply psychological pricing if enabled
        if ( ! empty( $rule_data['apply_rounding'] ) ) {
            $new_regular_price = $this->apply_psychological_pricing( $new_regular_price, $rule_data );
        }
        
        $new_regular_price = max( 0, $new_regular_price );
        $product->set_regular_price( $new_regular_price );
        
        // Check if sale price is now higher than regular price and remove if so
        $sale_price = $product->get_sale_price();
        if ( ! empty( $sale_price ) && floatval( $sale_price ) >= $new_regular_price ) {
            $product->set_sale_price( '' );
        }
        
        $product->save();
        wc_delete_product_transients( $product->get_id() );
        
        return true;
    }

    /**
     * Apply sale price only update
     *
     * @param WC_Product $product Product to update.
     * @param array      $rule_data Rule configuration.
     * @param float      $sale_price Current sale price.
     * @return bool Success status.
     */
    private function apply_sale_price_update( $product, $rule_data, $sale_price ) {
        // Skip products without sale prices
        if ( $sale_price <= 0 ) {
            return false;
        }
        
        $new_sale_price = $this->calculate_new_price( $sale_price, $rule_data );
        
        // Apply psychological pricing if enabled
        if ( ! empty( $rule_data['apply_rounding'] ) ) {
            $new_sale_price = $this->apply_psychological_pricing( $new_sale_price, $rule_data );
        }
        
        // Ensure sale price doesn't exceed regular price
        $regular_price = floatval( $product->get_regular_price() );
        $new_sale_price = max( 0, min( $new_sale_price, $regular_price ) );
        
        $product->set_sale_price( $new_sale_price );
        $product->save();
        wc_delete_product_transients( $product->get_id() );
        
        return true;
    }

    /**
     * Apply both prices update - maintains discount relationship
     *
     * @param WC_Product $product Product to update.
     * @param array      $rule_data Rule configuration.
     * @param float      $regular_price Current regular price.
     * @param float      $sale_price Current sale price.
     * @return bool Success status.
     */
    private function apply_both_prices_update( $product, $rule_data, $regular_price, $sale_price ) {
        // Update regular price
        $new_regular_price = $this->calculate_new_price( $regular_price, $rule_data );
        
        // Apply psychological pricing if enabled
        if ( ! empty( $rule_data['apply_rounding'] ) ) {
            $new_regular_price = $this->apply_psychological_pricing( $new_regular_price, $rule_data );
        }
        
        $new_regular_price = max( 0, $new_regular_price );
        $product->set_regular_price( $new_regular_price );
        
        // Update sale price if it exists
        if ( $sale_price > 0 ) {
            $new_sale_price = $this->calculate_new_price( $sale_price, $rule_data );
            
            // Apply psychological pricing if enabled
            if ( ! empty( $rule_data['apply_rounding'] ) ) {
                $new_sale_price = $this->apply_psychological_pricing( $new_sale_price, $rule_data );
            }
            
            // Ensure sale price doesn't exceed new regular price
            $new_sale_price = max( 0, min( $new_sale_price, $new_regular_price ) );
            
            $product->set_sale_price( $new_sale_price );
        }
        
        $product->save();
        wc_delete_product_transients( $product->get_id() );
        
        return true;
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
        $custom_ending = isset( $rule_data['custom_ending'] ) ? floatval( $rule_data['custom_ending'] ) : 0;

        switch ( $rounding_type ) {
            case '0.99':
                return floor( $price ) + 0.99;
            
            case '0.97':
                return floor( $price ) + 0.97;
            
            case '0.95':
                return floor( $price ) + 0.95;
                
            case '0.89':
                return floor( $price ) + 0.89;
            
            case '0.00':
                return round( $price );
            
            case 'custom':
                if ( $custom_ending >= 0 && $custom_ending < 1 ) {
                    return floor( $price ) + $custom_ending;
                }
                // Fall back to .99 if custom ending is invalid
                return floor( $price ) + 0.99;
            
            default:
                return $price;
        }
    }

    /**
     * Preview price rules without applying changes - UPDATED
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

            // Filter products based on target price type
            $target_price_type = isset( $rule_data['target_price_type'] ) ? $rule_data['target_price_type'] : 'smart';
            if ( 'sale_only' === $target_price_type ) {
                // Filter to only products with sale prices
                $products = array_filter( $products, function( $product_data ) {
                    $product = $product_data['product'];
                    $sale_price = $product->get_sale_price();
                    return ! empty( $sale_price ) && floatval( $sale_price ) > 0;
                });
                
                if ( empty( $products ) ) {
                    return array(
                        'success' => false,
                        'message' => esc_html__( 'No products with sale prices found matching the criteria.', 'pricetunex' ),
                    );
                }
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
     * Calculate price preview for a product - UPDATED TO SHOW BOTH PRICES
     *
     * @param WC_Product $product Product to preview.
     * @param array      $rule_data Rule configuration.
     * @return array|null Preview data or null if no price.
     */
    private function calculate_price_preview( $product, $rule_data ) {
        $regular_price = $product->get_regular_price();
        $sale_price = $product->get_sale_price();

        if ( empty( $regular_price ) || ! is_numeric( $regular_price ) ) {
            return null;
        }

        $regular_price = floatval( $regular_price );
        $sale_price = ! empty( $sale_price ) ? floatval( $sale_price ) : 0;

        $target_price_type = isset( $rule_data['target_price_type'] ) ? $rule_data['target_price_type'] : 'smart';

        // Get product name with variation details if applicable
        $product_name = $product->get_name();
        if ( $product->is_type( 'variation' ) ) {
            $parent = wc_get_product( $product->get_parent_id() );
            if ( $parent ) {
                $product_name = $parent->get_name() . ' - ' . $product->get_name();
            }
        }

        // Base preview structure
        $preview = array(
            'name' => $product_name,
            'target_price_type' => $target_price_type,
            'regular_price' => array(
                'original' => $regular_price,
                'formatted_original' => wc_price( $regular_price ),
            ),
            'sale_price' => array(
                'original' => $sale_price,
                'formatted_original' => $sale_price > 0 ? wc_price( $sale_price ) : null,
            ),
            'updates' => array(), // Will contain the actual updates
            'primary_change' => array(), // The main price change customers see
        );

        // Calculate updates based on target price type
        switch ( $target_price_type ) {
            case 'smart':
                $preview = $this->calculate_smart_preview( $preview, $rule_data );
                break;

            case 'regular_only':
                $preview = $this->calculate_regular_only_preview( $preview, $rule_data );
                break;

            case 'sale_only':
                if ( $sale_price <= 0 ) {
                    return null; // Skip products without sale prices
                }
                $preview = $this->calculate_sale_only_preview( $preview, $rule_data );
                break;

            case 'both_prices':
                $preview = $this->calculate_both_prices_preview( $preview, $rule_data );
                break;
        }

        return $preview;
    }

    /**
     * Calculate smart preview (updates the price customers see)
     */
    private function calculate_smart_preview( $preview, $rule_data ) {
        $sale_price = $preview['sale_price']['original'];
        $regular_price = $preview['regular_price']['original'];

        if ( $sale_price > 0 ) {
            // Update sale price (what customers see)
            $new_price = $this->calculate_new_price( $sale_price, $rule_data );
            $new_price = $this->apply_rounding_if_enabled( $new_price, $rule_data );
            $new_price = max( 0, min( $new_price, $regular_price ) );

            $preview['updates']['sale'] = array(
                'new' => $new_price,
                'formatted_new' => wc_price( $new_price ),
            );

            $preview['primary_change'] = array(
                'type' => 'sale',
                'label' => 'Sale Price',
                'old' => $sale_price,
                'new' => $new_price,
                'formatted_old' => wc_price( $sale_price ),
                'formatted_new' => wc_price( $new_price ),
                'change_type' => $new_price > $sale_price ? 'increase' : 'decrease',
                'change_amount' => abs( $new_price - $sale_price ),
                'formatted_change' => wc_price( abs( $new_price - $sale_price ) ),
            );
        } else {
            // Update regular price
            $new_price = $this->calculate_new_price( $regular_price, $rule_data );
            $new_price = $this->apply_rounding_if_enabled( $new_price, $rule_data );
            $new_price = max( 0, $new_price );

            $preview['updates']['regular'] = array(
                'new' => $new_price,
                'formatted_new' => wc_price( $new_price ),
            );

            $preview['primary_change'] = array(
                'type' => 'regular',
                'label' => 'Regular Price',
                'old' => $regular_price,
                'new' => $new_price,
                'formatted_old' => wc_price( $regular_price ),
                'formatted_new' => wc_price( $new_price ),
                'change_type' => $new_price > $regular_price ? 'increase' : 'decrease',
                'change_amount' => abs( $new_price - $regular_price ),
                'formatted_change' => wc_price( abs( $new_price - $regular_price ) ),
            );
        }

        return $preview;
    }

    /**
     * Calculate regular only preview
     */
    private function calculate_regular_only_preview( $preview, $rule_data ) {
        $regular_price = $preview['regular_price']['original'];

        $new_price = $this->calculate_new_price( $regular_price, $rule_data );
        $new_price = $this->apply_rounding_if_enabled( $new_price, $rule_data );
        $new_price = max( 0, $new_price );

        $preview['updates']['regular'] = array(
            'new' => $new_price,
            'formatted_new' => wc_price( $new_price ),
        );

        $preview['primary_change'] = array(
            'type' => 'regular',
            'label' => 'Regular Price',
            'old' => $regular_price,
            'new' => $new_price,
            'formatted_old' => wc_price( $regular_price ),
            'formatted_new' => wc_price( $new_price ),
            'change_type' => $new_price > $regular_price ? 'increase' : 'decrease',
            'change_amount' => abs( $new_price - $regular_price ),
            'formatted_change' => wc_price( abs( $new_price - $regular_price ) ),
        );

        return $preview;
    }

    /**
     * Calculate sale only preview
     */
    private function calculate_sale_only_preview( $preview, $rule_data ) {
        $sale_price = $preview['sale_price']['original'];
        $regular_price = $preview['regular_price']['original'];

        $new_price = $this->calculate_new_price( $sale_price, $rule_data );
        $new_price = $this->apply_rounding_if_enabled( $new_price, $rule_data );
        $new_price = max( 0, min( $new_price, $regular_price ) );

        $preview['updates']['sale'] = array(
            'new' => $new_price,
            'formatted_new' => wc_price( $new_price ),
        );

        $preview['primary_change'] = array(
            'type' => 'sale',
            'label' => 'Sale Price',
            'old' => $sale_price,
            'new' => $new_price,
            'formatted_old' => wc_price( $sale_price ),
            'formatted_new' => wc_price( $new_price ),
            'change_type' => $new_price > $sale_price ? 'increase' : 'decrease',
            'change_amount' => abs( $new_price - $sale_price ),
            'formatted_change' => wc_price( abs( $new_price - $sale_price ) ),
        );

        return $preview;
    }

    /**
     * Calculate both prices preview - SIMPLIFIED
     */
    private function calculate_both_prices_preview( $preview, $rule_data ) {
        $regular_price = $preview['regular_price']['original'];
        $sale_price = $preview['sale_price']['original'];

        // Update regular price
        $new_regular = $this->calculate_new_price( $regular_price, $rule_data );
        $new_regular = $this->apply_rounding_if_enabled( $new_regular, $rule_data );
        $new_regular = max( 0, $new_regular );

        $preview['updates']['regular'] = array(
            'new' => $new_regular,
            'formatted_new' => wc_price( $new_regular ),
        );

        // Update sale price if exists
        if ( $sale_price > 0 ) {
            $new_sale = $this->calculate_new_price( $sale_price, $rule_data );
            $new_sale = $this->apply_rounding_if_enabled( $new_sale, $rule_data );
            $new_sale = max( 0, min( $new_sale, $new_regular ) );

            $preview['updates']['sale'] = array(
                'new' => $new_sale,
                'formatted_new' => wc_price( $new_sale ),
            );

            // Primary change is the customer-facing price (sale price)
            $preview['primary_change'] = array(
                'type' => 'both',
                'label' => 'Both Prices',
                'old' => $sale_price,
                'new' => $new_sale,
                'formatted_old' => wc_price( $sale_price ),
                'formatted_new' => wc_price( $new_sale ),
                'change_type' => $new_sale > $sale_price ? 'increase' : 'decrease',
                'change_amount' => abs( $new_sale - $sale_price ),
                'formatted_change' => wc_price( abs( $new_sale - $sale_price ) ),
            );
        } else {
            // Primary change is regular price
            $preview['primary_change'] = array(
                'type' => 'both',
                'label' => 'Both Prices',
                'old' => $regular_price,
                'new' => $new_regular,
                'formatted_old' => wc_price( $regular_price ),
                'formatted_new' => wc_price( $new_regular ),
                'change_type' => $new_regular > $regular_price ? 'increase' : 'decrease',
                'change_amount' => abs( $new_regular - $regular_price ),
                'formatted_change' => wc_price( abs( $new_regular - $regular_price ) ),
            );
        }

        return $preview;
    }

    /**
     * Helper: Apply rounding if enabled
     */
    private function apply_rounding_if_enabled( $price, $rule_data ) {
        if ( ! empty( $rule_data['apply_rounding'] ) ) {
            return $this->apply_psychological_pricing( $price, $rule_data );
        }
        return $price;
    }

    /**
     * UPDATED: Backup product prices before changes - now includes sale prices
     *
     * @param array $products Array of products to backup.
     */
    private function backup_product_prices( $products ) {
        $backup_data = array();

        foreach ( $products as $product_data ) {
            $product = $product_data['product'];
            $product_id = $product->get_id();

            // Backup both regular and sale prices
            $backup_data[ $product_id ] = array(
                'regular_price' => $product->get_regular_price(),
                'sale_price'    => $product->get_sale_price(),
            );
        }

        // Store backup data
        update_option( 'pricetunex_price_backup', $backup_data );
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
            // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
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
     * Get action description - UPDATED to include target price type
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
        $target_price_type = isset( $rule_data['target_price_type'] ) ? $rule_data['target_price_type'] : 'smart';

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

        // Add target price type info
        $price_type_labels = array(
            'smart'        => esc_html__( 'using smart price selection', 'pricetunex' ),
            'regular_only' => esc_html__( 'to regular prices only', 'pricetunex' ),
            'sale_only'    => esc_html__( 'to sale prices only', 'pricetunex' ),
            'both_prices'  => esc_html__( 'to both regular and sale prices', 'pricetunex' ),
        );
        
        if ( isset( $price_type_labels[ $target_price_type ] ) ) {
            $description .= ' ' . $price_type_labels[ $target_price_type ];
        }

        // Target scope
        if ( 'all' !== $target_scope ) {
            $description .= ' ' . sprintf(
                /* translators: %s: Target scope */
                esc_html__( 'for %s products', 'pricetunex' ),
                str_replace( '_', ' ', $target_scope )
            );
        } else {
            $description .= ' ' . esc_html__( 'for all products', 'pricetunex' );
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