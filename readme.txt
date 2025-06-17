=== PriceTuneX – WooCommerce Smart Price Manager ===
Contributors: theweblab
Tags: woocommerce, price, bulk, psychological pricing, price management
Requires at least: 5.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
WC requires at least: 6.0
WC tested up to: 8.8
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Transform your WooCommerce pricing strategy with smart bulk updates and psychological pricing that converts.

== Description ==

**PriceTuneX** is the ultimate WooCommerce price management plugin designed for store owners who want to maximize revenue while saving time. Whether you're running seasonal sales, adjusting for inflation, or implementing psychological pricing strategies, PriceTuneX makes it simple, safe, and lightning-fast.

Transform what used to take hours of manual work into a 30-second task. Apply proven pricing psychology to boost conversions. Preview changes before applying them. And always have the safety of a one-click undo feature.

### Core Features

**Lightning-Fast Bulk Updates**
* Update hundreds of products in seconds instead of hours
* Apply percentage increases or fixed amount changes
* Target your entire catalog or specific segments

**Smart Price Targeting**
* Smart Selection: Updates the price customers actually see (sale price if active, otherwise regular price)
* Regular Price Only: Always updates regular prices, keeps sale prices intact
* Sale Price Only: Perfect for flash sales on products with active sale prices
* Both Prices: Updates both regular and sale prices maintaining discount relationships

**Psychological Pricing Magic**
* Automatically round prices to .99, .95, .89 or whole numbers
* Custom endings for brand-specific pricing strategies
* Tap into proven pricing psychology that increases conversions

**Advanced Product Targeting**
* All Products: Apply changes across your entire catalog
* Specific Categories: Target products in selected categories (supports hierarchical structure)
* Product Tags: Perfect for seasonal items or special collections
* Product Types: Filter by simple, variable, or other WooCommerce product types
* Price Range: Target products within specific price ranges

**Variable Product Support**
* Full support for WooCommerce variable products
* Intelligently updates all variations while preserving product structure
* Handles complex product hierarchies automatically

**Safe Preview & Undo System**
* Preview exactly which products will be affected before applying changes
* See before/after prices for the first 10 products
* One-click undo to restore all products to previous prices
* Automatic backup system stores original prices

**Comprehensive Activity Logs**
* Track all price changes with detailed audit trail
* See who made changes and when
* Monitor your pricing strategy performance
* Configurable log retention settings

### Business Value

**Save Hours Every Week**
What used to take hours of tedious manual work now takes 30 seconds. Spend your time growing your business, not updating prices.

**Boost Conversions**
Psychological pricing (.99 endings) can increase conversions by up to 30%. Apply proven pricing psychology across your entire store instantly.

**Risk-Free Updates**
Preview changes, apply safely, and undo instantly if needed. Our backup system ensures you never lose your original pricing data.

### Perfect For

* **Store Owners** running sales, adjusting for inflation, or implementing pricing strategies
* **Marketing Teams** executing promotional campaigns and psychological pricing
* **Agencies** managing multiple WooCommerce stores efficiently
* **Developers** needing reliable bulk price management for clients

### Security & Compatibility

* **WordPress 5.0+** compatibility
* **WooCommerce 6.0+** compatibility with HPOS support
* **PHP 7.4+** requirement for modern performance
* Proper nonce verification and capability checks
* All user inputs sanitized and validated
* Translation ready with .pot file included

### Technical Features

* **HPOS Compatible**: Full support for WooCommerce High-Performance Order Storage
* **Multisite Ready**: Works seamlessly in WordPress multisite environments
* **Developer Friendly**: Extensive hooks and filters for customization
* **Performance Optimized**: Efficient batch processing for large catalogs
* **Memory Safe**: Smart memory management for processing thousands of products

== Installation ==

### Automatic Installation

1. Log in to your WordPress admin panel
2. Navigate to Plugins → Add New
3. Click "Upload Plugin" and select the PriceTuneX zip file
4. Click "Install Now" and then "Activate Plugin"
5. Navigate to WooCommerce → PriceTuneX to start using the plugin

### Manual Installation

1. Upload the `pricetunex` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to WooCommerce → PriceTuneX to configure settings

### Requirements Check

Before installation, ensure your system meets these requirements:
* WordPress 5.0 or higher
* WooCommerce 6.0 or higher
* PHP 7.4 or higher
* At least 128MB PHP memory limit
* User with 'manage_woocommerce' capability

== Frequently Asked Questions ==

= Does PriceTuneX work with variable products? =

Yes! PriceTuneX fully supports WooCommerce variable products. You can update parent products and all their variations simultaneously, or target specific variation types. The plugin intelligently handles complex product structures.

= Can I undo price changes if I make a mistake? =

Absolutely! PriceTuneX automatically backs up your original prices before any changes. You can restore all products to their previous prices with a single click. This safety feature gives you complete peace of mind.

= Will this plugin slow down my website? =

Not at all! PriceTuneX is built for performance. It only loads on the admin side when you're using it, has zero impact on your frontend, and uses efficient WooCommerce APIs. Your site speed won't be affected.

= How many products can I update at once? =

There's no hard limit! PriceTuneX is designed to handle stores of any size. Whether you have 100 products or 10,000+, the plugin efficiently processes updates in batches to ensure reliable performance.

= Do I need coding knowledge to use PriceTuneX? =

Zero coding required! PriceTuneX features an intuitive point-and-click interface. Simply choose your pricing rules, preview the changes, and apply. It's designed for store owners, not developers.

= What's the difference between the price targeting modes? =

* **Smart Selection**: Updates the price customers actually see (sale price if active, otherwise regular price) - recommended for most users
* **Regular Price Only**: Only updates regular prices, leaves sale prices untouched
* **Sale Price Only**: Only updates products that have active sale prices
* **Both Prices**: Updates both regular and sale prices by the same amount

= Does it work with other pricing plugins? =

PriceTuneX works by updating WooCommerce's native price fields, so it's compatible with most other plugins. However, dynamic pricing plugins may override the changes. We recommend testing with your specific setup.

= Can I target specific product categories? =

Yes! You can target products by:
* All products in your store
* Specific categories (supports hierarchical categories)
* Product tags
* Product types (simple, variable, etc.)
* Price ranges (e.g., products between $50-$100)

= What happens to sale prices when I update regular prices? =

This depends on your target price type setting:
* **Smart Selection**: Updates sale prices if they exist, otherwise regular prices
* **Regular Price Only**: Updates only regular prices; if sale price becomes higher than new regular price, it's automatically removed
* **Both Prices**: Updates both regular and sale prices maintaining the same discount amount

= Is there a log of all price changes? =

Yes! PriceTuneX keeps detailed activity logs showing:
* What changes were made and when
* Which user made the changes
* How many products were affected
* Detailed descriptions of the pricing rules applied

You can view, refresh, or clear these logs from the admin interface.

= Can I use psychological pricing with any adjustment type? =

Yes! Psychological pricing (like .99 endings) can be applied with both percentage and fixed amount adjustments. The rounding is applied after the price calculation, so a $47.50 product with +10% and .99 rounding becomes $51.99.

= What if I have a large store with thousands of products? =

PriceTuneX is optimized for large stores:
* Efficient batch processing prevents timeouts
* Memory management handles large datasets
* Progress indicators show update status
* You can target specific segments instead of all products to reduce processing time

= Does it support custom product types? =

PriceTuneX works with WooCommerce's standard product types (simple, variable) out of the box. For custom product types, developers can use the provided filters to extend support.

== Screenshots ==

1. **Main Interface** - Clean, intuitive interface for defining price rules with smart targeting options
2. **Target Price Types** - Choose between smart selection, regular only, sale only, or both prices
3. **Product Targeting** - Target all products, specific categories, tags, product types, or price ranges
4. **Psychological Pricing** - Apply .99, .95, .89 endings or custom rounding for better conversions
5. **Preview Results** - See exactly which products will be affected before applying changes
6. **Activity Logs** - Complete audit trail of all price changes with detailed information
7. **Settings Panel** - Configure logging, backup options, and plugin preferences

== Changelog ==

= 1.0.0 - 2024-12-17 =
* Initial release
* Lightning-fast bulk price updates
* Smart price targeting with 4 different modes
* Psychological pricing with multiple rounding options
* Advanced product targeting (categories, tags, types, price ranges)
* Full variable product support
* Safe preview and one-click undo system
* Comprehensive activity logging
* WooCommerce HPOS compatibility
* Translation ready

== Upgrade Notice ==

= 1.0.0 =
Initial release of PriceTuneX. Transform your WooCommerce pricing strategy with smart bulk updates and psychological pricing that converts.

== Technical Documentation ==

### Hooks and Filters

**Action Hooks:**
* `pricetunex_loaded` - Fired when the plugin is fully loaded
* `pricetunex_before_apply_rules` - Fired before applying price rules
* `pricetunex_after_apply_rules` - Fired after applying price rules
* `pricetunex_after_price_update` - Fired after updating individual product prices

**Filter Hooks:**
* `pricetunex_allowed_product_types` - Filter allowed product types
* `pricetunex_rounding_options` - Filter available rounding options
* `pricetunex_rule_data` - Filter rule data before processing

### API Usage

```php
// Get price manager instance
$price_manager = new Pricetunex_Price_Manager();

// Apply rules
$result = $price_manager->apply_rules( $rule_data );

// Preview changes
$preview = $price_manager->preview_rules( $rule_data );

// Helper functions
$value = pricetunex_get_setting( 'enable_logging', true );
pricetunex_log_activity( 'custom_action', 'Description' );
```

### System Requirements

* **WordPress**: 5.0 or higher
* **WooCommerce**: 6.0 or higher  
* **PHP**: 7.4 or higher (8.1+ recommended)
* **MySQL**: 5.6 or higher (8.0+ recommended)
* **Memory**: 128MB minimum (256MB+ recommended for large stores)
* **Server**: Apache or Nginx with mod_rewrite

### Performance Recommendations

For optimal performance with large product catalogs:
* Increase PHP memory limit to 512MB+
* Set max execution time to 300+ seconds
* Use targeted updates instead of "All Products" when possible
* Enable object caching if available

### Support

For technical support, feature requests, or bug reports, please contact our support team. Include your WordPress version, WooCommerce version, PHP version, and a detailed description of the issue.

**Note**: This plugin requires WooCommerce to be installed and active. It will not work without WooCommerce.

== Privacy Policy ==

PriceTuneX does not collect, store, or transmit any personal data from your website visitors. All plugin data is stored locally in your WordPress database. Activity logs contain only administrative actions related to price changes and can be cleared at any time from the plugin settings.

== Credits ==

Developed by TheWebLab with ❤️ for the WooCommerce community.

Special thanks to the WordPress and WooCommerce teams for creating such excellent platforms.