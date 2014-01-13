<?php
  namespace CasaSync;

  class Archive {
    public $conversion = null;

    public function __construct(){ 
      $this->conversion = new Conversion;
      $this->setArchiveJsVars();
      //add_action( 'wp_enqueue_scripts', array($this, 'setArchiveJsVars') );
    }

    public function setArchiveJsVars(){
        $script_params = array(
           'categories'   => $this->getCategoryOptions(),
           'locations'    => $this->getLocationsOptions(false),
           'salestypes'   => $this->getSalestypeOptions(),
           'archive_link' => $this->getArchiveLink()
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
            'add_args' => array_map( 'urlencode', $query_args ),
            'prev_text' => '&laquo;',
            'next_text' => '&raquo;',
            'type' => 'list',
            'add_args' => false, //TODO: add filter arguments
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

    public function getCategoryOptions(){
        global $wp_query;
        $categories = array();
        foreach ($wp_query->tax_query->queries as $tax_query) {
            if ($tax_query['taxonomy'] == 'casasync_category') {
                $categories = $tax_query['terms'];
            }
        }
        $options = array();
        $terms = get_terms('casasync_category');
        foreach ($terms as $term) {
            $options[$term->slug]['value'] = $term->slug; 
            //$options[$term->slug]['label'] = $this->conversion->casasync_convert_categoryKeyToLabel($term->name) . ' (' . $term->count . ')';
            $options[$term->slug]['label'] = $this->conversion->casasync_convert_categoryKeyToLabel($term->name);
            $options[$term->slug]['checked'] = (in_array($term->slug, $categories) ? 'SELECTED' : '');
        }
        return $options;
    }
    public function getLocationsOptions($return_unchecked = true){
        global $wp_query;
        $categories = array();
        foreach ($wp_query->tax_query->queries as $tax_query) {
            if ($tax_query['taxonomy'] == 'casasync_location') {
                $categories = $tax_query['terms'];
            }
        }
        $options = array();
        $terms = get_terms('casasync_location');
        foreach ($terms as $term) {
            if (in_array($term->slug, $categories) || $return_unchecked ) {
                $options[$term->slug]['value'] = $term->slug; 
                $options[$term->slug]['label'] = $this->conversion->casasync_convert_categoryKeyToLabel($term->name);
                $options[$term->slug]['checked'] = (in_array($term->slug, $categories) ? 'SELECTED' : '');
            }
        }
        return $options;
    }
    public function getLocationsOptionsHyr() {
        global $wp_query;
        if (isset($wp_query->query_vars['casasync_location'])) {
            $locations = explode(',', $wp_query->query_vars['casasync_location']);
        } else {
            $locations = array();
        }
        $locations = array();
        foreach ($wp_query->tax_query->queries as $tax_query) {
            if ($tax_query['taxonomy'] == 'casasync_location') {
                $locations = $tax_query['terms'];
            }
        }

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
                    $return .= "<optgroup label='" . $this->conversion->countrycode_to_countryname($countryCode)  . "''>";
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
        global $wp_query;
        $casasync_salestype = get_terms('casasync_salestype');
        foreach ($casasync_salestype as $term) {            
            if (isset($wp_query->query_vars['casasync_salestype'])) {
                $cur_basis = explode(',', $wp_query->query_vars['casasync_salestype'] );
            } else {
                $cur_basis = array();
            }
            //if (in_array($term->slug, $cur_basis)) {
            //    $return .= '<input type="hidden" name="casasync_salestype_s[]" value="'.$term->slug.'" /> ';
            //}
        }
        $salestypes = array();
        foreach ($wp_query->tax_query->queries as $tax_query) {
            if ($tax_query['taxonomy'] == 'casasync_salestype') {
                $salestypes = $tax_query['terms'];
            }
        }
        $terms = get_terms('casasync_salestype');
        $options = array();
        if (count($terms) > 1) {
            foreach ($terms as $term) {
                $options[$term->slug]['value'] = $term->slug; 
                //$options[$term->slug]['label'] = __(ucfirst($term->name)) . ' (' . $term->count . ')';
                $options[$term->slug]['label'] = __(ucfirst($term->name), 'casasync');
                $options[$term->slug]['checked'] = (in_array($term->slug, $salestypes) ? 'SELECTED' : '');
            }
        }
        return $options;
    }

    public function getFilterForm($size = 'large', $wrapper_class = 'casasync-filterform-wrap', $title = 'Erweiterte Suche'){
        global $wp_query;
        $size = ($size == 'large') ? ('large') : ('small');
        $return =  '<div class="' . $wrapper_class . ' ' . $size . '">';
        $return .=  '<h3>' . $title . '</h3>';
        $return .= '<form action="' .  get_post_type_archive_link( 'casasync_property' ) . '" class="casasync-filterform">';
        //if permalinks are off
        if ( get_option('permalink_structure') == '' ) {
            $return .= '<input type="hidden" name="post_type" value="casasync_property" />';
        }

        $return .= '<select name="casasync_salestype_s[]" multiple class="casasync_multiselect chosen-select" data-placeholder="' . __('Choose offer','casasync') . '">';
        $salestype_options = $this->getSalestypeOptions();
        foreach ($salestype_options as $option) {
            $return .= "<option value='" . $option['value'] . "' " . $option['checked'] . ">" . $option['label'] . "</option>";
        }
        $return .= '</select>';

        $return .= '<select name="casasync_category_s[]" multiple class="casasync_multiselect chosen-select" data-placeholder="' . __('Choose category','casasync') . '">';
        $cat_options = $this->getCategoryOptions();
        foreach ($cat_options as $option) {
            $return .= "<option value='" . $option['value'] . "' " . $option['checked'] . ">" . $option['label'] . "</option>";
        }
        $return .= '</select>';
        
        $return .= '<select name="casasync_location_s[]" multiple class="casasync_multiselect chosen-select" data-placeholder="' . __('Choose locality','casasync') . '">';
        $return .= $this->getLocationsOptionsHyr();
        $return .= '</select>';
                
        $return .= '<input class="casasync-filterform-button" type="submit" value="' . __('Search','casasync') . '" />';
        $return .= '</form>';
        $return .= '<div class="clearfix"></div>';
        $return .= '</div>';

        return $return;
    }
  }
