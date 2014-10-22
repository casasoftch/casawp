<?php
  namespace CasaSync;

  class Single {  
    public $conversion = null;

    public $attachments = array();
    public $documents = array();
    public $plans = array();
    public $logos = array();
    public $address_street = false;
    public $address_streetnumber = false;
    public $address_postalcode = false;
    public $address_region = false;
    public $address_locality = false;
    public $address_country = false;
    public $address_country_name = false;
    public $casa_id = false;
    public $customer_id = false;
    public $property_id = false;
    public $reference_id = false;
    public $property = false;
    public $tag_values = array();
    public $categories_names = array();
    public $floors = array();
    public $basises = array();
    public $main_basis = 'buy';
    public $prices = array();
    public $price_currency = 'CHF';
    public $numvals = array();
    public $features = array();
    public $content = '';
    public $content_parts = array();
    public $urls = array();
    public $availability = false;
    public $availability_label = '';
    public $the_availability = ''; //taxonomy
    public $start = false;
    public $seller = array(
      'fallback'      => false,
      'country'       => '',
      'locality'      => '',
      'region'        => '',
      'postalcode'    => '',
      'street'        => '',
      'legalname'     => '',
      'email'         => '',
      'fax'           => '',
      'phone_central' => '',
    );
    public $salesperson = array(
      'function'      => '',
      'givenname'     => '',
      'familyname'    => '',
      'email'         => '',
      'phone_direct'  => '',
      'phone_mobile'  => '',
      'gender'        => '',
      'honorific'     => false,
    );
    public $seller_inquiry = array(
      'email'         => ''
    );
    public $contactEmails = array(
      'inquiry'       => false,
      'remcat'        => false,
      'fallback'      => false
    );

    public function __construct($post){ 
      $this->conversion = new Conversion;
      $this->setProperty($post);
    }  

    public function getPrevNext($query){
      $w_categories = array();
      foreach ($query['categories'] as $slug => $options) {
        if ($options['checked']) {
          $w_categories[] = $options['value'];
        }
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

      $w_locations = array();
      if (isset($query['locations'])) {
        foreach ($query['locations'] as $slug => $options) {
          if ($options['checked']) {
            $w_locations[] = $options['value'];
          }
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
      }
      $w_salestypes = array();
      if (array_key_exists('salestypes', $query)) {
        foreach ($query['salestypes'] as $slug => $options) {
          if ($options['checked']) {
            $w_salestypes[] = $options['value'];
          }
        }
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


      $posts_per_page = get_option('posts_per_page', 10);
      $args = array(
        'post_type' => 'casasync_property',
        'posts_per_page' => $posts_per_page,
        'tax_query' => $taxquery_new, 
      );
      switch (get_option('casasync_archive_orderby', 'date')) {
        case 'title':
            $args['orderby'] = 'title';
            break;
        case 'location':
            $args['meta_key'] = 'casasync_property_address_locality';
            $args['orderby'] = 'meta_value';
            break;
        case 'price':
            $args['orderby'] = 'price';
            break;
        case 'menu_order':
            $args['orderby'] = 'menu_order';
            break;
        case 'casasync_referenceId':
            $args['meta_key'] = 'casasync_referenceId';
            $args['orderby'] = 'meta_value';
            break;
        case 'date':
        default:
            $args['orderby'] = 'date';
            break;
      }
      $args['order'] = get_option('casasync_archive_order', 'DESC');
      $the_query = new \WP_Query( $args );

      $prev = false;
      $next = false;
      while( $the_query->have_posts() ) {
        $the_query->next_post();
        if ($the_query->post->post_name == $this->property->post_name) {
          if ($the_query->current_post + 1 < $the_query->post_count ) {
            $next_post = $the_query->next_post();
            $next = $next_post;
            break;
          }
        }
        if ($the_query->post_count-1 != $the_query->current_post) { //because nextpost will fail at the end :-)
          $prev = $the_query->post;
        }
      }

      if ( get_option('permalink_structure') == '' ) {
        $post_type_slug = 'casasync_property';
      } else {
        $post_type = get_post_type_object('casasync_property');
        $post_type_slug = $post_type->rewrite['slug'];
      }
      $prevnext = array(
        'nextlink' => ($prev ? get_permalink($prev->ID) : 'no'), 
        'prevlink' => ($next ? get_permalink($next->ID) : 'no')
      );
      return $prevnext;

    }


    public function setProperty($post){
      $this->property = $post;

      $this->attachments = get_posts( array(
        'post_type'                => 'attachment',
        'posts_per_page'           => -1,
        'post_parent'              => get_the_ID(),
        //'exclude'                => get_post_thumbnail_id(),
        'casasync_attachment_type' => 'image',
        'orderby'                  => 'menu_order',
        'order'                    => 'ASC'
      ) ); 

      $this->documents = get_posts( array(
        'post_type'                => 'attachment',
        'posts_per_page'           => -1,
        'post_parent'              => get_the_ID(),
        //'exclude'                => get_post_thumbnail_id(),
        'casasync_attachment_type' => 'document',
        'orderby'                  => 'menu_order'
      ) ); 

      $this->plans = get_posts( array(
        'post_type'                => 'attachment',
        'posts_per_page'           => -1,
        'post_parent'              => get_the_ID(),
        //'exclude'                => get_post_thumbnail_id(),
        'casasync_attachment_type' => 'plan'
      ) ); 

      $this->logos = get_posts( array(
        'post_type'                => 'attachment',
        'posts_per_page'           => -1,
        'post_parent'              => get_the_ID(),
        //'exclude'                => get_post_thumbnail_id(),
        'casasync_attachment_type' => 'offer-logo'
      ) ); 

      $this->address_street       = get_post_meta( get_the_ID(), 'casasync_property_address_streetaddress', $single = true );
      $this->address_streetnumber = get_post_meta( get_the_ID(), 'casasync_property_address_streetnumber', $single = true );
      $this->address_postalcode   = get_post_meta( get_the_ID(), 'casasync_property_address_postalcode', $single = true );
      $this->address_region       = get_post_meta( get_the_ID(), 'casasync_property_address_region', $single = true );
      $this->address_locality     = get_post_meta( get_the_ID(), 'casasync_property_address_locality', $single = true );
      $this->address_country      = get_post_meta( get_the_ID(), 'casasync_property_address_country', $single = true );
      $this->address_country_name = $this->conversion->countrycode_to_countryname($this->address_country);


      $this->casa_id = get_post_meta( get_the_ID(), 'casasync_id', $single = true );
      $casa_id_arr = explode('_', $this->casa_id);
      $this->customer_id = (!empty($casa_id_arr[0])) ? $casa_id_arr[0] : null;
      $this->property_id =(!empty($casa_id_arr[1])) ? $casa_id_arr[1] : null;

      $this->reference_id = get_post_meta( get_the_ID(), 'casasync_referenceId', $single = true );

      $this->start = get_post_meta( get_the_ID(), 'casasync_start', $single = true );

      $categories = wp_get_post_terms( get_the_ID(), 'casasync_category'); 
      $this->categories_names = array();
      foreach ($categories as $category) {
        if ($this->conversion->casasync_convert_categoryKeyToLabel($category->slug, $category->name)) {
          $this->categories_names[] = $this->conversion->casasync_convert_categoryKeyToLabel($category->slug, $category->name);
        }
      } 

      $availabilities = wp_get_post_terms( get_the_ID(), 'casasync_availability'); 
      $availability = false;
      if ($availabilities) {
        $availability = $availabilities[0];
        $this->the_availability = $this->conversion->casasync_convert_availabilityKeyToLabel($availability->slug);
      }

      
      $this->categories_names = array();
      foreach ($categories as $category) {
        if ($this->conversion->casasync_convert_categoryKeyToLabel($category->slug, $category->name)) {
          $this->categories_names[] = $this->conversion->casasync_convert_categoryKeyToLabel($category->slug, $category->name);
        }
      } 

      $floors = get_post_meta( get_the_ID(), 'floor', $single = true ); 
      if($floors != "") {
        if($floors == '0') {
          $floor_type = '';
          $this->floors[] = __('Ground', 'casasync');
        } elseif(strpos($floors, '-') === false) {
          $floor_type = '. ' . __('Floor', 'casasync');
        } else {
          $floors = str_replace('-', '', $floors);
          $floor_type = '. UG';
        }
        $this->floors[] = $floors . $floor_type;
      }
      
      $basis = wp_get_post_terms( get_the_ID(), 'casasync_salestype'); 
      $basis_slugs = array();
      foreach ($basis as $basi) {
        $this->basises[] = __(ucfirst($basi->name),'casasync');
        $basis_slugs[] = $basi->slug;
      } 
      if ($basis_slugs) {
        $this->main_basis = $basis_slugs[0];
      }

      $this->price_currency = get_post_meta( get_the_ID(), 'price_currency', $single = true );

      $sales['num']             = get_post_meta( get_the_ID(), 'price', $single = true );
      $sales['propertysegment'] = get_post_meta( get_the_ID(), 'price_propertysegment', $single = true );
      $sales['timesegment']     = get_post_meta( get_the_ID(), 'price_timesegment', $single = true );

      $gross['num']             = get_post_meta( get_the_ID(), 'grossPrice', $single = true );
      $gross['propertysegment'] = get_post_meta( get_the_ID(), 'grossPrice_propertysegment', $single = true );
      $gross['timesegment']     = get_post_meta( get_the_ID(), 'grossPrice_timesegment', $single = true );

      $net['num']             = get_post_meta( get_the_ID(), 'netPrice', $single = true );
      $net['propertysegment'] = get_post_meta( get_the_ID(), 'netPrice_propertysegment', $single = true );
      $net['timesegment']     = get_post_meta( get_the_ID(), 'netPrice_timesegment', $single = true );

      $extra_costs_json =  get_post_meta( get_the_ID(), 'extraPrice', $single = true );
      $extra_costs_arr = array();
      if ($extra_costs_json && $extra_costs_json != '[]') {
        $extra_costs_arr = $extra_costs_json;
      }

      $this->prices = array(
        'sales' => $sales,
        'net' => $net,
        'gross' => $gross,
        'extra_costs' => $extra_costs_arr
      );

      $this->urls = get_post_meta( get_the_ID(), 'casasync_urls', $single = true );

      foreach ($this->conversion->casasync_get_allNumvalKeys() as $numval_key) {
        $numval = get_post_meta( get_the_ID(), $numval_key, $single = true );
        if ($numval) {
          $title = $this->conversion->casasync_convert_numvalKeyToLabel($numval_key);
          $this->numvals[$numval_key] = array('title' => $title, 'value' => $numval, 'key' => $numval_key);
        }
      }


      $features_json = get_post_meta( get_the_ID(), 'casasync_features', $single = true );
      if ($features_json) {
        $this->features = json_decode($features_json, true);
      }

      $this->content = get_the_content(); 
      $this->content_parts = explode('<hr class="property-separator" />', $this->content);

      $this->seller['fallback']      = true;
      $this->seller['country']       = get_post_meta( get_the_ID(), 'seller_org_address_country', $single = true );
      $this->seller['locality']      = get_post_meta( get_the_ID(), 'seller_org_address_locality', $single = true );
      $this->seller['region']        = get_post_meta( get_the_ID(), 'seller_org_address_region', $single = true );
      $this->seller['postalcode']    = get_post_meta( get_the_ID(), 'seller_org_address_postalcode', $single = true );
      $this->seller['street']        = get_post_meta( get_the_ID(), 'seller_org_address_streetaddress', $single = true );
      $this->seller['legalname']     = get_post_meta( get_the_ID(), 'seller_org_legalname', $single = true );
      $this->seller['brand']         = get_post_meta( get_the_ID(), 'seller_org_brand', $single = true );
      $this->seller['email']         = get_post_meta( get_the_ID(), 'seller_org_email', $single = true );
      $this->seller['fax']           = get_post_meta( get_the_ID(), 'seller_org_fax', $single = true );
      $this->seller['phone_direct']  = get_post_meta( get_the_ID(), 'seller_org_phone_direct', $single = true );
      $this->seller['phone_central'] = get_post_meta( get_the_ID(), 'seller_org_phone_central', $single = true );
      $this->seller['phone_mobile']  = get_post_meta( get_the_ID(), 'seller_org_phone_mobile', $single = true );

      if (get_option('casasync_sellerfallback_show_organization') == 1) {
        if (!$this->hasSeller()) {
          $this->seller['country']       = get_option('casasync_sellerfallback_address_country');
          $this->seller['locality']      = get_option('casasync_sellerfallback_address_locality');
          $this->seller['region']        = get_option('casasync_sellerfallback_address_region');
          $this->seller['postalcode']    = get_option('casasync_sellerfallback_address_postalcode');
          $this->seller['street']        = get_option('casasync_sellerfallback_address_street');
          $this->seller['legalname']     = get_option('casasync_sellerfallback_legalname');
          $this->seller['email']         = get_option('casasync_sellerfallback_email');
          $this->seller['fax']           = get_option('casasync_sellerfallback_fax');
          $this->seller['phone_central'] = get_option('casasync_sellerfallback_phone_central');
        }
      }
      
      $this->salesperson['function']      = get_post_meta(get_the_ID(), 'seller_person_function', true);
      $this->salesperson['givenname']     = get_post_meta(get_the_ID(), 'seller_person_givenname', true);
      $this->salesperson['familyname']    = get_post_meta(get_the_ID(), 'seller_person_familyname', true);
      $this->salesperson['email']         = get_post_meta(get_the_ID(), 'seller_person_email', true);
      //$this->salesperson['fax']           = get_post_meta(get_the_ID(), 'seller_person_fax', true);
      $this->salesperson['phone_direct']  = get_post_meta(get_the_ID(), 'seller_person_phone_direct', true);
      //$this->salesperson['phone_central'] = get_post_meta(get_the_ID(), 'seller_person_phone_central', true);
      $this->salesperson['phone_mobile']  = get_post_meta(get_the_ID(), 'seller_person_phone_mobile', true);
      $this->salesperson['gender']        = get_post_meta(get_the_ID(), 'seller_person_gender', true);

      if (get_option('casasync_sellerfallback_show_person_view') == 1) {
        if (!$this->hasSalesPerson()) {
          $this->salesperson['function']      = get_option('casasync_salesperson_fallback_function');
          $this->salesperson['givenname']     = get_option('casasync_salesperson_fallback_givenname');
          $this->salesperson['familyname']    = get_option('casasync_salesperson_fallback_familyname');
          $this->salesperson['email']         = get_option('casasync_salesperson_fallback_email');
          //$this->salesperson['phone_direct']  = get_option('casasync_salesperson_fallback_phone_direct');
          $this->salesperson['phone_mobile']  = get_option('casasync_salesperson_fallback_phone_mobile');
          $this->salesperson['gender']        = get_option('casasync_salesperson_fallback_gender');
        }
      }
      if ($this->salesperson['gender'] == 'F') {
        $this->salesperson['honorific'] = 'Frau';
      } elseif ($this->salesperson['gender'] == 'M') {
        $this->salesperson['honorific'] = 'Herr';
      }

      $this->seller_inquiry['email'] = get_post_meta(get_the_ID(), 'seller_inquiry_person_email', true);

      $this->availability = get_post_meta( get_the_ID(), 'availability', $single = true );
      $this->availability_label = get_post_meta( get_the_ID(), 'availability_label', $single = true );
    }

    public function getAvailabilityLabel(){
      $return = false;
      # old
      /*if($this->start) {
        $current_datetime = strtotime(date('c'));
        $property_datetime = strtotime($this->start);
        if ($property_datetime > $current_datetime && $this->start != false) {
          $datetime = new \DateTime(str_replace(array("+02:00", "+01:00"), "", $this->start));
          $return = date_i18n(get_option('date_format'), $datetime->getTimestamp());
        } else {
          $return = $this->conversion->casasync_convert_availabilityKeyToLabel('immediately');
        }
      }
      if($this->availability_label != '') {
        $return = $this->conversion->casasync_convert_availabilityKeyToLabel($this->availability_label);
      }*/

      #new
      if($this->start) {
        $current_datetime = strtotime(date('c'));
        $property_datetime = strtotime($this->start);
        if ($property_datetime > $current_datetime && $this->start != false) {
          $datetime = new \DateTime(str_replace(array("+02:00", "+01:00"), "", $this->start));
          $return = date_i18n(get_option('date_format'), $datetime->getTimestamp());
        } else {
          $return = $this->conversion->casasync_convert_availabilityKeyToLabel('immediately');
        }
      } else {
        $return = __('On Request', 'casasync');
      }

      return $return;
    }

    public function getGallery(){

      if ($this->attachments) {

        if(get_option('casasync_load_css') == 'bootstrapv2') {
          $return = '<div class="casasync-slider-currentimage" id="slider">';
            $return .= '<div class="row-fluid">';
              $return .= '<div class="span12" id="carousel-bounding-box">';
                $return .= '<div id="casasyncCarousel" class="carousel slide">';
                  $return .= '<div class="carousel-inner">';

                    $i = 0;
                    foreach ( $this->attachments as $attachment ) {
                      $i++;
                      $return .= '<div class="' . ($i == 1 ? 'active' : '') . ' item" data-slide-number="'.($i-1) .'">';
                        $thumbimgL = wp_get_attachment_image( $attachment->ID, 'full', true );
                        $return .= '<a href="'. wp_get_attachment_url( $attachment->ID ) .'" data-featherlight="#casasync-property-images">'. $thumbimgL .'</a>';
                        $return .= '<div id="carousel-text" class="carousel-caption" >';
                        if($attachment->post_excerpt != '') {
                          $return .= '<p>'. $attachment->post_excerpt .'</p>';
                        }
                        $return .= '</div>';
                      $return .= '</div>';
                    }
                    
                  $return .= '</div>';
                  $return .= '<a class="casasync-carousel-left" href="#casasyncCarousel" data-slide="prev"><i>‹</i></a>';
                  $return .= '<a class="casasync-carousel-right" href="#casasyncCarousel" data-slide="next"><i>›</i></a>';

                $return .= '</div>';
              $return .= '</div>';
            $return .= '</div>';
          $return .= '</div>';
        } else {

          /* --- Bootstrap v3 --- */
          $return = '<div id="slider_'.get_the_ID().'" class="casasync-carousel slide" data-ride="carousel" data-interval="false">';
          
            //indicators
            $indicators = get_option('casasync_single_show_carousel_indicators' , '0');
            if($indicators != 0) {
              $return .= '<ol class="carousel-indicators">';
              $i = 0;
              foreach ($this->attachments as $attachment) {
                $return .= '<li data-target="#slider_'.get_the_ID().'" data-slide-to="'.$i.'" class="'.($i==0?'active':'').'"></li>';  
                $i++;
              }
              $return .= '</ol>';
            }

            //Wrapper for slides
            $return .= '<div class="casasync-carousel-inner">';
              $i = 0;
              foreach ($this->attachments as $attachment) {
                $return .= '<div class="item '.($i==0?'active':'').'">';
                  $img     = wp_get_attachment_image( $attachment->ID, 'full', true, array('class' => 'carousel-image') );
                  $img_url = wp_get_attachment_image_src( $attachment->ID, 'full' );
                  /*if (get_option('casasync_load_featherlight', false)) {*/
                    $return .= '<a class="property-image-gallery" rel="casasync-single-gallery" href="' . $img_url[0] . '" title="' . $attachment->post_excerpt . '">' . $img . '</a>';
                  /*} else {
                    $return .= $img;
                  }*/
                  if ($attachment->post_excerpt) {
                    $class = ($indicators != '0') ? ('hasIndicators') : (null);
                    $return .= '<div class="casasync-carousel-caption '.$class.'">';
                      $return .= '<p>' . $attachment->post_excerpt . '</p>';
                    $return .= '</div>';
                  }
                  if ($this->getAvailability()) {
                    $return .= $this->getAvailability();
                  }
                  
                $return .= '</div>';
                $i++;
              }
              $return .= '</div>';

              //controlls
              $return .= '<a class="left casasync-carousel-control" href="#slider_'.get_the_ID().'" data-slide="prev">
                <span class="glyphicon glyphicon-chevron-left"></span>
              </a>
              <a class="right casasync-carousel-control" href="#slider_'.get_the_ID().'" data-slide="next">
                <span class="glyphicon glyphicon-chevron-right"></span>
              </a>';

            $return .= '</div>';
        }

        //thumbnails
        //if($i > 1) {
        //  $return .= '<div class="casasync-slider-thumbnails" id="slider-thumbs">'
        //              .'<ul class="thumbnail-pane active">';
        //  $i = 0;
        //  foreach ( $this->attachments as $attachment ) {
        //    $i++;
        //    $class = "post-attachment mime-" . sanitize_title( $attachment->post_mime_type ) . ($i == 1 ? ' active' : '');
        //    $thumburl = wp_get_attachment_url($attachment->ID);
        //    $thumbimg = wp_get_attachment_image( $attachment->ID, 'casasync-thumb', true );

        //    $return .= '<li class="' . $class . ' "><a href="'.$thumburl.'" id="carousel-selector-'.($i-1).'">' . $thumbimg . '</a></li>';
        //    $return .= ($i % 4 == 0 ? '</ul><ul class="thumbnail-pane hidden">' : '');
        //  } 
        //  $return .= '</ul></div>';
        //}
        return $return;
      }
    }

    //TODO: Svgs dont work yet
    public function getLogo(){
      if ($this->logos) {
        foreach ($this->logos as $logo) {
          $img     = wp_get_attachment_image( $logo->ID, 'full', true, array('class' => 'carousel-image') );
          $img_url = wp_get_attachment_image_src( $logo->ID, 'full' );
          if ($img_url) {
            return '<img class="casasync-offer-logo img-responsive" alt="Property Logo" title="Property Logo" src="'.$img_url[0].'" />';
          }
        }
      }
    }

    public function getTitle(){
      $title = get_the_title();
      $new_title = str_replace('m2', 'm<sup>2</sup>', $title);
      return $new_title;
    }

    public function getExcerpt(){
      global $post;
      $excerpt = $post->post_excerpt;
      $new_excerpt = str_replace('m2', 'm<sup>2</sup>', $excerpt);
      return $new_excerpt;
    }

    public function getBasicBoxes(){
      $content = '<div class="casasync-basic-box"><div>';
        $content .= '<h4>'.implode(', ', $this->categories_names).'</h4>';

        $presentable_numvals = array(
          'casasync_single_show_number_of_rooms',
          'casasync_single_show_surface_usable',
          'casasync_single_show_surface_living',
          'casasync_single_show_surface_property',
          'casasync_single_show_floor',
          'casasync_single_show_number_of_floors',
          'casasync_single_show_year_built',
          'casasync_single_show_year_renovated',
          'casasync_single_show_availability'
        );

        $numvals_to_display = array();
        $i = 1000;
        foreach ($presentable_numvals as $value) {
          if(get_option($value, false)) {
            $numval_order = get_option($value.'_order', false);
            if($numval_order) {
              $numvals_to_display[$numval_order] = $value;
            } else {
              $numvals_to_display[$i] = $value;
              $i++;
            }
          }
        }
        ksort($numvals_to_display);

        if(!empty($numvals_to_display)) {
          foreach ($numvals_to_display as $value) {
            $br = '<br>';
            switch ($value) {
              case 'casasync_single_show_number_of_rooms':
                if ($this->getNumval('number_of_rooms')){
                  $content .= __('Number of rooms:', 'casasync') . ' ' . $this->getNumval('number_of_rooms');
                  $content .= $br;
                }
                break;
              case 'casasync_single_show_surface_usable':
                if ($this->getNumval('surface_usable')){
                  $content .= __('Surface usable:', 'casasync') . ' ' . $this->getNumval('surface_usable');
                  $content .= $br;
                }
                break;
              case 'casasync_single_show_surface_living':
                if ($this->getNumval('surface_living')){
                  $content .= __('Living space:', 'casasync') . ' ' . $this->getNumval('surface_living');
                  $content .= $br;
                }
                break;
              case 'casasync_single_show_surface_property':
                if ($this->getNumval('surface_property')){
                  $content .= __('Property space:', 'casasync') . ' ' . $this->getNumval('surface_property');
                  $content .= $br;
                }
                break;
              case 'casasync_single_show_floor':
                if ($this->floors){
                  $content .= __('Floor:', 'casasync') . ' ' . $this->floors[0];
                  $content .= $br;
                }
                break;
              case 'casasync_single_show_number_of_floors':
                if ($this->getNumval('number_of_floors')){
                  $content .= __('Number of floors:', 'casasync') . ' ' . $this->getNumval('number_of_floors');
                  $content .= $br;
                }
                break;
              case 'casasync_single_show_year_built':
                if ($this->getNumval('year_built')){
                  $content .= __('Year of construction:', 'casasync') . ' ' . $this->getNumval('year_built');
                  $content .= $br;
                }
                break;
              case 'casasync_single_show_year_renovated':
                if ($this->getNumval('year_renovated')){
                  $content .= __('Year of renovation:', 'casasync') . ' ' . $this->getNumval('year_renovated');
                  $content .= $br;
                }
                break;
              case 'casasync_single_show_availability':
                if ($this->getAvailabilityLabel()) {
                  $content .= __('Available:','casasync') . ' ' . $br . $this->getAvailabilityLabel();
                  $content .= $br;
                }
              break;
              default:
                break;
            }
          }
        }
      $content .= '</div></div>';
      $content .= '<div class="casasync-basic-box"><div>';
        $content .= '<h4>' . __("Address", 'casasync') . '</h4>';
        if ($this->getAddress('property')){
          $content .= $this->getAddress('property');  
        };
      $content .= '</div></div>';
      $content .= '<div class="casasync-basic-box"><div>';
      $price_title = ($this->main_basis == 'rent') ? (__('rent', 'casasync')) : (__('Price', 'casasync'));
        $content .= '<h4>' . $price_title . '</h4>';
        if ($this->main_basis == 'buy') {
          $content .= __('Sales price:', 'casasync') . ' ';
          if ($this->getPrice('sales')) {
            $content .= $this->getPrice('sales', 'full');
          } else {
            $content .= __('On Request', 'casasync');
          }
        }
        if ($this->main_basis == 'rent') {
          if ($this->getPrice('gross') || $this->getPrice('net')) {
            if ($this->getPrice('gross')) {
              $content .= __('Gross price:', 'casasync') . '<br>';
              $content .= $this->getPrice('gross', 'full') . '<br>';
            }
            if ($this->getPrice('net')) {
              $content .= __('Net price:', 'casasync') . '<br>';
              $content .= $this->getPrice('net', 'full') . '<br>';
            }
          } else {
            $content .= __('On Request', 'casasync');
          }
        }
        if ($this->getExtraCosts('Nebenkosten')) {
          $content .= '<br>'.__('Additional costs', 'casasync') . ': ' . $this->getExtraCosts('Nebenkosten');
        }
      $content .= '</div></div>';
      return $content;
    }

    public function getSpecificationsTable(){
      $content = '<h3>' . __('Offer','casasync'). '</h3>';
      $content .= '<table class="table">';
      if ($this->main_basis == 'buy') {
        $content .= '<tr>'
          .'<td class="width-25">' . __('Sales price', 'casasync') . '</td>'
        .'<td class="width-75">';
        $content .= $this->getPrice('sales') ? $this->getPrice('sales', 'full') : __('On Request', 'casasync');
        $content .= '</td>'
        .'</tr>';
      }
      if ($this->main_basis == 'rent') {
        if ( $this->getPrice('gross') || $this->getPrice('net')  ) {
          if ($this->getPrice('gross')) {
            $content .= '<tr>'
              .'<td class="width-25">' . __('Gross price','casasync') . '</td>'
            .'<td class="width-75">';
            $content .= $this->getPrice('gross', 'full');
            $content .= '</td>'
            .'</tr>';
          }
          if ($this->getPrice('net')) {
            $content .= '<tr>'
              .'<td class="width-25">' . __('Net price','casasync') . '</td>'
            .'<td class="width-75">';
            $content .= $this->getPrice('net', 'full');
            $content .= '</td>'
            .'</tr>';
          }
        } else {
          $content .= '<tr>'
              .'<td class="width-25">' . __('Rent price','casasync') . '</td>'
            .'<td class="width-75">';
            $content .=  __('On Request', 'casasync');
            $content .= '</td>'
            .'</tr>';
        }
      }
      if ($this->getExtraCosts('Nebenkosten')) {
        $content .= '<tr>
          <td class="width-25"> ' . __('Additional costs', 'casasync') . '</td>'
          .'<td class="width-75">' . $this->getExtraCosts('Nebenkosten') . '</td>'
        .'</tr>';
      }
      if ($this->the_availability) {
        $content .= '<tr>'
              .'<td class="width-25">' . __('Availability','casasync') . '</td>'
            .'<td class="width-75">';
            $content .= $this->the_availability;
            $content .= '</td>'
            .'</tr>';
      }
      $content .= '</table>';

      if ($this->numvals || $this->getAddress('property') || $this->reference_id || $this->property_id) {
        $content .= '<h3>' . __('Property','casasync') . '</h3>';
        $content .= '<table class="table">';
        $reference_or_property_id = ($this->reference_id) ? ($this->reference_id) : ($this->property_id);
        if($reference_or_property_id) {
          $content .= '<tr>
            <td class="width-25">' . __('Reference','casasync') .'</td>'
            .'<td class="width-75">' . $reference_or_property_id . '</td>'
          .'</tr>';
        }

        if($this->getAddress('property')) {
          $content .= '<tr>
            <td class="width-25">' . __('Address','casasync') . '</td>'
            .'<td class="width-75">' . $this->getAddress('property') . '</td>'
          .'</tr>';
        }

        $all_numvals = $this->getAllNumvals(array('surface_usable', 'surface_living', 'surface_property'));

        foreach ($all_numvals as $numval) {
          $content .= '<tr>
            <td class="width-25">' . __($numval['title'], 'casasync') . '</td>'
            .'<td class="width-75">' . $this->getNumval($numval["key"]) . '</td>'
          .'</tr>';
        }
        $content .= '</table>';
      }

      if ($this->features){
        $content .= '<div class="casasync-features">';
        $content .= '<h3>' . __('Features','casasync') . '</h3>';
        $content .= $this->getAllFeatures();
        $content .= '</div>';
      }

      if ($this->getAllDocuments()){
        $content .= '<div class="casasync-documents">';
        $content .= '<h3>' . __('Documents','casasync') . '</h3>';
        $content .= $this->getAllDocuments();
        $content .= '</div>';
      }

      if ($this->getAllDistances()) {
        $content .= '<div class="casasync_distances">';
        $content .= '<h3>' . __('Distances','casasync') . '</h3>';
        $content .= $this->getAllDistances();
        $content .= '</div>';
      }

      if ($this->getProvidedURL()) {
        $content .= '<div class="casasync_provided_url">';
        $content .= '<h3>' . __('Link','casasync') . '</h3>';
        $content .= $this->getProvidedURL();
        $content .= '</div>';
      }

      return $content;
    }

    public function getQuickInfosTable() {
      $presentable_values = array(
        'casasync_archive_show_street_and_number',
        'casasync_archive_show_location',
        'casasync_archive_show_number_of_rooms',
        'casasync_archive_show_surface_usable',
        'casasync_archive_show_surface_living',
        'casasync_archive_show_surface_property',
        'casasync_archive_show_floor',
        'casasync_archive_show_number_of_floors',
        'casasync_archive_show_year_built',
        'casasync_archive_show_year_renovated',
        'casasync_archive_show_price',
        'casasync_archive_show_excerpt',
        'casasync_archive_show_availability'
      );

      $value_to_display = array();
      $i = 1000;
      foreach ($presentable_values as $value) {
        if(get_option($value, false)) {
          $value_order = get_option($value.'_order', false);
          if($value_order) {
            $value_to_display[$value_order] = $value;
          } else {
            $value_to_display[$i] = $value;
            $i++;
          }
        }
      }
      ksort($value_to_display);

      $return = NULL;
      if ($value_to_display) {
        $return .= '<table class="table">';
        $return .= '<tbody>';

        foreach ($value_to_display as $value) {
          switch ($value) {
            case 'casasync_archive_show_street_and_number':
              if($this->address_street){
                  $return .= '<tr>'
                  .'<th>' . __('Street', 'casasync') . '</th>'
                  .'<td>' . $this->address_street . ' ' . $this->address_streetnumber . '</td>'
                .'</tr>';
              }
              break;
            case 'casasync_archive_show_location':
              if ($this->getAddress('property')){
                $return .= '<tr>'
                  .'<th>' . __('Locality', 'casasync') . '</th>'
                  .'<td>' . $this->getAddress('property', true) . '</td>'
                .'</tr>';
              }
              break;
            case 'casasync_archive_show_number_of_rooms':
              if ($this->getNumval('number_of_rooms')){
                $return .= '<tr>'
                  .'<th>' .  __('Number of rooms', 'casasync') . '</th>'
                  .'<td>' . $this->getNumval('number_of_rooms') . '</td>'
                .'</tr>';
              }
              break;
            case 'casasync_archive_show_surface_usable':
              if ($this->getNumval('surface_usable')){
                $return .= '<tr>'
                  .'<th>' .  __('Surface usable', 'casasync') . '</th>'
                  .'<td>' . $this->getNumval('surface_usable') . '</td>'
                .'</tr>';
              }
              break;
            case 'casasync_archive_show_surface_living':
              if ($this->getNumval('surface_living')){
                $return .= '<tr>'
                  .'<th>' . __('Living space', 'casasync') . '</th>'
                  .'<td>' . $this->getNumval('surface_living', true) . '</td>'
                .'</tr>';
              }
              break;
            case 'casasync_archive_show_surface_property':
              if ($this->getNumval('surface_property')){
                $return .= '<tr>'
                  .'<th>' . __('Property space', 'casasync') . '</th>'
                  .'<td>' . $this->getNumval('surface_property', true) . '</td>'
                .'</tr>';
              }
              break;
            case 'casasync_archive_show_floor':
              if ($this->floors){
                $return .= '<tr>'
                  .'<th>' . __('Floor', 'casasync') . '</th>'
                  .'<td>' . $this->floors[0] . '</td>'
                .'</tr>';
              }
              break;
            case 'casasync_archive_show_number_of_floors':
             if ($this->getNumval('number_of_floors')){
                $return .= '<tr>'
                  .'<th>' . __('Number of floors', 'casasync') . '</th>'
                  .'<td>' . $this->getNumval('number_of_floors', true) . '</td>'
                .'</tr>';
              }
              break;
            case 'casasync_archive_show_year_built':
              if ($this->getNumval('year_built')){
                    $return .= '<tr>'
                  .'<th>' . __('Year of construction', 'casasync') . '</th>'
                  .'<td>' . $this->getNumval('year_built', true) . '</td>'
                .'</tr>';
                }
              break;
            case 'casasync_archive_show_year_renovated':
              if ($this->getNumval('year_renovated')){
                $return .= '<tr>'
                  .'<th>' . __('Year of renovation', 'casasync') . '</th>'
                  .'<td>' . $this->getNumval('year_renovated', true) . '</td>'
                .'</tr>';
              }
              break;
            case 'casasync_archive_show_price':
              if ($this->main_basis == 'buy') {
                $return .= '<tr>'
                  .'<th>' . __('Sales price','casasync') . '</th>'
                .'<td>';
                $return .= $this->getPrice('sales') ? $this->getPrice('sales', 'full') : __('On Request', 'casasync');
                $return .= '</td>'
                .'</tr>';
              }
              if ($this->main_basis == 'rent') {
                if ( $this->getPrice('gross') || $this->getPrice('net')  ) {
                  if ($this->getPrice('gross')) {
                    $return .= '<tr>'
                      .'<th>' . __('Gross price','casasync') . '</th>'
                    .'<td>';
                    $return .= $this->getPrice('gross', 'full');
                    $return .= '</td>'
                    .'</tr>';
                  }
                  if ($this->getPrice('net')) {
                    $return .= '<tr>'
                      .'<th>' . __('Net price','casasync') . '</th>'
                    .'<td>';
                    $return .= $this->getPrice('net', 'full');
                    $return .= '</td>'
                    .'</tr>';
                  }
                } else {
                  $return .= '<tr>'
                      .'<th>' . __('Rent price','casasync') . '</th>'
                    .'<td>';
                    $return .=  __('On Request', 'casasync');
                    $return .= '</td>'
                    .'</tr>';
                }
              }
              break;
              case 'casasync_archive_show_excerpt':
                if($this->getExcerpt()) {
                  $return .= '<tr><td colspan="2">' . $this->getExcerpt() . '</td></tr>';
                }
              break;
              case 'casasync_archive_show_availability':
                if ($this->getAvailabilityLabel()) {
                  $return .= '<tr>'
                    .'<th>' . __('Available','casasync') . '</th>'
                  .'<td>';
                  $return .= $this->getAvailabilityLabel();
                  $return .= '</td>'
                  .'</tr>';
                } else {
                  $return .= '<tr>'
                    .'<th>' . __('Available','casasync') . '</th>'
                  .'<td>';
                  $return .= __('On Request', 'casasync');
                  $return .= '</td>'
                  .'</tr>';
                }
              break;
            default:
              break;
          }
        }
        $return .= '</tbody>';
        $return .= '</table>';
      }
      return $return;
    }

    public function getPagination(){
      $return = '<div class="btn-group btn-group-justified casasync-single-pagination casasync-btn-justified hidden">'
        .'<a href="" class="btn btn-default casasync-single-next" role="button"><i class="fa fa-arrow-left"></i><span> ' . __('Previous','casasync') . '</span></a>'
        .'<a href="" class="btn btn-default casasync-single-archivelink" role="button">' . __('To list','casasync') . '</a>'
        .'<a href="" class="btn btn-default casasync-single-prev" role="button"><span>' . __('Next','casasync') . ' </span><i class="fa fa-arrow-right"></i></a>'
      .'</div>';
      return $return;
    }


    public function setContactEmails(){
      $emails = array(
        'inquiry'      => false,
        'remcat'       => false,
        'fallback'     => false
      );

      if ($this->seller_inquiry['email'] && get_option('casasync_request_per_mail')) {
        $emails["inquiry"] = $this->seller_inquiry['email'];
      }
      if (get_option('casasync_remCat_email') != '' && get_option('casasync_request_per_remcat')) {
        $emails["remcat"] = get_option('casasync_remCat_email');
      }
      if (get_option('casasync_request_per_mail_fallback') != false && get_option('casasync_request_per_mail_fallback_value') != '') {
        $emails["fallback"] = get_option('casasync_request_per_mail_fallback_value');
      }
      $this->contactEmails = $emails;

      return $this->contactEmails;
    }


    public function getContactEmails(){
      if ($this->contactEmails['inquiry'] !== true || $this->contactEmails['remcat'] !== true || $this->contactEmails['fallback'] !== true) {
        $this->setContactEmails();
      }
      return $this->contactEmails;
    }

    public function getContactform(){
      $emails = $this->getContactEmails();
      $i = 0;
      foreach ($emails as $k => $v) {
        if($v !== false) {
          $i++;
        }
      }
      if ($i > 0) {
        $rec = array();
        $emails['inquiry'] !== false ? $rec['inquiry'] = $emails['inquiry'] : null;
        switch (get_option('casasync_request_per_mail_fallback')) {
          case 'always':
            $emails['fallback'] !== false ? $rec['fallback'] = $emails['fallback'] : null;
            break;
          case 'fallback':
            if($emails['inquiry'] === false) {
              $emails['fallback'] !== false ? $rec['fallback'] = $emails['fallback'] : null;
            }
            break;
          default:
            break;
        }
        return do_shortcode( 
          '[casasync_contact 
          recipients="' . implode(';', $rec) . '"
          remcat="' . ($emails['remcat'] !== false ? $emails['remcat'] : '') . '"
          post_id="' . get_the_ID() . '"]'
        );
      }
    }

    public function getTabable(){
      $class = (get_option('casasync_load_css') == 'bootstrapv2') ? (' nav nav-tabs') : (null); // hack for bs2
      $nav = '<ul class="casasync-tabable-nav' . $class . '">';
      $navend = '</ul>';
      $content = '<div class="casasync-tabable-content">';
      $contentend = '</div>';
      
      //basics
      $nav .= '<li class="active"><a data-toggle="tab" href="#text_basics"><small>' . __("Base data", 'casasync') . '</small></a></li>';
      $content .= '<div class="casasync-tabable-pane active in" id="text_basics">';
        $content .= '<div class="casasync-basic-boxes">';
          $content .= $this->getBasicBoxes();
        $content .= '</div>';
        $content .= $this->getMap();
      $content .= '</div>';

      //Description
      $nav .= '<li><a data-toggle="tab" href="#text_description"><small>' . __('Description', 'casasync') . '</small></a></li>';
      $content .= '<div class="casasync-tabable-pane" id="text_description">';
      $content .= $this->content;
      $content .= '</div>';

      //details table
      $nav .= '<li><a data-toggle="tab" href="#text_numbers"><small>' . __("Specifications", 'casasync') . '</small></a></li>';
      $content .= '<div class="casasync-tabable-pane" id="text_numbers">';
        $content .= $this->getSpecificationsTable();
      $content .= '</div>';
      return $nav . $navend . $content . $contentend;

    }

    public function getPermalink(){
      return get_permalink();
    }

    public function getAddress($from, $singleline = false){
      switch ($from) {
        case 'seller':
          $address  = ($this->seller['street'] ? $this->seller['street'] . '<br>' : '');
          $address .= ($this->seller['postalcode'] ?  $this->seller['postalcode'] . ' ': '') . ($this->seller['locality'] ? $this->seller['locality'] : '') . ($this->seller['postalcode'] || $this->seller['locality'] ? '<br>' : '');
          $address .= $this->conversion->countrycode_to_countryname($this->seller['country']); 
          return $address;
          break;
        case 'property':
        if ($singleline === false) {
          $address  = ($this->address_street ? $this->address_street . ' ' . $this->address_streetnumber . '<br>' : '');
          $address .= ($this->address_postalcode ?  $this->address_postalcode . ' ': '') . ($this->address_locality ? $this->address_locality : '') . ($this->address_postalcode || $this->address_locality ? '<br>' : '');
          $address .= ($this->address_country_name ? $this->address_country_name : '');
        } else {
          $address = '';
          if(is_post_type_archive('casasync_property')) {
            if(get_option('casasync_archive_show_zip', '0') != '0') {
              $address .= ($this->address_postalcode ? $this->address_postalcode . ' ' : '');
            }
          } else {
            $address .= ($this->address_postalcode ? $this->address_postalcode . ' ' : '');
          }
          $address .= ($this->address_locality ? $this->address_locality : '');
          $address .= ($this->address_country ? ' (' . $this->address_country . ')' : '');
        }
          return $address;
        default:
          break;
      }
    }

    public function getMap() {
      $return = NULL;
      if ($this->getAddress('property')){
        if(get_option('casasync_single_use_zoomlevel') != '0'){
          $map_url = "https://maps.google.com/maps?f=q&amp;source=s_q&amp;hl=" . substr(get_locale(), 0, 2)  . "&amp;geocode=&amp;q=" . urlencode( str_replace(' ',', ', str_replace('<br>', ', ', $this->getAddress('property') ))) . "&amp;aq=&amp;ie=UTF8&amp;hq=&amp;hnear=" . urlencode( str_replace('<br>', ', ', $this->getAddress('property') )) . "&amp;t=m&amp;z=12";
          $map_url_embed = $map_url . '&amp;output=embed';
          $return = '<div class="casasync-hidden-xs"><div class="casasync-map" style="display:none" data-address="'. str_replace('<br>', ', ', $this->getAddress('property')) . '"><div id="map-canvas" style="width:100%; height:400px;" ></div><br /><small><a href="' . $map_url . '">' . __('View lager version', 'casasync') . '</a></small></div></div>';
          $return .= '<div class="casasync-visible-xs"><a class="btn btn-default btn-block" href="' . $map_url . '" target="_blank"><i class="fa fa-map-marker"></i> Auf Google Maps anzeigen</a></div>';
        }
      }
      return $return;
    }

    public function contactSellerByMailBox() {
      $emails = $this->getContactEmails();
      $i = 0;
      foreach ($emails as $k => $v) {
        if($v !== false) {
          $i++;
        }
      }
      if ($i > 0) {
        $html = '<div class="single-property-container">'
          .'<p class="casasyncContact"><i class="fa fa-envelope"></i> '
          .'<a href="#casasyncPropertyContactForm" id="casasyncContactAnchor">' . __('Contact provider directly', 'casasync') . '</a>'
        .'</p></div>';
        return $html;
      }
    }

    public function hasSeller(){
      if (
          $this->getAddress('seller')
          . $this->seller['legalname']
          . $this->seller['email']
          . $this->seller['phone_central']
          . $this->seller['phone_mobile']
          . $this->seller['fax']
        ) {
          return true;
        }
    }

    public function getSeller($title = true, $icon = true) {
      if($this->hasSeller()) {
        $return = '';
        if ($title) {
            $return  .= '<h3>'.($icon ? '<i class="fa fa-briefcase"></i> ' : '') . __('Provider' , 'casasync') . '</h3><address>';
        }
        $return .= ($this->seller['legalname'] != '') ? ('<strong>' . $this->seller['legalname'] . '</strong><br>') : ('');
        $return .= ($this->seller['brand'] != '') ? ($this->seller['brand'] . '<br>') : ('');
        $return .= $this->getAddress('seller');

        $return .= '<div class="casasync-seller-infos">';
        if(get_option('casasync_show_email_organisation')) {
          if($this->seller['email'] != '') {
            $objektlink = get_permalink();
            $id_number = ($this->reference_id) ? ($this->reference_id) : ($this->casa_id);
            $mailto = 'mailto:' . $this->seller['email'] . '?subject=Anfrage%20auf%20Objekt-Nr.%20' . $id_number .'&amp;body='
            .rawurlencode(__('I am interested concerning this property. Please contact me.', 'casasync')) . '%0A%0ALink%20' . $objektlink;
            $return .= '<p><span class="casasync-label">' . __('E-Mail', 'casasync') . '</span> ';
            $return .= '<span class="value break-word"> <a href="' . $mailto . '">' . $this->seller['email'] . '</a></span>';
          }
        }
        if($this->seller['phone_central'] != '') {
          $return .= '<p class="casasync-phone-central">'
            .'<span class="casasync-label">' . __('Phone', 'casasync')  . '</span>'
          .'<span class="value break-word"> ' . $this->seller['phone_central'] . '</span></p>';
        }
        /*if($this->seller['phone_mobile'] != '') {
          $return .= '<p class="casasync-phone-mobile">'
            .'<span class="casasync-label">' . __('Mobile', 'casasync')  . '</span>'
          .'<span class="value break-word"> ' . $this->seller['phone_mobile'] . '</span></p>';
        }*/
        if($this->seller['fax'] != '') {
          $return .= '<p class="casasync-phone-fax">'
            .'<span class="casasync-label">' . __('Fax', 'casasync')  . '</span>'
          .'<span class="value break-word"> ' . $this->seller['fax'] . '</span></p>';
        }
        $return .= '</div></address>';
        return $return;
      }
    }

    public function getSellerName() {
      if($this->seller['legalname'] != '') {
        return $this->seller['legalname'];
      }
    }

    public function hasSalesPerson(){
      if (
          $this->salesperson['givenname']
          . $this->salesperson['familyname']
          . $this->salesperson['email']
          . $this->salesperson['phone_direct']
          . $this->salesperson['phone_mobile']
        ) {
        return true;
      }
    }

    public function getSalesPerson($title = true, $icon = true) {
      if ($this->hasSalesPerson()) {
        $return = '<h3>'.($icon ? '<i class="fa fa-briefcase"></i> ' : '') . __('Contact person' , 'casasync') . '</h3><address>';
        if ($this->salesperson['givenname'] != '' && $this->salesperson['familyname'] != '') {
          $return .= '<p><strong>' . $this->salesperson['givenname'] . ' ' . $this->salesperson['familyname'] . '</strong>';
        }
        if ($this->salesperson['function'] != '') {
          $return .= '<br><i>' . $this->salesperson['function'] . '</i></p>';
        }
        if(get_option('casasync_show_email_person_view')) {
          if ($this->salesperson['email'] != '') {
            $objektlink = get_permalink();
            $id_number = ($this->reference_id) ? ($this->reference_id) : ($this->casa_id);
            $mailto = 'mailto:' . $this->salesperson['email'] . '?subject=Anfrage%20auf%20Objekt-Nr.%20' . $id_number . '&amp;body='
              . rawurlencode(__('I am interested concerning this property. Please contact me.', 'casasync'))
            .'%0A%0ALink:%20' . $objektlink;
            $return .= '<p><span class="casasync-label">' . __('Email', 'casasync') . '</span>'
            .'<span class="value break-word"> <a href="' . $mailto . '">' . $this->salesperson['email'] . '</a></span></p>';
          }
        }
        if($this->salesperson['phone_direct'] != '') {
          $return .= '<p class="casasync-phone-direct">'
            .'<span class="casasync-label">' . __('Phone direct', 'casasync')  . '</span>'
          .'<span class="value break-word"> ' . $this->salesperson['phone_direct'] . '</span></p>';
        }
        if($this->salesperson['phone_mobile'] != '') {
          $return .= '<p class="casasync-phone-mobile">'
            .'<span class="casasync-label">' . __('Mobile', 'casasync')  . '</span>'
          .'<span class="value break-word"> ' . $this->salesperson['phone_mobile'] . '</span></p>';
        }
        $return .= '</address>';
        return $return;
      }
    }

    public function getSalesPersonName() {
      if ($this->salesperson['givenname'] != '' && $this->salesperson['familyname'] != '') {
          return $this->salesperson['givenname'] . ' ' . $this->salesperson['familyname'];
        }
    }

    public function getShareWidget($name = false) {
      $return = false;
      switch ($name) {
        case 'facebook':
          if (get_option( 'casasync_share_facebook', false )) {
            $return .= '<div class="fb-like" data-send="true" data-layout="button_count" data-href="http://' . $_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'] . '" data-width="200" data-show-faces="true"></div>';
          }
          break;
        default:
          $return = false;
      }
      return $return;
    }

    public function getAllShareWidgets() {
      $return = false;
      if (get_option( 'casasync_share_facebook', false )) {
        $return = '<h3><i class="fa fa-share-square"></i> ' . __('Share', 'casasync') . '</h3>';
        $return .= '<div class="fb-like" data-send="true" data-layout="button_count" data-href="http://' . $_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'] . '" data-width="200" data-show-faces="true"></div><br>';
      }

      if(get_option( 'casasync_share_googleplus', false )) {
        $return .= '<div class="g-plusone" data-size="medium"></div><br>';
      }

      if(get_option( 'casasync_share_twitter', false )) {
        $return .= '<a href="https://twitter.com/share" class="twitter-share-button" data-lang="de">Twittern</a><br>';
      }
      add_action('wp_footer', array($this, 'getShareWidgetsScripts'), 30);
      return $return;
    }

    public function getShareWidgetsScripts() {
      $return = null;
      if (get_option( 'casasync_share_facebook', false )) {
        $return .= '<div id="fb-root"></div><script>(function(d, s, id) {'
          .'var js, fjs = d.getElementsByTagName(s)[0];'
          .'if (d.getElementById(id)) return;'
          .'js = d.createElement(s); js.id = id;'
          .'js.src = "//connect.facebook.net/' . str_replace('-','_',get_bloginfo('language')) . '/all.js#xfbml=1";'
          .'fjs.parentNode.insertBefore(js, fjs);'
        ."}(document, 'script', 'facebook-jssdk'));</script>";
      }
      if(get_option( 'casasync_share_googleplus', false )) {
         $return .= '<script type="text/javascript">'
          .'(function() {'
          ."var po = document.createElement('script'); po.type = 'text/javascript'; po.async = true;"
          ."po.src = 'https://apis.google.com/js/platform.js';"
          ."var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(po, s);"
          ."})();"
        .'</script>';
      }
      if(get_option( 'casasync_share_twitter', false )) {
        $return .= '<script type="text/javascript">'
          .'!function(d,s,id){'
          .'var js,fjs=d.getElementsByTagName(s)[0],p=/^http:/.test(d.location)'
          ."?'http':'https';if(!d.getElementById(id)){js=d.createElement(s);"
          ."js.id=id;js.src=p+'://platform.twitter.com/widgets.js';fjs.parentNode.insertBefore(js,fjs);}}"
          ."(document, 'script', 'twitter-wjs');"
        ."</script>";
      }
      return print_r($return);
    }

    public function getPrice($type = 'auto', $format = 'num', $byrequest = true){
      $timesegment_labels = array(
        'm' => __('month', 'casasync'),
        'w' => __('week', 'casasync'),
        'd' => __('day', 'casasync'),
        'y' => __('year', 'casasync'),
        'h' => __('hour', 'casasync')
      );

      switch ($type) {
        case 'sales':
          if($this->prices['sales']) {
            $price = $this->prices['sales'];
          }
          break;
        case 'net':
          if($this->prices['net']) {
            $price = $this->prices['net'];
          }
          break;
        case 'gross':
          if($this->prices['gross']) {
            $price = $this->prices['gross'];
          }
          break;
        case 'auto':
        default:
          if ($this->main_basis == 'buy') {
            if ($this->prices['sales']) {
              $price = $this->prices['sales'];
            }
          }
          if ($this->main_basis == 'rent') {
            if ($this->prices['gross']['num'] || $this->prices['net']['num']) {
              if ($this->prices['gross']) {
                $price = $this->prices['gross'];
              }
              if ($this->prices['net']['num']) {
                $price = $this->prices['net'];
              }
            }
          }
          break;
      }

      $return = '';
      switch ($format) {
        case 'num':
          $return .= $price['num'];
          break;
        case 'currency':
        case 'formated':
        case 'full':
          $return .= $this->price_currency . ' ';
        case 'formated':
        case 'full':
          if(array_key_exists('num', $price) && $price['num'] != NULL) {
            $return .= number_format(
                        round($price['num']), 
                        0, 
                        '', 
                        '\''
                      ) . '.–';
          }
        case 'full':
            $sep     = '/';
            if (
                array_key_exists('propertysegment', $price) 
                && $price['propertysegment'] != NULL 
                && $price['propertysegment'] != 'full'
            ) {
                $return .= '&nbsp;' . $sep . '&nbsp;' . substr($price['propertysegment'], 0, -1) . '<sup>2</sup>';              
            }
            if (
                array_key_exists('timesegment', $price)     
                && $price['timesegment'] != NULL 
                && $price['timesegment'] != 'infinite'
            ) {
                $return .= '&nbsp;' . $sep . '&nbsp;' . str_replace($price['timesegment'], $timesegment_labels[$price['timesegment']], $price['timesegment']);
            }
      }
      if ($return) {
        $return = '<span style="white-space: nowrap;">' . $return . '</span>';
      }
      return $return;
    }

    public function getExtraCosts($name) {
      $return = null;
      if(!empty($this->prices['extra_costs']) && is_array($this->prices['extra_costs'])) {
        foreach ($this->prices['extra_costs'] as $key => $extraCost) {

          $timesegment     = '';
          $propertysegment = '';
          $offer_types = get_terms('casasync_salestype');

          foreach ($offer_types as $k => $v) {
            $offer_type = $v->name = 'rent' ? 'rent' : 'buy';
          }

          $timesegment = $extraCost['timesegment'];
          if (!in_array($timesegment, array('m','w','d','y','h','infinite'))) {
            $timesegment = ($offer_type == 'rent' ? 'm' : 'infinite');
          }

          $propertysegment = $extraCost['propertysegment'];
          if (!in_array($propertysegment, array('m2','km2','full'))) {
            $propertysegment = 'full';
          }

          $timesegment_labels = array(
            'm' => __('month', 'casasync'),
            'w' => __('week', 'casasync'),
            'd' => __('day', 'casasync'),
            'y' => __('year', 'casasync'),
            'h' => __('hour', 'casasync')
          );

          $extraPrice = array(
            'value' => '<span style="white-space: nowrap;">'.
              (isset($extraCost['currency']) && $extraCost['currency'] ? $extraCost['currency'] . '&nbsp;' : '') .
              number_format(round($extraCost['price']), 0, '', '\'') . '.&#8211;' .
              ($propertysegment != 'full' ? '&nbsp;/&nbsp;' . substr($propertysegment, 0, -1) . '<sup>2</sup>' : '') .
              ($timesegment != 'infinite' ? '&nbsp;/&nbsp;' . $timesegment_labels[(string) $timesegment] : '')
              . '</span>'
            ,
            'title' => (string) $extraCost['title']
          );

          $return = false;
          if ($extraCost['title'] == $name) {
            $return = $extraPrice['value'];
          }
        }
      }

      return $return;
    }

    public function getNumval($name){
      switch ($name) {
        case 'floor':
        case 'number_of_floors':
        case 'number_of_rooms':
          return (isset($this->numvals[$name]) ? ($this->numvals[$name]['value']) : false);
          break;
        case 'surface_usable':
        case 'surface_living':
        case 'surface_property':
          if (isset($this->numvals[$name])) {
            preg_match_all('/^(\d+)(\w+)$/', $this->numvals[$name]['value'], $matches);
            $number = implode($matches[1]);
            $letter = implode($matches[2]);
            return number_format($number, 0, '.', "'") . $letter . '<sup>2</sup>';
          } else {
            return false;
          }
          break;
        case 'volume':
          return (isset($this->numvals[$name]) ? ($this->numvals[$name]['value'] . '<sup>3</sup>') : false);
          break;
        case 'distances':
          $distances = array();
          foreach ($this->conversion->casasync_get_allDistanceKeys() as $distance_key) {
            $distance = get_post_meta( get_the_ID(), $distance_key, $single = true );
            $distance_arr = $this->casasync_distance_to_array($distance);
            if ($distance) {
              $title = $this->conversion->casasync_convert_distanceKeyToLabel($distance_key);
              $distances[$distance_key] = array('title' => $title, 'value' => implode($distance_arr, ' ' . __('and','casasync') . ' '));
            }
          }
          return $distances;
          break;
        default:
          return (isset($this->numvals[$name]) ? $this->numvals[$name]['value'] : false);
          break;
      }
    }

     public function getAllNumvals($sort = NULL) {
      $return = array();
      foreach ($this->numvals as $numval) {
        if (array_search($numval["key"], $sort) !== false) {
          $return[array_search($numval["key"], $sort)] = $numval;
        } else {
          $array_keys_to_num = !empty($return) ? max(array_keys($return)) : 0;
          $h_key = $array_keys_to_num + 100;
          $return[$h_key] = $numval;
        }
      }
      ksort($return);
      return $return;
    }

    public function getAllFeatures() {
      $html = NULL;
      if ($this->features) {
        foreach ($this->features as $key => $value) {
          if (isset($key)) {
            $html .= '<span class="casasync-label"><i class="fa fa-check"></i> '
              . __($this->conversion->casasync_convert_featureKeyToLabel($value['key']), "casasync") 
            .'</span> ';
          }
        }
      }
      return $html;
    }

    public function getAllDocuments() {
      $html = false;
      $count = 1;
      $args = array(
        'post_parent' => get_the_ID(),
        'post_type' => 'attachment',
      );
      $attachments = get_children( $args );
      if($attachments) {
        $html .= '<ul class="casasync-unstyled">';
        foreach ( (array) $attachments as $attachment_id => $attachment ) {
          if(strpos($attachment->post_mime_type, 'image') === false ) {
            $url = wp_get_attachment_url( $attachment_id );
            $title = (is_numeric($attachment->post_title)) ? (__('Document', 'casasync') . ' ' . $count) : ($attachment->post_title);
            $html .= '<li><a href="' . $url . '" title="' . $title . '" target="_blank" >' . $title . '</a></li>';
            $count++;
          }
        }
        $html .= '</ul>';
      }
      if($count > 1) {
        return $html;
      }
    }

    public function getProvidedURL() {
      $html = NULL;
      $providedURL = get_post_meta(get_the_ID(), 'the_urls');
      if($providedURL) {
        if(substr($providedURL[0][0]['href'], 0, 4) != "http") {
          $providedURL[0][0]['href'] = 'http://' . $providedURL[0][0]['href'];
        }
        $html = '<a href="' . $providedURL[0][0]['href'] . '" title="' . $providedURL[0][0]['title'] . '" target="_blank">' . $providedURL[0][0]['label'] . '</a>';
      }
      return $html;
    }


    public function getFeaturedImage($link = true) {
      $return = NULL;
      $pid = get_the_ID();
      if (has_post_thumbnail($pid)) {
        $url = ($link ? get_permalink($pid) : '#');
        $return .= '<a href ="' . $url . '" class="casasync-thumbnail" style="position:relative;">';
        $return .= $this->getAvailability();
        $return .= get_the_post_thumbnail($pid, 'casasync-thumb');
        $return .= '</a>';
      }
      return $return;
    }

    public function getAvailability() {
      $return = NULL;
      if (isset($this->availability) && $this->availability) {
        switch ($this->availability) {
          case 'active':
            $availability_converted_slug = 'active';
            break;
          case 'reference':
            $availability_converted_slug = 'reference';
            break;
          case 'reserved':
            $availability_converted_slug = 'reserved';
            break;
          case 'taken':
            if ($this->main_basis == 'rent') {
              $availability_converted_slug = 'rented';
            } else {
              $availability_converted_slug = 'sold';
            }
            break;
          default:
            $availability_converted_slug = $this->availability;
            break;
        }
        $availability_converted_name = $this->conversion->casasync_convert_availabilityKeyToLabel($availability_converted_slug);

        $return .= '<div class="availability-outerlabel">';
        $return .= '<div class="availability-label availability-label-' . $this->availability . '">' . __($availability_converted_name, 'casasync') . '</div>';
        $return .= '</div>';
      }
      return $return;
    }

    public function getAllDistances() {
      $distances = $this->getNumval('distances');
      $html = NULL;
      if($distances) {
        $html .= '<ul class="casasync-unstyled">';
        foreach ($distances as $key => $value) {
          $html .= '<li><strong>' . $value['title'] . ': </strong>';
          $html .= '<span>' . $value['value'] . '</span></li>';
        }
        $html .= '</ul>';
      }
      return $html;
    }

    public function casasync_distance_to_array($distance){
      if ($distance) {
        $distance_quirk = trim($distance,"[");   
        $distance_quirk = trim($distance_quirk,"]");
        $distance_arr = explode(']+[', $distance_quirk);
        return $distance_arr;
      }
      return false;
    }
      
    public function setTagValue($tagname){
      switch ($tagname) {
        case 'title':
            $this->tag[$tagname] = get_the_title();
          break;
        default:
          $this->tag[$tagname] = false;
          break;
      }
      return $this->tag[$tagname];

    }

    public function getTagValue($tagname){
      if (isset($this->tag[$tagname])) {
        return $this->tag[$tagname];
      } else {
        return $this->setTagValue($tagname);
      }
    }


    public function getTextBetweenTags($string, $tagname){
      $d = new DOMDocument();
      $d->loadHTML($string);
      $return = array();
      foreach($d->getElementsByTagName($tagname) as $item){
          $return[] = $item->textContent;
      }
      return (array_key_exists(0, $return) ? $return[0] : '');
    }

  }  
