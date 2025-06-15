<?php
/**
 * Product Query - Handle product fetching and filtering based on rules
 *
 * @package PriceTuneX
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Product Query Class
 */
class Pricetunex_Product_Query {

    /**
     * Get products based on targeting rules
     *
     * @param array $rule_data Rule configuration containing target criteria.
     * @return array Array of product data objects.
     */
    public function get_products_by_rules( $rule_data ) {
        try {
            // Build query arguments based on rules
            $query_args = $this->build_query_args( $rule_data );
            
            // Execute the query
            $products = $this->execute_product_query( $query_args );
            
            // Apply additional filters if needed
            $filtered_products = $this->apply_additional_filters( $products, $rule_data );
            
            return $filtered_products;

        } catch ( Exception $e ) {
            error_log( 'PriceTuneX Product Query Error: ' . $e->getMessage() );
            return array();
        }
    }

    /**
     * Build WooCommerce query arguments based on rule data
     *
     * @param array $rule_data Rule configuration.
     * @return array WC_Product_Query arguments.
     */
    private function build_query_args( $rule_data ) {
        $args = array(
            'status'         => 'publish',
            'limit'          => -1, // Get all matching products
            'paginate'       => false,
            'return'         => 'objects',
            'orderby'        => 'ID',
            'order'          => 'ASC',
        );

        // Get target scope
        $target_scope = isset( $rule_data['target_scope'] ) ? $rule_data['target_scope'] : 'all';

        switch ( $target_scope ) {
            case 'categories':
                $args = $this->add_category_filters( $args, $rule_data );
                break;

            case 'tags':
                $args = $this->add_tag_filters( $args, $rule_data );
                break;

            case 'product_types':
                $args = $this->add_product_type_filters( $args, $rule_data );
                break;

            case 'price_range':
                // Price range will be handled in post-query filtering
                // because WooCommerce doesn't have built-in price range query
                break;

            case 'all':
            default:
                // No additional filters needed for all products
                break;
        }

        // Always exclude certain product types that shouldn't have price changes
        $args['type'] = $this->get_allowed_product_types();

        return $args;
    }

    /**
     * Add category filters to query arguments
     *
     * @param array $args Current query arguments.
     * @param array $rule_data Rule configuration.
     * @return array Modified query arguments.
     */
    private function add_category_filters( $args, $rule_data ) {
        if ( ! empty( $rule_data['categories'] ) && is_array( $rule_data['categories'] ) ) {
            $category_ids = array_map( 'absint', $rule_data['categories'] );
            
            // Use tax_query for category filtering
            $args['tax_query'] = array(
                array(
                    'taxonomy' => 'product_cat',
                    'field'    => 'term_id',
                    'terms'    => $category_ids,
                    'operator' => 'IN',
                ),
            );
        }

        return $args;
    }

    /**
     * Add tag filters to query arguments
     *
     * @param array $args Current query arguments.
     * @param array $rule_data Rule configuration.
     * @return array Modified query arguments.
     */
    private function add_tag_filters( $args, $rule_data ) {
        if ( ! empty( $rule_data['tags'] ) && is_array( $rule_data['tags'] ) ) {
            $tag_ids = array_map( 'absint', $rule_data['tags'] );
            
            // Use tax_query for tag filtering
            $args['tax_query'] = array(
                array(
                    'taxonomy' => 'product_tag',
                    'field'    => 'term_id',
                    'terms'    => $tag_ids,
                    'operator' => 'IN',
                ),
            );
        }

        return $args;
    }

    /**
     * Add product type filters to query arguments
     *
     * @param array $args Current query arguments.
     * @param array $rule_data Rule configuration.
     * @return array Modified query arguments.
     */
    private function add_product_type_filters( $args, $rule_data ) {
        if ( ! empty( $rule_data['product_types'] ) && is_array( $rule_data['product_types'] ) ) {
            $product_types = array_map( 'sanitize_text_field', $rule_data['product_types'] );
            
            // Validate product types
            $valid_types = $this->get_allowed_product_types();
            $filtered_types = array_intersect( $product_types, $valid_types );
            
            if ( ! empty( $filtered_types ) ) {
                $args['type'] = $filtered_types;
            }
        }

        return $args;
    }

    /**
     * Execute the product query
     *
     * @param array $args Query arguments.
     * @return array Array of products.
     */
    private function execute_product_query( $args ) {
        // Use WC_Product_Query for better performance and WooCommerce compatibility
        $query = new WC_Product_Query( $args );
        $products = $query->get_products();

        // Convert to our standard format
        $product_data = array();
        
        foreach ( $products as $product ) {
            if ( $this->is_product_eligible( $product ) ) {
                $product_data[] = array(
                    'product'    => $product,
                    'product_id' => $product->get_id(),
                    'type'       => $product->get_type(),
                    'price'      => $product->get_regular_price(),
                );
            }
        }

        return $product_data;
    }

    /**
     * Apply additional filters that can't be handled in the main query
     *
     * @param array $products Array of product data.
     * @param array $rule_data Rule configuration.
     * @return array Filtered products.
     */
    private function apply_additional_filters( $products, $rule_data ) {
        $target_scope = isset( $rule_data['target_scope'] ) ? $rule_data['target_scope'] : 'all';

        // Apply price range filter
        if ( 'price_range' === $target_scope ) {
            $products = $this->filter_by_price_range( $products, $rule_data );
        }

        // Remove products without prices
        $products = $this->filter_products_with_prices( $products );

        return $products;
    }

    /**
     * Filter products by price range
     *
     * @param array $products Array of product data.
     * @param array $rule_data Rule configuration.
     * @return array Filtered products.
     */
    private function filter_by_price_range( $products, $rule_data ) {
        $min_price = isset( $rule_data['price_min'] ) ? floatval( $rule_data['price_min'] ) : 0;
        $max_price = isset( $rule_data['price_max'] ) ? floatval( $rule_data['price_max'] ) : 0;

        // If both min and max are 0, return all products
        if ( 0 === $min_price && 0 === $max_price ) {
            return $products;
        }

        $filtered_products = array();

        foreach ( $products as $product_data ) {
            $product = $product_data['product'];
            $current_price = $this->get_product_price_for_filtering( $product );

            if ( empty( $current_price ) ) {
                continue;
            }

            $current_price = floatval( $current_price );

            // Check min price
            if ( $min_price > 0 && $current_price < $min_price ) {
                continue;
            }

            // Check max price
            if ( $max_price > 0 && $current_price > $max_price ) {
                continue;
            }

            $filtered_products[] = $product_data;
        }

        return $filtered_products;
    }

    /**
     * Filter out products without prices
     *
     * @param array $products Array of product data.
     * @return array Filtered products with prices.
     */
    private function filter_products_with_prices( $products ) {
        $filtered_products = array();

        foreach ( $products as $product_data ) {
            $product = $product_data['product'];
            
            // Check if product has a regular price
            if ( $this->product_has_manageable_price( $product ) ) {
                $filtered_products[] = $product_data;
            }
        }

        return $filtered_products;
    }

    /**
     * Check if a product is eligible for price management
     *
     * @param WC_Product $product Product to check.
     * @return bool True if eligible.
     */
    private function is_product_eligible( $product ) {
        // Skip if product is not published
        if ( 'publish' !== $product->get_status() ) {
            return false;
        }

        // Skip external/affiliate products (they don't have manageable prices)
        if ( $product->is_type( 'external' ) ) {
            return false;
        }

        // Skip grouped products (parent products don't have direct prices)
        if ( $product->is_type( 'grouped' ) ) {
            return false;
        }

        // Additional checks can be added here
        return true;
    }

    /**
     * Check if product has manageable price
     *
     * @param WC_Product $product Product to check.
     * @return bool True if has manageable price.
     */
    private function product_has_manageable_price( $product ) {
        if ( $product->is_type( 'variable' ) ) {
            // For variable products, check if any variation has a price
            $variations = $product->get_children();
            
            foreach ( $variations as $variation_id ) {
                $variation = wc_get_product( $variation_id );
                if ( $variation && ! empty( $variation->get_regular_price() ) ) {
                    return true;
                }
            }
            
            return false;
        } else {
            // For simple products, check regular price
            return ! empty( $product->get_regular_price() );
        }
    }

    /**
     * Get product price for filtering purposes
     *
     * @param WC_Product $product Product to get price from.
     * @return float|string Product price.
     */
    private function get_product_price_for_filtering( $product ) {
        if ( $product->is_type( 'variable' ) ) {
            // For variable products, use the lowest variation price
            $min_price = $product->get_variation_price( 'min' );
            return $min_price;
        } else {
            // For simple products, use regular price
            return $product->get_regular_price();
        }
    }

    /**
     * Get allowed product types for price management
     *
     * @return array Array of allowed product types.
     */
    private function get_allowed_product_types() {
        $allowed_types = array(
            'simple',
            'variable',
        );

        /**
         * Filter allowed product types for price management
         *
         * @param array $allowed_types Array of allowed product types.
         */
        return apply_filters( 'pricetunex_allowed_product_types', $allowed_types );
    }

    /**
     * Get products count by rules (for statistics)
     *
     * @param array $rule_data Rule configuration.
     * @return int Number of products that would be affected.
     */
    public function get_products_count_by_rules( $rule_data ) {
        $products = $this->get_products_by_rules( $rule_data );
        return count( $products );
    }

    /**
     * Get all product categories
     *
     * @return array Array of category data.
     */
    public function get_all_product_categories() {
        $categories = get_terms( array(
            'taxonomy'   => 'product_cat',
            'hide_empty' => false,
            'orderby'    => 'name',
            'order'      => 'ASC',
        ) );

        $category_data = array();

        if ( ! is_wp_error( $categories ) ) {
            foreach ( $categories as $category ) {
                $category_data[] = array(
                    'id'    => $category->term_id,
                    'name'  => $category->name,
                    'slug'  => $category->slug,
                    'count' => $category->count,
                );
            }
        }

        return $category_data;
    }

    /**
     * Get all product tags
     *
     * @return array Array of tag data.
     */
    public function get_all_product_tags() {
        $tags = get_terms( array(
            'taxonomy'   => 'product_tag',
            'hide_empty' => false,
            'orderby'    => 'name',
            'order'      => 'ASC',
        ) );

        $tag_data = array();

        if ( ! is_wp_error( $tags ) ) {
            foreach ( $tags as $tag ) {
                $tag_data[] = array(
                    'id'    => $tag->term_id,
                    'name'  => $tag->name,
                    'slug'  => $tag->slug,
                    'count' => $tag->count,
                );
            }
        }

        return $tag_data;
    }

    /**
     * Get total products count
     *
     * @return int Total number of eligible products.
     */
    public function get_total_products_count() {
        $args = array(
            'status'  => 'publish',
            'type'    => $this->get_allowed_product_types(),
            'limit'   => -1,
            'return'  => 'ids',
        );

        $query = new WC_Product_Query( $args );
        $product_ids = $query->get_products();

        // Count products with prices
        $count = 0;
        foreach ( $product_ids as $product_id ) {
            $product = wc_get_product( $product_id );
            if ( $product && $this->product_has_manageable_price( $product ) ) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Get products by specific IDs
     *
     * @param array $product_ids Array of product IDs.
     * @return array Array of product data.
     */
    public function get_products_by_ids( $product_ids ) {
        if ( empty( $product_ids ) || ! is_array( $product_ids ) ) {
            return array();
        }

        $product_ids = array_map( 'absint', $product_ids );
        $product_data = array();

        foreach ( $product_ids as $product_id ) {
            $product = wc_get_product( $product_id );
            
            if ( $product && $this->is_product_eligible( $product ) ) {
                $product_data[] = array(
                    'product'    => $product,
                    'product_id' => $product_id,
                    'type'       => $product->get_type(),
                    'price'      => $product->get_regular_price(),
                );
            }
        }

        return $product_data;
    }

    /**
     * Search products by name or SKU
     *
     * @param string $search_term Search term.
     * @param int    $limit Maximum number of results.
     * @return array Array of product data.
     */
    public function search_products( $search_term, $limit = 50 ) {
        if ( empty( $search_term ) ) {
            return array();
        }

        $args = array(
            'status'  => 'publish',
            'type'    => $this->get_allowed_product_types(),
            'limit'   => $limit,
            'orderby' => 'relevance',
            'order'   => 'DESC',
            's'       => sanitize_text_field( $search_term ),
        );

        $query = new WC_Product_Query( $args );
        $products = $query->get_products();

        $product_data = array();

        foreach ( $products as $product ) {
            if ( $this->is_product_eligible( $product ) && $this->product_has_manageable_price( $product ) ) {
                $product_data[] = array(
                    'product'    => $product,
                    'product_id' => $product->get_id(),
                    'type'       => $product->get_type(),
                    'price'      => $product->get_regular_price(),
                    'name'       => $product->get_name(),
                    'sku'        => $product->get_sku(),
                );
            }
        }

        return $product_data;
    }

    /**
     * Get products in specific price range for statistics
     *
     * @param float $min_price Minimum price.
     * @param float $max_price Maximum price.
     * @return array Array with count and sample products.
     */
    public function get_products_in_price_range( $min_price = 0, $max_price = 0 ) {
        $rule_data = array(
            'target_scope' => 'price_range',
            'price_min'    => $min_price,
            'price_max'    => $max_price,
        );

        $products = $this->get_products_by_rules( $rule_data );

        return array(
            'count'    => count( $products ),
            'products' => array_slice( $products, 0, 10 ), // Return first 10 for preview
        );
    }

    /**
     * Get product statistics
     *
     * @return array Array of product statistics.
     */
    public function get_product_statistics() {
        $stats = array(
            'total_products'     => 0,
            'simple_products'    => 0,
            'variable_products'  => 0,
            'products_with_price' => 0,
            'average_price'      => 0,
            'price_ranges'       => array(
                'under_10'   => 0,
                '10_to_50'   => 0,
                '50_to_100'  => 0,
                'over_100'   => 0,
            ),
        );

        // Get all eligible products
        $args = array(
            'status' => 'publish',
            'type'   => $this->get_allowed_product_types(),
            'limit'  => -1,
            'return' => 'objects',
        );

        $query = new WC_Product_Query( $args );
        $products = $query->get_products();

        $total_price = 0;
        $price_count = 0;

        foreach ( $products as $product ) {
            if ( ! $this->is_product_eligible( $product ) ) {
                continue;
            }

            $stats['total_products']++;

            // Count by type
            if ( $product->is_type( 'simple' ) ) {
                $stats['simple_products']++;
            } elseif ( $product->is_type( 'variable' ) ) {
                $stats['variable_products']++;
            }

            // Check if has price
            if ( $this->product_has_manageable_price( $product ) ) {
                $stats['products_with_price']++;

                // Get price for statistics
                $price = $this->get_product_price_for_filtering( $product );
                
                if ( ! empty( $price ) ) {
                    $price = floatval( $price );
                    $total_price += $price;
                    $price_count++;

                    // Categorize by price range
                    if ( $price < 10 ) {
                        $stats['price_ranges']['under_10']++;
                    } elseif ( $price < 50 ) {
                        $stats['price_ranges']['10_to_50']++;
                    } elseif ( $price < 100 ) {
                        $stats['price_ranges']['50_to_100']++;
                    } else {
                        $stats['price_ranges']['over_100']++;
                    }
                }
            }
        }

        // Calculate average price
        if ( $price_count > 0 ) {
            $stats['average_price'] = $total_price / $price_count;
        }

        return $stats;
    }
}