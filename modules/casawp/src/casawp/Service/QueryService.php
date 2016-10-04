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
            'locations' => array(),
            'salestypes' => array(),
            'availabilities' => array('active'),
            'categories_not' => array(),
            'locations_not' => array(),
            'salestypes_not' => array(),
            'availabilities_not' => array(),
            'features' => array(),
            'my_lng' => null,
            'my_lat' => null,
            'radius_km' => 10,
            'projectunit_id' => null
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
            switch ($key) {
                case 'casawp_category_s':
                case 'casawp_category':
                case 'categories':
                case 'casawp_location_s':
                case 'casawp_location':
                case 'locations':
                case 'casawp_salestype_s':
                case 'casawp_salestype':
                case 'salestypes':
                case 'casawp_availability_s':
                case 'casawp_availability':
                case 'availabilities':
                case 'casawp_category_not_s':
                case 'casawp_category_not':
                case 'categories_not':
                case 'casawp_location_not_s':
                case 'casawp_location_not':
                case 'locations_not':
                case 'casawp_salestype_not_s':
                case 'casawp_salestype_not':
                case 'salestypes_not':
                case 'casawp_availability_s_not':
                case 'casawp_availability_not':
                case 'availabilities_not':
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
            case 'menu_order':
                $args['orderby'] = 'menu_order date';
                break;
            case 'casawp_referenceId':
                $args['meta_key'] = 'referenceId';
                $args['orderby'] = 'meta_value';
                break;
            case 'date':
            default:
                $args['orderby'] = 'date';
                break;
        }


        $meta_query_items_new = array();
        if ($this->query['projectunit_id']) {
            $meta_query_items_new[] = array(
                'key' => 'projectunit_id',
                'value' => $this->query['projectunit_id'],
                'compare'   => '='
            );
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
        if ($this->query['locations']) {
            $taxquery_new[] = array(
                'taxonomy' => 'casawp_location',
                'terms' => $this->query['locations'],
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
        if ($this->query['locations_not']) {
            $taxquery_new[] = array(
                'taxonomy' => 'casawp_location',
                'terms' => $this->query['locations_not'],
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
            if (is_tax('casawp_location')) {
                $this->query['locations'] = array(get_query_var( 'casawp_location' ));
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
