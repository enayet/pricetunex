<?php
/**
 * Plugin Name: PriceTuneX – WooCommerce Smart Price Manager
 * Description: Bulk price editor with smart rounding and psychological pricing tools for WooCommerce.
 * Version: 1.0.0
 * Author: TheWebLab
 * Author URI: https://theweblab.xyz
 * Text Domain: pricetunex
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.5
 * Requires PHP: 7.4
 * WC requires at least: 6.0
 * WC tested up to: 8.8
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * 
 * @package PriceTuneX
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants
define( 'PRICETUNEX_VERSION', '1.0.1' );
define( 'PRICETUNEX_PLUGIN_FILE', __FILE__ );
define( 'PRICETUNEX_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'PRICETUNEX_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'PRICETUNEX_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'PRICETUNEX_TEXT_DOMAIN', 'pricetunex' );

/**
 * Main PriceTuneX Class
 *
 * @class Pricetunex
 */
final class Pricetunex {

    /**
     * Plugin instance
     *
     * @var Pricetunex
     */
    private static $instance = null;

    /**
     * Get plugin instance
     *
     * @return Pricetunex
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Activation and deactivation hooks
        register_activation_hook( PRICETUNEX_PLUGIN_FILE, array( $this, 'activate' ) );
        register_deactivation_hook( PRICETUNEX_PLUGIN_FILE, array( $this, 'deactivate' ) );

        // Initialize plugin after all plugins are loaded
        add_action( 'plugins_loaded', array( $this, 'init' ) );

        // Add settings link on plugin page
        add_filter( 'plugin_action_links_' . PRICETUNEX_PLUGIN_BASENAME, array( $this, 'add_action_links' ) );

        // Load text domain
        add_action( 'init', array( $this, 'load_textdomain' ) );
    }

    /**
     * Initialize the plugin
     */
    public function init() {
        // Check if WooCommerce is active
        if ( ! $this->is_woocommerce_active() ) {
            add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
            return;
        }
        
        // Check WooCommerce HPOS compatibility
        if ( ! $this->check_hpos_compatibility() ) {
            return;
        }        
        
        // Check minimum requirements
        if ( ! $this->check_requirements() ) {
            return;
        }

        // Include required files
        $this->includes();
        
        // Declare WooCommerce HPOS compatibility
        $this->declare_wc_compatibility();        

        // Initialize classes
        $this->init_classes();

        // Plugin fully loaded action
        do_action( 'pricetunex_loaded' );
    }

    /**
     * Include required files
     */
    private function includes() {
        // Helper functions
        require_once PRICETUNEX_PLUGIN_PATH . 'includes/functions.php';

        // Core classes
        require_once PRICETUNEX_PLUGIN_PATH . 'includes/class-product-query.php';
        require_once PRICETUNEX_PLUGIN_PATH . 'includes/class-price-manager.php';

        // Admin classes
        if ( is_admin() ) {
            require_once PRICETUNEX_PLUGIN_PATH . 'admin/class-admin.php';
        }
    }

    /**
     * Initialize classes
     */
    private function init_classes() {
        // Initialize admin interface
        if ( is_admin() ) {
            new Pricetunex_Admin();
        }
    }

    /**
     * Check if WooCommerce is active
     *
     * @return bool
     */
    private function is_woocommerce_active() {
        return class_exists( 'WooCommerce' );
    }

    /**
     * Check minimum requirements
     *
     * @return bool
     */
    private function check_requirements() {
        // Check PHP version
        if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
            add_action( 'admin_notices', array( $this, 'php_version_notice' ) );
            return false;
        }

        // Check WordPress version
        global $wp_version;
        if ( version_compare( $wp_version, '5.0', '<' ) ) {
            add_action( 'admin_notices', array( $this, 'wp_version_notice' ) );
            return false;
        }

        // Check WooCommerce version
        if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '6.0', '<' ) ) {
            add_action( 'admin_notices', array( $this, 'wc_version_notice' ) );
            return false;
        }

        return true;
    }

    /**
     * Plugin activation
     */
    public function activate() {
        // Check requirements on activation
        if ( ! $this->is_woocommerce_active() ) {
            deactivate_plugins( PRICETUNEX_PLUGIN_BASENAME );
            wp_die(
                esc_html__( 'PriceTuneX requires WooCommerce to be installed and active.', 'pricetunex' ),
                esc_html__( 'Plugin Activation Error', 'pricetunex' ),
                array( 'back_link' => true )
            );
        }

        // Create necessary database tables or options
        $this->create_options();

        // Set activation flag
        update_option( 'pricetunex_activated', true );

        // Clear any cached data
        if ( function_exists( 'wp_cache_flush' ) ) {
            wp_cache_flush();
        }

        // Schedule any necessary events
        $this->schedule_events();
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clear scheduled events
        $this->clear_scheduled_events();

        // Clear any cached data
        if ( function_exists( 'wp_cache_flush' ) ) {
            wp_cache_flush();
        }

        // Set deactivation flag
        delete_option( 'pricetunex_activated' );
    }

    /**
     * Create plugin options
     */
    private function create_options() {
        // Default plugin settings
        $default_settings = array(
            'version'           => PRICETUNEX_VERSION,
            'enable_logging'    => true,
            'max_log_entries'   => 1000,
            'backup_prices'     => true,
            'default_rounding'  => '0.99',
        );

        add_option( 'pricetunex_settings', $default_settings );

        // Create logs directory if it doesn't exist
        $logs_dir = PRICETUNEX_PLUGIN_PATH . 'logs';
        if ( ! file_exists( $logs_dir ) ) {
            wp_mkdir_p( $logs_dir );
            
            // Create .htaccess to protect logs directory
            $htaccess_content = "deny from all\n";
            file_put_contents( $logs_dir . '/.htaccess', $htaccess_content );
        }
    }

    /**
     * Schedule events
     */
    private function schedule_events() {
        // Schedule log cleanup event
        if ( ! wp_next_scheduled( 'pricetunex_cleanup_logs' ) ) {
            wp_schedule_event( time(), 'weekly', 'pricetunex_cleanup_logs' );
        }
    }

    /**
     * Clear scheduled events
     */
    private function clear_scheduled_events() {
        wp_clear_scheduled_hook( 'pricetunex_cleanup_logs' );
    }

    /**
     * Load text domain for translations
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'pricetunex',
            false,
            dirname( PRICETUNEX_PLUGIN_BASENAME ) . '/languages/'
        );
    }

    /**
     * Add action links to plugin page
     *
     * @param array $links Existing links.
     * @return array Modified links.
     */
    public function add_action_links( $links ) {
        $action_links = array(
            'settings' => '<a href="' . esc_url( admin_url( 'admin.php?page=pricetunex-settings' ) ) . '">' . esc_html__( 'Settings', 'pricetunex' ) . '</a>',
        );

        return array_merge( $action_links, $links );
    }

    /**
     * WooCommerce missing notice
     */
    public function woocommerce_missing_notice() {
        /* translators: %1$s: Plugin name, %2$s: WooCommerce link */
        $message = sprintf(
            esc_html__( '%1$s requires %2$s to be installed and active.', 'pricetunex' ),
            '<strong>' . esc_html__( 'PriceTuneX', 'pricetunex' ) . '</strong>',
            '<a href="https://wordpress.org/plugins/woocommerce/" target="_blank">' . esc_html__( 'WooCommerce', 'pricetunex' ) . '</a>'
        );

        printf(
            '<div class="notice notice-error"><p>%s</p></div>',
            wp_kses_post( $message )
        );
    }

    /**
     * PHP version notice
     */
    public function php_version_notice() {
        /* translators: %1$s: Plugin name, %2$s: Required PHP version, %3$s: Current PHP version */
        $message = sprintf(
            esc_html__( '%1$s requires PHP version %2$s or higher. You are running version %3$s.', 'pricetunex' ),
            '<strong>' . esc_html__( 'PriceTuneX', 'pricetunex' ) . '</strong>',
            '<strong>7.4</strong>',
            '<strong>' . esc_html( PHP_VERSION ) . '</strong>'
        );

        printf(
            '<div class="notice notice-error"><p>%s</p></div>',
            wp_kses_post( $message )
        );
    }

    /**
     * WordPress version notice
     */
    public function wp_version_notice() {
        global $wp_version;

        /* translators: %1$s: Plugin name, %2$s: Required WordPress version, %3$s: Current WordPress version */
        $message = sprintf(
            esc_html__( '%1$s requires WordPress version %2$s or higher. You are running version %3$s.', 'pricetunex' ),
            '<strong>' . esc_html__( 'PriceTuneX', 'pricetunex' ) . '</strong>',
            '<strong>5.0</strong>',
            '<strong>' . esc_html( $wp_version ) . '</strong>'
        );

        printf(
            '<div class="notice notice-error"><p>%s</p></div>',
            wp_kses_post( $message )
        );
    }

    /**
     * WooCommerce version notice
     */
    public function wc_version_notice() {
        /* translators: %1$s: Plugin name, %2$s: Required WooCommerce version, %3$s: Current WooCommerce version */
        $message = sprintf(
            esc_html__( '%1$s requires WooCommerce version %2$s or higher. You are running version %3$s.', 'pricetunex' ),
            '<strong>' . esc_html__( 'PriceTuneX', 'pricetunex' ) . '</strong>',
            '<strong>6.0</strong>',
            '<strong>' . esc_html( WC_VERSION ) . '</strong>'
        );

        printf(
            '<div class="notice notice-error"><p>%s</p></div>',
            wp_kses_post( $message )
        );
    }
    
    
    /**
     * Check HPOS compatibility
     *
     * @return bool
     */
    private function check_hpos_compatibility() {
        // Check if WooCommerce is using HPOS
        if ( class_exists( \Automattic\WooCommerce\Utilities\OrderUtil::class ) ) {
            // If HPOS is enabled, ensure our plugin is compatible
            if ( \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ) {
                // Our plugin is compatible with HPOS since we only work with products, not orders
                return true;
            }
        }
        
        // If HPOS is not available or not enabled, that's fine too
        return true;
    }

    /**
     * Declare WooCommerce compatibility
     */
    private function declare_wc_compatibility() {
        // Declare HPOS compatibility
        add_action( 'before_woocommerce_init', function() {
            if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
                \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
            }
        });

        // Declare other WooCommerce features compatibility
        add_action( 'before_woocommerce_init', function() {
            if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
                // Declare compatibility with Cart and Checkout blocks
                \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
            }
        });
    }    
    

    /**
     * Get plugin version
     *
     * @return string
     */
    public function get_version() {
        return PRICETUNEX_VERSION;
    }

    /**
     * Get plugin path
     *
     * @return string
     */
    public function plugin_path() {
        return untrailingslashit( PRICETUNEX_PLUGIN_PATH );
    }

    /**
     * Get plugin URL
     *
     * @return string
     */
    public function plugin_url() {
        return untrailingslashit( PRICETUNEX_PLUGIN_URL );
    }
    
}

/**
 * Initialize the plugin
 *
 * @return Pricetunex
 */
function pricetunex() {
    return Pricetunex::get_instance();
}

// Initialize the plugin
pricetunex();

/**
 * Cleanup logs on scheduled event
 */
add_action( 'pricetunex_cleanup_logs', 'pricetunex_cleanup_old_logs' );

/**
 * Cleanup old log entries
 */
function pricetunex_cleanup_old_logs() {
    $settings = get_option( 'pricetunex_settings', array() );
    $max_entries = isset( $settings['max_log_entries'] ) ? (int) $settings['max_log_entries'] : 1000;
    
    // This will be implemented in the logging functionality
    do_action( 'pricetunex_cleanup_logs_action', $max_entries );
}

/**
 * Global helper function to get plugin settings
 *
 * @param string $key     Setting key.
 * @param mixed  $default Default value.
 * @return mixed
 */
//function pricetunex_get_setting( $key, $default = null ) {
//    $settings = get_option( 'pricetunex_settings', array() );
//    return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
//}

/**
 * Global helper function to update plugin settings
 *
 * @param string $key   Setting key.
 * @param mixed  $value Setting value.
 * @return bool
 */
//function pricetunex_update_setting( $key, $value ) {
//    $settings = get_option( 'pricetunex_settings', array() );
//    $settings[ $key ] = $value;
//    return update_option( 'pricetunex_settings', $settings );
//}

/**
 * Check if PriceTuneX is properly loaded
 *
 * @return bool
 */
function pricetunex_is_loaded() {
    return class_exists( 'Pricetunex' ) && function_exists( 'pricetunex' );
}