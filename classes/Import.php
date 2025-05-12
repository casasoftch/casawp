<?php

namespace casawp;

use Exception;

class Import
{
  public $importFile = false;
  public $main_lang = false;
  public $WPML = null;
  public $transcript = array();
  public $curtrid = false;
  public $trid_store = array();

  private $ranksort = array();

  private $touched = [];

  public function __construct($casagatewaypoke = false, $casagatewayupdate = false)
  {
   /*  if ($casagatewaypoke) {
      add_action('init', array($this, 'updateImportFileThroughCasaGateway'));
    }
    if ($casagatewayupdate) {
      $this->updateImportFileThroughCasaGateway();
    } */
  }

  private function mark_touched( int $post_id ): void {
      $this->touched[ $post_id ] = true;
  }

   private function prune_orphans(): void {

      if ( empty( $this->touched ) ) {
          return;                       
      }

      $all_ids = get_posts( [
          'posts_per_page' => -1,
          'post_type'      => 'casawp_property',
          'post_status'    => 'publish',
          'fields'         => 'ids',
      ] );

      $orphans = array_diff( $all_ids, array_keys( $this->touched ) );
      if ( ! $orphans ) {
          return;
      }

      foreach ( $orphans as $post_id ) {

          $att = get_posts( [
              'post_type'      => 'attachment',
              'posts_per_page' => -1,
              'post_status'    => 'any',
              'post_parent'    => $post_id,
              'fields'         => 'ids',
          ] );
          foreach ( $att as $att_id ) {
              wp_delete_attachment( $att_id, true );
          }

          wp_delete_post( $post_id, true );
      }

      $this->addToLog( 'Pruned ' . count( $orphans ) . ' orphan properties.' );
  }

  public function fetchFileFromCasaGateway(): string
  {
    $this->addToLog('CASAWP: Start fetching fresh XML from CasaGateway at ' . time());

    $apikey     = get_option('casawp_api_key');
    $privatekey = get_option('casawp_private_key');
    $apiurl     = 'https://casagateway.ch/rest/publisher-properties';
    $options    = [
      'format' => 'casa-xml',
      'debug'  => 1,
    ];

    if (!$apikey || !$privatekey) {
      $this->addToLog('CASAWP: gateway keys missing');
      throw new \Exception('API Keys missing.');
    }
    if (!function_exists('curl_version')) {
      $this->addToLog('CASAWP: gateway ERR (CURL MISSING!!!)');
      throw new \Exception('CURL is missing.');
    }

    $timestamp   = time();
    ksort($options);
    $checkstring = '';
    foreach ($options as $key => $value) {
      $checkstring .= $key . $value;
    }
    $checkstring .= $privatekey . $timestamp;
    $hmac = hash('sha256', $checkstring, false);

    $query = [
      'hmac'      => $hmac,
      'apikey'    => $apikey,
      'timestamp' => $timestamp
    ] + $options;

    $url      = $apiurl . '?' . http_build_query($query, '', '&');
    $response = false;

    $ch = curl_init();
    try {
      curl_setopt($ch, CURLOPT_URL, $url);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
      curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
      $response = curl_exec($ch);
      $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      if ($httpCode == 404) {
        throw new \Exception('Received 404 from CasaGateway.');
      }
    } catch (\Exception $e) {
      $this->addToLog('CASAWP: cURL Exception: ' . $e->getMessage());
      throw $e;
    } finally {
      curl_close($ch);
    }

    if (!$response || is_numeric($response)) {
      $this->addToLog('CASAWP: Invalid response from gateway');
      throw new \Exception('Invalid response from CasaGateway.');
    }

    $importDir = CASASYNC_CUR_UPLOAD_BASEDIR . '/casawp/import';
    if (!is_dir($importDir)) {
      if (!mkdir($importDir, 0755, true)) {
        $this->addToLog('CASAWP: Failed to create import directory.');
        throw new \Exception('Failed to create import directory.');
      }
    }

    $filePath = $importDir . '/data.xml';
    if (file_put_contents($filePath, $response) === false) {
      $this->addToLog('CASAWP: Failed to save XML file');
      throw new \Exception('Failed to save imported XML file.');
    }

    $this->addToLog('CASAWP: File fetched & saved to ' . $filePath . ' at ' . time());

    return $filePath;
  }

  public function deactivate_all_properties()
  {
    $args = array(
      'posts_per_page' => -1,
      'post_type'      => 'casawp_property',
      'post_status'    => array('publish', 'pending', 'draft', 'future', 'trash'),
      'fields'         => 'ids',
    );

    $properties = get_posts($args);

    foreach ($properties as $property_id) {
      update_post_meta($property_id, 'is_active', false);
    }
  }

  public function reactivate_properties($current_batch_ids)
  {
    foreach ($current_batch_ids as $property_id) {
      update_post_meta($property_id, 'is_active', true);
    }
  }

  public function finalize_import_cleanup() {

      $this->addToLog( 'Finalizing import cleanup (diff-mode).' );

      // 1. Delete everything we did not touch
      $this->prune_orphans();

      // 2. Usual housekeeping
      flush_rewrite_rules();

      if ( class_exists( '\WpeCommon' ) ) {
          \WpeCommon::purge_varnish_cache();
          \WpeCommon::purge_memcached();
          $this->addToLog( 'Triggered WP Engine cache purge.' );
      }

      delete_transient( 'casawp_import_in_progress' );
      delete_option   ( 'casawp_import_failed' );

      $this->addToLog( 'Import completed and lock cleared.' );
      do_action( 'casawp_import_finished' );
  }


  /**
   * Absolute path for a given chunk number.
   */
  private function chunk_path(int $batch_no): string
  {
    return CASASYNC_CUR_UPLOAD_BASEDIR . "/casawp/import/chunks/batch_$batch_no.json";
  }

  /**
   * Stream the big XML file once and write N-item JSON chunks.
   *
   * @param int $chunkSize  Number of <property> nodes per chunk.
   * @return int            How many chunks were generated.
   */
  public function splitXmlIntoChunks(int $chunkSize = 50): int
  {

    $override = (int) get_option('casawp_batch_size_override', 0);
    if ($override > 0) {
      $chunkSize = $override;
    }

    $dir = CASASYNC_CUR_UPLOAD_BASEDIR . '/casawp/import/chunks';
    if (! is_dir($dir)) {
      wp_mkdir_p($dir);
    }

    $reader  = new \XMLReader();
    $reader->open($this->getImportFile());

    $created = 0;
    $current = [];

    while ($reader->read()) {
      if ($reader->nodeType === \XMLReader::ELEMENT && $reader->name === 'property') {

        $node     = simplexml_load_string($reader->readOuterXML(), 'SimpleXMLElement', LIBXML_NOCDATA);
        $current[] = $this->property2Array($node);

        if (count($current) === $chunkSize) {
          ++$created;
          file_put_contents($this->chunk_path($created), json_encode($current));
          $current = [];
        }
      }
    }
    $reader->close();

    if ($current) {
      ++$created;
      file_put_contents($this->chunk_path($created), json_encode($current));
    }
    return $created;
  }

  /**
   * Walk a chunk array and turn any JSON-encoded DateTime payload
   * back into real \DateTime instances, no matter whether the value
   * came back as a plain string or as the {"date": …} array used by
   * PHP’s built-in JsonSerializable implementation.
   */
  private function restoreDateTimes(array &$data): void
  {

    // keys we know are DateTimes in your property / offer structure
    static $datetimeKeys = ['creation', 'last_update', 'start'];

    foreach ($data as $k => &$v) {

      /* --- nested structure – recurse first ----------------------- */
      if (is_array($v) && ! isset($v['date'])) {
        $this->restoreDateTimes($v);
        continue;
      }

      /* --- {"date": "...", "timezone": "..."} shape --------------- */
      if (is_array($v) && isset($v['date'], $v['timezone'])) {
        try {
          $v = new \DateTime($v['date'], new \DateTimeZone($v['timezone']));
        } catch (\Exception $e) {
          // leave as-is on failure; log if you like
        }
        continue;
      }

      /* --- plain ISO string and key matches one we expect --------- */
      if (is_string($v) && in_array($k, $datetimeKeys, true)) {
        // quick check – looks like "YYYY-MM-DD"
        if (preg_match('/^\d{4}\-\d{2}\-\d{2}/', $v)) {
          try {
            $v = new \DateTime($v);
          } catch (\Exception $e) { /* ignore */
          }
        }
      }
    }
  }



  public function handle_chunk(int $batch_no): void
  {

    /* 4-A  global cancel flag */
    if (get_option('casawp_import_canceled', false)) {
      $this->addToLog("Chunk $batch_no skipped – import canceled.");
      return;
    }

    /* 4-B  load JSON */
    $path = $this->chunk_path($batch_no);
    if (! file_exists($path)) {
      $this->addToLog("Chunk file missing for batch $batch_no.");
      return;
    }
    $items = json_decode(file_get_contents($path), true);
    if (! is_array($items)) {
      $this->addToLog("Chunk $batch_no contains invalid JSON.");
      @unlink($path);
      return;
    }

    /* 4-C  run the heavy work */
    try {
      array_walk($items, function (&$prop) {
        $this->restoreDateTimes($prop);
      });
      $this->updateOffers($items);
      if (empty($items)) {                 // JSON file was empty
        usleep(200000);                    // 0.2 s
      }
    } catch ( \Throwable $e ) {

      $msg = $e->getMessage();

      /* Known, recoverable WPML glitch – skip & continue */
      if ( strpos( $msg, 'No translation entry found' ) !== false ) {
        $this->addToLog( "WPML recoverable error ignored in chunk $batch_no: $msg" );
        // fall through – do NOT re-throw
      } else {
        $this->addToLog( "Fatal in chunk $batch_no: $msg" );
        throw $e;  
      }
    } finally {
      @unlink($path);                       // always clean up
    }

    /* 4-D  progress accounting */
    $done  = (int) get_option('casawp_completed_batches', 0) + 1;
    $total = (int) get_option('casawp_total_batches',     0);
    update_option('casawp_completed_batches', $done);

    $this->addToLog("Finished chunk $batch_no / $total.");

    /* 4-E  last chunk?  else queue the next one */
    if ($done >= $total) {

      $this->finalize_import_cleanup();
      delete_option('casawp_total_batches');
      delete_option('casawp_completed_batches');
      delete_option('casawp_import_canceled');
      delete_transient('casawp_import_in_progress');

      $this->addToLog('All chunks processed – import completed.');
    } else {

      $next = $batch_no + 1;

    }
  }


  public function handle_single_request_import()
  {
    // No top-level try/catch here. Let exceptions bubble up.
    if (!get_transient('casawp_import_in_progress')) {
      set_transient('casawp_import_in_progress', true, 6 * HOUR_IN_SECONDS);
    }

    // Possibly throws exceptions if something goes wrong
    $this->fetchFileFromCasaGateway();

    // Read the file or throw an exception
    $xmlString = file_get_contents($this->getImportFile());
    if ($xmlString === false) {
      $this->addToLog('Failed to read import file.');
      throw new Exception('Failed to read import file.');
    }

    $xml = simplexml_load_string($xmlString, "SimpleXMLElement", LIBXML_NOCDATA);
    if ($xml === false) {
      $this->addToLog('Failed to parse XML.');
      throw new Exception('Failed to parse XML.');
    }

    // Another example check
    if (!$xml->properties || !$xml->properties->property) {
      $this->addToLog('No properties found in XML.');
      throw new Exception('No properties found in XML.');
    }

    // If we get here, parse the properties and import
    $properties_array = [];
    foreach ($xml->properties->property as $property) {
      $properties_array[] = $this->property2Array($property);
    }

    // Mark single batch for consistency
    update_option('casawp_total_batches', 1);
    update_option('casawp_completed_batches', 0);

    // Do the update
    $this->updateOffers($properties_array);

    // Mark done
    update_option('casawp_completed_batches', 1);

    // Clean up leftover properties
    $this->finalize_import_cleanup();

    // Clear lock
    delete_transient('casawp_import_in_progress');

    // No exception thrown means success
  }


  public function getMainLang()
  {
    global $sitepress;
    if (!$this->main_lang) {
      $main_lang = 'de';
      if ($this->hasWPML()) {
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

  public function hasWPML()
  {
    if ($this->WPML !== true && $this->WPML !== false) {
      $this->WPML = $this->loadWPML();
    }
    return $this->WPML;
  }

  public function loadWPML()
  {
    global $sitepress;
    if ($sitepress && is_object($sitepress) && method_exists($sitepress, 'get_language_details')) {
      if (is_file(WP_PLUGIN_DIR . '/sitepress-multilingual-cms/inc/wpml-api.php')) {
        require_once(WP_PLUGIN_DIR . '/sitepress-multilingual-cms/inc/wpml-api.php');
      }
      return true;
    }
    return false;
  }

  /**
   * Link a property post to its WPML TRID – self-heals when rows
   * are missing **and never lets a WPML exception bubble up**.
   *
   * @param \WP_Post $wp_post
   * @param string   $lang
   * @param string   $trid_identifier (exportproperty_id, etc.)
   */
  public function updateInsertWPMLconnection( $wp_post, string $lang, string $trid_identifier ) {

    /* ---------- 0. WPML available? -------------------------------- */
    if ( ! $this->hasWPML() ) {
      return;
    }
    global $sitepress, $wpdb;

    $table    = $wpdb->prefix . 'icl_translations';
    $default  = $sitepress->get_default_language();

    /* ---------- 1. Get (or fabricate) a trid ---------------------- */
    if ( isset( $this->trid_store[ $trid_identifier ] ) ) {
      $trid = (int) $this->trid_store[ $trid_identifier ];
    } else {
      $trid = (int) apply_filters(
        'wpml_element_trid',
        null,
        $wp_post->ID,
        'post_' . $wp_post->post_type
      );
      if ( ! $trid ) {
        $trid  = ( $wp_post->post_type === 'casawp_property' ? 1000 : 2000 ) + $wp_post->ID;
      }
      $this->trid_store[ $trid_identifier ] = $trid;
    }

    /* ---------- 2. Make sure rows actually exist ------------------ */
    $row_tr = $wpdb->get_var( $wpdb->prepare(
      "SELECT translation_id FROM $table WHERE trid = %d LIMIT 1",
      $trid
    ) );
    $row_def = $wpdb->get_var( $wpdb->prepare(
      "SELECT translation_id FROM $table WHERE trid = %d AND language_code = %s LIMIT 1",
      $trid,
      $default
    ) );

    // If either the TRID itself or its default-language row is gone,
    // force WPML to start a fresh translation set.
    if ( ! $row_tr || ! $row_def ) {
      $this->addToLog(
        sprintf(
          'WPML: missing rows – will rebuild set (trid=%d row_tr=%s row_def=%s)',
          $trid,
          $row_tr ? 'OK' : 'MISSING',
          $row_def ? 'OK' : 'MISSING'
        )
      );
      $trid = null;
    }

    /* ---------- 3. Call WPML – and catch *everything* ------------- */
    try {
      $sitepress->set_element_language_details(
        $wp_post->ID,
        'post_' . $wp_post->post_type,
        $trid,              // may be null
        $lang,
        ( $lang === $default ? null : $default ),
        true
      );

      /* cache the fresh trid WPML may have created */
      $new_trid = (int) apply_filters(
        'wpml_element_trid',
        null,
        $wp_post->ID,
        'post_' . $wp_post->post_type
      );
      if ( $new_trid && ( $trid !== $new_trid ) ) {
        $this->trid_store[ $trid_identifier ] = $new_trid;
        $this->addToLog(
          sprintf(
            'WPML: set_element_language_details() OK – trid=%d lang=%s post=%d',
            $new_trid,
            $lang,
            $wp_post->ID
          )
        );
      }

    } catch ( \Throwable $e ) {

      /* ---------- 3-b  graceful fallback -------------------- */
      $this->addToLog(
        sprintf(
          'WPML error swallowed (post=%d lang=%s): %s',
          $wp_post->ID,
          $lang,
          $e->getMessage()
        )
      );

      // Do **not** re-throw – we deliberately keep going.
    }
  }



  public function getImportFile()
  {
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
        #$this->addToLog('file found lets go: ' . time());
      } else {
        $this->addToLog('file was missing ' . time());
      }
      if ($good_to_go) {
        $this->importFile = $file;
      }
    } else {
      #$this->addToLog('importfile already set: ' . time());
    }

    return $this->importFile;
  }

  public function renameImportFileTo($to)
  {
    if ($this->importFile != $to) {
      rename($this->importFile, $to);
      $this->importFile = $to;
    }
  }

  public function backupImportFile()
  {
    copy($this->getImportFile(), CASASYNC_CUR_UPLOAD_BASEDIR  . '/casawp/done/' . get_date_from_gmt('', 'Y_m_d_H_i_s') . '_completed.xml');
    return true;
  }

  public function findLangKey($lang, $array)
  {
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

  public function delete_orphan_casawp_properties() {
      global $wpdb;

      $orphans = $wpdb->get_col(
          "SELECT p.ID
             FROM {$wpdb->posts} p
        LEFT JOIN {$wpdb->postmeta} m
               ON m.post_id = p.ID AND m.meta_key = 'casawp_id'
            WHERE p.post_type   = 'casawp_property'
              AND p.post_status = 'publish'
              AND m.meta_id IS NULL"
      );

      foreach ( $orphans as $id ) {
          wp_delete_post( $id, true );
      }

      if ( $orphans ) {
          $this->addToLog( 'Removed ' . count( $orphans ) . ' orphan properties (no casawp_id).' );
      }
  }

  public function fillMissingTranslations($theoffers)
  {

    $languages = array();
    if (function_exists('icl_get_languages')) {
      $maybe_languages = icl_get_languages('skip_missing=0&orderby=code');
      // WPML sometimes returns false if it's not fully configured or no languages exist
      if (is_array($maybe_languages)) {
        $languages = $maybe_languages;
      }
    }

    $translations = array();
    foreach ($languages as $lang) {
      $translations[$lang['language_code']] = false;
    }

    foreach ($theoffers as $offerData) {
      $translations[$offerData['lang']] = $offerData;
    }

    $mainLangKey = $this->findLangKey($this->getMainLang(), $translations);
    if ($mainLangKey) {
      $carbon = $translations[$mainLangKey];
    } else {
      foreach ($translations as $translation) {
        if ($translation) {
          $carbon = $translation;
          break;
        }
      }
    }

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
                } elseif ($offer_media['type'] == 'plan') {
                  $copy['offer_medias'][$i]['title'] = 'Plan #' . $plan;
                  $plan++;
                } elseif ($offer_media['type'] == 'image' && $offer_media['caption'] != '') {
                  $copy['offer_medias'][$i]['caption'] = 'Bild #' . $img;
                  $img++;
                }
              }
            }
          } elseif ($language['language_code'] == 'fr') {
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
                } elseif ($offer_media['type'] == 'plan') {
                  $copy['offer_medias'][$i]['title'] = 'Plan #' . $plan;
                  $plan++;
                } elseif ($offer_media['type'] == 'image' && $offer_media['caption'] != '') {
                  $copy['offer_medias'][$i]['caption'] = 'Image #' . $img;
                  $img++;
                }
              }
            }
          } elseif ($language['language_code'] == 'en') {
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
                } elseif ($offer_media['type'] == 'plan') {
                  $copy['offer_medias'][$i]['title'] = 'Plan #' . $plan;
                  $plan++;
                } elseif ($offer_media['type'] == 'image' && $offer_media['caption'] != '') {
                  $copy['offer_medias'][$i]['caption'] = 'Image #' . $img;
                  $img++;
                }
              }
            }
          } elseif ($language['language_code'] == 'it') {
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
                } elseif ($offer_media['type'] == 'plan') {
                  $copy['offer_medias'][$i]['title'] = 'Piano #' . $plan;
                  $plan++;
                } elseif ($offer_media['type'] == 'image' && $offer_media['caption'] != '') {
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

  /**
   * Build a map: casawp_id → post_id for all IDs
   * we are about to process in *this* chunk.
   *
   * @param string[] $casawpIds
   * @return array   [ casawp_id => post_id ]
   */
  private function mapExistingPosts(array $casawpIds): array
  {

    if (empty($casawpIds)) {
      return [];
    }

    global $wpdb;

    $placeholders = implode(',', array_fill(0, count($casawpIds), '%s'));
    $sql          = "
        SELECT post_id, meta_value AS casawp_id
        FROM   {$wpdb->postmeta}
        WHERE  meta_key = 'casawp_id'
          AND  meta_value IN ( $placeholders )";

    /** @var stdClass[] $rows */
    $rows = $wpdb->get_results($wpdb->prepare($sql, ...$casawpIds));

    $map  = [];
    foreach ($rows as $r) {
      $map[$r->casawp_id] = (int) $r->post_id;
    }
    return $map;
  }


  /**
   * Replace the whole updateOffers() method with this one
   * -----------------------------------------------------
   * – handles duplicates created by posts that lost their casawp_id      –
   * – hash-skip, rank handling, WPML link etc. are unchanged             –
   */
  public function updateOffers( array $batched_file ) {

      global $wpdb;

      /* ---------- helpers -------------------------------------------- */

      // find a post that *looks* right (same slug + language) but has no casawp_id
      $find_orphan = function ( string $slug, string $lang ): ?int {
          global $wpdb;

          // match anything beginning with "<slug>-<lang>" optionally followed
          // by "-2", "-3", …  (important: esc_like() + trailing %)
          $like = $wpdb->esc_like( "{$slug}-{$lang}" ) . '%';

          $id = $wpdb->get_var( $wpdb->prepare(
              "SELECT  p.ID
                 FROM  {$wpdb->posts}      p
            LEFT JOIN {$wpdb->postmeta}    m
                       ON (m.post_id = p.ID AND m.meta_key = 'casawp_id')
                WHERE  p.post_type   = 'casawp_property'
                  AND  p.post_status = 'publish'
                  AND  p.post_name   LIKE %s
                  AND  m.meta_id     IS NULL          -- no casawp_id !
                ORDER BY p.ID DESC                     -- newest first
                LIMIT 1",
              $like
          ) );

          return $id ? (int) $id : null;
      };

      /* ---------- phase 0   build quick lookup ----------------------- */

      $neededIds   = [];
      foreach ( $batched_file as $prop ) {
          foreach ( $prop['offers'] as $of ) {
              $neededIds[] = $prop['exportproperty_id'] . $of['lang'];
          }
      }

      $existingMap = $this->mapExistingPosts( $neededIds );

      /* ---------- phase 1   walk every property ---------------------- */

      $found_posts = [];
      $curRank     = get_option( 'casawp_current_rank', 0 );
      $enable_hash = get_option( 'casawp_enable_import_hash', false );

      foreach ( $batched_file as $property ) {

          ++$curRank;

          /* collect language variants */
          $offers = [];
          foreach ( $property['offers'] as $of ) {
              $of['locality'] = $property['address']['locality'];
              $idx            = ( $of['lang'] === $this->getMainLang() ) ? 0 : count( $offers ) + 1;
              $offers[ $idx ] = $of;
          }
          if ( $this->hasWPML() ) {
              $offers = $this->fillMissingTranslations( $offers );
          }

          /* iterate over language variants */
          foreach ( $offers as $ofPos => $offer ) {

              $casawp_id = $property['exportproperty_id'] . $offer['lang'];

              /* ----- 1-A  fetch existing post ------------------------ */
              if ( isset( $existingMap[ $casawp_id ] ) ) {

                  $wp_post = get_post( $existingMap[ $casawp_id ], OBJECT, 'raw' );
                  $this->transcript[ $casawp_id ]['action'] = 'update';

              } else {

                  /* ----- 1-B  salvage an orphan -------------------- */
                  $slug      = $this->casawp_sanitize_title( $property['exportproperty_id'] );
                  $orphId    = $find_orphan( $slug, $offer['lang'] );

                  if ( $orphId ) {                       //  ➜ re-use it
                      $wp_post = get_post( $orphId, OBJECT, 'raw' );
                      update_post_meta( $wp_post->ID, 'casawp_id', $casawp_id );
                      $existingMap[ $casawp_id ] = $wp_post->ID;
                      $this->transcript[ $casawp_id ]['action'] = 'relink';

                  } else {                               //  ➜ insert new
                      $insert_id = wp_insert_post( [
                          'post_title'   => $offer['name'],
                          'post_content' => 'unsaved property',
                          'post_status'  => 'publish',
                          'post_type'    => 'casawp_property',
                          'menu_order'   => $curRank,
                          'post_name'    => $slug . '-' . $offer['lang'],
                          'post_date'    => (
                              $property['creation']
                              ? $property['creation']->format('Y-m-d H:i:s')
                              : $property['last_update']->format('Y-m-d H:i:s')
                          ),
                      ] );
                      update_post_meta( $insert_id, 'casawp_id', $casawp_id );
                      update_post_meta( $insert_id, 'is_active', true );

                      $wp_post = get_post( $insert_id, OBJECT, 'raw' );
                      $this->transcript[ $casawp_id ]['action'] = 'new';
                  }
              }

              /* ---------- NEW LINE: remember we handled this post ---- */
              $this->mark_touched( $wp_post->ID );

              /* keep order */
              if ( $wp_post->menu_order !== $curRank ) {
                  wp_update_post( [
                      'ID'         => $wp_post->ID,
                      'menu_order' => $curRank,
                  ], false, false );
              }
              $this->ranksort[ $wp_post->ID ] = $curRank;
              $found_posts[]                  = $wp_post->ID;

              /* ----- 1-C  hash-based skip -------------------------- */
              if ( $enable_hash ) {
                  $propHash  = md5( serialize( $property ) );
                  $mediaHash = md5( serialize( $offer['offer_medias'] ?? [] ) );
                  $oldProp   = get_post_meta( $wp_post->ID, 'last_import_hash', true );
                  $oldMedia  = get_post_meta( $wp_post->ID, 'last_media_hash',  true );

                  if ( $oldProp === $propHash && $oldMedia === $mediaHash ) {
                      update_post_meta( $wp_post->ID, 'is_active', true );
                      $this->transcript[ $casawp_id ]['action'] = 'skipped (hash match)';
                      continue;
                  }
              }

              /* ----- 1-D  heavy update ----------------------------- */
              $this->updateOffer( $casawp_id, $ofPos, $property, $offer, $wp_post );

              if ( $enable_hash ) {
                  update_post_meta( $wp_post->ID, 'last_import_hash', md5( serialize( $property ) ) );
                  update_post_meta( $wp_post->ID, 'last_media_hash',  md5( serialize( $offer['offer_medias'] ?? [] ) ) );
              }

              /* WPML linkage */
              $this->updateInsertWPMLconnection( $wp_post, $offer['lang'], $property['exportproperty_id'] );
          }
      }

      /* ---------- phase 2   housekeeping ---------------------------- */

      update_option( 'casawp_current_rank', $curRank );
     # $this->reactivate_properties( $found_posts );


    $meta_key_area = 'areaForOrder';
    $query = $wpdb->prepare("SELECT max( cast( meta_value as UNSIGNED ) ) FROM $wpdb->postmeta WHERE meta_key=%s", $meta_key_area);
    $max_area = $wpdb->get_var($query);
    $query = $wpdb->prepare("SELECT min( cast( meta_value as UNSIGNED ) ) FROM $wpdb->postmeta WHERE meta_key=%s", $meta_key_area);
    $min_area = $wpdb->get_var($query);

    $meta_key_rooms = 'number_of_rooms';
    $query = $wpdb->prepare("SELECT max( cast(meta_value as DECIMAL(10, 1) ) ) FROM $wpdb->postmeta WHERE meta_key=%s", $meta_key_rooms);
    $max_rooms = $wpdb->get_var($query);
    $query = $wpdb->prepare("SELECT min( cast( meta_value as DECIMAL(10, 1) ) ) FROM $wpdb->postmeta WHERE meta_key=%s", $meta_key_rooms);
    $min_rooms = $wpdb->get_var($query);

    update_option('casawp_archive_area_min', $min_area);
    update_option('casawp_archive_area_max', $max_area);
    update_option('casawp_archive_rooms_min', $min_rooms);
    update_option('casawp_archive_rooms_max', $max_rooms);


    //projects
    /* if ($xml->projects) {

      $found_posts = array();
      $sorti = 0;
      foreach ($xml->projects->project as $project) {
        $sorti++;

        $projectData = $this->project2Array($project);
        $projectDataLangified = $this->langifyProject($projectData);

        foreach ($projectDataLangified as $projectData) {
          $lang = $projectData['lang'];
          $casawp_id = $projectData['ref'] . $projectData['lang'];

          $the_query = new \WP_Query('post_type=casawp_project&suppress_filters=true&meta_key=casawp_id&meta_value=' . $casawp_id);
          $wp_post = false;
          while ($the_query->have_posts()) :
            $the_query->the_post();
            global $post;
            $wp_post = $post;
          endwhile;
          wp_reset_postdata();
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
          $this->updateInsertWPMLconnection($wp_post, $lang, 'project_' . $projectData['ref']);
        }
      }


      $projects_to_remove = get_posts(
        array(
          'suppress_filters' => true,
          'language' => 'ALL',
          'numberposts' =>  100,
          'exclude'     =>  $found_posts,
          'post_type'   =>  'casawp_project',
          'post_status' =>  'publish'
        )
      );
      foreach ($projects_to_remove as $prop_to_rm) {
        wp_trash_post($prop_to_rm->ID);
        $this->transcript['projects_removed'] = count($projects_to_remove);
      }
    }
     */
  }


  public function updateOffer($casawp_id, $offer_pos, $property, $offer, $wp_post)
  {

    $new_meta_data = array();
    $old_meta_data = array();


    $meta_values = get_post_meta($wp_post->ID, null, true);

    foreach ($meta_values as $key => $values) {
      $old_meta_data[$key] = maybe_unserialize($values[0]);
    }
    ksort($old_meta_data);

    $cleanPropertyData = $property;
    $curImportHash = md5(serialize($cleanPropertyData));

    if (!isset($old_meta_data['last_import_hash'])) {
      $old_meta_data['last_import_hash'] = 'no_hash';
    }

    //skip if is the same as before (accept if was trashed (reactivation))
    /* if ($wp_post->post_status == 'publish' && isset($old_meta_data['last_import_hash']) && !isset($_GET['force_all_properties'])) {
      if ($curImportHash == $old_meta_data['last_import_hash']) {
        $this->addToLog('skipped property: ' . $casawp_id);
        return 'skipped';
      }
    } */

    #$this->addToLog('beginn property update: [' . $casawp_id . ']' . time());
    #$this->addToLog(array($old_meta_data['last_import_hash'], $curImportHash));

    $new_meta_data['last_import_hash'] = $curImportHash;

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

    $curRank = $this->ranksort[$wp_post->ID];

    $site_timezone = wp_timezone();

    if ($property['creation']) {
      $post_date = clone $property['creation'];
      $post_date_gmt = clone $property['creation'];
    } elseif ($property['last_update']) {
      $post_date = clone $property['last_update'];
      $post_date_gmt = clone $property['last_update'];
    } else {
      $post_date = new \DateTime('now', $site_timezone);
      $post_date_gmt = new \DateTime('now', new \DateTimeZone('UTC'));
    }

    $post_date->setTimezone($site_timezone);
    $post_date_gmt->setTimezone(new \DateTimeZone('UTC'));

    $post_date_formatted = $post_date->format('Y-m-d H:i:s');
    $post_date_gmt_formatted = $post_date_gmt->format('Y-m-d H:i:s');

    $current_time = new \DateTime('now', $site_timezone);
    if ($post_date > $current_time) {
      $post_date_formatted = $current_time->format('Y-m-d H:i:s');
      $post_date_gmt_formatted = $current_time->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    $new_main_data = array(
      'ID'            => $wp_post->ID,
      'post_title'    => ($name ? $name : 'Objekt'),
      'post_content'  => $this->extractDescription($offer, $publisher_options),
      'post_status'   => 'publish',
      'post_type'     => 'casawp_property',
      'post_excerpt'  => $excerpt,
      'post_date'      => $post_date_formatted,
      'post_date_gmt'  => $post_date_gmt_formatted,
      'menu_order'   => $curRank
    );

    $old_main_data = array(
      'ID'            => $wp_post->ID,
      'post_title'    => $wp_post->post_title,
      'post_content'  => $wp_post->post_content,
      'post_status'   => $wp_post->post_status,
      'post_type'     => $wp_post->post_type,
      'post_excerpt'  => $wp_post->post_excerpt,
      'post_date'      => $wp_post->post_date,
      'post_date_gmt'  => $wp_post->post_date_gmt,
      'menu_order'   => $wp_post->menu_order
    );

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
    $new_meta_data['exportproperty_id']              = $property['exportproperty_id'];

    if (isset($property['zoneTypes']) && $property['zoneTypes']) {
      $new_meta_data['zoneTypes']              = $property['zoneTypes'];
    }

    if (!$new_meta_data['referenceId']) {
      echo '<div id="message" class="error">Warning! no referenceId found. for:' . $casawp_id . ' This could cause problems when sending inquiries</div>';
    }
    if (isset($property['organization'])) {
      $new_meta_data['seller_org_phone_central'] = $property['organization']['phone'];
      $new_meta_data['seller_org_legalname']                     = $property['organization']['displayName'];
      $new_meta_data['seller_org_brand']                         = $property['organization']['addition'];
      $new_meta_data['seller_org_customerid']                    = $property['organization']['id'];

      if (isset($property['organization']['postalAddress'])) {
        $new_meta_data['seller_org_address_country']               = $property['organization']['postalAddress']['country'];
        $new_meta_data['seller_org_address_locality']              = $property['organization']['postalAddress']['locality'];
        $new_meta_data['seller_org_address_region']                = $property['organization']['postalAddress']['region'];
        $new_meta_data['seller_org_address_postalcode']            = $property['organization']['postalAddress']['postal_code'];
        $new_meta_data['seller_org_address_postofficeboxnumber']   = $property['organization']['postalAddress']['post_office_box_number'];
        $new_meta_data['seller_org_address_streetaddress']         = $property['organization']['postalAddress']['street'] . ' ' . $property['organization']['postalAddress']['street_number'];
        $new_meta_data['seller_org_address_streetaddition']         = $property['organization']['postalAddress']['street_addition'];
      }
    }

    $personType = 'view';
    if (isset($property[$personType . 'Person']) && $property[$personType . 'Person']) {
      $prefix = 'seller_' . $personType . '_person_';
      $new_meta_data[$prefix . 'function']      = $property[$personType . 'Person']['function'];
      $new_meta_data[$prefix . 'givenname']     = $property[$personType . 'Person']['firstName'];
      $new_meta_data[$prefix . 'familyname']    = $property[$personType . 'Person']['lastName'];
      $new_meta_data[$prefix . 'email']         = $property[$personType . 'Person']['email'];
      $new_meta_data[$prefix . 'fax']           = $property[$personType . 'Person']['fax'];
      $new_meta_data[$prefix . 'phone_direct']  = $property[$personType . 'Person']['phone'];
      $new_meta_data[$prefix . 'phone_mobile']  = $property[$personType . 'Person']['mobile'];
      $new_meta_data[$prefix . 'gender']        = $property[$personType . 'Person']['gender'];
      $new_meta_data[$prefix . 'note']          = $property[$personType . 'Person']['note'];
    }

    $personType = 'inquiry';
    if (isset($property[$personType . 'Person']) && $property[$personType . 'Person']) {
      $prefix = 'seller_' . $personType . '_person_';
      $new_meta_data[$prefix . 'function']      = $property[$personType . 'Person']['function'];
      $new_meta_data[$prefix . 'givenname']     = $property[$personType . 'Person']['firstName'];
      $new_meta_data[$prefix . 'familyname']    = $property[$personType . 'Person']['lastName'];
      $new_meta_data[$prefix . 'email']         = $property[$personType . 'Person']['email'];
      $new_meta_data[$prefix . 'fax']           = $property[$personType . 'Person']['fax'];
      $new_meta_data[$prefix . 'phone_direct']  = $property[$personType . 'Person']['phone'];
      $new_meta_data[$prefix . 'phone_mobile']  = $property[$personType . 'Person']['mobile'];
      $new_meta_data[$prefix . 'gender']        = $property[$personType . 'Person']['gender'];
      $new_meta_data[$prefix . 'note']          = $property[$personType . 'Person']['note'];
    }

    $personType = 'visit';
    if (isset($property[$personType . 'Person']) && $property[$personType . 'Person']) {
      $prefix = 'seller_' . $personType . '_person_';
      $new_meta_data[$prefix . 'function']      = $property[$personType . 'Person']['function'];
      $new_meta_data[$prefix . 'givenname']     = $property[$personType . 'Person']['firstName'];
      $new_meta_data[$prefix . 'familyname']    = $property[$personType . 'Person']['lastName'];
      $new_meta_data[$prefix . 'email']         = $property[$personType . 'Person']['email'];
      $new_meta_data[$prefix . 'fax']           = $property[$personType . 'Person']['fax'];
      $new_meta_data[$prefix . 'phone_direct']  = $property[$personType . 'Person']['phone'];
      $new_meta_data[$prefix . 'phone_mobile']  = $property[$personType . 'Person']['mobile'];
      $new_meta_data[$prefix . 'gender']        = $property[$personType . 'Person']['gender'];
      $new_meta_data[$prefix . 'note']          = $property[$personType . 'Person']['note'];
    }

    $url = null;
    $the_urls = array();
    if (isset($offer['urls'])) {
      foreach ($offer['urls'] as $url) {
        $href = $url['url'];
        if (! (substr($href, 0, 7) === "http://" || substr($href, 0, 8) === "https://")) {
          $href = 'http://' . $href;
        }

        $label = (isset($url['label']) ? $url['label'] : false);
        $title = (isset($url['title']) ? $url['title'] : false);
        $type =  (isset($url['type'])  ? (string) $url['type'] : false);
        if ($type) {
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

    if (array_key_exists('price', $new_meta_data) && $new_meta_data['price'] !== "") {
      $tmp_price = $new_meta_data['price'];
    } elseif (array_key_exists('grossPrice', $new_meta_data) && $new_meta_data['grossPrice'] !== "") {
      $tmp_price = $new_meta_data['grossPrice'];
    } elseif (array_key_exists('netPrice', $new_meta_data) && $new_meta_data['netPrice'] !== "") {
      $tmp_price = $new_meta_data['netPrice'];
    } else {
      $tmp_price = 9999999999;
    }

    $new_meta_data['priceForOrder'] = $tmp_price;

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
      #$this->addToLog('_options_================HERE====================');
      #$this->addToLog('_options_' . print_r($custom_metas, true));
    }

    foreach ($custom_metas as $key => $value) {
      $new_meta_data['custom_option_' . $key] = $value;
      #$this->addToLog('custom_option_' . $key);
    }

    ksort($new_meta_data);


    if ($new_meta_data != $old_meta_data) {
      #$this->addToLog('updating metadata');
      foreach ($new_meta_data as $key => $value) {
        $newval = $value;

        if ($newval === true) {
          $newval = "1";
        }
        if (is_numeric($newval)) {
          $newval = (string) $newval;
        }
        if ($key == "floor" && $newval == 0) {
          $newval = "EG";
        }

        if (function_exists("casawp_unicode_dirty_replace") && !is_array($newval)) {
          $newval = casawp_unicode_dirty_replace($newval);
        }

        $new_meta_data[$key] = $newval;
      }
    }


    $new_main_data['meta_input'] = $new_meta_data;

    $main_data_changed = ($new_main_data != $old_main_data);
    $meta_data_changed = ($new_meta_data != $old_meta_data);

    if ($main_data_changed || $meta_data_changed) {
      if ($main_data_changed) {
        foreach ($old_main_data as $key => $value) {
          if ($new_main_data[$key] != $value) {
            $this->transcript[$casawp_id]['main_data'][$key]['from'] = $value;
            $this->transcript[$casawp_id]['main_data'][$key]['to'] = $new_main_data[$key];
            #$this->addToLog('updating main data (' . $key . '): ' . $value . ' -> ' . $new_main_data[$key]);
          }
        }
      }

      if (!$wp_post->post_name) {
        $new_main_data['post_name'] = $this->casawp_sanitize_title($casawp_id . '-' . $offer['name']);
      } else {
        $new_main_data['post_name'] = $wp_post->post_name;
      }

      wp_update_post($new_main_data);

      $keys_to_delete = array_diff(array_keys($old_meta_data), array_keys($new_meta_data));
      foreach ($keys_to_delete as $key) {
        if (!in_array($key, array('casawp_id', 'projectunit_id', 'projectunit_sort')) && strpos($key, '_') !== 0) {
          delete_post_meta($wp_post->ID, $key);
          $this->transcript[$casawp_id]['meta_data']['removed'][$key] = $old_meta_data[$key];
        }
      }
    }

    if (isset($property['property_categories'])) {
      #$this->addToLog('updating categories');
      $custom_categories = array();
      foreach ($publisher_options as $key => $values) {
        if (strpos($key, 'custom_category') === 0) {
          $parts = explode('_', $key);
          $sort = (isset($parts[2]) && is_numeric($parts[2]) ? $parts[2] : false);
          $slug = (isset($parts[3]) && $parts[3] == 'slug' ? true : false);
          $label = (isset($parts[3]) && $parts[3] == 'label' ? true : false);
          if (!$values[0] || !$sort) {
          } elseif ($slug) {
            $custom_categories[$sort]['slug'] = $values[0];
          } elseif ($label) {
            $custom_categories[$sort]['label'] = $values[0];
          }
        }
      }

      $this->setOfferCategories($wp_post, $property['property_categories'], $custom_categories, $casawp_id);
    }

    #$this->addToLog('updating custom regions');
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

    #$this->addToLog('updating features');
    $this->setOfferFeatures($wp_post, $property['features'], $casawp_id);

    #$this->addToLog('updating utilities');
    $this->setOfferUtilities($wp_post, $property['property_utilities'], $casawp_id);

    #$this->addToLog('updating salestypes');
    $this->setOfferSalestype($wp_post, $property['type'], $casawp_id);

    #$this->addToLog('updating availabilities');
    $this->setOfferAvailability($wp_post, $property['availability'], $casawp_id);

    #$this->addToLog('updating localities');
    $this->setOfferLocalities($wp_post, $property['address'], $casawp_id);

    #$this->addToLog('updating attachments');
    $this->setOfferAttachments($offer['offer_medias'], $wp_post, $property['exportproperty_id'], $casawp_id, $property);

    #$this->addToLog('finish property update: [' . $casawp_id . ']' . time());
  }

  public function casawp_sanitize_title($result)
  {
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

  public function extractDescription($offer, $publisher_options = null)
  {
    $descriptionDatas = $offer['descriptions'];

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

  public function setcasawpCategoryTerm($term_slug, $label = false)
  {
    $label = (!$label ? $term_slug : $label);
    $term = get_term_by('slug', $term_slug, 'casawp_category', OBJECT, 'raw');
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

  public function setcasawpRegionTerm($term_slug, $label = false)
  {
    $label = (!$label ? $term_slug : $label);
    $term = get_term_by('slug', $term_slug, 'casawp_region', OBJECT, 'raw');
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
      #$this->addToLog('inserting region ' . $label);
      return $id;
    }
  }

  public function setcasawpFeatureTerm($term_slug, $label = false)
  {
    $label = (!$label ? $term_slug : $label);
    $term = get_term_by('slug', $term_slug, 'casawp_feature', OBJECT, 'raw');
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

  public function setcasawpUtilityTerm($term_slug, $label = false)
  {
    $label = (!$label ? $term_slug : $label);
    $term = get_term_by('slug', $term_slug, 'casawp_utility', OBJECT, 'raw');
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

  public function casawpUploadAttachmentFromGateway($property_id, $fileurl)
  {
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
      $pathWithoutExtension = str_replace('.' . $file_parts['extension'], '', $path);

      $query    = isset($parsed_url['query']) ? '?' . $parsed_url['query'] : '';
      $fragment = isset($parsed_url['fragment']) ? '#' . $parsed_url['fragment'] : '';

      $converted = $scheme . $user . $pass . $host . $port . $pathWithoutExtension . str_replace(['?', '&', '#', '='], '-', $query . $fragment) . '.' . $extension;

      $filename = '/casawp/import/attachment/externalsync/' . $property_id . '/' . basename($converted);
    } else {
      $filename = '/casawp/import/attachment/externalsync/' . $property_id . '/' . basename($fileurl);
    }

    $file_parts = pathinfo($filename);
    if (!isset($file_parts['extension'])) {
      $filename = $filename . '.jpg';
    }

    $full_path = CASASYNC_CUR_UPLOAD_BASEDIR . $filename;

    $directory = dirname($full_path);
    if (!is_dir($directory)) {
      if (!mkdir($directory, 0755, true)) {
        return false;
      }
    }

    if (!is_file($full_path)) {
      if (!isset($this->transcript['attachments'][$property_id]["uploaded_from_gateway"])) {
        $this->transcript['attachments'][$property_id]["uploaded_from_gateway"] = array();
      }
      $this->transcript['attachments'][$property_id]["uploaded_from_gateway"][] = $filename;

      if (strpos($fileurl, '://')) {
        $could_copy = copy(urldecode($fileurl), $full_path);
      } else {
        $could_copy = copy($fileurl, $full_path);
      }

      if (!$could_copy) {
        $this->transcript['attachments'][$property_id]["uploaded_from_gateway"][] = 'FAILED: ' . $filename;
        return false;
      }
    }

    return $filename;
  }

  public function casawpUploadAttachment($the_mediaitem, $post_id, $property_id)
  {
    if ($the_mediaitem['file']) {
      $filename = '/casawp/import/attachment/' . $the_mediaitem['file'];
    } elseif ($the_mediaitem['url']) { //external
      if ($the_mediaitem['type'] === 'image' && get_option('casawp_use_casagateway_cdn', false)) {
        $filename = $the_mediaitem['url'];
      } else {
        $filename = $this->casawpUploadAttachmentFromGateway($property_id, $the_mediaitem['url']);
      }
    } else {
      $filename = false;
    }

    if ($filename && (is_file(CASASYNC_CUR_UPLOAD_BASEDIR . $filename) || get_option('casawp_use_casagateway_cdn', false))) {

      $wp_filetype = wp_check_filetype(basename($filename), null);
      $guid = CASASYNC_CUR_UPLOAD_BASEURL . $filename;
      if ($the_mediaitem['type'] === 'image' && get_option('casawp_use_casagateway_cdn', false)) {
        $guid = $filename;
      }
      $attachment = array(
        'guid'           => $guid,
        'post_mime_type' => $wp_filetype['type'],
        'post_title'     => ($the_mediaitem['title'] ? $the_mediaitem['title'] : basename($filename)),
        'post_name'      => sanitize_title_with_dashes($guid, '', 'save'),
        'post_content'   => '',
        'post_excerpt'   => $the_mediaitem['caption'],
        'post_status'    => 'inherit',
        'menu_order'     => $the_mediaitem['order']
      );

      $attach_id = wp_insert_attachment($attachment, CASASYNC_CUR_UPLOAD_BASEDIR . $filename, $post_id);

      require_once(ABSPATH . 'wp-admin/includes/image.php');
      $attach_data = wp_generate_attachment_metadata($attach_id, CASASYNC_CUR_UPLOAD_BASEDIR . $filename);
      wp_update_attachment_metadata($attach_id, $attach_data);

      $term = get_term_by('slug', $the_mediaitem['type'], 'casawp_attachment_type');
      if ($term) {
        $term_id = $term->term_id;
        wp_set_post_terms($attach_id,  array($term_id), 'casawp_attachment_type');
      }

      update_post_meta($attach_id, '_wp_attachment_image_alt', $the_mediaitem['alt']);

      update_post_meta($attach_id, '_origin', ($the_mediaitem['file'] ? $the_mediaitem['file'] : $the_mediaitem['url']));

      return $attach_id;
    } else {
      return $filename . " could not be found!";
    }
  }

  public function integratedOffersToArray($integratedOffers)
  {
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

  public function setOfferAttachments($offer_medias, $wp_post, $property_id, $casawp_id, $property)
  {

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
            'title'   => ($offer_media['title'] ? $offer_media['title'] : basename($media['original_file'])),
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
          'terms'    => array('image', 'plan', 'document', 'offer-logo', 'sales-brochure')
        )
      )
    );
    $attachments = get_posts($args);
    if ($attachments) {
      foreach ($attachments as $attachment) {
        $wp_casawp_attachments[] = $attachment;
      }
    }

    if (isset($the_casawp_attachments)) {
      $wp_casawp_attachments_to_remove = $wp_casawp_attachments;
      $dup_checker_arr = [];
      foreach ($the_casawp_attachments as $the_mediaitem) {
        $existing = false;
        $existing_attachment = array();
        foreach ($wp_casawp_attachments as $key => $wp_mediaitem) {
          $attachment_customfields = get_post_custom($wp_mediaitem->ID);
          $original_filename = (array_key_exists('_origin', $attachment_customfields) ? $attachment_customfields['_origin'][0] : '');
          if (in_array($original_filename, $dup_checker_arr)) {
            #$this->addToLog('found duplicate for id: ' . $wp_mediaitem->ID . ' orig: ' . $original_filename);
          }
          $dup_checker_arr[] = $original_filename;

          $alt = '';
          if (
            $original_filename == ($the_mediaitem['file'] ? $the_mediaitem['file'] : $the_mediaitem['url'])
            ||
            str_replace('%3D', '=', str_replace('%3F', '?', $original_filename)) == ($the_mediaitem['file'] ? $the_mediaitem['file'] : $the_mediaitem['url'])
          ) {
            $existing = true;
            #$this->addToLog('updating attachment ' . $wp_mediaitem->ID);

            unset($wp_casawp_attachments_to_remove[$key]);

            $types = wp_get_post_terms($wp_mediaitem->ID, 'casawp_attachment_type');
            if (array_key_exists(0, $types)) {
              $typeslug = $types[0]->slug;
              $alt = get_post_meta($wp_mediaitem->ID, '_wp_attachment_image_alt', true);
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

            if ($existing_attachment != $the_mediaitem) {
              $changed = true;
              $this->transcript[$casawp_id]['attachments']["updated"] = 1;
              if (
                $existing_attachment['caption'] != $the_mediaitem['caption']
                || $existing_attachment['title'] != $the_mediaitem['title']
                || $existing_attachment['order'] != $the_mediaitem['order']
              ) {
                $att['post_excerpt'] = $the_mediaitem['caption'];
                $att['post_title']   = ($the_mediaitem['title'] ? $the_mediaitem['title'] : basename($filename));
                $att['ID']           = $wp_mediaitem->ID;
                $att['menu_order']   = $the_mediaitem['order'];
                $insert_id           = wp_update_post($att);
              }

              if ($existing_attachment['type'] != $the_mediaitem['type']) {
                $term = get_term_by('slug', $the_mediaitem['type'], 'casawp_attachment_type');
                if ($term) {
                  $term_id = $term->term_id;
                  wp_set_post_terms($wp_mediaitem->ID,  array($term_id), 'casawp_attachment_type');
                }
              }

              if ($alt != $the_mediaitem['alt']) {
                update_post_meta($wp_mediaitem->ID, '_wp_attachment_image_alt', $the_mediaitem['alt']);
              }
            }
          }
        }

        if (!$existing) {
          if (isset($wp_mediaitem->ID)) {
            #$this->addToLog('creating new attachment ' . $wp_mediaitem->ID);
          }
          $new_id = $this->casawpUploadAttachment($the_mediaitem, $wp_post->ID, $property_id);
          if (is_int($new_id)) {
            $this->transcript[$casawp_id]['attachments']["created"] = $the_mediaitem['file'];
          } else {
            $this->transcript[$casawp_id]['attachments']["failed_to_create"] = $new_id;
          }
        }

        if (! get_option('casawp_use_casagateway_cdn', false) && isset($the_mediaitem['url'])) {
          $this->casawpUploadAttachmentFromGateway($property_id, $the_mediaitem['url']);
        }
      }

      if ($wp_casawp_attachments_to_remove) {
        #$this->addToLog('removing ' . count($wp_casawp_attachments_to_remove) . ' attachments');
      }
      foreach ($wp_casawp_attachments_to_remove as $attachment) {
        #$this->addToLog('removing ' . $attachment->ID);
        $this->transcript[$casawp_id]['attachments']["removed"] = $attachment;
        wp_delete_attachment($attachment->ID);
      }

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
            'terms'    => array('image', 'plan', 'document', 'offer-logo', 'sales-brochure')
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
              $cur_thumbnail_id = get_post_thumbnail_id($wp_post->ID);
              if ($cur_thumbnail_id != $wp_mediaitem->ID) {
                set_post_thumbnail($wp_post->ID, $wp_mediaitem->ID);
                $this->transcript[$casawp_id]['attachments']["featured_image_set"] = 1;
                break;
              }
            }
          }
        }
      }
    }
  }

  public function setOfferSalestype($wp_post, $salestype, $casawp_id)
  {

    $new_term_id = null;
    $old_term_id = null;

    if ($salestype) {
      $salestype_slug = sanitize_title($salestype);
      $salestype_label = sanitize_text_field($salestype);

      $term = get_term_by('slug', $salestype_slug, 'casawp_salestype');
      if (!$term || is_wp_error($term)) {
        $inserted_term = wp_insert_term($salestype_label, 'casawp_salestype', array('slug' => $salestype_slug));
        if (is_wp_error($inserted_term)) {
          #$this->addToLog('Error inserting salestype term "' . $salestype_label . '": ' . $inserted_term->get_error_message());
          $new_term_id = null;
        } else {
          $new_term_id = $inserted_term['term_id'];
          $term = get_term($new_term_id, 'casawp_salestype');
          if (is_wp_error($term)) {
            #$this->addToLog('Error retrieving term after creation: ' . $term->get_error_message());
            $new_term_id = null;
          }
        }
      } else {
        $new_term_id = $term->term_id;
      }
    }

    $current_terms = wp_get_object_terms($wp_post->ID, 'casawp_salestype', array('fields' => 'ids'));
    if (is_wp_error($current_terms)) {
      #$this->addToLog('Error retrieving current salestype terms: ' . $current_terms->get_error_message());
      $current_terms = array();
    }

    if (!empty($current_terms)) {
      $old_term_id = $current_terms[0];
    }

    if ($old_term_id !== $new_term_id) {
      $old_term_name = 'none';
      if ($old_term_id) {
        $old_term = get_term($old_term_id, 'casawp_salestype');
        if (!is_wp_error($old_term)) {
          $old_term_name = $old_term->name;
        }
      }

      $new_term_name = 'none';
      if ($new_term_id) {
        $new_term = get_term($new_term_id, 'casawp_salestype');
        if (!is_wp_error($new_term)) {
          $new_term_name = $new_term->name;
        }
      }

      $this->transcript[$casawp_id]['salestype']['from'] = $old_term_name;
      $this->transcript[$casawp_id]['salestype']['to'] = $new_term_name;

      $result = wp_set_object_terms($wp_post->ID, $new_term_id, 'casawp_salestype');
      if (is_wp_error($result)) {
        #$this->addToLog('Error assigning salestype term to post: ' . $result->get_error_message());
      } else {
        #$this->addToLog('Salestype updated from "' . $old_term_name . '" to "' . $new_term_name . '".');
      }
    } else {
      #$this->addToLog('No salestype changes detected.');
    }
  }

  public function setOfferAvailability($wp_post, $availability, $casawp_id)
  {

    $allowed_availabilities = array(
      'active',
      'taken',
      'reserved',
      'private',
      'reference'
    );

    if ($availability === 'available') {
      $availability = 'active';
    }

    if (!in_array($availability, $allowed_availabilities)) {
      $availability = null;
    }

    $new_term_id = null;
    $old_term_id = null;

    if ($availability) {
      $availability_slug = sanitize_title($availability);
      $availability_label = sanitize_text_field($availability);

      $term = get_term_by('slug', $availability_slug, 'casawp_availability');
      if (!$term || is_wp_error($term)) {
        $inserted_term = wp_insert_term($availability_label, 'casawp_availability', array('slug' => $availability_slug));
        if (is_wp_error($inserted_term)) {
          #$this->addToLog('Error inserting availability term "' . $availability_label . '": ' . $inserted_term->get_error_message());
          $new_term_id = null;
        } else {
          $new_term_id = $inserted_term['term_id'];
          $term = get_term($new_term_id, 'casawp_availability');
          if (is_wp_error($term)) {
            #$this->addToLog('Error retrieving term after creation: ' . $term->get_error_message());
            $new_term_id = null;
          }
        }
      } else {
        $new_term_id = $term->term_id;
      }
    }

    $current_terms = wp_get_object_terms($wp_post->ID, 'casawp_availability', array('fields' => 'ids'));
    if (is_wp_error($current_terms)) {
      #$this->addToLog('Error retrieving current availability terms: ' . $current_terms->get_error_message());
      $current_terms = array();
    }

    if (!empty($current_terms)) {
      $old_term_id = $current_terms[0];
    }

    if ($old_term_id !== $new_term_id) {
      $old_term_name = 'none';
      if ($old_term_id) {
        $old_term = get_term($old_term_id, 'casawp_availability');
        if (!is_wp_error($old_term)) {
          $old_term_name = $old_term->name;
        }
      }

      $new_term_name = 'none';
      if ($new_term_id) {
        $new_term = get_term($new_term_id, 'casawp_availability');
        if (!is_wp_error($new_term)) {
          $new_term_name = $new_term->name;
        }
      }

      $this->transcript[$casawp_id]['availability']['from'] = $old_term_name;
      $this->transcript[$casawp_id]['availability']['to'] = $new_term_name;

      $result = wp_set_object_terms($wp_post->ID, $new_term_id, 'casawp_availability');
      if (is_wp_error($result)) {
        #$this->addToLog('Error assigning availability term to post: ' . $result->get_error_message());
      } else {
        #$this->addToLog('Availability updated from "' . $old_term_name . '" to "' . $new_term_name . '".');
      }
    } else {
      #$this->addToLog('No availability changes detected.');
    }
  }

  public function setOfferLocalities($wp_post, $address, $casawp_id)
  {

    $country  = strtoupper($address['country']);
    $region   = $address['region'];
    $locality = $address['locality'];

    $term_ids = array();
    $parent_term_ids = array();
    $region_slug = '';

    $sanitize_slug = function ($prefix, $name) {
      return sanitize_title($prefix . '_' . $name);
    };

    $sanitize_name = function ($name) {
      return sanitize_text_field($name);
    };

    if ($country) {
      $country_slug = $sanitize_slug('country', $country);
      $country_label = $sanitize_name($country);

      $term = $this->ensureTermExists('casawp_location', $country_slug, $country_label, 0);
      if ($term) {
        $term_ids[] = $term->term_id;
        $parent_term_ids[$country_slug] = $term->term_id;
      }
    }

    if ($region) {
      $region_slug = $sanitize_slug('region', $region);
      $region_label = $sanitize_name($region);
      $parent_id = isset($parent_term_ids[$country_slug]) ? $parent_term_ids[$country_slug] : 0;

      $term = $this->ensureTermExists('casawp_location', $region_slug, $region_label, $parent_id);
      if ($term) {
        $term_ids[] = $term->term_id;
        $parent_term_ids[$region_slug] = $term->term_id;
      }
    }

    if ($locality) {
      $locality_slug = $sanitize_slug('locality', $locality);
      $locality_label = $sanitize_name($locality);
      $parent_id = isset($parent_term_ids[$region_slug]) ? $parent_term_ids[$region_slug] : (isset($parent_term_ids[$country_slug]) ? $parent_term_ids[$country_slug] : 0);

      $term = $this->ensureTermExists('casawp_location', $locality_slug, $locality_label, $parent_id);
      if ($term) {
        $term_ids[] = $term->term_id;
        $parent_term_ids[$locality_slug] = $term->term_id;
      }
    }

    $term_ids = array_unique($term_ids);
    asort($term_ids);
    $term_ids = array_values($term_ids);

    $old_terms = wp_get_object_terms($wp_post->ID, 'casawp_location', array('fields' => 'ids'));
    if (is_wp_error($old_terms)) {
      $old_terms = array();
    }
    asort($old_terms);
    $old_terms = array_values($old_terms);

    if ($term_ids != $old_terms) {
      $this->transcript[$casawp_id]['locations'][] = array('from' => $old_terms, 'to' => $term_ids);

      $result = wp_set_object_terms($wp_post->ID, $term_ids, 'casawp_location');
      if (is_wp_error($result)) {
        #$this->addToLog('Error assigning location terms to post: ' . $result->get_error_message());
      } else {
        if (defined('WPSEO_VERSION') && isset($parent_term_ids[$locality_slug])) {
          $primary_term_id = $parent_term_ids[$locality_slug];
          $yoast_primary_term = new \WPSEO_Primary_Term('casawp_location', $wp_post->ID);
          $yoast_primary_term->set_primary_term($primary_term_id);
        }
      }
    } else {
      #$this->addToLog('No location changes detected.');
    }
  }

  private function ensureTermExists($taxonomy, $slug, $label, $parent_id = 0)
  {
    $term = get_term_by('slug', $slug, $taxonomy);
    if (!$term || is_wp_error($term)) {
      $args = array(
        'slug'   => $slug,
        'parent' => $parent_id
      );
      $inserted_term = wp_insert_term($label, $taxonomy, $args);
      if (is_wp_error($inserted_term)) {
        #$this->addToLog('Error inserting term "' . $label . '": ' . $inserted_term->get_error_message());
        return null;
      } else {
        $term_id = $inserted_term['term_id'];
        $this->transcript['new_locations'][] = array($label, $slug);
        $term = get_term($term_id, $taxonomy);
        if (is_wp_error($term)) {
          #$this->addToLog('Error retrieving term after creation: ' . $term->get_error_message());
          return null;
        }
        return $term;
      }
    } else {
      return $term;
    }
  }

  public function setOfferCategories($wp_post, $categories, $customCategories, $casawp_id)
  {

    $old_categories = wp_get_object_terms($wp_post->ID, 'casawp_category', array('fields' => 'slugs'));
    if (is_wp_error($old_categories)) {
      $old_categories = array();
    }

    $new_categories = array();

    if (!empty($categories)) {
      $new_categories = array_merge($new_categories, $categories);
    }

    $custom_categorylabels = array();
    if (!empty($customCategories)) {
      foreach ($customCategories as $custom_category) {
        $slug = 'custom_' . $custom_category['slug'];
        $label = isset($custom_category['label']) ? $custom_category['label'] : $custom_category['slug'];
        $new_categories[] = $slug;
        $custom_categorylabels[$slug] = $label;
      }
    }

    if (array_diff($new_categories, $old_categories) || array_diff($old_categories, $new_categories)) {
      $slugs_to_add = array_diff($new_categories, $old_categories);
      $slugs_to_remove = array_diff($old_categories, $new_categories);

      $this->transcript[$casawp_id]['categories_changed']['removed_category'] = $slugs_to_remove;
      $this->transcript[$casawp_id]['categories_changed']['added_category'] = $slugs_to_add;

      foreach ($slugs_to_add as $new_term_slug) {
        $label = isset($custom_categorylabels[$new_term_slug]) ? $custom_categorylabels[$new_term_slug] : $new_term_slug;
        if (!term_exists($new_term_slug, 'casawp_category')) {
          wp_insert_term($label, 'casawp_category', array('slug' => $new_term_slug));
        }
      }
      wp_set_object_terms($wp_post->ID, $new_categories, 'casawp_category', false);
    }
  }

  public function setOfferFeatures($wp_post, $features, $casawp_id)
  {
    $old_features = wp_get_object_terms($wp_post->ID, 'casawp_feature', array('fields' => 'slugs'));
    if (is_wp_error($old_features)) {
      $old_features = array();
    }

    $new_features = !empty($features) ? $features : array();

    if (array_diff($new_features, $old_features) || array_diff($old_features, $new_features)) {
      $slugs_to_add = array_diff($new_features, $old_features);
      $slugs_to_remove = array_diff($old_features, $new_features);

      $this->transcript[$casawp_id]['features_changed']['removed_feature'] = $slugs_to_remove;
      $this->transcript[$casawp_id]['features_changed']['added_feature'] = $slugs_to_add;

      $term_ids = array();

      foreach ($new_features as $feature_slug) {
        $term = get_term_by('slug', $feature_slug, 'casawp_feature');
        if (!$term) {
          $label = $feature_slug;
          $inserted_term = wp_insert_term($feature_slug, 'casawp_feature', array('slug' => $feature_slug));
          if (is_wp_error($inserted_term)) {
            #$this->addToLog('Error inserting feature term "' . $label . '": ' . $inserted_term->get_error_message());
            continue;
          } else {
            $term_id = $inserted_term['term_id'];
            #$this->addToLog('Inserted new feature term "' . $label . '" with ID ' . $term_id);
          }
        } else {
          $term_id = $term->term_id;
          #$this->addToLog('Feature term already exists: "' . $term->name . '" with ID ' . $term_id);
        }
        $term_ids[] = (int) $term_id;
      }

      if (!empty($term_ids)) {
        $result = wp_set_object_terms($wp_post->ID, $term_ids, 'casawp_feature');
        if (is_wp_error($result)) {
          #$this->addToLog('Error assigning features to post: ' . $result->get_error_message());
        } else {
          #$this->addToLog('Assigned features to post successfully.');
        }
      } else {
        wp_set_object_terms($wp_post->ID, array(), 'casawp_feature');
        #$this->addToLog('Removed all features from post.');
      }
    } else {
      #$this->addToLog('No feature changes detected.');
    }
  }

  public function setOfferUtilities($wp_post, $utilities, $casawp_id)
  {

    $old_utilities = wp_get_object_terms($wp_post->ID, 'casawp_utility', array('fields' => 'slugs'));
    if (is_wp_error($old_utilities)) {
      $old_utilities = array();
    }

    $new_utilities = !empty($utilities) ? $utilities : array();

    if (array_diff($new_utilities, $old_utilities) || array_diff($old_utilities, $new_utilities)) {
      $slugs_to_add = array_diff($new_utilities, $old_utilities);
      $slugs_to_remove = array_diff($old_utilities, $new_utilities);

      $this->transcript[$casawp_id]['utilities_changed']['removed_utility'] = $slugs_to_remove;
      $this->transcript[$casawp_id]['utilities_changed']['added_utility'] = $slugs_to_add;

      $term_ids = array();

      foreach ($new_utilities as $utility_slug) {
        $term = get_term_by('slug', $utility_slug, 'casawp_utility');
        if (!$term) {
          $label = ucwords(str_replace('-', ' ', $utility_slug));
          $inserted_term = wp_insert_term($label, 'casawp_utility', array('slug' => $utility_slug));
          if (is_wp_error($inserted_term)) {
            #$this->addToLog('Error inserting utility term "' . $label . '": ' . $inserted_term->get_error_message());
            continue;
          } else {
            $term_id = $inserted_term['term_id'];
            #$this->addToLog('Inserted new utility term "' . $label . '" with ID ' . $term_id);
          }
        } else {
          $term_id = $term->term_id;
          #$this->addToLog('Utility term already exists: "' . $term->name . '" with ID ' . $term_id);
        }
        $term_ids[] = (int) $term_id;
      }

      if (!empty($term_ids)) {
        $result = wp_set_object_terms($wp_post->ID, $term_ids, 'casawp_utility');
        if (is_wp_error($result)) {
          #$this->addToLog('Error assigning utilities to post: ' . $result->get_error_message());
        } else {
          #$this->addToLog('Assigned utilities to post successfully.');
        }
      } else {
        wp_set_object_terms($wp_post->ID, array(), 'casawp_utility');
        #$this->addToLog('Removed all utilities from post.');
      }
    } else {
      #$this->addToLog('No utility changes detected.');
    }
  }

  public function setOfferRegions($wp_post, $terms, $casawp_id)
  {

    $old_terms = wp_get_object_terms($wp_post->ID, 'casawp_region', array('fields' => 'slugs'));
    if (is_wp_error($old_terms)) {
      $old_terms = array();
    }

    $new_terms = array();
    $custom_labels = array();

    if (!empty($terms)) {
      foreach ($terms as $term) {
        $slug = $term['slug'];
        $label = isset($term['label']) ? $term['label'] : $slug;
        $new_terms[] = $slug;
        $custom_labels[$slug] = $label;
      }
    }

    if (array_diff($new_terms, $old_terms) || array_diff($old_terms, $new_terms)) {
      $slugs_to_add = array_diff($new_terms, $old_terms);
      $slugs_to_remove = array_diff($old_terms, $new_terms);

      $this->transcript[$casawp_id]['regions_changed']['removed_region'] = $slugs_to_remove;
      $this->transcript[$casawp_id]['regions_changed']['added_region'] = $slugs_to_add;

      $term_ids = array();

      foreach ($new_terms as $term_slug) {
        $label = isset($custom_labels[$term_slug]) ? $custom_labels[$term_slug] : $term_slug;

        $term = get_term_by('slug', $term_slug, 'casawp_region');
        if (!$term) {
          $inserted_term = wp_insert_term($label, 'casawp_region', array('slug' => $term_slug));
          if (is_wp_error($inserted_term)) {
            #$this->addToLog('Error inserting term "' . $label . '": ' . $inserted_term->get_error_message());
            continue;
          } else {
            $term_id = $inserted_term['term_id'];
            #$this->addToLog('Inserted new term "' . $label . '" with ID ' . $term_id);
          }
        } else {
          $term_id = $term->term_id;
          #$this->addToLog('Term already exists: "' . $term->name . '" with ID ' . $term_id);
        }
        $term_ids[] = (int) $term_id;
      }

      if (!empty($term_ids)) {
        $result = wp_set_object_terms($wp_post->ID, $term_ids, 'casawp_region');
        if (is_wp_error($result)) {
          #$this->addToLog('Error assigning terms to post: ' . $result->get_error_message());
        } else {
          #$this->addToLog('Assigned regions to post successfully.');
        }
      } else {
        wp_set_object_terms($wp_post->ID, array(), 'casawp_region');
        #$this->addToLog('Removed all regions from post.');
      }
    } else {
      #$this->addToLog('No region changes detected.');
    }
  }

  public function cleanup_log_files()
  {
    $log_dir = CASASYNC_CUR_UPLOAD_BASEDIR . '/casawp/logs';

    if (!is_dir($log_dir)) {
      $this->addToLog('Log directory does not exist. Skipping cleanup.');
      return;
    }

    $files = glob($log_dir . '/*.log');

    if (!$files) {
      #$this->addToLog('No log files found for cleanup.');
      return;
    }

    $current_time = time();
    $six_months_in_seconds = 2 * MONTH_IN_SECONDS;

    foreach ($files as $file) {
      $filename = basename($file, '.log');

      if (!preg_match('/^\d{6}$/', $filename)) {
        continue;
      }

      $file_time = strtotime("{$filename}01");

      if ($file_time === false) {
        continue;
      }

      $age = $current_time - $file_time;

      if ($age > $six_months_in_seconds) {
        if (unlink($file)) {
          #$this->addToLog("Deleted old log file: {$filename}.log");
        } else {
          #$this->addToLog("Failed to delete log file: {$filename}.log");
        }
      }
    }
  }

  public function addToLog($transcript)
  {
    $dir = CASASYNC_CUR_UPLOAD_BASEDIR  . '/casawp/logs';
    if (!file_exists($dir)) {
      mkdir($dir, 0777, true);
    }
    file_put_contents($dir . "/" . get_date_from_gmt('', 'Ym') . '.log', "\n" . json_encode(array(get_date_from_gmt('', 'Y-m-d H:i') => $transcript)), FILE_APPEND);
  }

  public function addToTranscript($msg)
  {
    $this->transcript[] = $msg;
  }

  public function property2Array($property_xml)
  {

    $propertydata['address'] = array(
      'country'       => ($property_xml->address->country->__toString() ?: ''),
      'locality'      => ($property_xml->address->locality->__toString() ?: ''),
      'region'        => ($property_xml->address->region->__toString() ?: ''),
      'postal_code'   => ($property_xml->address->postalCode->__toString() ?: ''),
      'street'        => ($property_xml->address->street->__toString() ?: ''),
      'streetNumber' => ($property_xml->address->streetNumber->__toString() ?: ''),
      'streetAddition' => ($property_xml->address->streetAddition->__toString() ?: ''),
      'subunit'       => ($property_xml->address->subunit->__toString() ?: ''),
      'lng'           => ($property_xml->address->geo ? $property_xml->address->geo->longitude->__toString() : ''),
      'lat'           => ($property_xml->address->geo ? $property_xml->address->geo->latitude->__toString() : ''),
    );

    $creation = isset($property_xml->softwareInformation->creation)
      ? new \DateTime($property_xml->softwareInformation->creation->__toString())
      : null;

    $last_update = isset($property_xml->softwareInformation->lastUpdate)
      ? new \DateTime($property_xml->softwareInformation->lastUpdate->__toString())
      : null;

    $propertydata['creation'] = $creation;
    $propertydata['last_update'] = $last_update;
    $propertydata['exportproperty_id'] = (isset($property_xml['id']) ? $property_xml['id']->__toString() : '');
    $propertydata['referenceId'] = (isset($property_xml->referenceId) ? $property_xml->referenceId->__toString() : '');
    $propertydata['visualReferenceId'] = (isset($property_xml->visualReferenceId) ? $property_xml->visualReferenceId->__toString() : '');
    $propertydata['availability'] = ($property_xml->availability->__toString() ? $property_xml->availability->__toString() : 'available');
    $propertydata['price_currency'] = $property_xml->priceCurrency->__toString();
    $propertydata['price'] = $property_xml->price->__toString();
    $propertydata['price_property_segment'] = (!$property_xml->price['propertysegment'] ?: str_replace('2', '', $property_xml->price['propertysegment']->__toString()));
    if ($property_xml->priceRange) {
      $propertydata['price_range_from'] = $property_xml->priceRange->from->__toString();
      $propertydata['price_range_to'] = $property_xml->priceRange->to->__toString();
    } else {
      $propertydata['price_range_from'] = null;
      $propertydata['price_range_to'] = null;
    }
    $propertydata['net_price'] = $property_xml->netPrice->__toString();
    $propertydata['net_price_time_segment'] = ($property_xml->netPrice['timesegment'] ? strtolower($property_xml->netPrice['timesegment']->__toString()) : '');
    $propertydata['net_price_property_segment'] = (!$property_xml->netPrice['propertysegment'] ?: str_replace('2', '', $property_xml->netPrice['propertysegment']->__toString()));
    $propertydata['gross_price'] = $property_xml->grossPrice->__toString();
    $propertydata['gross_price_time_segment'] = ($property_xml->grossPrice['timesegment'] ? strtolower($property_xml->grossPrice['timesegment']->__toString()) : '');
    $propertydata['gross_price_property_segment'] = (!$property_xml->grossPrice['propertysegment'] ?: str_replace('2', '', $property_xml->grossPrice['propertysegment']->__toString()));

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

    if ($property_xml->seller) {

      $propertydata['organization'] = array();

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

    $offerDatas = array();
    if ($property_xml->offers) {
      foreach ($property_xml->offers->offer as $offer_xml) {
        if (get_option('casawp_force_lang')) {
          $offerData['lang'] =  get_option('casawp_force_lang');
        } else {
          $offerData['lang'] =  strtolower($offer_xml['lang']->__toString());
        }
        $offerData['type'] =  $property_xml->type->__toString();
        if ($property_xml->start) {
          $offerData['start'] =  new \DateTime($property_xml->start->__toString());
        } else {
          $offerData['start'] = null;
        }
        $offerData['status'] = 'active';
        $offerData['name'] = $offer_xml->name->__toString();
        $offerData['excerpt'] = $offer_xml->excerpt->__toString();

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
              #$this->addToTranscript("file or url missing from attachment media!");
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

    return $propertydata;
  }

  public function project2Array($project_xml)
  {
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

  public function langifyProject($projectData)
  {

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

  public function simpleXMLget($node, $fallback = false)
  {
    if ($node) {
      $result = $node->__toString();
      if ($result) {
        return $result;
      }
    }
    return $fallback;
  }

  public function updateProject($sort, $casawp_id, $projectData, $wp_post, $parent_post = false, $found_posts = array())
  {
    $new_meta_data = array();

    $old_meta_data = array();
    $meta_values = get_post_meta($wp_post->ID, null, true);
    foreach ($meta_values as $key => $meta_value) {
      $old_meta_data[$key] = $meta_value[0];
    }
    ksort($old_meta_data);

    $cleanProjectData = $projectData;

    unset($cleanProjectData['last_update']);
    if (isset($cleanProjectData['modified'])) {
      unset($cleanProjectData['modified']);
    }
    $curImportHash = md5(serialize($cleanProjectData));

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
        #$this->addToLog('skipped project: ' . $casawp_id);
      }
    }

    if ($update) {
      $this->transcript[$casawp_id]['action'] = 'update';
      if (!isset($old_meta_data['last_import_hash'])) {
        $this->transcript[$casawp_id]['action'] = 'new';
      }

      $new_meta_data['last_import_hash'] = $curImportHash;

      $new_meta_data['referenceId'] = $projectData['referenceId'];

      $new_main_data = array(
        'ID'            => $wp_post->ID,
        'post_title'    => ($projectData['detail']['name'] ? $projectData['detail']['name'] : $casawp_id),
        'post_content'  => $this->extractDescription($projectData['detail']),
        'post_status'   => 'publish',
        'post_type'     => 'casawp_project',
        'post_excerpt'  => '',
        'menu_order'    => $sort
      );

      $old_main_data = array(
        'ID'            => $wp_post->ID,
        'post_title'    => $wp_post->post_title,
        'post_content'  => $wp_post->post_content,
        'post_status'   => $wp_post->post_status,
        'post_type'     => $wp_post->post_type,
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


        if (!$wp_post->post_name) {
          $new_main_data['post_name'] = $this->casawp_sanitize_title($casawp_id . '-' . $projectData['detail']['name']);
        } else {
          $new_main_data['post_name'] = $wp_post->post_name;
        }

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
      }
    }

    $lang = $this->getMainLang();
    if ($this->hasWPML()) {
      if ($parent_post) {
        $my_post_language_details = apply_filters('wpml_post_language_details', NULL, $parent_post->ID);
        if ($my_post_language_details) {
          $lang = $my_post_language_details['language_code'];
        }
      } else {
        $lang = $projectData['lang'];
      }
    }

    if (isset($projectData['units'])) {
      foreach ($projectData['units'] as $sortu => $unitData) {

        $unit_casawp_id = 'subunit_' . $unitData['ref'] . $lang;

        $the_query = new \WP_Query('post_status=publish,pending,draft,future,trash&post_type=casawp_project&suppress_filters=true&meta_key=casawp_id&meta_value=' . $unit_casawp_id);
        $wp_unit_post = false;
        while ($the_query->have_posts()) :
          $the_query->the_post();
          global $post;
          $wp_unit_post = $post;
        endwhile;
        wp_reset_postdata();

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
        $this->updateInsertWPMLconnection($wp_unit_post, $lang, 'unit_' . $unitData['ref']);
      }
    }


    if ($parent_post && isset($projectData['property_links'])) {
      $sort = 0;
      foreach ($projectData['property_links'] as $sort => $propertyLink) {
        $sort++;
        $casawp_id = $propertyLink['ref'] . $lang;
        $the_query = new \WP_Query('post_type=casawp_property&suppress_filters=true&meta_key=casawp_id&meta_value=' . $casawp_id);
        $wp_property_post = false;
        while ($the_query->have_posts()) :
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
      }
    }


    return $found_posts;
  }
}
