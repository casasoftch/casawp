<?php
  namespace casawp;

  class Archive {
    public $conversion = null;
    private $query = array();

    public function __construct(){ 
      $this->conversion = new Conversion;
      if (!is_admin()) {
        $this->setRequestParams($_GET);
        $this->setArchiveJsVars();
      }
      
      //add_action( 'wp_enqueue_scripts', array($this, 'setArchiveJsVars') );
    }



    public function setRequestParams($query){
        $w_categories = array();
        
        if (isset($query['casawp_category_s'])) {
          foreach ($query['casawp_category_s'] as $slug => $value) {
            $w_categories[] = $value;
          }
        }
        if (isset($query['casawp_category'])) {
            $w_categories[] = $query['casawp_category'];
        }
        
        $taxquery_new = array();

        if ($w_categories) {
          $taxquery_new[] =
             array(
                 'taxonomy' => 'casawp_category',
                 'terms' => $w_categories,
                 'include_children' => 1,
                 'field' => 'slug',
                 'operator'=> 'IN'
             )
          ;

        }
        
        $w_categories_not = array();
        if (isset($query['casawp_category_not_s'])) {
            foreach ($query['casawp_category_not_s'] as $slug => $value) {
                $w_categories_not[] = $value;
            }
        }
        if (isset($query['casawp_category_not'])) {
            $w_categories_not[] = $query['casawp_category_not'];
        }
        if ($w_categories_not) {
            $taxquery_new[] =
               array(
                   'taxonomy' => 'casawp_category',
                   'terms' => $w_categories_not,
                   'include_children' => 1,
                   'field' => 'slug',
                   'operator'=> 'NOT IN'
               )
            ;
        }

        $w_availability = array();
        if (isset($query['casawp_availability_s'])) {
          foreach ($query['casawp_availability_s'] as $slug => $value) {
            $w_availability[] = $value;
          }
        }
        if (isset($query['casawp_availability'])) {
            $w_availability[] = $query['casawp_availability'];
        }
        if (empty($w_availability)) {
          //reference and taken are hidden by default
          $w_availability = array('active','reserved');
        }
        if ($w_availability) {
          $taxquery_new[] =
             array(
                 'taxonomy' => 'casawp_availability',
                 'terms' => $w_availability,
                 'include_children' => 1,
                 'field' => 'slug',
                 'operator'=> 'IN'
             )
          ;
        }
        $w_availability_not = array();
        if (isset($query['casawp_availability_not_s'])) {
            foreach ($query['casawp_availability_not_s'] as $slug => $value) {
                $w_availability_not[] = $value;
            }
        }
        if (isset($query['casawp_availability_not'])) {
            $w_availability_not[] = $query['casawp_availability_not'];
        }
        if ($w_availability_not) {
            $taxquery_new[] =
               array(
                   'taxonomy' => 'casawp_availability',
                   'terms' => $w_availability_not,
                   'include_children' => 1,
                   'field' => 'slug',
                   'operator'=> 'NOT IN'
               )
            ;
        }

        $w_locations = array();
        if (isset($query['casawp_location_s'])) {
            foreach ($query['casawp_location_s'] as $slug => $value) {
                $w_locations[] = $value;
            }
        }
        if (isset($query['casawp_location'])) {
            $w_locations[] = $query['casawp_location'];
        }
        if ($w_locations) {
            $taxquery_new[] =
               array(
                   'taxonomy' => 'casawp_location',
                   'terms' => $w_locations,
                   'include_children' => 1,
                   'field' => 'slug',
                   'operator'=> 'IN'
               )
            ;
        }
        $w_locations_not = array();
        if (isset($query['casawp_location_not_s'])) {
            foreach ($query['casawp_location_not_s'] as $slug => $value) {
                $w_locations_not[] = $value;
            }
        }
        if (isset($query['casawp_location_not'])) {
            $w_locations_not[] = $query['casawp_location_not'];
        }
        if ($w_locations_not) {
            $taxquery_new[] =
               array(
                   'taxonomy' => 'casawp_location',
                   'terms' => $w_locations_not,
                   'include_children' => 1,
                   'field' => 'slug',
                   'operator'=> 'NOT IN'
               )
            ;
        }

        $w_salestypes = array();
        if (isset($query['casawp_salestype_s'])) {
          if (!is_array($query['casawp_salestype_s'])) {
            $query['casawp_salestype_s'] = array($query['casawp_salestype_s']);
          }
          foreach ($query['casawp_salestype_s'] as $slug => $value) {
            $w_salestypes[] = $value;
          }
        }
        if (isset($query['casawp_salestype'])) {
            $w_salestypes[] = $query['casawp_salestype'];
        }
        if ($w_salestypes) {
          $taxquery_new[] =
             array(
                 'taxonomy' => 'casawp_salestype',
                 'terms' => $w_salestypes,
                 'include_children' => 1,
                 'field' => 'slug',
                 'operator'=> 'IN'
             )
          ;
        }

        $posts_per_page = 2000;
        $args = array(
          'post_type' => 'casawp_property',
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

        /*wp_enqueue_script(
            'casawp_script',
            CASASYNC_PLUGIN_URL . 'plugin_assets/js/script.js',
            array( 'jquery'),
            false,
            true
        );*/

        //wp_localize_script( 'casawp_script', 'casawpParams', $script_params );
    }

    public function getArchiveLink(){
        $casawp_category_s = array();
        if ($this->getCategoryOptions()) {
            foreach ($this->getCategoryOptions() as $slug => $options) {
                if ($options['checked']) {
                    $casawp_category_s[] = $options['value'];
                }
            }
        }
        $casawp_salestype_s = array();
        if ($this->getSalestypeOptions()) {
            foreach ($this->getSalestypeOptions() as $slug => $options) {
                if ($options['checked']) {
                    $casawp_salestype_s[] = $options['value'];
                }
            }
        }
        $casawp_availability_s = array();
        if ($this->getAvailabilityOptions()) {
            foreach ($this->getAvailabilityOptions() as $slug => $options) {
                if ($options['checked']) {
                    $casawp_availability_s[] = $options['value'];
                }
            }
        }
        $casawp_location_s = array();
        if ($this->getLocationsOptions()) {
            foreach ($this->getLocationsOptions() as $slug => $options) {
                if ($options['checked']) {
                    $casawp_location_s[] = $options['value'];
                }
            }
        }

        $query = array(
            'casawp_location_s' => $casawp_location_s,
            'casawp_salestype_s' => $casawp_salestype_s,
            'casawp_availability_s' => $casawp_availability_s,
            'casawp_category_s' => $casawp_category_s,
        );


        $link = get_post_type_archive_link('casawp_property');
        if ( get_option('permalink_structure') == '' ) {
            return $link . '?'. http_build_query(array_merge($query, array("post_type" => "casawp_property")));
        } else {
            $post_type = get_post_type_object('casawp_property');
            return $link . '?'. http_build_query($query);
        }
    }

    public function getPagination(){
      global $casawp;
      return $casawp->renderArchivePagination();
    }

    public function getOrder(){
        return get_option("casawp_archive_order");
    }

    public function getOrderby(){
        return get_option("casawp_archive_orderby");
    }

    public function getCategoryOptions(){
        $categories = array();
        $categories = $this->getCorrectTaxQuery('casawp_category');
        
        $options = array();
        $terms = get_terms('casawp_category');
        foreach ($terms as $term) {
            $label = $this->conversion->casawp_convert_categoryKeyToLabel($term->slug, $term->name);
            //$options[$term->slug]['label'] = $this->conversion->casawp_convert_categoryKeyToLabel($term->name) . ' (' . $term->count . ')';
           
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
        $locations = $this->getCorrectTaxQuery('casawp_location');
        
        $options = array();
        $terms = get_terms('casawp_location');
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
        $locations = $this->getCorrectTaxQuery('casawp_location');
       
        $return = '';
        $terms_lvl1 = get_terms('casawp_location',array('parent'=>0));
        $no_child_lvl1 = '';
        $no_child_lvl2 = '';
        foreach ($terms_lvl1 as $term) {
            $terms_lvl1_has_children = false;
            
            
            $terms_lvl2 = get_terms('casawp_location',array('parent'=>$term->term_id));
            foreach ($terms_lvl2 as $term2) {
                $terms_lvl1_has_children = true;
                
                $terms_lvl3 = get_terms('casawp_location',array('parent' => $term2->term_id));
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
        $salestypes = $this->getCorrectTaxQuery('casawp_salestype');

        $terms = get_terms('casawp_salestype');
        $options = array();
        foreach ($terms as $term) {
            $options[$term->slug]['value'] = $term->slug; 
            //$options[$term->slug]['label'] = __(ucfirst($term->name)) . ' (' . $term->count . ')';
            if ($term->slug == 'buy') {
                $options[$term->slug]['label'] = __('Buy', 'casawp');
            } elseif ($term->slug == 'rent') {
                $options[$term->slug]['label'] = __('Rent', 'casawp');
            } else {
                $options[$term->slug]['label'] = ucfirst($term->name);
            }

            $options[$term->slug]['checked'] = (in_array($term->slug, $salestypes) ? 'SELECTED' : '');
        }
        return $options;
    }

    public function getAvailabilityOptions() {
        $availabilities = array();
        $availabilities = $this->getCorrectTaxQuery('casawp_availability');

        $terms = get_terms('casawp_availability');
        $options = array();
        foreach ($terms as $term) {
            $options[$term->slug]['value'] = $term->slug; 
            //$options[$term->slug]['label'] = __(ucfirst($term->name)) . ' (' . $term->count . ')';
            if ($term->slug == 'active') {
                $options[$term->slug]['label'] = __('Active', 'casawp');
            } elseif ($term->slug == 'reference') {
                $options[$term->slug]['label'] = __('Reference', 'casawp');
            } elseif ($term->slug == 'reserved') {
                $options[$term->slug]['label'] = __('Reserved', 'casawp');
            } elseif ($term->slug == 'taken') {
                $options[$term->slug]['label'] = __('Taken', 'casawp');
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


    public function getFilterForm($size = 'large', $wrapper_class = 'casawp-filterform-wrap', $title = false){ //'Erweiterte Suche'




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
                $title = __('Advanced Search', 'casawp');
            }
            $size = ($size == 'large') ? ('large') : ('small');
            $return .=  '<div class="' . $wrapper_class . ' ' . $size . '">';
            $return .=  '<h3>' . $title . '</h3>';
            $return .= '<form action="' .  get_post_type_archive_link( 'casawp_property' ) . '" class="casawp-filterform">';

            $return .= ($size == 'small') ? '<div class="wrap">' : null;
            //if permalinks are off
            if ( get_option('permalink_structure') == '' ) {
                $return .= '<input type="hidden" name="post_type" value="casawp_property" />';
            }

            $salestype_options = $this->getSalestypeOptions();
            if(count($salestype_options) > 1) {
                $return .= '<select name="casawp_salestype_s[]" multiple class="casawp_multiselect chosen-select" data-placeholder="' . __('Choose offer','casawp') . '">';
                foreach ($salestype_options as $option) {
                    $return .= "<option value='" . $option['value'] . "' " . $option['checked'] . ">" . $option['label'] . "</option>";
                }
                $return .= '</select>';
            }

            $return .= '<select name="casawp_category_s[]" multiple class="casawp_multiselect chosen-select" data-placeholder="' . __('Choose category','casawp') . '">';
            $cat_options = $this->getCategoryOptions();

            usort($cat_options, array($this, "labelSort"));
            foreach ($cat_options as $option) {
                $return .= "<option value='" . $option['value'] . "' " . $option['checked'] . ">" . $option['label'] . "</option>";

            }
            $return .= '</select>';
            
            $return .= '<select name="casawp_location_s[]" multiple class="casawp_multiselect chosen-select" data-placeholder="' . __('Choose locality','casawp') . '">';
            $return .= $this->getLocationsOptionsHyr();
            $return .= '</select>';

            $availability_options = $this->getAvailabilityOptions();
            if(count($availability_options) > 1) {
                $return .= '<select name="casawp_availability_s[]" multiple class="hidden" data-placeholder="' . __('Choose availability','casawp') . '">';
                foreach ($availability_options as $option) {
                    $return .= "<option value='" . $option['value'] . "' " . $option['checked'] . ">" . $option['label'] . "</option>";
                }
                $return .= '</select>';
            }
            $return .= ($size == 'small') ? '</div>' : null;

            $return .= '<input class="casawp-filterform-button" type="submit" value="' . __('Search','casawp') . '" />';
            $return .= '</form>';
            $return .= '<div class="clearfix"></div>';
            $return .= '</div>';
        }
        return $return;
    }
  }
