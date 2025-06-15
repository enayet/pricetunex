<?php
/**
 * Admin functionality for PriceTuneX - SIMPLIFIED VERSION
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

        // SIMPLIFIED: Handle AJAX requests
        add_action( 'wp_ajax_pricetunex_apply_rules', array( $this, 'ajax_apply_rules' ) );
        add_action( 'wp_ajax_pricetunex_preview_rules', array( $this, 'ajax_preview_rules' ) );
        add_action( 'wp_ajax_pricetunex_undo_changes', array( $this, 'ajax_undo_changes' ) );
        add_action( 'wp_ajax_pricetunex_get_logs', array( $this, 'ajax_get_logs' ) );
        add_action( 'wp_ajax_pricetunex_clear_logs', array( $this, 'ajax_clear_logs' ) );

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
            array( 'jquery' ),
            PRICETUNEX_VERSION,
            true
        );

        // SIMPLIFIED: Localization
        wp_localize_script(
            'pricetunex-admin',
            'pricetunex_ajax',
            array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'pricetunex_admin_nonce' ),
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
     * SIMPLIFIED: AJAX handler for applying price rules
     */
    public function ajax_apply_rules() {
        // Basic security checks
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'pricetunex_admin_nonce' ) ) {
            wp_send_json_error( 'Security check failed.' );
        }

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( 'Insufficient permissions.' );
        }

        // Get and sanitize form data
        $rule_data = $this->sanitize_rule_data( $_POST );

        // Basic validation
        if ( empty( $rule_data['rule_value'] ) || ! is_numeric( $rule_data['rule_value'] ) ) {
            wp_send_json_error( 'Invalid rule value.' );
        }

        try {
            // Initialize price manager
            $price_manager = new Pricetunex_Price_Manager();
            
            // Apply the rules
            $result = $price_manager->apply_rules( $rule_data );

            if ( $result['success'] ) {
                wp_send_json_success( array(
                    'message'          => 'Price rules applied successfully.',
                    'products_updated' => $result['products_updated'],
                ) );
            } else {
                wp_send_json_error( $result['message'] );
            }
            
        } catch ( Exception $e ) {
            wp_send_json_error( 'An error occurred while applying rules.' );
        }
    }

    /**
     * SIMPLIFIED: AJAX handler for previewing price rules
     */
    public function ajax_preview_rules() {
        // Basic security checks
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'pricetunex_admin_nonce' ) ) {
            wp_send_json_error( 'Security check failed.' );
        }

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( 'Insufficient permissions.' );
        }

        // Get and sanitize form data
        $rule_data = $this->sanitize_rule_data( $_POST );

        // Basic validation
        if ( empty( $rule_data['rule_value'] ) || ! is_numeric( $rule_data['rule_value'] ) ) {
            wp_send_json_error( 'Invalid rule value.' );
        }

        // DEBUG: Log the rule data to see what we're getting
        error_log( 'PriceTuneX Preview Rule Data: ' . print_r( $rule_data, true ) );

        try {
            // Initialize price manager
            $price_manager = new Pricetunex_Price_Manager();
            
            // Preview the rules
            $preview = $price_manager->preview_rules( $rule_data );

            if ( $preview['success'] ) {
                wp_send_json_success( array(
                    'message'           => 'Preview generated successfully.',
                    'products_affected' => $preview['products_affected'],
                    'preview_data'      => $preview['preview_data'],
                ) );
            } else {
                wp_send_json_error( $preview['message'] );
            }
            
        } catch ( Exception $e ) {
            error_log( 'PriceTuneX Preview Error: ' . $e->getMessage() );
            wp_send_json_error( 'An error occurred while generating preview.' );
        }
    }

    /**
     * SIMPLIFIED: AJAX handler for undoing last changes
     */
    public function ajax_undo_changes() {
        // Basic security checks
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'pricetunex_admin_nonce' ) ) {
            wp_send_json_error( 'Security check failed.' );
        }

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( 'Insufficient permissions.' );
        }

        try {
            // Initialize price manager
            $price_manager = new Pricetunex_Price_Manager();

            // Undo last changes
            $result = $price_manager->undo_last_changes();

            if ( $result['success'] ) {
                wp_send_json_success( array(
                    'message'           => 'Last changes undone successfully.',
                    'products_restored' => $result['products_restored'],
                ) );
            } else {
                wp_send_json_error( $result['message'] );
            }
        } catch ( Exception $e ) {
            wp_send_json_error( 'An error occurred while undoing changes.' );
        }
    }

    /**
     * SIMPLIFIED: AJAX handler for getting logs
     */
    public function ajax_get_logs() {
        // Basic security checks
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'pricetunex_admin_nonce' ) ) {
            wp_send_json_error( 'Security check failed.' );
        }

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( 'Insufficient permissions.' );
        }

        // Get logs
        $logs = get_option( 'pricetunex_activity_logs', array() );
        
        // Limit to recent entries
        $logs = array_slice( $logs, 0, 50 );

        wp_send_json_success( array(
            'logs' => $logs,
        ) );
    }

    /**
     * SIMPLIFIED: AJAX handler for clearing logs
     */
    public function ajax_clear_logs() {
        // Basic security checks
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ), 'pricetunex_admin_nonce' ) ) {
            wp_send_json_error( 'Security check failed.' );
        }

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( 'Insufficient permissions.' );
        }

        // Clear activity logs
        delete_option( 'pricetunex_activity_logs' );

        wp_send_json_success( array(
            'message' => 'Activity logs cleared successfully.',
        ) );
    }

    /**
     * SIMPLIFIED: Sanitize rule data
     */
    private function sanitize_rule_data( $data ) {
        $sanitized = array();

        // Basic fields
        $sanitized['rule_type'] = sanitize_text_field( wp_unslash( $data['rule_type'] ?? 'percentage' ) );
        $sanitized['rule_value'] = floatval( $data['rule_value'] ?? 0 );
        $sanitized['target_scope'] = sanitize_text_field( wp_unslash( $data['target_scope'] ?? 'all' ) );

        // Categories - FIXED: Handle array properly
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
        $sanitized['price_min'] = floatval( $data['price_min'] ?? 0 );
        $sanitized['price_max'] = floatval( $data['price_max'] ?? 0 );

        // Rounding options
        $sanitized['apply_rounding'] = isset( $data['apply_rounding'] ) ? (bool) $data['apply_rounding'] : false;
        $sanitized['rounding_type'] = sanitize_text_field( wp_unslash( $data['rounding_type'] ?? '0.99' ) );

        return $sanitized;
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
     */
    public function get_product_types() {
        return array(
            'simple'   => esc_html__( 'Simple', 'pricetunex' ),
            'variable' => esc_html__( 'Variable', 'pricetunex' ),
        );
    }
}