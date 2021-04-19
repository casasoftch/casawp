<?php
namespace casawp\Service;

class QueryService{

    private $defaultQuery = array();

    public function __construct(){
        $this->defaultQuery = array(
            'post-type' => 'casawp_property',
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
              if (strpos($value, ',')) {
                $query[$key] = array_map('trim', explode(',', $value));
              } elseif (is_string($value) && in_array($key, [
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
                'availabilities_not',
                'regions_not',
                'features',
                'features_not'])) {
                  $query[$key] = [$value];
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

            //remove legacy prefixes
            if (strpos($key, 'casawp_') === 0) {
                $key = str_replace('casawp_', '', $key);
            }

            //remove legacy postfixes
            if (strpos($key, '_not_s') !== false) {
                $key = str_replace('_not_s', '_not', $key);
            }
            if (strpos($key, '_s') !== false) {
                $key = str_replace('_s', '', $key);
            }

            //fix singles
            if (strpos($key, 'category') !== false) {
                $key = str_replace('category', 'categories', $key);
            }
            if (strpos($key, 'utility') !== false) {
                $key = str_replace('utility', 'utilities', $key);
            }
            if (strpos($key, 'locations') === false && strpos($key, 'location') !== false) {
                $key = str_replace('location', 'locations', $key);
            }
            if (strpos($key, 'countries') === false && strpos($key, 'country') !== false) {
                $key = str_replace('country', 'countries', $key);
            }
            if (strpos($key, 'regions') === false && strpos($key, 'region') !== false) {
                $key = str_replace('region', 'regions', $key);
            }
            if (strpos($key, 'features') === false && strpos($key, 'feature') !== false) {
                $key = str_replace('feature', 'features', $key);
            }
            if (strpos($key, 'salestypes') === false && strpos($key, 'salestype') !== false) {
                $key = str_replace('salestype', 'salestypes', $key);
            }
            if (strpos($key, 'availability') !== false) {
                $key = str_replace('availability', 'availabilities', $key);
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
                    if (is_array($value) && count($value) === 1) {
                        $value = $value[0];
                    }
                    if (is_string($value) && strpos($value, ",") !== null) {
                        $value = explode(',', $value);
                    }
                    $query[$key] = (is_array($value) ? $value : array($value));
                    $query[$key] = ($query[$key][0] !== '' ? $query[$key] : array());
                    break;
                default:
                    $query[$key] = $value;
                    break;
            }
        }

        //tax_queries override this
        /*if (is_tax('casawp_category')) {
            $query['categories'] = array(get_query_var( 'casawp_category' ));
        }
*/
        /*
        if (is_tax('casawp_location')) {
            $query['locations'] = array(get_query_var( 'casawp_location' ));
        }
        if (is_tax('casawp_salestype')) {
            $query['locations'] = array(get_query_var( 'casawp_salestype' ));
        }
        if (is_tax('casawp_availability')) {
            $query['locations'] = array(get_query_var( 'casawp_availability' ));
        }*/

        return $query;
    }


    public function getArgs(){
        $args = array();
        $args['post-type'] = $this->query['post-type'];
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
            case 'price':
                $args['meta_key'] = 'priceForOrder';
                $args['orderby'] = 'meta_value';
                break;
            case 'start':
                $args['meta_key'] = 'priceForOrder';
                $args['orderby'] = 'meta_value';
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
        if ($this->query['rooms_from']) {
            $meta_query_items_new[] = array(
                'key' => 'number_of_rooms',
                'value' => (is_array($this->query['rooms_from']) ? $this->query['rooms_from'][0] : $this->query['rooms_from']),
                'compare'   => '>=',
                'type' => 'DECIMAL(10,1)'
            );
        }
        if ($this->query['rooms_to']) {
            $meta_query_items_new[] = array(
                'key' => 'number_of_rooms',
                'value' => (is_array($this->query['rooms_to']) ? $this->query['rooms_to'][0] : $this->query['rooms_to']),
                'compare'   => '<=',
                'type' => 'DECIMAL(10,1)'
            );
        }
        if ($this->query['areas_from']) {
            $meta_query_items_new[] = array(
                'key' => 'areaForOrder',
                'value' => (is_array($this->query['areas_from']) ? $this->query['areas_from'][0] : $this->query['areas_from']),
                'compare'   => '>=',
                'type' => 'NUMERIC'
            );
        }
        if ($this->query['areas_to']) {
            $meta_query_items_new[] = array(
                'key' => 'areaForOrder',
                'value' => (is_array($this->query['areas_to']) ? $this->query['areas_to'][0] : $this->query['areas_to']),
                'compare'   => '<=',
                'type' => 'NUMERIC'
            );
        }
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
            if (strpos($this->query['price_to'], '-') !== false) {
              $price_parts = explode('-', $this->query['price_to']);
              if ($price_parts[0]) {
                $meta_query_items_new[] = array(
                    'key' => 'grossPrice',
                    'value' => $price_parts[0],
                    'compare'   => '>=',
                    'type' => 'NUMERIC'
                );
              }
              if ($price_parts[1]) {
                $meta_query_items_new[] = array(
                    'key' => 'grossPrice',
                    'value' => $price_parts[1],
                    'compare'   => '<=',
                    'type' => 'NUMERIC'
                );
              }
            } else {
              $meta_query_items_new[] = array(
                  'key' => 'grossPrice',
                  'value' => (is_array($this->query['price_to']) ? $this->query['price_to'][0] : $this->query['price_to']),
                  'compare'   => '<=',
                  'type' => 'NUMERIC'
              );
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
                // $meta_query_items_new[] = array(
                //   'key' => 'price_range_from',
                //   'value' => (int) $range_seek_to,
                //   'compare'   => '<='
                // );
                // $meta_query_items_new[] = array(
                //   'key' => 'price_range_to',
                //   'value' => (int) $range_seek_from,
                //   'compare'   => '>='
                // );
                

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


                // $meta_query_items_new[] = array(
                //   array(
                //     'relation' => 'OR',
                //     array(
                //       'relation' => 'AND',
                //       array(
                //         'key' => 'price_range_from',
                //         'value' => array($range_seek_from, $range_seek_to),
                //         'compare'   => 'BETWEEN'
                //       ),
                //       array(
                //         'key' => 'price_range_to',
                //         'value' => array($range_seek_from, $range_seek_to),
                //         'compare'   => 'BETWEEN'
                //       )
                //     ),
                //     array(
                //       'key' => 'price',
                //       'value' => array($range_seek_from, $range_seek_to),
                //       'compare'   => 'BETWEEN'
                //     )
                //   )
                // );
                }
            }

            //Define custom ranges in theme and search all prices. Did this for Property One
            if ($this->query['price_range_custom'] && strpos($this->query['price_range_custom'], '-') !== false ) {
                $price_seek_parts = explode('-', $this->query['price_range_custom']);
                $range_seek_from = $price_seek_parts[0];
                $range_seek_to = $price_seek_parts[1];

                #die('billburr' . print_r($price_seek_parts));

                /* if ($range_seek_from && $range_seek_to) {
                    $meta_query_items_new[] = array(
                        'key' => 'price',
                        'value' => $price_seek_parts,
                        'compare'   => 'BETWEEN',
                        'type' => 'NUMERIC'
                    );
                } */

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



        if ($meta_query_items_new) {

            $meta_query_items_new['relation'] = 'AND';
            $args['meta_query'] = $meta_query_items_new;
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
