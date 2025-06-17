<?php
/**
 * Helper Functions for PriceTuneX
 *
 * @package PriceTuneX
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Get plugin setting with default fallback
 *
 * @param string $key     Setting key.
 * @param mixed  $default Default value if setting not found.
 * @return mixed Setting value or default.
 */
function pricetunex_get_setting( $key, $default = null ) {
    $settings = get_option( 'pricetunex_settings', array() );
    return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
}

/**
 * Update a specific plugin setting
 *
 * @param string $key   Setting key.
 * @param mixed  $value Setting value.
 * @return bool True on successful update.
 */
function pricetunex_update_setting( $key, $value ) {
    $settings = get_option( 'pricetunex_settings', array() );
    $settings[ $key ] = $value;
    return update_option( 'pricetunex_settings', $settings );
}

/**
 * Format price for display
 *
 * @param float  $price Price to format.
 * @param string $currency_symbol Currency symbol (optional).
 * @return string Formatted price.
 */
function pricetunex_format_price( $price, $currency_symbol = '' ) {
    if ( function_exists( 'wc_price' ) ) {
        return wc_price( $price );
    }
    
    // Fallback if WooCommerce is not available
    if ( empty( $currency_symbol ) ) {
        $currency_symbol = get_woocommerce_currency_symbol();
    }
    
    return $currency_symbol . number_format( floatval( $price ), 2 );
}

/**
 * Sanitize price value
 *
 * @param mixed $price Price value to sanitize.
 * @return float Sanitized price.
 */
function pricetunex_sanitize_price( $price ) {
    // Remove any non-numeric characters except decimal point and minus sign
    $price = preg_replace( '/[^0-9.-]/', '', $price );
    
    // Convert to float and ensure it's not negative for prices
    $price = floatval( $price );
    
    return max( 0, $price );
}

/**
 * Validate percentage value
 *
 * @param mixed $percentage Percentage to validate.
 * @param int   $min        Minimum allowed percentage.
 * @param int   $max        Maximum allowed percentage.
 * @return array Validation result with 'valid' and 'value' keys.
 */
function pricetunex_validate_percentage( $percentage, $min = -100, $max = 1000 ) {
    $percentage = floatval( $percentage );
    
    if ( $percentage < $min || $percentage > $max ) {
        return array(
            'valid' => false,
            'value' => 0,
            'message' => sprintf(
                /* translators: %1$d: minimum percentage, %2$d: maximum percentage */
                esc_html__( 'Percentage must be between %1$d%% and %2$d%%.', 'pricetunex' ),
                $min,
                $max
            ),
        );
    }
    
    return array(
        'valid' => true,
        'value' => $percentage,
        'message' => '',
    );
}

/**
 * Calculate percentage change between two values
 *
 * @param float $old_value Original value.
 * @param float $new_value New value.
 * @return float Percentage change.
 */
function pricetunex_calculate_percentage_change( $old_value, $new_value ) {
    if ( 0 === floatval( $old_value ) ) {
        return 0;
    }
    
    $change = $new_value - $old_value;
    return ( $change / $old_value ) * 100;
}

/**
 * Apply psychological pricing to a price
 *
 * @param float  $price        Price to round.
 * @param string $rounding_type Type of rounding (0.99, 0.95, 0.00, custom).
 * @param string $custom_ending Custom ending for rounding (optional).
 * @return float Rounded price.
 */
function pricetunex_apply_psychological_pricing( $price, $rounding_type = '0.99', $custom_ending = 0 ) {
    $price = floatval( $price );
    
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
            if ( ! empty( $custom_ending ) && is_numeric( $custom_ending ) ) {
                $ending = floatval( $custom_ending );
                if ( $ending >= 0 && $ending < 1 ) {
                    return floor( $price ) + $ending;
                }
            }
            // Fall back to .99 if custom ending is invalid
            return floor( $price ) + 0.99;
            
        default:
            return $price;
    }
}

/**
 * Check if WooCommerce is active and loaded
 *
 * @return bool True if WooCommerce is available.
 */
function pricetunex_is_woocommerce_active() {
    return class_exists( 'WooCommerce' ) && function_exists( 'WC' );
}

/**
 * Get WooCommerce currency symbol
 *
 * @return string Currency symbol.
 */
function pricetunex_get_currency_symbol() {
    if ( function_exists( 'get_woocommerce_currency_symbol' ) ) {
        return get_woocommerce_currency_symbol();
    }
    
    return '$'; // Fallback
}

/**
 * Log activity with proper formatting
 *
 * @param string $action      Action type.
 * @param string $description Description of the action.
 * @param array  $meta_data   Additional metadata.
 * @return bool True on successful logging.
 */
function pricetunex_log_activity( $action, $description, $meta_data = array() ) {
    // Check if logging is enabled
    if ( ! pricetunex_get_setting( 'enable_logging', true ) ) {
        return false;
    }
    
    $logs = get_option( 'pricetunex_activity_logs', array() );
    
    $log_entry = array(
        'timestamp'   => time(),
        'date'        => date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ),
        'action'      => sanitize_text_field( $action ),
        'description' => sanitize_text_field( $description ),
        'meta_data'   => $meta_data,
        'user_id'     => get_current_user_id(),
        'user_name'   => wp_get_current_user()->display_name,
    );
    
    // Add to beginning of logs array
    array_unshift( $logs, $log_entry );
    
    // Limit log entries
    $max_entries = pricetunex_get_setting( 'max_log_entries', 1000 );
    $logs = array_slice( $logs, 0, $max_entries );
    
    return update_option( 'pricetunex_activity_logs', $logs );
}

/**
 * Get activity logs
 *
 * @param int $limit Number of logs to retrieve.
 * @return array Array of log entries.
 */
function pricetunex_get_activity_logs( $limit = 100 ) {
    $logs = get_option( 'pricetunex_activity_logs', array() );
    
    if ( $limit > 0 ) {
        $logs = array_slice( $logs, 0, $limit );
    }
    
    return $logs;
}

/**
 * Clear activity logs
 *
 * @return bool True on successful clearing.
 */
function pricetunex_clear_activity_logs() {
    return delete_option( 'pricetunex_activity_logs' );
}

/**
 * Generate nonce for admin actions
 *
 * @param string $action Action name.
 * @return string Nonce value.
 */
function pricetunex_generate_nonce( $action = 'pricetunex_admin_nonce' ) {
    return wp_create_nonce( $action );
}

/**
 * Verify nonce for admin actions
 *
 * @param string $nonce  Nonce to verify.
 * @param string $action Action name.
 * @return bool True if nonce is valid.
 */
function pricetunex_verify_nonce( $nonce, $action = 'pricetunex_admin_nonce' ) {
    return wp_verify_nonce( $nonce, $action );
}

/**
 * Sanitize rule data array
 *
 * @param array $data Raw rule data.
 * @return array Sanitized rule data.
 */
function pricetunex_sanitize_rule_data( $data ) {
    $sanitized = array();
    
    // Rule type
    $sanitized['rule_type'] = isset( $data['rule_type'] ) ? sanitize_text_field( wp_unslash( $data['rule_type'] ) ) : 'percentage';
    
    // Rule value
    $sanitized['rule_value'] = isset( $data['rule_value'] ) ? floatval( $data['rule_value'] ) : 0;
    
    // Target scope
    $sanitized['target_scope'] = isset( $data['target_scope'] ) ? sanitize_text_field( wp_unslash( $data['target_scope'] ) ) : 'all';
    
    // Categories
    $sanitized['categories'] = array();
    if ( isset( $data['categories'] ) && is_array( $data['categories'] ) ) {
        $sanitized['categories'] = array_map( 'absint', $data['categories'] );
    }
    
    // Tags
    $sanitized['tags'] = array();
    if ( isset( $data['tags'] ) && is_array( $data['tags'] ) ) {
        $sanitized['tags'] = array_map( 'absint', $data['tags'] );
    }
    
    // Product types
    $sanitized['product_types'] = array();
    if ( isset( $data['product_types'] ) && is_array( $data['product_types'] ) ) {
        $sanitized['product_types'] = array_map( 'sanitize_text_field', array_map( 'wp_unslash', $data['product_types'] ) );
    }
    
    // Price range
    $sanitized['price_min'] = isset( $data['price_min'] ) ? pricetunex_sanitize_price( $data['price_min'] ) : 0;
    $sanitized['price_max'] = isset( $data['price_max'] ) ? pricetunex_sanitize_price( $data['price_max'] ) : 0;
    
    // Rounding options
    $sanitized['apply_rounding'] = isset( $data['apply_rounding'] ) && $data['apply_rounding'];
    $sanitized['rounding_type'] = isset( $data['rounding_type'] ) ? sanitize_text_field( wp_unslash( $data['rounding_type'] ) ) : '0.99';
    
    return $sanitized;
}

/**
 * Get human-readable file size
 *
 * @param int $size Size in bytes.
 * @return string Formatted file size.
 */
function pricetunex_format_file_size( $size ) {
    $units = array( 'B', 'KB', 'MB', 'GB', 'TB' );
    
    for ( $i = 0; $size > 1024 && $i < count( $units ) - 1; $i++ ) {
        $size /= 1024;
    }
    
    return round( $size, 2 ) . ' ' . $units[ $i ];
}

/**
 * Get plugin information
 *
 * @return array Plugin information array.
 */
function pricetunex_get_plugin_info() {
    $plugin_data = get_plugin_data( PRICETUNEX_PLUGIN_FILE );
    
    return array(
        'name'          => $plugin_data['Name'],
        'version'       => $plugin_data['Version'],
        'description'   => $plugin_data['Description'],
        'author'        => $plugin_data['Author'],
        'plugin_uri'    => $plugin_data['PluginURI'],
        'text_domain'   => $plugin_data['TextDomain'],
        'wp_version'    => get_bloginfo( 'version' ),
        'wc_version'    => defined( 'WC_VERSION' ) ? WC_VERSION : 'Not detected',
        'php_version'   => PHP_VERSION,
    );
}

/**
 * Check system requirements
 *
 * @return array Array of requirement checks.
 */
function pricetunex_check_system_requirements() {
    $requirements = array(
        'php_version' => array(
            'required' => '7.4',
            'current'  => PHP_VERSION,
            'status'   => version_compare( PHP_VERSION, '7.4', '>=' ),
        ),
        'wp_version' => array(
            'required' => '5.0',
            'current'  => get_bloginfo( 'version' ),
            'status'   => version_compare( get_bloginfo( 'version' ), '5.0', '>=' ),
        ),
        'wc_version' => array(
            'required' => '6.0',
            'current'  => defined( 'WC_VERSION' ) ? WC_VERSION : '0',
            'status'   => defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '6.0', '>=' ),
        ),
        'wc_active' => array(
            'required' => true,
            'current'  => pricetunex_is_woocommerce_active(),
            'status'   => pricetunex_is_woocommerce_active(),
        ),
    );
    
    return $requirements;
}

/**
 * Get memory usage information
 *
 * @return array Memory usage data.
 */
function pricetunex_get_memory_usage() {
    return array(
        'current'     => pricetunex_format_file_size( memory_get_usage() ),
        'peak'        => pricetunex_format_file_size( memory_get_peak_usage() ),
        'limit'       => ini_get( 'memory_limit' ),
        'usage_pct'   => round( ( memory_get_usage() / wp_convert_hr_to_bytes( ini_get( 'memory_limit' ) ) ) * 100, 2 ),
    );
}

/**
 * Debug log function
 *
 * @param mixed  $data    Data to log.
 * @param string $message Log message.
 * @return bool True if logged successfully.
 */
function pricetunex_debug_log( $data, $message = '' ) {
    // Only log if WP_DEBUG is enabled
    if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
        return false;
    }
    
    $log_message = '[PriceTuneX] ';
    
    if ( ! empty( $message ) ) {
        $log_message .= $message . ': ';
    }
    
    if ( is_array( $data ) || is_object( $data ) ) {
        $log_message .= wp_json_encode( $data, true );
    } else {
        $log_message .= $data;
    }
    
    // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
    return error_log( $log_message );
}

/**
 * Get default rounding options
 *
 * @return array Array of rounding options.
 */
function pricetunex_get_rounding_options() {
    return array(
        '0.99' => esc_html__( 'End in .99 (e.g., $19.99)', 'pricetunex' ),
        '0.95' => esc_html__( 'End in .95 (e.g., $19.95)', 'pricetunex' ),
        '0.89' => esc_html__( 'End in .89 (e.g., $19.89)', 'pricetunex' ),
        '0.00' => esc_html__( 'Round to whole number (e.g., $20)', 'pricetunex' ),
    );
}

/**
 * Get available product types for selection
 *
 * @return array Array of product types.
 */
function pricetunex_get_available_product_types() {
    $types = array(
        'simple'   => esc_html__( 'Simple', 'pricetunex' ),
        'variable' => esc_html__( 'Variable', 'pricetunex' ),
    );
    
    /**
     * Filter available product types
     *
     * @param array $types Array of product types.
     */
    return apply_filters( 'pricetunex_available_product_types', $types );
}

/**
 * Check if current user can manage prices
 *
 * @return bool True if user has permission.
 */
function pricetunex_current_user_can_manage_prices() {
    return current_user_can( 'manage_woocommerce' );
}

/**
 * Get admin page URL
 *
 * @param string $tab Optional tab to link to.
 * @return string Admin page URL.
 */
function pricetunex_get_admin_url( $tab = '' ) {
    $url = admin_url( 'admin.php?page=pricetunex-settings' );
    
    if ( ! empty( $tab ) ) {
        $url .= '#' . sanitize_text_field( $tab );
    }
    
    return $url;
}

/**
 * Generate admin notice HTML
 *
 * @param string $message Notice message.
 * @param string $type    Notice type (success, error, warning, info).
 * @param bool   $dismissible Whether notice is dismissible.
 * @return string Notice HTML.
 */
function pricetunex_generate_admin_notice( $message, $type = 'info', $dismissible = true ) {
    $classes = array( 'notice', 'notice-' . $type );
    
    if ( $dismissible ) {
        $classes[] = 'is-dismissible';
    }
    
    $html = sprintf(
        '<div class="%s"><p>%s</p></div>',
        esc_attr( implode( ' ', $classes ) ),
        wp_kses_post( $message )
    );
    
    return $html;
}

/**
 * Set admin notice transient
 *
 * @param string $message Notice message.
 * @param string $type    Notice type.
 * @param int    $expiry  Expiry time in seconds.
 * @return bool True on success.
 */
function pricetunex_set_admin_notice( $message, $type = 'info', $expiry = 30 ) {
    return set_transient( 'pricetunex_admin_notice', array(
        'message' => $message,
        'type'    => $type,
    ), $expiry );
}

/**
 * Get and delete admin notice transient
 *
 * @return array|false Notice data or false if none.
 */
function pricetunex_get_admin_notice() {
    $notice = get_transient( 'pricetunex_admin_notice' );
    
    if ( $notice ) {
        delete_transient( 'pricetunex_admin_notice' );
    }
    
    return $notice;
}

/**
 * Convert rule data to human-readable description
 *
 * @param array $rule_data Rule configuration.
 * @return string Human-readable description.
 */
function pricetunex_describe_rule( $rule_data ) {
    if ( empty( $rule_data ) ) {
        return esc_html__( 'No rule data available.', 'pricetunex' );
    }
    
    $description = '';
    
    // Rule type and value
    $rule_type = isset( $rule_data['rule_type'] ) ? $rule_data['rule_type'] : 'percentage';
    $rule_value = isset( $rule_data['rule_value'] ) ? $rule_data['rule_value'] : 0;
    
    if ( 'percentage' === $rule_type ) {
        $description = sprintf(
            /* translators: %s: percentage value with sign */
            esc_html__( '%s%% price adjustment', 'pricetunex' ),
            $rule_value > 0 ? '+' . $rule_value : $rule_value
        );
    } else {
        $description = sprintf(
            /* translators: %s: price amount with sign */
            esc_html__( '%s price adjustment', 'pricetunex' ),
            $rule_value > 0 ? '+' . pricetunex_format_price( $rule_value ) : pricetunex_format_price( $rule_value )
        );
    }
    
    // Target scope
    $target_scope = isset( $rule_data['target_scope'] ) ? $rule_data['target_scope'] : 'all';
    
    switch ( $target_scope ) {
        case 'categories':
            if ( ! empty( $rule_data['categories'] ) ) {
                $description .= ' ' . sprintf(
                    /* translators: %d: number of categories */
                    esc_html__( 'to %d selected categories', 'pricetunex' ),
                    count( $rule_data['categories'] )
                );
            }
            break;
            
        case 'tags':
            if ( ! empty( $rule_data['tags'] ) ) {
                $description .= ' ' . sprintf(
                    /* translators: %d: number of tags */
                    esc_html__( 'to %d selected tags', 'pricetunex' ),
                    count( $rule_data['tags'] )
                );
            }
            break;
            
        case 'product_types':
            if ( ! empty( $rule_data['product_types'] ) ) {
                $description .= ' ' . sprintf(
                    /* translators: %s: product types list */
                    esc_html__( 'to %s products', 'pricetunex' ),
                    implode( ', ', $rule_data['product_types'] )
                );
            }
            break;
            
        case 'price_range':
            $min = isset( $rule_data['price_min'] ) ? $rule_data['price_min'] : 0;
            $max = isset( $rule_data['price_max'] ) ? $rule_data['price_max'] : 0;
            
            if ( $min > 0 && $max > 0 ) {
                $description .= ' ' . sprintf(
                    /* translators: %1$s: minimum price, %2$s: maximum price */
                    esc_html__( 'to products priced between %1$s and %2$s', 'pricetunex' ),
                    pricetunex_format_price( $min ),
                    pricetunex_format_price( $max )
                );
            } elseif ( $min > 0 ) {
                $description .= ' ' . sprintf(
                    /* translators: %s: minimum price */
                    esc_html__( 'to products priced above %s', 'pricetunex' ),
                    pricetunex_format_price( $min )
                );
            } elseif ( $max > 0 ) {
                $description .= ' ' . sprintf(
                    /* translators: %s: maximum price */
                    esc_html__( 'to products priced below %s', 'pricetunex' ),
                    pricetunex_format_price( $max )
                );
            }
            break;
            
        case 'all':
        default:
            $description .= ' ' . esc_html__( 'to all products', 'pricetunex' );
            break;
    }
    
    // Psychological pricing
    if ( ! empty( $rule_data['apply_rounding'] ) ) {
        $rounding_type = isset( $rule_data['rounding_type'] ) ? $rule_data['rounding_type'] : '0.99';
        $description .= ' ' . sprintf(
            /* translators: %s: rounding type */
            esc_html__( 'with %s rounding', 'pricetunex' ),
            $rounding_type
        );
    }
    
    return $description;
}