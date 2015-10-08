<?php
namespace casawp;

class Import {
  public $lastTranscript = '';
  public $importFile = false;
  public $main_lang = false;
  public $max_auto_create = 10;
  public $xmlOffers = array();
  public $WPML = null;
  public $transcript = array();
  public $curtrid = false;
  public $meta_keys = array(
     #'surface_living'              ,
      'surface_property'            ,
      #'surface_usable'              ,

      'area_bwf'                    ,
      'area_nwf'                    ,
      'area_sia_gf'                 ,
      'area_sia_nf'                 ,

      'volume'                      ,
      'ceiling_height'              ,
      'hall_height'                 ,
      'maximal_floor_loading'       ,
      'carrying_capacity_crane'     ,
      'carrying_capacity_elevator'  ,
      'floor'                       ,
      'year_built'                  ,
      'year_renovated'              ,
      'number_of_rooms'             ,
      'number_of_apartments'        ,
      'number_of_floors'            ,
      'number_of_lavatory'          ,
      'number_of_guest_wc'          ,

      'visitInformation'                    ,
      'property_url'                        ,
      'property_address_country'            ,
      'property_address_locality'           ,
      'property_address_region'             ,
      'property_address_postalcode'         ,
      'property_address_streetaddress'      ,
      'property_address_streetnumber'       ,
      'urls'                                ,
      'start'                               ,
      'referenceId'                         ,
      'availability'                                 ,
      'availability_label'                           ,
      'offer_type'                                   ,
      'price_currency'                               ,
      'price_timesegment'                            ,
      'price_propertysegment'                        ,
      'grossPrice_timesegment'                       ,
      'grossPrice_propertysegment'                   ,
      'netPrice_timesegment'                         ,
      'netPrice_propertysegment'                     ,
      'priceForOrder'                                ,
      'parking-exterior-space'                       ,
      'parking-carport'                              ,
      'parking-garage-connected'                     ,
      'parking-garage-box'                           ,
      'parking-garage-underground'                   ,
      'parking-house'                                ,
      'room-workroom'                                ,
      'room-storage-basement'                        ,
      'seller_org_address_country'                   ,
      'seller_org_address_locality'                  ,
      'seller_org_address_region'                    ,
      'seller_org_address_postalcode'                ,
      'seller_org_address_postofficeboxnumber'       ,
      'seller_org_address_streetaddress'             ,
      'seller_org_legalname'                         ,
      'seller_org_email'                             ,
      'seller_org_fax'                               ,
      'seller_org_phone_direct'                      ,
      'seller_org_phone_central'                     ,
      'seller_org_phone_mobile'                      ,
      'seller_org_brand'                             ,
      'seller_person_function'                       ,
      'seller_person_givenname'                      ,
      'seller_person_familyname'                     ,
      'seller_person_email'                          ,
      'seller_person_fax'                            ,
      'seller_person_phone_direct'                   ,
      'seller_person_phone_central'                  ,
      'seller_person_phone_mobile'                   ,
      'seller_person_gender'                         ,
      'seller_inquiry_person_function'               ,
      'seller_inquiry_person_givenname'              ,
      'seller_inquiry_person_familyname'             ,
      'seller_inquiry_person_email'                  ,
      'seller_inquiry_person_fax'                    ,
      'seller_inquiry_person_phone_direct'           ,
      'seller_inquiry_person_phone_central'          ,
      'seller_inquiry_person_phone_mobile'           ,
      'seller_inquiry_person_gender'                 ,
      'seller_visit_person_givenname'              ,
      'seller_visit_person_phone_direct'           ,
      'seller_visit_person_note'                 ,
      'property_geo_latitude'               ,
      'property_geo_longitude'              ,
      'price'                                        ,
      'grossPrice'                                   ,
      'netPrice'                                     ,
      'the_url'                                     ,
      'the_urls'                                     ,
      'the_tags'                                     ,
      'extraPrice'                                   ,

      'distance_public_transport'                    ,
      'distance_shop'                                ,
      'distance_kindergarten'                        ,
      'distance_motorway'                            ,
      'distance_school1'                             ,
      'distance_school2'                             ,
      'distance_bus_stop',
      'distance_train_station',
      'distance_post',
      'distance_bank',
      'distance_cable_railway_station',
      'distance_boat_dock',
      'distance_airport',
  );

  public function __construct($doimport = true, $casagatewayupdate = false){
    $this->conversion = new Conversion;
    if ($doimport) {
      add_action( 'init', array($this, 'casawpImport') );  
    }
    if ($casagatewayupdate) {
      add_action( 'init', array($this, 'updateImportFileThroughCasaGateway') );  
    }
    //$this->casawpImport();
  }

  public function getImportFile(){
    if (!$this->importFile) {
      $good_to_go = false;
      if (!is_dir(CASASYNC_CUR_UPLOAD_BASEDIR . '/casawp')) {
        mkdir(CASASYNC_CUR_UPLOAD_BASEDIR . '/casawp');
      }
      if (!is_dir(CASASYNC_CUR_UPLOAD_BASEDIR . '/casawp/import')) {
        mkdir(CASASYNC_CUR_UPLOAD_BASEDIR . '/casawp/import');
      }
      $file = CASASYNC_CUR_UPLOAD_BASEDIR  . '/casawp/import/data.xml';
      if (file_exists($file)) {
        $good_to_go = true;
      } else {
        //if force last check for last
        if (isset($_GET['force_last_import'])) {
          $file = CASASYNC_CUR_UPLOAD_BASEDIR  . '/casawp/import/data-done.xml';
          if (file_exists($file)) {
            $good_to_go = true;
          }
        }
      }
      if ($good_to_go) {
        $this->importFile = $file;
      }
    }
    return $this->importFile;
  }

  public function renameImportFileTo($to){
    if ($this->importFile != $to) {
      rename($this->importFile, $to);
      $this->importFile = $to;
    }
  }

  public function backupImportFile(){
    copy ( $this->getImportFile() , CASASYNC_CUR_UPLOAD_BASEDIR  . '/casawp/done/' . date('Y_m_d_H_i_s') . '_completed.xml');
    return true;
  }

  public function extractDescription($offer){
    $the_description = '';
    foreach ($offer['descriptions'] as $description) {
      $the_description .= ($the_description ? '<hr class="property-separator" />' : '');
      if ($description['title']) {
        $the_description .= '<h2>' . $description['title'] . '</h2>';
      }
      $the_description .= $description['text'];
    }
    return $the_description;
  }

  public function getLastTranscript(){
    return $this->lastTranscript;
  }

  public function setcasawpCategoryTerm($term_slug, $label = false) {
    $label = (!$label ? $term_slug : $label);
    $term = get_term_by('slug', $term_slug, 'casawp_category', OBJECT, 'raw' );
    //$existing_term_id = term_exists( $label, 'casawp_category');
    $existing_term_id = false;
    if ($term) {
      if (
        $term->slug != $term_slug
        || $term->name != $label
      ) {
        wp_update_term($term->term_id, 'casawp_category', array(
          'name' => $label,
          'slug' => $term_slug
        ));
      }
    } else {
      $options = array(
        'description' => '',
        'slug' => $term_slug
      );
      $id = wp_insert_term(
        $label,
        'casawp_category',
        $options
      );
      return $id;
    }
  }

  public function setcasawpFeatureTerm($term_slug, $label = false) {
    $label = (!$label ? $term_slug : $label);
    $term = get_term_by('slug', $term_slug, 'casawp_feature', OBJECT, 'raw' );
    //$existing_term_id = term_exists( $label, 'casawp_feature');
    $existing_term_id = false;
    if ($term) {
      if (
        $term->slug != $term_slug
        || $term->name != $label
      ) {
        wp_update_term($term->term_id, 'casawp_feature', array(
          'name' => $label,
          'slug' => $term_slug
        ));
      }
    } else {
      $options = array(
        'description' => '',
        'slug' => $term_slug
      );
      $id = wp_insert_term(
        $label,
        'casawp_feature',
        $options
      );
      return $id;
    }
  }

  public function casawpUploadAttachment($the_mediaitem, $post_id, $property_id) {
    if ($the_mediaitem['file']) {
      $filename = '/casawp/import/attachment/'. $the_mediaitem['file'];
    } elseif ($the_mediaitem['url']) { //external
      $filename = '/casawp/import/attachment/externalsync/' . $property_id . '/' . basename($the_mediaitem['url']);

      //extention is required
      $file_parts = pathinfo($filename);
      if (!isset($file_parts['extension'])) {
          $filename = $filename . '.jpg';
      }
      if (!is_file(CASASYNC_CUR_UPLOAD_BASEDIR . $filename)) {
        if (!is_dir(CASASYNC_CUR_UPLOAD_BASEDIR . '/casawp/import/attachment/externalsync')) {
          mkdir(CASASYNC_CUR_UPLOAD_BASEDIR . '/casawp/import/attachment/externalsync');
        }
        if (!is_dir(CASASYNC_CUR_UPLOAD_BASEDIR . '/casawp/import/attachment/externalsync/' . $property_id)) {
          mkdir(CASASYNC_CUR_UPLOAD_BASEDIR . '/casawp/import/attachment/externalsync/' . $property_id);
        }
        if (!is_file(CASASYNC_CUR_UPLOAD_BASEDIR . $filename )) {
          $could_copy = copy($the_mediaitem['url'], CASASYNC_CUR_UPLOAD_BASEDIR . $filename );
          if (!$could_copy) {
            $filename = false;
          }
        }

        
      }
    } else { //missing
      $filename = false;
    }

    if ($filename && is_file(CASASYNC_CUR_UPLOAD_BASEDIR . $filename)) {
      //new file attachment upload it and attach it fully
      $wp_filetype = wp_check_filetype(basename($filename), null );
      $attachment = array(
        'guid'           => CASASYNC_CUR_UPLOAD_BASEURL . $filename,
        'post_mime_type' => $wp_filetype['type'],
        'post_title'     =>  preg_replace('/\.[^.]+$/', '', ( $the_mediaitem['title'] ? $the_mediaitem['title'] : basename($filename)) ),
        'post_content'   => '',
        'post_excerpt'   => $the_mediaitem['caption'],
        'post_status'    => 'inherit',
        'menu_order'     => $the_mediaitem['order']
      );

      $attach_id = wp_insert_attachment( $attachment, CASASYNC_CUR_UPLOAD_BASEDIR . $filename, $post_id );
      // you must first include the image.php file
      // for the function wp_generate_attachment_metadata() to work
      require_once(ABSPATH . 'wp-admin/includes/image.php');
      $attach_data = wp_generate_attachment_metadata( $attach_id, CASASYNC_CUR_UPLOAD_BASEDIR . $filename );
      wp_update_attachment_metadata( $attach_id, $attach_data );

      //category
      $term = get_term_by('slug', $the_mediaitem['type'], 'casawp_attachment_type');
      $term_id = $term->term_id;
      wp_set_post_terms( $attach_id,  array($term_id), 'casawp_attachment_type' );

      //alt
      update_post_meta($attach_id, '_wp_attachment_image_alt', $the_mediaitem['alt']);

      //orig
      update_post_meta($attach_id, '_origin', ($the_mediaitem['file'] ? $the_mediaitem['file'] : $the_mediaitem['url']));

      return $attach_id;
    } else {
      return $filename . " could not be found!";
    }
  }

  public function getMainLang(){
    global $sitepress;
    if (!$this->main_lang) {
      $main_lang = 'de';
      if($this->hasWPML()) {
          if (function_exists("wpml_get_default_language")) {
            $main_lang = wpml_get_default_language();
            $this->WPML = true;
          }
      } else {
        if (get_bloginfo('language')) {
          $main_lang = substr(get_bloginfo('language'), 0, 2);
        } 
      }
      $this->main_lang = $main_lang;
    }
    return $this->main_lang;
  }

  public function hasWPML(){
    if ($this->WPML !== true && $this->WPML !== false) {
      $this->WPML = $this->loadWPML();
    }
    return $this->WPML;
  }

  public function loadWPML(){
    global $sitepress;
    if( $sitepress && is_object($sitepress) && method_exists($sitepress, 'get_language_details' )) {
      if (is_file( WP_PLUGIN_DIR . '/sitepress-multilingual-cms/inc/wpml-api.php' )) {
        require_once( WP_PLUGIN_DIR . '/sitepress-multilingual-cms/inc/wpml-api.php' );
      }
      return true;
    }
    return false;
  }

  public function updateInsertWPMLconnection($offer_pos, $wp_post, $lang, $casawp_id){
    if ($this->hasWPML()) {

      if ($this->getMainLang() == $lang) {
        $this->curtrid = wpml_get_content_trid('post_casawp_property', $wp_post->ID);
      }

      $_POST['icl_post_language'] = $lang; 
      
      global $sitepress;
      if ($this->getMainLang() != $lang) {
        $sitepress->set_element_language_details($wp_post->ID, 'post_casawp_property', $this->curtrid, $lang, $sitepress->get_default_language(), true);
      } else {
        $sitepress->set_element_language_details($wp_post->ID, 'post_casawp_property', $this->curtrid, $lang, NULL, true);
      }
    }
  }


  public function integratedOffersToArray($integratedOffers){
    $the_offers = array();

    if (!empty($integratedOffers)) {
      foreach ($integratedOffers->integratedOffer as $offer) {
        $the_offer = array();
        $the_offer['price']           = (int) $offer;
        $the_offer['frequency']       = (int) $offer['frequency'];
        $the_offer['timesegment']     = (string) $offer['timesegment'];
        $the_offer['propertysegment'] = (string) $offer['propertysegment'];
        $the_offer['inclusive']       = (int) $offer['inclusive'];

        $the_offers[(string) $offer['type']][] = $the_offer;
      }
    }

    return $the_offers;
  }


  public function setOfferAttachments($offer_medias, $wp_post, $property_id, $casawp_id){
    ### future task: for better performace compare new and old data ###

    
    //get xml media files
    $the_casawp_attachments = array();
    if ($offer_medias) {
      $o = 0;
      foreach ($offer_medias as $offer_media) {
        $o++;
        $media = $offer_media['media'];
        if (in_array($offer_media['type'], array('image', 'document', 'plan', 'offer-logo', 'sales-brochure'))) {
          $the_casawp_attachments[] = array(
            'type'    => $offer_media['type'],
            'alt'     => $offer_media['alt'],
            'title'   => preg_replace('/\.[^.]+$/', '', ( $offer_media['title'] ? $offer_media['title'] : basename($media['original_file'])) ),
            'file'    => '',
            'url'     => $media['original_file'],
            'caption' => $offer_media['caption'],
            'order'   => $o
          );
        }
      }
    }

    //get post attachments already attached
    $wp_casawp_attachments = array();
    $args = array(
      'post_type'   => 'attachment',
      'numberposts' => -1,
      'post_status' => null,
      'post_parent' => $wp_post->ID,
      'tax_query'   => array(
        'relation'  => 'AND',
        array(
          'taxonomy' => 'casawp_attachment_type',
          'field'    => 'slug',
          'terms'    => array( 'image', 'plan', 'document', 'offer-logo', 'sales-brochure' )
        )
      )
    );
    $attachments = get_posts($args);
    if ($attachments) {
      foreach ($attachments as $attachment) {
        $wp_casawp_attachments[] = $attachment;
      }
    }

    //upload necesary images to wordpress
    if (isset($the_casawp_attachments)) {
      $wp_casawp_attachments_to_remove = $wp_casawp_attachments;
      foreach ($the_casawp_attachments as $the_mediaitem) {
        //look up wp and see if file is already attached
        $existing = false;
        $existing_attachment = array();
        foreach ($wp_casawp_attachments as $key => $wp_mediaitem) {
          $attachment_customfields = get_post_custom($wp_mediaitem->ID);
          $original_filename = (array_key_exists('_origin', $attachment_customfields) ? $attachment_customfields['_origin'][0] : '');
          $alt = '';
          if ($original_filename == ($the_mediaitem['file'] ? $the_mediaitem['file'] : $the_mediaitem['url'])) {
            $existing = true;

            //its here to stay
            unset($wp_casawp_attachments_to_remove[$key]);

            $types = wp_get_post_terms( $wp_mediaitem->ID, 'casawp_attachment_type');
            if (array_key_exists(0, $types)) {
              $typeslug = $types[0]->slug;
              $alt = get_post_meta($wp_mediaitem->ID, '_wp_attachment_image_alt', true);
              //build a proper array out of it
              $existing_attachment = array(
                'type'    => $typeslug,
                'alt'     => $alt,
                'title'   => $wp_mediaitem->post_title,
                'file'    => $the_mediaitem['file'],
                'url'     => $the_mediaitem['url'],
                'caption' => $wp_mediaitem->post_excerpt,
                'order'   => $wp_mediaitem->menu_order
              );
            }

            //have its values changed?
            if($existing_attachment != $the_mediaitem ){
              $changed = true;
              $this->transcript[$casawp_id]['attachments']["updated"] = 1;
              //update attachment data
              if ($existing_attachment['caption'] != $the_mediaitem['caption']
                || $existing_attachment['title'] != $the_mediaitem['title']
                || $existing_attachment['order'] != $the_mediaitem['order']
                ) {
                $att['post_excerpt'] = $the_mediaitem['caption'];
                $att['post_title']   = preg_replace('/\.[^.]+$/', '', ( $the_mediaitem['title'] ? $the_mediaitem['title'] : basename($filename)) );
                $att['ID']           = $wp_mediaitem->ID;
                $att['menu_order']   = $the_mediaitem['order'];
                $insert_id           = wp_update_post( $att);
              }
              //update attachment category
              if ($existing_attachment['type'] != $the_mediaitem['type']) {
                $term = get_term_by('slug', $the_mediaitem['type'], 'casawp_attachment_type');
                $term_id = $term->term_id;
                wp_set_post_terms( $wp_mediaitem->ID,  array($term_id), 'casawp_attachment_type' );
              }
              //update attachment alt
              if ($alt != $the_mediaitem['alt']) {
                update_post_meta($wp_mediaitem->ID, '_wp_attachment_image_alt', $the_mediaitem['alt']);
              }
            }
          }

          
        }

        if (!$existing) {
          //insert the new image
          $new_id = $this->casawpUploadAttachment($the_mediaitem, $wp_post->ID, $property_id);
          if (is_int($new_id)) {
            $this->transcript[$casawp_id]['attachments']["created"] = $the_mediaitem['file'];
          } else {
            $this->transcript[$casawp_id]['attachments']["failed_to_create"] = $new_id;
          }
        }
        

      } //foreach ($the_casawp_attachments as $the_mediaitem) {

      //featured image
      $args = array(
        'post_type'   => 'attachment',
        'numberposts' => -1,
        'post_status' => null,
        'post_parent' => $wp_post->ID,
        'tax_query'   => array(
          'relation'  => 'AND',
          array(
            'taxonomy' => 'casawp_attachment_type',
            'field'    => 'slug',
            'terms'    => array( 'image', 'plan', 'document', 'offer-logo', 'sales-brochure' )
          )
        )
      );
      $attachments = get_posts($args);
      if ($attachments) {
        unset($wp_casawp_attachments);
        foreach ($attachments as $attachment) {
          $wp_casawp_attachments[] = $attachment;
        }
      }

      $attachment_image_order = array();
      foreach ($the_casawp_attachments as $the_mediaitem) {
        if ($the_mediaitem['type'] == 'image') {
          $attachment_image_order[$the_mediaitem['order']] = $the_mediaitem;
        }
      }
      if (isset($attachment_image_order) && !empty($attachment_image_order)) {
        ksort($attachment_image_order);
        $attachment_image_order = reset($attachment_image_order);
        if (!empty($attachment_image_order)) {
          foreach ($wp_casawp_attachments as $wp_mediaitem) {
            $attachment_customfields = get_post_custom($wp_mediaitem->ID);
            $original_filename = (array_key_exists('_origin', $attachment_customfields) ? $attachment_customfields['_origin'][0] : '');
            if ($original_filename == ($attachment_image_order['file'] ? $attachment_image_order['file'] : $attachment_image_order['url'])) {
              $cur_thumbnail_id = get_post_thumbnail_id( $wp_post->ID );
              if ($cur_thumbnail_id != $wp_mediaitem->ID) {
                set_post_thumbnail( $wp_post->ID, $wp_mediaitem->ID );
                $this->transcript[$casawp_id]['attachments']["featured_image_set"] = 1;
              }
            }
          }
        }
      }

      //images to remove
      foreach ($wp_casawp_attachments_to_remove as $attachment) {
        $this->transcript[$casawp_id]['attachments']["removed"] = $attachment;

        $attachment_customfields = get_post_custom($attachment->ID);
        $original_filename = (array_key_exists('_origin', $attachment_customfields) ? $attachment_customfields['_origin'][0] : '');
        wp_delete_attachment( $attachment->ID );
      }


    } //(isset($the_casawp_attachments)

   
  }

  public function setOfferSalestype($wp_post, $salestype, $casawp_id){
    $new_salestype = null;
    $old_salestype = null;

    if ($salestype) {
      $new_salestype = get_term_by('slug', $salestype, 'casawp_salestype', OBJECT, 'raw' );
      if (!$new_salestype) {
        $options = array(
          'description' => '',
          'slug' => $salestype
        );
        $id = wp_insert_term(
          $salestype,
          'casawp_salestype',
          $options
        );
        $new_salestype = get_term($id, 'casawp_salestype', OBJECT, 'raw');

      }
    }

    $wp_salestype_terms = wp_get_object_terms($wp_post->ID, 'casawp_salestype');
    if ($wp_salestype_terms) {
      $old_salestype = $wp_salestype_terms[0];
    }
    
    if ($old_salestype != $new_salestype) {
      $this->transcript[$casawp_id]['salestype']['from'] = ($old_salestype ? $old_salestype->name : 'none');
      $this->transcript[$casawp_id]['salestype']['to'] =   ($new_salestype ? $new_salestype->name : 'none');
      wp_set_object_terms( $wp_post->ID, ($new_salestype ? $new_salestype->term_id : NULL), 'casawp_salestype' );
    }
    
  }


  public function setOfferAvailability($wp_post, $availability, $casawp_id){
    $new_term = null;
    $old_term = null;

    //backward compadable
    if ($availability == 'available') {
      $availability = 'active';
    }

    if (!in_array($availability, array(
      'active',
      'taken',
      'reserved',
      'reference'
    ))) {
      $availability = null;
    }

    if ($availability) {
      $new_term = get_term_by('slug', $availability, 'casawp_availability', OBJECT, 'raw' );
      if (!$new_term) {
        $options = array(
          'description' => '',
          'slug' => $availability
        );
        $id = wp_insert_term(
          $availability,
          'casawp_availability',
          $options
        );
        $new_term = get_term($id, 'casawp_availability', OBJECT, 'raw');

      }
    }

    $wp_post_terms = wp_get_object_terms($wp_post->ID, 'casawp_availability');
    if ($wp_post_terms) {
      $old_term = $wp_post_terms[0];
    }
    
    if ($old_term != $new_term) {
      $this->transcript[$casawp_id]['availability']['from'] = ($old_term ? $old_term->name : 'none');
      $this->transcript[$casawp_id]['availability']['to'] =   ($new_term ? $new_term->name : 'none');
      wp_set_object_terms( $wp_post->ID, ($new_term ? $new_term->term_id : NULL), 'casawp_availability' );
    }
    
  }

  public function setOfferLocalities($wp_post, $address, $casawp_id){
    $country  = strtoupper($address['country']);
    $region   = $address['region'];
    $locality = $address['locality'];

    $country_arr = array($country, 'country_'.strtolower($country));
    $lvl1_arr = false;
    $lvl2_arr = false;
    if ($region) {
      $lvl1_arr = array($region, 'region_'.sanitize_title_with_dashes($region));
      if ($locality) {
        $lvl2_arr = array($locality, 'locality_'.sanitize_title_with_dashes($locality));
      }
    } elseif($locality) {
      $lvl1_arr = array($locality, 'locality_'.sanitize_title_with_dashes($locality));
      $lvl2_arr = false;
    }

    
    //make sure country exists
    $wp_country = false;
    if ($country_arr) {
      $wp_country = get_term_by('slug', $country_arr[1], 'casawp_location', OBJECT, 'raw' );

      if (!$wp_country || $wp_country instanceof WP_Error) {
        $options = array(
          'description' => '',
          'slug' => $country_arr[1]
        );
        $new_term = wp_insert_term(
          $country_arr[0],
          'casawp_location',
          $options
        );
        delete_option("casawp_location_children");
        $wp_country = get_term($new_term['term_id'], 'casawp_location', OBJECT, 'raw');
        $this->transcript['new_locations'][] = $country_arr;
      }
    }
    
    //make sure lvl1 exists
    $wp_lvl1 = false;
    if ($lvl1_arr) {
      $wp_lvl1 = get_term_by('slug', $lvl1_arr[1], 'casawp_location', OBJECT, 'raw' );

      if (!$wp_lvl1 || $wp_lvl1 instanceof WP_Error) {

        $options = array(
          'description' => '',
          'slug' => $lvl1_arr[1],
          'parent'=> ($wp_country ? (int) $wp_country->term_id : 0)
        );
        $new_term = wp_insert_term(
          $lvl1_arr[0],
          'casawp_location',
          $options
        );
        delete_option("casawp_location_children");
        $wp_lvl1 = get_term($new_term['term_id'], 'casawp_location', OBJECT, 'raw');
        $this->transcript['new_locations'][] = $lvl1_arr;
      }
    }

    //make sure lvl2 exists
    $wp_lvl2 = false;
    if ($lvl2_arr) {
      $wp_lvl2 = get_term_by('slug', $lvl2_arr[1], 'casawp_location', OBJECT, 'raw' );
      if (!$wp_lvl2 || $wp_lvl2 instanceof WP_Error) {
        $options = array(
          'description' => '',
          'slug' => $lvl2_arr[1],
          'parent' => ($wp_lvl1 ? (int) $wp_lvl1->term_id : 0)
        );
        $new_term = wp_insert_term(
          $lvl2_arr[0],
          'casawp_location',
          $options
        );
        delete_option("casawp_location_children");
        $wp_lvl2 = get_term($new_term['term_id'], 'casawp_location', OBJECT, 'raw');
        $this->transcript['new_locations'][] = $lvl2_arr;
      }
    }

    $new_terms = array();
    if ($wp_country) {
      $new_terms[] = $wp_country->term_id;
    }
    if ($wp_lvl1) {
      $new_terms[] = $wp_lvl1->term_id;
    }
    if ($wp_lvl2) {
      $new_terms[] = $wp_lvl2->term_id;
    }
    asort($new_terms);
    $new_terms = array_values($new_terms);

    $old_terms = array();
    $old_terms_obj = wp_get_object_terms($wp_post->ID, 'casawp_location');
    foreach ($old_terms_obj as $old_term) {
      $old_terms[] = $old_term->term_id;
    }
    asort($old_terms);
    $old_terms = array_values($old_terms);

    if ($new_terms != $old_terms) {
      $this->transcript[$casawp_id]['locations'][]['from'] = $old_terms;
      $this->transcript[$casawp_id]['locations'][]['to'] = $new_terms;
      wp_set_object_terms( $wp_post->ID, $new_terms, 'casawp_location' );
    }
    
  }

  public function setOfferCategories($wp_post, $categories, $customCategories, $casawp_id){
    $new_categories = array();
    $old_categories = array();

    //set post category
    $old_categories = array();
    $wp_category_terms = wp_get_object_terms($wp_post->ID, 'casawp_category');
    foreach ($wp_category_terms as $term) {
      $old_categories[] = $term->slug;
    }

    //supported
    if ($categories) {
      foreach ($categories as $category) {
        $new_categories[] = $category;
      }
    }
    //custom
    if (isset($customCategories)) {
      $custom_categories = $customCategories;
      sort($custom_categories);
      foreach ($custom_categories as $custom_category) {
        $new_categories[] = 'custom_' . $custom_category['slug'];
      }
    }

    //have categories changed?
    if (array_diff($new_categories, $old_categories) || array_diff($old_categories, $new_categories)) {
      $slugs_to_remove = array_diff($old_categories, $new_categories);
      $slugs_to_add    = array_diff($new_categories, $old_categories);
      $this->transcript[$casawp_id]['categories_changed']['removed_category'] = $slugs_to_remove;
      $this->transcript[$casawp_id]['categories_changed']['added_category'] = $slugs_to_add;

      //get the custom labels they need them
      $custom_categorylabels = array();
      if (isset($customCategories)) {
        foreach ($customCategories as $custom) {
          $custom_categorylabels[$custom['slug']] = $custom['label'];
        }
      }

      //make sure the categories exist first
      foreach ($slugs_to_add as $new_term_slug) {
        $label = (array_key_exists($new_term_slug, $custom_categorylabels) ? $custom_categorylabels[$new_term_slug] : false);
        $this->setcasawpCategoryTerm($new_term_slug, $label);
      }

      //add the new ones
      $category_terms = get_terms( array('casawp_category'), array('hide_empty' => false));
      foreach ($category_terms as $term) {
        if (in_array($term->slug, $new_categories)) {
          $connect_term_ids[] = (int) $term->term_id;
        }
      }
      if ($connect_term_ids) {
        wp_set_object_terms( $wp_post->ID, $connect_term_ids, 'casawp_category' );
      }
    }

  }

  public function setOfferFeatures($wp_post, $features, $casawp_id){
    $new_features = array();
    $old_features = array();

    //set post feature
    $old_features = array();
    $wp_feature_terms = wp_get_object_terms($wp_post->ID, 'casawp_feature');
    foreach ($wp_feature_terms as $term) {
      $old_features[] = $term->slug;
    }

    //supported
    if ($features) {
      foreach ($features as $feature) {
        $new_features[] = $feature;
      }
    }

    //have features changed?
    if (array_diff($new_features, $old_features) || array_diff($old_features, $new_features)) {
      $slugs_to_remove = array_diff($old_features, $new_features);
      $slugs_to_add    = array_diff($new_features, $old_features);
      $this->transcript[$casawp_id]['features_changed']['removed_feature'] = $slugs_to_remove;
      $this->transcript[$casawp_id]['features_changed']['added_feature'] = $slugs_to_add;

      //make sure the features exist first
      foreach ($slugs_to_add as $new_term_slug) {
        $label = false;
        $this->setcasawpFeatureTerm($new_term_slug, $label);
      }

      //add the new ones
      $feature_terms = get_terms( array('casawp_feature'), array('hide_empty' => false));
      foreach ($feature_terms as $term) {
        if (in_array($term->slug, $new_features)) {
          $connect_term_ids[] = (int) $term->term_id;
        }
      }
      if ($connect_term_ids) {
        wp_set_object_terms( $wp_post->ID, $connect_term_ids, 'casawp_feature' );
      }
    }

  }

  public function addToLog($transcript){
    $dir = CASASYNC_CUR_UPLOAD_BASEDIR  . '/casawp/logs';
    if (!file_exists($dir)) {
        mkdir($dir, 0777, true);
    }
    file_put_contents($dir."/".date('Y M').'.log', "\n".json_encode(array(date('Y-m-d H:i') => $transcript)), FILE_APPEND);
  }

  public function casawpImport(){
    if ($this->getImportFile()) {
      if (is_admin()) {
        $this->updateOffers();
        echo '<div id="message" class="updated"><p>casawp <strong>updated</strong>.</p><pre>' . print_r($this->transcript, true) . '</pre></div>';
      } else {
        //do task in the background
        add_action('asynchronous_import', array($this,'updateOffers'));
        wp_schedule_single_event(time(), 'asynchronous_import');
      }
    }
  }

  public function gatewaypoke(){
    add_action('asynchronous_gatewayupdate', array($this,'gatewaypokeanswer'));
    $this->addToLog('Scheduled an Update on: ' . time());
    wp_schedule_single_event(time(), 'asynchronous_gatewayupdate');
  }

  public function gatewaypokeanswer(){
    $this->updateImportFileThroughCasaGateway();
    $this->addToLog('gateway import answer: ' . time());
    $this->updateOffers();
  }

  public function updateImportFileThroughCasaGateway(){
    $apikey = get_option('casawp_api_key');
    $privatekey = get_option('casawp_private_key');
    $apiurl = 'http://immobilien-gateway.ch/rest/publisher-properties';
    $options = array(
      'format' => 'casa-xml',
      'debug' => 1
    );
    if ($apikey && $privatekey) {

      //specify the current UnixTimeStamp
      $timestamp = time();

      //sort the options alphabeticaly and combine it into the checkstring
      ksort($options);
      $checkstring = '';
      foreach ($options as $key => $value) {
          $checkstring .= $key . $value;
      }
      
      //add private key at end of the checkstring
      $checkstring .= $privatekey;

      //add the timestamp at the end of the checkstring
      $checkstring .= $timestamp;

      //hash it to specify the hmac
      $hmac = hash('sha256', $checkstring, false);

      //combine the query (DONT INCLUDE THE PRIVATE KEY!!!)
      $query = array(
          'hmac' => $hmac,
          'apikey' => $apikey,
          'timestamp' => $timestamp
      ) + $options;

      //build url
      $url = $apiurl . '?' . http_build_query($query);

      $response = false;
      try {
          //$url = 'http://casacloud.cloudcontrolapp.com' . '/rest/provider-properties?' . http_build_query($query);
          $ch = curl_init(); 
          curl_setopt($ch, CURLOPT_URL, $url); 
          curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
          curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
          $response = curl_exec($ch); 
          $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
          if($httpCode == 404) {
              $response = $httpCode;
          }
          curl_close($ch); 
      } catch (Exception $e) {
          $response =  $e->getMessage() ;
      }

      if ($response) {
        if (!is_dir(CASASYNC_CUR_UPLOAD_BASEDIR . '/casawp/import')) {
          mkdir(CASASYNC_CUR_UPLOAD_BASEDIR . '/casawp/import');
        }
        $file = CASASYNC_CUR_UPLOAD_BASEDIR  . '/casawp/import/data.xml';

        file_put_contents($file, $response);
      } 

      //echo '<div id="message" class="updated">XML wurde aktualisiert</div>';
    } else {
      echo '<div id="message" class="updated"> API Keys missing</div>';
    }
  }

  public function addToTranscript($msg){
    $this->transcript[] = $msg;
  }

  public function property2Array($property_xml){

    //$this->addToTranscript("*** CasaXml Conversion start ***");

    $propertydata['address'] = array(
        'country'       => ($property_xml->address->country->__toString() ?:''),
        'locality'      => ($property_xml->address->locality->__toString() ?:''),
        'region'        => ($property_xml->address->region->__toString() ?:''),
        'postal_code'   => ($property_xml->address->postalCode->__toString() ?:''),
        'street'        => ($property_xml->address->street->__toString() ?:''),
        'street_number' => ($property_xml->address->street_number->__toString() ?:''),
        'subunit'       => ($property_xml->address->subunit->__toString() ?:''),
        'lng'           => ($property_xml->address->geo ? $property_xml->address->geo->longitude->__toString():''),
        'lat'           => ($property_xml->address->geo ? $property_xml->address->geo->latitude->__toString():''),
    );

    $propertydata['last_update'] = new \DateTime((isset($property_xml->softwareInformation->lastUpdate) ? $property_xml->softwareInformation->lastUpdate->__toString() : ''));
    $propertydata['exportproperty_id'] = (isset($property_xml['id']) ? $property_xml['id']->__toString() : '');
    $propertydata['availability'] = ($property_xml->availability->__toString() ? $property_xml->availability->__toString() : 'available');
    $propertydata['price_currency'] = $property_xml->priceCurrency->__toString();
    $propertydata['price_currency'] = $property_xml->priceCurrency->__toString();
    $propertydata['price'] = $property_xml->price->__toString();
    $propertydata['price_property_segment'] = (!$property_xml->price['propertysegment']?:str_replace('2', '', $property_xml->price['propertysegment']->__toString()));
    $propertydata['net_price'] = $property_xml->netPrice->__toString();
    $propertydata['net_price_time_segment'] = ($property_xml->netPrice['timesegment'] ? strtoupper($property_xml->netPrice['timesegment']->__toString()) : '');
    $propertydata['net_price_property_segment'] = (!$property_xml->netPrice['propertysegment']?: str_replace('2', '', $property_xml->netPrice['propertysegment']->__toString()));
    $propertydata['gross_price'] = $property_xml->grossPrice->__toString();
    $propertydata['gross_price_time_segment'] = ($property_xml->grossPrice['timesegment'] ? strtoupper($property_xml->grossPrice['timesegment']->__toString()) : '');
    $propertydata['gross_price_property_segment'] = (!$property_xml->grossPrice['propertysegment']?:str_replace('2', '', $property_xml->grossPrice['propertysegment']->__toString()));
    $propertydata['status'] = 'active';
    $propertydata['type'] =  $property_xml->type->__toString();
    $propertydata['zoneTypes'] = ($property_xml->zoneTypes ? $property_xml->zoneTypes->__toString() : '');
    $propertydata['parcelNumbers'] = ($property_xml->parcelNumbers ? $property_xml->parcelNumbers->__toString() : '');

    $propertydata['property_categories'] = array();
    if ($property_xml->categories) {
        foreach ($property_xml->categories->category as $xml_category) {
            $propertydata['property_categories'][] = $xml_category->__toString();
        }
    }

    $propertydata['property_utilities'] = array();
    if ($property_xml->utilities) {
        foreach ($property_xml->utilities->utility as $xml_utility) {
            $propertydata['property_utilities'][] = $xml_utility->__toString();
        }
    }

    $propertydata['numeric_values'] = array();
    if ($property_xml->numericValues) {
        foreach ($property_xml->numericValues->value as $xml_numval) {
            $key = (isset($xml_numval['key']) ? $xml_numval['key']->__toString() : false);
            if ($key) {
                $value = $xml_numval->__toString();
                $propertydata['numeric_values'][] = array(
                    'key' => $key,
                    'value' => $value
                );
            }
        }
    }

    $propertydata['features'] = array();
    if ($property_xml->features) {
        foreach ($property_xml->features->feature as $xml_feature) {
            $propertydata['features'][] = $xml_feature->__toString();
        }
    }

    //seller ****************************************************************
    if ($property_xml->seller) {

        $propertydata['organization'] = array();

        //organization
        if ($property_xml->seller->organization) {
            $propertydata['organization']['displayName']    = $property_xml->seller->organization->legalName->__toString();
            $propertydata['organization']['addition']         = $property_xml->seller->organization->brand->__toString();
            $propertydata['organization']['email']         = $property_xml->seller->organization->email->__toString();
            $propertydata['organization']['email_rem']     = $property_xml->seller->organization->emailRem->__toString();
            $propertydata['organization']['fax']           = $property_xml->seller->organization->fax->__toString();
            $propertydata['organization']['phone']         = $property_xml->seller->organization->phone->__toString();
            $propertydata['organization']['website_url']   = ($property_xml->seller->organization ? $property_xml->seller->organization->website->__toString() : '');
            $propertydata['organization']['website_title'] = ($property_xml->seller->organization && $property_xml->seller->organization->website ? $property_xml->seller->organization->website['title']->__toString() : '');
            $propertydata['organization']['website_label'] = ($property_xml->seller->organization && $property_xml->seller->organization->website ? $property_xml->seller->organization->website['label']->__toString() : '');
        }
       
        //viewPerson
        $propertydata['viewPerson'] = array();
        if ($property_xml->seller->viewPerson) {
            $person                                  = $property_xml->seller->viewPerson;
            $propertydata['viewPerson']['function']  = $person->function->__toString();
            $propertydata['viewPerson']['firstName'] = $person->givenName->__toString();
            $propertydata['viewPerson']['lastName']  = $person->familyName->__toString();
            $propertydata['viewPerson']['email']     = $person->email->__toString();
            $propertydata['viewPerson']['fax']       = $person->fax->__toString();
            $propertydata['viewPerson']['phone']     = $person->phone->__toString();
            $propertydata['viewPerson']['mobile']    = $person->mobile->__toString();
            $propertydata['viewPerson']['gender']    = $person->gender->__toString();
            $propertydata['viewPerson']['note']      = $person->note->__toString();
        }

        //visitPerson
        $propertydata['visitPerson'] = array();
        if ($property_xml->seller->visitPerson) {
            $person                                   = $property_xml->seller->visitPerson;
            $propertydata['visitPerson']['function']  = $person->function->__toString();
            $propertydata['visitPerson']['firstName'] = $person->givenName->__toString();
            $propertydata['visitPerson']['lastName']  = $person->familyName->__toString();
            $propertydata['visitPerson']['email']     = $person->email->__toString();
            $propertydata['visitPerson']['fax']       = $person->fax->__toString();
            $propertydata['visitPerson']['phone']     = $person->phone->__toString();
            $propertydata['visitPerson']['mobile']    = $person->mobile->__toString();
            $propertydata['visitPerson']['gender']    = $person->gender->__toString();
            $propertydata['visitPerson']['note']      = $person->note->__toString();
        }

        //inquiryPerson
        $propertydata['inquiryPerson'] = array();
        if ($property_xml->seller->inquiryPerson) {
            $person                                     = $property_xml->seller->inquiryPerson;
            $propertydata['inquiryPerson']['function']  = $person->function->__toString();
            $propertydata['inquiryPerson']['firstName'] = $person->givenName->__toString();
            $propertydata['inquiryPerson']['lastName']  = $person->familyName->__toString();
            $propertydata['inquiryPerson']['email']     = $person->email->__toString();
            $propertydata['inquiryPerson']['fax']       = $person->fax->__toString();
            $propertydata['inquiryPerson']['phone']     = $person->phone->__toString();
            $propertydata['inquiryPerson']['mobile']    = $person->mobile->__toString();
            $propertydata['inquiryPerson']['gender']    = $person->gender->__toString();
            $propertydata['inquiryPerson']['note']      = $person->note->__toString();
        }

    }
    //END sellers ****************************************************************



    //offers
    $offerDatas = array();
    if ($property_xml->offers) {
        foreach ($property_xml->offers->offer as $offer_xml) { 
            $offerData['lang'] =  strtolower($offer_xml['lang']->__toString());
            $offerData['type'] =  $property_xml->type->__toString();
            if ($property_xml->start) {
              $offerData['start'] =  new \DateTime($property_xml->start->__toString());
            } else {
              $offerData['start'] = null;
            }
            $offerData['status'] = 'active';
            $offerData['name'] = $offer_xml->name->__toString();
            $offerData['excerpt'] = $property_xml->excerpt->__toString();
            
            //publisher settings
            $publishingDatas = array();
            if ($offer_xml->publishers) {
                foreach ($offer_xml->publishers->publisher as $publisher_xml) {
                    $options = array();
                    if ($publisher_xml->options) {
                        foreach ($publisher_xml->options->option as $option_xml) {
                            $options[$option_xml['key']->__toString()][] = $option_xml->__toString();
                        }
                    }
                    $publishingDatas[$publisher_xml['id']->__toString()] = array(
                        'options' => $options
                    );
                }
            }

            $offerData['publish'] = $publishingDatas;

            //urls
            $urlDatas = array();
            if ($offer_xml->urls) {
                foreach ($offer_xml->urls->url as $xml_url) {
                    $title = (isset($xml_url['title']) ? $xml_url['title']->__toString() : false);
                    $type = (isset($xml_url['type']) ? $xml_url['type']->__toString() : false);
                    $label = (isset($xml_url['label']) ? $xml_url['label']->__toString() : false);
                    $url = $xml_url->__toString();
                    
                    $urlDatas[] = array(
                        'title' => $title,
                        'type' => $type,
                        'label' => $label,
                        'url' => $url,

                    );
                }
            }
            $offerData['urls'] = $urlDatas;
                
            //descriptions
            $descriptionDatas = array();
            if ($offer_xml->descriptions) {
                foreach ($offer_xml->descriptions->description as $xml_description) {
                    $title = (isset($xml_description['title']) ? $xml_description['title']->__toString() : false);
                    $text = $xml_description->__toString();
                    
                    $descriptionDatas[] = array(
                        'title' => $title,
                        'text' => $text,
                    );
                }
            }
            $offerData['descriptions'] = $descriptionDatas;

            //attachments
            $offerData['offer_medias'] = array();
            if ($offer_xml->attachments) {
                foreach ($offer_xml->attachments->media as $xml_media) {
                    if ($xml_media->file) {
                        $source = dirname($this->file) . $xml_media->file->__toString();
                    } elseif ($xml_media->url) {
                        $source = $xml_media->url->__toString();
                        $source = implode('/', array_map('rawurlencode', explode('/', $source)));
                        $source = str_replace('http%3A//', 'http://', $source);
                        $source = str_replace('https%3A//', 'https://', $source);                        
                    } else {
                        $this->addToTranscript("file or url missing from attachment media!");
                        continue;
                    }
                    $offerData['offer_medias'][] = array(
                        'alt' => $xml_media->alt->__toString(),
                        'title' => $xml_media->title->__toString(),
                        'caption' => $xml_media->caption->__toString(),
                        'description' => $xml_media->description->__toString(),
                        'type' => (isset($xml_media['type']) ? $xml_media['type']->__toString() : 'image'),
                        'media' => array(
                            'original_file' => $source,
                        )
                    );
                }
            }

            $offerDatas[] = $offerData;

        }            
    }        

    $propertydata['offers'] = $offerDatas;


    //$this->addToTranscript("*** CasaXml Conversion complete ***");

    return $propertydata;

}

  public function updateOffers(){

     //make sure dires exist

    if (!is_dir(CASASYNC_CUR_UPLOAD_BASEDIR . '/casawp')) {
      mkdir(CASASYNC_CUR_UPLOAD_BASEDIR . '/casawp');
    }
    if (!is_dir(CASASYNC_CUR_UPLOAD_BASEDIR . '/casawp/import')) {
      mkdir(CASASYNC_CUR_UPLOAD_BASEDIR . '/casawp/import');
    }
    if (!is_dir(CASASYNC_CUR_UPLOAD_BASEDIR . '/casawp/import/attachment')) {
      mkdir(CASASYNC_CUR_UPLOAD_BASEDIR . '/casawp/import/attachment');
    }
    if (!is_dir(CASASYNC_CUR_UPLOAD_BASEDIR . '/casawp/import/attachment/externalsync')) {
      mkdir(CASASYNC_CUR_UPLOAD_BASEDIR . '/casawp/import/attachment/externalsync');
    }


    $this->renameImportFileTo(CASASYNC_CUR_UPLOAD_BASEDIR  . '/casawp/import/data-done.xml');
    set_time_limit(300);
    global $wpdb;
    $found_posts = array();

    $xml = simplexml_load_file($this->getImportFile(), 'SimpleXMLElement', LIBXML_NOCDATA);
    foreach ($xml->properties->property as $property) {
      $propertyData = $this->property2Array($property);
      //make main language first and single out if not multilingual
      $theoffers = array();
      $i = 0;
      foreach ($propertyData['offers'] as $offer) {
        $i++;
        if ($offer['lang'] == $this->getMainLang()) {
          $theoffers[0] = $offer;
        } else {
          if ($this->hasWPML()) {
            $theoffers[$i] = $offer;
          }
        }
      }
      $offer_pos = 0;
      $first_offer_trid = false;
      foreach ($theoffers as $offerData) {
        $offer_pos++;


        //is it already in db
        $casawp_id = $propertyData['exportproperty_id'] . $offerData['lang'];

        $the_query = new \WP_Query( 'post_type=casawp_property&suppress_filters=true&meta_key=casawp_id&meta_value=' . $casawp_id );
        $wp_post = false;
        while ( $the_query->have_posts() ) :
          $the_query->the_post();
          global $post;
          $wp_post = $post;
        endwhile;
        wp_reset_postdata();

        //if not create a basic property
        if (!$wp_post) {
          $this->transcript[$casawp_id]['action'] = 'new';
          $the_post['post_title'] = 'unsaved property';
          $the_post['post_content'] = 'unsaved property';
          $the_post['post_status'] = 'pending';
          $the_post['post_type'] = 'casawp_property';
          $insert_id = wp_insert_post($the_post);
          update_post_meta($insert_id, 'casawp_id', $casawp_id);
          $wp_post = get_post($insert_id, OBJECT, 'raw');
        }
        $found_posts[] = $wp_post->ID;
        $this->updateOffer($casawp_id, $offer_pos, $propertyData, $offerData, $wp_post);

      }
    }

    //3. remove all the unused properties
    $properties_to_remove = get_posts(  array(
      'suppress_filters'=>true,
      'language'=>'ALL',
      'numberposts' =>  100,
      'exclude'     =>  $found_posts,
      'post_type'   =>  'casawp_property',
      'post_status' =>  'publish'
      )
    );
    foreach ($properties_to_remove as $prop_to_rm) {
      //remove the attachments
      $attachments = get_posts( array(
        'suppress_filters'=>true,
        'language'=>'ALL',
        'post_type'      => 'attachment',
        'posts_per_page' => -1,
        'post_parent'    => $prop_to_rm->ID,
        'exclude'        => get_post_thumbnail_id()
      ) );
      if ( $attachments ) {
        foreach ( $attachments as $attachment ) {
          $attachment_id = $attachment->ID;
        }
      }
      wp_trash_post($prop_to_rm->ID);
      $this->transcript['properties_removed'] = count($properties_to_remove);
    }

    //flush_rewrite_rules();

    $this->addToLog($this->transcript);
  }

  public function simpleXMLget($node, $fallback = false){
    if ($node) {
      $result = $node->__toString();
      if ($result) {
        return $result;
      }
    }
    return $fallback;
  }


  public function updateOffer($casawp_id, $offer_pos, $property, $offer, $wp_post){
    //$publisher_options = $offer->publish;
    $publisher_options = array();
    if (isset($offer['publish'])) {
      foreach ($offer['publish'] as $slug => $content) {
        if (isset($content['options'])) {
          foreach ($content['options'] as $key => $value) {
            $publisher_options[$key] = $value;
          }
        }
      }
    }
    

    //lang
    $this->updateInsertWPMLconnection($offer_pos, $wp_post, $offer['lang'], $casawp_id);

    /* main post data */
    $new_main_data = array(
      'ID'            => $wp_post->ID,
      'post_title'    => $offer['name'],
      'post_content'  => $this->extractDescription($offer),
      'post_status'   => 'publish',
      'post_type'     => 'casawp_property',
      'post_excerpt'  => $offer['excerpt'],
      'post_date'     => $property['last_update']->format('Y-m-d H:i:s'),
      //'post_modified' => date('Y-m-d H:i:s', strtotime($property->software->lastUpdate->__toString())),
    );

    $old_main_data = array(
      'ID'            => $wp_post->ID,
      'post_title'    => $wp_post->post_title   ,
      'post_content'  => $wp_post->post_content ,
      'post_status'   => $wp_post->post_status  ,
      'post_type'     => $wp_post->post_type    ,
      'post_excerpt'  => $wp_post->post_excerpt ,
      'post_date'     => $wp_post->post_date    ,
      //'post_modified' => $wp_post->post_modified,
    );
    if ($new_main_data != $old_main_data) {
      foreach ($old_main_data as $key => $value) {
        if ($new_main_data[$key] != $old_main_data[$key]) {
          $this->transcript[$casawp_id]['main_data'][$key]['from'] = $old_main_data[$key];
          $this->transcript[$casawp_id]['main_data'][$key]['to'] = $new_main_data[$key];
        }
      }
      //persist change
      $newPostID = wp_insert_post($new_main_data);

    }


    /* Post Metas */
    $old_meta_data = array();
    $meta_values = get_post_meta($wp_post->ID, null, true);
    foreach ($meta_values as $key => $meta_value) {
      $old_meta_data[$key] = $meta_value[0];
    }

    $new_meta_data = array();
    //$casawp_visitInformation = $property->visitInformation->__toString();
    //$casawp_property_url = $property->url->__toString();
    $new_meta_data['property_address_country']       = $property['address']['country'];
    $new_meta_data['property_address_locality']      = $property['address']['locality'];
    $new_meta_data['property_address_region']        = $property['address']['region'];
    $new_meta_data['property_address_postalcode']    = $property['address']['postal_code'];
    $new_meta_data['property_address_streetaddress'] = $property['address']['street'];
    $new_meta_data['property_address_streetnumber']  = $property['address']['street_number'];
    $new_meta_data['property_geo_latitude']          = $property['address']['lat'];
    $new_meta_data['property_geo_longitude']         = $property['address']['lng'];

    if ($offer['start']) {
      $new_meta_data['start']                          = $offer['start']->format('Y-m-d H:i:s');
    }
    
    //$new_meta_data['referenceId']                    = $this->simpleXMLget($property->referenceId);
    if (isset($property['organization'])) {
      //$new_meta_data['seller_org_phone_direct'] = $property['organization'][''];
      $new_meta_data['seller_org_phone_central'] = $property['organization']['phone'];
      //$new_meta_data['seller_org_phone_mobile'] = $property['organization'][''];
      $new_meta_data['seller_org_legalname']                     = $property['organization']['displayName'];
      $new_meta_data['seller_org_brand']                         = $property['organization']['addition'];
      if (isset($property['organization']['address'])) {
        $new_meta_data['seller_org_address_country']               = $property['organization']['address']['country'];
        $new_meta_data['seller_org_address_locality']              = $property['organization']['address']['locality'];
        $new_meta_data['seller_org_address_region']                = $property['organization']['address']['region'];
        $new_meta_data['seller_org_address_postalcode']            = $property['organization']['address']['postal_code'];
        $new_meta_data['seller_org_address_postofficeboxnumber']   = $property['organization']['address']['postofficeboxnumber'];
        $new_meta_data['seller_org_address_streetaddress']         = $property['organization']['address']['street'].' '.$property['organization']['address']['street_number'];
      }
    }

    $personType = 'view';
    if (isset($property[$personType.'Person']) && $property[$personType.'Person']) {
      $prefix = 'seller' . ($personType != 'view' ? '_' . $personType : '') . '_person_';
      $new_meta_data[$prefix.'function']      = $property[$personType.'Person']['function'];
      $new_meta_data[$prefix.'givenname']     = $property[$personType.'Person']['firstName'];
      $new_meta_data[$prefix.'familyname']    = $property[$personType.'Person']['lastName'];
      $new_meta_data[$prefix.'email']         = $property[$personType.'Person']['email'];
      $new_meta_data[$prefix.'fax']           = $property[$personType.'Person']['fax'];
      $new_meta_data[$prefix.'phone_direct']  = $property[$personType.'Person']['phone'];
      $new_meta_data[$prefix.'phone_mobile']  = $property[$personType.'Person']['mobile'];
      $new_meta_data[$prefix.'gender']        = $property[$personType.'Person']['gender'];
    }
    
    $personType = 'inquiry';
    if (isset($property[$personType.'Person']) && $property[$personType.'Person']) {
      $prefix = 'seller' . ($personType != 'view' ? '_' . $personType : '') . '_person_';
      $new_meta_data[$prefix.'function']      = $property[$personType.'Person']['function'];
      $new_meta_data[$prefix.'givenname']     = $property[$personType.'Person']['firstName'];
      $new_meta_data[$prefix.'familyname']    = $property[$personType.'Person']['lastName'];
      $new_meta_data[$prefix.'email']         = $property[$personType.'Person']['email'];
      $new_meta_data[$prefix.'fax']           = $property[$personType.'Person']['fax'];
      $new_meta_data[$prefix.'phone_direct']  = $property[$personType.'Person']['phone'];
      $new_meta_data[$prefix.'phone_mobile']  = $property[$personType.'Person']['mobile'];
      $new_meta_data[$prefix.'gender']        = $property[$personType.'Person']['gender'];
    }

    $personType = 'visit';
    if (isset($property[$personType.'Person']) && $property[$personType.'Person']) {
      $prefix = 'seller' . ($personType != 'view' ? '_' . $personType : '') . '_person_';
      $new_meta_data[$prefix.'function']      = $property[$personType.'Person']['function'];
      $new_meta_data[$prefix.'givenname']     = $property[$personType.'Person']['firstName'];
      $new_meta_data[$prefix.'familyname']    = $property[$personType.'Person']['lastName'];
      $new_meta_data[$prefix.'email']         = $property[$personType.'Person']['email'];
      $new_meta_data[$prefix.'fax']           = $property[$personType.'Person']['fax'];
      $new_meta_data[$prefix.'phone_direct']  = $property[$personType.'Person']['phone'];
      $new_meta_data[$prefix.'phone_mobile']  = $property[$personType.'Person']['mobile'];
      $new_meta_data[$prefix.'gender']        = $property[$personType.'Person']['gender'];
    }




   //urls
   $url = null;
   $the_urls = array();
   if ($offer['urls']) {
     foreach ($offer['urls'] as $url) {
       $href = $url['url'];
       if (! (substr( $href, 0, 7 ) === "http://" || substr( $href, 0, 8 ) === "https://") ) {
        $href = 'http://'.$href;
       }

       $label = (isset($url['label']) ? $url['label'] : false);
       $title = (isset($url['title']) ? $url['title'] : false);
       $type =  (isset($url['type'])  ? (string) $url['type'] : false);
       if ($type ) {
         $the_urls[$type][] = array(
           'href' => $href,
           'label' => $label,
           'title' => $title
         );
       } else {
         $the_urls[] = array(
           'href' => $href,
           'label' => $label,
           'title' =>  $title
         );
       }
     }
     ksort($the_urls);
     $new_meta_data['the_urls'] = $the_urls;
  }


  //tags
  /*$the_tags = array();
  if ($xmloffer->tags) {
    foreach ($xmloffer->tags->tag as $tag) {
      $the_tags[] = $this->simpleXMLget($tag);
    }
  }
  $new_meta_data['the_tags'] = $the_tags;*/



    $offer_type     = $property['type'];
    $new_meta_data['price_currency'] = $property['price_currency'];

    $new_meta_data['availability'] = $property['availability'];

    //prices 
    if ($property['price']) {
      $new_meta_data['price'] = $property['price'];
      $new_meta_data['price_propertysegment'] = $property['price_property_segment'];
    }

    if ($property['net_price']) {
      $new_meta_data['netPrice'] = $property['net_price'];
      $new_meta_data['netPrice_timesegment'] = $property['net_price_time_segment'];
      $new_meta_data['netPrice_propertysegment'] = $property['net_price_property_segment'];
    }

    if ($property['gross_price']) {
      $new_meta_data['netPrice'] = $property['gross_price'];
      $new_meta_data['netPrice_timesegment'] = $property['gross_price_time_segment'];
      $new_meta_data['netPrice_propertysegment'] = $property['gross_price_property_segment'];
    }

    /*
    $extraPrice = array();
    if($xmloffer->extraCost){
      foreach ($xmloffer->extraCost as $extraCost) {
        $propertysegment = '';
        $timesegment     = $extraCost['timesegment'];

        if (!in_array($timesegment, array('m','w','d','y','h','infinite'))) {
          $timesegment = ($offer_type == 'rent' ? 'm' : 'infinite');
        }
        $propertysegment = $extraCost['propertysegment'];
        if (!in_array($propertysegment, array('m2','km2','full'))) {
          $propertysegment = 'full';
        }
        if (is_object($propertysegment)) {
          $propertysegment = $propertysegment->__toString(); 
        }
        $the_extraPrice = (float) $extraCost->__toString();

        $extraPrice[] = array(
          'price' => $the_extraPrice,
          'title' => (string) $extraCost['title'],
          'timesegment' => $timesegment->__toString(),
          'propertysegment' => $propertysegment,
          'currency' => $new_meta_data['price_currency'],
          'frequency' => 1
        );
      }
      $new_meta_data['extraPrice'] = $extraPrice;
    }*/

    //price for order
    $tmp_price      = (array_key_exists('price', $new_meta_data)      && $new_meta_data['price'] !== 0)      ? ($new_meta_data['price'])      :(9999999999);
    $tmp_grossPrice = (array_key_exists('grossPrice', $new_meta_data) && $new_meta_data['grossPrice'] !== 0) ? ($new_meta_data['grossPrice']) :(9999999999);
    $tmp_netPrice   = (array_key_exists('netPrice', $new_meta_data)   && $new_meta_data['netPrice'] !== 0)   ? ($new_meta_data['netPrice'])   :(9999999999);
    $new_meta_data['priceForOrder'] = str_pad($tmp_netPrice, 10, 0, STR_PAD_LEFT) . str_pad($tmp_grossPrice, 10, 0, STR_PAD_LEFT) . str_pad($tmp_price, 10, 0, STR_PAD_LEFT);

    //nuvals    
    $numericValues = array();
    foreach ($property['numeric_values'] as $numval) {
      $numericValues[$numval['key']] = $numval['value'];
    }
    $new_meta_data = array_merge($new_meta_data, $numericValues);

    //integratedOffers
    //$integratedOffers = $this->integratedOffersToArray($property->offer->integratedOffers);
    //$new_meta_data = array_merge($new_meta_data, $integratedOffers);


    //clean up arrays   
    foreach ($old_meta_data as $key => $value) {
      if (!in_array($key, $this->meta_keys)) {
        unset($old_meta_data[$key]);
      }
    }
    ksort($old_meta_data);
    foreach ($new_meta_data as $key => $value) {
      if (!in_array($key, $this->meta_keys)) {
        $this->transcript['error']['unknown_metakeys'][$key] = $value;
      }
      if (!$value || !in_array($key, $this->meta_keys)) {
        unset($new_meta_data[$key]);
      }
    }
    ksort($new_meta_data);


    if ($new_meta_data != $old_meta_data) {
      foreach ($this->meta_keys as $key) {
        if (in_array($key, array('the_urls', 'the_url', 'the_tags', 'extraPrice'))) {
          if (isset($new_meta_data[$key])) {
            $new_meta_data[$key] = $new_meta_data[$key];
          }
        }
        $newval = (isset($new_meta_data[$key]) ? $new_meta_data[$key] : '');
        $oldval = (isset($old_meta_data[$key]) ? maybe_unserialize($old_meta_data[$key]) : '');
        if (($oldval || $newval) && $oldval != $newval) {
          update_post_meta($wp_post->ID, $key, $newval);
          $this->transcript[$casawp_id]['meta_data'][$key]['from'] = $oldval;
          $this->transcript[$casawp_id]['meta_data'][$key]['to'] = $newval;
        }
      }

      //remove supurflous meta_data
      foreach ($old_meta_data as $key => $value) {
        if (!isset($new_meta_data[$key])) {
          //remove
          delete_post_meta($wp_post->ID, $key, $value);
          $this->transcript[$casawp_id]['meta_data'][$key] = 'removed';

        }
      }
    }

    if (isset($property['property_categories'])) {
      $custom_categories = array();
      foreach ($publisher_options as $key => $values) {
        if (strpos($key, 'custom_category') === 0) {
          $parts = explode('_', $key);
          $sort = (isset($parts[2]) && is_numeric($parts[2]) ? $parts[2] : false);
          $slug = (isset($parts[3]) && $parts[3] == 'slug' ? true : false);
          $label = (isset($parts[3]) && $parts[3] == 'label' ? true : false);
          if ($slug) {
            $custom_categories[$sort]['slug'] = $values[0];
          } elseif ($label) {
            $custom_categories[$label]['label'] = $values[0];
          }
          
        }
      }
      $this->setOfferCategories($wp_post, $property['property_categories'], $custom_categories, $casawp_id);
    }
    
    $this->setOfferFeatures($wp_post, $property['features'], $casawp_id);
    $this->setOfferSalestype($wp_post, $property['type'], $casawp_id);
    $this->setOfferAvailability($wp_post, $property['availability'], $casawp_id);
    $this->setOfferLocalities($wp_post, $property['address'], $casawp_id);
    $this->setOfferAttachments($offer['offer_medias'] , $wp_post, $property['exportproperty_id'], $casawp_id);
    

  }
}
