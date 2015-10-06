<?php
namespace Casasync\Service;

class QueryService{
   

    public function __construct(){
    
    }

    public function filter($query){
    	if ($query->is_main_query()) {
            if (is_tax('casasync_salestype') || is_tax('casasync_availability') || is_tax('casasync_category') || is_tax('casasync_location') || is_post_type_archive('casasync_property')) {
                $query->set('post-type', "casasync_property");

                $posts_per_page = get_option('posts_per_page', 10);
                $query->set('posts_per_page', $posts_per_page);
                $query->set('order', get_option('casasync_archive_order', 'DESC'));

                $query->set('ignore_sticky_posts',0);
                if (get_option( 'casasync_hide_sticky_properties_in_main')) {
                    $query->set('post__not_in', get_option( 'sticky_posts' ));
                }
                
                switch (get_option('casasync_archive_orderby', 'date')) {
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

                if ((isset($_GET['casasync_category_s']) && is_array($_GET['casasync_category_s']) )) {
                    $categories = $_GET['casasync_category_s'];
                } elseif (isset($_GET['casasync_category_s'])) {
                    $categories = array($_GET['casasync_category_s']);
                } elseif(is_tax('casasync_category')) {
                    $categories = array(get_query_var( 'casasync_category' ));
                } else {
                    $categories = array();
                }
                if ($categories) {
                    $taxquery_new[] = array(
                        'taxonomy'         => 'casasync_category',
                        'terms'            => $categories,
                        'include_children' => 1,
                        'field'            => 'slug',
                        'operator'         => 'IN'
                    );
                }
                if ((isset($_GET['casasync_location_s']) && is_array($_GET['casasync_location_s']) )) {
                    $locations = $_GET['casasync_location_s'];
                } elseif (isset($_GET['casasync_location_s'])) {
                    $locations = array($_GET['casasync_location_s']);
                } elseif(is_tax('casasync_location')) {
                    $locations = array(get_query_var( 'casasync_location' ));
                } else {
                    $locations = array();
                }
                if ($locations) {
                    $taxquery_new[] = array(
                        'taxonomy' => 'casasync_location',
                        'terms' => $locations,
                        'include_children' => 1,
                        'field' => 'slug',
                        'operator'=> 'IN'
                    );
                }

                $salestypes = array();
                if ((isset($_GET['casasync_salestype_s']) && is_array($_GET['casasync_salestype_s']) )) {
                    $salestypes = $_GET['casasync_salestype_s'];
                } elseif (isset($_GET['casasync_salestype_s'])) {
                    $salestypes = array($_GET['casasync_salestype_s']);
                } elseif(is_tax('casasync_salestype')) {
                    $salestypes = array(get_query_var( 'casasync_salestype' ));
                } else {
                    //$salestypes = array('rent','buy');
                }
                if ($salestypes) {
                    $taxquery_new[] = array(
                        'taxonomy' => 'casasync_salestype',
                        'terms' => $salestypes,
                        'include_children' => 1,
                        'field' => 'slug',
                        'operator'=> 'IN'
                     );
                }


                $availabilities = array();
                if ((isset($_GET['casasync_availability_s']) && is_array($_GET['casasync_availability_s']) )) {
                    $availabilities = $_GET['casasync_availability_s'];
                } elseif (isset($_GET['casasync_availability_s'])) {
                    $availabilities = array($_GET['casasync_availability_s']);
                } elseif(is_tax('casasync_availability')) {
                    $availabilities = array(get_query_var( 'casasync_availability' ));
                } else {
                    //reference and taken are hidden by default
                    $availabilities = array('active','reserved');
                }
                if ($availabilities) {
                    $taxquery_new[] = array(
                        'taxonomy' => 'casasync_availability',
                        'terms' => $availabilities,
                        'include_children' => 1,
                        'field' => 'slug',
                        'operator'=> 'IN'
                     );
                }

                if ($taxquery_new) {
                    $query->set('tax_query', $taxquery_new);
                }

                $this->tax_query = $taxquery_new;
                
                add_filter( 'posts_where' , array($this, 'nearmefilter') );    

            }
        }

	    return $query;
   	}

   	public function nearmefilter($where){
        $mylng = (float) (isset($_GET['my_lng']) ? $_GET['my_lng'] : null);
        $mylat = (float) (isset($_GET['my_lat']) ? $_GET['my_lat'] : null);
        $radiusKm = (int) (isset($_GET['radius_km']) ? $_GET['radius_km'] : 10);
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

}