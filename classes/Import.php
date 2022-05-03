<?php
namespace casawp;

class Import {
  public $importFile = false;
  public $main_lang = false;
  public $WPML = null;
  public $transcript = array();
  public $curtrid = false;
  public $trid_store = array();

  public function __construct($doimport = true, $casagatewayupdate = false){
    if ($doimport) {
      add_action( 'init', array($this, 'casawpImport') );
    }
    if ($casagatewayupdate) {
      add_action( 'init', array($this, 'updateImportFileThroughCasaGateway') );
    }
  }

  public function getImportFile(){
    if (!$this->importFile) {
      $good_to_go = false;
      if (!is_dir(CASASYNC_CUR_UPLOAD_BASEDIR . '/casawp')) {
        mkdir(CASASYNC_CUR_UPLOAD_BASEDIR . '/casawp');
        $this->addToLog('directory casawp was missing: ' . time());
      }
      if (!is_dir(CASASYNC_CUR_UPLOAD_BASEDIR . '/casawp/import')) {
        mkdir(CASASYNC_CUR_UPLOAD_BASEDIR . '/casawp/import');
        $this->addToLog('directory casawp/import was missing: ' . time());
      }
      $file = CASASYNC_CUR_UPLOAD_BASEDIR  . '/casawp/import/data.xml';
      if (file_exists($file)) {
        $good_to_go = true;
        $this->addToLog('file found lets go: ' . time());
      } else {
        //if force last check for last
        $this->addToLog('file was missing ' . time());
        if (isset($_GET['force_last_import'])) {
          $this->addToLog('importing last file based on force_last_import: ' . time());
          $file = CASASYNC_CUR_UPLOAD_BASEDIR  . '/casawp/import/data-done.xml';
          if (file_exists($file)) {
            $good_to_go = true;
          }
        }
      }
      if ($good_to_go) {
        $this->importFile = $file;
      }
    } else {
        $this->addToLog('importfile already set: ' . time());
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
    copy ( $this->getImportFile() , CASASYNC_CUR_UPLOAD_BASEDIR  . '/casawp/done/' . get_date_from_gmt('', 'Y_m_d_H_i_s') . '_completed.xml');
    return true;
  }

  public function casawp_sanitize_title($result){
      $result = strtolower($result);
      $replacer = array(
          '&shy;' => '',
          ' ' => '-',
          'ä' => 'ae',
          'ö' => 'oe',
          'ü' => 'ue',
          'é' => 'e',
          'è' => 'e',
          'ê' => 'e',
          'à' => 'a',
          'ô' => 'o',
          'ò' => 'o',
          'û' => 'u',
          'â' => 'a',
          'ì' => 'i',
          'î' => 'i',
          'ï' => 'i',
          'æ' => 'ae',
          'œ' => 'oe',
          'ÿ' => 'y',
          'ù' => 'u',
          'û' => 'u',
          'ë' => 'e',
          'ç' => 'c',
          'ß' => 'ss',
          '/' => '-',
          ',' => '-'
      );

      foreach ($replacer as $key => $value) {
          $result = str_replace($key, $value, $result);
      }
      $result = preg_replace('/[^A-Za-z0-9\-]/', '', $result);
      return $result;
  }

  public function extractDescription($offer, $publisher_options = null){
    $descriptionDatas = $offer['descriptions'];

    //add custom_descriptions
    if ($publisher_options && isset($publisher_options['custom_descriptions']) && $publisher_options['custom_descriptions']) {
      if (is_array($publisher_options['custom_descriptions'])) {
        $json = $publisher_options['custom_descriptions'][0];
      } else {
        $json = $publisher_options['custom_descriptions'];
      }
      $custom_descriptions = json_decode($json, true);
      if ($custom_descriptions && is_array($custom_descriptions)) {
        foreach ($custom_descriptions as $custom_description_data) {
          if (isset($custom_description_data['html'])) {
            $newDescroptionData = array();
            $newDescroptionData['title'] = (isset($custom_description_data['title']) ? $custom_description_data['title'] : '');
            $newDescroptionData['text'] = $custom_description_data['html'];
            $descriptionDatas[] = $newDescroptionData;
          }

        }
      }
    }

    $the_description = '';
    foreach ($descriptionDatas as $description) {
      $the_description .= ($the_description ? '<hr class="property-separator" />' : '');
      if ($description['title']) {
        $the_description .= '<h2>' . $description['title'] . '</h2>';
      }
      $the_description .= $description['text'];
    }
    if ($the_description) {
      return $the_description;
    } else {
      return '';
    }

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

  public function setcasawpRegionTerm($term_slug, $label = false) {
    $label = (!$label ? $term_slug : $label);
    $term = get_term_by('slug', $term_slug, 'casawp_region', OBJECT, 'raw' );
    $existing_term_id = false;
    if ($term) {
      if (
        $term->slug != $term_slug
        || $term->name != $label
      ) {
        wp_update_term($term->term_id, 'casawp_region', array(
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
        'casawp_region',
        $options
      );
      $this->addToLog('inserting region ' . $label);
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

  public function setcasawpUtilityTerm($term_slug, $label = false) {
    $label = (!$label ? $term_slug : $label);
    $term = get_term_by('slug', $term_slug, 'casawp_utility', OBJECT, 'raw' );
    //$existing_term_id = term_exists( $label, 'casawp_utility');
    $existing_term_id = false;
    if ($term) {
      if (
        $term->slug != $term_slug
        || $term->name != $label
      ) {
        wp_update_term($term->term_id, 'casawp_utility', array(
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
        'casawp_utility',
        $options
      );
      return $id;
    }
  }

  public function casawpUploadAttachmentFromGateway($property_id, $fileurl){
    if (strpos($fileurl, '://')) {
      $parsed_url = parse_url(urldecode($fileurl));
    } else {
      $parsed_url = [];
    }
    if (isset($parsed_url['query']) && $parsed_url['query']) {
      $file_parts = pathinfo($parsed_url['path']);

      $scheme   = isset($parsed_url['scheme']) ? $parsed_url['scheme'] . '://' : '';
      $host     = isset($parsed_url['host']) ? $parsed_url['host'] : '';
      $port     = isset($parsed_url['port']) ? ':' . $parsed_url['port'] : '';
      $user     = isset($parsed_url['user']) ? $parsed_url['user'] : '';
      $pass     = isset($parsed_url['pass']) ? ':' . $parsed_url['pass']  : '';
      $pass     = ($user || $pass) ? "$pass@" : '';
      $path     = isset($parsed_url['path']) ? $parsed_url['path'] : '';

      $extension = $file_parts['extension'];
      $pathWithoutExtension = str_replace('.'.$file_parts['extension'], '', $path);

      $query    = isset($parsed_url['query']) ? '?' . $parsed_url['query'] : '';
      $fragment = isset($parsed_url['fragment']) ? '#' . $parsed_url['fragment'] : '';

      $converted = $scheme.$user.$pass.$host.$port.$pathWithoutExtension . str_replace(['?', '&', '#', '='], '-', $query.$fragment) . '.'.$extension;

      $filename = '/casawp/import/attachment/externalsync/' . $property_id . '/' . basename($converted);

    } else {
      $filename = '/casawp/import/attachment/externalsync/' . $property_id . '/' . basename($fileurl);
    }

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
        if (!isset($this->transcript['attachments'][$property_id]["uploaded_from_gateway"])) {
          $this->transcript['attachments'][$property_id]["uploaded_from_gateway"] = array();
        }
        $this->transcript['attachments'][$property_id]["uploaded_from_gateway"][] = $filename;

        if (strpos($fileurl, '://')) {
          $could_copy = copy(urldecode($fileurl), CASASYNC_CUR_UPLOAD_BASEDIR . $filename );
        } else {
          $could_copy = copy($fileurl, CASASYNC_CUR_UPLOAD_BASEDIR . $filename );
        }
        if (!$could_copy) {
          $this->transcript['attachments'][$property_id]["uploaded_from_gateway"][] = 'FAILED: ' .$filename;
          $filename = false;
        }

      }
    }
    return $filename;
  }

  public function casawpUploadAttachment($the_mediaitem, $post_id, $property_id) {
    if ($the_mediaitem['file']) {
      $filename = '/casawp/import/attachment/'. $the_mediaitem['file'];
    } elseif ($the_mediaitem['url']) { //external
      if ($the_mediaitem['type'] === 'image' && get_option('casawp_use_casagateway_cdn', false)){
        // simply don't copy the original file (the orig meta is used for rendering instead)
        $filename = $the_mediaitem['url'];
      } else {
        $filename = $this->casawpUploadAttachmentFromGateway($property_id, $the_mediaitem['url']);
      }
    } else { //missing
      $filename = false;
    }

    if ($filename && (is_file(CASASYNC_CUR_UPLOAD_BASEDIR . $filename) || get_option('casawp_use_casagateway_cdn', false))) {
      //new file attachment upload it and attach it fully
      $wp_filetype = wp_check_filetype(basename($filename), null );
      $guid = CASASYNC_CUR_UPLOAD_BASEURL . $filename;
      if ($the_mediaitem['type'] === 'image' && get_option('casawp_use_casagateway_cdn', false)) {
        $guid = $filename;
      }
      $attachment = array(
        'guid'           => $guid,
        'post_mime_type' => $wp_filetype['type'],
        'post_title'     => ( $the_mediaitem['title'] ? $the_mediaitem['title'] : basename($filename)),
        'post_name'      => sanitize_title_with_dashes($guid,'', 'save'),
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
      if ($term) {
        $term_id = $term->term_id;
        wp_set_post_terms( $attach_id,  array($term_id), 'casawp_attachment_type' );
      }

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
        if (get_locale()) {
          $main_lang = substr(get_locale(), 0, 2);
        //if (get_bloginfo('language')) {
        //  $main_lang = substr(get_bloginfo('language'), 0, 2);
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

  //NEW WAY
  /*public function updateInsertWPMLconnection($wp_post, $lang, $trid_identifier){
    if ($this->hasWPML()) {
      if ($this->getMainLang() == $lang) {
        $trid = wpml_get_content_trid('post_'.$wp_post->post_type, $wp_post->ID);
        if (!$trid) {
          $trid = ($wp_post->post_type == 'casawp_property' ? 1000 : 2000) . $wp_post->ID;
        }
        $this->trid_store[$trid_identifier] = $trid;
      } else {
        $trid = (isset($this->trid_store[$trid_identifier]) ? $this->trid_store[$trid_identifier] : false);
      }
      if ($trid) {
        $_POST['icl_post_language'] = $lang;

        global $wpdb;
        $existing = $wpdb->get_results( 'SELECT * FROM ' . $wpdb->prefix . 'icl_translations WHERE
          trid = ' . $trid . '
          AND language_code = \'' . $lang . '\'
          ', OBJECT );

          $new = array(
            'trid' => $trid,
            'element_id' => $wp_post->ID,
            'element_type' => 'post_'.$wp_post->post_type,
            'language_code' => $lang
          );
          if ($this->getMainLang() != $lang) {
            $new['source_language_code'] = $this->getMainLang();
            $this->transcript['wpml_'.$wp_post->post_type][] = 'set alternate language for trid:' . $trid . '(' . $lang . ')';
          } else {
            $this->transcript['wpml_'.$wp_post->post_type][] = 'set main language for trid:' . $trid . '(' . $lang . ')';
          }
        if (!$existing) {
          $wpdb->insert( $wpdb->prefix . 'icl_translations', $new );
        } else {
          $old = array(
            'trid'          => $existing[0]->trid,
            'element_id'    => $existing[0]->element_id,
            'element_type'  => $existing[0]->element_type,
            'language_code' => $existing[0]->language_code
          );
          if ($this->getMainLang() != $lang) {
            $old['source_language_code'] = $existing[0]->source_language_code;
          }
          if ($new != $old) {
            $wpdb->update( $wpdb->prefix . 'icl_translations', $new, array('translation_id' => $existing[0]->translation_id) );
          }

        }

      } else {
        $this->transcript['wpml_'.$wp_post->post_type][] = 'unable to find trid for ' . $trid_identifier;
      }
    }
  }*/

  //OLD WAY
  /*public function bhjkfwInsertWPMLconnection($wp_post, $lang, $trid_identifier){
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
  }*/

  //HYBRID
  public function updateInsertWPMLconnection($wp_post, $lang, $trid_identifier){
    if ($this->hasWPML()) {
      if ($this->getMainLang() == $lang) {
        $trid = wpml_get_content_trid('post_'.$wp_post->post_type, $wp_post->ID);
        if (!$trid) {
          $trid = ($wp_post->post_type == 'casawp_property' ? 1000 : 2000) . $wp_post->ID;
        }
        $this->trid_store[$trid_identifier] = $trid;
      } else {
        $trid = (isset($this->trid_store[$trid_identifier]) ? $this->trid_store[$trid_identifier] : false);
      }
      if ($trid) {
        $_POST['icl_post_language'] = $lang;

        global $sitepress;
        if ($this->getMainLang() != $lang) {
          $sitepress->set_element_language_details($wp_post->ID, 'post_casawp_property', $trid, $lang, $sitepress->get_default_language(), true);
        } else {
          $sitepress->set_element_language_details($wp_post->ID, 'post_casawp_property', $trid, $lang, NULL, true);
        }

        /*
        $_POST['icl_post_language'] = $lang;

        global $wpdb;
        $existing = $wpdb->get_results( 'SELECT * FROM ' . $wpdb->prefix . 'icl_translations WHERE
          trid = ' . $trid . '
          AND language_code = \'' . $lang . '\'
          ', OBJECT );

          $new = array(
            'trid' => $trid,
            'element_id' => $wp_post->ID,
            'element_type' => 'post_'.$wp_post->post_type,
            'language_code' => $lang
          );
          if ($this->getMainLang() != $lang) {
            $new['source_language_code'] = $this->getMainLang();
            $this->transcript['wpml_'.$wp_post->post_type][] = 'set alternate language for trid:' . $trid . '(' . $lang . ')';
          } else {
            $this->transcript['wpml_'.$wp_post->post_type][] = 'set main language for trid:' . $trid . '(' . $lang . ')';
          }
        if (!$existing) {
          $wpdb->insert( $wpdb->prefix . 'icl_translations', $new );
        } else {
          $old = array(
            'trid'          => $existing[0]->trid,
            'element_id'    => $existing[0]->element_id,
            'element_type'  => $existing[0]->element_type,
            'language_code' => $existing[0]->language_code
          );
          if ($this->getMainLang() != $lang) {
            $old['source_language_code'] = $existing[0]->source_language_code;
          }
          if ($new != $old) {
            $wpdb->update( $wpdb->prefix . 'icl_translations', $new, array('translation_id' => $existing[0]->translation_id) );
          }

        }*/

      } else {
        $this->transcript['wpml_'.$wp_post->post_type][] = 'unable to find trid for ' . $trid_identifier;
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


  public function setOfferAttachments($offer_medias, $wp_post, $property_id, $casawp_id, $property){
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
            'title'   => ( $offer_media['title'] ? $offer_media['title'] : basename($media['original_file'])),
            'file'    => '',
            'url'     => $media['original_file'],
            'caption' => $offer_media['caption'],
            'order'   => $o
          );
        }
      }
    }


    if (get_option('casawp_limit_reference_images') && $property['availability'] == 'reference') {
      $title_image = false;
      foreach ($the_casawp_attachments as $key => $attachment) {
        if ($attachment['type'] == 'image') {
          $title_image = $attachment;
          break;
        }
      }
      if ($title_image) {
        $the_casawp_attachments = array(0 => $title_image);
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
    if (isset($the_casawp_attachments)) { // go through each attachment specified in xml
      $wp_casawp_attachments_to_remove = $wp_casawp_attachments;
      $dup_checker_arr = [];
      foreach ($the_casawp_attachments as $the_mediaitem) { // go through each available attachment already in db
        //look up wp and see if file is already attached
        $existing = false;
        $existing_attachment = array();
        foreach ($wp_casawp_attachments as $key => $wp_mediaitem) {
          $attachment_customfields = get_post_custom($wp_mediaitem->ID);
          $original_filename = (array_key_exists('_origin', $attachment_customfields) ? $attachment_customfields['_origin'][0] : '');

          // this checks for duplicates and ignores them if they exist. This can fix duplicates existing in the DB if they where, for instance, created durring run-in imports.
          if (in_array($original_filename, $dup_checker_arr)) {
            $this->addToLog('found duplicate for id: ' . $wp_mediaitem->ID . ' orig: ' . $original_filename);
            // this file appears to be a duplicate, skip it (that way it will be deleted later) aka. it will remain in $wp_casawp_attachments_to_remove.
            // because it encountered this file before it must be made existing in the past loop right?
            // DISABLE FOR NOW
            // $existing = true;
            // continue;
          }
          $dup_checker_arr[] = $original_filename;

          $alt = '';
          if (
            $original_filename == ($the_mediaitem['file'] ? $the_mediaitem['file'] : $the_mediaitem['url'])
            ||
            str_replace('%3D', '=', str_replace('%3F', '?', $original_filename)) == ($the_mediaitem['file'] ? $the_mediaitem['file'] : $the_mediaitem['url'])
          ) {
            $existing = true;
            $this->addToLog('updating attachment ' . $wp_mediaitem->ID);

            //it's here to stay
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
                //'file'    => maibe? -> (is_file($the_mediaitem['file']) ? $the_mediaitem['file'] : '')
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
                $att['post_title']   = ( $the_mediaitem['title'] ? $the_mediaitem['title'] : basename($filename));
                $att['ID']           = $wp_mediaitem->ID;
                $att['menu_order']   = $the_mediaitem['order'];
                $insert_id           = wp_update_post( $att);
              }
              //update attachment category
              if ($existing_attachment['type'] != $the_mediaitem['type']) {
                $term = get_term_by('slug', $the_mediaitem['type'], 'casawp_attachment_type');
                if ($term) {
                  $term_id = $term->term_id;
                  wp_set_post_terms( $wp_mediaitem->ID,  array($term_id), 'casawp_attachment_type' );
                }
              }
              //update attachment alt
              if ($alt != $the_mediaitem['alt']) {
                update_post_meta($wp_mediaitem->ID, '_wp_attachment_image_alt', $the_mediaitem['alt']);
              }
            }
          }


        }

        if (!$existing) {
          if (isset($wp_mediaitem->ID)) {
            $this->addToLog('creating new attachment ' . $wp_mediaitem->ID);
          }
          //insert the new image
          $new_id = $this->casawpUploadAttachment($the_mediaitem, $wp_post->ID, $property_id);
          if (is_int($new_id)) {
            $this->transcript[$casawp_id]['attachments']["created"] = $the_mediaitem['file'];
          } else {
            $this->transcript[$casawp_id]['attachments']["failed_to_create"] = $new_id;
          }
        }

        //tries to fix missing files
        if (! get_option('casawp_use_casagateway_cdn', false) && isset($the_mediaitem['url'])) {
          $this->casawpUploadAttachmentFromGateway($property_id, $the_mediaitem['url']);
        }


      } //foreach ($the_casawp_attachments as $the_mediaitem) {

      //images to remove
      if ($wp_casawp_attachments_to_remove){
        $this->addToLog('removing ' . count($wp_casawp_attachments_to_remove) . ' attachments');
      }
      foreach ($wp_casawp_attachments_to_remove as $attachment) {
        $this->addToLog('removing ' . $attachment->ID);
        $this->transcript[$casawp_id]['attachments']["removed"] = $attachment;

        // $attachment_customfields = get_post_custom($attachment->ID);
        // $original_filename = (array_key_exists('_origin', $attachment_customfields) ? $attachment_customfields['_origin'][0] : '');
        wp_delete_attachment( $attachment->ID );
      }

      //featured image (refetch to avoid setting just removed items or not having new items)
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
            if (
              $original_filename == ($attachment_image_order['file'] ? $attachment_image_order['file'] : $attachment_image_order['url'])
              ||
              str_replace('%3D', '=', str_replace('%3F', '?', $original_filename)) == ($attachment_image_order['file'] ? $attachment_image_order['file'] : $attachment_image_order['url'])
            ) {
              $cur_thumbnail_id = get_post_thumbnail_id( $wp_post->ID );
              if ($cur_thumbnail_id != $wp_mediaitem->ID) {
                set_post_thumbnail( $wp_post->ID, $wp_mediaitem->ID );
                $this->transcript[$casawp_id]['attachments']["featured_image_set"] = 1;
                break;
              }
            }
          }
        }
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
      'private',
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

    // TODO: non official cateogries cause weird updates!!!!
    // if (in_array('PARKING', $new_categories) ) {
    //   print_r($new_categories);
    //   print_r($old_categories);
    //   die();
    // }


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
          if (isset($custom['label'])) {
            $custom_categorylabels[$custom['slug']] = $custom['label'];
          } else {
            $custom_categorylabels[$custom['slug']] = $custom['slug'];
          }

        }
      }

      //make sure the categories exist first
      foreach ($slugs_to_add as $new_term_slug) {
        $label = (array_key_exists($new_term_slug, $custom_categorylabels) ? $custom_categorylabels[$new_term_slug] : false);
        $this->setcasawpCategoryTerm($new_term_slug, $label);
      }

      //add the new ones
      $connect_term_ids = array();
      $category_terms = get_terms( array('casawp_category'), array('hide_empty' => false));
      $connect_term_ids = array();
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
      $connect_term_ids = array();
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

  public function setOfferUtilities($wp_post, $utilities, $casawp_id){
    $new_utilities = array();
    $old_utilities = array();

    //set post feature
    $old_utilities = array();
    $wp_utility_terms = wp_get_object_terms($wp_post->ID, 'casawp_utility');
    foreach ($wp_utility_terms as $term) {
      $old_utilities[] = $term->slug;
    }

    //supported
    if ($utilities) {
      foreach ($utilities as $utility) {
        $new_utilities[] = $utility;
      }
    }

    //have utilities changed?
    if (array_diff($new_utilities, $old_utilities) || array_diff($old_utilities, $new_utilities)) {
      $slugs_to_remove = array_diff($old_utilities, $new_utilities);
      $slugs_to_add    = array_diff($new_utilities, $old_utilities);
      $this->transcript[$casawp_id]['utilities_changed']['removed_utility'] = $slugs_to_remove;
      $this->transcript[$casawp_id]['utilities_changed']['added_utility'] = $slugs_to_add;

      //make sure the utilities exist first
      foreach ($slugs_to_add as $new_term_slug) {
        $label = false;
        $this->setcasawputilityTerm($new_term_slug, $label);
      }

      //add the new ones
      $utility_terms = get_terms( array('casawp_utility'), array('hide_empty' => false));
      $connect_term_ids = array();
      foreach ($utility_terms as $term) {
        if (in_array($term->slug, $new_utilities)) {
          $connect_term_ids[] = (int) $term->term_id;
        }
      }
      if ($connect_term_ids) {
        wp_set_object_terms( $wp_post->ID, $connect_term_ids, 'casawp_utility' );
      }
    }
  }


  public function setOfferRegions($wp_post, $terms, $casawp_id){
    $new_terms = array();
    $old_terms = array();

    //set post term
    $old_terms = array();
    $wp_term_terms = wp_get_object_terms($wp_post->ID, 'casawp_region');
    foreach ($wp_term_terms as $term) {
      $old_terms[] = $term->slug;
    }

    //supported
    if ($terms) {
      foreach ($terms as $term) {
        $new_terms[] = $term['slug'];
      }
    }

    //have terms changed?
    if (array_diff($new_terms, $old_terms) || array_diff($old_terms, $new_terms)) {
      $slugs_to_remove = array_diff($old_terms, $new_terms);
      $slugs_to_add    = array_diff($new_terms, $old_terms);
      $this->transcript[$casawp_id]['regions_changed']['removed_region'] = $slugs_to_remove;
      $this->transcript[$casawp_id]['regions_changed']['added_region'] = $slugs_to_add;

      //get the custom labels they need them
      $custom_labels = array();
      if (isset($terms)) {
        foreach ($terms as $custom) {
          if (isset($custom['label'])) {
            $custom_labels[$custom['slug']] = $custom['label'];
          } else {
            $custom_labels[$custom['slug']] = $custom['slug'];
          }

        }
      }

      //make sure the terms exist first
      foreach ($slugs_to_add as $new_term_slug) {
        $label = (array_key_exists($new_term_slug, $custom_labels) ? $custom_labels[$new_term_slug] : false);
        $this->setcasawpRegionTerm($new_term_slug, $label);
      }

      //add the new ones
      $term_terms = get_terms( array('casawp_region'), array('hide_empty' => false));
      $connect_term_ids = array();
      foreach ($term_terms as $term) {
        if (in_array($term->slug, $new_terms)) {
          $connect_term_ids[] = (int) $term->term_id;
        }
      }
      if ($connect_term_ids) {
        wp_set_object_terms( $wp_post->ID, $connect_term_ids, 'casawp_region' );
      }
    }
  }

  public function addToLog($transcript){
    $dir = CASASYNC_CUR_UPLOAD_BASEDIR  . '/casawp/logs';
    if (!file_exists($dir)) {
        mkdir($dir, 0777, true);
    }
    file_put_contents($dir."/".get_date_from_gmt('', 'Ym').'.log', "\n".json_encode(array(get_date_from_gmt('', 'Y-m-d H:i') => $transcript)), FILE_APPEND);
  }

  public function casawpImport(){


    if ($this->getImportFile()) {
      if (is_admin()) {
        $this->updateOffers();
        echo '<div id="message" class="updated"><p>casawp <strong>updated</strong>.</p><pre>' . print_r($this->transcript, true) . '</pre></div>';
      } else {
        $this->updateOffers();
        $this->transcript;
        //echo '<div id="message" class="updated"><p>casawp <strong>updated</strong>.</p><pre>' . print_r($this->transcript, true) . '</pre></div>';
        //do task in the background
        //add_action('asynchronous_import', array($this,'updateOffers'));
        //wp_schedule_single_event(time(), 'asynchronous_import');
      }
    }
  }

  public function gatewaypoke(){
    add_action('asynchronous_gatewayupdate', array($this,'gatewaypokeanswer'));
    $this->addToLog('Scheduled an Update on: ' . time());
    wp_schedule_single_event(time(), 'asynchronous_gatewayupdate');
  }

  public function gatewaypokeanswer(){
    $this->addToLog('gateway call file: ' . time());
    $this->updateImportFileThroughCasaGateway();
    $this->addToLog('gateway import answer: ' . time());
    $this->updateOffers();
  }

  public function updateImportFileThroughCasaGateway(){
    $this->addToLog('gateway file retriaval start: ' . time());

    $apikey = get_option('casawp_api_key');
    $privatekey = get_option('casawp_private_key');
    $apiurl = 'https://casagateway.ch/rest/publisher-properties';
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
      $url = $apiurl . '?' . http_build_query($query, '', '&');

      $response = false;


      if (!function_exists('curl_version')) {
        $this->addToLog('gateway ERR (CURL MISSING!!!): ' . time());
        echo '<div id="message" class="updated"> CURL MISSING!!!</div>';
      }

      $ch = curl_init();
      try {
          //$url = 'http://casacloud.cloudcontrolapp.com' . '/rest/provider-properties?' . http_build_query($query);
          curl_setopt($ch, CURLOPT_URL, $url);
          curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
          curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
          $response = curl_exec($ch);
          $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
          if($httpCode == 404) {
              $response = $httpCode;
          }
      } catch (Exception $e) {
          $response =  $e->getMessage() ;
          $this->addToLog('gateway ERR (' . $response . '): ' . time());
      }

      if ($response) {
        if (!is_dir(CASASYNC_CUR_UPLOAD_BASEDIR . '/casawp/import')) {
          mkdir(CASASYNC_CUR_UPLOAD_BASEDIR . '/casawp/import');
        }
        $file = CASASYNC_CUR_UPLOAD_BASEDIR  . '/casawp/import/data.xml';

        file_put_contents($file, $response);
      } else {
        $this->addToLog('ERR no response from gateway: ' . time());
        $this->addToLog(curl_error($ch));
      }
      curl_close($ch);

      $this->addToLog('gateway start update: ' . time());
      //UPDATE OFFERS NOW!!!!
      if ($this->getImportFile()) {
        $this->addToLog('import start');
        $this->updateOffers();
        $this->addToLog('import end');
      }


      //echo '<div id="message" class="updated">XML wurde aktualisiert</div>';
    } else {
      $this->addToLog('gateway keys missing: ' . time());
      echo '<div id="message" class="updated"> API Keys missing</div>';
    }
  }

  public function addToTranscript($msg){
    $this->transcript[] = $msg;
  }

  public function property2Array($property_xml){
    /* echo '<pre>';
    print_r($property_xml);
    echo '</pre>';
    die(); */
    $propertydata['address'] = array(
        'country'       => ($property_xml->address->country->__toString() ?:''),
        'locality'      => ($property_xml->address->locality->__toString() ?:''),
        'region'        => ($property_xml->address->region->__toString() ?:''),
        'postal_code'   => ($property_xml->address->postalCode->__toString() ?:''),
        'street'        => ($property_xml->address->street->__toString() ?:''),
        'streetNumber' => ($property_xml->address->streetNumber->__toString() ?:''),
        'streetAddition' => ($property_xml->address->streetAddition->__toString() ?:''),
        'subunit'       => ($property_xml->address->subunit->__toString() ?:''),
        'lng'           => ($property_xml->address->geo ? $property_xml->address->geo->longitude->__toString():''),
        'lat'           => ($property_xml->address->geo ? $property_xml->address->geo->latitude->__toString():''),
    );
    $propertydata['creation'] = (isset($property_xml->softwareInformation->creation) ? new \DateTime($property_xml->softwareInformation->creation->__toString()) : '');
    $propertydata['last_update'] = (isset($property_xml->softwareInformation->lastUpdate) ? new \DateTime($property_xml->softwareInformation->lastUpdate->__toString()) : '');
    $propertydata['exportproperty_id'] = (isset($property_xml['id']) ? $property_xml['id']->__toString() : '');
    $propertydata['referenceId'] = (isset($property_xml->referenceId) ? $property_xml->referenceId->__toString() : '');
    $propertydata['visualReferenceId'] = (isset($property_xml->visualReferenceId) ? $property_xml->visualReferenceId->__toString() : '');
    $propertydata['availability'] = ($property_xml->availability->__toString() ? $property_xml->availability->__toString() : 'available');
    $propertydata['price_currency'] = $property_xml->priceCurrency->__toString();
    $propertydata['price'] = $property_xml->price->__toString();
    $propertydata['price_property_segment'] = (!$property_xml->price['propertysegment']?:str_replace('2', '', $property_xml->price['propertysegment']->__toString()));
    if ($property_xml->priceRange) {
      $propertydata['price_range_from'] = $property_xml->priceRange->from->__toString();
      $propertydata['price_range_to'] = $property_xml->priceRange->to->__toString();
    } else {
      $propertydata['price_range_from'] = null;
      $propertydata['price_range_to'] = null;
    }
    $propertydata['net_price'] = $property_xml->netPrice->__toString();
    $propertydata['net_price_time_segment'] = ($property_xml->netPrice['timesegment'] ? strtolower($property_xml->netPrice['timesegment']->__toString()) : '');
    $propertydata['net_price_property_segment'] = (!$property_xml->netPrice['propertysegment']?: str_replace('2', '', $property_xml->netPrice['propertysegment']->__toString()));
    $propertydata['gross_price'] = $property_xml->grossPrice->__toString();
    $propertydata['gross_price_time_segment'] = ($property_xml->grossPrice['timesegment'] ? strtolower($property_xml->grossPrice['timesegment']->__toString()) : '');
    $propertydata['gross_price_property_segment'] = (!$property_xml->grossPrice['propertysegment']?:str_replace('2', '', $property_xml->grossPrice['propertysegment']->__toString()));

    if ($property_xml->integratedOffers) {
        $propertydata['integratedoffers'] = array();
        foreach ($property_xml->integratedOffers->integratedOffer as $xml_integratedoffer) {
            $cost = $xml_integratedoffer->__toString();
            $propertydata['integratedoffers'][] = array(
                'type'             => ($xml_integratedoffer['type'] ? $xml_integratedoffer['type']->__toString() : ''),
                'cost'             => $cost,
                'frequency'        => ($xml_integratedoffer['frequency'] ? $xml_integratedoffer['frequency']->__toString() : ''),
                'time_segment'     => ($xml_integratedoffer['timesegment'] ? $xml_integratedoffer['timesegment']->__toString() : ''),
                'property_segment' => ($xml_integratedoffer['propertysegment'] ? $xml_integratedoffer['propertysegment']->__toString() : ''),
                'inclusive'        => ($xml_integratedoffer['inclusive'] ? $xml_integratedoffer['inclusive']->__toString() : 0)
            );
        }
    }

    if ($property_xml->extraCosts) {
        $propertydata['extracosts'] = array();
        foreach ($property_xml->extraCosts->extraCost as $xml_extra_cost) {
            $cost = $xml_extra_cost->__toString();
            $propertydata['extracosts'][] = array(
                'type'             => ($xml_extra_cost['type'] ? $xml_extra_cost['type']->__toString() : ''),
                'cost'             => $cost,
                'frequency'        => ($xml_extra_cost['frequency'] ? $xml_extra_cost['frequency']->__toString() : ''),
                'property_segment' => ($xml_extra_cost['propertysegment'] ? $xml_extra_cost['propertysegment']->__toString() : ''),
                'time_segment'     => ($xml_extra_cost['timesegment'] ? $xml_extra_cost['timesegment']->__toString() : ''),
            );
        }
    }

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
            if ($property_xml->seller->organization['id']) {
              $propertydata['organization']['id']    = $property_xml->seller->organization['id']->__toString();
            } else {
              $propertydata['organization']['id'] = false;
            }
            $propertydata['organization']['displayName']    = $property_xml->seller->organization->legalName->__toString();
            $propertydata['organization']['addition']         = $property_xml->seller->organization->brand->__toString();
            $propertydata['organization']['email']         = $property_xml->seller->organization->email->__toString();
            $propertydata['organization']['email_rem']     = $property_xml->seller->organization->emailRem->__toString();
            $propertydata['organization']['fax']           = $property_xml->seller->organization->fax->__toString();
            $propertydata['organization']['phone']         = $property_xml->seller->organization->phone->__toString();
            $propertydata['organization']['website_url']   = ($property_xml->seller->organization ? $property_xml->seller->organization->website->__toString() : '');
            $propertydata['organization']['website_title'] = ($property_xml->seller->organization && $property_xml->seller->organization->website ? $property_xml->seller->organization->website['title']->__toString() : '');
            $propertydata['organization']['website_label'] = ($property_xml->seller->organization && $property_xml->seller->organization->website ? $property_xml->seller->organization->website['label']->__toString() : '');

            //organization address
            if ($property_xml->seller->organization->address) {
                $propertydata['organization']['postalAddress'] = array();
                $propertydata['organization']['postalAddress']['country'] = $property_xml->seller->organization->address->country->__toString();
                $propertydata['organization']['postalAddress']['locality'] = $property_xml->seller->organization->address->locality->__toString();
                $propertydata['organization']['postalAddress']['region'] = $property_xml->seller->organization->address->region->__toString();
                $propertydata['organization']['postalAddress']['postal_code'] = $property_xml->seller->organization->address->postalCode->__toString();
                $propertydata['organization']['postalAddress']['street'] = $property_xml->seller->organization->address->street->__toString();
                $propertydata['organization']['postalAddress']['street_number'] = $property_xml->seller->organization->address->streetNumber->__toString();
                $propertydata['organization']['postalAddress']['street_addition'] = $property_xml->seller->organization->address->streetAddition->__toString();
                $propertydata['organization']['postalAddress']['post_office_box_number'] = $property_xml->seller->organization->address->postOfficeBoxNumber->__toString();
            }
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
            $offerData['excerpt'] = $offer_xml->excerpt->__toString();

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

            /*  echo '<pre>';
            print_r($offerData['offer_medias']);
            echo '</pre>';
            die(); */

            $offerDatas[] = $offerData;

        }
    }

    $propertydata['offers'] = $offerDatas;

    return $propertydata;

  }

  public function project2Array($project_xml){
    $data['ref'] = (isset($project_xml['id']) ? $project_xml['id']->__toString() : '');
    $data['referenceId'] = (isset($project_xml['referenceId']) ? $project_xml['referenceId']->__toString() : '');

    $di = 0;
    if ($project_xml->details) {
      foreach ($project_xml->details->detail as $xml_detail) {
        $di++;
        $data['details'][$di]['lang'] = (isset($xml_detail['lang']) ? $xml_detail['lang']->__toString() : '');
        $data['details'][$di]['name'] = (isset($xml_detail->name) ? $xml_detail->name->__toString() : '');

        $dd = 0;
        $data['details'][$di]['descriptions'] = [];
        if ($xml_detail->descriptions) {
          foreach ($xml_detail->descriptions->description as $xml_description) {
            $dd++;
            $data['details'][$di]['descriptions'][$dd]['title'] = (isset($xml_description['title']) ? $xml_description['title']->__toString() : '');
            $data['details'][$di]['descriptions'][$dd]['text'] = $xml_description->__toString();
          }
        }

      }
    }

    $ui = 0;
    if ($project_xml->units) {
        $data['units'] = array();
        foreach ($project_xml->units->unit as $xml_unit) {
          $ui++;
          $data['units'][$ui]['referenceId'] = (isset($xml_unit['referenceId']) ? $xml_unit['referenceId']->__toString() : '');
          $data['units'][$ui]['ref'] = (isset($xml_unit['id']) ? $xml_unit['id']->__toString() : '');
          $data['units'][$ui]['name'] = (isset($xml_unit->name) ? $xml_unit->name->__toString() : '');
          if ($xml_unit->details) {
            foreach ($xml_unit->details->detail as $xml_detail) {
              $di++;
              $data['units'][$ui]['details'][$di]['lang'] = (isset($xml_detail['lang']) ? $xml_detail['lang']->__toString() : '');
              $data['units'][$ui]['details'][$di]['name'] = (isset($xml_detail->name) ? $xml_detail->name->__toString() : '');

              $dd = 0;
              $data['units'][$ui]['details'][$di]['descriptions'] = [];
              if ($xml_detail->descriptions) {
                foreach ($xml_detail->descriptions->description as $xml_description) {
                  $dd++;
                  $data['units'][$ui]['details'][$di]['descriptions'][$dd]['title'] = (isset($xml_description['title']) ? $xml_description['title']->__toString() : '');
                  $data['units'][$ui]['details'][$di]['descriptions'][$dd]['text'] = $xml_description->__toString();
                }
              }

            }
          }

          $data['units'][$ui]['property_links'] = array();
          $pri = 0;
          foreach ($xml_unit->properties->propertyRef as $propertyRef) {
              $pri++;
              $data['units'][$ui]['property_links'][$pri]['ref'] = $propertyRef->__toString();
          }
        }
    }

    return $data;

  }

  public function langifyProject($projectData){
    //complete missing translations if multilingual

    $languages = array(0 => array(
      'language_code' => $this->getMainLang()
    ));

    if ($this->hasWPML()) {
      $languages = icl_get_languages('skip_missing=0&orderby=code');
    }

    $li = 0;
    foreach ($languages as $lang) {
      $li++;
      $translation = $projectData;
      $translation['lang'] = $lang['language_code'];
      $translation['detail'] = array('name' => '', 'descriptions' => array());
      foreach ($projectData['details'] as $key => $detail) {
        if ($detail['lang'] == $lang['language_code']) {
          $translation['detail'] = $detail;
        }
      }
      unset($translation['details']);

      foreach ($translation['units'] as $ukey => $unit) {
        $translation['units'][$ukey]['detail'] = array('name' => '', 'descriptions' => array());
        foreach ($unit['details'] as $key => $detail) {
          if ($detail['lang'] == $lang['language_code']) {
            $translation['units'][$ukey]['detail'] = $detail;
          }
        }
        unset($translation['units'][$ukey]['details']);
      }
      if ($lang['language_code'] == $this->getMainLang()) {
        $translations[0] = $translation;
      } else {
        $translations[$li] = $translation;
      }
    }

    ksort($translations);
    return $translations;

  }

  public function findLangKey($lang, $array){
    foreach ($array as $key => $value) {
      if (isset($value['lang'])) {
        if ($lang == $value['lang']) {
          return $key;
        }
      } else {
        return false;
      }
    }
    return false;
  }

  public function fillMissingTranslations($theoffers){
    $translations = array();
    $languages = icl_get_languages('skip_missing=0&orderby=code');
    //build wish list
    foreach ($languages as $lang) {
      $translations[$lang['language_code']] = false;
    }

    //complete that which is available
    foreach ($theoffers as $offerData) {
      $translations[$offerData['lang']] = $offerData;
    }

    $mainLangKey = $this->findLangKey($this->getMainLang(), $translations);
    if ($mainLangKey) {
      $carbon = $translations[$mainLangKey];
    } else {
      //first
      foreach ($translations as $translation) {
        if ($translation) {
          $carbon = $translation;
          break;
        }
      }
    }

    /* echo '<pre>';
    print_r($carbon);
    echo '</pre>';
    die(); */
    //copy main language to missing translations
    foreach ($languages as $language) {
      if (!$translations[$language['language_code']]) {
        $copy = $carbon;
        $copy['lang'] = $language['language_code'];
        
        if (get_option('casawp_auto_translate_properties')) {

          if ($copy['urls']) {
            foreach ($copy['urls'] as $i => $url) {
              $urlString = str_replace(array('http://', 'https://'), '', $url['url']);
              $urlString = strtok($urlString, '/');
              $copy['urls'][$i]['title'] = $urlString;
            }
          }

          if ($language['language_code'] == 'de') {
            if ($copy['type'] == 'rent') {
              $copy['name'] = 'Mietobjekt in ' . $copy['locality'];
            } else {
              $copy['name'] = 'Kaufobjekt in ' . $copy['locality'];
            }
            if ($copy['offer_medias']) {
              $doc = 1;
              $plan = 1;
              $img = 1;
              foreach ($copy['offer_medias'] as $i => $offer_media) {
                if ($offer_media['type'] == 'document') {                  
                  $copy['offer_medias'][$i]['title'] = 'Dokument #' . $doc;
                  $doc++;
                } elseif($offer_media['type'] == 'plan') {
                  $copy['offer_medias'][$i]['title'] = 'Plan #' . $plan;
                  $plan++;
                } elseif($offer_media['type'] == 'image' && $offer_media['caption'] != '') {
                  $copy['offer_medias'][$i]['caption'] = 'Bild #' . $img;
                  $img++;
                }
              }
            }
          } elseif($language['language_code'] == 'fr') {
            if ($copy['type'] == 'rent') {
              $copy['name'] = 'Objet à louer à ' . $copy['locality'];
            } else {
              $copy['name'] = 'Objet à acheter à ' . $copy['locality'];
            }
            if ($copy['offer_medias']) {
              $doc = 1;
              $plan = 1;
              $img = 1;
              foreach ($copy['offer_medias'] as $i => $offer_media) {
                if ($offer_media['type'] == 'document') {                  
                  $copy['offer_medias'][$i]['title'] = 'Document #' . $doc;
                  $doc++;
                } elseif($offer_media['type'] == 'plan') {
                  $copy['offer_medias'][$i]['title'] = 'Plan #' . $plan;
                  $plan++;
                } elseif($offer_media['type'] == 'image' && $offer_media['caption'] != '') {
                  $copy['offer_medias'][$i]['caption'] = 'Image #' . $img;
                  $img++;
                }
              }
            }
          } elseif($language['language_code'] == 'en') {
            if ($copy['type'] == 'rent') {
              $copy['name'] = 'Property for rent in ' . $copy['locality'];
            } else {
              $copy['name'] = 'Property for sale in ' . $copy['locality'];
            }
            if ($copy['offer_medias']) {
              $doc = 1;
              $plan = 1;
              $img = 1;
              foreach ($copy['offer_medias'] as $i => $offer_media) {
                if ($offer_media['type'] == 'document') {                  
                  $copy['offer_medias'][$i]['title'] = 'Document #' . $doc;
                  $doc++;
                } elseif($offer_media['type'] == 'plan') {
                  $copy['offer_medias'][$i]['title'] = 'Plan #' . $plan;
                  $plan++;
                } elseif($offer_media['type'] == 'image' && $offer_media['caption'] != '') {
                  $copy['offer_medias'][$i]['caption'] = 'Image #' . $img;
                  $img++;
                }
              }
            }
          } elseif($language['language_code'] == 'it') {
            if ($copy['type'] == 'rent') {
              $copy['name'] = 'Oggetto in affitto a ' . $copy['locality'];
            } else {
              $copy['name'] = 'Oggetto in vendita a ' . $copy['locality'];
            }
            if ($copy['offer_medias']) {
              $doc = 1;
              $plan = 1;
              $img = 1;
              foreach ($copy['offer_medias'] as $i => $offer_media) {
                if ($offer_media['type'] == 'document') {                  
                  $copy['offer_medias'][$i]['title'] = 'Documento #' . $doc;
                  $doc++;
                } elseif($offer_media['type'] == 'plan') {
                  $copy['offer_medias'][$i]['title'] = 'Piano #' . $plan;
                  $plan++;
                } elseif($offer_media['type'] == 'image' && $offer_media['caption'] != '') {
                  $copy['offer_medias'][$i]['caption'] = 'Immagine #' . $img;
                  $img++;
                }
              }
            }
          }        
          $copy['descriptions'] = array();
          $copy['excerpt'] = '';
        }
        $translations[$language['language_code']] = $copy;
      }
    }

    //find main key and move it to the front key=0
    $key = 0;
    $theoffers = array();
    foreach ($translations as $value) {
      $key++;
      if ($value['lang'] == $this->getMainLang()) {
        $theoffers[0] = $value;
      } else {
        $theoffers[$key] = $value;
      }
    }
    ksort($theoffers);
    return $theoffers;
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
    set_time_limit(600);
    global $wpdb;
    libxml_use_internal_errors();
    $xml = simplexml_load_file($this->getImportFile(), 'SimpleXMLElement', LIBXML_NOCDATA);
    $errors = libxml_get_errors();
    if (!$xml) {
      die('could not read XML!!!');
    }
    if ($errors) {
      $this->transcript['error'] = 'XML read error' . print_r($errors, true);
      die('XML read error');
    }
    $found_posts = array();
    //key is id value is rank!!!!
    $ranksort = array();
    $curRank = 0;


    // echo '<pre>';
    // $totalTime = microtime(true);

    // select all properties from db at once
    $startfullselectiontime = microtime(true);
    $posts_pool = [];
    $the_query = new \WP_Query( 'post_status=publish,pending,draft,future,trash&post_type=casawp_property&suppress_filters=true&posts_per_page=100000' );
    $wp_post = false;
    while ( $the_query->have_posts() ) :
      $the_query->the_post();
      global $post;
      $existing_casawp_import_id = get_post_meta($post->ID, 'casawp_id', true);
      if ($existing_casawp_import_id) {
        $posts_pool[$existing_casawp_import_id] = $post;
      }
    endwhile;
    wp_reset_postdata();
    // echo count($posts_pool);
    // echo'<br />select all time';
    // echo number_format((microtime(true) - $startfullselectiontime), 10);
    // echo '<br />';


    // function convert($size)
    // {
    //     $unit=array('b','kb','mb','gb','tb','pb');
    //     return @round($size/pow(1024,($i=floor(log($size,1024)))),2).' '.$unit[$i];
    // }

    // echo convert(memory_get_usage(true)); // 123 kb

    // die();

    /* echo '<pre>';
    print_r($xml->properties);
    echo '</pre>';
    die(); */

    foreach ($xml->properties->property as $property) {
      $curRank++;


      $timeStart = microtime(true);

      $propertyData = $this->property2Array($property);
      //make main language first and "single out" if not multilingual
      $theoffers = array();
      $i = 0;
      foreach ($propertyData['offers'] as $offer) {
        $i++;
        if ($offer['lang'] == $this->getMainLang()) {
          $theoffers[0] = $offer;
          $theoffers[0]['locality'] = $propertyData['address']['locality'];
        } else {
          if ($this->hasWPML()) {
            $theoffers[$i] = $offer;
            $theoffers[$i]['locality'] = $propertyData['address']['locality'];
          }
        }
      }

     /*  echo '<pre>';
      print_r($theoffers);
      echo '</pre>';
      die(); */

      //complete missing translations if multilingual
      if ($this->hasWPML()) {
        $theoffers = $this->fillMissingTranslations($theoffers);
      }



      $offer_pos = 0;
      foreach ($theoffers as $offerData) {
        $offer_pos++;

        //is it already in db
        $casawp_id = $propertyData['exportproperty_id'] . $offerData['lang'];


        // select one at a time
        // $the_query = new \WP_Query( 'post_status=publish,pending,draft,future,trash&post_type=casawp_property&suppress_filters=true&meta_key=casawp_id&meta_value=' . $casawp_id );
        // $wp_post = false;
        // while ( $the_query->have_posts() ) :
        //   $the_query->the_post();
        //   global $post;
        //   $wp_post = $post;
        // endwhile;
        // wp_reset_postdata();

        // select from pool
        $wp_post = false;
        if (array_key_exists($casawp_id, $posts_pool)) {
          $wp_post = $posts_pool[$casawp_id];
        }
        
        
        //if not create a basic property
        if (!$wp_post) {
          $this->transcript[$casawp_id]['action'] = 'new';
          $the_post['post_title'] = $offerData['name'];
          $the_post['post_content'] = 'unsaved property';
          $the_post['post_status'] = 'publish';
          $the_post['post_type'] = 'casawp_property';
          $the_post['menu_order'] = $curRank;
          $the_post['post_name'] = $this->casawp_sanitize_title($casawp_id . '-' . $offerData['name']);

          //use the casagateway creation date if its new
          $the_post['post_date'] = ($propertyData['creation'] ? $propertyData['creation']->format('Y-m-d H:i:s') : $propertyData['last_update']->format('Y-m-d H:i:s'));
          //die($the_post['post_date']);

          $_POST['icl_post_language'] = $offerData['lang'];
          $insert_id = wp_insert_post($the_post);
          update_post_meta($insert_id, 'casawp_id', $casawp_id);
          $wp_post = get_post($insert_id, OBJECT, 'raw');
          $this->addToLog('new property: '. $casawp_id);
        }

        $ranksort[$wp_post->ID] = $curRank;

        $found_posts[] = $wp_post->ID;

        $this->updateOffer($casawp_id, $offer_pos, $propertyData, $offerData, $wp_post);
        
        $this->updateInsertWPMLconnection($wp_post, $offerData['lang'], $propertyData['exportproperty_id']);

      }

      // echo $curRank . '<br />';
      // echo number_format((microtime(true) - $timeStart), 10);
      // echo '<br />';
      // if ($curRank > 500) {
      //   break;
      // }
      // echo '</pre>';
    }
    /* print_r($propertyData);
    die(); */
    // echo'<br />Total';
    // echo number_format((microtime(true) - $totalTime), 10);
    // echo '<br />';
    // die();

    if (!$found_posts) {
      $this->transcript['error'] = 'NO PROPERTIES FOUND IN XML!!!';
      $this->transcript['error_infos'] = [
        'filesize' => filesize(CASASYNC_CUR_UPLOAD_BASEDIR  . '/casawp/import/data-done.xml') . ' !'
      ];

      copy(CASASYNC_CUR_UPLOAD_BASEDIR  . '/casawp/import/data-done.xml', CASASYNC_CUR_UPLOAD_BASEDIR  . '/casawp/import/data-error.xml');

      wp_mail('alert@casasoft.com', get_bloginfo('name'), 'Dieser Kunde hat alle Objekte von der Webseite gelöscht. Kann das sein? Bitte prüfen.');
      //die('custom block');
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
          'exclude'        => get_post_thumbnail_id(),
          'tax_query'   => array(
            'relation'  => 'AND',
            array(
              'taxonomy' => 'casawp_attachment_type',
              'field'    => 'slug',
              'terms'    => array( 'image', 'plan', 'document', 'offer-logo', 'sales-brochure' )
            )
          )
        ) );

        if ( $attachments ) {
          foreach ( $attachments as $attachment ) {
            $attachment_id = $attachment->ID;
            if (get_option('casawp_permanently_delete_properties')) {
              wp_delete_attachment( $attachment->ID );
            }
          }
        }
        if (get_option('casawp_permanently_delete_properties')) {
          wp_delete_post($prop_to_rm->ID, 1);
        } else {
          wp_trash_post($prop_to_rm->ID);
        }

      }

      //4. set property menu_order
      $properties_to_sort = get_posts(  array(
        'suppress_filters'=>true,
        'language'=>'ALL',
        'numberposts' =>  100,
        'include'     =>  $found_posts,
        'post_type'   =>  'casawp_property',
        'post_status' =>  'publish'
        )
      );
      $sortsUpdated = 0;
       //echo '<pre>';
       //echo "properties_to_sort\n";
       //print_r($properties_to_sort);
//
       //echo "ranksort\n";
       //print_r($ranksort);
      // TODO: when one changes an id of a property in the xml with wpml:  Error: Maximum function nesting level of '256'  happens: 	WPML_Post_Synchronization->sync_with_translations( ) happens indefinetly
      foreach ($properties_to_sort as $prop_to_sort) {
        if (array_key_exists($prop_to_sort->ID, $ranksort)) {
          if ($prop_to_sort->menu_order != $ranksort[$prop_to_sort->ID]) {
            // echo "wp_post_update\n";
            // print_r('ID' . $prop_to_sort->ID . ':' . $prop_to_sort->menu_order . 'to' . $ranksort[$prop_to_sort->ID]);
            $sortsUpdated++;
            try {
              $newPostID = wp_update_post(array(
                'ID' => $prop_to_sort->ID,
                'menu_order' => $ranksort[$prop_to_sort->ID]
              ));
            } catch (\Throwable $th) {
              //throw $th;
              if (isset($this->transcript['wp_update_post_error'])) {
                $this->transcript['wp_update_post_error'][] = $th->getMessage();
              } else {
                $this->transcript['wp_update_post_error'] = [$th->getMessage()];
              }
            }
          }

        }

      }

       //echo '</pre>';

      $this->transcript['sorts_updated'] = $sortsUpdated;
      $this->transcript['properties_found_in_xml'] = count($found_posts);
      $this->transcript['properties_removed'] = count($properties_to_remove);

      //5a. fetch max and min options and set them anew
      global $wpdb;
      $meta_key_area = 'areaForOrder';
      $query = $wpdb->prepare("SELECT max( cast( meta_value as UNSIGNED ) ) FROM $wpdb->postmeta WHERE meta_key=%s", $meta_key_area );
      $max_area = $wpdb->get_var( $query );
      $query = $wpdb->prepare("SELECT min( cast( meta_value as UNSIGNED ) ) FROM $wpdb->postmeta WHERE meta_key=%s", $meta_key_area );
      $min_area = $wpdb->get_var( $query );

      //5b. fetch max and min options and set them anew
      $meta_key_rooms = 'number_of_rooms';
      $query = $wpdb->prepare("SELECT max( cast(meta_value as DECIMAL(10, 1) ) ) FROM $wpdb->postmeta WHERE meta_key=%s", $meta_key_rooms );
      $max_rooms = $wpdb->get_var( $query );
      $query = $wpdb->prepare("SELECT min( cast( meta_value as DECIMAL(10, 1) ) ) FROM $wpdb->postmeta WHERE meta_key=%s", $meta_key_rooms );
      $min_rooms = $wpdb->get_var( $query );

      update_option('casawp_archive_area_min', $min_area);
      update_option('casawp_archive_area_max', $max_area);
      update_option('casawp_archive_rooms_min', $min_rooms);
      update_option('casawp_archive_rooms_max', $max_rooms);


    //projects
    if ($xml->projects) {

      $found_posts = array();
      $sorti = 0;
      foreach ($xml->projects->project as $project) {
        $sorti++;

        $projectData = $this->project2Array($project);
        $projectDataLangified = $this->langifyProject($projectData);

        foreach ($projectDataLangified as $projectData) {
          $lang = $projectData['lang'];
          //is project already in db
          $casawp_id = $projectData['ref'] . $projectData['lang'];

          $the_query = new \WP_Query( 'post_type=casawp_project&suppress_filters=true&meta_key=casawp_id&meta_value=' . $casawp_id );
          $wp_post = false;
          while ( $the_query->have_posts() ) :
            $the_query->the_post();
            global $post;
            $wp_post = $post;
          endwhile;
          wp_reset_postdata();

          //if not create a basic project
          if (!$wp_post) {
            $this->transcript[$casawp_id]['action'] = 'new';
            $the_post['post_title'] = $projectData['detail']['name'];
            $the_post['post_content'] = 'unsaved project';
            $the_post['post_status'] = 'publish';
            $the_post['post_type'] = 'casawp_project';
            $the_post['post_name'] = $this->casawp_sanitize_title($casawp_id . '-' . $projectData['detail']['name']);
            $_POST['icl_post_language'] = $lang;
            $insert_id = wp_insert_post($the_post);

            update_post_meta($insert_id, 'casawp_id', $casawp_id);
            $wp_post = get_post($insert_id, OBJECT, 'raw');

          }
          $found_posts[] = $wp_post->ID;


          $found_posts = $this->updateProject($sorti, $casawp_id, $projectData, $wp_post, false, $found_posts);
          $this->updateInsertWPMLconnection($wp_post, $lang, 'project_'.$projectData['ref']);


        }
      }


      //3. remove all the unused projects
      $projects_to_remove = get_posts(  array(
        'suppress_filters' => true,
        'language' => 'ALL',
        'numberposts' =>  100,
        'exclude'     =>  $found_posts,
        'post_type'   =>  'casawp_project',
        'post_status' =>  'publish'
        )
      );
      foreach ($projects_to_remove as $prop_to_rm) {
        //remove the attachments
        /*$attachments = get_posts( array(
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
        }*/
        wp_trash_post($prop_to_rm->ID);
        $this->transcript['projects_removed'] = count($projects_to_remove);
      }
    }

    flush_rewrite_rules();

    //WPEngine clear cache hook
    global $wpe_common;
    if (isset($wpe_common)) {
      $this->transcript['wpengine'] = 'cache-cleared';
      foreach (array('clean_post_cache','trashed_posts','deleted_posts') as $hook){
        add_action( $hook, array( $wpe_common, 'purge_varnish_cache'));
      }
    }


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

  public function updateProject($sort, $casawp_id, $projectData, $wp_post, $parent_post = false, $found_posts = array()){
    $new_meta_data = array();

    //load meta data
    $old_meta_data = array();
    $meta_values = get_post_meta($wp_post->ID, null, true);
    foreach ($meta_values as $key => $meta_value) {
      $old_meta_data[$key] = $meta_value[0];
    }
    ksort($old_meta_data);

    //generate import hash
    $cleanProjectData = $projectData;
    //We dont trust this date – it tends to interfere with serialization because large exporters sometimes refresh this date without reason
    unset($cleanProjectData['last_update']);
    if (isset($cleanProjectData['modified'])) {
        unset($cleanProjectData['modified']);
    }
    $curImportHash = md5(serialize($cleanProjectData));




    //skip if is the same as before (accept if was trashed (reactivation))
    $update = true;
    if ($wp_post->post_status == 'publish') {
      $update = false;
      if (
        !isset($old_meta_data['last_import_hash'])
        || isset($_GET['force_all_properties'])
        || $curImportHash != $old_meta_data['last_import_hash']
      ) {
          $update = true;
      } else {
        //skip if is the same as before
        $this->addToLog('skipped project: '. $casawp_id);
      }
    }

    if ($update) {
      $this->transcript[$casawp_id]['action'] = 'update';
      if (!isset($old_meta_data['last_import_hash']) ) {
        $this->transcript[$casawp_id]['action'] = 'new';
      }

      //set new hash;
      $new_meta_data['last_import_hash'] = $curImportHash;

      //set referenceId
      $new_meta_data['referenceId'] = $projectData['referenceId'];

      // $descriptionParts = [];
      // foreach ($projectData['detail']['descriptions'] as $desc) {
      //   $desciptionParts = '<strong>'.$desc['name'].'</strong>' . '<br /><br />' . $desc['text'];
      // }

      /* main post data */
      $new_main_data = array(
        'ID'            => $wp_post->ID,
        'post_title'    => ($projectData['detail']['name'] ? $projectData['detail']['name'] : $casawp_id),
        //'post_content'  => implode('<br /><hr /><br />', $descriptionParts),
        'post_content'  => $this->extractDescription($projectData['detail']),
        'post_status'   => 'publish',
        'post_type'     => 'casawp_project',
        'post_excerpt'  => '',
        'menu_order'    => $sort
      );

      $old_main_data = array(
        'ID'            => $wp_post->ID,
        'post_title'    => $wp_post->post_title   ,
        'post_content'  => $wp_post->post_content ,
        'post_status'   => $wp_post->post_status  ,
        'post_type'     => $wp_post->post_type    ,
        'post_excerpt'  => '',
        'menu_order'    => $wp_post->menu_order
      );

      if ($parent_post) {
        $new_main_data['post_parent'] = $parent_post->ID;
        $old_main_data['post_parent'] = $parent_post->ID;
      }

      if ($new_main_data != $old_main_data) {
        foreach ($old_main_data as $key => $value) {
          if ($new_main_data[$key] != $old_main_data[$key]) {
            $this->transcript[$casawp_id]['main_data'][$key]['from'] = $old_main_data[$key];
            $this->transcript[$casawp_id]['main_data'][$key]['to'] = $new_main_data[$key];
          }
        }


        //manage post_name and post_date (if new)
        if (!$wp_post->post_name) {
          $new_main_data['post_name'] = $this->casawp_sanitize_title($casawp_id . '-' . $projectData['detail']['name']);
          //$new_main_date['post_date'] = ($property['creation'] ? $property['creation']->format('Y-m-d H:i:s') : $property['last_update']->format('Y-m-d H:i:s'));
        } else {
          $new_main_data['post_name'] = $wp_post->post_name;
        }

        //persist change
        $newPostID = wp_insert_post($new_main_data);
      }


      ksort($new_meta_data);

      if ($new_meta_data != $old_meta_data) {
        foreach ($new_meta_data as $key => $value) {
          $newval = $value;
          $oldval = (isset($old_meta_data[$key]) ? maybe_unserialize($old_meta_data[$key]) : '');
          if (($oldval || $newval) && $oldval != $newval) {
            update_post_meta($wp_post->ID, $key, $newval);
            $this->transcript[$casawp_id]['meta_data'][$key]['from'] = $oldval;
            $this->transcript[$casawp_id]['meta_data'][$key]['to'] = $newval;
          }
        }

        //remove supurflous meta_data
        /*foreach ($old_meta_data as $key => $value) {
          if (
            !isset($new_meta_data[$key])
            && !in_array($key, array('casawp_id'))
            && strpos($key, '_') !== 0
          ) {
            //remove
            delete_post_meta($wp_post->ID, $key, $value);
            $this->transcript[$casawp_id]['meta_data']['removed'][$key] = $value;
          }
        }*/
      }
    } //end update

    $lang = $this->getMainLang();
    if ($this->hasWPML()) {
      if ($parent_post) {
        $my_post_language_details = apply_filters( 'wpml_post_language_details', NULL, $parent_post->ID );
        if ($my_post_language_details) {
          $lang = $my_post_language_details['language_code'];
        }
      } else {
        $lang = $projectData['lang'];
      }
    }

    if (isset($projectData['units'])) {
      foreach ($projectData['units'] as $sortu => $unitData) {

        //is unit already in db
        $unit_casawp_id = 'subunit_' . $unitData['ref'] . $lang;

        $the_query = new \WP_Query( 'post_status=publish,pending,draft,future,trash&post_type=casawp_project&suppress_filters=true&meta_key=casawp_id&meta_value=' . $unit_casawp_id );
        $wp_unit_post = false;
        while ( $the_query->have_posts() ) :
          $the_query->the_post();
          global $post;
          $wp_unit_post = $post;
        endwhile;
        wp_reset_postdata();

        //if not create a basic project
        if (!$wp_unit_post) {
          $this->transcript[$unit_casawp_id]['action'] = 'new';
          $the_post['post_title'] = $unitData['detail']['name'];
          $the_post['post_content'] = 'unsaved unit';
          $the_post['post_status'] = 'publish';
          $the_post['post_type'] = 'casawp_project';
          $the_post['post_name'] = $this->casawp_sanitize_title($unit_casawp_id . '-' . $unitData['detail']['name']);
          $_POST['icl_post_language'] = $lang;
          $insert_id = wp_insert_post($the_post);
          update_post_meta($insert_id, 'casawp_id', $unit_casawp_id);
          $wp_unit_post = get_post($insert_id, OBJECT, 'raw');
        }

        $found_posts[] = $wp_unit_post->ID;


        $found_posts = $this->updateProject($sortu, $unit_casawp_id, $unitData, $wp_unit_post, $wp_post, $found_posts);
        $this->updateInsertWPMLconnection($wp_unit_post, $lang, 'unit_'.$unitData['ref']);


      }
    }


    if ($parent_post && isset($projectData['property_links'])) {
      //create links to properties
      $sort = 0;
      foreach ($projectData['property_links'] as $sort => $propertyLink) {
        $sort++;
        //1. find property by casawp_id
        //is it already in db
        $casawp_id = $propertyLink['ref'] . $lang;

        $the_query = new \WP_Query( 'post_type=casawp_property&suppress_filters=true&meta_key=casawp_id&meta_value=' . $casawp_id );
        $wp_property_post = false;
        while ( $the_query->have_posts() ) :
          $the_query->the_post();
          global $post;
          $wp_property_post = $post;
        endwhile;
        wp_reset_postdata();

        if ($wp_property_post) {
          update_post_meta($wp_property_post->ID, 'projectunit_id', $wp_post->ID);
          update_post_meta($wp_property_post->ID, 'projectunit_sort', $sort);

        } else {
        }

        //$casawp_id = $propertyData['exportproperty_id'] . $offerData['lang'];
      }
    }


    return $found_posts;


  }

  public function updateOffer($casawp_id, $offer_pos, $property, $offer, $wp_post){


    $new_meta_data = array();

    //load meta data
    $old_meta_data = array();
    $meta_values = get_post_meta($wp_post->ID, null, true);
    foreach ($meta_values as $key => $meta_value) {
      $old_meta_data[$key] = $meta_value[0];
    }
    ksort($old_meta_data);

    //generate import hash
    $cleanPropertyData = $property;
    //We dont trust this date – it tends to interfere with serialization because large exporters sometimes refresh this date without reason
    unset($cleanPropertyData['last_update']);
    unset($cleanPropertyData['last_import_hash']);
    if (isset($cleanPropertyData['modified'])) {
        unset($cleanPropertyData['modified']);
    }
    $curImportHash = md5(serialize($cleanPropertyData));

    if (!isset($old_meta_data['last_import_hash'])) {
      $old_meta_data['last_import_hash'] = 'no_hash';
    }

    //skip if is the same as before (accept if was trashed (reactivation))
    if ($wp_post->post_status == 'publish' && isset($old_meta_data['last_import_hash']) && !isset($_GET['force_all_properties'])) {
      if ($curImportHash == $old_meta_data['last_import_hash']) {
        $this->addToLog('skipped property: '. $casawp_id);
        return 'skipped';
      }
    }

    $this->addToLog('beginn property update: [' . $casawp_id . ']' . time());
    $this->addToLog(array($old_meta_data['last_import_hash'], $curImportHash));

    //set new hash;
    $new_meta_data['last_import_hash'] = $curImportHash;


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

    $name = (isset($publisher_options['override_name']) && $publisher_options['override_name'] ? $publisher_options['override_name'] : $offer['name']);
    if (is_array($name)) {
      $name = $name[0];
    }
    $excerpt = (isset($publisher_options['override_excerpt']) && $publisher_options['override_excerpt'] ? $publisher_options['override_excerpt'] : $offer['excerpt']);
    if (is_array($excerpt)) {
      $excerpt = $excerpt[0];
    }

    /* main post data */
    $new_main_data = array(
      'ID'            => $wp_post->ID,
      'post_title'    => ($name ? $name : 'Objekt'),
      'post_content'  => $this->extractDescription($offer, $publisher_options),
      'post_status'   => 'publish',
      'post_type'     => 'casawp_property',
      'post_excerpt'  => $excerpt,
      'post_date' => $wp_post->post_date,
      //'post_date'     => ($property['creation'] ? $property['creation']->format('Y-m-d H:i:s') : $property['last_update']->format('Y-m-d H:i:s')),
      /*'post_modified' => $property['last_update']->format('Y-m-d H:i:s'),*/
    );

    $old_main_data = array(
      'ID'            => $wp_post->ID,
      'post_title'    => $wp_post->post_title   ,
      'post_content'  => $wp_post->post_content ,
      'post_status'   => $wp_post->post_status  ,
      'post_type'     => $wp_post->post_type    ,
      'post_excerpt'  => $wp_post->post_excerpt ,
      'post_date' => $wp_post->post_date
      //'post_date'     => $wp_post->post_date    ,
      /*'post_modified' => $wp_post->post_modified,*/
    );
    if ($new_main_data != $old_main_data) {
      foreach ($old_main_data as $key => $value) {
        if ($new_main_data[$key] != $old_main_data[$key]) {
          $this->transcript[$casawp_id]['main_data'][$key]['from'] = $old_main_data[$key];
          $this->transcript[$casawp_id]['main_data'][$key]['to'] = $new_main_data[$key];
          $this->addToLog('updating main data (' . $key . '): ' . $old_main_data[$key] . ' -> ' . $new_main_data[$key]);
        }
      }


      //manage post_name and post_date (if new)
      if (!$wp_post->post_name) {
        $new_main_data['post_name'] = $this->casawp_sanitize_title($casawp_id . '-' . $offer['name']);
        //$new_main_date['post_date'] = ($property['creation'] ? $property['creation']->format('Y-m-d H:i:s') : $property['last_update']->format('Y-m-d H:i:s'));
      } else {
        $new_main_data['post_name'] = $wp_post->post_name;
        //$new_main_date['post_date'] = ($property['creation'] ? $property['creation']->format('Y-m-d H:i:s') : $property['last_update']->format('Y-m-d H:i:s'));
      }

      //persist change
      $newPostID = wp_insert_post($new_main_data);

    }



    //$casawp_visitInformation = $property->visitInformation->__toString();
    //$casawp_property_url = $property->url->__toString();
    $new_meta_data['property_address_country']       = $property['address']['country'];
    $new_meta_data['property_address_locality']      = $property['address']['locality'];
    $new_meta_data['property_address_region']        = $property['address']['region'];
    $new_meta_data['property_address_postalcode']    = $property['address']['postal_code'];
    $new_meta_data['property_address_streetaddress'] = $property['address']['street'];
    $new_meta_data['property_address_streetnumber']  = $property['address']['streetNumber'];
    $new_meta_data['property_address_streetaddition']  = $property['address']['streetAddition'];
    $new_meta_data['property_geo_latitude']          = $property['address']['lat'];
    $new_meta_data['property_geo_longitude']         = $property['address']['lng'];

    if ($offer['start']) {
      $new_meta_data['start']                          = $offer['start']->format('Y-m-d H:i:s');
    } else {
      $new_meta_data['start']                          = null;
    }

    $new_meta_data['referenceId']                    = $property['referenceId'];
    $new_meta_data['visualReferenceId']              = $property['visualReferenceId'];
    if (!$new_meta_data['referenceId']) {
      echo '<div id="message" class="error">Warning! no referenceId found. for:'.$casawp_id.' This could cause problems when sending inquiries</div>';
    }
    if (isset($property['organization'])) {
      //$new_meta_data['seller_org_phone_direct'] = $property['organization'][''];
      $new_meta_data['seller_org_phone_central'] = $property['organization']['phone'];
      //$new_meta_data['seller_org_phone_mobile'] = $property['organization'][''];
      $new_meta_data['seller_org_legalname']                     = $property['organization']['displayName'];
      $new_meta_data['seller_org_brand']                         = $property['organization']['addition'];
      $new_meta_data['seller_org_customerid']                    = $property['organization']['id'];

      if (isset($property['organization']['postalAddress'])) {
        $new_meta_data['seller_org_address_country']               = $property['organization']['postalAddress']['country'];
        $new_meta_data['seller_org_address_locality']              = $property['organization']['postalAddress']['locality'];
        $new_meta_data['seller_org_address_region']                = $property['organization']['postalAddress']['region'];
        $new_meta_data['seller_org_address_postalcode']            = $property['organization']['postalAddress']['postal_code'];
        $new_meta_data['seller_org_address_postofficeboxnumber']   = $property['organization']['postalAddress']['post_office_box_number'];
        $new_meta_data['seller_org_address_streetaddress']         = $property['organization']['postalAddress']['street'].' '.$property['organization']['postalAddress']['street_number'];
        $new_meta_data['seller_org_address_streetaddition']         = $property['organization']['postalAddress']['street_addition'];
      }
    }

    $personType = 'view';
    if (isset($property[$personType.'Person']) && $property[$personType.'Person']) {
      $prefix = 'seller_' . $personType . '_person_';
      $new_meta_data[$prefix.'function']      = $property[$personType.'Person']['function'];
      $new_meta_data[$prefix.'givenname']     = $property[$personType.'Person']['firstName'];
      $new_meta_data[$prefix.'familyname']    = $property[$personType.'Person']['lastName'];
      $new_meta_data[$prefix.'email']         = $property[$personType.'Person']['email'];
      $new_meta_data[$prefix.'fax']           = $property[$personType.'Person']['fax'];
      $new_meta_data[$prefix.'phone_direct']  = $property[$personType.'Person']['phone'];
      $new_meta_data[$prefix.'phone_mobile']  = $property[$personType.'Person']['mobile'];
      $new_meta_data[$prefix.'gender']        = $property[$personType.'Person']['gender'];
      $new_meta_data[$prefix.'note']          = $property[$personType.'Person']['note'];
    }

    $personType = 'inquiry';
    if (isset($property[$personType.'Person']) && $property[$personType.'Person']) {
      $prefix = 'seller_' . $personType . '_person_';
      $new_meta_data[$prefix.'function']      = $property[$personType.'Person']['function'];
      $new_meta_data[$prefix.'givenname']     = $property[$personType.'Person']['firstName'];
      $new_meta_data[$prefix.'familyname']    = $property[$personType.'Person']['lastName'];
      $new_meta_data[$prefix.'email']         = $property[$personType.'Person']['email'];
      $new_meta_data[$prefix.'fax']           = $property[$personType.'Person']['fax'];
      $new_meta_data[$prefix.'phone_direct']  = $property[$personType.'Person']['phone'];
      $new_meta_data[$prefix.'phone_mobile']  = $property[$personType.'Person']['mobile'];
      $new_meta_data[$prefix.'gender']        = $property[$personType.'Person']['gender'];
      $new_meta_data[$prefix.'note']          = $property[$personType.'Person']['note'];
    }

    $personType = 'visit';
    if (isset($property[$personType.'Person']) && $property[$personType.'Person']) {
      $prefix = 'seller_' . $personType . '_person_';
      $new_meta_data[$prefix.'function']      = $property[$personType.'Person']['function'];
      $new_meta_data[$prefix.'givenname']     = $property[$personType.'Person']['firstName'];
      $new_meta_data[$prefix.'familyname']    = $property[$personType.'Person']['lastName'];
      $new_meta_data[$prefix.'email']         = $property[$personType.'Person']['email'];
      $new_meta_data[$prefix.'fax']           = $property[$personType.'Person']['fax'];
      $new_meta_data[$prefix.'phone_direct']  = $property[$personType.'Person']['phone'];
      $new_meta_data[$prefix.'phone_mobile']  = $property[$personType.'Person']['mobile'];
      $new_meta_data[$prefix.'gender']        = $property[$personType.'Person']['gender'];
      $new_meta_data[$prefix.'note']          = $property[$personType.'Person']['note'];
    }




    //urls
    $url = null;
    $the_urls = array();
    if (isset($offer['urls'])) {
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

    $new_meta_data['price_currency'] = $property['price_currency'];

    //prices
    if (isset($property['price'])) {
      $new_meta_data['price'] = $property['price'];
      $new_meta_data['price_propertysegment'] = $property['price_property_segment'];
    }
    if (isset($property['price_range_from'])) {
      $new_meta_data['price_range_from'] = $property['price_range_from'];
    }
    if (isset($property['price_range_to'])) {
      $new_meta_data['price_range_to'] = $property['price_range_to'];
    }


    if (isset($property['net_price'])) {
      $new_meta_data['netPrice'] = $property['net_price'];
      $new_meta_data['netPrice_timesegment'] = $property['net_price_time_segment'];
      $new_meta_data['netPrice_propertysegment'] = $property['net_price_property_segment'];
    }

    if (isset($property['gross_price'])) {
      $new_meta_data['grossPrice'] = $property['gross_price'];
      $new_meta_data['grossPrice_timesegment'] = $property['gross_price_time_segment'];
      $new_meta_data['grossPrice_propertysegment'] = $property['gross_price_property_segment'];
    }

    $extraPrice = array();
    if (isset($property['extracosts'])) {
      foreach ($property['extracosts'] as $extra) {
        $extraPrice[] = array(
          'price' => $extra['cost'],
          'timesegment' => $extra['time_segment'],
          'propertysegment' => $extra['property_segment'],
          'currency' => $new_meta_data['price_currency'],
          'frequency' => $extra['frequency']
        );
      }
    }
    $new_meta_data['extraPrice'] = $extraPrice;

    $integratedoffers = array();
    if (isset($property['integratedoffers'])) {
      foreach ($property['integratedoffers'] as $integratedoffer) {
        $integratedoffers[] = array(
          'type' => $integratedoffer['type'],
          'price' => $integratedoffer['cost'],
          'timesegment' => $integratedoffer['time_segment'],
          'propertysegment' => $integratedoffer['property_segment'],
          'currency' => $new_meta_data['price_currency'],
          'frequency' => $integratedoffer['frequency'],
          'inclusive' => $integratedoffer['inclusive']
        );
      }
    }
    $new_meta_data['integratedoffers'] = $integratedoffers;


    //price for order

    if (array_key_exists('price', $new_meta_data) && $new_meta_data['price'] !== "") {
      $tmp_price = $new_meta_data['price'];
    } elseif(array_key_exists('grossPrice', $new_meta_data) && $new_meta_data['grossPrice'] !== "") {
      $tmp_price = $new_meta_data['grossPrice'];
    } elseif(array_key_exists('netPrice', $new_meta_data) && $new_meta_data['netPrice'] !== "") {
      $tmp_price = $new_meta_data['netPrice'];
    } else {
      $tmp_price = 9999999999;
    }

    $new_meta_data['priceForOrder'] = $tmp_price;

    #$tmp_price      = (array_key_exists('price', $new_meta_data)      && $new_meta_data['price'] !== "")      ? ($new_meta_data['price'])      :(9999999999);
    #$tmp_grossPrice = (array_key_exists('grossPrice', $new_meta_data) && $new_meta_data['grossPrice'] !== "") ? ($new_meta_data['grossPrice']) :(9999999999);
    #$tmp_netPrice   = (array_key_exists('netPrice', $new_meta_data)   && $new_meta_data['netPrice'] !== "")   ? ($new_meta_data['netPrice'])   :(9999999999);
    #$new_meta_data['priceForOrder'] = str_pad($tmp_netPrice, 10, 0, STR_PAD_LEFT) . str_pad($tmp_grossPrice, 10, 0, STR_PAD_LEFT) . str_pad($tmp_price, 10, 0, STR_PAD_LEFT);
    /* if ($tmp_price) {
      $new_meta_data['priceForOrder'] = $tmp_price;
    } else if ($tmp_grossPrice) {
      $new_meta_data['priceForOrder'] = $tmp_grossPrice;
    } else if ($tmp_netPrice) {
      $new_meta_data['priceForOrder'] = $tmp_netPrice;
    } */
    //nuvals
    $numericValues = array();
    foreach ($property['numeric_values'] as $numval) {
      $numericValues[$numval['key']] = $numval['value'];
    }
    $new_meta_data = array_merge($new_meta_data, $numericValues);


    $tmp_area_bwf      = (array_key_exists('area_bwf', $new_meta_data)      && $new_meta_data['area_bwf'] !== "")      ? ($new_meta_data['area_bwf'])      : null;
    $tmp_area_nwf      = (array_key_exists('area_nwf', $new_meta_data)      && $new_meta_data['area_nwf'] !== "")      ? ($new_meta_data['area_nwf'])      : null;
    $tmp_area_sia_nf      = (array_key_exists('area_sia_nf', $new_meta_data)      && $new_meta_data['area_sia_nf'] !== "")      ? ($new_meta_data['area_sia_nf'])      : null;
    if ($tmp_area_bwf) {
      $new_meta_data['areaForOrder'] = $tmp_area_bwf;
    } else if ($tmp_area_nwf) {
      $new_meta_data['areaForOrder'] = $tmp_area_nwf;
    } else if ($tmp_area_sia_nf) {
      $new_meta_data['areaForOrder'] = $tmp_area_sia_nf;
    }
     /* else {
          $new_meta_data['areaForOrder'] = 0;
        } */

    //integratedOffers
    //$integratedOffers = $this->integratedOffersToArray($property->offer->integratedOffers);
    //$new_meta_data = array_merge($new_meta_data, $integratedOffers);


    //custom option metas
    $custom_metas = array();
    foreach ($publisher_options as $key => $value) {
      if (strpos($key, 'custom_option') === 0) {
        $parts = explode('_', $key);
        $sort = (isset($parts[2]) && is_numeric($parts[2]) ? $parts[2] : false);
        $meta_key = (isset($parts[3]) && $parts[3] == 'key' ? true : false);
        $meta_value = (isset($parts[3]) && $parts[3] == 'value' ? true : false);

        if ($meta_key) {
          foreach ($publisher_options as $key2 => $value2) {
            if (strpos($key2, 'custom_option') === 0) {
              $parts2 = explode('_', $key2);
              $sort2 = (isset($parts2[2]) && is_numeric($parts2[2]) ? $parts2[2] : false);
              $meta_key2 = (isset($parts2[3]) && $parts2[3] == 'key' ? true : false);
              $meta_value2 = (isset($parts2[3]) && $parts2[3] == 'value' ? true : false);
              if ($meta_value2 && $sort2 == $sort) {
                $custom_metas[$value[0]] = $value2[0];
                break;
              }
            }
          }
        } elseif ($meta_value) {
          foreach ($publisher_options as $key2 => $value2) {
            if (strpos($key2, 'custom_option') === 0) {
              $parts2 = explode('_', $key2);
              $sort2 = (isset($parts2[2]) && is_numeric($parts2[2]) ? $parts2[2] : false);
              $meta_key2 = (isset($parts2[3]) && $parts2[3] == 'key' ? true : false);
              $meta_value2 = (isset($parts2[3]) && $parts2[3] == 'value' ? true : false);
              if ($meta_key2 && $sort2 == $sort) {
                $custom_metas[$value2[0]] = $value[0];
                break;
              }
            }
          }
        }
      }
    }

    if ($custom_metas) {
      $this->addToLog('_options_================HERE====================');
      $this->addToLog('_options_' . print_r($custom_metas, true));
    }

    foreach ($custom_metas as $key => $value) {
      $new_meta_data['custom_option_'.$key] = $value;
      $this->addToLog('custom_option_'.$key);
    }

    foreach ($new_meta_data as $key => $value) {
     /* if (!$value) {
        unset($new_meta_data[$key]);
      }*/
    }
    ksort($new_meta_data);

    if ($new_meta_data != $old_meta_data) {
      $this->addToLog('updating metadata');
      foreach ($new_meta_data as $key => $value) {
        $newval = $value;

        if ($newval === true) {
          $newval = "1";
        }
        if (is_numeric($value)) {
          $newval = (string) $value;
        }
        if ($key == "floor" && $newval == 0) {
          $newval = "EG"; // TODO Translate
        }

        $oldval = (isset($old_meta_data[$key]) ? maybe_unserialize($old_meta_data[$key]) : '');
        if (function_exists("casawp_unicode_dirty_replace") && !is_array($oldval)) {
          $oldval = casawp_unicode_dirty_replace($oldval); 
        }
        
        if (($oldval || $newval || $newval === 0) && $oldval !== $newval) {
          update_post_meta($wp_post->ID, $key, $newval);
          $this->transcript[$casawp_id]['meta_data'][$key]['from'] = $oldval;
          $this->transcript[$casawp_id]['meta_data'][$key]['to'] = $newval;
        }
      }

      //remove supurflous meta_data
      $this->addToLog('removing supurflous metadata');
      foreach ($old_meta_data as $key => $value) {
        if (
          !isset($new_meta_data[$key])
          && !in_array($key, array('casawp_id', 'projectunit_id', 'projectunit_sort'))
          && strpos($key, '_') !== 0
        ) {
          //remove
          delete_post_meta($wp_post->ID, $key, $value);
          $this->transcript[$casawp_id]['meta_data']['removed'][$key] = $value;
        }
      }
    }

    if (isset($property['property_categories'])) {
      $this->addToLog('updating categories');
      $custom_categories = array();
      foreach ($publisher_options as $key => $values) {
        if (strpos($key, 'custom_category') === 0) {
          $parts = explode('_', $key);
          $sort = (isset($parts[2]) && is_numeric($parts[2]) ? $parts[2] : false);
          $slug = (isset($parts[3]) && $parts[3] == 'slug' ? true : false);
          $label = (isset($parts[3]) && $parts[3] == 'label' ? true : false);
          if (!$values[0] || !$sort) {
            // skip
          } elseif ($slug) {
            $custom_categories[$sort]['slug'] = $values[0];
          } elseif ($label) {
            $custom_categories[$sort]['label'] = $values[0];
          }
        }

      }
     
      $this->setOfferCategories($wp_post, $property['property_categories'], $custom_categories, $casawp_id);
    }


    $this->addToLog('updating custom regions');
    $custom_regions = array();
    foreach ($publisher_options as $key => $values) {
      if (strpos($key, 'custom_region') === 0) {
        $parts = explode('_', $key);
        $sort = (isset($parts[2]) && is_numeric($parts[2]) ? $parts[2] : false);
        $slug = (isset($parts[3]) && $parts[3] == 'slug' ? true : false);
        $label = (isset($parts[3]) && $parts[3] == 'label' ? true : false);
        if (!$values[0] || !$sort) {
          // skip
        } elseif ($slug) {
          $custom_regions[$sort]['slug'] = $values[0];
        } elseif ($label) {
          $custom_regions[$sort]['label'] = $values[0];
        }
      }

    }


    $this->setOfferRegions($wp_post, $custom_regions, $casawp_id);



    $this->addToLog('updating features');
    $this->setOfferFeatures($wp_post, $property['features'], $casawp_id);

    $this->addToLog('updating utilities');
    $this->setOfferUtilities($wp_post, $property['property_utilities'], $casawp_id);

    $this->addToLog('updating salestypes');
    $this->setOfferSalestype($wp_post, $property['type'], $casawp_id);

    $this->addToLog('updating availabilities');
    $this->setOfferAvailability($wp_post, $property['availability'], $casawp_id);

    $this->addToLog('updating localities');
    $this->setOfferLocalities($wp_post, $property['address'], $casawp_id);

    $this->addToLog('updating attachments');
    $this->setOfferAttachments($offer['offer_medias'] , $wp_post, $property['exportproperty_id'], $casawp_id, $property);

    $this->addToLog('finish property update: [' . $casawp_id . ']' . time());

  }
}
