<?php
namespace CasaSync;

class Import {
  public $lastTranscript = '';

  public function __construct(){
    $this->conversion = new Conversion;
    add_action( 'init', array($this, 'casasyncImport') );
    //$this->casasyncImport();
  }

  public function getLastTranscript(){
    return $this->lastTranscript;
  }

  public function setCasasyncCategoryTerm($term_slug, $label = false) {
    $label = (!$label ? $term_slug : $label);
    $term = get_term_by('slug', $term_slug, 'casasync_category', ARRAY_A, 'raw' );
    //$existing_term_id = term_exists( $label, 'casasync_category');
    $existing_term_id = false;
    if ($term) {
      $existing_term_id = $term['term_id'];
    }
    if ($existing_term_id) {
      return $existing_term_id['term_id'];
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
    }
  }

  public function casasyncImport(){
    $good_to_go = false;
    $continue_later = false;
    $max_offerimport_auto = 3;
    $transcript = array();

    //WPML
    $main_lang = 'de';
    global $sitepress;
    global $wpdb;
    $WPML = false;
    if( $sitepress && is_object($sitepress) && method_exists($sitepress, 'get_language_details' )) {
        if (is_file( WP_PLUGIN_DIR . '/sitepress-multilingual-cms/inc/wpml-api.php' )) {
            require_once( WP_PLUGIN_DIR . '/sitepress-multilingual-cms/inc/wpml-api.php' );
        }
        if (function_exists("wpml_get_default_language")) {
          $main_lang = wpml_get_default_language();
          $WPML = true;
        }
    }
    //1. check if file exists
    if (!is_dir(CASASYNC_CUR_UPLOAD_BASEDIR . '/casasync/import')) {
      mkdir(CASASYNC_CUR_UPLOAD_BASEDIR . '/casasync/import');
    }
    $file = CASASYNC_CUR_UPLOAD_BASEDIR  . '/casasync/import/data.xml';
    if (file_exists($file)) {
      $good_to_go = true;
    } else {
      if (isset($_GET['force_last_import'])) {
        $file = CASASYNC_CUR_UPLOAD_BASEDIR  . '/casasync/import/data.xml.done';
        if (file_exists($file)) {
          $good_to_go = true;
        }
      }
    }
    if ($good_to_go == true) {
      //2. get file properties and update/insert them
      //A. Save it to processing dir
      if (!is_dir(CASASYNC_CUR_UPLOAD_BASEDIR . '/casasync/processing')) {
        mkdir(CASASYNC_CUR_UPLOAD_BASEDIR . '/casasync/processing');
      }
      $processing_file = CASASYNC_CUR_UPLOAD_BASEDIR . '/casasync/processing/' . date('Y_m_d_H_i_s') . '_processing.xml';
      copy($file, $processing_file);
      //B. rename the file so that it wont import again
      if (!isset($_GET['force_last_import'])) {
        rename ( $file , $file . '.done' );
      }

      //C. To be filled during process
      $found_properties = array();

      //D. read xml and save the property
      $xml = simplexml_load_file($processing_file, 'SimpleXMLElement', LIBXML_NOCDATA);
      //depricated!!! **********************************************************
      //update admin options

      if (get_option('casasync_feedback_update') == 1) {
        if ($xml->technicalFeedback) {
          if ($xml->technicalFeedback->givenName) {update_option("casasync_feedback_given_name", $xml->technicalFeedback->givenName->__toString() );}
          if ($xml->technicalFeedback->familyName) {update_option("casasync_feedback_family_name", $xml->technicalFeedback->familyName->__toString() );}
          if ($xml->technicalFeedback->email) {update_option("casasync_feedback_email", $xml->technicalFeedback->email->__toString() );}
          if ($xml->technicalFeedback->phone) {update_option("casasync_feedback_telephone", $xml->technicalFeedback->phone->__toString() );}
          if ($xml->technicalFeedback->gender) {update_option("casasync_feedback_gender", $xml->technicalFeedback->gender->__toString() );}
        }
      }
      //END depricated!!! *********************************

      $new_location = array();
      $newoffercount = 0;
      foreach ($xml->property as $property) {
        //WPML 1 (move main language to the top)
        $first_offer_trid = false;
        $i = 0;
        $offers = array();
        foreach ($property->offer as $offer) {
          $i++;
          if ($offer['lang'] == $main_lang) {
            $offers[0] = $offer;
          } else{
            $offers[$i] = $offer;
          }
        }
        foreach ($offers as $offer) {
          //requirenments
          if (!$property['id'] || !$property->provider || !$property->provider['id'] || !isset($offer)) {
            $transcript[] =   "required data missing!!!";
            continue;
          }
          if (isset($property->provider['id']) ) {
            $exporter = $property->provider['id'];
          } elseif(isset($property->provider->organization->legalName) && $property->provider->organization->legalName) {
            $exporter = urlencode(str_replace(' ', '-', strtolower($property->provider->organization->legalName)));
          } else {
            $exporter = 'cs';
          }

          //publisher settings
          $publisher_options = array();
          if ($offer->publisherSettings) {
            $publisher_options = $this->convertXmlPublisherOptions($offer->publisherSettings);
          }

          //defaults
          $the_post_custom = array();
          //try to fetch property from wordpress
          $wp_property = false;
          $wp_post_custom = false;

          $casasync_id = $property->provider['id'] . '_' . $property['id'] . $offer['lang'];
          $wp_category_terms = array(); //all
          $wp_category_terms_to_keep = array(); //non casasync in property
          $wp_casasync_category_terms = array(); //casasync in property
          $wp_casasync_category_terms_slugs = array(); //casasync slugs in property
          $wp_casasync_attachments = array();
          $the_casasync_attachments = array();
          $the_post_category_term_slugs = array();

          $the_query = new \WP_Query( 'post_type=casasync_property&suppress_filters=true&meta_key=casasync_id&meta_value=' . $casasync_id );
          while ( $the_query->have_posts() ) :
            $the_query->the_post();
            global $post;
            $wp_property = $post;
          endwhile;
          wp_reset_postdata();

          //get xml media files
          if ($offer->attachments) {
            foreach ($offer->attachments->media as $media) {
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

          $attachment_image_order = array();
          foreach ($the_casasync_attachments as $the_mediaitem) {
            if ($the_mediaitem['type'] == 'image') {
              $attachment_image_order[$the_mediaitem['order']] = $the_mediaitem;
            }
          }

          //check if data has changed
          $changed = false;
          if (isset($_GET['force_all_properties'])) {
            $changed = true;
          }

          if(!$wp_property){
            $newoffercount++;
            if (!isset($_GET['force_last_import'])) {
              if ($newoffercount > $max_offerimport_auto && 3 == 1) { //DOES NOT WORK
                $continue_later = true;
                //rename back to original for further importing at next request;
                rename ( $file . '.done' , CASASYNC_CUR_UPLOAD_BASEDIR  . '/casasync/import/data.xml');

                break 2; //break out of offer and property loop !!!
              }
            }
            $the_post_custom['casasync_id'] = $casasync_id;
            $changed = true;
            $transcript[$casasync_id][] =   "new";
          } else {
            //collect all the ids
            $found_properties[] = $wp_property->ID;
            //get post custom fields
            $wp_post_custom = get_post_custom( $wp_property->ID );
            //get post categoryterms
            $wp_category_terms = wp_get_object_terms($wp_property->ID, 'casasync_category');
            //get post attachments already attached
            $args = array(
              'post_type'   => 'attachment',
              'numberposts' => -1,
              'post_status' => null,
              'post_parent' => $wp_property->ID,
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
            $attachmentfilenames_in_xml = array();
            foreach ($the_casasync_attachments as $the_mediaitem) {
              //look up wp and see if file is already attached
              $existing = false;
              $existing_attachment = array();
              $attachmentfilenames_in_xml[] = ($the_mediaitem['file'] ? $the_mediaitem['file'] : $the_mediaitem['url']);
              foreach ($wp_casasync_attachments as $wp_mediaitem) {
                $attachment_customfields = get_post_custom($wp_mediaitem->ID);
                $original_filename = (array_key_exists('_origin', $attachment_customfields) ? $attachment_customfields['_origin'][0] : '');
                $alt = '';
                if ($original_filename == ($the_mediaitem['file'] ? $the_mediaitem['file'] : $the_mediaitem['url'])) {
                  $existing = true;
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
                    $transcript[$casasync_id][] =   "attachment_change";
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
                $new_id = $this->casasyncUploadAttachment($the_mediaitem, $wp_property->ID, $property['id']->__toString());
              }
            }

            //remove all extra atachments
            foreach ($wp_casasync_attachments as $wp_mediaitem2) {
              $attachment_customfields = get_post_custom($wp_mediaitem2->ID);
              $original_filename = (array_key_exists('_origin', $attachment_customfields) ? $attachment_customfields['_origin'][0] : '');
              if (!in_array($original_filename , $attachmentfilenames_in_xml)) {
                wp_delete_attachment( $wp_mediaitem2->ID );
              }
            }
          } // end if existing


          //build description
          $the_description = '';
          foreach ($offer->description as $description) {
            $the_description .= ($the_description ? '<hr class="property-separator" />' : '');
            if ($description['title']) {
              $the_description .= '<h2>' . $description['title']->__toString() . '</h2>';
            }
            $the_description .= $description->__toString();
          }
          $the_post['post_excerpt']  = $offer->excerpt->__toString();
          $the_post['post_content']  = $the_description;
          $the_post['post_title']    = $offer->name->__toString();
          $the_post['post_date']     = date('Y-m-d H:i:s', strtotime(($property->software->creation->__toString() ? $property->software->creation->__toString() : $property->software->lastUpdate->__toString() ) ));
          $the_post['post_modified'] = date('Y-m-d H:i:s', strtotime($property->software->lastUpdate->__toString()));


          $compare_r = array(
            'excerpt' => $offer->excerpt->__toString(),
            'description' => $the_description,
            'title' => $offer->name->__toString(),
          );
          $compare_l = array();
          if ($wp_property) {
            $compare_l = array(
              'excerpt' => $wp_property->post_excerpt,
              'description' => $wp_property->post_content,
              'title' => $wp_property->post_title,
            );
          }


          if($compare_l != $compare_r){
            $changed = true;
            foreach ($compare_l as $key => $value) {
              if ($compare_l[$key] != $compare_r[$key]) {
                $transcript[$casasync_id]['property_change'] = array('L' => $compare_l[$key], 'R' => $compare_r[$key]);
              }
            }
          }
          // set post custom fields
          $casasync_visitInformation = $property->visitInformation->__toString();
          $casasync_property_url = $property->url->__toString();
          $casasync_property_address_country       = ($property->address ? $property->address->country->__toString() : '');
          $casasync_property_address_locality      = ($property->address ? $property->address->locality->__toString() : '');
          $casasync_property_address_region        = ($property->address ? $property->address->region->__toString() : '');
          $casasync_property_address_postalcode    = ($property->address ? $property->address->postalCode->__toString() : '');
          $casasync_property_address_streetaddress = ($property->address ? $property->address->street->__toString() : '');
          $casasync_property_address_streetnumber  = ($property->address ? $property->address->streetNumber->__toString() : '');

          $casasync_property_geo_latitude  = (int) ($property->geo ? $property->geo->latitude->__toString() : '');
          $casasync_property_geo_longitude = (int) ($property->geo ? $property->geo->longitude->__toString() : '');

          $casasync_start       = ($offer->start ? $offer->start->__toString() : '');
          $casasync_referenceId = ($property->referenceId ? $property->referenceId->__toString() : '');

          $offer_type     = '';
          $price_currency = '';

          $price_timesegment     = '';
          $price_propertysegment = '';
          $price                 = 0;

          $grossPrice_timesegment     = '';
          $grossPrice_propertysegment = '';
          $grossPrice                 = 0;

          $netPrice_timesegment     = '';
          $netPrice_propertysegment = '';
          $netPrice                 = 0;

          $priceForOrder = 0;

          $availability       = '';
          $availability_label = '';

          $extraPrice = array();

          if ($offer) {
            //urls
            $the_urls = array();
            if ($offer->url) {
              foreach ($offer->url as $url) {
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
              $the_urls = array_values($the_urls);
            }
            //$the_urls = json_encode($the_urls);

            $offer_type = $offer->type->__toString();
            $price_currency = $offer->priceCurrency->__toString();
            if (!in_array($offer->priceCurrency, array('CHF', 'EUR', 'USD', 'GBP'))) {
              $price_currency = '';
            }


            if ($offer->availability) {
              $availability = $offer->availability->__toString();
              if ($offer->availability['title']) {
                $availability_label = $offer->availability['title']->__toString();
              }
            }


            if ($offer->price) {
              $price_timesegment = $offer->price['timesegment'];
              if (!in_array($price_timesegment, array('m','w','d','y','h','infinite'))) {
                $price_timesegment = ($offer_type == 'rent' ? 'm' : 'infinite');
              }
              $price_propertysegment = $offer->price['propertysegment'];
              if (!in_array($price_propertysegment, array('m2','km2','full'))) {
                $price_propertysegment = 'full';
              }
              $price = (float) $offer->price->__toString();
            }

            if ($offer->netPrice) {
              $netPrice_timesegment = $offer->netPrice['timesegment'];
              if (!in_array($netPrice_timesegment, array('m','w','d','y','h','infinite'))) {
                $netPrice_timesegment = ($offer_type == 'rent' ? 'm' : 'infinite');
              }
              $netPrice_propertysegment = $offer->netPrice['propertysegment'];
              if (!in_array($netPrice_propertysegment, array('m2','km2','full'))) {
                $netPrice_propertysegment = 'full';
              }
              $netPrice = (float) $offer->netPrice->__toString();
            }

            if ($offer->grossPrice) {
              $grossPrice_timesegment = $offer->grossPrice['timesegment'];
              if (!in_array($grossPrice_timesegment, array('m','w','d','y','h','infinite'))) {
                $grossPrice_timesegment = ($offer_type == 'rent' ? 'm' : 'infinite');
              }
              $grossPrice_propertysegment = $offer->grossPrice['propertysegment'];
              if (!in_array($grossPrice_propertysegment, array('m2','km2','full'))) {
                $grossPrice_propertysegment = 'full';
              }
              $grossPrice = (float) $offer->grossPrice->__toString();
            }

            $tmp_price      = $price;
            $tmp_grossPrice = $grossPrice;
            $tmp_netPrice   = $netPrice;
            $tmp_price = ($tmp_price === 0) ? (9999999999) : ($tmp_price);
            $tmp_grossPrice = ($tmp_grossPrice === 0) ? (9999999999) : ($tmp_grossPrice);
            $tmp_netPrice = ($tmp_netPrice === 0) ? (9999999999) : ($tmp_netPrice);

            $priceForOrder = str_pad($tmp_netPrice, 10, 0, STR_PAD_LEFT) . str_pad($tmp_grossPrice, 10, 0, STR_PAD_LEFT) . str_pad($tmp_price, 10, 0, STR_PAD_LEFT);

            if($offer->extraCost){
              foreach ($offer->extraCost as $extraCost) {
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

                $timesegment_labels = array(
                  'm' => __('month', 'casasync'),
                  'w' => __('week', 'casasync'),
                  'd' => __('day', 'casasync'),
                  'y' => __('year', 'casasync'),
                  'h' => __('hour', 'casasync')
                );
                $extraPrice[] = array(
                  'value' =>
                    ($price_currency ? $price_currency . ' ' : '') .
                    number_format(round($the_extraPrice), 0, '', '\'') . '.&#8211;' .
                    ($propertysegment != 'full' ? ' / ' . substr($propertysegment, 0, -1) . '<sup>2</sup>' : '') .
                    ($timesegment != 'infinite' ? ' / ' . $timesegment_labels[(string) $timesegment] : '')
                  ,
                  'title' => (string) $extraCost['title']
                );
              }
            }

          }

          $seller_org_address_country             = '';
          $seller_org_address_locality            = '';
          $seller_org_address_region              = '';
          $seller_org_address_postalcode          = '';
          $seller_org_address_postofficeboxnumber = '';
          $seller_org_address_streetaddress       = '';
          $seller_org_legalname                   = '';
          $seller_org_email                       = '';
          $seller_org_fax                         = '';
          $seller_org_phone_direct                = '';
          $seller_org_phone_central               = '';
          $seller_org_phone_mobile                = '';
          $seller_org_brand                       = '';
          $seller_person_function                 = '';
          $seller_person_givenname                = '';
          $seller_person_familyname               = '';
          $seller_person_email                    = '';
          $seller_person_fax                      = '';
          $seller_person_phone_direct             = '';
          $seller_person_phone_central            = '';
          $seller_person_phone_mobile             = '';
          $seller_person_phone_gender             = '';
          $seller_inquiry_person_function         = '';
          $seller_inquiry_person_givenname        = '';
          $seller_inquiry_person_familyname       = '';
          $seller_inquiry_person_email            = '';
          $seller_inquiry_person_fax              = '';
          $seller_inquiry_person_phone_direct     = '';
          $seller_inquiry_person_phone_central    = '';
          $seller_inquiry_person_phone_mobile     = '';
          $seller_inquiry_person_phone_gender     = '';

          if ($offer->seller && $offer->seller->organization) {
            if ($offer->seller->organization->address) {
              $seller_org_address_country             = $offer->seller->organization->address->Country->__toString();
              $seller_org_address_locality            = $offer->seller->organization->address->locality->__toString();
              $seller_org_address_region              = $offer->seller->organization->address->region->__toString();
              $seller_org_address_postalcode          = $offer->seller->organization->address->postalCode->__toString();
              $seller_org_address_postofficeboxnumber = $offer->seller->organization->address->postOfficeBoxNumber->__toString();
              $seller_org_address_streetaddress       = $offer->seller->organization->address->street->__toString();
            }
            $seller_org_legalname = $offer->seller->organization->legalName->__toString();
            $seller_org_email = $offer->seller->organization->email->__toString();
            $seller_org_fax = $offer->seller->organization->faxNumber->__toString();
            if ($offer->seller->organization->phone) {
              $central = false;
              foreach ($offer->seller->organization->phone as $phone) {
                if ($phone['type']) {
                  switch ($phone['type']->__toString()) {
                    case 'direct':
                      $seller_org_phone_direct = $phone->__toString();
                      break;
                    case 'central':
                      $seller_org_phone_central = $phone->__toString();
                      $central = true;
                      break;
                    case 'mobile':
                      $seller_org_phone_mobile = $phone->__toString();
                      break;
                    default:
                      if (!$central) {
                        $seller_org_phone_central = $phone->__toString();
                      }
                      break;
                  }
                } else {
                  if (!$central) {
                    $seller_org_phone_central = $phone->__toString();
                  }
                }
              }
            }
            $seller_org_brand = $offer->seller->organization->brand->__toString();
          }
          if ($offer->seller && $offer->seller->person) {
            $view_person_set = false;
            foreach ($offer->seller->person as $person) {
              if (!$view_person_set && (!$person['type'] || $person['type']->__toString() == 'view')) {
                $view_person_set = true;
                $seller_person_function = $person->function->__toString();
                $seller_person_givenname = $person->givenName->__toString();
                $seller_person_familyname = $person->familyName->__toString();
                $seller_person_email = $person->email->__toString();
                $seller_person_fax = $person->faxNumber->__toString();
                if ($person->phone) {
                  $central = false;
                  foreach ($person->phone as $phone) {
                    if ($phone['type']) {
                      switch ($phone['type']->__toString()) {
                        case 'direct':
                          $seller_person_phone_direct = $phone->__toString();
                          break;
                        case 'central':
                          $seller_person_phone_central = $phone->__toString();
                          $central = true;
                          break;
                        case 'mobile':
                          $seller_person_phone_mobile = $phone->__toString();
                          break;
                        default:
                          if (!$central) {
                            $seller_person_phone_central = $phone->__toString();
                          }
                          break;
                      }
                    } else {
                      if (!$central) {
                        $seller_person_phone_central = $phone->__toString();
                      }
                    }
                  }
                }
                $seller_person_phone_gender = $person->gender->__toString();
              } elseif ($person['type'] && $person['type']->__toString() == 'inquiry') {
                $seller_inquiry_person_function = $person->function->__toString();
                $seller_inquiry_person_givenname = $person->givenName->__toString();
                $seller_inquiry_person_familyname = $person->familyName->__toString();
                $seller_inquiry_person_email = $person->email->__toString();
                $seller_inquiry_person_fax = $person->faxNumber->__toString();
                if ($person->phone) {
                  $central = false;
                  foreach ($person->phone as $phone) {
                    if ($phone['type']) {
                      switch ($phone['type']->__toString()) {
                        case 'direct':
                          $seller_inquiry_person_phone_direct = $phone->__toString();
                          break;
                        case 'central':
                          $seller_inquiry_person_phone_central = $phone->__toString();
                          $central = true;
                          break;
                        case 'mobile':
                          $seller_inquiry_person_phone_mobile = $phone->__toString();
                           break;
                        default:
                          if (!$central) {
                            $seller_inquiry_person_phone_central = $phone->__toString();
                          }
                          break;
                      }
                    } else {
                      if (!$central) {
                        $seller_inquiry_person_phone_central = $phone->__toString();
                      }
                    }
                  }
                }
                $seller_inquiry_person_phone_gender = $person->gender->__toString();
              }
            }
          }


          $compare_l = array(
            'casasync_visitInformation'             => $casasync_visitInformation,
            'casasync_property_url'                 => $casasync_property_url,
            'casasync_property_address_country'     => $casasync_property_address_country,
            'casasync_property_address_locality'    => $casasync_property_address_locality,
            'casasync_property_address_region'      => $casasync_property_address_region,
            'casasync_property_address_postalcode'  => $casasync_property_address_postalcode,
            'casasync_property_address_streetaddress'=> $casasync_property_address_streetaddress,
            'casasync_property_address_streetnumber'=> $casasync_property_address_streetnumber,
            'casasync_property_geo_latitude'        => $casasync_property_geo_latitude,
            'casasync_property_geo_longitude'       => $casasync_property_geo_longitude,
            'the_urls'                              => $the_urls,
            'casasync_start'                        => $casasync_start,
            'casasync_referenceId'                  => $casasync_referenceId,
            'availability'                          => $availability,
            'availability_label'                    => $availability_label,
            'offer_type'                            => $offer_type,
            'price_currency'                        => $price_currency,
            'price_timesegment'                     => $price_timesegment,
            'price_propertysegment'                 => $price_propertysegment,
            'price'                                 => $price,
            'grossPrice_timesegment'                => $grossPrice_timesegment,
            'grossPrice_propertysegment'            => $grossPrice_propertysegment,
            'grossPrice'                            => $grossPrice,
            'netPrice_timesegment'                  => $netPrice_timesegment,
            'netPrice_propertysegment'              => $netPrice_propertysegment,
            'netPrice'                              => $netPrice,
            'priceForOrder'                         => $priceForOrder,
            'extraPrice'                            => $extraPrice,
            'seller_org_address_country'            => $seller_org_address_country,
            'seller_org_address_locality'           => $seller_org_address_locality,
            'seller_org_address_region'             => $seller_org_address_region,
            'seller_org_address_postalcode'         => $seller_org_address_postalcode,
            'seller_org_address_postofficeboxnumber'=> $seller_org_address_postofficeboxnumber,
            'seller_org_address_streetaddress'      => $seller_org_address_streetaddress,
            'seller_org_legalname'                  => $seller_org_legalname,
            'seller_org_email'                      => $seller_org_email,
            'seller_org_fax'                        => $seller_org_fax,
            'seller_org_phone_direct'               => $seller_org_phone_direct,
            'seller_org_phone_central'              => $seller_org_phone_central,
            'seller_org_phone_mobile'               => $seller_org_phone_mobile,
            'seller_org_brand'                      => $seller_org_brand,
            'seller_person_function'                => $seller_person_function,
            'seller_person_givenname'               => $seller_person_givenname,
            'seller_person_familyname'              => $seller_person_familyname,
            'seller_person_email'                   => $seller_person_email,
            'seller_person_fax'                     => $seller_person_fax,
            'seller_person_phone_direct'            => $seller_person_phone_direct,
            'seller_person_phone_central'           => $seller_person_phone_central,
            'seller_person_phone_mobile'            => $seller_person_phone_mobile,
            'seller_person_phone_gender'            => $seller_person_phone_gender,
            'seller_inquiry_person_function'        => $seller_inquiry_person_function,
            'seller_inquiry_person_givenname'       => $seller_inquiry_person_givenname,
            'seller_inquiry_person_familyname'      => $seller_inquiry_person_familyname,
            'seller_inquiry_person_email'           => $seller_inquiry_person_email,
            'seller_inquiry_person_fax'             => $seller_inquiry_person_fax,
            'seller_inquiry_person_phone_direct'    => $seller_inquiry_person_phone_direct,
            'seller_inquiry_person_phone_central'   => $seller_inquiry_person_phone_central,
            'seller_inquiry_person_phone_mobile'    => $seller_inquiry_person_phone_mobile,
            'seller_inquiry_person_phone_gender'    => $seller_inquiry_person_phone_gender,
          );

          $cpost_custom_key_transforms = array(
            'string' => array(
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
              'seller_person_phone_gender'                   ,
              'seller_inquiry_person_function'               ,
              'seller_inquiry_person_givenname'              ,
              'seller_inquiry_person_familyname'             ,
              'seller_inquiry_person_email'                  ,
              'seller_inquiry_person_fax'                    ,
              'seller_inquiry_person_phone_direct'           ,
              'seller_inquiry_person_phone_central'          ,
              'seller_inquiry_person_phone_mobile'           ,
              'seller_inquiry_person_phone_gender'           ,
            ),
            'float' => array(
              'casasync_property_geo_latitude'               ,
              'casasync_property_geo_longitude'              ,
              'price'                                        ,
              'grossPrice'                                   ,
              'netPrice'                                     ,
            ),
            'array' => array(
              'the_urls'                                     ,
              'extraPrice'                                   ,
              )
          );

          $compare_r = array();
          foreach ($cpost_custom_key_transforms as $transform => $keys) {
            foreach ($keys as $key) {
              switch ($transform) {
                case 'string':
                  $compare_r[$key] = (string) (isset($wp_post_custom[$key]) ? $wp_post_custom[$key][0] : '');
                  break;
                case 'float':
                  $compare_r[$key] = (float) (isset($wp_post_custom[$key]) ? $wp_post_custom[$key][0] : '');
                  break;
                case 'array':
                  $oldval =  (isset($wp_post_custom[$key]) ? unserialize($wp_post_custom[$key][0]) : array());
                  $compare_r[$key] = $oldval;
                  break;
              }
            }
          }


          ksort($compare_r);
          ksort($compare_l);

          //check if changed and set the values
          if (!$wp_property || $compare_l != $compare_r) {
              $changed = true;

              foreach ($compare_l as $key => $newvalue) {
                $oldvalue = (array_key_exists($key, $compare_r) ? $compare_r[$key] : '');
                if ($newvalue != $oldvalue) {
                  $transcript[$casasync_id]['offer_changed'][$key] = array('OLD' => $oldvalue, 'NEW' => $newvalue);
                }
                $the_post_custom[$key] = $newvalue;
              }
          }

          //set numericValues
          $the_numvals = array();
          if ($property->numericValues && $property->numericValues->value) {
            foreach ($property->numericValues->value as $numval) {
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
          $casasync_floors       = '';
          $casasync_living_space = '';
          $all_distance_keys     = $this->conversion->casasync_get_allDistanceKeys();
          $all_numval_keys       = $this->conversion->casasync_get_allNumvalKeys();
          $the_distances         = array();
          $xml_numval            = array();

          foreach ($the_numvals as $key => $numval) {
            if (in_array($key, $all_distance_keys)) {
              $the_value = '';
              foreach ($numval as $key2 => $value) {
                $the_value .= ($key2 != 0 ? '+' : '') . '[' . $value['from']['value'] . $value['from']['si'] . ']';
              }
              $the_distances[$key] = $the_value;
            }
            if (in_array($key, $all_numval_keys)) {
              switch ($key) {
                //multiple simple values
                case 'multiple':
                  /*$the_value = '';
                  foreach ($numval as $key2 => $value) {
                    $the_value .= ($key2 != 0 ? '+' : '') . '[' . $value['from']['value'] . ']';
                  }
                  $xml_numval[$key] = $the_value;*/
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
                  $xml_numval[$key] = $the_value;
                  break;
                //INT
                case 'floor':
                case 'year_built':
                case 'year_renovated':
                  $the_value = '';
                  foreach ($numval as $key2 => $value) {
                    $the_value = round($value['from']['value']);
                  }
                  $xml_numval[$key] = $the_value;
                  break;
                //float
                case 'number_of_rooms':
                case 'number_of_apartments':
                case 'number_of_floors':
                  $the_value = '';
                  foreach ($numval as $key2 => $value) {
                    $the_value = $value['from']['value'];
                  }
                  $xml_numval[$key] = $the_value;
                  break;
                default:
                  break;
              }
            }
          }
          foreach ($all_distance_keys as $distance_key) {
            if (!isset($the_distances[$distance_key])) {
              $the_distances[$distance_key] = '';
            }
            if ((string) $the_distances[$distance_key] != (string) (isset($wp_post_custom[$distance_key]) ? $wp_post_custom[$distance_key][0] : '') ) {
              $transcript[$casasync_id][] =   "distances_changed";
              $changed = true;
              $the_post_custom[$distance_key] = (string) $the_distances[$distance_key];
            }
          }
          foreach ($all_numval_keys as $numval_key) {
            if (!isset($xml_numval[$numval_key])) {
              $xml_numval[$numval_key] = '';
            }
            if ((string) $xml_numval[$numval_key] != (string) (isset($wp_post_custom[$numval_key]) ? $wp_post_custom[$numval_key][0] : '') ) {
              $transcript[$casasync_id][] =   "numvals_changed";
              $changed = true;
              $the_post_custom[$numval_key] = (string) $xml_numval[$numval_key];
            }
          }
          //set features
          $the_features = array();
          if ($property->features && $property->features->feature) {
            $set_orders = array();
            foreach ($property->features->feature as $feature) {
              //requirenments
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
          if (
              !$wp_property
              || (string) $the_features_json != (string) (isset($wp_post_custom['casasync_features']) ? $wp_post_custom['casasync_features'][0] : '')
          ) {
            $transcript[$casasync_id][] =   "features_changed";
            $changed = true;
            $the_post_custom['casasync_features'] = (string) $the_features_json;
          }

          //set post global data
          $the_post['post_type'] = 'casasync_property';
          $the_post['post_status'] =  'publish';
          $the_id = ($wp_property ? $wp_property->ID : false);
          $the_post['ID'] = $the_id;
          //$changed = true;

          if ($changed) {
            //insert post
            $insert_id = wp_insert_post( $the_post);

            //WPML (set language of foreign post language)
            if($WPML) {
              if ($first_offer_trid) {
                //$wpdb->delete($wpdb->prefix.'icl_translations', array( 'trid' => $first_offer_trid, 'language_code' => $offer['lang']->__toString()));
                $wpdb->update( $wpdb->prefix.'icl_translations', array( 'trid' => $first_offer_trid, 'language_code' => $offer['lang']->__toString(), 'source_language_code' => wpml_get_default_language() ), array( 'element_id' => $insert_id ) );
              }
            }

            $found_properties[] = $insert_id;
            if (!$the_id) {
              $the_id = $insert_id;
            }
            //set post custom fields
            foreach ($the_post_custom as $key => $value) {
              if ($value != "") {
                update_post_meta($the_id, $key, $value);
              } elseif($value == "") {
                delete_post_meta($the_id, $key, $value);
              }
            }

            //set post category
            $wp_category_terms_to_keep = array();
            $wp_casasync_category_terms_slugs = array();
            foreach ($wp_category_terms as $term) {
              $wp_casasync_category_terms_slugs[] = $term->slug;
            }
            $le_cats = array();
            //supported
            if ($property->category) {
              foreach ($property->category as $category) {
                $le_cats[] = $category->__toString();
              }
            }
            //custom
            if (isset($publisher_options['custom_categories'])) {
              $custom_categories = $publisher_options['custom_categories'];
              sort($custom_categories);
              foreach ($custom_categories as $custom_category) {
                $le_cats[] = $custom_category['slug'];
              }
            }
            $the_post_category_term_slugs = $le_cats;

            //have categories changed?
            if (array_diff($the_post_category_term_slugs, $wp_casasync_category_terms_slugs)) {
              $changed = true;
              $old = $wp_casasync_category_terms_slugs;
              $new = $the_post_category_term_slugs;
              $transcript[$casasync_id]['categories_changed']['removed_category'] = array_diff($old, $new);
              $transcript[$casasync_id]['categories_changed']['added_category'] = array_diff($new, $old);
            }

            //get the custom labels they need them
            $custom_categorylabels = array();
            if (isset($publisher_options['custom_categories'])) {
              foreach ($publisher_options['custom_categories'] as $custom) {
                $custom_categorylabels[$custom['slug']] = $custom['label'];
              }
            }

            //make sure the categories exist first
            $terms_to_add = array();
            foreach ($the_post_category_term_slugs as $new_term_slug) {
              $label = (array_key_exists($new_term_slug, $custom_categorylabels) ? $custom_categorylabels[$new_term_slug] : false);
              $newterm = $this->setCasasyncCategoryTerm($new_term_slug, $label);
              if ($newterm) {
                $terms_to_add[] = $newterm;
              }
            }

            //figure out which to add
            $category_terms = get_terms( array('casasync_category'), array('hide_empty'    => false));
            foreach ($category_terms as $term) {
              foreach ($the_post_category_term_slugs as $xml_slug) {
                if ( $term->slug == $xml_slug) {
                  $terms_to_add[] = $term->term_id;
                }
              }
            }

            //add THEM
            wp_set_post_terms( $the_id, array_merge($terms_to_add,$wp_category_terms_to_keep), 'casasync_category' );

            //set post locations
            $lvl1_country     = ($casasync_property_address_country ? $casasync_property_address_country : 'CH' );
            $lvl1_country_id  = false;
            $lvl2_region      = $casasync_property_address_region;
            $lvl2_region_id   = false;
            $lvl3_locality    = $casasync_property_address_locality;
            $lvl3_locality_id = false;

            //set country
            $country_term = get_term_by('name', $lvl1_country, 'casasync_location');
            if ($country_term) {
              $lvl1_country_id = $country_term->term_id;
            }
            if (!$lvl1_country_id) {
              if (!isset($new_location[$lvl1_country])) {
                $new_location[$lvl1_country] = array('properties' => array($the_id));
              } else {
                $new_location[$lvl1_country]['properties'][] = $the_id;
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
                    $new_location[$lvl1_country][$lvl2_region] = array('properties' => array($the_id));
                  } else {
                    $new_location[$lvl1_country][$lvl2_region]['properties'][] = $the_id;
                  }
                } else {
                  if (!isset($new_location[$lvl2_region])) {
                    $new_location[$lvl2_region] = array('properties' => array($the_id));
                  } else {
                    $new_location[$lvl2_region]['properties'][] = $the_id;
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
                    $new_location[$lvl1_country][$lvl2_region][$lvl3_locality] = array('properties' => array($the_id));
                  } else {
                    $new_location[$lvl1_country][$lvl2_region][$lvl3_locality]['properties'][] = $the_id;
                  }
                } elseif ($lvl2_region) {
                  if (!isset($new_location[$lvl2_region])) {
                    $new_location[$lvl2_region][$lvl3_locality] = array('properties' => array($the_id));
                  } else {
                    $new_location[$lvl2_region][$lvl3_locality]['properties'][] = $the_id;
                  }
                } elseif ($lvl1_country){
                  if (!isset($new_location[$lvl1_country])) {
                    $new_location[$lvl1_country][$lvl3_locality] = array('properties' => array($the_id));
                  } else {
                    $new_location[$lvl1_country][$lvl3_locality]['properties'][] = $the_id;
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

            wp_set_post_terms( $the_id, $terms_to_add_real, 'casasync_location' );
            delete_option("casasync_location_children");
            wp_cache_flush();

            //set basis
            wp_set_post_terms( $the_id, $offer_type, 'casasync_salestype' );


            //global $wpdb;
            //$wpdb->query("DELETE FROM wp_options WHERE option_name LIKE 'casasync_location_children'");


              //if new upload the new attachments
            if (!$wp_property) {
              foreach ($the_casasync_attachments as $the_mediaitem) {
                $new_id = $this->casasyncUploadAttachment($the_mediaitem, $the_id, $property['id']->__toString());
              }
              //(re)get post attachments already attached (again)
              $args = array(
                'post_type' => 'attachment',
                'numberposts' => -1,
                'post_status' => null,
                'post_parent' => $the_id,
                'tax_query' => array(
                  'relation' => 'AND',
                  array(
                    'taxonomy' => 'casasync_attachment_type',
                    'field' => 'slug',
                    'terms' => array( 'image', 'plan', 'document' )
                  )
                )
              );
              $attachments = get_posts($args);
              if ($attachments) {
                foreach ($attachments as $attachment) {
                  $wp_casasync_attachments[] = $attachment;
                }
              }
            }
            //set featured image
            if (isset($attachment_image_order) && !empty($attachment_image_order)) {
              ksort($attachment_image_order);
              $attachment_image_order = reset($attachment_image_order);
              if (!empty($attachment_image_order)) {
                foreach ($wp_casasync_attachments as $wp_mediaitem) {
                  $attachment_customfields = get_post_custom($wp_mediaitem->ID);
                  $original_filename = (array_key_exists('_origin', $attachment_customfields) ? $attachment_customfields['_origin'][0] : '');
                  if ($original_filename == ($attachment_image_order['file'] ? $attachment_image_order['file'] : $attachment_image_order['url'])) {
                    set_post_thumbnail( $the_id, $wp_mediaitem->ID );
                  }
                }
              }
            }
          }

          //WPML (get the main trid)
          if ($WPML && !$first_offer_trid) {
            $first_offer_trid = wpml_get_content_trid('post_' . $the_post['post_type'], $insert_id );
          }

        } //endoffer
      } //endproperty

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




      //3. remove all the unused properties
      $properties_to_remove = get_posts(  array(
        'numberposts' =>  100,
        'exclude'     =>  $found_properties,
        'post_type'   =>  'casasync_property',
        'post_status' =>  'publish'
        )
      );

      if (!$continue_later) {
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
        }
      }
      //4. finish off
      if (!is_dir(CASASYNC_CUR_UPLOAD_BASEDIR . '/casasync/done')) {
        mkdir(CASASYNC_CUR_UPLOAD_BASEDIR . '/casasync/done');
      }
      copy ( $processing_file , CASASYNC_CUR_UPLOAD_BASEDIR  . '/casasync/done/' . date('Y_m_d_H_i_s') . '_completed.xml');
      //rename ( $file , CASASYNC_CUR_UPLOAD_BASEDIR  . '/casasync/data_i_' . date('Y_m_d_H_i_s') . '.xml');
    }
    flush_rewrite_rules();
    $this->lastTranscript = $transcript;
    if ($transcript) {
      if (is_admin()) {
        echo "<textarea cols='100' rows='30' style='position:relative; z-index:10000; width:inherit; height:200px;'>";
        print_r($transcript);
        echo "</textarea>";
      }
    }

  }
}
