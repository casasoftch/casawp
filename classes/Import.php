<?php
namespace CasaSync;

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
      'surface_living'              ,
      'surface_property'            ,
      'surface_usable'              ,
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

      'casasync_visitInformation'                    ,
      'casasync_property_url'                        ,
      'casasync_property_address_country'            ,
      'casasync_property_address_locality'           ,
      'casasync_property_address_region'             ,
      'casasync_property_address_postalcode'         ,
      'casasync_property_address_streetaddress'      ,
      'casasync_property_address_streetnumber'       ,
      'casasync_urls'                                ,
      'casasync_start'                               ,
      'casasync_referenceId'                         ,
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
      'casasync_property_geo_latitude'               ,
      'casasync_property_geo_longitude'              ,
      'price'                                        ,
      'grossPrice'                                   ,
      'netPrice'                                     ,
      'the_urls'                                     ,
      'extraPrice'                                   ,

      'distance_public_transport'                    ,
      'distance_shop'                                ,
      'distance_kindergarten'                        ,
      'distance_motorway'                            ,
      'distance_school1'                             ,
      'distance_school2'                             ,

      'casasync_features'                            ,
  );

  public function __construct(){
    $this->conversion = new Conversion;
    add_action( 'init', array($this, 'casasyncImport') );
    //$this->casasyncImport();
  }

  public function getImportFile(){
    if (!$this->importFile) {
      $good_to_go = false;
      if (!is_dir(CASASYNC_CUR_UPLOAD_BASEDIR . '/casasync/import')) {
        mkdir(CASASYNC_CUR_UPLOAD_BASEDIR . '/casasync/import');
      }
      $file = CASASYNC_CUR_UPLOAD_BASEDIR  . '/casasync/import/data.xml';
      if (file_exists($file)) {
        $good_to_go = true;
      } else {
        //if force last check for last
        if (isset($_GET['force_last_import'])) {
          $file = CASASYNC_CUR_UPLOAD_BASEDIR  . '/casasync/import/data-done.xml';
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
    copy ( $this->getImportFile() , CASASYNC_CUR_UPLOAD_BASEDIR  . '/casasync/done/' . date('Y_m_d_H_i_s') . '_completed.xml');
    return true;
  }

  public function extractDescription($offer){
    $the_description = '';
    foreach ($offer->description as $description) {
      $the_description .= ($the_description ? '<hr class="property-separator" />' : '');
      if ($description['title']) {
        $the_description .= '<h2>' . $description['title']->__toString() . '</h2>';
      }
      $the_description .= $description->__toString();
    }
    return $the_description;
  }

  public function getLastTranscript(){
    return $this->lastTranscript;
  }

  public function setCasasyncCategoryTerm($term_slug, $label = false) {
    $label = (!$label ? $term_slug : $label);
    $term = get_term_by('slug', $term_slug, 'casasync_category', OBJECT, 'raw' );
    //$existing_term_id = term_exists( $label, 'casasync_category');
    $existing_term_id = false;
    if ($term) {
      if (
        $term->slug != $term_slug
        || $term->name != $label
      ) {
        wp_update_term($term->term_id, 'casasync_category', array(
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
        'casasync_category',
        $options
      );
      return $id;
    }
  }

  public function convertXmlPublisherOptions($publisher_options_xml){
    $publisher_options = array();
      foreach ($publisher_options_xml as $settings) {
        if ($settings['id'] == 'casasync') {
          if ($settings->options) {
            foreach ($settings->options->option as $option) {
              $key   = (isset($option['name']) ? $option['name'] : false);
              $value = $option->__toString();
              if ($key && $value) {
                if (strpos($key, 'custom_category_') === 0) {
                  $parts = explode('_', $key);
                  $sort = (isset($parts[2]) && is_numeric($parts[2]) ? $parts[2] : false);
                  $slug = (isset($parts[3]) && $parts[3] == 'slug' ? true : false);
                  $label = (isset($parts[3]) && $parts[3] == 'label' ? true : false);
                  if ($slug) {
                    $publisher_options['custom_categories'][$sort]['slug'] = $value;
                  } elseif ($label) {
                    $publisher_options['custom_categories'][$label]['label'] = $value;
                  }
                }
              }
            }
          }
        }
      }

    return $publisher_options;
  }

  public function casasyncUploadAttachment($the_mediaitem, $post_id, $property_id) {
    if ($the_mediaitem['file']) {
      $filename = '/casasync/import/attachment/'. $the_mediaitem['file'];
    } elseif ($the_mediaitem['url']) { //external
      $filename = '/casasync/import/attachment/externalsync/' . $property_id . '/' . basename($the_mediaitem['url']);

      //extention is required
      $file_parts = pathinfo($filename);
      if (!isset($file_parts['extension'])) {
          $filename = $filename . '.jpg';
      }
      if (!is_file(CASASYNC_CUR_UPLOAD_BASEDIR . $filename)) {
        if (!is_dir(CASASYNC_CUR_UPLOAD_BASEDIR . '/casasync/import/attachment/externalsync')) {
          mkdir(CASASYNC_CUR_UPLOAD_BASEDIR . '/casasync/import/attachment/externalsync');
        }
        if (!is_dir(CASASYNC_CUR_UPLOAD_BASEDIR . '/casasync/import/attachment/externalsync/' . $property_id)) {
          mkdir(CASASYNC_CUR_UPLOAD_BASEDIR . '/casasync/import/attachment/externalsync/' . $property_id);
        }
        if (is_file(CASASYNC_CUR_UPLOAD_BASEDIR . $filename )) {
          $could_copy = copy($the_mediaitem['url'], CASASYNC_CUR_UPLOAD_BASEDIR . $filename );
        } else {
          $could_copy = false;
        }

        if (!$could_copy) {
          $filename = false;
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
      $term = get_term_by('slug', $the_mediaitem['type'], 'casasync_attachment_type');
      $term_id = $term->term_id;
      wp_set_post_terms( $attach_id,  array($term_id), 'casasync_attachment_type' );

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

  public function updateInsertWPMLconnection($offer_pos, $wp_post, $lang, $casasync_id){
    global $wpdb;
    if ($this->hasWPML()) {
      if ($offer_pos == 1) {
        $this->curtrid = $wp_post->ID;
      }

      $row = $wpdb->get_row( 'SELECT * FROM '.$wpdb->prefix.'icl_translations 
          WHERE element_id = "' .$wp_post->ID. '" 
          AND element_type = "post_' . $wp_post->post_type . '" 
          
        '
        );
      if ($row) {
        if (
          $row->trid != $this->curtrid
          || $row->language_code != $lang
          || $row->source_language_code != $this->getMainLang()
        ) {
        $wpdb->update( $wpdb->prefix.'icl_translations',
            array(
              'trid' => $this->curtrid,
              'language_code' => $lang,
              'source_language_code' => $this->getMainLang()
            ),
            array(
              'element_id' => $wp_post->ID,
              'element_type' => 'post_' . $wp_post->post_type
            ) );
            $this->transcript[$casasync_id]['language'][] = 'updated';
        }
      } elseif(!$row) {
        $wpdb->insert( $wpdb->prefix.'icl_translations',
            array(
              'trid' => $this->curtrid,
              'language_code' => $lang,
              'source_language_code' => $this->getMainLang(),
              'element_id' => $wp_post->ID,
              'element_type' => 'post_' . $wp_post->post_type
            )
        );
        $this->transcript[$casasync_id]['language'][] = 'inserted';
      }
    }
  }

  public function personsXMLtoArray($seller){
    $r_persons = array();
    if ($seller && $seller->person) {
      foreach ($seller->person as $person) {
        $type = $person['type']->__toString();
        if ($type && in_array($type, array('inquiry', 'view'))) {
          $prefix = 'seller' . ($type != 'view' ? '_' . $type : '') . '_person_';

          $r_persons[$prefix.'function']   = $this->simpleXMLget($person->function);
          $r_persons[$prefix.'givenname']  = $this->simpleXMLget($person->givenName);
          $r_persons[$prefix.'familyname'] = $this->simpleXMLget($person->familyName);
          $r_persons[$prefix.'email']      = $this->simpleXMLget($person->email);
          $r_persons[$prefix.'fax']        = $this->simpleXMLget($person->faxNumber);
          $r_persons[$prefix.'phone_direct'] = '';
          $r_persons[$prefix.'phone_central'] = '';
          $r_persons[$prefix.'phone_mobile'] = '';
          if ($person->phone) {
            $central = false;
            foreach ($person->phone as $phone) {
              if ($phone['type']) {
                switch ($phone['type']->__toString()) {
                  case 'direct':
                    $r_persons[$prefix.'phone_direct'] = $phone->__toString();
                    break;
                  case 'central':
                    $r_persons[$prefix.'phone_central'] = $phone->__toString();
                    break;
                  case 'mobile':
                    $r_persons[$prefix.'phone_mobile'] = $phone->__toString();
                    break;
                  default:
                    if (!$phone_central) {
                      $r_persons[$prefix.'phone_central'] = $phone->__toString();
                    }
                    break;
                }
              } else {
                if (!$central) {
                  $r_persons[$prefix.'phone_central'] = $phone->__toString();
                }
              }
            }
          }
          $r_persons[$prefix.'gender'] = $this->simpleXMLget($person->gender);
        }
      }
    }

    return $r_persons;
  }

  public function numvalsXMLtoArray($numericValues){
    //set numericValues
    $the_numvals = array();
    if ($numericValues && $numericValues->value) {
      foreach ($numericValues->value as $numval) {
        if ($numval->__toString() && $numval['key']) {
          $values = explode('+', $numval->__toString());
          $the_values = array();
          foreach ($values as $value) {
            $numval_parts = explode('to', $value);
            $numval_from = $numval_parts[0];
            $numval_to = (isset($numval_parts[1]) ? $numval_parts[1] : false);
            $the_values[] = array(
              'from' => $this->conversion->casasync_numStringToArray($numval_from),
              'to' => $this->conversion->casasync_numStringToArray($numval_to)
            );
          }
          $the_numvals[(string)$numval['key']] = $the_values;
        }
      }
    }
    $all_distance_keys     = $this->conversion->casasync_get_allDistanceKeys();
    $all_numval_keys       = $this->conversion->casasync_get_allNumvalKeys();
    $r_distances         = array();
    $r_numvals            = array();

    foreach ($the_numvals as $key => $numval) {
      if (in_array($key, $all_distance_keys)) {
        $the_value = '';
        foreach ($numval as $key2 => $value) {
          $the_value .= ($key2 != 0 ? '+' : '') . '[' . $value['from']['value'] . $value['from']['si'] . ']';
        }
        $r_distances[$key] = $the_value;
      }
      if (in_array($key, $all_numval_keys)) {
        switch ($key) {
          //multiple simple values
          case 'multiple':
            /*$the_value = '';
            foreach ($numval as $key2 => $value) {
              $the_value .= ($key2 != 0 ? '+' : '') . '[' . $value['from']['value'] . ']';
            }
            $r_numvals[$key] = $the_value;*/
            break;
          //simple value with si
          case 'surface_living':
          case 'surface_property':
          case 'surface_usable':
          case 'volume':
          case 'ceiling_height':
          case 'hall_height':
          case 'maximal_floor_loading':
          case 'carrying_capacity_crane':
          case 'carrying_capacity_elevator':
            $the_value = '';
            foreach ($numval as $key2 => $value) {
              $the_value = $value['from']['value'] . $value['from']['si'];
            }
            $r_numvals[$key] = $the_value;
            break;
          //INT
          case 'floor':
          case 'year_built':
          case 'year_renovated':
            $the_value = '';
            foreach ($numval as $key2 => $value) {
              $the_value = round($value['from']['value']);
            }
            $r_numvals[$key] = $the_value;
            break;
          //float
          case 'number_of_rooms':
          case 'number_of_apartments':
          case 'number_of_floors':
            $the_value = '';
            foreach ($numval as $key2 => $value) {
              $the_value = $value['from']['value'];
            }
            $r_numvals[$key] = $the_value;
            break;
          default:
            break;
        }
      }
    }


    return array_merge($r_numvals, $r_distances);

  }


  public function featuresXMLtoJson($features){
    $the_features = array();
    if ($features && $features->feature) {
      $set_orders = array();
      foreach ($features->feature as $feature) {
        if ($feature['key']) {
          $key = $feature['key']->__toString();
          $value = $feature->__toString();
          if ($set_orders) {
            $next_key_available = max($set_orders) + 1;
          } else {
            $next_key_available = 0;
          }
          $order = ($feature['order'] && !in_array($feature['order']->__toString(), $set_orders) ? $feature['order']->__toString() : $next_key_available);
          $set_orders[] = $order;

          $the_features[$order] = array(
            'key' => $key,
            'value' => $value,
          );
        }
      }
    }
    if ($the_features) {
      ksort($the_features);
      $the_features_json = json_encode($the_features);
    } else {
      $the_features_json = '';
    }
    return $the_features_json;
  }


  /*
    KEYS: custom_category_{$i}
  */
  public function publisherOptionsXMLtoArray($publisher_options_xml){
    $publisher_options = array();
      foreach ($publisher_options_xml as $settings) {
        if ($settings['id'] == 'casasync') {
          if ($settings->options) {
            foreach ($settings->options->option as $option) {
              $key   = (isset($option['name']) ? $option['name'] : false);
              $value = $option->__toString();
              if ($key && $value) {
                if (strpos($key, 'custom_category_') === 0) {
                  $parts = explode('_', $key);
                  $sort = (isset($parts[2]) && is_numeric($parts[2]) ? $parts[2] : false);
                  $slug = (isset($parts[3]) && $parts[3] == 'slug' ? true : false);
                  $label = (isset($parts[3]) && $parts[3] == 'label' ? true : false);
                  if ($slug) {
                    $publisher_options['custom_categories'][$sort]['slug'] = $value;
                  } elseif ($label) {
                    $publisher_options['custom_categories'][$label]['label'] = $value;
                  }
                }
              }
            }
          }
        }
      }

    return $publisher_options;
  }

  public function setOfferAttachments($xmlattachments, $wp_post, $property_id, $casasync_id){
    //get xml media files
    $the_casasync_attachments = array();
    if ($xmlattachments) {
      foreach ($xmlattachments->media as $media) {
        if (in_array($media['type']->__toString(), array('image', 'document', 'plan'))) {
          $filename = ($media->file->__toString() ? $media->file->__toString() : $media->url->__toString());
          $the_casasync_attachments[] = array(
            'type'    => $media['type']->__toString(),
            'alt'     => $media->alt->__toString(),
            'title'   => preg_replace('/\.[^.]+$/', '', ( $media->title->__toString() ? $media->title->__toString() : basename($filename)) ),
            'file'    => $media->file->__toString(),
            'url'     => $media->url->__toString(),
            'caption' => $media->caption->__toString(),
            'order'   => $media['order']->__toString()
          );
        }
      }
    }

    //get post attachments already attached
    $wp_casasync_attachments = array();
    $args = array(
      'post_type'   => 'attachment',
      'numberposts' => -1,
      'post_status' => null,
      'post_parent' => $wp_post->ID,
      'tax_query'   => array(
        'relation'  => 'AND',
        array(
          'taxonomy' => 'casasync_attachment_type',
          'field'    => 'slug',
          'terms'    => array( 'image', 'plan', 'document' )
        )
      )
    );
    $attachments = get_posts($args);
    if ($attachments) {
      foreach ($attachments as $attachment) {
        $wp_casasync_attachments[] = $attachment;
      }
    }

    //upload necesary images to wordpress
    if (isset($the_casasync_attachments)) {
      $wp_casasync_attachments_to_remove = $wp_casasync_attachments;
      foreach ($the_casasync_attachments as $the_mediaitem) {
        //look up wp and see if file is already attached
        $existing = false;
        $existing_attachment = array();
        foreach ($wp_casasync_attachments as $key => $wp_mediaitem) {
          $attachment_customfields = get_post_custom($wp_mediaitem->ID);
          $original_filename = (array_key_exists('_origin', $attachment_customfields) ? $attachment_customfields['_origin'][0] : '');
          $alt = '';
          if ($original_filename == ($the_mediaitem['file'] ? $the_mediaitem['file'] : $the_mediaitem['url'])) {
            $existing = true;

            //its here to stay
            unset($wp_casasync_attachments_to_remove[$key]);

            $types = wp_get_post_terms( $wp_mediaitem->ID, 'casasync_attachment_type');
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
              $this->transcript[$casasync_id]['attachments']["updated"] = 1;
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
                $term = get_term_by('slug', $the_mediaitem['type'], 'casasync_attachment_type');
                $term_id = $term->term_id;
                wp_set_post_terms( $wp_mediaitem->ID,  array($term_id), 'casasync_attachment_type' );
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
          $new_id = $this->casasyncUploadAttachment($the_mediaitem, $wp_post->ID, $property_id);
          if (is_int($new_id)) {
            $this->transcript[$casasync_id]['attachments']["created"] = $the_mediaitem['file'];
          } else {
            $this->transcript[$casasync_id]['attachments']["failed_to_create"] = $new_id;
          }
        }
        

      } //foreach ($the_casasync_attachments as $the_mediaitem) {

      //featured image
      $attachment_image_order = array();
      foreach ($the_casasync_attachments as $the_mediaitem) {
        if ($the_mediaitem['type'] == 'image') {
          $attachment_image_order[$the_mediaitem['order']] = $the_mediaitem;
        }
      }
      if (isset($attachment_image_order) && !empty($attachment_image_order)) {
        ksort($attachment_image_order);
        $attachment_image_order = reset($attachment_image_order);
        if (!empty($attachment_image_order)) {
          foreach ($wp_casasync_attachments as $wp_mediaitem) {
            $attachment_customfields = get_post_custom($wp_mediaitem->ID);
            $original_filename = (array_key_exists('_origin', $attachment_customfields) ? $attachment_customfields['_origin'][0] : '');
            if ($original_filename == ($attachment_image_order['file'] ? $attachment_image_order['file'] : $attachment_image_order['url'])) {
              $cur_thumbnail_id = get_post_thumbnail_id( $wp_post->ID );
              if ($cur_thumbnail_id != $wp_mediaitem->ID) {
                set_post_thumbnail( $wp_post->ID, $wp_mediaitem->ID );
                $this->transcript[$casasync_id]['attachments']["featured_image_set"] = 1;
              }
            }
          }
        }
      }

      //images to remove
      foreach ($wp_casasync_attachments_to_remove as $attachment) {
        $this->transcript[$casasync_id]['attachments']["removed"] = $attachment;

        $attachment_customfields = get_post_custom($attachment->ID);
        $original_filename = (array_key_exists('_origin', $attachment_customfields) ? $attachment_customfields['_origin'][0] : '');
        wp_delete_attachment( $attachment->ID );
      }


    } //(isset($the_casasync_attachments)

   
  }

  public function setOfferSalestype($wp_post, $salestype, $casasync_id){
    $new_salestype = null;
    $old_salestype = null;

    if ($salestype) {
      $new_salestype = get_term_by('slug', $salestype, 'casasync_salestype', OBJECT, 'raw' );
      if (!$new_salestype) {
        $options = array(
          'description' => '',
          'slug' => $salestype
        );
        $id = wp_insert_term(
          $salestype,
          'casasync_salestype',
          $options
        );
        $new_salestype = get_term($id, 'casasync_salestype', OBJECT, 'raw');

      }
    }

    $wp_salestype_terms = wp_get_object_terms($wp_post->ID, 'casasync_salestype');
    if ($wp_salestype_terms) {
      $old_salestype = $wp_salestype_terms[0];
    }
    
    if ($old_salestype != $new_salestype) {
      $this->transcript[$casasync_id]['salestype']['from'] = ($old_salestype ? $old_salestype->name : 'none');
      $this->transcript[$casasync_id]['salestype']['to'] =   ($new_salestype ? $new_salestype->name : 'none');
      wp_set_object_terms( $wp_post->ID, ($new_salestype ? $new_salestype->term_id : NULL), 'casasync_salestype' );
    }
    
  }

  public function setOfferLocalities($country, $region, $locality, $wp_post, $casasync_id){
    //set post locations
    $lvl1_country     = ($country ? $country : 'CH' );
    $lvl1_country_id  = false;
    $lvl2_region      = $region;
    $lvl2_region_id   = false;
    $lvl3_locality    = $locality;
    $lvl3_locality_id = false;

    //set country
    $country_term = get_term_by('name', $lvl1_country, 'casasync_location');
    if ($country_term) {
      $lvl1_country_id = $country_term->term_id;
    }
    if (!$lvl1_country_id) {
      if (!isset($new_location[$lvl1_country])) {
        $new_location[$lvl1_country] = array('properties' => array($wp_post->ID));
      } else {
        $new_location[$lvl1_country]['properties'][] = $wp_post->ID;
      }
    }

    //set region
    if ($lvl2_region) {

      $region_term = get_term_by('name', $lvl2_region, 'casasync_location');
      if ($region_term) {
         $lvl2_region_id = $region_term->term_id;
      }
      if (!$lvl2_region_id) {
        if ($lvl1_country) {
          if (!isset($new_location[$lvl1_country])) {
            $new_location[$lvl1_country][$lvl2_region] = array('properties' => array($wp_post->ID));
          } else {
            $new_location[$lvl1_country][$lvl2_region]['properties'][] = $wp_post->ID;
          }
        } else {
          if (!isset($new_location[$lvl2_region])) {
            $new_location[$lvl2_region] = array('properties' => array($wp_post->ID));
          } else {
            $new_location[$lvl2_region]['properties'][] = $wp_post->ID;
          }
        }
      }
    }

    //set city
    if ($lvl3_locality) {
      $locality_term = get_term_by('name', $lvl3_locality, 'casasync_location');
      if ($locality_term) {
        $lvl3_locality_id = $locality_term->term_id;
      }
      if (!$lvl3_locality_id) {
        if ($lvl1_country && $lvl2_region) {
          if (!isset($new_location[$lvl1_country][$lvl2_region])) {
            $new_location[$lvl1_country][$lvl2_region][$lvl3_locality] = array('properties' => array($wp_post->ID));
          } else {
            $new_location[$lvl1_country][$lvl2_region][$lvl3_locality]['properties'][] = $wp_post->ID;
          }
        } elseif ($lvl2_region) {
          if (!isset($new_location[$lvl2_region])) {
            $new_location[$lvl2_region][$lvl3_locality] = array('properties' => array($wp_post->ID));
          } else {
            $new_location[$lvl2_region][$lvl3_locality]['properties'][] = $wp_post->ID;
          }
        } elseif ($lvl1_country){
          if (!isset($new_location[$lvl1_country])) {
            $new_location[$lvl1_country][$lvl3_locality] = array('properties' => array($wp_post->ID));
          } else {
            $new_location[$lvl1_country][$lvl3_locality]['properties'][] = $wp_post->ID;
          }
        }
      }
    }

    $terms_to_add_real = array();
    if ($lvl1_country_id) {
      $terms_to_add_real[] = $lvl1_country_id;
    }
    if ($lvl2_region_id) {
      $terms_to_add_real[] = $lvl2_region_id;
    }
    if ($lvl3_locality_id) {
      $terms_to_add_real[] = $lvl3_locality_id;
    }

    wp_set_post_terms( $wp_post->ID, $terms_to_add_real, 'casasync_location' );
    delete_option("casasync_location_children");
    wp_cache_flush();
  }

  public function addNewLocalities(){
    //set new locations
      if (!empty($new_location)) {
        foreach ($new_location as $lvl1 => $lvl1_value) {
          $lvl1_id = false;
          $lvl2_id = false;
          $lvl3_id = false;
          $term = get_term_by('name', $lvl1, 'casasync_location');
          if ($term) {
            $lvl1_id = $term->term_id;
          } else {
            $lvl1_id = wp_insert_term( $lvl1, 'casasync_location');
            if (!is_wp_error($lvl1_id)) {
              $lvl1_id = $lvl1_id['term_id'];
            } else {
              $lvl1_id = false;
            }
            delete_option("casasync_location_children"); // clear the cache
          }
          if ($lvl1_id) {
            if (isset($lvl1_value['properties'])) {
              foreach ($lvl1_value['properties'] as $property_id) {
                wp_set_post_terms( $property_id, $lvl1_id, 'casasync_location', true );
              }
            }
            foreach ($lvl1_value as $lvl2 => $lvl2_value) {
              if ($lvl2 != 'properties') {
                $term = get_term_by('name', $lvl2, 'casasync_location');
                if ($term) {
                  $lvl2_id = $term->term_id;
                } else {
                  $lvl2_id = wp_insert_term( $lvl2, 'casasync_location', $args = array('parent' => (int)$lvl1_id));
                  if (!$lvl2_id instanceof WP_Error) {
                    $lvl2_id = $lvl2_id['term_id'];
                  } else {
                    $lvl2_id = false;
                  }
                  delete_option("casasync_location_children"); // clear the cache
                }
                if ($lvl2_id) {
                  if (isset($lvl2_value['properties'])) {
                    foreach ($lvl2_value['properties'] as $property_id) {
                      wp_set_post_terms( $property_id, $lvl2_id, 'casasync_location', true );
                    }
                  }
                  foreach ($lvl2_value as $lvl3 => $lvl3_value) {
                    if ($lvl3 != 'properties') {
                      $term = get_term_by('name', $lvl3, 'casasync_location');
                      if ($term) {
                        $lvl3_id = $term->term_id;
                      } else {
                        $lvl3_id = wp_insert_term( $lvl3, 'casasync_location', $args = array('parent' => (int)$lvl2_id));
                        if (!$lvl3_id instanceof WP_Error) {
                          $lvl3_id = $lvl3_id['term_id'];
                        } else {
                          $lvl3_id = false;
                        }
                        delete_option("casasync_location_children"); // clear the cache
                      }
                      if ($lvl3_id) {
                        if (isset($lvl3_value['properties'])) {
                          foreach ($lvl3_value['properties'] as $property_id) {
                            wp_set_post_terms( $property_id, $lvl3_id, 'casasync_location', true );
                          }
                        }
                      }
                    }
                  }
                }
              }
            }
          }
        }
      }
  }

  public function setOfferCategories($wp_post, $categories, $publisher_options, $casasync_id){
    $new_categories = array();;
    $old_categories = array();

    //set post category
    $old_categories = array();
    $wp_category_terms = wp_get_object_terms($wp_post->ID, 'casasync_category');
    foreach ($wp_category_terms as $term) {
      $old_categories[] = $term->slug;
    }

    //supported
    if ($categories) {
      foreach ($categories as $category) {
        $new_categories[] = $category->__toString();
      }
    }
    //custom
    if (isset($publisher_options['custom_categories'])) {
      $custom_categories = $publisher_options['custom_categories'];
      sort($custom_categories);
      foreach ($custom_categories as $custom_category) {
        $new_categories[] = 'custom_' . $custom_category['slug'];
      }
    }

    //have categories changed?
    if (array_diff($new_categories, $old_categories)) {
      $slugs_to_remove = array_diff($old_categories, $new_categories);
      $slugs_to_add    = array_diff($new_categories, $old_categories);
      $this->transcript[$casasync_id]['categories_changed']['removed_category'] = $slugs_to_remove;
      $this->transcript[$casasync_id]['categories_changed']['added_category'] = $slugs_to_add;

      //get the custom labels they need them
      $custom_categorylabels = array();
      if (isset($publisher_options['custom_categories'])) {
        foreach ($publisher_options['custom_categories'] as $custom) {
          $custom_categorylabels[$custom['slug']] = $custom['label'];
        }
      }

      //make sure the categories exist first
      foreach ($slugs_to_add as $new_term_slug) {
        $label = (array_key_exists($new_term_slug, $custom_categorylabels) ? $custom_categorylabels[$new_term_slug] : false);
        $this->setCasasyncCategoryTerm($new_term_slug, $label);
      }

      //add the new ones
      $category_terms = get_terms( array('casasync_category'), array('hide_empty' => false));
      foreach ($category_terms as $term) {
        if (in_array($term->slug, $new_categories)) {
          $connect_term_ids[] = (int) $term->term_id;
        }
      }
      if ($connect_term_ids) {
        wp_set_object_terms( $wp_post->ID, $connect_term_ids, 'casasync_category' );
      }
    }

  }

  public function casasyncImport(){
    if ($this->getImportFile()) {
      $this->renameImportFileTo(CASASYNC_CUR_UPLOAD_BASEDIR  . '/casasync/import/data-done.xml');
      $this->updateOffers();
      if (is_admin()) {
        echo '<div id="message" class="updated"><p>Casasync <strong>updated</strong>.</p><pre>' . print_r($this->transcript, true) . '</pre></div>';
      }
    }
  }


  public function updateOffers(){
    global $wpdb;
    $found_posts = array();

    $xml = simplexml_load_file($this->getImportFile(), 'SimpleXMLElement', LIBXML_NOCDATA);
    foreach ($xml->property as $property) {
      //make main language first and single out if not multilingual
      $xmloffers = array();
      $i = 0;
      foreach ($property->offer as $offer) {
        $i++;
        if ($offer['lang'] == $this->getMainLang()) {
          $xmloffers[0] = $offer;
        } else {
          if ($this->hasWPML()) {
            $xmloffers[$i] = $offer;
          }
        }
      }
      $offer_pos = 0;
      $first_offer_trid = false;
      foreach ($xmloffers as $xmloffer) {
        $offer_pos++;


        //is it already in db
        $casasync_id = $property['id'] . $xmloffer['lang'];

        $the_query = new \WP_Query( 'post_type=casasync_property&suppress_filters=true&meta_key=casasync_id&meta_value=' . $casasync_id );
        $wp_post = false;
        while ( $the_query->have_posts() ) :
          $the_query->the_post();
          global $post;
          $wp_post = $post;
        endwhile;
        wp_reset_postdata();

        //if not create a basic property
        if (!$wp_post) {
          $this->transcript[$casasync_id]['action'] = 'new';
          $the_post['post_title'] = 'unsaved property';
          $the_post['post_content'] = 'unsaved property';
          $the_post['post_status'] = 'pending';
          $the_post['post_type'] = 'casasync_property';
          $insert_id = wp_insert_post($the_post);
          update_post_meta($insert_id, 'casasync_id', $casasync_id);
          $wp_post = get_post($insert_id, OBJECT, 'raw');
        }
        $found_posts[] = $wp_post->ID;
        $this->updateOffer($casasync_id, $offer_pos, $property, $xmloffer, $wp_post);

      }
    }

    //3. remove all the unused properties
    $properties_to_remove = get_posts(  array(
      'numberposts' =>  100,
      'exclude'     =>  $found_posts,
      'post_type'   =>  'casasync_property',
      'post_status' =>  'publish'
      )
    );
    foreach ($properties_to_remove as $prop_to_rm) {
      //remove the attachments
      $attachments = get_posts( array(
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

    flush_rewrite_rules();
  }

  public function simpleXMLget($node){
    if ($node) {
      return $node->__toString();
    } else {
      return '';
    }
  }


  public function updateOffer($casasync_id, $offer_pos, $property, $xmloffer, $wp_post){
    $publisher_options = $this->publisherOptionsXMLtoArray($xmloffer->publisherSettings);

    //lang
    $this->updateInsertWPMLconnection($offer_pos, $wp_post, $xmloffer['lang'], $casasync_id);

    /* main post data */
    $new_main_data = array(
      'ID'            => $wp_post->ID,
      'post_title'    => $xmloffer->name->__toString(),
      'post_content'  => $this->extractDescription($xmloffer),
      'post_status'   => 'publish',
      'post_type'     => 'casasync_property',
      'post_excerpt'  => $xmloffer->excerpt->__toString(),
      'post_date'     => date('Y-m-d H:i:s', strtotime(($property->software->creation->__toString() ? $property->software->creation->__toString() : $property->software->lastUpdate->__toString() ) )),
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
          $this->transcript[$casasync_id]['main_data'][$key]['from'] = $old_main_data[$key];
          $this->transcript[$casasync_id]['main_data'][$key]['to'] = $new_main_data[$key];
        }
      }
      //persist change
      wp_insert_post($new_main_data);
    }


    /* Post Metas */
    $old_meta_data = array();
    $meta_values = get_post_meta($wp_post->ID, null, true);
    foreach ($meta_values as $key => $meta_value) {
      $old_meta_data[$key] = $meta_value[0];
    }

    $new_meta_data = array();
    $casasync_visitInformation = $property->visitInformation->__toString();
    $casasync_property_url = $property->url->__toString();
    $new_meta_data['casasync_property_address_country']       = $this->simpleXMLget($property->address->country);
    $new_meta_data['casasync_property_address_locality']      = $this->simpleXMLget($property->address->locality);
    $new_meta_data['casasync_property_address_region']        = $this->simpleXMLget($property->address->region);
    $new_meta_data['casasync_property_address_postalcode']    = $this->simpleXMLget($property->address->postalCode);
    $new_meta_data['casasync_property_address_streetaddress'] = $this->simpleXMLget($property->address->street);
    $new_meta_data['casasync_property_address_streetnumber']  = $this->simpleXMLget($property->address->streetNumber);
    if ($property->geo) {
      $new_meta_data['casasync_property_geo_latitude']          = (int) $this->simpleXMLget($property->geo->latitude);
      $new_meta_data['casasync_property_geo_longitude']         = (int) $this->simpleXMLget($property->geo->longitude);
    }
    $new_meta_data['casasync_start']                          = $this->simpleXMLget($xmloffer->start);
    $new_meta_data['casasync_referenceId']                    = $this->simpleXMLget($property->referenceId);
    if ($xmloffer->seller && $xmloffer->seller->organization && $xmloffer->seller->organization->address) {
      $new_meta_data['seller_org_address_country']               = $this->simpleXMLget($xmloffer->seller->organization->address->Country            );
      $new_meta_data['seller_org_address_locality']              = $this->simpleXMLget($xmloffer->seller->organization->address->locality           );
      $new_meta_data['seller_org_address_region']                = $this->simpleXMLget($xmloffer->seller->organization->address->region             );
      $new_meta_data['seller_org_address_postalcode']            = $this->simpleXMLget($xmloffer->seller->organization->address->postalCode         );
      $new_meta_data['seller_org_address_postofficeboxnumber']   = $this->simpleXMLget($xmloffer->seller->organization->address->postOfficeBoxNumber);
      $new_meta_data['seller_org_address_streetaddress']         = $this->simpleXMLget($xmloffer->seller->organization->address->street             );
    }


    //urls
    $the_urls = array();
    if ($xmloffer->url) {
      foreach ($xmloffer->url as $url) {
        $href = $url->__toString();
        $label = (isset($url['label']) && $url['label'] ? $url['label'] : false);
        $title = (isset($url['title']) && $url['title'] ? $url['title'] : false);
        $rank =  (isset($url['rank'])  && (int) $url['rank'] ? (int) $url['rank'] : false);
        if ($rank ) {
          $the_urls[$rank] = array(
            'href' => $href,
            'label' => ($label ? $label : $href),
            'title' => ($title ? $title : $href)
          );
        } else {
          $the_urls[] = array(
            'href' => $href,
            'label' => ($label ? $label : $href),
            'title' => ($title ? $title : $href)
          );
        }
      }
      ksort($the_urls);
      $new_meta_data['the_urls'] = $the_urls;
    }

    $offer_type     = $this->simpleXMLget($xmloffer->type);
    $new_meta_data['price_currency'] = $this->simpleXMLget($xmloffer->priceCurrency);

    if ($xmloffer->availability) {
      $new_meta_data['availability'] = $this->simpleXMLget($xmloffer->availability);
      if ($xmloffer->availability['title']) {
        $new_meta_data['availability_label'] = $this->simpleXMLget($xmloffer->availability['title']);
      }
    }

    //prices 
    // is_object($new_meta_data['price_timesegment']) should be fixed!!!!!!!!!!!!
    if ($xmloffer->price) {
      $new_meta_data['price_timesegment'] = $xmloffer->price['timesegment'];
      if (!in_array($new_meta_data['price_timesegment'], array('m','w','d','y','h','infinite')) || is_object($new_meta_data['price_timesegment'])) {
        $new_meta_data['price_timesegment'] = ($offer_type == 'rent' ? 'm' : 'infinite');
      }
      $new_meta_data['price_propertysegment'] = $xmloffer->price['propertysegment'];
      if (!in_array($new_meta_data['price_propertysegment'], array('m2','km2','full')) || is_object($new_meta_data['price_propertysegment'])) {
        $new_meta_data['price_propertysegment'] = 'full';
      }
      $new_meta_data['price'] = (float) $xmloffer->price->__toString();
    }

    if ($xmloffer->netPrice) {
      $new_meta_data['netPrice_timesegment'] = $xmloffer->netPrice['timesegment'];
      if (!in_array($new_meta_data['netPrice_timesegment'], array('m','w','d','y','h','infinite')) || is_object($new_meta_data['netPrice_timesegment'])) {
        $new_meta_data['netPrice_timesegment'] = ($offer_type == 'rent' ? 'm' : 'infinite');
      }
      $new_meta_data['netPrice_propertysegment'] = $xmloffer->netPrice['propertysegment'];
      if (!in_array($new_meta_data['netPrice_propertysegment'], array('m2','km2','full')) || is_object($new_meta_data['netPrice_propertysegment'])) {
        $new_meta_data['netPrice_propertysegment'] = 'full';
      }
      $new_meta_data['netPrice'] = (float) $xmloffer->netPrice->__toString();
    }

    if ($xmloffer->grossPrice) {
      $new_meta_data['grossPrice_timesegment'] = $xmloffer->grossPrice['timesegment'];
      if (!in_array($new_meta_data['grossPrice_timesegment'], array('m','w','d','y','h','infinite')) || is_object($new_meta_data['grossPrice_timesegment'])) {
        $new_meta_data['grossPrice_timesegment'] = ($offer_type == 'rent' ? 'm' : 'infinite');
      }
      $new_meta_data['grossPrice_propertysegment'] = $xmloffer->grossPrice['propertysegment'];
      if (!in_array($new_meta_data['grossPrice_propertysegment'], array('m2','km2','full')) || is_object($new_meta_data['grossPrice_propertysegment'])) {
        $new_meta_data['grossPrice_propertysegment'] = 'full';
      }
      $new_meta_data['grossPrice'] = (float) $xmloffer->grossPrice->__toString();
    }

    //extraCosts
    // $extraPrice = array();
    // if($xmloffer->extraCost){
    //   foreach ($xmloffer->extraCost as $extraCost) {
    //     $timesegment     = '';
    //     $propertysegment = '';
    //     $timesegment     = $extraCost['timesegment'];

    //     if (!in_array($timesegment, array('m','w','d','y','h','infinite'))) {
    //       $timesegment = ($offer_type == 'rent' ? 'm' : 'infinite');
    //     }
    //     $propertysegment = $extraCost['propertysegment'];
    //     if (!in_array($propertysegment, array('m2','km2','full'))) {
    //       $propertysegment = 'full';
    //     }
    //     $the_extraPrice = (float) $extraCost->__toString();

    //     $timesegment_labels = array(
    //       'm' => __('month', 'casasync'),
    //       'w' => __('week', 'casasync'),
    //       'd' => __('day', 'casasync'),
    //       'y' => __('year', 'casasync'),
    //       'h' => __('hour', 'casasync')
    //     );
    //     $extraPrice[] = array(
    //       'value' =>
    //         (isset($new_meta_data['price_currency']) && $new_meta_data['price_currency'] ? $new_meta_data['price_currency'] . ' ' : '') .
    //         number_format(round($the_extraPrice), 0, '', '\'') . '.&#8211;' .
    //         ($propertysegment != 'full' ? ' / ' . substr($propertysegment, 0, -1) . '<sup>2</sup>' : '') .
    //         ($timesegment != 'infinite' ? ' / ' . $timesegment_labels[(string) $timesegment] : '')
    //       ,
    //       'title' => (string) $extraCost['title']
    //     );
    //   }
    //   $new_meta_data['extraPrice'] = serialize($extraPrice);
    // }


    $extraPrice = array();
    if($xmloffer->extraCost){
      foreach ($xmloffer->extraCost as $extraCost) {
        $timesegment     = '';
        $propertysegment = '';
        $timesegment     = $extraCost['timesegment'];

        if (!in_array($timesegment, array('m','w','d','y','h','infinite'))) {
          $timesegment = ($offer_type == 'rent' ? 'm' : 'infinite');
        }
        $propertysegment = $extraCost['propertysegment'];
        if (!in_array($propertysegment, array('m2','km2','full'))) {
          $propertysegment = 'full';
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
    }


    //persons
    $persons = $this->personsXMLtoArray($xmloffer->seller);
    $new_meta_data = array_merge($new_meta_data, $persons);

    //nuvals
    $numericValues = $this->numvalsXMLtoArray($property->numericValues);
    $new_meta_data = array_merge($new_meta_data, $numericValues);

    //features
    $new_meta_data['casasync_features'] = $this->featuresXMLtoJson($property->features);

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
        if (in_array($key, array('the_urls', 'extraPrice'))) {
          if (isset($new_meta_data[$key])) {
            $new_meta_data[$key] = $new_meta_data[$key];
          }
        }
        $newval = (isset($new_meta_data[$key]) ? $new_meta_data[$key] : '');
        $oldval = (isset($old_meta_data[$key]) ? maybe_unserialize($old_meta_data[$key]) : '');
        if (($oldval || $newval) && $oldval != $newval) {
          update_post_meta($wp_post->ID, $key, $newval);
          $this->transcript[$casasync_id]['meta_data'][$key]['from'] = $oldval;
          $this->transcript[$casasync_id]['meta_data'][$key]['to'] = $newval;
        }
      }

      //remove supurflous meta_data
      foreach ($old_meta_data as $key => $value) {
        if (!isset($new_meta_data[$key])) {
          //remove
          delete_post_meta($wp_post->ID, $key, $value);
          $this->transcript[$casasync_id]['meta_data'][$key] = 'removed';

        }
      }
    }

    $this->setOfferCategories($wp_post, $property->category, $publisher_options, $casasync_id);
    $this->setOfferSalestype($wp_post, $xmloffer->type->__toString(), $casasync_id);
    //$this->setOfferLocalities($country, $region, $locality, $wp_post, $casasync_id);
    $this->setOfferAttachments($xmloffer->attachments , $wp_post, $property['id']->__toString(), $casasync_id);

  }
}
