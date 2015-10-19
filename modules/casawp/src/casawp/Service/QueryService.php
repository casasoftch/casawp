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
            'features' => array(),
            'my_lng' => null,
            'my_lat' => null,
            'radius_km' => 10,
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
    public function getQuery(){
        return $this->query;
    }

    private function interpretRequest(){
        $r_query = $_GET;
        $query = array();
        foreach ($r_query as $key => $value) {
            switch ($key) {
                case 'casawp_category_s':
                case 'casawp_category':
                case 'categories':
                    $query['categories'] = (is_array($value) ? $value : array($value));
                    break;
                case 'casawp_location_s':
                case 'casawp_location':
                case 'locations':
                    $query['locations'] = (is_array($value) ? $value : array($value));
                    break;
                case 'casawp_salestype_s':
                case 'casawp_salestype':
                case 'salestypes':
                    $query['salestypes'] = (is_array($value) ? $value : array($value));
                    break;
                case 'casawp_availability_s':
                case 'casawp_availability':
                case 'availabilities':
                    $query['availabilities'] = (is_array($value) ? $value : array($value));
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
        }
        
        switch ($this->query['orderby']) {
            case 'title':
                $args['orderby'] = 'title';
                break;
            case 'location':
                $args['meta_key'] = 'casawp_property_address_locality';
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
                $args['meta_key'] = 'casawp_referenceId';
                $args['orderby'] = 'meta_value';
                break;
            case 'date':
            default:
                $args['orderby'] = 'date';
                break;
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
                                * cos( radians( longitude.meta_value ) 
                                - radians(" . $mylng . ") ) 
                                + sin( radians(" . $mylat . ") ) 
                                * sin( radians( latitude.meta_value ) ) ) ) <= " . $radiusKm . ") ";  
              
        }

        return $where;  
    }

    public function nearmejoin($join){
        global $wpdb;
        $join .= "LEFT JOIN $wpdb->postmeta AS latitude ON $wpdb->posts.ID = latitude.post_id AND latitude.meta_key = 'casawp_property_geo_latitude' ";
        $join .= "LEFT JOIN $wpdb->postmeta AS longitude ON $wpdb->posts.ID = longitude.post_id AND longitude.meta_key = 'casawp_property_geo_longitude' ";
        return $join;
    }

    public function getArrayCopy(){
        return $this->getQuery();
    }

    public function exchangeArray(){
        return $this->getQuery();
    }

}