<?php
  namespace CasaSync;

  class Archive {
    public $conversion = null;

    public function __construct(){ 
      $this->conversion = new Conversion;
    }  

    public function getPagination(){
      global $wp_query;
      $total_pages = $wp_query->max_num_pages;
      if ($total_pages > 1) {
        $current_page = max(1, get_query_var('paged'));
        if($current_page) {
          $prev_page = '<li class="disabled"><span>&laquo;</span></li>';
          $next_page = '<li class="disabled"><a href="#">&raquo;</a></li>';
          $i = 0;
          $return = '<ul class="casasync-pagination">';
          $return .= $prev_page;
          while ($i < $total_pages) {
            $i++;
            if ($current_page == $i) {
              $return .= '<li><span>' . $i . '<span class="sr-only">(current)</span></span></a></li>';
            } else {
              $return .= '<li><a href="' . get_pagenum_link($i) . '">' . $i . '</a></li>';
            }
          }
          $return .= $next_page;
          $return .= '</ul>';

        }

      }
      return $return;
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
            $options[$term->slug]['label'] = $this->conversion->casasync_convert_categoryKeyToLabel($term->name) . ' (' . $term->count . ')';
            $options[$term->slug]['checked'] = (in_array($term->slug, $categories) ? 'SELECTED' : '');
        }
        return $options;
    }
    public function getLocationsOptions() {
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
                    $store .= "<option class='lvl3' value='" . $term3->slug . "' " . (in_array($term3->slug, $locations) ? 'SELECTED' : '') . ">" . '' . $term3->name . ' (' . $term3->count . ')' . "</option>";
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
                        $return .= "<option class='lvl2' value='" . $location->slug . "' " . (in_array($location->slug, $locations) ? 'SELECTED' : '') . ">" . '' . $location->name . ' (' . $location->count . ')' . "</option>";      
                    }
                    $return .= "</optgroup>";
                }
            }   
            unset($otherCountry);

            if (!$terms_lvl1_has_children) {
                $no_child_lvl1 .=  "<option value='" . $term->slug . "' " . (in_array($term->slug, $locations) ? 'SELECTED' : '') . ">" . $term->name . ' (' . $term->count . ')' . "</option>";
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
            if (in_array($term->slug, $cur_basis)) {
                $return .= '<input type="hidden" name="casasync_salestype_s[]" value="'.$term->slug.'" /> ';
            }
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
                $options[$term->slug]['label'] = __(ucfirst($term->name)) . ' (' . $term->count . ')';
                $options[$term->slug]['checked'] = (in_array($term->slug, $salestypes) ? 'SELECTED' : '');
            }
        }
        return $options;
    }

    public function getFilterForm(){
        global $wp_query;
        $return = '<form action="' .  get_post_type_archive_link( 'casasync_property' ) . '" class="casasync-filterform">';
        //if permalinks are off
        if ( get_option('permalink_structure') == '' ) {
            $return .= '<input type="hidden" name="post_type" value="casasync_property" />';
        }

        $return .= '<select name="casasync_category_s[]" multiple class="casasync_multiselect" data-empty="' . __('Choose category','casasync') . '">';
        $cat_options = $this->getCategoryOptions();
        foreach ($cat_options as $option) {
            $return .= "<option value='" . $option['value'] . "' " . $option['checked'] . ">" . $option['label'] . "</option>";
        }
        $return .= '</select>';
        
        $return .= '<select name="casasync_location_s[]" multiple class="casasync_multiselect" data-empty="' . __('Choose locality','casasync') . '">';
        $return .= $this->getLocationsOptions();
        $return .= '</select>';

        $return .= '<select name="casasync_salestype_s[]" multiple class="casasync_multiselect" data-empty="Angebot wählen">';
        $salestype_options = $this->getSalestypeOptions();
        foreach ($salestype_options as $option) {
            $return .= "<option value='" . $option['value'] . "' " . $option['checked'] . ">" . $option['label'] . "</option>";
        }
        $return .= '</select>';
                
        $return .= '<input class="casasync-filterform-button" type="submit" value="' . __('Search','casasync') . '" />';
        $return .= '</form>';

        return $return;
    }
  }
