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
    public $visitperson = array(
      'givenname'     => '',
      'phone_direct'  => '',
      'note'        => '',
    );
    public $seller_inquiry = array(
      'email'         => ''
    );
    public $contactEmails = array(
      'inquiry'       => false,
      'remcat'        => false,
      'fallback'      => false
    );
    public $property_geo_latitude;
    public $property_geo_longitude;


    public $loadImages     = true;
    public $loadDocuments  = true;
    public $loadPlans      = true;
    public $loadOfferLogos = true;

    public function __construct($post, $loadImages = true, $loadDocuments = true, $loadPlans = true, $loadOfferLogos = true){ 
      $this->loadImages     = $loadImages;
      $this->loadDocuments  = $loadDocuments;
      $this->loadPlans      = $loadPlans;
      $this->loadOfferLogos = $loadOfferLogos;

      $this->conversion = new Conversion;
      if (!is_admin()) {
        $this->setProperty($post);
      }

      //lets invite the new kid
      global $casasync;
      $this->offer = $casasync->prepareOffer($post);



      //$this->categoryService = new \CasasoftStandards\Service\CategoryService();
    }

    public function getPropertyQuery() {
      $query = $_GET;
      $w_categories = array();
      if (isset($query['casasync_category_s'])) {
        foreach ($query['casasync_category_s'] as $slug => $value) {
          $w_categories[] = $value;
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
      if (isset($query['casasync_location_s'])) {
        foreach ($query['casasync_location_s'] as $slug => $options) {
          $w_locations[] = $value;
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
      if (isset($query['casasync_salestype_s'])) {
        if (!is_array($query['casasync_salestype_s'])) {
          $query['casasync_salestype_s'] = array($query['casasync_salestype_s']);
        }
        foreach ($query['casasync_salestype_s'] as $slug => $value) {
          $w_salestypes[] = $value;
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

      $posts_per_page = 2000;
      $args = array(
        'post_type' => 'casasync_property',
        'posts_per_page' => $posts_per_page,
        'tax_query' => $taxquery_new, 
      );

      $the_query = new \WP_Query( $args );

      return $the_query;
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
      
      $w_availabilities = array();
      if (array_key_exists('availabilities', $query)) {
        foreach ($query['availabilities'] as $slug => $options) {
          if ($options['checked']) {
            $w_availabilities[] = $options['value'];
          }
        }
      } else {
        $w_availabilities = array('active','reserved');
      }
      if ($w_availabilities) {
        $taxquery_new[] =
           array(
               'taxonomy' => 'casasync_availability',
               'terms' => $w_availabilities,
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


      if ($this->loadImages) {
        $this->attachments = get_posts( array(
          'post_type'                => 'attachment',
          'posts_per_page'           => -1,
          'post_parent'              => get_the_ID(),
          //'exclude'                => get_post_thumbnail_id(),
          'casasync_attachment_type' => 'image',
          'orderby'                  => 'menu_order',
          'order'                    => 'ASC'
        ) );
      }

      if($this->loadDocuments) {
        $this->documents = get_posts( array(
          'post_type'                => 'attachment',
          'posts_per_page'           => -1,
          'post_parent'              => get_the_ID(),
          //'exclude'                => get_post_thumbnail_id(),
          'casasync_attachment_type' => 'document',
          'orderby'                  => 'menu_order'
        ) );
      }

      if($this->loadPlans) {
        $this->plans = get_posts( array(
          'post_type'                => 'attachment',
          'posts_per_page'           => -1,
          'post_parent'              => get_the_ID(),
          //'exclude'                => get_post_thumbnail_id(),
          'casasync_attachment_type' => 'plan'
        ) );
      }

      if($this->loadOfferLogos) {
        $this->logos = get_posts( array(
          'post_type'                => 'attachment',
          'posts_per_page'           => -1,
          'post_parent'              => get_the_ID(),
          //'exclude'                => get_post_thumbnail_id(),
          'casasync_attachment_type' => 'offer-logo'
        ) );
      }

      $this->address_street       = get_post_meta( get_the_ID(), 'casasync_property_address_streetaddress', $single = true );
      $this->address_streetnumber = get_post_meta( get_the_ID(), 'casasync_property_address_streetnumber', $single = true );
      $this->address_postalcode   = get_post_meta( get_the_ID(), 'casasync_property_address_postalcode', $single = true );
      $this->address_region       = get_post_meta( get_the_ID(), 'casasync_property_address_region', $single = true );
      $this->address_locality     = get_post_meta( get_the_ID(), 'casasync_property_address_locality', $single = true );
      $this->address_country      = get_post_meta( get_the_ID(), 'casasync_property_address_country', $single = true );
      $this->address_country_name = $this->conversion->countrycode_to_countryname($this->address_country);

      $this->property_geo_latitude  = get_post_meta( get_the_ID(), 'casasync_property_geo_latitude', $single = true );
      $this->property_geo_longitude = get_post_meta( get_the_ID(), 'casasync_property_geo_longitude', $single = true );


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

      $this->visitperson['givenname']     = get_post_meta(get_the_ID(), 'seller_visit_person_givenname', true);
      $this->visitperson['phone_direct']  = get_post_meta(get_the_ID(), 'seller_visit_person_phone_direct', true);
      $this->visitperson['note']        = get_post_meta(get_the_ID(), 'seller_visit_person_note', true);

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
      return $this->offer->renderAvailabilityDate();
    }

    public function getGallery(){
      return $this->offer->renderGallery();
    }

    public function getLogo(){
      return 'DEPRICATED';
    }

    public function getTitle(){
      return $this->offer->getTitle();
    }

    public function getExcerpt(){
      global $post;
      $excerpt = $post->post_excerpt;
      $new_excerpt = str_replace('m2', 'm<sup>2</sup>', $excerpt);
      return $new_excerpt;
    }

    public function getBasicBoxes(){
      return $this->offer->renderBasicBoxes();
    }

    public function formatStartdate($str){
      return $this->offer->renderAvailabilityDate($sr);
    } 

    public function getSpecificationsTable(){
      return $this->offer->renderDatatable();
    }

    public function getQuickInfosTable() {
      return $this->offer->renderQuickInfosTable();
    }

    public function getPagination(){
      return $this->offer->renderPagination();
    }

    public function getContactform(){
      return $this->offer->renderContactform();
    }

    public function getTabable(){
      return $this->offer->renderTabable();
    }

    public function getPermalink(){
      return get_permalink();
    }

    public function getSeller($title = true, $icon = true) {
      return $this->offer->renderSeller();
    }

    public function getMap() {
      return $this->offer->renderMap();
    }

    public function getSalesPerson($title = true, $icon = true) {
      return $this->offer->renderSalesPerson();
    }

    public function getFeaturedImage($lightbox = false) {
      return $this->offer->renderFeaturedImage();
    }

    public function getGalleryThumbnails() {
      $html = '';

      if ($this->attachments) {
        $html .= '<div class="casasync-gallery-thumbnails">';
        $max_thumbnail = get_option('casasync_single_max_thumbnails', 15);
        $count = 0;
          foreach ( $this->attachments as $attachment ) {
            if ($count < $max_thumbnail) {
              $thumbImgMeidum = wp_get_attachment_image( $attachment->ID, 'thumbnail', true );
              $thumbImgFull = wp_get_attachment_image_src( $attachment->ID, 'full', true );
              $html .= '<a class="property-image-gallery" rel="casasync-thumbnail-gallery" href="' . $thumbImgFull[0] . '" title="' . $attachment->post_excerpt . '">' . $thumbImgMeidum . '</a>';
            }
            $count++;
          }
        $html .= '</div>';
      }

      return $html;
    }

    public function getAddress($from, $singleline = false){
      switch ($from) {
        case 'seller':
          return $this->offer->renderSellerAddress();
          break;
        case 'property':

        if ($singleline === false) {
          return $this->offer->renderAddress();
        } else {
            $address = '';
            $address .= ($this->address_postalcode ? $this->address_postalcode . ' ' : '');
            $address .= ($this->address_locality ? $this->address_locality : '');
            $address .= ($this->address_country ? ' (' . $this->address_country . ')' : '');
        }
        return $address;

        default:
          break;
      }
    }

    public function contactSellerByMailBox() {
      //$emails = $this->getContactEmails();
      $emails = array();
      $mail = array();
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

    public function hasVisitPerson(){
      if (
          $this->visitperson['givenname']
          . $this->visitperson['phone_direct']
        ) {
        return true;
      }
    }

    

    public function getSalesPersonName() {
      if ($this->salesperson['givenname'] != '' && $this->salesperson['familyname'] != '') {
          return $this->salesperson['givenname'] . ' ' . $this->salesperson['familyname'];
        }
    }

    public function getVisitPerson($title = true, $icon = true) {
      if ($this->hasVisitPerson()) {
        $return = '<h3>'.($icon ? '<i class="fa fa-briefcase"></i> ' : '') . __('Contact person' , 'casasync') . '</h3><address>';
        if ($this->visitperson['givenname'] != '') {
          $return .= '<p><strong>' . $this->visitperson['givenname'] . '</strong>';
        }
       
          
        if($this->visitperson['phone_direct'] != '') {
          $return .= '<p class="casasync-phone-direct">'
            .'<span class="casasync-label">' . __('Phone direct', 'casasync')  . '</span>'
          .'<span class="value break-word"> ' . $this->visitperson['phone_direct'] . '</span></p>';
        }
        if($this->visitperson['note'] != '') {
          $return .= '<p class="casasync-note">'
            .'<p>' . $this->visitperson['note']  . '</p>';
        }
        $return .= '</address>';
        return $return;
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
      $price = array();
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
          $return .= isset($price['num']) ? $price['num'] : '';
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
                && $price['propertysegment'] != 'all'
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
        #case 'surface_usable':
        #case 'surface_living':
        case 'area_bwf':
        case 'area_nwf':
        case 'area_sia_gf':
        case 'area_sia_nf':
        case 'surface_property':
          if (isset($this->numvals[$name])) {
            preg_match_all('/^(\d+)(\w+)$/', $this->numvals[$name]['value'], $matches);
            $number = implode($matches[1]);
            $letter = implode($matches[2]);
            return number_format($number, 0, '.', "'") . $letter . 'm<sup>2</sup>';
          } else {
            return false;
          }
          break;
        case 'volume':
          return (isset($this->numvals[$name]) ? ($this->numvals[$name]['value'] . 'm<sup>3</sup>') : false);
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

    public function getAllDocuments($icon = false) {
      $html = false;
      $count = 1;
      $args = array(
        'post_parent' => get_the_ID(),
        'post_type' => 'attachment',
      );
      $attachments = get_children( $args );
      if($attachments) {
        $html .= '<ul class="casasync-unstyled casasync-documents">';
        foreach ( (array) $attachments as $attachment_id => $attachment ) {
          if(strpos($attachment->post_mime_type, 'image') === false ) {
            $url = wp_get_attachment_url( $attachment_id );
            $title = (is_numeric($attachment->post_title)) ? (__('Document', 'casasync') . ' ' . $count) : ($attachment->post_title);
            $html .= '<li>' . $icon . '<a href="' . $url . '" title="' . $title . '" target="_blank" >' . $title . '</a></li>';
            $count++;
          }
        }
        $html .= '</ul>';
      }
      if($count > 1) {
        return $html;
      }
    }

    public function getAllPlans() {
      $args = array(
        'post_type' => 'attachment',
        'posts_per_page' => -1,
        'casasync_attachment_type' => 'plan',
        'post_status' =>'publish',
        'post_parent' => get_the_ID(),
      ); 
      $attachments = get_posts( $args );
      return $attachments;
    }

    public function getAllVirutalTours() {
      return get_post_meta(get_the_ID(), 'the_urls', true);
    }

    public function getAllSalesBrochure() {
      $args = array(
        'post_type' => 'attachment',
        'posts_per_page' => -1,
        'casasync_attachment_type' => 'sales-brochure',
        'post_status' =>'publish',
        'post_parent' => get_the_ID(),
      ); 
      $attachments = get_posts( $args );
      return $attachments;
    }

    public function getProvidedURL() {
      $html = NULL;
      $providedURL = get_post_meta(get_the_ID(), 'the_url');
      if($providedURL) {
        if(substr($providedURL[0][0]['href'], 0, 4) != "http") {
          $providedURL[0][0]['href'] = 'http://' . $providedURL[0][0]['href'];
        }
        $html = '<a href="' . $providedURL[0][0]['href'] . '" title="' . $providedURL[0][0]['title'] . '" target="_blank">' . $providedURL[0][0]['label'] . '</a>';
      }
      return $html;
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

    public static function getEventTrackingCode() {
      $return = '';
      $eventTrackingCode = get_option('casasync_form_event_tracking');
      if ($eventTrackingCode != "") {
          $eventTrackingCode = stripslashes($eventTrackingCode);
          if (strpos($eventTrackingCode,'%casasync_id%') !== false) {
              $casasync_id = get_post_meta(get_the_ID(), 'casasync_id', true);
              $return = str_replace('%casasync_id%', $casasync_id, $eventTrackingCode);
          }
      }
      echo '<script>' . $return . '</script>';
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

    public function getUrls(){
      return get_post_meta( get_the_ID(), 'the_urls', $single = true );
    }

  }  
