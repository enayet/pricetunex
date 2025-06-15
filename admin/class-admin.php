<?php
/**
 * Admin functionality for PriceTuneX - COMPLETE FIXED VERSION
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
        add_action( 'wp_ajax_pricetunex_clear_logs', array( $this, 'ajax_clear_logs' ) );
        add_action( 'wp_ajax_pricetunex_get_stats', array( $this, 'ajax_get_stats' ) );

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

        // Localization
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
     * AJAX handler for applying price rules
     */
    public function ajax_apply_rules() {
        // Security checks
        if ( ! $this->verify_ajax_request() ) {
            return;
        }

        // DEBUG: Log all received data
        error_log( 'PriceTuneX Apply Rules - Raw POST data: ' . print_r( $_POST, true ) );

        // Get and sanitize form data
        $rule_data = $this->sanitize_rule_data( $_POST );

        // DEBUG: Log sanitized data
        error_log( 'PriceTuneX Apply Rules - Sanitized data: ' . print_r( $rule_data, true ) );

        // Validate rule data
        $validation = $this->validate_rule_data( $rule_data );
        if ( ! $validation['valid'] ) {
            wp_send_json_error( $validation['message'] );
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
            error_log( 'PriceTuneX Apply Error: ' . $e->getMessage() );
            wp_send_json_error( 'An error occurred while applying rules.' );
        }
    }

    /**
     * AJAX handler for previewing price rules
     */
    public function ajax_preview_rules() {
        // Security checks
        if ( ! $this->verify_ajax_request() ) {
            return;
        }

        // Get and sanitize form data
        $rule_data = $this->sanitize_rule_data( $_POST );

        // Validate rule data
        $validation = $this->validate_rule_data( $rule_data );
        if ( ! $validation['valid'] ) {
            wp_send_json_error( $validation['message'] );
        }

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
     * AJAX handler for undoing last changes
     */
    public function ajax_undo_changes() {
        // Security checks
        if ( ! $this->verify_ajax_request() ) {
            return;
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
            error_log( 'PriceTuneX Undo Error: ' . $e->getMessage() );
            wp_send_json_error( 'An error occurred while undoing changes.' );
        }
    }

    /**
     * AJAX handler for getting logs
     */
    public function ajax_get_logs() {
        // Security checks
        if ( ! $this->verify_ajax_request() ) {
            return;
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
     * AJAX handler for clearing logs
     */
    public function ajax_clear_logs() {
        // Security checks
        if ( ! $this->verify_ajax_request() ) {
            return;
        }

        // Clear activity logs
        delete_option( 'pricetunex_activity_logs' );

        wp_send_json_success( array(
            'message' => 'Activity logs cleared successfully.',
        ) );
    }

    /**
     * AJAX handler for getting statistics
     */
    public function ajax_get_stats() {
        // Security checks
        if ( ! $this->verify_ajax_request() ) {
            return;
        }

        try {
            // Get basic statistics
            $product_query = new Pricetunex_Product_Query();
            $total_products = $product_query->get_total_products_count();
            
            $last_update = get_option( 'pricetunex_last_update', 0 );
            $last_update_formatted = $last_update ? date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $last_update ) : esc_html__( 'Never', 'pricetunex' );

            wp_send_json_success( array(
                'total_products' => $total_products,
                'last_update'    => $last_update_formatted,
            ) );

        } catch ( Exception $e ) {
            error_log( 'PriceTuneX Stats Error: ' . $e->getMessage() );
            wp_send_json_error( 'Failed to load statistics.' );
        }
    }

    /**
     * Verify AJAX request security
     */
    private function verify_ajax_request() {
        // Check nonce
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'pricetunex_admin_nonce' ) ) {
            wp_send_json_error( 'Security check failed.' );
            return false;
        }

        // Check user capabilities
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( 'Insufficient permissions.' );
            return false;
        }

        return true;
    }

    /**
     * Sanitize and validate rule data - FIXED VERSION
     */
    private function sanitize_rule_data( $data ) {
        $sanitized = array();

        // Basic fields
        $sanitized['rule_type'] = isset( $data['rule_type'] ) ? sanitize_text_field( wp_unslash( $data['rule_type'] ) ) : 'percentage';
        $sanitized['rule_value'] = isset( $data['rule_value'] ) ? floatval( $data['rule_value'] ) : 0;
        $sanitized['target_scope'] = isset( $data['target_scope'] ) ? sanitize_text_field( wp_unslash( $data['target_scope'] ) ) : 'all';

        // Categories - FIXED: Properly handle array and convert to integers
        $sanitized['categories'] = array();
        if ( isset( $data['categories'] ) ) {
            if ( is_array( $data['categories'] ) ) {
                // Array of category IDs
                $sanitized['categories'] = array_map( 'absint', $data['categories'] );
                $sanitized['categories'] = array_filter( $sanitized['categories'] ); // Remove zeros
            } elseif ( is_string( $data['categories'] ) && ! empty( $data['categories'] ) ) {
                // Single category ID as string
                $category_id = absint( $data['categories'] );
                if ( $category_id > 0 ) {
                    $sanitized['categories'] = array( $category_id );
                }
            }
        }

        // Tags - FIXED: Same treatment as categories
        $sanitized['tags'] = array();
        if ( isset( $data['tags'] ) ) {
            if ( is_array( $data['tags'] ) ) {
                $sanitized['tags'] = array_map( 'absint', $data['tags'] );
                $sanitized['tags'] = array_filter( $sanitized['tags'] );
            } elseif ( is_string( $data['tags'] ) && ! empty( $data['tags'] ) ) {
                $tag_id = absint( $data['tags'] );
                if ( $tag_id > 0 ) {
                    $sanitized['tags'] = array( $tag_id );
                }
            }
        }

        // Product types
        $sanitized['product_types'] = array();
        if ( isset( $data['product_types'] ) && is_array( $data['product_types'] ) ) {
            $valid_types = array( 'simple', 'variable', 'external', 'grouped' );
            foreach ( $data['product_types'] as $type ) {
                $clean_type = sanitize_text_field( wp_unslash( $type ) );
                if ( in_array( $clean_type, $valid_types, true ) ) {
                    $sanitized['product_types'][] = $clean_type;
                }
            }
        }

        // Price range
        $sanitized['price_min'] = isset( $data['price_min'] ) ? floatval( $data['price_min'] ) : 0;
        $sanitized['price_max'] = isset( $data['price_max'] ) ? floatval( $data['price_max'] ) : 0;

        // Rounding options
        $sanitized['apply_rounding'] = isset( $data['apply_rounding'] ) && ! empty( $data['apply_rounding'] );
        $sanitized['rounding_type'] = isset( $data['rounding_type'] ) ? sanitize_text_field( wp_unslash( $data['rounding_type'] ) ) : '0.99';
        $sanitized['custom_ending'] = isset( $data['custom_ending'] ) ? floatval( $data['custom_ending'] ) : 0;        
        

        // DEBUG: Log the final sanitized data
        error_log( 'PriceTuneX: Final sanitized rule data: ' . print_r( $sanitized, true ) );

        return $sanitized;
    }

    /**
     * Validate rule data
     */
    private function validate_rule_data( $rule_data ) {
        // Check if rule value is set and valid
        if ( ! isset( $rule_data['rule_value'] ) || ! is_numeric( $rule_data['rule_value'] ) || 0 === floatval( $rule_data['rule_value'] ) ) {
            return array(
                'valid'   => false,
                'message' => esc_html__( 'Please enter a valid adjustment value (cannot be zero).', 'pricetunex' ),
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
        $target_scope = $rule_data['target_scope'];
        
        if ( 'categories' === $target_scope ) {
            if ( empty( $rule_data['categories'] ) || ! is_array( $rule_data['categories'] ) ) {
                return array(
                    'valid'   => false,
                    'message' => esc_html__( 'Please select at least one category.', 'pricetunex' ),
                );
            }
        }
        
        if ( 'tags' === $target_scope ) {
            if ( empty( $rule_data['tags'] ) || ! is_array( $rule_data['tags'] ) ) {
                return array(
                    'valid'   => false,
                    'message' => esc_html__( 'Please select at least one tag.', 'pricetunex' ),
                );
            }
        }
        
        if ( 'product_types' === $target_scope ) {
            if ( empty( $rule_data['product_types'] ) || ! is_array( $rule_data['product_types'] ) ) {
                return array(
                    'valid'   => false,
                    'message' => esc_html__( 'Please select at least one product type.', 'pricetunex' ),
                );
            }
        }

        if ( 'price_range' === $target_scope ) {
            if ( 0 === $rule_data['price_min'] && 0 === $rule_data['price_max'] ) {
                return array(
                    'valid'   => false,
                    'message' => esc_html__( 'Please specify a price range (minimum and/or maximum price).', 'pricetunex' ),
                );
            }
            
            if ( $rule_data['price_min'] > 0 && $rule_data['price_max'] > 0 && $rule_data['price_min'] >= $rule_data['price_max'] ) {
                return array(
                    'valid'   => false,
                    'message' => esc_html__( 'Maximum price must be greater than minimum price.', 'pricetunex' ),
                );
            }
        }

        return array(
            'valid'   => true,
            'message' => '',
        );
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
     * Get product categories for dropdown - PROPER HIERARCHY WITH PRESERVED IDS
     */
    public function get_product_categories() {
        $categories = get_terms( array(
            'taxonomy'   => 'product_cat',
            'hide_empty' => false,
            'orderby'    => 'name',
            'order'      => 'ASC',
        ) );

        $options = array();
        if ( ! is_wp_error( $categories ) ) {
            // Build hierarchical structure
            $hierarchy = $this->build_category_hierarchy( $categories );
            $options = $this->flatten_category_hierarchy_fixed( $hierarchy );
        }

        return $options;
    }

    /**
     * FIXED: Flatten category hierarchy but preserve actual term IDs
     * The key issue was using array_merge() which reindexes arrays
     */
    private function flatten_category_hierarchy_fixed( $categories, $depth = 0 ) {
        $options = array();
        
        foreach ( $categories as $category ) {
            // Create indentation for child categories
            $indent = str_repeat( '— ', $depth );
            $display_name = $indent . $category->name . ' (' . $category->count . ')';
            
            // CRITICAL: Use actual term_id as key
            $options[ $category->term_id ] = $display_name;
            
            // Add children recursively
            if ( isset( $category->children ) ) {
                $child_options = $this->flatten_category_hierarchy_fixed( $category->children, $depth + 1 );
                
                // FIXED: Use + operator instead of array_merge to preserve keys
                $options = $options + $child_options;
            }
        }
        
        return $options;
    }



    /**
     * Build category hierarchy from flat terms array
     */
    private function build_category_hierarchy( $categories, $parent_id = 0 ) {
        $branch = array();
        
        foreach ( $categories as $category ) {
            if ( $category->parent == $parent_id ) {
                $children = $this->build_category_hierarchy( $categories, $category->term_id );
                if ( $children ) {
                    $category->children = $children;
                }
                $branch[] = $category;
            }
        }
        
        return $branch;
    }


    /**
     * Get product tags for dropdown - SIMPLE WORKING VERSION  
     */
    public function get_product_tags() {
        $tags = get_terms( array(
            'taxonomy'   => 'product_tag',
            'hide_empty' => false,
            'orderby'    => 'name',
            'order'      => 'ASC',
        ) );

        $options = array();
        if ( ! is_wp_error( $tags ) ) {
            foreach ( $tags as $tag ) {
                $options[ $tag->term_id ] = $tag->name . ' (' . $tag->count . ')';
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