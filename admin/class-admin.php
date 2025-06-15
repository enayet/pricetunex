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

        // Handle AJAX requests
        add_action( 'wp_ajax_pricetunex_apply_rules', array( $this, 'ajax_apply_rules' ) );
        add_action( 'wp_ajax_pricetunex_preview_rules', array( $this, 'ajax_preview_rules' ) );
        add_action( 'wp_ajax_pricetunex_undo_changes', array( $this, 'ajax_undo_changes' ) );
        add_action( 'wp_ajax_pricetunex_get_logs', array( $this, 'ajax_get_logs' ) );

        // NEW AJAX HANDLERS
        add_action( 'wp_ajax_pricetunex_get_stats', array( $this, 'ajax_get_stats' ) );
        add_action( 'wp_ajax_pricetunex_clear_logs', array( $this, 'ajax_clear_logs' ) );
        add_action( 'wp_ajax_pricetunex_search_products', array( $this, 'ajax_search_products' ) );

        // Handle form submissions
        add_action( 'admin_post_pricetunex_save_settings', array( $this, 'handle_save_settings' ) );

        // Add admin notices
        add_action( 'admin_notices', array( $this, 'display_admin_notices' ) );
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

        // Localize script for AJAX
        wp_localize_script(
            'pricetunex-admin',
            'pricetunex_ajax',
            array(
                'ajax_url'    => admin_url( 'admin-ajax.php' ),
                'nonce'       => wp_create_nonce( 'pricetunex_admin_nonce' ),
                'strings'     => array(
                    'confirm_apply'    => esc_html__( 'Are you sure you want to apply these price changes? This action cannot be undone without using the undo feature.', 'pricetunex' ),
                    'confirm_undo'     => esc_html__( 'Are you sure you want to undo the last price changes?', 'pricetunex' ),
                    'processing'       => esc_html__( 'Processing...', 'pricetunex' ),
                    'error'           => esc_html__( 'An error occurred. Please try again.', 'pricetunex' ),
                    'success'         => esc_html__( 'Operation completed successfully.', 'pricetunex' ),
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
     * AJAX handler for applying price rules
     */
    public function ajax_apply_rules() {
        // Verify nonce
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'pricetunex_admin_nonce' ) ) {
            wp_send_json_error( array( 'message' => esc_html__( 'Security check failed.', 'pricetunex' ) ) );
        }

        // Check user capabilities
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => esc_html__( 'Insufficient permissions.', 'pricetunex' ) ) );
        }

        // Get and sanitize form data
        $rule_data = $this->sanitize_rule_data( $_POST );

        try {
            // Initialize price manager
            $price_manager = new Pricetunex_Price_Manager();

            // Apply the rules
            $result = $price_manager->apply_rules( $rule_data );

            if ( $result['success'] ) {
                wp_send_json_success( array(
                    'message'          => esc_html__( 'Price rules applied successfully.', 'pricetunex' ),
                    'products_updated' => $result['products_updated'],
                    'changes_made'     => $result['changes_made'],
                ) );
            } else {
                wp_send_json_error( array( 'message' => $result['message'] ) );
            }
        } catch ( Exception $e ) {
            wp_send_json_error( array( 'message' => esc_html__( 'An error occurred while applying rules.', 'pricetunex' ) ) );
        }
    }

    /**
     * AJAX handler for previewing price rules
     */
    public function ajax_preview_rules() {
        // Verify nonce
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'pricetunex_admin_nonce' ) ) {
            wp_send_json_error( array( 'message' => esc_html__( 'Security check failed.', 'pricetunex' ) ) );
        }

        // Check user capabilities
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => esc_html__( 'Insufficient permissions.', 'pricetunex' ) ) );
        }

        // Get and sanitize form data
        $rule_data = $this->sanitize_rule_data( $_POST );

        try {
            // Initialize price manager
            $price_manager = new Pricetunex_Price_Manager();

            // Preview the rules
            $preview = $price_manager->preview_rules( $rule_data );

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
            wp_send_json_error( array( 'message' => esc_html__( 'An error occurred while generating preview.', 'pricetunex' ) ) );
        }
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
     * Sanitize rule data from form submission
     *
     * @param array $data Raw form data.
     * @return array Sanitized data.
     */
    private function sanitize_rule_data( $data ) {
        $sanitized = array();

        // Rule type
        $sanitized['rule_type'] = isset( $data['rule_type'] ) ? sanitize_text_field( wp_unslash( $data['rule_type'] ) ) : 'percentage';

        // Rule value
        $sanitized['rule_value'] = isset( $data['rule_value'] ) ? floatval( $data['rule_value'] ) : 0;

        // Target scope
        $sanitized['target_scope'] = isset( $data['target_scope'] ) ? sanitize_text_field( wp_unslash( $data['target_scope'] ) ) : 'all';

        // Categories
        $sanitized['categories'] = isset( $data['categories'] ) && is_array( $data['categories'] ) 
            ? array_map( 'absint', $data['categories'] ) 
            : array();

        // Tags
        $sanitized['tags'] = isset( $data['tags'] ) && is_array( $data['tags'] ) 
            ? array_map( 'absint', $data['tags'] ) 
            : array();

        // Product types
        $sanitized['product_types'] = isset( $data['product_types'] ) && is_array( $data['product_types'] ) 
            ? array_map( 'sanitize_text_field', array_map( 'wp_unslash', $data['product_types'] ) )
            : array();

        // Price range
        $sanitized['price_min'] = isset( $data['price_min'] ) ? floatval( $data['price_min'] ) : 0;
        $sanitized['price_max'] = isset( $data['price_max'] ) ? floatval( $data['price_max'] ) : 0;

        // Rounding options
        $sanitized['apply_rounding'] = isset( $data['apply_rounding'] ) ? true : false;
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
        usort( $logs, function( $a, $b ) {
            return $b['timestamp'] - $a['timestamp'];
        });

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
            'grouped'  => esc_html__( 'Grouped', 'pricetunex' ),
            'external' => esc_html__( 'External/Affiliate', 'pricetunex' ),
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
            pricetunex_clear_activity_logs();

            wp_send_json_success( array(
                'message' => esc_html__( 'Activity logs cleared successfully.', 'pricetunex' ),
            ) );

        } catch ( Exception $e ) {
            wp_send_json_error( array( 'message' => esc_html__( 'An error occurred while clearing logs.', 'pricetunex' ) ) );
        }
    }

    /**
     * AJAX handler for product search (for future enhancement)
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