<?php
  namespace CasaSync;

  class Single {  
    public $conversion = null;

    public $attachments = array();
    public $documents = array();
    public $plans = array();
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
    public $seller = array(
      'fallback' => false,
      'country' => '',
      'locality' => '',
      'region' => '',
      'postalcode' => '',
      'street' => '',
      'legalname' => '',
      'email' => '',
      'fax' => '',
      'phone_direct' => '',
      'phone_central' => '',
      'phone_mobile' => '',
    );
    public $salesperson = array(
      'function' => '',
      'givenname' => '',
      'familyname' => '',
      'email' => '',
      'fax' => '',
      'phone_direct' => '',
      'phone_central' => '',
      'phone_mobile' => '',
      'gender' => '',
      'honorific' => false,
    );

    public function __construct($post){ 
      $this->conversion = new Conversion;
      $this->setProperty($post);
    }  

    public function getPrevNext($query){
      global $wpdb;

      $w_categories = array();
      foreach ($query['categories'] as $slug => $options) {
        if ($options['checked']) {
          $w_categories[] = $options['value'];
        }
      }

      $w_locations = array();
      foreach ($query['locations'] as $slug => $options) {
        if ($options['checked']) {
          $w_locations[] = $options['value'];
        }
      }

      $w_salestypes = array();
      foreach ($query['salestypes'] as $slug => $options) {
        if ($options['checked']) {
          $w_salestypes[] = $options['value'];
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
        'tax_query' => $taxquery_new
      );
      $the_query = new \WP_Query( $args );

      $prev = false;
      $next = false;
      while( $the_query->have_posts() ) {
        $the_query->next_post();
        if ($the_query->post->post_name == $this->property->post_name) {
          $next_post = $the_query->next_post();
          if ($next_post) {
              $next = $next_post;
              break;
          }
        }
        $prev = $the_query->post;
      }
     

      $prevnext = array(
        'nextlink' => ($prev ? '/property/'.$prev->post_name.'/' : 'no'), 
        'prevlink' => ($next ? '/property/'.$next->post_name.'/' : 'no')
      );
      return $prevnext;

    }


    public function setProperty($post){
      $this->property = $post;

      $this->attachments = get_posts( array(
        'post_type'                => 'attachment',
        'posts_per_page'           => -1,
        'post_parent'              => $post->ID,
        //'exclude'                => get_post_thumbnail_id(),
        'casasync_attachment_type' => 'image',
        'orderby'                  => 'menu_order',
        'order'                    => 'ASC'
      ) ); 

      $this->documents = get_posts( array(
        'post_type'                => 'attachment',
        'posts_per_page'           => -1,
        'post_parent'              => $post->ID,
        //'exclude'                => get_post_thumbnail_id(),
        'casasync_attachment_type' => 'document',
        'orderby'                  => 'menu_order'
      ) ); 

      $this->plans = get_posts( array(
        'post_type'                => 'attachment',
        'posts_per_page'           => -1,
        'post_parent'              => $post->ID,
        //'exclude'                => get_post_thumbnail_id(),
        'casasync_attachment_type' => 'plan'
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
      $this->customer_id = $casa_id_arr[0];
      $this->property_id = $casa_id_arr[1];

      $this->reference_id = get_post_meta( get_the_ID(), 'casasync_referenceId', $single = true );

      $start = get_post_meta( get_the_ID(), 'casasync_start', $single = true );

      $categories = wp_get_post_terms( get_the_ID(), 'casasync_category'); 
      $this->categories_names = array();
      foreach ($categories as $category) {
        $this->categories_names[] = $this->conversion->casasync_convert_categoryKeyToLabel($category->name);
      } 

      $floors = get_post_meta( get_the_ID(), 'casasync_floors', $single = true ); 
      if ($floors) {
        $floors_quirk = trim($floors,"[");   
        $floors_quirk = trim($floors_quirk,"]");
        $floors_arr = explode(']+[', $floors_quirk);
        $largest_val = 0;
        $largest_key = 0;
        foreach ($floors_arr as $key => $value) {
          if ((int)$value > $largest_val ) {
            $largest_val = (int)$value;
            $largest_key = $key;
          }
          $this->floors[] = $value . '. Stock' . ($value == '0' ? ' (EG)' : '');
        }
        if (isset($this->floors[$largest_key])) {
          $this->floors[$largest_key] = $floors_arr[$largest_key] . '. Stock (OG)';
        }
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

      $sales['num'] = get_post_meta( get_the_ID(), 'price', $single = true );
      $sales['propertysegment'] = get_post_meta( get_the_ID(), 'price_propertysegment', $single = true );
      $sales['timesegment'] = get_post_meta( get_the_ID(), 'price_timesegment', $single = true );

      $net['num'] = get_post_meta( get_the_ID(), 'grossprice', $single = true );
      $net['propertysegment'] = get_post_meta( get_the_ID(), 'grossprice_propertysegment', $single = true );
      $net['timesegment'] = get_post_meta( get_the_ID(), 'grossprice_timesegment', $single = true );

      $gross['num'] = get_post_meta( get_the_ID(), 'netPrice', $single = true );
      $gross['propertysegment'] = get_post_meta( get_the_ID(), 'netPrice_propertysegment', $single = true );
      $gross['timesegment'] = get_post_meta( get_the_ID(), 'netPrice_timesegment', $single = true );

      $extra_costs_json =  get_post_meta( get_the_ID(), 'extraPrice', $single = true ); 
      $extra_costs_arr = array();
      if ($extra_costs_json) {
        $extra_costs_arr = json_decode($extra_costs_json, true);
      }

      $this->prices = array(
        'sales' => $sales,
        'net' => $net,
        'gross' => $gross,
        'extra_costs' => $extra_costs_arr
      );

      $this->urls = json_decode(get_post_meta( get_the_ID(), 'casasync_urls', $single = true ), true);

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

      /*$own_seller_email = false;
      $emails = array();
      if (!get_option('casasync_seller_email_block')) {
        $email = get_post_meta( get_the_ID(), 'seller_inquiry_person_email', true );
        if ($email) {
          $firstname = get_post_meta( get_the_ID(), 'seller_inquiry_person_givenname', true );
          $lastname = get_post_meta( get_the_ID(), 'seller_inquiry_person_familyname', true );
          $gender    = get_post_meta( get_the_ID(), 'seller_inquiry_person_gender', true);
          if ($gender == 'F') {
            $honorific = 'Frau';
          } elseif ($gender == 'M') {
            $honorific = 'Herr';
          } else {
            $honorific = false;
          }
          $name = ($honorific ? $honorific . ' ' : '') . ($firstname ? $firstname . ' ' : '') . $lastname;
          $emails[] = ($name ? $name : 'Kontaktperson') . ':' . $email;
          $own_seller_email = true;
        }
      }
      if (get_option('casasync_sellerfallback_email_use') != 'never') {
        if (($own_seller_email == false && get_option('casasync_sellerfallback_email_use') == 'fallback') || (get_option('casasync_sellerfallback_email_use') == 'always')) {
          $email = get_option('casasync_sellerfallback_email');
          if ($email) {
            $name = get_option('casasync_sellerfallback_legalname');
            $emails[] = ($name ? $name : 'Hauptanbieter') . ':' . $email;
          }
        }
      }

      $has_remcat = false;
      if (get_option('casasync_remCat', false ) && get_option('casasync_remCat_email', false )) {
        $has_remcat = true; 
      }*/

      if (get_option('casasync_seller_show') == 1) {
        $this->seller['fallback']   = true;
        $this->seller['country']    = get_post_meta( get_the_ID(), 'seller_org_address_country', $single = true );
        $this->seller['locality']   = get_post_meta( get_the_ID(), 'seller_org_address_locality', $single = true );
        $this->seller['region']     = get_post_meta( get_the_ID(), 'seller_org_address_region', $single = true );
        $this->seller['postalcode'] = get_post_meta( get_the_ID(), 'seller_org_address_postalcode', $single = true );
        $this->seller['street']     = get_post_meta( get_the_ID(), 'seller_org_address_streetaddress', $single = true );

        //$seller_address  = ($seller_address_street ? $seller_address_street . '<br>' : '');
        //$seller_address .= ($seller_address_postalcode ?  $seller_address_postalcode . ' ': '') . ($seller_address_locality ? $seller_address_locality : '') . ($seller_address_postalcode || $seller_address_locality ? '<br>' : '');
        //$seller_address .= countrycode_to_countryname($seller_address_country); 

        $this->seller['legalname']     = get_post_meta( get_the_ID(), 'seller_org_legalname', $single = true );
        $this->seller['email']         = get_post_meta( get_the_ID(), 'seller_org_email', $single = true );
        $this->seller['fax']           = get_post_meta( get_the_ID(), 'seller_org_fax', $single = true );
        $this->seller['phone_direct']  = get_post_meta( get_the_ID(), 'seller_org_phone_direct', $single = true );
        $this->seller['phone_central'] = get_post_meta( get_the_ID(), 'seller_org_phone_central', $single = true );
        $this->seller['phone_mobile']  = get_post_meta( get_the_ID(), 'seller_org_phone_mobile', $single = true );
        
        /*if (
          $seller_address 
          . $sellerlegalname
          . $selleremail
          . $sellerfax
          . $sellerphone_direct
          . $sellerphone_central 
          . $sellerphone_mobile
        ) {
          $own_seller = true;
          $has_seller = true;
        }*/

      }
      if (get_option('casasync_sellerfallback_show') == 1) {
        if (!$this->hasSeller()) {
          $this->seller['country']    = get_option('casasync_sellerfallback_address_country');
          $this->seller['locality']   = get_option('casasync_sellerfallback_address_locality');
          $this->seller['region']     = get_option('casasync_sellerfallback_address_region');
          $this->seller['postalcode'] = get_option('casasync_sellerfallback_address_postalcode');
          $this->seller['street']     = get_option('casasync_sellerfallback_address_street');

          //$seller_address  = ($seller_address_street ? $seller_address_street . '<br>' : '');
          //$seller_address .= ($seller_address_postalcode ?  $seller_address_postalcode . ' ': '') . ($seller_address_locality ? $seller_address_locality : '') . ($seller_address_postalcode || $seller_address_locality ? '<br>' : '');
          //$seller_address .= countrycode_to_countryname($seller_address_country); 

          $this->seller['legalname']     = get_option('casasync_sellerfallback_legalname');
          $this->seller['email']         = get_option('casasync_sellerfallback_email');
          $this->seller['fax']           = get_option('casasync_sellerfallback_fax');
          $this->seller['phone_direct']  = get_option('casasync_sellerfallback_phone_direct');
          $this->seller['phone_central'] = get_option('casasync_sellerfallback_phone_central');
          $this->seller['phone_mobile']  = get_option('casasync_sellerfallback_phone_mobile');

          /*if (
            $seller_address 
            . $sellerlegalname
            . $selleremail
            . $sellerfax
            . $sellerphone_direct
            . $sellerphone_central 
            . $sellerphone_mobile
          ) {
            $own_seller = false;
            $has_seller = true;
          }*/
        }
      }
      

      $this->salesperson['function']        = get_post_meta( get_the_ID(), 'seller_person_function', true);
      $this->salesperson['givenname']       = get_post_meta( get_the_ID(), 'seller_person_givenname', true);
      $this->salesperson['familyname']      = get_post_meta( get_the_ID(), 'seller_person_familyname', true);
      $this->salesperson['email']           = get_post_meta( get_the_ID(), 'seller_person_email', true);
      $this->salesperson['fax']             = get_post_meta( get_the_ID(), 'seller_person_fax', true);
      $this->salesperson['phone_direct']    = get_post_meta( get_the_ID(), 'seller_person_phone_direct', true);
      $this->salesperson['phone_central']   = get_post_meta( get_the_ID(), 'seller_person_phone_central', true);
      $this->salesperson['phone_mobile']    = get_post_meta( get_the_ID(), 'seller_person_phone_mobile', true);
      $this->salesperson['gender']          = get_post_meta( get_the_ID(), 'seller_person_phone_gender', true);
      if ($this->salesperson['gender'] == 'F') {
        $this->salesperson['honorific'] = 'Frau';
      } elseif ($this->salesperson['gender'] == 'M') {
        $this->salesperson['honorific'] = 'Herr';
      }

      $this->availability = get_post_meta( get_the_ID(), 'availability', $single = true );
      $this->availability_label = get_post_meta( get_the_ID(), 'availability_label', $single = true );
      if ($this->availability && !$this->availability_label) {
        $this->availability_label = __($this->availability, 'casasync');
      }
    }

    public function getGallery($size = 'large'){

      if ($this->attachments) {
        $return = '<div id="slider_'.get_the_ID().'" class="casasync-carousel slide" data-ride="carousel" data-interval="false">';
          
          //indicators
          $return .= '<ol class="carousel-indicators">';
          $i = 0;
          foreach ($this->attachments as $attachment) {
            $return .= '<li data-target="#slider_'.get_the_ID().'" data-slide-to="'.$i.'" class="'.($i==0?'active':'').'"></li>';  
            $i++;
          }
          $return .= '</ol>';

          //Wrapper for slides
          $return .= '<div class="casasync-carousel-inner">';
            $i = 0;
            foreach ($this->attachments as $attachment) {
              $return .= '<div class="item '.($i==0?'active':'').'">';
                $img     = wp_get_attachment_image( $attachment->ID, 'full', true, array('class' => 'carousel-image') );
                $img_url = wp_get_attachment_image_src( $attachment->ID, 'full' );
                $return .= '<a href="' . $img_url[0] . '" class="casasync-fancybox" rel="group">' . $img . '</a>';
                if ($size == 'large') {
                  $return .= '<div class="casasync-carousel-caption">';
                    $return .= '<p>' . $attachment->post_excerpt . '</p>';
                  $return .= '</div>';
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

    public function getTitle(){
      return get_the_title();
    }


    public function getBasicBoxes(){
      $content = '<div class="casasync-basic-box"><div>';
        $content .= '<h4>'.implode(', ', $this->categories_names).'</h4>';
        if ($this->getNumval('number_of_rooms')){
          $content .= __('Number of rooms:', 'casasync') . ' ' . $this->getNumval('number_of_rooms') . '<br>';
        }
        if ($this->floors && !isset($this->floors[0])){
          $content .= __("Floor:", 'casasync');
          $content .= $this->floors[0];
        }
        if ($this->getNumval('surface_living')){
          $content .= __("Living space:", 'casasync') . ' ' . $this->getNumval('surface_living');
        }
      $content .= '</div></div>';
      $content .= '<div class="casasync-basic-box"><div>';
        $content .= '<h4>' . __("Address", 'casasync') . '</h4>';
        if ($this->getAddress('property')){
          $content .= $this->getAddress('property');  
        };
      $content .= '</div></div>';
      $content .= '<div class="casasync-basic-box"><div>';
        $content .= '<h4>' . implode(', ', $this->basises) . '</h4>';
        if ($this->main_basis == 'buy') {
          if ($this->getPrice('sales')) {
            $content .= $this->getPrice('sales', 'full');
          } else {
            $content .= __('By Request', 'casasync');
          }
        }
        if ($this->main_basis == 'rent') {
          if ($this->getPrice('gross') || $this->getPrice('net')) {
            if ($this->getPrice('gross')) {
              $content .= $this->getPrice('gross', 'full') . ' ' . __('(gross)', 'casasync') . '<br>';
            }
            if ($this->getPrice('net')) {
              $content .= $this->getPrice('net', 'full') . ' ' . __('(net)', 'casasync') . '<br>';
            }
          } else {
            $content .= __('By Request', 'casasync');
          }
        }
      $content .= '</div></div>';
      return $content;
    }

    public function getSpecificationsTable(){
      $content = '<h3>' . __('Offer','casasync'). '</h3>';
      $content .= '<table class="table">';
      if ($this->main_basis == 'buy') {
        $content .= '<tr>'
          .'<td width="25%">' . __('Sales price', 'casasync') . '</td>'
        .'<td width="75%">';
        $content .= $this->getPrice('sales') ? $this->getPrice('sales', 'full') : __('By Request', 'casasync');
        $content .= '</td>'
        .'</tr>';
      }
      if ($this->main_basis == 'rent') {
        if ( $this->getPrice('gross') || $this->getPrice('net')  ) {
          if ($this->getPrice('gross')) {
            $content .= '<tr>'
              .'<td width="25%">' . __('Gross price','casasync') . '</td>'
            .'<td width="75%">';
            $content .= $this->getPrice('gross', 'full');
            $content .= '</td>'
            .'</tr>';
          }
          if ($this->getPrice('net')) {
            $content .= '<tr>'
              .'<td width="25%">' . __('Net price','casasync') . '</td>'
            .'<td width="75%">';
            $content .= $this->getPrice('net', 'full');
            $content .= '</td>'
            .'</tr>';
          }
        } else {
          $content .= '<tr>'
              .'<td width="25%">' . __('Rent price','casasync') . '</td>'
            .'<td width="75%">';
            $content .=  __('By Request', 'casasync');
            $content .= '</td>'
            .'</tr>';
        }
      }
      if ($this->getExtraCosts('Nebenkosten')) {
        $content .= '<tr>
          <td width="25%"> ' . __('Additional costs', 'casasync') . '</td>'
          .'<td width="75%">' . $this->getExtraCosts('Nebenkosten') . '</td>'
        .'</tr>';
      }
      $content .= '</table>';

      /****/
      if ($this->numvals || $this->getAddress() || $this->reference_id || $this->property_id) {
        $content .= '<h3>' . __('Property','casasync') . '</h3>';
        $content .= '<table class="table">';
        $reference_or_property_id = ($this->reference_id) ? ($this->reference_id) : ($this->property_id);
        if($reference_or_property_id) {
          $content .= '<tr>
            <td width="25%">' . __('Reference','casasync') .'</td>'
            .'<td width="75%">' . $reference_or_property_id . '</td>'
          .'</tr>';
        }

        if($this->getAddress('property')) {
          $content .= '<tr>
            <td width="25%">' . __('Address','casasync') . '</td>'
            .'<td width="75%">' . $this->getAddress('property') . '</td>'
          .'</tr>';
        }

        $all_numvals = $this->getAllNumvals(array('surface_usable', 'surface_living', 'surface_property'));

        foreach ($all_numvals as $numval) {
          $content .= '<tr>
            <td width="25%">' . __($numval['title'], 'casasync') . '</td>'
            .'<td width="75%">' . $this->getNumval($numval["key"]) . '</td>'
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
      if ($this->getAllDistances()) {
        $content .= '<div class="casasync_distances">';
        $content .= '<h3>' . __('Distances','casasync') . '</h3>';
        $content .= $this->getAllDistances();
        $content .= '</div>';
      }

      return $content;
    }

    public function getQuickInfosTable() {
      $return = NULL;
      if ($this->getAddress('property')){
        $return .= '<table class="table">';
        $return .= '<tbody>';
        if ($this->getAddress('property')){
          $return .= '<tr>'
            .'<th>' . __('Locality', 'casasync') . '</th>'
            .'<td>' . $this->getAddress('property', true) . '</td>'
          .'</tr>';
        }
        if ($this->getNumval('number_of_rooms')) {
          $return .= '<tr>'
            .'<th>' . __('Number of rooms', 'casasync') . '</th>'
            .'<td>' . $this->getNumval('number_of_rooms') . '</td>'
          .'</tr>';
        }
        if ($this->getNumval('surface_living')) {
          $return .= '<tr>'
            .'<th>' . __('Living space', 'casasync') . '</th>'
            .'<td>' . $this->getNumval('surface_living') . '</td>'
          .'</tr>';
        }
        if ($this->main_basis == 'buy') {
          $return .= '<tr>'
            .'<th>' . __('Sales price','casasync') . '</th>'
          .'<td>';
          $return .= $this->getPrice('sales') ? $this->getPrice('sales', 'full') : __('By Request', 'casasync');
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
              $return .=  __('By Request', 'casasync');
              $return .= '</td>'
              .'</tr>';
          }
        }
        $return .= '</tbody>';
        $return .= '</table>';
      }
      return $return;
    }

    public function getPagination(){
      $return = '<div class="btn-group btn-group-justified casasync-single-pagination">'
        .'<a href="" class="btn btn-default casasync-single-next" role="button"><i class="fa fa-arrow-left"></i> ' . __('Previous','casasync') . '</a>'
        .'<a href="" class="btn btn-default casasync-single-archivelink" role="button">' . __('To list','casasync') . '</a>'
        .'<a href="" class="btn btn-default casasync-single-prev" role="button">' . __('Next','casasync') . ' <i class="fa fa-arrow-right"></i></a>'
      .'</div>';
      return $return;
    }

    public function getEmails(){
      $emails = array();
      if ($this->seller['email']) {
        $emails[] = (isset($this->seller['firstname']) ? $this->seller['firstname'] : null ) . ' '
          . (isset($this->seller['lastname']) ? $this->seller['lastname'] : null) .':'
        . (isset($this->seller['email']) ? $this->seller['email'] : null);
      }
      return $emails;
    }

    public function getContactform(){
      return do_shortcode( '[casasync_contact recipients="' . implode(';', $this->getEmails()) . '" post_id="' . get_the_ID() . '"]' );
    }

    public function getTabable(){
      $nav = '<ul class="casasync-tabable-nav">';
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
      $nav .= '<li><a data-toggle="tab" href="#text_description">&nbsp;&#9998;&nbsp;<small>' . __('Description', 'casasync') . '</small></a></li>';
      $content .= '<div class="casasync-tabable-pane" id="text_description">';
      $content .= $this->content;
      $content .= '</div>';

      //details table
      $nav .= '<li><a data-toggle="tab" href="#text_numbers"><i class="fa fa-file-o"></i> <small>' . __("Specifications", 'casasync') . '</small></a></li>';
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
          $address  = ($this->address_postalcode ? $this->address_postalcode . ' ' : '') . ($this->address_locality ? $this->address_locality : '') . ', ';
          $address .= ($this->address_region ? $this->address_region : '') . ' ' . ($this->address_country ? '(' . $this->address_country . ')' : '');
        }
          return $address;
        default:
          break;
      }
    }

    public function getMap() {
      $return = NULL;
      if ($this->getAddress('property')){ 
          $map_url = "https://maps.google.com/maps?f=q&amp;source=s_q&amp;hl=" . substr(get_locale(), 0, 2)  . "&amp;geocode=&amp;q=" . urlencode( str_replace(' ',', ', str_replace('<br>', ', ', $this->getAddress('property') ))) . "&amp;aq=&amp;ie=UTF8&amp;hq=&amp;hnear=" . urlencode( str_replace('<br>', ', ', $this->getAddress('property') )) . "&amp;t=m&amp;z=14&amp;output=embed";
          $return = '<div class="hidden-xs"><div class="casasync-map" style="display:none" data-address="'. str_replace('<br>', ', ', $this->getAddress('property')) . '"><div id="map-canvas" style="width:100%; height:400px;" ></div><br /><small><a href="' . $map_url . '" class="casasync-fancybox" data-fancybox-type="iframe">' . __('View lager version', 'casasync') . '</a></small></div></div>';
          $return .= '<div class="visible-xs"><a class="btn btn-default btn-block" href="' . $map_url . '"><i class="icon icon-map-marker"></i> Auf Google Maps anzeigen</a></div>';
        }
      return $return;
    }

    public function contactSellerByMailBox() {
      $html = '<div class="single-property-container">'
        .'<p class="casasyncContact"><i class="fa fa-envelope"></i> '
        .'<a href="#casasyncPropertyContactForm" id="casasyncContactAnchor">Jetzt Anbieter direkt kontaktieren</a>'
      .'</p></div>';
      return $html;
    }

    public function hasSeller(){
      if (
          $this->getAddress('seller')
          . $this->seller['legalname']
          . $this->seller['email']
          . $this->seller['fax']
          . $this->seller['phone_direct']
          . $this->seller['phone_central']
          . $this->seller['phone_mobile']
        ) {
          return true;
        } else {
          return false;
        }
    }

    public function getSeller() {
      $return  = '<h3><i class="fa fa-briefcase"></i> ' . __('Provider' , 'casasync') . '</h3><address>';
      $return .= ($this->seller['legalname'] != '') ? ('<strong>' . $this->seller['legalname'] . '</strong><br>') : ('');
      $return .= $this->getAddress('seller');

      $return .= '<div class="casasync-seller-infos">';
      if($this->seller['email'] != '') {
        $objektlink = get_permalink();
        $mailto = 'mailto:' . $this->seller['email'] . '?subject=Ich%20habe%20eine%20Frage%20bez%C3%BCglich%20dem%20Objekt%3A%20'
          .rawurlencode(html_entity_decode(get_the_title())) . '?body='
        .rawurlencode(__('I am interested concerning this property. Please contact me.', 'casasync')) . '%0A%0ALink: ' . $objektlink;
        $return .= '<p><span class="casasync-label">' . __('E-Mail', 'casasync') . '</span> ';
        $return .= '<span class="value break-word"> <a href="' . $mailto . '">' . $this->seller['email'] . '</a></span>';
      }
      if($this->seller['phone_mobile'] != '') {
        $return .= '<p class="casasync-phone-mobile">'
          .'<span class="casasync-label">' . __('Mobile', 'casasync')  . '</span>'
        .'<span class="value break-word"> ' . $this->seller['phone_mobile'] . '</span></p>';
      }
      if($this->seller['phone_direct'] != '') {
        $return .= '<p class="casasync-phone-direct">'
          .'<span class="casasync-label">' . __('Phone direct', 'casasync')  . '</span>'
        .'<span class="value break-word"> ' . $this->seller['phone_direct'] . '</span></p>';
      }
      if($this->seller['phone_central'] != '') {
        $return .= '<p class="casasync-phone-central">'
          .'<span class="casasync-label">' . __('Phone', 'casasync')  . '</span>'
        .'<span class="value break-word"> ' . $this->seller['phone_central'] . '</span></p>';
      }
      if($this->seller['fax'] != '') {
        $return .= '<p class="casasync-phone-fax">'
          .'<span class="casasync-label">' . __('Fax', 'casasync')  . '</span>'
        .'<span class="value break-word"> ' . $this->seller['fax'] . '</span></p>';
      }
    $return .= '</div></address>';
    return $return;
    }

    public function getSellerName() {
      if($this->seller['legalname'] != '') {
        return $this->seller['legalname'];
      }
    }

    public function getSalesPerson() {
      $return = '<h3><i class="fa fa-user"></i> ' . __('Contact person' , 'casasync') . '</h3><address>';
      if ($this->salesperson['givenname'] != '' && $this->salesperson['familyname'] != '') {
        $return .= '<p>' . $this->salesperson['givenname'] . ' ' . $this->salesperson['familyname'];
      }
      if ($this->salesperson['function'] != '') {
        $return .= '<br><i>' . $this->salesperson['function'] . '</i></p>';
      }
      if ($this->salesperson['email'] != '') {
        $objektlink = get_permalink();
        $mailto = 'mailto:' . $this->salesperson['email'] . '?subject=Ich%20habe%20eine%20Frage%20bez%C3%BCglich%20dem%20Objekt%3A%20'
          .rawurlencode(html_entity_decode(get_the_title())) . '&body='. rawurlencode(__('I am interested concerning this property. Please contact me.', 'casasync'))
        .'%0A%0ALink: ' . $objektlink;
        $return .= '<p><span class="casasync-label">' . __('Email', 'casasync') . '</span>'
        .'<span class="value break-word"> <a href="' . $mailto . '">' . $this->salesperson['email'] . '</a></span></p>';
      }
      if($this->salesperson['phone_mobile'] != '') {
        $return .= '<p class="casasync-phone-mobile">'
          .'<span class="casasync-label">' . __('Mobile', 'casasync')  . '</span>'
        .'<span class="value break-word"> ' . $this->salesperson['phone_mobile'] . '</span></p>';
      }
     if($this->salesperson['phone_direct'] != '') {
        $return .= '<p class="casasync-phone-direct">'
          .'<span class="casasync-label">' . __('Phone direct', 'casasync')  . '</span>'
        .'<span class="value break-word"> ' . $this->salesperson['phone_direct'] . '</span></p>';
      }
      if($this->salesperson['phone_central'] != '') {
        $return .= '<p class="casasync-phone-central">'
          .'<span class="casasync-label">' . __('Phone', 'casasync')  . '</span>'
        .'<span class="value break-word"> ' . $this->salesperson['phone_central'] . '</span></p>';
      }
      if($this->salesperson['fax'] != '') {
        $return .= '<p class="casasync-phone-fax">'
          .'<span class="casasync-label">' . __('Fax', 'casasync')  . '</span>'
        .'<span class="value break-word"> ' . $this->salesperson['fax'] . '</span></p>';
      }
      $return .= '</address>';
      return $return;
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
      $headline = '<h3><i class="fa fa-share-square"></i> ' . __('Share', 'casasync') . '</h3>';
      if (get_option( 'casasync_share_facebook', false )) {
        $return = '<div class="fb-like" data-send="true" data-layout="button_count" data-href="http://' . $_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'] . '" data-width="200" data-show-faces="true"></div>';
      }
      return $headline . $return;
    }

    public function getFacebookShareScript() {
      if (get_option( 'casasync_share_facebook', false )) {
        $return = '<div id="fb-root"></div><script>(function(d, s, id) {'
          .'var js, fjs = d.getElementsByTagName(s)[0];'
          .'if (d.getElementById(id)) return;'
          .'js = d.createElement(s); js.id = id;'
          .'js.src = "//connect.facebook.net/' . str_replace('-','_',get_bloginfo('language')) . '/all.js#xfbml=1";'
          .'fjs.parentNode.insertBefore(js, fjs);'
        ."}(document, 'script', 'facebook-jssdk'));</script>";
        return $return;
      }
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
            if ($this->prices['gross'] || $this->prices['net']) {
              if ($this->prices['gross']) {
                $price = $this->prices['gross'];
              }
              if ($this->prices['net']) {
                $price = $this->prices['net'];
              }
            }
          }
          break;
      }
      switch ($format) {
        case 'num':
          $return = $price['num'];
          break;
        case 'currency':
        case 'formated':
        case 'full':
          $return = $this->price_currency . ' ';
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
            $return .= (array_key_exists('propertysegment', $price) && $price['propertysegment'] != NULL && $price['propertysegment'] != 'full') ? (' / ' . substr($price['propertysegment'], 0, -1) . '<sup>2</sup>') : ('');
            $sep     = (array_key_exists('propertysegment', $price) && $price['propertysegment'] != NULL && $price['propertysegment'] != 'full') ? (__('per', 'casasync')) : ('/');
            $return .= (array_key_exists('timesegment', $price)     && $price['timesegment']     != NULL && $price['timesegment'] != 'infinite') ? (' ' . $sep . ' ' . str_replace($price['timesegment'], $timesegment_labels[$price['timesegment']], $price['timesegment'])) : ('');
      }
      return $return;
    }

    public function getExtraCosts($name) {
      $return = null;
      if(!empty($this->prices['extra_costs'])) {
        foreach ($this->prices['extra_costs'] as $key => $value) {
          if ($value['title'] == $name) {
            $return = $value['value'];
          }
        }
      }
      return $return;
    }

    public function getNumval($name){
      switch ($name) {
        case 'rooms':
        case 'floor':
        case 'number_of_rooms':
          return (isset($this->numvals[$name]) ? ($this->numvals[$name]['value']) : false);
          break;
        case 'surface_usable':
        case 'surface_living':
        case 'surface_property':
          return (isset($this->numvals[$name]) ? ($this->numvals[$name]['value'] . '<sup>2</sup>') : false);
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
        if (array_search($numval["key"], $sort)) {
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

    public function getFeaturedImage() {
      $return = NULL;
      $pid = get_the_ID();
      if (has_post_thumbnail($pid)) {
        $return .= '<a href ="' . get_permalink($pid) . '" class="casasync-thumbnail" style="position:relative;">';
        $return .= $this->getAvailability();
        $return .= get_the_post_thumbnail($pid, 'casasync-thumb');
        $return .= '</a>';
      }
      return $return;
    }

    public function getAvailability() {
      $return = NULL;
      if (isset($this->availability)) {
        $the_availability_label = ($this->availability_label != '') ? ($this->availability_label) :($this->availability);
        $return .= '<div class="availability-outerlabel">';
        $return .= '<div class="availability-label availability-label-' . $this->availability . '">' . $the_availability_label . '</div>';
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
