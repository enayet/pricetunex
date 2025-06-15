<?php
/**
 * Product Query - Handle product fetching and filtering based on rules - FIXED VERSION
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
     * Get products based on targeting rules - FIXED for variable products
     *
     * @param array $rule_data Rule configuration containing target criteria.
     * @return array Array of product data objects.
     */
    public function get_products_by_rules( $rule_data ) {
        try {
            // DEBUG: Log the incoming rule data
            error_log( 'PriceTuneX: Getting products with rules: ' . print_r( $rule_data, true ) );
            
            // Build query arguments based on rules
            $query_args = $this->build_query_args( $rule_data );
            
            // DEBUG: Log the query arguments
            error_log( 'PriceTuneX: Query args: ' . print_r( $query_args, true ) );
            
            // Execute the query
            $products = $this->execute_product_query( $query_args );
            
            // Apply additional filters if needed
            $filtered_products = $this->apply_additional_filters( $products, $rule_data );
            
            // DEBUG: Final count
            error_log( 'PriceTuneX: Final filtered products count: ' . count( $filtered_products ) );
            
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
            
            // DEBUG: Log what categories we're filtering by
            error_log( 'PriceTuneX: Filtering by category IDs: ' . implode( ', ', $category_ids ) );
            
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
     * Execute the product query - FIXED to properly handle variable products
     *
     * @param array $args Query arguments.
     * @return array Array of products.
     */
    private function execute_product_query( $args ) {
        // Use WC_Product_Query for better performance and WooCommerce compatibility
        $query = new WC_Product_Query( $args );
        $products = $query->get_products();

        // DEBUG: Log what WooCommerce returned
        error_log( 'PriceTuneX: WooCommerce returned ' . count( $products ) . ' parent products' );

        // Convert to our standard format and expand variable products
        $product_data = array();
        $processed_variations = array(); // Track variations to prevent duplicates
        
        foreach ( $products as $product ) {
            if ( ! $this->is_product_eligible( $product ) ) {
                continue;
            }

            error_log( 'PriceTuneX: Processing product: ' . $product->get_name() . ' (ID: ' . $product->get_id() . ', Type: ' . $product->get_type() . ')' );

            if ( $product->is_type( 'variable' ) ) {
                // For variable products, add each variation as a separate entry
                $variations = $product->get_children();
                error_log( 'PriceTuneX: Variable product has ' . count( $variations ) . ' variations' );
                
                foreach ( $variations as $variation_id ) {
                    // Check if we've already processed this variation
                    if ( in_array( $variation_id, $processed_variations ) ) {
                        error_log( 'PriceTuneX: Skipping duplicate variation ID: ' . $variation_id );
                        continue;
                    }
                    
                    $variation = wc_get_product( $variation_id );
                    
                    if ( $variation && $this->product_has_manageable_price( $variation ) ) {
                        $product_data[] = array(
                            'product'    => $variation,
                            'product_id' => $variation_id,
                            'parent_id'  => $product->get_id(),
                            'type'       => 'variation',
                            'price'      => $variation->get_regular_price(),
                        );
                        
                        // Mark this variation as processed
                        $processed_variations[] = $variation_id;
                        error_log( 'PriceTuneX: Added variation: ' . $variation->get_name() . ' (ID: ' . $variation_id . ', Price: ' . $variation->get_regular_price() . ')' );
                    }
                }
            } else {
                // For simple products, add normally
                if ( $this->product_has_manageable_price( $product ) ) {
                    $product_data[] = array(
                        'product'    => $product,
                        'product_id' => $product->get_id(),
                        'type'       => $product->get_type(),
                        'price'      => $product->get_regular_price(),
                    );
                    error_log( 'PriceTuneX: Added simple product: ' . $product->get_name() . ' (ID: ' . $product->get_id() . ', Price: ' . $product->get_regular_price() . ')' );
                }
            }
        }

        // DEBUG: Final breakdown
        error_log( 'PriceTuneX: Total variations/products found: ' . count( $product_data ) );
        error_log( 'PriceTuneX: Processed variations count: ' . count( $processed_variations ) );
        
        // Group by parent to see the breakdown
        $debug_breakdown = array();
        foreach ( $product_data as $item ) {
            if ( isset( $item['parent_id'] ) ) {
                $parent_name = wc_get_product( $item['parent_id'] )->get_name();
                if ( ! isset( $debug_breakdown[ $parent_name ] ) ) {
                    $debug_breakdown[ $parent_name ] = 0;
                }
                $debug_breakdown[ $parent_name ]++;
            } else {
                $product_name = $item['product']->get_name();
                $debug_breakdown[ $product_name ] = 1;
            }
        }
        
        error_log( 'PriceTuneX Debug Breakdown: ' . print_r( $debug_breakdown, true ) );

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
            $current_price = floatval( $product_data['price'] );

            if ( empty( $current_price ) ) {
                continue;
            }

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

        return true;
    }

    /**
     * Check if product has manageable price - SIMPLIFIED
     *
     * @param WC_Product $product Product to check.
     * @return bool True if has manageable price.
     */
    private function product_has_manageable_price( $product ) {
        // Simple check - just see if the product has a regular price
        $regular_price = $product->get_regular_price();
        return ! empty( $regular_price ) && is_numeric( $regular_price ) && floatval( $regular_price ) > 0;
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

        // Count products with prices (including variations)
        $count = 0;
        foreach ( $product_ids as $product_id ) {
            $product = wc_get_product( $product_id );
            if ( ! $product ) {
                continue;
            }

            if ( $product->is_type( 'variable' ) ) {
                // Count variations with prices
                $variations = $product->get_children();
                foreach ( $variations as $variation_id ) {
                    $variation = wc_get_product( $variation_id );
                    if ( $variation && $this->product_has_manageable_price( $variation ) ) {
                        $count++;
                    }
                }
            } else {
                // Count simple products with prices
                if ( $this->product_has_manageable_price( $product ) ) {
                    $count++;
                }
            }
        }

        return $count;
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
                
                if ( $this->product_has_manageable_price( $product ) ) {
                    $stats['products_with_price']++;
                    $price = floatval( $product->get_regular_price() );
                    $total_price += $price;
                    $price_count++;
                }
            } elseif ( $product->is_type( 'variable' ) ) {
                $stats['variable_products']++;
                
                // Count variations
                $variations = $product->get_children();
                foreach ( $variations as $variation_id ) {
                    $variation = wc_get_product( $variation_id );
                    if ( $variation && $this->product_has_manageable_price( $variation ) ) {
                        $stats['products_with_price']++;
                        $price = floatval( $variation->get_regular_price() );
                        $total_price += $price;
                        $price_count++;
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