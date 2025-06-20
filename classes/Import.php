<?php

namespace casawp;

use Exception;

if ( ! class_exists( 'CASAWP_Bulk_Meta' ) ) {
  final class CASAWP_Bulk_Meta {
      public static function sync( int $post_id, array $new ): void {
          global $wpdb;
          $tbl = $wpdb->postmeta;

          /* keep special keys intact when they are **not** in $new */
          $protected = [ 'casawp_id', 'projectunit_id', 'projectunit_sort' ];

          foreach ( $new as $k => $v ) {
              $v = maybe_serialize( $v );

              // 1) remove *all* rows for this key (except protected ones)
              if ( ! in_array( $k, $protected, true ) ) {
                  $wpdb->delete(
                      $tbl,
                      [ 'post_id' => $post_id, 'meta_key' => $k ],
                      [ '%d', '%s' ]
                  );
              }

              // 2) insert the fresh value
              $wpdb->insert(
                  $tbl,
                  [
                      'post_id'    => $post_id,
                      'meta_key'   => $k,
                      'meta_value' => $v,
                  ],
                  [ '%d', '%s', '%s' ]
              );
          }

          /* 3) drop stale keys that disappeared from the payload -------- */
          $placeholders = implode( ',', array_fill( 0, count( $new ), '%s' ) );
          $wpdb->query(
              $wpdb->prepare(
                  "
                  DELETE FROM $tbl
                  WHERE post_id = %d
                    AND meta_key NOT IN ( $placeholders )
                    AND meta_key NOT IN ( '" . implode( "','", $protected ) . "' )
                  ",
                  array_merge( [ $post_id ], array_keys( $new ) )
              )
          );

          wp_cache_delete( $post_id, 'post_meta' );   // purge object-cache
      }
  }

}

class Import
{
  public $importFile = false;
  public $main_lang = false;
  public $WPML = null;
  public $transcript = array();
  public $curtrid = false;
  public $trid_store = array();

  private $ranksort = array();

  private int $current_run_id = 0;

  public function __construct($casagatewaypoke = false, $casagatewayupdate = false)
  {
    if ($casagatewaypoke) {
      add_action('init', array($this, 'updateImportFileThroughCasaGateway'));
    }
    if ($casagatewayupdate) {
      $this->updateImportFileThroughCasaGateway();
    }
  }

  public function register_hooks()
  {
    add_action('casawp_batch_import', array($this, 'handle_properties_import_batch'), 10, 2);
    add_action('casawp_delete_outdated_properties', array($this, 'delete_outdated_properties'));
  }

  public function start_import() {

      // mint / reuse run-id
      $run_id = (int) get_option( 'casawp_current_run_id', 0 );
      if ( ! $run_id ) {
          $run_id = time();                              // unique
          update_option( 'casawp_current_run_id', $run_id, 'no' );
      }

      // schedule batch #1 in its *own* group
      as_schedule_single_action(
          time() + 10,
          'casawp_batch_import',
          [ 'batch_number' => 1, 'run_id' => $run_id ],
          'casawp_run_' . $run_id       // ← group
      );
  }

  public function init_single_run( int $run_id ): void {
    $this->current_run_id = $run_id;
  }

  /**
   * Recursively k-sort every associative sub-array while keeping pure
   * numeric lists (0-based, consecutive keys) in their original order.
   */
  private function deepKsort( array &$a ): void
  {
      // ── sort children first ─────────────────────────────────────────
      foreach ( $a as &$v ) {
          if ( is_array( $v ) ) {
              $this->deepKsort( $v );        // recurse
          }
      }

      // ── skip plain lists, sort true maps ───────────────────────────
      $isList = function_exists( 'array_is_list' )
          ? array_is_list( $a )              // PHP 8.1+
          : (                             // fallback for older PHP
              static function ( array $arr ): bool {
                  $i = 0;
                  foreach ( $arr as $k => $_ ) {
                      if ( $k !== $i++ ) {            // non-sequential key
                          return false;               // → not a list
                      }
                  }
                  return true;                        // 0,1,2,3…
              }
          )( $a );

      if ( ! $isList ) {
          ksort( $a, SORT_STRING );        // stable, deterministic order
      }
  }


  private function fingerprint( array $meta ): string {

      static $skip = [
          // housekeeping / volatile
          'last_import_hash',
          'last_processed_run',
          'is_active',

          // keys created outside the generator
          '_thumbnail_id',
          '_wpml_word_count',
          '_yoast_indexnow_last_ping',
          '_last_translation_edit_mode',

          // identifiers you store once during insert
          'casawp_id',
          'projectunit_id',
          'projectunit_sort',
      ];

      foreach ( $skip as $k ) {
          unset( $meta[ $k ] );
      }

      $this->deepKsort( $meta );
      return md5( serialize( $meta ) );
  }


  private function group(): string {
      // when the handler is already inside a batch, use the cached ID
      if ( $this->current_run_id ) {
          return 'casawp_run_' . $this->current_run_id;
      }
      // fallback for places that call group() before we set the property
      return 'casawp_run_' . (int) get_option( 'casawp_current_run_id', 0 );
  }


  private function fetchFileFromCasaGateway(): string
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

  public function updateImportFileThroughCasaGateway( bool $already_locked = false )
  {
    $this->addToLog('gateway file retrieval start: ' . time());

    if ( ! $already_locked && ! casawp_acquire_lock() ) {
        casawp_set_pending();
        $this->addToLog( 'Import already running - poke queued.' );
        return;
    }

    try {

      $filePath = $this->fetchFileFromCasaGateway();

      if ($filePath && $this->getImportFile()) {

        as_schedule_single_action(time(), 'casawp_delete_outdated_properties', [], 'casawp_delete_outdated_properties');

        delete_option('casawp_import_canceled');
        $this->deactivate_all_properties();
        $this->start_import();
        $this->addToLog('import start');

      }

    } catch (\Exception $e) {
        $this->addToLog('Import failed: ' . $e->getMessage());
        update_option('casawp_import_failed', true);
    } finally {
      if ( ! $already_locked ) {
          casawp_release_lock();
      }
    }
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

  /**
   * Called once per run – deletes posts that have NOT been processed in
   * the current run and clears the “import in progress” lock.
   *
   * Extra guards guarantee the function cannot execute a 2-nd time for
   * the same run (or when no run is in progress).
   */
  public function finalize_import_cleanup() {

    /* ───────────────────────────────────────────────────────────────
     *  0.  Safety guards
     *      –––––––––––––
     *      • do nothing if no run is active
     *      • do nothing if this run was already cleaned up
     * ─────────────────────────────────────────────────────────────── */
    $run_id = (int) get_option( 'casawp_current_run_id', 0 );
    if ( ! $run_id ) {
      return;                                    // no active run → bail
    }
    if ( (int) get_option( 'casawp_cleanup_done_for_run', 0 ) === $run_id ) {
      return;                                    // already cleaned up → bail
    }

    $this->addToLog( 'Finalizing import cleanup.' );

    if ( as_next_scheduled_action( 'casawp_batch_import', [], $this->group() ) ) {
        $this->addToLog( 'Cleanup postponed – batches still pending.' );
        return;
    }

    /* ----------------------------------------------------------------
     *  1.  Early exits for cancelled / failed runs
     * ---------------------------------------------------------------- */
    if ( get_option( 'casawp_import_canceled' ) ) {
      $this->addToLog( 'Import was canceled - cleanup skips deletions.' );
      casawp_release_lock();
      return;
    }

    if ( get_option( 'casawp_import_failed' ) ) {
      $this->addToLog( 'Import marked as failed. Skipping deletion of inactive properties.' );
      delete_option( 'casawp_import_failed' );
      casawp_release_lock();
      return;
    }

    /* ----------------------------------------------------------------
     *  2.  Remove posts that were NOT touched in this run
     * ---------------------------------------------------------------- */
    $posts_to_remove = get_posts( [
      'posts_per_page' => -1,
      'post_type'      => 'casawp_property',
      'post_status'    => 'publish',
      'fields'         => 'ids',
      'meta_query'     => [
        'relation' => 'OR',
        [
          'key'     => 'last_processed_run',
          'compare' => 'NOT EXISTS',          // never processed
        ],
        [
          'key'     => 'last_processed_run',  // processed in an earlier run
          'value'   => $run_id,
          'compare' => '!=',
          'type'    => 'NUMERIC',
        ],
      ],
    ] );

    foreach ( $posts_to_remove as $post_id ) {

      $attachments = get_posts( [
        'post_type'      => 'attachment',
        'posts_per_page' => -1,
        'post_status'    => 'any',
        'post_parent'    => $post_id,
        'fields'         => 'ids',
      ] );

      $this->addToLog( "Deleting " . count( $attachments ) . " attachments for property ID: $post_id" );

      foreach ( $attachments as $attachment_id ) {
        if ( wp_delete_attachment( $attachment_id, true ) ) {
          $this->addToLog( "Deleted attachment ID: $attachment_id" );
        } else {
          $this->addToLog( "Failed to delete attachment ID: $attachment_id" );
        }
      }

      if ( wp_delete_post( $post_id, true ) ) {
        $this->addToLog( "Deleted property ID: $post_id" );
      } else {
        $this->addToLog( "Failed to delete property ID: $post_id" );
      }
    }

    flush_rewrite_rules();

    if ( class_exists( '\WpeCommon' ) ) {
      \WpeCommon::purge_varnish_cache();
      \WpeCommon::purge_memcached();
      $this->addToLog( 'Triggered WP Engine cache purge.' );
    }

    /* ----------------------------------------------------------------
     *  3.  Mark this run as *fully* cleaned-up and tidy the scheduler
     * ---------------------------------------------------------------- */
    update_option( 'casawp_cleanup_done_for_run', $run_id );

    if ( function_exists( 'as_unschedule_all_actions' ) ) {
      as_unschedule_all_actions( 'casawp_batch_import', [], $this->group() );
    }

    casawp_release_lock();
    delete_option   ( 'casawp_current_run_id' );            // now safe to remove
    $this->addToLog( 'Import lock cleared.' );
    $this->addToLog( 'Import completed and lock cleared.' );

    /* 4.  Fire follow-up actions / pending imports */
    if ( casawp_has_pending() ) {
      casawp_clear_pending();
      $this->addToLog( 'Pending import triggered right after cleanup.' );
      casawp_start_new_import( 'Pending after previous run', false );
    }

    do_action( 'casawp_import_finished' );
  }


  public function handle_properties_import_batch($batch_number, $run_id = 0)
  {

    if ( ! $this->current_run_id ) {
        $this->current_run_id = $run_id
            ? (int) $run_id
            : (int) get_option( 'casawp_current_run_id', 0 );

        if ( ! $this->current_run_id && $batch_number == 1 ) {
            $this->current_run_id = time();
            update_option( 'casawp_current_run_id', $this->current_run_id, 'no' );
        }
    }

    $started_at = microtime( true );

    // Temporarily suspend cache additions for speed
    wp_suspend_cache_addition(true);
    if ( get_option('casawp_import_failed') ) {
      $this->addToLog('Import already marked as failed. Skipping batch number: ' . $batch_number);
      wp_suspend_cache_addition(false);
      return;
    }

    if (get_option('casawp_import_canceled', false)) {
      $this->addToLog('Import has been canceled. Skipping batch number: ' . $batch_number);
      wp_suspend_cache_addition(false);
      return;
    }

    $batch_size_override = get_option('casawp_batch_size_override', '');
    if (!empty($batch_size_override) && is_numeric($batch_size_override) && (int)$batch_size_override > 0) {
      $batch_size = (int)$batch_size_override;
    } else {
      $batch_size = 1;
      if (get_option('casawp_use_casagateway_cdn', false)) {
        $language_count = 1;
        if ( function_exists('icl_get_languages') ) {
          $langs = icl_get_languages();
          if ( ! is_array($langs) ) {
              $langs = [];
          }
            $language_count = count($langs);
        }
        if ($language_count <= 2) {
          $batch_size = 8;
        } elseif ($language_count === 3) {
          $batch_size = 6;
        } else {
          $batch_size = 4;
        }
      }
    }

    $this->ranksort = get_option('casawp_ranksort', array());

    try {

      $xmlString = file_get_contents($this->getImportFile());
      if ($xmlString === false) {
        throw new \Exception('Failed to read import file.');
      }

      $xml = simplexml_load_string($xmlString, "SimpleXMLElement", LIBXML_NOCDATA);
      if ($xml === false) {
        throw new \Exception('Failed to parse XML.');
      }

      // OLD WAY, throwing Exception
      /* $properties = $xml->properties->property;
      if ($properties === null) {
        throw new \Exception('No properties found in XML.');
      } */

      //NEW WAY, to delete all properties if empty file
      $propertiesNode = $xml->properties ?? null;
      $properties     = $propertiesNode ? $propertiesNode->property : [];

      if ( empty( $properties ) ) {
        $this->addToLog('Feed contained zero properties - treating as full unpublish.');
        $this->finalize_import_cleanup();
        casawp_release_lock();
        wp_suspend_cache_addition(false);
        return;
      }

      $properties_array = array();
      foreach ($properties as $property) {
        $properties_array[] = $this->property2Array($property);
      }

      $total_items   = count($properties_array);
      $total_batches = ceil($total_items / $batch_size);

      if ($batch_number == 1) {
        update_option('casawp_total_batches', $total_batches);
        update_option('casawp_completed_batches', 0);
        update_option('casawp_current_rank', 0);
        #$this->addToLog('Initialized import: Total Batches = ' . $total_batches);
      }

      $items_for_current_batch = array_slice($properties_array, ($batch_number - 1) * $batch_size, $batch_size, true);

      #$this->addToLog('Processing batch number: ' . $batch_number . ' with ' . count($items_for_current_batch) . ' properties.');

      $this->updateOffers($items_for_current_batch);

      update_option('casawp_ranksort', $this->ranksort);
      update_option('casawp_completed_batches', $batch_number);
      #$this->addToLog('Completed batch number: ' . $batch_number);

      if ($batch_number >= $total_batches) {
        $this->finalize_import_cleanup();
        update_option('casawp_completed_batches', $total_batches);
        delete_option('casawp_current_rank');
        delete_option('casawp_ranksort');
        $this->addToLog('Import process completed.');
      } else {
        $next_batch_number = $batch_number + 1;

        /* ── calculate self-tuning delay ───────────── */
        $runtime = microtime( true ) - $started_at;   // seconds
        $delay   = min( max( ceil( $runtime ) + 8, 12 ), 90 );

        $this->addToLog(
            sprintf(
                'Batch %d took %.2fs - next in %ds',
                $batch_number,
                $runtime,
                $delay
            )
        );

        /* If another batch with that number isn’t
           already queued in this run, schedule it.   */
        if ( ! as_next_scheduled_action(
                 'casawp_batch_import',
                 [ 'batch_number' => $next_batch_number ],
                 $this->group()
             ) ) {

            as_schedule_single_action(
                time() + $delay,
                'casawp_batch_import',
                [ 'batch_number' => $next_batch_number, 'run_id' => $this->current_run_id ],
                $this->group()
            );
        }
      }
    } catch (\Exception $e) {
      $this->addToLog('Error: ' . $e->getMessage());
      if ($e->getMessage() === 'No properties found in XML.') {
        set_transient('casawp_no_properties_alert', 'No properties were found during the import. Please verify the data.', 60);
        casawp_release_lock();
      }
    }
    wp_suspend_cache_addition(false);
  }

  public function handle_single_request_import()
  {
      wp_suspend_cache_addition(true);

      // Possibly throws exceptions if something goes wrong
      $this->fetchFileFromCasaGateway(); 

      // Read the file or throw an exception
      $xmlString = file_get_contents($this->getImportFile());
      if ($xmlString === false) {
          $this->addToLog('Failed to read import file.');
          wp_suspend_cache_addition(false);
          throw new Exception('Failed to read import file.');
      }

      $xml = simplexml_load_string($xmlString, "SimpleXMLElement", LIBXML_NOCDATA);
      if ($xml === false) {
          $this->addToLog('Failed to parse XML.');
          wp_suspend_cache_addition(false);
          throw new Exception('Failed to parse XML.');
      }

      // OLD WAY, throwing Exception
      /* $properties = $xml->properties->property;
      if ($properties === null) {
        throw new \Exception('No properties found in XML.');
      } */

      //NEW WAY, to delete all properties if empty file
      $propertiesNode = $xml->properties ?? null;
      $properties     = $propertiesNode ? $propertiesNode->property : [];

      if ( empty( $properties ) ) {
        $this->addToLog('Feed contained zero properties – treating as full unpublish.');
        $this->finalize_import_cleanup();
        casawp_release_lock();
        wp_suspend_cache_addition(false);
        return;
      }

      // If we get here, parse the properties and import
      $properties_array = [];
      foreach ($properties as $property) {
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
      casawp_release_lock();
      wp_suspend_cache_addition(false);
  }

  public function delete_outdated_properties()
  {
    $this->addToLog('Starting deletion of outdated properties.');

    $xml_file_path = CASASYNC_CUR_UPLOAD_BASEDIR . '/casawp/import/data.xml';
    if (!file_exists($xml_file_path)) {
      $this->addToLog("XML file not found at: $xml_file_path");
      #error_log("XML file not found at: $xml_file_path");
      return;
    }

    libxml_use_internal_errors(true);
    $xml = simplexml_load_file($xml_file_path);
    if ($xml === false) {
      $this->addToLog("Failed to parse XML file.");
      libxml_clear_errors();
      return;
    }

    $xml_property_ids = array();

    foreach ($xml->properties->property as $property) {
      $id = (string) $property['id'];
      if ($id) {
        $xml_property_ids[] = $id;
      }
    }

    if (empty($xml_property_ids)) {
      $this->addToLog('Feed contained zero properties - skipping “outdated” pass, full clean-up will handle.');
      return;
    }

    $args = array(
      'post_type'      => 'casawp_property',
      'posts_per_page' => -1,
      'fields'         => 'ids',
      'meta_key'       => 'exportproperty_id',
    );

    $query = new \WP_Query($args);
    $existing_posts = $query->posts;

    $exportproperty_to_posts = [];
    foreach ($existing_posts as $post_id) {
      $exportproperty_id = (string) get_post_meta($post_id, 'exportproperty_id', true);
      if ($exportproperty_id) {
        if (!isset($exportproperty_to_posts[$exportproperty_id])) {
          $exportproperty_to_posts[$exportproperty_id] = [];
        }
        $exportproperty_to_posts[$exportproperty_id][] = $post_id;
      }
    }

    $existing_exportproperty_ids = array_keys($exportproperty_to_posts);
    $outdated_exportproperty_ids = array_diff($existing_exportproperty_ids, $xml_property_ids);


    $batch_size = 50;
    $batches = array_chunk($outdated_exportproperty_ids, $batch_size);

    foreach ($batches as $batch) {
      foreach ($batch as $exportproperty_id) {
        if (isset($exportproperty_to_posts[$exportproperty_id])) {
          foreach ($exportproperty_to_posts[$exportproperty_id] as $post_id) {
            if ($this->hasWPML()) {

              $trid = apply_filters('wpml_element_trid', null, $post_id, 'post_' . get_post_type($post_id));

              if (!$trid) {
                if (isset($this->trid_store[$exportproperty_id])) {
                  $trid = $this->trid_store[$exportproperty_id];
                } else {
                  #error_log("Unable to find TRID for exportproperty_id: {$exportproperty_id}");
                  continue;
                }
              }

              global $sitepress;
              if (isset($sitepress)) {
                $translations = $sitepress->get_element_translations($trid);
              } else {
                #error_log("SitePress global object not found.");
                continue;
              }

              if ($translations && is_array($translations)) {
                foreach ($translations as $lang => $translation) {
                  if (isset($translation->element_id)) {
                    $trans_post_id = $translation->element_id;

                    $attachments = get_posts(array(
                      'post_type'      => 'attachment',
                      'posts_per_page' => -1,
                      'post_status'    => 'any',
                      'post_parent'    => $trans_post_id,
                      'fields'         => 'ids',
                    ));

                    foreach ($attachments as $attachment_id) {
                      wp_delete_attachment($attachment_id, true);
                    }

                    wp_delete_post($trans_post_id, true);
                  }
                }
              }
            } else {

              $attachments = get_posts(array(
                'post_type'      => 'attachment',
                'posts_per_page' => -1,
                'post_status'    => 'any',
                'post_parent'    => $post_id,
                'fields'         => 'ids',
              ));

              foreach ($attachments as $attachment_id) {
                wp_delete_attachment($attachment_id, true);
              }
              wp_delete_post($post_id, true);
            }
          }
        }
      }
    }

    if (class_exists('\WpeCommon')) {
      \WpeCommon::purge_varnish_cache();
      \WpeCommon::purge_memcached();
      $this->addToLog('Triggered WP Engine cache purge after deletion.');
    }
    $this->addToLog("Deletion of outdated properties completed.");
  }

  public function getMainLang()
  {
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

  // Delayed WPML link creation to reduce overhead
  public function updateInsertWPMLconnection( $wp_post, string $lang, string $trid_identifier ): void {

      if ( ! $this->hasWPML() ) {
          return;
      }

      global $wpdb, $sitepress;
      if ( ! $sitepress ) {                     // ① guard
          return;
      }

      static $default_lang = null;              // ② micro-cache
      $default_lang = $default_lang ?? $sitepress->get_default_language();

      $main_lang    = $this->getMainLang();
      $element_type = 'post_casawp_property';

      /* 1 — obtain / mint TRID */
      if ( $lang === $main_lang ) {

          $trid = (int) $wpdb->get_var( $wpdb->prepare(
              "SELECT trid FROM {$wpdb->prefix}icl_translations
               WHERE element_id = %d AND element_type = %s LIMIT 1",
              $wp_post->ID,
              $element_type
          ) );

          if ( ! $trid ) {
              $trid = 50_000_000 + $wp_post->ID;         // ③ wider gap
          }

          $this->trid_store[ $trid_identifier ] = $trid;

      } else {
          $trid = $this->trid_store[ $trid_identifier ] ?? null;

          if ( ! $trid ) {                               // ④ fallback
              $trid = (int) $wpdb->get_var( $wpdb->prepare(
                  "SELECT trid FROM {$wpdb->prefix}icl_translations
                   WHERE element_id = %d AND element_type = %s LIMIT 1",
                  $wp_post->ID,
                  $element_type
              ) );
          }
      }

      if ( ! $trid ) {
          return;                                        // bail gracefully
      }

      /* Skip if mapping already present (saves UPDATE) */
      if ( $wpdb->get_var( $wpdb->prepare(
              "SELECT translation_id FROM {$wpdb->prefix}icl_translations
               WHERE element_id = %d AND trid = %d LIMIT 1",
              $wp_post->ID, $trid
          ) ) ) {
          return;
      }

      /* 2 — register mapping */
      $sitepress->set_element_language_details(
          $wp_post->ID,
          $element_type,
          $trid,
          $lang,
          ( $lang === $main_lang ? null : $default_lang ),
          true
      );
  }


  public function getImportFile()
  {
    if (!$this->importFile) {
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
        $this->importFile = $file;
      } else {
        $this->addToLog('file was missing ' . time());
      }
    }
    return $this->importFile;
  }

  public function findLangKey($lang, $array)
  {
    foreach ($array as $key => $value) {
      if (isset($value['lang']) && $lang == $value['lang']) {
        return $key;
      }
    }
    return false;
  }

  public function fillMissingTranslations($theoffers)
  {

     if (!function_exists('icl_get_languages')) {
      return $theoffers;
    }

    $maybe_languages = icl_get_languages('skip_missing=0&orderby=code');
    if (!is_array($maybe_languages)) {
      return $theoffers;
    }

    $translations = [];
    foreach ($maybe_languages as $lang) {
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

    foreach ($translations as $langcode => $val) {
      if (!$val) {
        $copy = $carbon;
        $copy['lang'] = $langcode;
        if (get_option('casawp_auto_translate_properties')) {
          // Minimal default naming if missing
          if ($copy['urls']) {
            foreach ($copy['urls'] as $i => $url) {
              $urlString = str_replace(array('http://', 'https://'), '', $url['url']);
              $urlString = strtok($urlString, '/');
              $copy['urls'][$i]['title'] = $urlString;
            }
          }
          if ($langcode == 'de') {
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
          } elseif ($langcode == 'fr') {
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
          } elseif ($langcode == 'en') {
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
          } elseif ($langcode == 'it') {
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
          $copy['descriptions'] = [];
          $copy['excerpt'] = '';
        }
        $translations[$langcode] = $copy;
      }
    }

    $merged = [];
    $i=0;
    foreach ($translations as $value) {
      if ($value['lang'] == $this->getMainLang()) {
        $merged[0] = $value;
      } else {
        $i++;
        $merged[$i] = $value;
      }
    }
    ksort($merged);
    return $merged;
  }

  public function updateOffers($batched_file)
  {

    global $wpdb;
    $found_posts = [];
    $curRank = get_option('casawp_current_rank', 0);

    if (!empty($batched_file)) {
      foreach ($batched_file as $property) {
        #error_log(print_r($property, true));
        $curRank++;
        $theoffers = [];
        $i = 0;
        foreach ($property['offers'] as $offer) {
          $i++;
          if ($offer['lang'] == $this->getMainLang()) {
            $theoffers[0] = $offer;
            $theoffers[0]['locality'] = $property['address']['locality'];
          } else {
            if ($this->hasWPML()) {
              $theoffers[$i] = $offer;
              $theoffers[$i]['locality'] = $property['address']['locality'];
            }
          }
        }

        if ($this->hasWPML()) {
          $theoffers = $this->fillMissingTranslations($theoffers);
        }

        $offer_pos = 0;
        foreach ($theoffers as $offerData) {
          $offer_pos++;

          $casawp_id = $property['exportproperty_id'] . $offerData['lang'];

          $the_query = new \WP_Query([
            'post_status' => ['publish', 'pending', 'draft', 'future', 'trash'],
            'post_type'   => 'casawp_property',
            'meta_query'  => [
              [
                'key'   => 'casawp_id',
                'value' => $casawp_id,
              ],
            ],
            'posts_per_page' => 1,
            'suppress_filters' => true,
            'language' => '',
          ]);

          if ($the_query->have_posts()) {
            $the_query->the_post();
            global $post;
            $wp_post = $post;
            $this->transcript[$casawp_id]['action'] = 'update';
          } else {
            $this->transcript[$casawp_id]['action'] = 'new';
            
            $the_post = [
              'post_title'   => $offerData['name'],
              'post_content' => 'unsaved property',
              'post_status'  => 'publish',
              'post_type'    => 'casawp_property',
              'menu_order'   => $curRank,
              'post_name'    => $this->casawp_sanitize_title($casawp_id . '-' . $offerData['name']),
              'post_date'    => (
                  $property['creation']
                  ? $property['creation']->format('Y-m-d H:i:s')
                  : $property['last_update']->format('Y-m-d H:i:s')
              ),
            ];

            $insert_id = wp_insert_post($the_post);
            update_post_meta($insert_id, 'casawp_id', $casawp_id);
            update_post_meta($insert_id, 'is_active', true);
            $wp_post = get_post($insert_id, OBJECT, 'raw');
          }

          wp_reset_postdata();

          wp_update_post(array(
            'ID' => $wp_post->ID,
            'menu_order' => $curRank,
          ));

          $this->ranksort[$wp_post->ID] = $curRank;

          $found_posts[] = $wp_post->ID;

          $this->updateOffer( $casawp_id, $offer_pos, $property, $offerData, $wp_post );

          $this->updateInsertWPMLconnection($wp_post, $offerData['lang'], $property['exportproperty_id']);

        }
      }
    }

    update_option('casawp_current_rank', $curRank);

    $this->reactivate_properties($found_posts);

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

    $old_meta_data = [];
    foreach ( get_post_meta( $wp_post->ID, null, true ) as $k => $vals ) {
        $old_meta_data[ $k ] = maybe_unserialize( $vals[0] );
    }
    unset( $old_meta_data['last_import_hash'] );    // <<< strip hash
    ksort( $old_meta_data );

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

    $new_meta_data = [];

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

    $personTypes = array('view', 'inquiry', 'visit');
    foreach ($personTypes as $ptype) {
      $keyName = $ptype . 'Person';
      if (isset($property[$keyName]) && $property[$keyName]) {
        $prefix = 'seller_' . $ptype . '_person_';
        $new_meta_data[$prefix . 'function']    = $property[$keyName]['function'];
        $new_meta_data[$prefix . 'givenname']   = $property[$keyName]['firstName'];
        $new_meta_data[$prefix . 'familyname']  = $property[$keyName]['lastName'];
        $new_meta_data[$prefix . 'email']       = $property[$keyName]['email'];
        $new_meta_data[$prefix . 'fax']         = $property[$keyName]['fax'];
        $new_meta_data[$prefix . 'phone_direct'] = $property[$keyName]['phone'];
        $new_meta_data[$prefix . 'phone_mobile'] = $property[$keyName]['mobile'];
        $new_meta_data[$prefix . 'gender']      = $property[$keyName]['gender'];
        $new_meta_data[$prefix . 'note']        = $property[$keyName]['note'];
      }
    }

    $urlDatas = array();
    if (isset($offer['urls'])) {
      foreach ($offer['urls'] as $url) {
        $href  = $url['url'];
        if (! (substr($href, 0, 7) === "http://" || substr($href, 0, 8) === "https://")) {
          $href = 'http://' . $href;
        }
        $label = (isset($url['label']) ? $url['label'] : false);
        $title = (isset($url['title']) ? $url['title'] : false);
        $type  = (isset($url['type'])  ? (string) $url['type'] : false);

        if ($type) {
          $urlDatas[$type][] = array(
            'href'  => $href,
            'label' => $label,
            'title' => $title
          );
        } else {
          $urlDatas[] = array(
            'href'  => $href,
            'label' => $label,
            'title' => $title
          );
        }
      }
      ksort($urlDatas);
      $new_meta_data['the_urls'] = $urlDatas;
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
      foreach ($property['integratedoffers'] as $io) {
        $integratedoffers[] = array(
          'type'            => $io['type'],
          'price'           => $io['cost'],
          'timesegment'     => $io['time_segment'],
          'propertysegment' => $io['property_segment'],
          'currency'        => $new_meta_data['price_currency'],
          'frequency'       => $io['frequency'],
          'inclusive'       => $io['inclusive']
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
      foreach ($custom_metas as $ckey => $cvalue) {
        $new_meta_data['custom_option_' . $ckey] = $cvalue;
      }
    }
   /*  if ( $casawp_id === '353188de' ) {          // 1 language is enough
        $yb_old = $old_meta_data['year_built']  ?? '-';
        $yb_new = $numericValues['year_built'] ?? '-';
        $this->addToLog(
            "DEBUG year_built  old={$yb_old}  new={$yb_new}"
        );
    } */


    $scalar = static function ($v) { return is_array($v) ? reset($v) : (string)$v; };

    $new_meta_data['_hash_availability'] = sanitize_title( $property['availability'] );

    # 2. salestype
    $new_meta_data['_hash_salestype'] = sanitize_title( $property['type'] );

    # 3. property-categories  (array → sorted pipe list)
    $cat_slugs = [];

    /* 1a. regular categories coming from <property_categories> */
    if ( ! empty( $property['property_categories'] ) ) {
      $cat_slugs = array_map(
        'sanitize_title',
        (array) $property['property_categories']
      );
    }

    $opt = static function ( $val ) {
        return is_array( $val ) ? reset( $val ) : (string) $val;
    };

    /* 1b. custom categories coming from <publisher><options> */
    foreach ( $publisher_options as $opt_key => $opt_val ) {
        if ( preg_match( '/^custom_category_\d+_slug$/', $opt_key ) ) {
            $slug = trim( $opt( $opt_val ) );          // <-- FIX
            if ( $slug !== '' ) {
                $cat_slugs[] = sanitize_title( 'custom_' . $slug );
            }
        }
    }

    /* 1c. deterministic order + de-dupe → single hash string */
    $cat_slugs = array_unique( $cat_slugs );
    sort( $cat_slugs, SORT_STRING );

    $new_meta_data['_hash_categories'] = implode( '|', $cat_slugs );

    # 4. features
    if ( ! empty( $property['features'] ) ) {
        $feat_slugs = array_map( 'sanitize_title',
                       (array) $property['features'] );
        sort( $feat_slugs, SORT_STRING );
        $new_meta_data['_hash_features'] = implode( '|', $feat_slugs );
    }

    # 5. utilities
    if ( ! empty( $property['property_utilities'] ) ) {
        $util_slugs = array_map( 'sanitize_title',
                        (array) $property['property_utilities'] );
        sort( $util_slugs, SORT_STRING );
        $new_meta_data['_hash_utilities'] = implode( '|', $util_slugs );
    }

    /* ------------------------------------------------------------------
     * 6.  LOCATION  (country / region / locality)
     * ----------------------------------------------------------------*/
    $loc_slugs = [];
    if ( ! empty( $property['address']['country'] ) ) {
      $loc_slugs[] = sanitize_title( 'country_' .
                                     strtoupper( $property['address']['country'] ) );
    }
    if ( ! empty( $property['address']['region'] ) ) {
      $loc_slugs[] = sanitize_title( 'region_' . $property['address']['region'] );
    }
    if ( ! empty( $property['address']['locality'] ) ) {
      $loc_slugs[] = sanitize_title( 'locality_' . $property['address']['locality'] );
    }
    sort( $loc_slugs, SORT_STRING );                        // deterministic
    $new_meta_data['_hash_location'] = implode( '|', $loc_slugs );

    /* ------------------------------------------------------------------
     * 7.  CUSTOM REGIONS  (publisher options → “casawp_region” taxonomy)
     * ----------------------------------------------------------------*/
    $region_slugs = [];
    foreach ( $publisher_options as $opt_key => $opt_val ) {
        if ( str_starts_with( $opt_key, 'custom_region_' ) &&
             str_ends_with(  $opt_key, '_slug' ) ) {

            $slug = trim( $scalar( $opt_val ) );
            if ( $slug !== '' ) {
                $region_slugs[] = sanitize_title( $slug );
            }
        }
    }
    sort( $region_slugs, SORT_STRING );
    $new_meta_data['_hash_regions'] = implode( '|', $region_slugs );


    /* ------------------------------------------------------------------
     * 8 · ATTACHMENTS
     *      Build a stable list of every <media original_file> or <media url>,
     *      sort it alphabetically, then hash the joined string.
     * ----------------------------------------------------------------*/
    $media_refs = [];

    if ( ! empty( $offer['offer_medias'] ) && is_array( $offer['offer_medias'] ) ) {
        foreach ( $offer['offer_medias'] as $m ) {

            // prefer original_file (images/plans) but fall back to url (docs etc.)
            if ( ! empty( $m['media']['original_file'] ) ) {
                $ref = $m['media']['original_file'];
            } elseif ( ! empty( $m['url'] ) ) {
                $ref = $m['url'];
            } else {
                continue;                               // nothing to fingerprint
            }

            $media_refs[] = trim( $ref );
        }

        if ( $media_refs ) {
            sort( $media_refs, SORT_STRING | SORT_FLAG_CASE );      // deterministic
            $new_meta_data['_hash_media'] = md5( implode( '|', $media_refs ) );
        }
    }



    ksort($new_meta_data);

    foreach ($new_meta_data as $meta_key => $meta_value) {
      if ($meta_value === true) {
        $meta_value = "1";
      }
      if (is_numeric($meta_value)) {
        $meta_value = (string) $meta_value;
      }
      if ($meta_key == "floor" && $meta_value == 0) {
        $meta_value = "EG";
      }
      if (function_exists("casawp_unicode_dirty_replace") && !is_array($meta_value)) {
        $meta_value = casawp_unicode_dirty_replace($meta_value);
      }
      $new_meta_data[$meta_key] = $meta_value;
    }

    $hashFromDb = $this->fingerprint( $old_meta_data );
    $delta = array_diff_assoc( $new_meta_data, $old_meta_data );
    if ( $delta && $casawp_id === '353188de' ) {
        $this->addToLog( 'DELTA '. print_r( $delta, true ) );
    }
    $newHash    = $this->fingerprint( $new_meta_data );

    

    /* DEBUGGING HASHING */

    if ( $casawp_id === '1596609de' ) {      // pick any ID
        $this->addToLog( 'HASH_OLD '.$hashFromDb );
        $this->addToLog( 'HASH_NEW '.$newHash );
    }

    if ( $casawp_id === '1596609de' ) {           // pick any one offer

        $mismatch = [];

        $all_keys = array_unique(
            array_merge( array_keys( $new_meta_data ), array_keys( $old_meta_data ) )
        );

        foreach ( $all_keys as $k ) {

            $has_new = array_key_exists( $k, $new_meta_data );
            $has_old = array_key_exists( $k, $old_meta_data );

            if ( ! $has_new || ! $has_old ) {
                $mismatch[ $k ] = $has_new ? 'only-new' : 'only-old';
                continue;
            }

            if ( serialize( $new_meta_data[ $k ] ) !== serialize( $old_meta_data[ $k ] ) ) {
                // tiny summary – avoids flooding the log with whole arrays
                $mismatch[ $k ] = [
                    'old' => is_scalar( $old_meta_data[ $k ] ) ? $old_meta_data[ $k ] : 'array('.count($old_meta_data[ $k ]).')',
                    'new' => is_scalar( $new_meta_data[ $k ] ) ? $new_meta_data[ $k ] : 'array('.count($new_meta_data[ $k ]).')',
                ];
            }
        }

        if ( $mismatch ) {
            $this->addToLog( 'MISMATCH '. print_r( $mismatch, true ) );
        }
    }


    if ( $hashFromDb === $newHash ) {         
        $this->addToLog( "Skip {$casawp_id} - unchanged (hash)" );
        update_post_meta( $wp_post->ID, 'is_active', true );
        update_post_meta( $wp_post->ID, 'last_processed_run', $this->current_run_id );
        return;
    }

    $new_meta_data['last_import_hash'] = $newHash;



    /* ---------- main-post update ---------- */
    if ( $new_main_data !== $old_main_data ) {
      if ( ! $wp_post->post_name ) {
        $new_main_data['post_name'] = $this->casawp_sanitize_title( $casawp_id . '-' . $offer['name'] );
      } else {
        $new_main_data['post_name'] = $wp_post->post_name;
      }
      wp_update_post( $new_main_data );
    }

    /* ---------- meta update (single statement) ---------- */
    if ( $new_meta_data !== $old_meta_data ) {
      CASAWP_Bulk_Meta::sync( $wp_post->ID, $new_meta_data );
      update_post_meta( $wp_post->ID, 'last_import_hash', $newHash );
      $this->addToLog( "Meta updated for {$casawp_id}" );
    }

    if ( isset( $property['property_categories'] ) ) {

      // helper → always give us the scalar value
      $unwrap = static function ( $v ) {
        return is_array( $v ) ? reset( $v ) : (string) $v;
      };

      $custom_categories = [];

      foreach ( $publisher_options as $key => $val ) {

        // only keys like  custom_category_1_slug  /  custom_category_2_label …
        if ( strpos( $key, 'custom_category_' ) !== 0 ) {
          continue;
        }

        $parts = explode( '_', $key );                // [custom,category,{n},slug|label]
        $sort  = isset( $parts[2] ) && is_numeric( $parts[2] ) ? (int) $parts[2] : false;
        if ( ! $sort ) {
          continue;                                 // malformed → skip
        }

        $piece = end( $parts );                      // 'slug' or 'label'
        $value = trim( $unwrap( $val ) );            // <- scalar  ('neu', 'Top 10', …)

        if ( $value === '' ) {
          continue;                                 // empty → skip
        }

        if ( $piece === 'slug' ) {
          $custom_categories[ $sort ]['slug']  = $value;
        } elseif ( $piece === 'label' ) {
          $custom_categories[ $sort ]['label'] = $value;
        }
      }

      $this->setOfferCategories(
        $wp_post,
        $property['property_categories'],
        $custom_categories,
        $casawp_id
      );
    }

    #$this->addToLog('updating custom regions');
    $custom_regions = array();
    foreach ( $publisher_options as $key => $val ) {
        if ( strpos($key, 'custom_region_') === 0 ) {
            $parts = explode('_', $key);
            $sort  = isset($parts[2]) && is_numeric($parts[2]) ? (int)$parts[2] : false;
            $piece = end($parts);                      // slug / label
            $value = trim( $scalar($val) );            // <-- no [0] deref

            if ( $value === '' || ! $sort ) { continue; }

            if ( $piece === 'slug' ) {
                $custom_regions[$sort]['slug']  = $value;
            } elseif ( $piece === 'label' ) {
                $custom_regions[$sort]['label'] = $value;
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
    // mark this post as “seen in the current run”
    update_post_meta( $wp_post->ID, 'last_processed_run', $this->current_run_id );

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

      /* -----------------------------------------------------------------------
       *  ➜  FEATURED IMAGE  (first image by menu_order)
       * -------------------------------------------------------------------- */
      $first_image = get_posts( [
          'post_type'   => 'attachment',
          'numberposts' => 1,            // just one
          'post_status' => 'inherit',
          'post_parent' => $wp_post->ID,
          'orderby'     => 'menu_order',
          'order'       => 'ASC',
          'tax_query'   => [
              [
                  'taxonomy' => 'casawp_attachment_type',
                  'field'    => 'slug',
                  'terms'    => [ 'image' ],
              ],
          ],
      ] );

      if ( $first_image ) {
          $img_id = $first_image[0]->ID;

          // update only if it changed – keeps the DB tidy
          if ( get_post_thumbnail_id( $wp_post->ID ) != $img_id ) {
              set_post_thumbnail( $wp_post->ID, $img_id );
              $this->transcript[ $casawp_id ]['attachments']['featured_image_set'] = $img_id;
          }
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

      wp_set_object_terms($wp_post->ID, $new_term_id, 'casawp_salestype');

      if ( $new_term_id ) {
          update_post_meta( $wp_post->ID,
                            '_hash_salestype',
                            get_term( $new_term_id )->slug );
      }
      
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

      wp_set_object_terms($wp_post->ID, $new_term_id, 'casawp_availability');
      if ( $new_term_id ) {
          update_post_meta( $wp_post->ID,
                            '_hash_availability',
                            get_term( $new_term_id )->slug );
      }
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

      $slugs = [];
      foreach ( wp_get_object_terms( $wp_post->ID, 'casawp_location' ) as $t ) {
          $slugs[] = $t->slug;
      }
      sort( $slugs, SORT_STRING );
      update_post_meta( $wp_post->ID, '_hash_location', implode( '|', $slugs ) );

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
        $custom_categorylabels[$slug] = $slug;
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
      $slugs = [];
      foreach ( wp_get_object_terms( $wp_post->ID, 'casawp_category' ) as $t ) {
          $slugs[] = $t->slug;
      }
      sort( $slugs, SORT_STRING );
      update_post_meta( $wp_post->ID, '_hash_categories', implode( '|', $slugs ) );
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
        wp_set_object_terms($wp_post->ID, $term_ids, 'casawp_feature');
      } else {
        wp_set_object_terms($wp_post->ID, array(), 'casawp_feature');
      }

      $slugs = [];
      foreach ( wp_get_object_terms( $wp_post->ID, 'casawp_feature' ) as $t ) {
          $slugs[] = $t->slug;
      }
      sort( $slugs, SORT_STRING );
      update_post_meta( $wp_post->ID, '_hash_features', implode( '|', $slugs ) );

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
        wp_set_object_terms($wp_post->ID, $term_ids, 'casawp_utility');
      } else {
        wp_set_object_terms($wp_post->ID, array(), 'casawp_utility');
      }
      $slugs = [];
      foreach ( wp_get_object_terms( $wp_post->ID, 'casawp_utility' ) as $t ) {
          $slugs[] = $t->slug;
      }
      sort( $slugs, SORT_STRING );
      update_post_meta( $wp_post->ID, '_hash_utilities', implode( '|', $slugs ) );

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
        wp_set_object_terms($wp_post->ID, $term_ids, 'casawp_region');
      } else {
        wp_set_object_terms($wp_post->ID, array(), 'casawp_region');
      }
      $slugs = [];
      foreach ( wp_get_object_terms( $wp_post->ID, 'casawp_region' ) as $t ) {
          $slugs[] = $t->slug;
      }
      sort( $slugs, SORT_STRING );
      update_post_meta( $wp_post->ID, '_hash_regions', implode( '|', $slugs ) );

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
