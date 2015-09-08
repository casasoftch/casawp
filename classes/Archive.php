<?php
  namespace CasaSync;

  class Archive {
    public $conversion = null;
    private $query = array();

    public function __construct(){ 
      $this->conversion = new Conversion;
      $this->setRequestParams($_GET);
      $this->setArchiveJsVars();
      
      //add_action( 'wp_enqueue_scripts', array($this, 'setArchiveJsVars') );
    }



    public function setRequestParams($query){
        $w_categories = array();
        
        if (isset($query['casasync_category_s'])) {
          foreach ($query['casasync_category_s'] as $slug => $value) {
            $w_categories[] = $value;
          }
        }
        if (isset($query['casasync_category'])) {
            $w_categories[] = $query['casasync_category'];
        }
        
        $taxquery_new = array();

        if ($w_categories) {
          $taxquery_new[] =
             array(
                 'taxonomy' => 'casasync_category',
                 'terms' => $w_categories,
                 'include_children' => 1,
                 'field' => 'slug',
                 'operator'=> 'IN'
             )
          ;

        }
        
        $w_categories_not = array();
        if (isset($query['casasync_category_not_s'])) {
            foreach ($query['casasync_category_not_s'] as $slug => $value) {
                $w_categories_not[] = $value;
            }
        }
        if (isset($query['casasync_category_not'])) {
            $w_categories_not[] = $query['casasync_category_not'];
        }
        if ($w_categories_not) {
            $taxquery_new[] =
               array(
                   'taxonomy' => 'casasync_category',
                   'terms' => $w_categories_not,
                   'include_children' => 1,
                   'field' => 'slug',
                   'operator'=> 'NOT IN'
               )
            ;
        }

        $w_availability = array();
        if (isset($query['casasync_availability_s'])) {
          foreach ($query['casasync_availability_s'] as $slug => $value) {
            $w_availability[] = $value;
          }
        }
        if (isset($query['casasync_availability'])) {
            $w_availability[] = $query['casasync_availability'];
        }
        if (empty($w_availability)) {
          //reference and taken are hidden by default
          $w_availability = array('active','reserved');
        }
        if ($w_availability) {
          $taxquery_new[] =
             array(
                 'taxonomy' => 'casasync_availability',
                 'terms' => $w_availability,
                 'include_children' => 1,
                 'field' => 'slug',
                 'operator'=> 'IN'
             )
          ;
        }
        $w_availability_not = array();
        if (isset($query['casasync_availability_not_s'])) {
            foreach ($query['casasync_availability_not_s'] as $slug => $value) {
                $w_availability_not[] = $value;
            }
        }
        if (isset($query['casasync_availability_not'])) {
            $w_availability_not[] = $query['casasync_availability_not'];
        }
        if ($w_availability_not) {
            $taxquery_new[] =
               array(
                   'taxonomy' => 'casasync_availability',
                   'terms' => $w_availability_not,
                   'include_children' => 1,
                   'field' => 'slug',
                   'operator'=> 'NOT IN'
               )
            ;
        }

        $w_locations = array();
        if (isset($query['casasync_location_s'])) {
            foreach ($query['casasync_location_s'] as $slug => $value) {
                $w_locations[] = $value;
            }
        }
        if (isset($query['casasync_location'])) {
            $w_locations[] = $query['casasync_location'];
        }
        if ($w_locations) {
            $taxquery_new[] =
               array(
                   'taxonomy' => 'casasync_location',
                   'terms' => $w_locations,
                   'include_children' => 1,
                   'field' => 'slug',
                   'operator'=> 'IN'
               )
            ;
        }
        $w_locations_not = array();
        if (isset($query['casasync_location_not_s'])) {
            foreach ($query['casasync_location_not_s'] as $slug => $value) {
                $w_locations_not[] = $value;
            }
        }
        if (isset($query['casasync_location_not'])) {
            $w_locations_not[] = $query['casasync_location_not'];
        }
        if ($w_locations_not) {
            $taxquery_new[] =
               array(
                   'taxonomy' => 'casasync_location',
                   'terms' => $w_locations_not,
                   'include_children' => 1,
                   'field' => 'slug',
                   'operator'=> 'NOT IN'
               )
            ;
        }

        $w_salestypes = array();
        if (isset($query['casasync_salestype_s'])) {
          if (!is_array($query['casasync_salestype_s'])) {
            $query['casasync_salestype_s'] = array($query['casasync_salestype_s']);
          }
          foreach ($query['casasync_salestype_s'] as $slug => $value) {
            $w_salestypes[] = $value;
          }
        }
        if (isset($query['casasync_salestype'])) {
            $w_salestypes[] = $query['casasync_salestype'];
        }
        if ($w_salestypes) {
          $taxquery_new[] =
             array(
                 'taxonomy' => 'casasync_salestype',
                 'terms' => $w_salestypes,
                 'include_children' => 1,
                 'field' => 'slug',
                 'operator'=> 'IN'
             )
          ;
        }

        $posts_per_page = 2000;
        $args = array(
          'post_type' => 'casasync_property',
          'posts_per_page' => $posts_per_page,
          'tax_query' => $taxquery_new, 
        );

        #query_posts( $args ); #????????????????????????????????????????????????????????????
        $this->query = $args;
    }

    public function getProperties(){
        global $post;
        $properties = array();
        query_posts( $this->query );
        while ( have_posts() ) : the_post();

            $single = new Single($post, false, false, false, false);
            $properties[] = $single;
        endwhile; 
        return $properties;
    }

    public function setArchiveJsVars(){
        $script_params = array(
           'categories'   => $this->getCategoryOptions(),
           'locations'    => $this->getLocationsOptions(false),
           'salestypes'   => $this->getSalestypeOptions(),
           'availabilities'=> $this->getAvailabilityOptions(),
           'archive_link' => $this->getArchiveLink(),
           'order'        => $this->getOrder(),
           'orderby'      => $this->getOrderby()
       );

        wp_enqueue_script(
            'casasync_script',
            CASASYNC_PLUGIN_URL . 'plugin_assets/js/script.js',
            array( 'jquery'),
            false,
            true
        );

        wp_localize_script( 'casasync_script', 'casasyncParams', $script_params );
    }

    public function getArchiveLink(){
        $casasync_category_s = array();
        if ($this->getCategoryOptions()) {
            foreach ($this->getCategoryOptions() as $slug => $options) {
                if ($options['checked']) {
                    $casasync_category_s[] = $options['value'];
                }
            }
        }
        $casasync_salestype_s = array();
        if ($this->getSalestypeOptions()) {
            foreach ($this->getSalestypeOptions() as $slug => $options) {
                if ($options['checked']) {
                    $casasync_salestype_s[] = $options['value'];
                }
            }
        }
        $casasync_availability_s = array();
        if ($this->getAvailabilityOptions()) {
            foreach ($this->getAvailabilityOptions() as $slug => $options) {
                if ($options['checked']) {
                    $casasync_availability_s[] = $options['value'];
                }
            }
        }
        $casasync_location_s = array();
        if ($this->getLocationsOptions()) {
            foreach ($this->getLocationsOptions() as $slug => $options) {
                if ($options['checked']) {
                    $casasync_location_s[] = $options['value'];
                }
            }
        }

        $query = array(
            'casasync_location_s' => $casasync_location_s,
            'casasync_salestype_s' => $casasync_salestype_s,
            'casasync_availability_s' => $casasync_availability_s,
            'casasync_category_s' => $casasync_category_s,
        );


        $link = get_post_type_archive_link('casasync_property');
        if ( get_option('permalink_structure') == '' ) {
            return $link . '?'. http_build_query(array_merge($query, array("post_type" => "casasync_property")));
        } else {
            $post_type = get_post_type_object('casasync_property');
            return $link . '?'. http_build_query($query);
        }
    }

    public function getPagination(){
        global $wp_query;

        if ( $GLOBALS['wp_query']->max_num_pages < 2 ) {
            return;
        }

        $paged        = get_query_var( 'paged' ) ? intval( get_query_var( 'paged' ) ) : 1;
        $pagenum_link = html_entity_decode( get_pagenum_link() );
        $query_args   = array();
        $url_parts    = explode( '?', $pagenum_link );

        if ( isset( $url_parts[1] ) ) {
            wp_parse_str( $url_parts[1], $query_args );
        }

        $pagenum_link = remove_query_arg( array_keys( $query_args ), $pagenum_link );
        $pagenum_link = trailingslashit( $pagenum_link ) . '%_%';

        $format  = $GLOBALS['wp_rewrite']->using_index_permalinks() && ! strpos( $pagenum_link, 'index.php' ) ? 'index.php/' : '';
        $format .= $GLOBALS['wp_rewrite']->using_permalinks() ? user_trailingslashit( 'page/%#%', 'paged' ) : '?paged=%#%';

        // Set up paginated links.
        $links = paginate_links( array(
            'base'     => $pagenum_link,
            'format'   => $format,
            'total'    => $GLOBALS['wp_query']->max_num_pages,
            'current'  => $paged,
            'mid_size' => 1,
            'add_args' => $query_args,
            'prev_text' => '&laquo;',
            'next_text' => '&raquo;',
            'type' => 'list',
        ) );

        if ( $links ) {
            return '<div class="casasync-pagination ' . (get_option('casasync_load_css', 'bootstrapv3') == 'bootstrapv2' ? 'pagination' : '') . '">' . $links . '</div>';
        }



      $total_pages = $wp_query->max_num_pages;
      if ($total_pages > 1) {
        $current_page = max(1, get_query_var('paged'));
        if($current_page) {
            //TODO: prev/next These dont work yet!
            $prev_page = '<li class="disabled"><a href="#">&laquo;</span></a></li>';
            $next_page = '<li class="disabled"><a href="#">&raquo;</a></li>';
            $i = 0;
            $return = '<ul class="casasync-pagination">';
            $return .= $prev_page;
            while ($i < $total_pages) {
                $i++;
                if ($current_page == $i) {
                    $return .= '<li><a href="#"><span>' . $i . '<span class="sr-only">(current)</span></span></a></li>';
                } else {
                    $return .= '<li><a href="' . get_pagenum_link($i) . '">' . $i . '</a></li>';
              }
            }
            $return .= $next_page;
            $return .= '</ul>';
            return $return;
        }
      }
    }

    public function getOrder(){
        return get_option("casasync_archive_order");
    }

    public function getOrderby(){
        return get_option("casasync_archive_orderby");
    }

    public function getCategoryOptions(){
        $categories = array();
        $categories = $this->getCorrectTaxQuery('casasync_category');
        
        $options = array();
        $terms = get_terms('casasync_category');
        foreach ($terms as $term) {
            $label = $this->conversion->casasync_convert_categoryKeyToLabel($term->slug, $term->name);
            //$options[$term->slug]['label'] = $this->conversion->casasync_convert_categoryKeyToLabel($term->name) . ' (' . $term->count . ')';
           
            if ($label) {
                $options[$term->slug]['value'] = $term->slug; 
                $options[$term->slug]['label'] = $label;
                $options[$term->slug]['checked'] = (in_array($term->slug, $categories) ? 'SELECTED' : '');
            }
        }
        return $options;
    }
    public function getLocationsOptions($return_unchecked = true){
        $locations = array();
        $locations = $this->getCorrectTaxQuery('casasync_location');
        
        $options = array();
        $terms = get_terms('casasync_location');
        foreach ($terms as $term) {
            if (in_array($term->slug, $locations) || $return_unchecked ) {
                $options[$term->slug]['value'] = $term->slug; 
                $options[$term->slug]['label'] = $term->name;
                $options[$term->slug]['checked'] = (in_array($term->slug, $locations) ? 'SELECTED' : '');
            }
        }
        return $options;
    }
    public function getLocationsOptionsHyr() {
        $locations = array();
        $locations = $this->getCorrectTaxQuery('casasync_location');
       
        $return = '';
        $terms_lvl1 = get_terms('casasync_location',array('parent'=>0));
        $no_child_lvl1 = '';
        $no_child_lvl2 = '';
        foreach ($terms_lvl1 as $term) {
            $terms_lvl1_has_children = false;
            
            
            $terms_lvl2 = get_terms('casasync_location',array('parent'=>$term->term_id));
            foreach ($terms_lvl2 as $term2) {
                $terms_lvl1_has_children = true;
                
                $terms_lvl3 = get_terms('casasync_location',array('parent' => $term2->term_id));
                $store = '';
                $terms_lvl2_has_children = false;
                foreach ($terms_lvl3 as $term3) {
                    $terms_lvl2_has_children = true;
                    //$store .= "<option class='lvl3' value='" . $term3->slug . "' " . (in_array($term3->slug, $locations) ? 'SELECTED' : '') . ">" . '' . $term3->name . ' (' . $term3->count . ')' . "</option>";
                    $store .= "<option class='lvl3' value='" . $term3->slug . "' " . (in_array($term3->slug, $locations) ? 'SELECTED' : '') . ">" . '' . $term3->name . "</option>";
                }
                if ($terms_lvl2_has_children) {
                    $return .= "<optgroup label='" . $term2->name . "'>";
                    $return .= $store;
                    $return .= "</optgroup>";
                } else {
                    //must be another country?
                    $otherCountry[$term->name][] = $term2;
                }
            }

            //list all other countries in seperate optgroup
            if (isset($otherCountry)) {
                foreach ( $otherCountry as $countryCode => $country ) {
                    $return .= "<optgroup label='" . $this->conversion->countrycode_to_countryname($countryCode)  . "'>";
                    foreach ( $country as $location ) {
                        //$return .= "<option class='lvl2' value='" . $location->slug . "' " . (in_array($location->slug, $locations) ? 'SELECTED' : '') . ">" . '' . $location->name . ' (' . $location->count . ')' . "</option>";      
                        $return .= "<option class='lvl2' value='" . $location->slug . "' " . (in_array($location->slug, $locations) ? 'SELECTED' : '') . ">" . '' . $location->name . "</option>";      
                    }
                    $return .= "</optgroup>";
                }
            }   
            unset($otherCountry);

            if (!$terms_lvl1_has_children) {
                //$no_child_lvl1 .=  "<option value='" . $term->slug . "' " . (in_array($term->slug, $locations) ? 'SELECTED' : '') . ">" . $term->name . ' (' . $term->count . ')' . "</option>";
                $no_child_lvl1 .=  "<option value='" . $term->slug . "' " . (in_array($term->slug, $locations) ? 'SELECTED' : '') . ">" . $term->name . "</option>";

            }
        }
        if ($no_child_lvl1) {
            $return .= "<optgroup label='Sonstige Ortschaften'>";
            $return .= $no_child_lvl1;
            $return .= "</optgroup>";
        }
        return $return;
    }
    public function getSalestypeOptions() {
        $salestypes = array();
        $salestypes = $this->getCorrectTaxQuery('casasync_salestype');

        $terms = get_terms('casasync_salestype');
        $options = array();
        foreach ($terms as $term) {
            $options[$term->slug]['value'] = $term->slug; 
            //$options[$term->slug]['label'] = __(ucfirst($term->name)) . ' (' . $term->count . ')';
            if ($term->slug == 'buy') {
                $options[$term->slug]['label'] = __('Buy', 'casasync');
            } elseif ($term->slug == 'rent') {
                $options[$term->slug]['label'] = __('Rent', 'casasync');
            } else {
                $options[$term->slug]['label'] = ucfirst($term->name);
            }

            $options[$term->slug]['checked'] = (in_array($term->slug, $salestypes) ? 'SELECTED' : '');
        }
        return $options;
    }

    public function getAvailabilityOptions() {
        $availabilities = array();
        $availabilities = $this->getCorrectTaxQuery('casasync_availability');

        $terms = get_terms('casasync_availability');
        $options = array();
        foreach ($terms as $term) {
            $options[$term->slug]['value'] = $term->slug; 
            //$options[$term->slug]['label'] = __(ucfirst($term->name)) . ' (' . $term->count . ')';
            if ($term->slug == 'active') {
                $options[$term->slug]['label'] = __('Active', 'casasync');
            } elseif ($term->slug == 'reference') {
                $options[$term->slug]['label'] = __('Reference', 'casasync');
            } elseif ($term->slug == 'reserved') {
                $options[$term->slug]['label'] = __('Reserved', 'casasync');
            } elseif ($term->slug == 'taken') {
                $options[$term->slug]['label'] = __('Taken', 'casasync');
            } else {
                $options[$term->slug]['label'] = ucfirst($term->name);
            }

            $options[$term->slug]['checked'] = (in_array($term->slug, $availabilities) ? 'SELECTED' : '');
        }
        return $options;
    }

    public function getCorrectTaxQuery($taxonomy) {
      global $wp_query;
      $query = array();

      // get params from $wp_query or $this->query
      if (empty($this->query["tax_query"])) {
        $tax_query = $wp_query->tax_query->queries;
      } else {
        $tax_query = $this->query["tax_query"];
      }

      foreach ($tax_query as $tax_query) {
          if ($tax_query['taxonomy'] == $taxonomy) {
              $query = $tax_query['terms'];
          }
      }

      return $query;
    }

    public function getStickyProperties(){
        if (get_option( 'sticky_posts' )) {
            global $wp_query;
            $new_query = $wp_query->query_vars;
            $new_query['post__in'] = get_option( 'sticky_posts' );
            $new_query['post__not_in'] = array();
            $the_query = new \WP_Query($new_query);
            return $the_query;
        } else {
            return false;
        }
    }

    public function labelSort($a, $b){
      return strcmp($a["label"], $b["label"]);
    }


    public function getFilterForm($size = 'large', $wrapper_class = 'casasync-filterform-wrap', $title = false){ //'Erweiterte Suche'




        $return = '';

        //in my area causes disabled filter
        $mylng = (float) (isset($_GET['my_lng']) ? $_GET['my_lng'] : null);
        $mylat = (float) (isset($_GET['my_lat']) ? $_GET['my_lat'] : null);
        $radiusKm = (int) (isset($_GET['radius_km']) ? $_GET['radius_km'] : 10);
        if ($mylng && $mylat) {
            $return .= '<p class="alert alert-info">Suchresultate für Objekte in Ihrer Umgebung.</p>';
            $return .= '<a href="/immobilien" class="btn btn-primary btn-block">Weitere Objekte Suchen</a>';
        } else {

            //normal filter
            global $wp_query;
            if (!$title) {
                $title = __('Advanced Search', 'casasync');
            }
            $size = ($size == 'large') ? ('large') : ('small');
            $return .=  '<div class="' . $wrapper_class . ' ' . $size . '">';
            $return .=  '<h3>' . $title . '</h3>';
            $return .= '<form action="' .  get_post_type_archive_link( 'casasync_property' ) . '" class="casasync-filterform">';

            $return .= ($size == 'small') ? '<div class="wrap">' : null;
            //if permalinks are off
            if ( get_option('permalink_structure') == '' ) {
                $return .= '<input type="hidden" name="post_type" value="casasync_property" />';
            }

            $salestype_options = $this->getSalestypeOptions();
            if(count($salestype_options) > 1) {
                $return .= '<select name="casasync_salestype_s[]" multiple class="casasync_multiselect chosen-select" data-placeholder="' . __('Choose offer','casasync') . '">';
                foreach ($salestype_options as $option) {
                    $return .= "<option value='" . $option['value'] . "' " . $option['checked'] . ">" . $option['label'] . "</option>";
                }
                $return .= '</select>';
            }

            $return .= '<select name="casasync_category_s[]" multiple class="casasync_multiselect chosen-select" data-placeholder="' . __('Choose category','casasync') . '">';
            $cat_options = $this->getCategoryOptions();

            usort($cat_options, array($this, "labelSort"));
            foreach ($cat_options as $option) {
                $return .= "<option value='" . $option['value'] . "' " . $option['checked'] . ">" . $option['label'] . "</option>";

            }
            $return .= '</select>';
            
            $return .= '<select name="casasync_location_s[]" multiple class="casasync_multiselect chosen-select" data-placeholder="' . __('Choose locality','casasync') . '">';
            $return .= $this->getLocationsOptionsHyr();
            $return .= '</select>';

            $availability_options = $this->getAvailabilityOptions();
            if(count($availability_options) > 1) {
                $return .= '<select name="casasync_availability_s[]" multiple class="hidden" data-placeholder="' . __('Choose availability','casasync') . '">';
                foreach ($availability_options as $option) {
                    $return .= "<option value='" . $option['value'] . "' " . $option['checked'] . ">" . $option['label'] . "</option>";
                }
                $return .= '</select>';
            }
            $return .= ($size == 'small') ? '</div>' : null;

            $return .= '<input class="casasync-filterform-button" type="submit" value="' . __('Search','casasync') . '" />';
            $return .= '</form>';
            $return .= '<div class="clearfix"></div>';
            $return .= '</div>';
        }
        return $return;
    }
  }
