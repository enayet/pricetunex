<?php
/**
 * Admin functionality for PriceTuneX
 *
 * @package PriceTuneX
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Admin class
 */
class Pricetunex_Admin {

    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */    
    private function init_hooks() {
        // Add admin menu
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );

        // Register admin scripts and styles
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

        // Handle AJAX requests - FIXED: Make sure these are properly registered
        add_action( 'wp_ajax_pricetunex_apply_rules', array( $this, 'ajax_apply_rules' ) );
        add_action( 'wp_ajax_pricetunex_preview_rules', array( $this, 'ajax_preview_rules' ) );
        add_action( 'wp_ajax_pricetunex_undo_changes', array( $this, 'ajax_undo_changes' ) );
        add_action( 'wp_ajax_pricetunex_get_logs', array( $this, 'ajax_get_logs' ) );
        add_action( 'wp_ajax_pricetunex_get_stats', array( $this, 'ajax_get_stats' ) );
        add_action( 'wp_ajax_pricetunex_clear_logs', array( $this, 'ajax_clear_logs' ) );
        add_action( 'wp_ajax_pricetunex_search_products', array( $this, 'ajax_search_products' ) );

        // Handle form submissions
        add_action( 'admin_post_pricetunex_save_settings', array( $this, 'handle_save_settings' ) );

        // Add admin notices
        add_action( 'admin_notices', array( $this, 'display_admin_notices' ) );
        
        // ADDED: Debug logging hook
        add_action( 'wp_ajax_pricetunex_debug', array( $this, 'ajax_debug' ) );
    }    
    
    /**
     * ADDED: Debug AJAX handler to test connection
     */
    public function ajax_debug() {
        // Simple debug endpoint
        wp_send_json_success( array(
            'message' => 'AJAX connection working',
            'time' => current_time( 'mysql' )
        ) );
    }

    /**
     * Add admin menu under WooCommerce
     */
    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            esc_html__( 'PriceTuneX Settings', 'pricetunex' ),
            esc_html__( 'PriceTuneX', 'pricetunex' ),
            'manage_woocommerce',
            'pricetunex-settings',
            array( $this, 'render_admin_page' )
        );
    }

    /**
     * Enqueue admin scripts and styles
     *
     * @param string $hook The current admin page hook.
     */
    public function enqueue_admin_assets( $hook ) {
        // Only load on our admin page
        if ( 'woocommerce_page_pricetunex-settings' !== $hook ) {
            return;
        }

        // Enqueue styles
        wp_enqueue_style(
            'pricetunex-admin',
            PRICETUNEX_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            PRICETUNEX_VERSION
        );

        // Enqueue scripts
        wp_enqueue_script(
            'pricetunex-admin',
            PRICETUNEX_PLUGIN_URL . 'assets/js/admin.js',
            array( 'jquery', 'wp-util' ),
            PRICETUNEX_VERSION,
            true
        );

        // FIXED: Enhanced localization with debug info
        wp_localize_script(
            'pricetunex-admin',
            'pricetunex_ajax',
            array(
                'ajax_url'    => admin_url( 'admin-ajax.php' ),
                'nonce'       => wp_create_nonce( 'pricetunex_admin_nonce' ),
                'debug'       => defined( 'WP_DEBUG' ) && WP_DEBUG,
                'strings'     => array(
                    'confirm_apply'    => esc_html__( 'Are you sure you want to apply these price changes? This action cannot be undone without using the undo feature.', 'pricetunex' ),
                    'confirm_undo'     => esc_html__( 'Are you sure you want to undo the last price changes?', 'pricetunex' ),
                    'processing'       => esc_html__( 'Processing...', 'pricetunex' ),
                    'error'           => esc_html__( 'An error occurred. Please try again.', 'pricetunex' ),
                    'success'         => esc_html__( 'Operation completed successfully.', 'pricetunex' ),
                    'validation_error' => esc_html__( 'Please correct the form errors before proceeding.', 'pricetunex' ),
                ),
            )
        );
    }

    /**
     * Render the main admin page
     */
    public function render_admin_page() {
        // Check user capabilities
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'pricetunex' ) );
        }

        // Get current settings
        $settings = get_option( 'pricetunex_settings', array() );

        // Include the admin page template
        include_once PRICETUNEX_PLUGIN_PATH . 'admin/views/settings-page.php';
    }

    /**
     * Handle save settings form submission
     */
    public function handle_save_settings() {
        // Verify nonce
        if ( ! isset( $_POST['pricetunex_settings_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['pricetunex_settings_nonce'] ) ), 'pricetunex_save_settings' ) ) {
            wp_die( esc_html__( 'Security check failed.', 'pricetunex' ) );
        }

        // Check user capabilities
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions to perform this action.', 'pricetunex' ) );
        }

        // Get current settings
        $settings = get_option( 'pricetunex_settings', array() );

        // Update settings
        $settings['enable_logging'] = isset( $_POST['enable_logging'] ) ? true : false;
        $settings['max_log_entries'] = isset( $_POST['max_log_entries'] ) ? absint( $_POST['max_log_entries'] ) : 1000;
        $settings['backup_prices'] = isset( $_POST['backup_prices'] ) ? true : false;
        $settings['default_rounding'] = isset( $_POST['default_rounding'] ) ? sanitize_text_field( wp_unslash( $_POST['default_rounding'] ) ) : '0.99';

        // Save settings
        update_option( 'pricetunex_settings', $settings );

        // Set success message
        set_transient( 'pricetunex_admin_notice', array(
            'type'    => 'success',
            'message' => esc_html__( 'Settings saved successfully.', 'pricetunex' ),
        ), 30 );

        // Redirect back to settings page
        wp_safe_redirect( admin_url( 'admin.php?page=pricetunex-settings' ) );
        exit;
    }

    /**
     * AJAX handler for applying price rules - ENHANCED WITH ERROR HANDLING
     */
    public function ajax_apply_rules() {
        // ADDED: Error logging for debugging
        error_log( 'PriceTuneX: ajax_apply_rules called' );
        
        try {
            // Verify nonce
            if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'pricetunex_admin_nonce' ) ) {
                error_log( 'PriceTuneX: Nonce verification failed in ajax_apply_rules' );
                wp_send_json_error( array( 'message' => esc_html__( 'Security check failed.', 'pricetunex' ) ) );
            }

            // Check user capabilities
            if ( ! current_user_can( 'manage_woocommerce' ) ) {
                error_log( 'PriceTuneX: User capability check failed in ajax_apply_rules' );
                wp_send_json_error( array( 'message' => esc_html__( 'Insufficient permissions.', 'pricetunex' ) ) );
            }

            // FIXED: Better error handling for form data
            if ( empty( $_POST ) ) {
                error_log( 'PriceTuneX: No POST data received in ajax_apply_rules' );
                wp_send_json_error( array( 'message' => esc_html__( 'No form data received.', 'pricetunex' ) ) );
            }

            // Get and sanitize form data
            $rule_data = $this->sanitize_rule_data( $_POST );
            error_log( 'PriceTuneX: Rule data sanitized: ' . print_r( $rule_data, true ) );

            // ADDED: Validate rule data before processing
            $validation_result = $this->validate_rule_data( $rule_data );
            if ( ! $validation_result['valid'] ) {
                error_log( 'PriceTuneX: Rule validation failed: ' . $validation_result['message'] );
                wp_send_json_error( array( 'message' => $validation_result['message'] ) );
            }

            // Initialize price manager
            if ( ! class_exists( 'Pricetunex_Price_Manager' ) ) {
                error_log( 'PriceTuneX: Pricetunex_Price_Manager class not found' );
                wp_send_json_error( array( 'message' => esc_html__( 'Price manager class not found.', 'pricetunex' ) ) );
            }

            $price_manager = new Pricetunex_Price_Manager();

            // Apply the rules
            $result = $price_manager->apply_rules( $rule_data );
            error_log( 'PriceTuneX: Apply rules result: ' . print_r( $result, true ) );

            if ( $result['success'] ) {
                wp_send_json_success( array(
                    'message'          => esc_html__( 'Price rules applied successfully.', 'pricetunex' ),
                    'products_updated' => $result['products_updated'],
                    'changes_made'     => isset( $result['changes_made'] ) ? $result['changes_made'] : array(),
                ) );
            } else {
                wp_send_json_error( array( 'message' => $result['message'] ) );
            }
            
        } catch ( Exception $e ) {
            error_log( 'PriceTuneX: Exception in ajax_apply_rules: ' . $e->getMessage() );
            wp_send_json_error( array( 'message' => esc_html__( 'An error occurred while applying rules.', 'pricetunex' ) ) );
        }
    }

    /**
     * AJAX handler for previewing price rules - ENHANCED WITH ERROR HANDLING
     */
    public function ajax_preview_rules() {
        // ADDED: Error logging for debugging
        error_log( 'PriceTuneX: ajax_preview_rules called' );
        
        try {
            // Verify nonce
            if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'pricetunex_admin_nonce' ) ) {
                error_log( 'PriceTuneX: Nonce verification failed in ajax_preview_rules' );
                wp_send_json_error( array( 'message' => esc_html__( 'Security check failed.', 'pricetunex' ) ) );
            }

            // Check user capabilities
            if ( ! current_user_can( 'manage_woocommerce' ) ) {
                error_log( 'PriceTuneX: User capability check failed in ajax_preview_rules' );
                wp_send_json_error( array( 'message' => esc_html__( 'Insufficient permissions.', 'pricetunex' ) ) );
            }

            // FIXED: Better error handling for form data
            if ( empty( $_POST ) ) {
                error_log( 'PriceTuneX: No POST data received in ajax_preview_rules' );
                wp_send_json_error( array( 'message' => esc_html__( 'No form data received.', 'pricetunex' ) ) );
            }

            // Get and sanitize form data
            $rule_data = $this->sanitize_rule_data( $_POST );
            error_log( 'PriceTuneX: Preview rule data: ' . print_r( $rule_data, true ) );

            // ADDED: Validate rule data before processing
            $validation_result = $this->validate_rule_data( $rule_data );
            if ( ! $validation_result['valid'] ) {
                error_log( 'PriceTuneX: Preview rule validation failed: ' . $validation_result['message'] );
                wp_send_json_error( array( 'message' => $validation_result['message'] ) );
            }

            // Initialize price manager
            if ( ! class_exists( 'Pricetunex_Price_Manager' ) ) {
                error_log( 'PriceTuneX: Pricetunex_Price_Manager class not found' );
                wp_send_json_error( array( 'message' => esc_html__( 'Price manager class not found.', 'pricetunex' ) ) );
            }

            $price_manager = new Pricetunex_Price_Manager();

            // Preview the rules
            $preview = $price_manager->preview_rules( $rule_data );
            error_log( 'PriceTuneX: Preview result: ' . print_r( $preview, true ) );

            if ( $preview['success'] ) {
                wp_send_json_success( array(
                    'message'           => esc_html__( 'Preview generated successfully.', 'pricetunex' ),
                    'products_affected' => $preview['products_affected'],
                    'preview_data'      => $preview['preview_data'],
                ) );
            } else {
                wp_send_json_error( array( 'message' => $preview['message'] ) );
            }
            
        } catch ( Exception $e ) {
            error_log( 'PriceTuneX: Exception in ajax_preview_rules: ' . $e->getMessage() );
            wp_send_json_error( array( 'message' => esc_html__( 'An error occurred while generating preview.', 'pricetunex' ) ) );
        }
    }

    /**
     * ADDED: Validate rule data
     */
    private function validate_rule_data( $rule_data ) {
        // Check if rule value is set and valid
        if ( ! isset( $rule_data['rule_value'] ) || empty( $rule_data['rule_value'] ) ) {
            return array(
                'valid' => false,
                'message' => esc_html__( 'Adjustment value is required.', 'pricetunex' )
            );
        }

        if ( ! is_numeric( $rule_data['rule_value'] ) ) {
            return array(
                'valid' => false,
                'message' => esc_html__( 'Adjustment value must be a number.', 'pricetunex' )
            );
        }

        // Validate percentage range
        if ( isset( $rule_data['rule_type'] ) && 'percentage' === $rule_data['rule_type'] ) {
            $percentage = floatval( $rule_data['rule_value'] );
            if ( $percentage < -100 || $percentage > 1000 ) {
                return array(
                    'valid' => false,
                    'message' => esc_html__( 'Percentage must be between -100% and 1000%.', 'pricetunex' )
                );
            }
        }

        return array(
            'valid' => true,
            'message' => ''
        );
    }

    /**
     * AJAX handler for undoing last changes
     */
    public function ajax_undo_changes() {
        // Verify nonce
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'pricetunex_admin_nonce' ) ) {
            wp_send_json_error( array( 'message' => esc_html__( 'Security check failed.', 'pricetunex' ) ) );
        }

        // Check user capabilities
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => esc_html__( 'Insufficient permissions.', 'pricetunex' ) ) );
        }

        try {
            // Initialize price manager
            $price_manager = new Pricetunex_Price_Manager();

            // Undo last changes
            $result = $price_manager->undo_last_changes();

            if ( $result['success'] ) {
                wp_send_json_success( array(
                    'message'          => esc_html__( 'Last changes undone successfully.', 'pricetunex' ),
                    'products_restored' => $result['products_restored'],
                ) );
            } else {
                wp_send_json_error( array( 'message' => $result['message'] ) );
            }
        } catch ( Exception $e ) {
            wp_send_json_error( array( 'message' => esc_html__( 'An error occurred while undoing changes.', 'pricetunex' ) ) );
        }
    }

    /**
     * AJAX handler for getting logs
     */
    public function ajax_get_logs() {
        // Verify nonce
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'pricetunex_admin_nonce' ) ) {
            wp_send_json_error( array( 'message' => esc_html__( 'Security check failed.', 'pricetunex' ) ) );
        }

        // Check user capabilities
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => esc_html__( 'Insufficient permissions.', 'pricetunex' ) ) );
        }

        try {
            // Get logs
            $logs = $this->get_activity_logs();

            wp_send_json_success( array(
                'logs' => $logs,
            ) );
        } catch ( Exception $e ) {
            wp_send_json_error( array( 'message' => esc_html__( 'An error occurred while retrieving logs.', 'pricetunex' ) ) );
        }
    }

    /**
     * FIXED: Enhanced sanitize rule data with better error handling
     */
    private function sanitize_rule_data( $data ) {
        $sanitized = array();

        // Rule type - with validation
        $sanitized['rule_type'] = isset( $data['rule_type'] ) ? sanitize_text_field( wp_unslash( $data['rule_type'] ) ) : 'percentage';
        if ( ! in_array( $sanitized['rule_type'], array( 'percentage', 'fixed' ), true ) ) {
            $sanitized['rule_type'] = 'percentage';
        }

        // Rule value - ensure it's numeric
        $rule_value = isset( $data['rule_value'] ) ? $data['rule_value'] : 0;
        if ( is_string( $rule_value ) ) {
            $rule_value = wp_unslash( $rule_value );
        }
        $sanitized['rule_value'] = is_numeric( $rule_value ) ? floatval( $rule_value ) : 0;

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
        $price_min = isset( $data['price_min'] ) ? $data['price_min'] : 0;
        $price_max = isset( $data['price_max'] ) ? $data['price_max'] : 0;
        
        if ( is_string( $price_min ) ) {
            $price_min = wp_unslash( $price_min );
        }
        if ( is_string( $price_max ) ) {
            $price_max = wp_unslash( $price_max );
        }
        
        $sanitized['price_min'] = is_numeric( $price_min ) ? floatval( $price_min ) : 0;
        $sanitized['price_max'] = is_numeric( $price_max ) ? floatval( $price_max ) : 0;

        // Rounding options
        $sanitized['apply_rounding'] = isset( $data['apply_rounding'] ) ? (bool) $data['apply_rounding'] : false;
        $sanitized['rounding_type'] = isset( $data['rounding_type'] ) ? sanitize_text_field( wp_unslash( $data['rounding_type'] ) ) : '0.99';

        return $sanitized;
    }

    /**
     * Get activity logs
     *
     * @return array
     */
    private function get_activity_logs() {
        $logs = get_option( 'pricetunex_activity_logs', array() );
        
        // Sort by timestamp (newest first)
        if ( ! empty( $logs ) && is_array( $logs ) ) {
            usort( $logs, function( $a, $b ) {
                $timestamp_a = isset( $a['timestamp'] ) ? $a['timestamp'] : 0;
                $timestamp_b = isset( $b['timestamp'] ) ? $b['timestamp'] : 0;
                return $timestamp_b - $timestamp_a;
            });
        }

        // Limit to recent entries
        $settings = get_option( 'pricetunex_settings', array() );
        $max_entries = isset( $settings['max_log_entries'] ) ? (int) $settings['max_log_entries'] : 1000;
        
        return array_slice( $logs, 0, $max_entries );
    }

    /**
     * Display admin notices
     */
    public function display_admin_notices() {
        $notice = get_transient( 'pricetunex_admin_notice' );
        
        if ( $notice && is_array( $notice ) ) {
            $type = isset( $notice['type'] ) ? sanitize_text_field( $notice['type'] ) : 'info';
            $message = isset( $notice['message'] ) ? wp_kses_post( $notice['message'] ) : '';
            
            if ( $message ) {
                printf(
                    '<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
                    esc_attr( $type ),
                    $message
                );
            }
            
            delete_transient( 'pricetunex_admin_notice' );
        }
    }

    /**
     * Get product categories for dropdown
     *
     * @return array
     */
    public function get_product_categories() {
        $categories = get_terms( array(
            'taxonomy'   => 'product_cat',
            'hide_empty' => false,
        ) );

        $options = array();
        if ( ! is_wp_error( $categories ) ) {
            foreach ( $categories as $category ) {
                $options[ $category->term_id ] = $category->name;
            }
        }

        return $options;
    }

    /**
     * Get product tags for dropdown
     *
     * @return array
     */
    public function get_product_tags() {
        $tags = get_terms( array(
            'taxonomy'   => 'product_tag',
            'hide_empty' => false,
        ) );

        $options = array();
        if ( ! is_wp_error( $tags ) ) {
            foreach ( $tags as $tag ) {
                $options[ $tag->term_id ] = $tag->name;
            }
        }

        return $options;
    }

    /**
     * Get available product types
     *
     * @return array
     */
    public function get_product_types() {
        return array(
            'simple'   => esc_html__( 'Simple', 'pricetunex' ),
            'variable' => esc_html__( 'Variable', 'pricetunex' ),
        );
    }
    
    /**
     * AJAX handler for getting statistics
     */
    public function ajax_get_stats() {
        // Verify nonce
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'pricetunex_admin_nonce' ) ) {
            wp_send_json_error( array( 'message' => esc_html__( 'Security check failed.', 'pricetunex' ) ) );
        }

        // Check user capabilities
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => esc_html__( 'Insufficient permissions.', 'pricetunex' ) ) );
        }

        try {
            // Get product statistics
            $product_query = new Pricetunex_Product_Query();
            $stats = $product_query->get_product_statistics();

            // Get total eligible products
            $total_products = $product_query->get_total_products_count();

            // Get last update time
            $last_update = get_option( 'pricetunex_last_update', 0 );
            $last_update_formatted = $last_update ? date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $last_update ) : esc_html__( 'Never', 'pricetunex' );

            wp_send_json_success( array(
                'total_products' => $total_products,
                'last_update'    => $last_update_formatted,
                'stats'          => $stats,
            ) );

        } catch ( Exception $e ) {
            wp_send_json_error( array( 'message' => esc_html__( 'An error occurred while retrieving statistics.', 'pricetunex' ) ) );
        }
    }

    /**
     * AJAX handler for clearing logs
     */
    public function ajax_clear_logs() {
        // Verify nonce
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'pricetunex_admin_nonce' ) ) {
            wp_send_json_error( array( 'message' => esc_html__( 'Security check failed.', 'pricetunex' ) ) );
        }

        // Check user capabilities
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => esc_html__( 'Insufficient permissions.', 'pricetunex' ) ) );
        }

        try {
            // Clear activity logs
            delete_option( 'pricetunex_activity_logs' );

            wp_send_json_success( array(
                'message' => esc_html__( 'Activity logs cleared successfully.', 'pricetunex' ),
            ) );

        } catch ( Exception $e ) {
            wp_send_json_error( array( 'message' => esc_html__( 'An error occurred while clearing logs.', 'pricetunex' ) ) );
        }
    }

    /**
     * AJAX handler for product search
     */
    public function ajax_search_products() {
        // Verify nonce
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'pricetunex_admin_nonce' ) ) {
            wp_send_json_error( array( 'message' => esc_html__( 'Security check failed.', 'pricetunex' ) ) );
        }

        // Check user capabilities
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => esc_html__( 'Insufficient permissions.', 'pricetunex' ) ) );
        }

        $search_term = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';

        if ( empty( $search_term ) ) {
            wp_send_json_error( array( 'message' => esc_html__( 'Search term is required.', 'pricetunex' ) ) );
        }

        try {
            $product_query = new Pricetunex_Product_Query();
            $products = $product_query->search_products( $search_term, 20 );

            $formatted_products = array();
            foreach ( $products as $product_data ) {
                $product = $product_data['product'];
                $formatted_products[] = array(
                    'id'    => $product->get_id(),
                    'name'  => $product->get_name(),
                    'sku'   => $product->get_sku(),
                    'price' => $product->get_regular_price(),
                    'type'  => $product->get_type(),
                );
            }

            wp_send_json_success( array(
                'products' => $formatted_products,
            ) );

        } catch ( Exception $e ) {
            wp_send_json_error( array( 'message' => esc_html__( 'An error occurred while searching products.', 'pricetunex' ) ) );
        }
    }    
}