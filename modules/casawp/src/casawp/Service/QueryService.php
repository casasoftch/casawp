<?php
namespace casawp\Service;

class QueryService{

    private $defaultQuery = array();

    public function __construct(){
        $this->defaultQuery = array(
            'post_type' => 'casawp_property',
            'posts_per_page' => get_option('posts_per_page', 10),
            'order' => get_option('casawp_archive_order', 'DESC'),
            'ignore_sticky_posts' => 0,
            'post__not_in' => null,
            'orderby' => get_option('casawp_archive_orderby', 'date'),
            'categories' => array(),
            'utilities' => array(),
            'locations' => array(),
            'countries' => array(),
            'regions' => array(),
            'salestypes' => array(),
            'availabilities' => array('active'),
            'categories_not' => array(),
            'utilities_not' => array(),
            'locations_not' => array(),
            'countries_not' => array(),
            'salestypes_not' => array(),
            'availabilities_not' => array(),
            'regions_not' => array(),
            'features' => array(),
            'features_not' => array(),
            'my_lng' => null,
            'my_lat' => null,
            'radius_km' => 10,
            'projectunit_id' => null,
            'rooms_from' => null,
            'rooms_to' => null,
            'areas_from' => null,
            'areas_to' => null,
            'price_from' => null,
            'price_to' => null,
            'price_range' => null,
            'price_range_custom' => null,
            'price_for_order' => null,
            'price_for_order_to' => null,
            'price_for_order_from' => null,
            'price_for_order_not' => null,
            'filter_meta_key' => null,
            'filter_meta_key_not' => null,
            'filter_meta_compare' => null,
            'filter_meta_value' => null,
            'filter_meta_key_2' => null,
            'filter_meta_compare_2' => null,
            'filter_meta_value_2' => null
        );
        $this->setQuery();
    }

    private $query = false;
    public function setQuery($manualquery = array()){
        $requestquery = $this->interpretRequest();
        $query = array_merge($this->defaultQuery, $requestquery, $manualquery);
        foreach ($query as $key => $value) {
            if (!array_key_exists($key, $this->defaultQuery)) {
                unset($query[$key]);
            }
        }
        $this->query = $query;
        //$this->applyToWpQuery();
        return $query;
    }
    public function setCustomQuery($query){
        foreach ($query as $key => $value) {
            if (!array_key_exists($key, $this->defaultQuery)) {
                unset($query[$key]);
            } else {
                if (is_string($value)) { // Ensure $value is a string before using strpos
                    if (strpos($value, ',') !== false) { // Check for comma in string
                        $query[$key] = array_map('trim', explode(',', $value));
                    } elseif (in_array($key, [
                        'categories',
                        'utilities',
                        'locations',
                        'countries',
                        'regions',
                        'salestypes',
                        'categories_not',
                        'locations_not',
                        'countries_not',
                        'salestypes_not',
                        'availabilities',
                        'availabilities_not',
                        'regions_not',
                        'features',
                        'features_not'
                    ])) {
                        $query[$key] = [$value];
                    }
                } else {
                    // Handle cases where $value is an array, if necessary
                    // For example, you might want to validate arrays or leave them as-is
                    // Currently, no action is taken for non-string $value
                }
            }
        }
        $this->query = array_merge($this->query, $query);
    }


    public function createWpQuery($args = false){
        if ($args) {
            $this->setCustomQuery($args);
        }
        return new \WP_Query( $this->getArgs());
    }

    public function getQuery(){
        return $this->query;
    }

    public function getQueryValue($key){
        if (array_key_exists($key, $this->query)) {
            return $this->query[$key];
        } else {
            return false;
        }
    }

    private function interpretRequest(){
        $r_query = $_GET;
        $query = array();
        foreach ($r_query as $key => $value) {

            if ($key == 'casawp_salestype_s') {
                $key = 'salestypes';
            }

            // Remove legacy prefixes
            if (strpos($key, 'casawp_') === 0) {
                $key = str_replace('casawp_', '', $key);
            }

            // Remove legacy postfixes
            if (strpos($key, '_not_s') !== false) {
                $key = str_replace('_not_s', '_not', $key);
            }
            if (strpos($key, '_s') !== false) {
                $key = str_replace('_s', '', $key);
            }

            // Fix singular terms
            $singular_plural_map = [
                'category'     => 'categories',
                'utility'      => 'utilities',
                'location'     => 'locations',
                'country'      => 'countries',
                'region'       => 'regions',
                'feature'      => 'features',
                'salestype'    => 'salestypes',
                'availability' => 'availabilities',
            ];

            foreach ($singular_plural_map as $singular => $plural) {
                if (strpos($key, $singular) !== false && strpos($key, $plural) === false) {
                    $key = str_replace($singular, $plural, $key);
                }
            }

            switch ($key) {
                case 'categories':
                case 'utilities':
                case 'locations':
                case 'countries':
                case 'regions':
                case 'features':
                case 'salestypes':
                case 'availabilities':
                case 'categories_not':
                case 'utilities_not':
                case 'locations_not':
                case 'countries_not':
                case 'regions_not':
                case 'features_not':
                case 'salestypes_not':
                case 'availabilities_not':
                    // If the value is an array with only one element, simplify it
                    if (is_array($value) && count($value) === 1) {
                        $value = $value[0];
                    }

                    // If the value is a comma-separated string, convert it to an array
                    if (is_string($value) && strpos($value, ",") !== false) {
                        $value = explode(',', $value);
                    }

                    // Ensure the value is an array
                    $query[$key] = is_array($value) ? $value : array($value);

                    // Check if the first element exists and is not an empty string
                    if (isset($query[$key][0]) && $query[$key][0] !== '') {
                        // Keep the array as is
                    } else {
                        // Set to an empty array if the first element is not valid
                        $query[$key] = array();
                    }
                    break;

                default:
                    $query[$key] = $value;
                    break;
            }
        }

        return $query;
    }


    public function getArgs(){
        $args = array();
        $args['post_type'] = $this->query['post_type'];
        $args['posts_per_page'] = $this->query['posts_per_page'];
        $args['order'] = $this->query['order'];

        $args['ignore_sticky_posts'] = $this->query['ignore_sticky_posts'];

        if (get_option( 'casawp_hide_sticky_properties_in_main')) {
            $args['post__not_in'] = get_option( 'sticky_posts' );
        } else {
            if (is_array($this->query["post__not_in"])) {
                $args['post__not_in'] = $this->query["post__not_in"];
            } else {
                $args['post__not_in'] = array($this->query["post__not_in"]);
            }
        }

        switch ($this->query['orderby']) {
            case 'title':
                $args['orderby'] = 'title';
                break;
            case 'location':
                $args['meta_key'] = 'property_address_locality';
                $args['orderby'] = 'meta_value';
                break;
            case 'rooms':
                $args['meta_key'] = 'number_of_rooms';
                $args['orderby'] = 'meta_value_num';
                break;
            case 'area':
                $args['meta_key'] = 'areaForOrder';
                $args['orderby'] = 'meta_value_num';
                break;
            case 'price':
                $args['meta_key'] = 'priceForOrder';
                $args['orderby'] = 'meta_value_num';
                break;
            case 'start':
                $args['meta_key'] = 'priceForOrder';
                $args['orderby'] = 'meta_value_num';
                break;
            case 'menu_order':
                $args['orderby'] = 'menu_order date';
                break;
            case 'casawp_referenceId':
                $args['meta_key'] = 'referenceId';
                $args['orderby'] = 'meta_value';
                break;
            case 'modified':
                $args['orderby'] = 'modified';
                break;
            case 'date':
            default:
                $args['orderby'] = 'date';
                break;
        }


        $meta_query_items_new = array();

        if ($this->query['filter_meta_key']) {
            $ametaquery = [
                'key' => $this->query['filter_meta_key'],
            ];
            if ($this->query['filter_meta_value']) {
                $ametaquery['compare'] = ($this->query['filter_meta_compare'] ? $this->query['filter_meta_compare'] : 'IN');
                $ametaquery['value'] = $this->query['filter_meta_value'];
            }
            $meta_query_items_new[] = $ametaquery;
        }

        if ($this->query['filter_meta_key_not']) {
            $ametaquery = [
                'key' => $this->query['filter_meta_key_not'],
                'compare' => 'NOT EXISTS'
            ];
            $meta_query_items_new[] = $ametaquery;
        }

        if ($this->query['filter_meta_key_2']) {
            $ametaquery = [
                'key' => $this->query['filter_meta_key_2'],
            ];
            if ($this->query['filter_meta_value_2']) {
                $ametaquery['compare'] = 'IN';
                $ametaquery['value'] = $this->query['filter_meta_value_2'];
            }
            $meta_query_items_new[] = $ametaquery;
        }



        if ($this->query['projectunit_id']) {
            $meta_query_items_new[] = array(
                'key' => 'projectunit_id',
                'value' => $this->query['projectunit_id'],
                'compare'   => '='
            );
        }

        if (isset($this->query['rooms_from']) && $this->query['rooms_from']) {
            $rooms_from = $this->get_first_element($this->query['rooms_from']);
            error_log('rooms_from: ' . $rooms_from);
            if ($rooms_from !== null && $rooms_from !== '') {
                $meta_query_items_new[] = array(
                    'key' => 'number_of_rooms',
                    'value' => floatval($rooms_from), // Ensure it's numeric
                    'compare' => '>=',
                    'type' => 'DECIMAL(10,1)'
                );
            }
        }
        
        if (isset($this->query['rooms_to']) && $this->query['rooms_to']) {
            $rooms_to = $this->get_first_element($this->query['rooms_to']);
            if ($rooms_to !== null && $rooms_to !== '') {
                $meta_query_items_new[] = array(
                    'key' => 'number_of_rooms',
                    'value' => floatval($rooms_to), // Ensure it's numeric
                    'compare' => '<=',
                    'type' => 'DECIMAL(10,1)'
                );
            }
        }

        if (isset($this->query['areas_from']) && $this->query['areas_from']) {
            $areas_from = $this->get_first_element($this->query['areas_from']);
            if ($areas_from !== null && $areas_from !== '') {
                $meta_query_items_new[] = array(
                    'key' => 'areaForOrder',
                    'value' => floatval($areas_from), // Ensure it's numeric
                    'compare' => '>=',
                    'type' => 'NUMERIC'
                );
            }
        }

        if (isset($this->query['areas_to']) && $this->query['areas_to']) {
            $areas_to = $this->get_first_element($this->query['areas_to']);
            if ($areas_to !== null && $areas_to !== '') {
                $meta_query_items_new[] = array(
                    'key' => 'areaForOrder',
                    'value' => floatval($areas_to), // Ensure it's numeric
                    'compare' => '<=',
                    'type' => 'NUMERIC'
                );
            }
        }

        if (isset($this->query['price_for_order_to'])) {
            $price_to_seek_part = $this->query['price_for_order_to'];
            if ($price_to_seek_part) {
                $meta_query_items_new[] = array(
                    array(
                        'key' => 'priceForOrder',
                        'value' => $price_to_seek_part,
                        'compare' => '<=',
                        'type' => 'NUMERIC'
                    ),
                );
            }
        }

        if (isset($this->query['price_for_order_from'])) {
            $price_from_seek_part = $this->query['price_for_order_from'];
            if ($price_from_seek_part) {
                $meta_query_items_new[] = array(
                    array(
                        'key' => 'priceForOrder',
                        'value' => $price_from_seek_part,
                        'compare' => '>=',
                        'type' => 'NUMERIC'
                    ),
                );
            }
        }

        //Price for Order
        if (isset($this->query['price_for_order'])) {
            $price_seek_part = $this->query['price_for_order'];
            if ($price_seek_part) {
                $meta_query_items_new[] = array(
                    array(
                        'key' => 'priceForOrder',
                        'value' => $price_seek_part,
                        'compare' => '==',
                        'type' => 'NUMERIC'
                    ),
                );
            }
        } elseif(isset($this->query['price_for_order_not'])) {
            $price_seek_part_not = $this->query['price_for_order_not'];
            if ($price_seek_part_not) {
                $meta_query_items_new[] = array(
                    array(
                        'key' => 'priceForOrder',
                        'value' => $price_seek_part_not,
                        'compare' => '!=',
                        'type' => 'NUMERIC'
                    ),
                );
            }
        } else {
            if (in_array('rent', $this->query['salestypes'])) {
            if ($this->query['price_from']) {
                $meta_query_items_new[] = array(
                    'key' => 'grossPrice',
                    'value' => (is_array($this->query['price_from']) ? $this->query['price_from'][0] : $this->query['price_from']),
                    'compare'   => '>=',
                    'type' => 'NUMERIC'
                );
            }
            if ($this->query['price_to']) {
                $price_to = $this->query['price_to'];
                if (is_array($price_to)) {
                    foreach ($price_to as $pt) {
                        if (is_string($pt) && strpos($pt, '-') !== false) {
                            $price_parts = explode('-', $pt);
                            if (!empty($price_parts[0])) {
                                $meta_query_items_new[] = array(
                                    'key' => 'grossPrice',
                                    'value' => floatval($price_parts[0]),
                                    'compare'   => '>=',
                                    'type' => 'NUMERIC'
                                );
                            }
                            if (!empty($price_parts[1])) {
                                $meta_query_items_new[] = array(
                                    'key' => 'grossPrice',
                                    'value' => floatval($price_parts[1]),
                                    'compare'   => '<=',
                                    'type' => 'NUMERIC'
                                );
                            }
                        } elseif (is_numeric($pt)) {
                            $meta_query_items_new[] = array(
                                'key' => 'grossPrice',
                                'value' => floatval($pt),
                                'compare'   => '<=',
                                'type' => 'NUMERIC'
                            );
                        }
                    }
                } elseif (is_string($price_to)) {
                    if (strpos($price_to, '-') !== false) {
                        $price_parts = explode('-', $price_to);
                        if (!empty($price_parts[0])) {
                            $meta_query_items_new[] = array(
                                'key' => 'grossPrice',
                                'value' => floatval($price_parts[0]),
                                'compare'   => '>=',
                                'type' => 'NUMERIC'
                            );
                        }
                        if (!empty($price_parts[1])) {
                            $meta_query_items_new[] = array(
                                'key' => 'grossPrice',
                                'value' => floatval($price_parts[1]),
                                'compare'   => '<=',
                                'type' => 'NUMERIC'
                            );
                        }
                    } else {
                        $meta_query_items_new[] = array(
                            'key' => 'grossPrice',
                            'value' => floatval($price_to),
                            'compare'   => '<=',
                            'type' => 'NUMERIC'
                        );
                    }
                }
            }
            } else if(in_array('buy', $this->query['salestypes'])){
            if ($this->query['price_from']) {
                $meta_query_items_new[] = array(
                    'key' => 'price',
                    'value' => (is_array($this->query['price_from']) ? $this->query['price_from'][0] : $this->query['price_from']),
                    'compare'   => '>=',
                    'type' => 'NUMERIC'
                );
            }
            if ($this->query['price_to']) {
                if (strpos($this->query['price_to'], '-') !== false) {
                $price_parts = explode('-', $this->query['price_to']);
                if ($price_parts[0]) {
                    $meta_query_items_new[] = array(
                        'key' => 'price',
                        'value' => $price_parts[0],
                        'compare'   => '>=',
                        'type' => 'NUMERIC'
                    );
                }
                if ($price_parts[1]) {
                    $meta_query_items_new[] = array(
                        'key' => 'price',
                        'value' => $price_parts[1],
                        'compare'   => '<=',
                        'type' => 'NUMERIC'
                    );
                }
                } else {
                $meta_query_items_new[] = array(
                    'key' => 'price',
                    'value' => (is_array($this->query['price_to']) ? $this->query['price_to'][0] : $this->query['price_to']),
                    'compare'   => '<=',
                    'type' => 'NUMERIC'
                );
                }
            }



                if ($this->query['price_range'] && strpos($this->query['price_range'], '-') !== false ) {
                    $price_seek_parts = explode('-', $this->query['price_range']);
                    $range_seek_from = $price_seek_parts[0];
                    $range_seek_to = $price_seek_parts[1];
                    if ($range_seek_from && $range_seek_to) {               
                        $meta_query_items_new[] = array(
                            'relation' => 'OR',
                            array(
                            'key' => 'price_range_from',
                            'value' => 0,
                            'compare'   => '>',
                            'type' => 'UNSIGNED'
                            ),
                            array(
                            'key' => 'price_range_to',
                            'value' => 0,
                            'compare'   => '>',
                            'type' => 'UNSIGNED'
                            )
                        );
                        $meta_query_items_new[] = array(
                            'relation' => 'OR',
                            array(
                            'key' => 'price_range_from',
                            'value' => array($range_seek_from, $range_seek_to),
                            'compare'   => 'BETWEEN',
                            'type' => 'UNSIGNED'
                            ),
                            array(
                            'key' => 'price_range_from',
                            'compare'   => 'NOT EXISTS'
                            )
                        );
                        $meta_query_items_new[] = array(
                            'relation' => 'OR',
                            array(
                            'key' => 'price_range_to',
                            'value' => array($range_seek_from, $range_seek_to),
                            'compare'   => 'BETWEEN',
                            'type' => 'UNSIGNED'
                            ),
                            array(
                            'key' => 'price_range_to',
                            'compare'   => 'NOT EXISTS'
                            )
                        );
                    }
                }

                //Define custom ranges in theme and search all prices. Did this for Property One
                if ($this->query['price_range_custom'] && strpos($this->query['price_range_custom'], '-') !== false ) {
                    $price_seek_parts = explode('-', $this->query['price_range_custom']);
                    $range_seek_from = $price_seek_parts[0];
                    $range_seek_to = $price_seek_parts[1];
                    if ($range_seek_from && $range_seek_to) {
                        $meta_query_items_new[] = array(
                            array(
                                'relation' => 'OR',
                                array(
                                    'key' => 'price',
                                    'value' => $price_seek_parts,
                                    'compare'   => 'BETWEEN',
                                    'type' => 'NUMERIC'
                                ),
                                array(
                                    'key' => 'price_range_from',
                                    'value' => $price_seek_parts,
                                    'compare'   => 'BETWEEN',
                                    'type' => 'NUMERIC'
                                ),
                                array(
                                    'key' => 'price_range_to',
                                    'value' => $price_seek_parts,
                                    'compare'   => 'BETWEEN',
                                    'type' => 'NUMERIC'
                                ),
                            ),                        
                        );
                    }
                }
            }
        }



        if ($meta_query_items_new) {

            $meta_query_items_new['relation'] = 'AND';
            $args['meta_query'] = $meta_query_items_new;
        }

        if ( isset($this->query['my_lng'], $this->query['my_lat'], $this->query['radius_km']) &&
             !empty($this->query['my_lng']) &&
             !empty($this->query['my_lat']) &&
             !empty($this->query['radius_km']) ) {

            // Sanitize and validate inputs
            $mylng_raw = $this->query['my_lng'];
            $mylat_raw = $this->query['my_lat'];
            $radius_raw = $this->query['radius_km'];

            // Convert to float and validate ranges
            $mylng = floatval($mylng_raw);
            $mylat = floatval($mylat_raw);
            $radius = floatval($radius_raw);

            // Validate latitude and longitude ranges
            if ( $mylat < -90 || $mylat > 90 ) {
                // Handle invalid latitude
                // You can set a default value or skip adding this filter
                $mylat = 0; // Example default
            }

            if ( $mylng < -180 || $mylng > 180 ) {
                // Handle invalid longitude
                $mylng = 0; // Example default
            }

            // Validate radius (example: positive and not exceeding 1000 km)
            if ( $radius <= 0 ) {
                $radius = 10; // Default radius
            } elseif ( $radius > 1000 ) {
                $radius = 1000; // Maximum allowed radius
            }

            global $wpdb;

            // Prepare the SQL query using placeholders
            $sql = $wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} 
                LEFT JOIN {$wpdb->postmeta} AS latitude 
                    ON {$wpdb->posts}.ID = latitude.post_id 
                    AND latitude.meta_key = %s
                LEFT JOIN {$wpdb->postmeta} AS longitude 
                    ON {$wpdb->posts}.ID = longitude.post_id 
                    AND longitude.meta_key = %s
                WHERE (6371 * ACOS(
                    COS(RADIANS(%f)) 
                    * COS(RADIANS(latitude.meta_value)) 
                    * COS(RADIANS(longitude.meta_value) - RADIANS(%f)) 
                    + SIN(RADIANS(%f)) 
                    * SIN(RADIANS(latitude.meta_value))
                ) <= %f)",
                'property_geo_latitude',    // %s for latitude.meta_key
                'property_geo_longitude',   // %s for longitude.meta_key
                $mylat,                     // %f for mylat
                $mylng,                     // %f for mylng
                $mylat,                     // %f for mylat (again)
                $radius                     // %f for radius
            );

            // Execute the prepared query
            $results = $wpdb->get_results( $sql, ARRAY_A );

            // Extract post IDs
            $post_ids = wp_list_pluck( $results, 'ID' );

            // Update the query arguments
            $args['post__in'] = empty( $post_ids ) ? [ 0 ] : $post_ids;
        }



        $taxquery_new = array();

        if ($this->query['categories']) {
            $taxquery_new[] = array(
                'taxonomy'         => 'casawp_category',
                'terms'            => $this->query['categories'],
                'include_children' => 1,
                'field'            => 'slug',
                'operator'         => 'IN'
            );
        }
        if ($this->query['utilities']) {
            $taxquery_new[] = array(
                'taxonomy'         => 'casawp_utility',
                'terms'            => $this->query['utilities'],
                'include_children' => 1,
                'field'            => 'slug',
                'operator'         => 'IN'
            );
        }
        if ($this->query['locations']) {
            $taxquery_new[] = array(
                'taxonomy' => 'casawp_location',
                'terms' => $this->query['locations'],
                'include_children' => 1,
                'field' => 'slug',
                'operator'=> 'IN'
            );
        }
        if ($this->query['countries']) {
            $taxquery_new[] = array(
                'taxonomy' => 'casawp_location',
                'terms' => $this->query['countries'],
                'include_children' => 1,
                'field' => 'slug',
                'operator'=> 'IN'
            );
        }
        if ($this->query['regions']) {
            $taxquery_new[] = array(
                'taxonomy' => 'casawp_region',
                'terms' => $this->query['regions'],
                'include_children' => 1,
                'field' => 'slug',
                'operator'=> 'IN'
            );
        }
        if ($this->query['features']) {
            $taxquery_new[] = array(
                'taxonomy' => 'casawp_feature',
                'terms' => $this->query['features'],
                'include_children' => 1,
                'field' => 'slug',
                'operator'=> 'IN'
            );
        }

        if ($this->query['salestypes']) {
            $taxquery_new[] = array(
                'taxonomy' => 'casawp_salestype',
                'terms' => $this->query['salestypes'],
                'include_children' => 1,
                'field' => 'slug',
                'operator'=> 'IN'
             );
        }

        if ($this->query['availabilities']) {
            $taxquery_new[] = array(
                'taxonomy' => 'casawp_availability',
                'terms' => $this->query['availabilities'],
                'include_children' => 1,
                'field' => 'slug',
                'operator'=> 'IN'
             );
        }

        if ($this->query['categories_not']) {
            $taxquery_new[] = array(
                'taxonomy'         => 'casawp_category',
                'terms'            => $this->query['categories_not'],
                'include_children' => 1,
                'field'            => 'slug',
                'operator'         => 'NOT IN'
            );
        }
        if ((isset($this->options['utilities_not']) ? $this->options['utilities_not'] : null)) {
            $taxquery_new[] = array(
                'taxonomy'         => 'casawp_utility',
                'terms'            => (isset($this->options['utilities_not']) ? $this->options['utilities_not'] : null),
                'include_children' => 1,
                'field'            => 'slug',
                'operator'         => 'NOT IN'

            );
        }
        if ($this->query['utilities_not']) {
            $taxquery_new[] = array(
                'taxonomy'         => 'casawp_utility',
                'terms'            => $this->query['utilities_not'],
                'include_children' => 1,
                'field'            => 'slug',
                'operator'         => 'NOT IN'
            );
        }
        if ($this->query['locations_not']) {
            $taxquery_new[] = array(
                'taxonomy' => 'casawp_location',
                'terms' => $this->query['locations_not'],
                'include_children' => 1,
                'field' => 'slug',
                'operator'=> 'NOT IN'
            );
        }
        if ($this->query['countries_not']) {
            $taxquery_new[] = array(
                'taxonomy' => 'casawp_location',
                'terms' => $this->query['countries_not'],
                'include_children' => 1,
                'field' => 'slug',
                'operator'=> 'NOT IN'
            );
        }
        if ($this->query['regions_not']) {
            $taxquery_new[] = array(
                'taxonomy' => 'casawp_region',
                'terms' => $this->query['regions_not'],
                'include_children' => 1,
                'field' => 'slug',
                'operator'=> 'NOT IN'
            );
        }
        if ($this->query['features_not']) {
            $taxquery_new[] = array(
                'taxonomy' => 'casawp_feature',
                'terms' => $this->query['features_not'],
                'include_children' => 1,
                'field' => 'slug',
                'operator'=> 'NOT IN'
            );
        }

        if ($this->query['salestypes_not']) {
            $taxquery_new[] = array(
                'taxonomy' => 'casawp_salestype',
                'terms' => $this->query['salestypes_not'],
                'include_children' => 1,
                'field' => 'slug',
                'operator'=> 'NOT IN'
             );
        }

        if ($this->query['availabilities_not']) {
            $taxquery_new[] = array(
                'taxonomy' => 'casawp_availability',
                'terms' => $this->query['availabilities_not'],
                'include_children' => 1,
                'field' => 'slug',
                'operator'=> 'NOT IN'
             );
        }

        if ($this->query['features']) {
            $taxquery_new[] = array(
                'taxonomy' => 'casawp_feature',
                'terms' => $this->query['features'],
                'include_children' => 1,
                'field' => 'slug',
                'operator'=> 'IN'
             );
        }

        if ($taxquery_new) {
            $args['tax_query'] = $taxquery_new;
        }

        return $args;
    }

    private function get_first_element($value, $default = null) {
        if (is_array($value)) {
            return isset($value[0]) ? $value[0] : $default;
        } elseif (is_scalar($value)) {
            return $value;
        }
        return $default;
    }

    public function applyToWpQuery($query){
        //tax pages overides
        if ($query->is_main_query()) {
            if (is_tax('casawp_category')) {
                $this->query['categories'] = array(get_query_var( 'casawp_category' ));
            }
            if (is_tax('casawp_utility')) {
                $this->query['utilities'] = array(get_query_var( 'casawp_utility' ));
            }
            if (is_tax('casawp_location')) {
                if (strpos(get_query_var( 'casawp_location' ), 'country_') !== false) {
                    $this->query['countries'] = array(get_query_var( 'casawp_location' ));
                } else {
                    $this->query['locations'] = array(get_query_var( 'casawp_location' ));
                }

            }
            if (is_tax('casawp_region')) {
                $this->query['regions'] = array(get_query_var( 'casawp_region' ));
            }
            if (is_tax('casawp_feature')) {
                $this->query['features'] = array(get_query_var( 'casawp_feature' ));
            }
            if (is_tax('casawp_salestype')) {
                $this->query['salestypes'] = array(get_query_var( 'casawp_salestype' ));
            }
            if (is_tax('casawp_availability')) {
                $this->query['availabilities'] = array(get_query_var( 'casawp_availability' ));
            }
            if (is_tax('casawp_feature')) {
                $this->query['features'] = array(get_query_var( 'casawp_feature' ));
            }
        }

        $args = $this->getArgs();
        foreach ($args as $key => $value) {
            $query->set($key, $value);
        }

        add_filter( 'posts_where' , array($this, 'nearmefilter') );

	    return $query;
   	}

   	public function nearmefilter($where){
        $mylng = (float) (isset($this->query['my_lng']) ? $this->query['my_lng'] : null);
        $mylat = (float) (isset($this->query['my_lat']) ? $this->query['my_lat'] : null);
        $radiusKm = (int) (isset($this->query['radius_km']) ? $this->query['radius_km'] : 10);
        if ($mylng && $mylat) {
            global $wpdb;
            add_filter( 'posts_join' , array($this, 'nearmejoin') );

            $where .= " AND $wpdb->posts.ID IN (SELECT post_id FROM $wpdb->postmeta WHERE
                 ( 6371 * acos( cos( radians(" . $mylat . ") )
                                * cos( radians( latitude.meta_value ) )
                                * cos( radians( longitude.meta_value ) - radians(" . $mylng . ") )
                                + sin( radians(" . $mylat . ") )
                                * sin( radians( latitude.meta_value ) ) ) ) <= " . $radiusKm . ") ";

        }

        return $where;
    }

    public function nearmejoin($join){
        global $wpdb;
        // THE SPACE IS NEEDED!!!!!!!!!!!!!!!
        $join .= " LEFT JOIN $wpdb->postmeta AS latitude ON $wpdb->posts.ID = latitude.post_id AND latitude.meta_key = 'property_geo_latitude' ";
        $join .= " LEFT JOIN $wpdb->postmeta AS longitude ON $wpdb->posts.ID = longitude.post_id AND longitude.meta_key = 'property_geo_longitude' ";

        return $join;
    }

    //for form only
    public function getArrayCopy(){
        return $this->getQuery();
    }

    public function exchangeArray(){
        return $this->getQuery();
    }

}
