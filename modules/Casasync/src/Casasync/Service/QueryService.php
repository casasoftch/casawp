<?php
namespace Casasync\Service;

class QueryService{
   
    private $defaultQuery = array();

    public function __construct(){
        $this->defaultQuery = array(
            'post-type' => 'casasync_property',
            'posts_per_page' => get_option('posts_per_page', 10),
            'order' => get_option('casasync_archive_order', 'DESC'),
            'ignore_sticky_posts' => 0,
            'post__not_in' => null,
            'orderby' => get_option('casasync_archive_orderby', 'date'),
            'categories' => array(),
            'locations' => array(),
            'salestypes' => array(),
            'availabilities' => array(),
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
                case 'casasync_category_s':
                case 'casasync_category':
                case 'categories':
                    $query['categories'] = (is_array($value) ? $value : array($value));
                    break;
                case 'casasync_location_s':
                case 'casasync_location':
                case 'locations':
                    $query['locations'] = (is_array($value) ? $value : array($value));
                    break;
                case 'casasync_salestype_s':
                case 'casasync_salestype':
                case 'salestypes':
                    $query['salestypes'] = (is_array($value) ? $value : array($value));
                    break;
                case 'casasync_availability_s':
                case 'casasync_availability':
                case 'availabilities':
                    $query['availabilities'] = (is_array($value) ? $value : array($value));
                    break;
                
                default:
                    $query[$key] = $value;
                    break;
            }
        }

        //tax_queries override this
        /*if (is_tax('casasync_category')) {
            $query['categories'] = array(get_query_var( 'casasync_category' ));
        }
        if (is_tax('casasync_location')) {
            $query['locations'] = array(get_query_var( 'casasync_location' ));
        }
        if (is_tax('casasync_salestype')) {
            $query['locations'] = array(get_query_var( 'casasync_salestype' ));
        }
        if (is_tax('casasync_availability')) {
            $query['locations'] = array(get_query_var( 'casasync_availability' ));
        }*/

        return $query;
    }

   
    public function applyToWpQuery($query){
    	if ($query->is_main_query()) {
            if (is_tax('casasync_salestype') || is_tax('casasync_availability') || is_tax('casasync_category') || is_tax('casasync_location') || is_post_type_archive('casasync_property')) {
                $query->set('post-type', $this->query['post-type']);
                $query->set('posts_per_page', $this->query['posts_per_page']);
                $query->set('order', $this->query['order']);

                $query->set('ignore_sticky_posts',$this->query['ignore_sticky_posts']);

                if (get_option( 'casasync_hide_sticky_properties_in_main')) {
                    $query->set('post__not_in', get_option( 'sticky_posts' ));
                }
                
                switch ($this->query['orderby']) {
                    case 'title':
                        $query->set('orderby', 'title');
                        break;
                    case 'location':
                        $query->set('meta_key', 'casasync_property_address_locality');
                        $query->set('orderby', 'meta_value');
                        break;
                    case 'price':
                        $query->set('meta_key', 'priceForOrder');
                        $query->set('orderby', 'meta_value');
                        break;
                    case 'menu_order':
                        $query->set('orderby', 'menu_order date');
                        break;
                    case 'casasync_referenceId':
                        $query->set('meta_key', 'casasync_referenceId');
                        $query->set('orderby', 'meta_value');
                        break;
                    case 'date':
                    default:
                        $query->set('orderby', 'date');
                        break;
                }

                //$query->set('orderby', 'date');
                //$query->set('order', 'ASC');

                $taxquery_new = array();

                if ($this->query['categories']) {
                    $taxquery_new[] = array(
                        'taxonomy'         => 'casasync_category',
                        'terms'            => $this->query['categories'],
                        'include_children' => 1,
                        'field'            => 'slug',
                        'operator'         => 'IN'
                    );
                }
                if ($this->query['locations']) {
                    $taxquery_new[] = array(
                        'taxonomy' => 'casasync_location',
                        'terms' => $this->query['locations'],
                        'include_children' => 1,
                        'field' => 'slug',
                        'operator'=> 'IN'
                    );
                }

                if ($this->query['salestypes']) {
                    $taxquery_new[] = array(
                        'taxonomy' => 'casasync_salestype',
                        'terms' => $this->query['salestypes'],
                        'include_children' => 1,
                        'field' => 'slug',
                        'operator'=> 'IN'
                     );
                }

                if ($this->query['availabilities']) {
                    $taxquery_new[] = array(
                        'taxonomy' => 'casasync_availability',
                        'terms' => $this->query['availabilities'],
                        'include_children' => 1,
                        'field' => 'slug',
                        'operator'=> 'IN'
                     );
                }

                if ($taxquery_new) {
                    $query->set('tax_query', $taxquery_new);
                }

                add_filter( 'posts_where' , array($this, 'nearmefilter') );    

            }
        }

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
        $join .= "LEFT JOIN $wpdb->postmeta AS latitude ON $wpdb->posts.ID = latitude.post_id AND latitude.meta_key = 'casasync_property_geo_latitude' ";
        $join .= "LEFT JOIN $wpdb->postmeta AS longitude ON $wpdb->posts.ID = longitude.post_id AND longitude.meta_key = 'casasync_property_geo_longitude' ";
        return $join;
    }

    public function getArrayCopy(){
        return $this->getQuery();
    }

    public function exchangeArray(){
        return $this->getQuery();
    }

}