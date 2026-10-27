<?php

namespace casawp;

use Exception;

if ( ! class_exists( 'CASAWP_Bulk_Meta' ) ) {
  final class CASAWP_Bulk_Meta {
    public static function sync( int $post_id, array $new ): void {
      global $wpdb;
      $tbl = $wpdb->postmeta;

      $protected = [ 'casawp_id', 'projectunit_id', 'projectunit_sort' ];

      // Add SEO meta keys that must never be touched by CASAWP imports
      $seo_like = [
        '_yoast_wpseo_%',     // Yoast
        '_rank_math_%',       // RankMath (optional)
        '_aioseo_%',          // All in One SEO (optional)
      ];

      foreach ($seo_like as $like) {
        $keys = $wpdb->get_col(
          $wpdb->prepare("SELECT meta_key FROM $tbl WHERE post_id = %d AND meta_key LIKE %s", $post_id, $like)
        );
        if ($keys) {
          $protected = array_merge($protected, $keys);
        }
      }

      // Optional: allow site code to extend protected keys
      $protected = apply_filters('casawp_protected_postmeta_keys', array_values(array_unique($protected)), $post_id, $new);

      foreach ( $new as $k => $v ) {
        // If a key is protected, do not overwrite it even if it shows up in payload
        if ( in_array( $k, $protected, true ) ) {
          continue;
        }

        $v = maybe_serialize( $v );

        $wpdb->delete($tbl, [ 'post_id' => $post_id, 'meta_key' => $k ], [ '%d', '%s' ]);
        $wpdb->insert($tbl, [ 'post_id' => $post_id, 'meta_key' => $k, 'meta_value' => $v ], [ '%d', '%s', '%s' ]);
      }

      // Drop stale keys that disappeared from payload, but keep protected
      $placeholders = implode(',', array_fill(0, count($new), '%s'));
      $wpdb->query(
        $wpdb->prepare(
          "DELETE FROM $tbl
           WHERE post_id = %d
             AND meta_key NOT IN ( $placeholders )
             AND meta_key NOT IN ('" . implode("','", array_map('esc_sql', $protected)) . "')",
          array_merge([ $post_id ], array_keys($new))
        )
      );

      wp_cache_delete( $post_id, 'post_meta' );
    }
  }
}

class Import
{
  private const MEDIA_SYNC_SCHEMA_VERSION = 1;
  private const CLEANUP_BATCH_SIZE = 10;
  private const CLEANUP_TIME_LIMIT = 45;
  private const SKIPPED_FEATURE_SLUGS = array('has-evelator');
  private int $log_cleanup_bytes = 0;

  public $importFile = false;
  public $main_lang = false;
  public $WPML = null;
  public $transcript = array();
  public $curtrid = false;
  public $trid_store = array();

  private $ranksort = array();

  private int $current_run_id = 0;
  private string $media_download_error = '';
  private array $logged_media_failure_skips = [];
  private ?ImportLogger $import_logger = null;
  private string $feed_fingerprint = '';
  private bool $import_start_logged = false;

  private function logger(): ImportLogger
  {
    if ($this->import_logger === null) {
      $this->import_logger = new ImportLogger(function () {
        return $this->current_run_id ?: (int) get_option('casawp_current_run_id', 0);
      });
    }
    return $this->import_logger;
  }

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
    add_action('casawp_delete_outdated_properties', array($this, 'delete_outdated_properties'), 10, 2);
    add_action('casawp_finalize_import_cleanup_batch', array($this, 'handle_finalize_import_cleanup_batch'), 10, 2);
  }

  public function start_import() {

      // mint / reuse run-id
      $run_id = (int) get_option( 'casawp_current_run_id', 0 );
      if ( ! $run_id ) {
          $run_id = time();                              // unique
          update_option( 'casawp_current_run_id', $run_id, 'no' );
          ManualCleanup::dismissCompletionNotice();
          update_option('casawp_import_progress_run_id', $run_id, false);
      }

      $this->logImportStart();

      // schedule batch #1 in its *own* group
      $action_id = as_schedule_single_action(
          time() + 10,
          'casawp_batch_import',
          [ 'batch_number' => 1, 'run_id' => $run_id ],
          'casawp_run_' . $run_id       // ← group
      );
      $this->logScheduledAction($action_id, 'casawp_batch_import', 1, $run_id, 10);
  }

  private function logScheduledAction($action_id, string $hook, int $batch, int $run_id, int $delay): void
  {
    $details = [
      'event' => $action_id ? 'scheduler.scheduled' : 'scheduler.failed',
      'action_id' => (int) $action_id,
      'hook' => $hook,
      'scheduled_batch_number' => $batch,
      'run_id' => $run_id,
      'delay_seconds' => $delay,
    ];
    if ($hook === 'casawp_finalize_import_cleanup_batch') {
      $details['cleanup_phase'] = $this->cleanupPhaseForRun($run_id);
    }
    $this->logImport($action_id ? 'Import action scheduled.' : 'Import action could not be scheduled.', $details, $action_id ? 'info' : 'error');
  }

  public function init_single_run( int $run_id ): void {
    $this->current_run_id = $run_id;
  }

  public function logImportStart(): void
  {
    if (!$this->import_start_logged) {
      $this->logger()->separator('Start of import', 'import.separator.start');
      $this->import_start_logged = true;
    }
  }

  private function logCleanupStart(int $run_id): void
  {
    $progress = get_option('casawp_import_cleanup_progress', []);
    if (($progress['run_id'] ?? 0) !== $run_id || empty($progress['separator_logged'])) {
      $this->logger()->separator('Start of cleanup', 'cleanup.separator.start', ['cleanup_phase' => 'properties']);
      update_option('casawp_import_cleanup_progress', ['run_id' => $run_id, 'phase' => 'properties', 'separator_logged' => true], false);
    }
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

    static $skip_exact = [
      'last_import_hash',
      'last_processed_run',
      'is_active',
      '_thumbnail_id',
      '_wpml_word_count',
      '_yoast_indexnow_last_ping',
      '_last_translation_edit_mode',
      'casawp_id',
      'projectunit_id',
      'projectunit_sort',
      'casawp_media_sync_version',
    ];

    $skip_prefixes = [
      '_yoast_wpseo_', // Yoast
      '_rank_math_',   // optional
      '_aioseo_',      // optional
    ];

    foreach ($skip_exact as $k) {
      unset($meta[$k]);
    }

    foreach (array_keys($meta) as $k) {
      foreach ($skip_prefixes as $p) {
        if (str_starts_with($k, $p)) {
          unset($meta[$k]);
          break;
        }
      }
    }

    $this->deepKsort($meta);
    return md5(serialize($meta));
  }



  private function group(): string {
      // when the handler is already inside a batch, use the cached ID
      if ( $this->current_run_id ) {
          return 'casawp_run_' . $this->current_run_id;
      }
      // fallback for places that call group() before we set the property
      return 'casawp_run_' . (int) get_option( 'casawp_current_run_id', 0 );
  }

  private function needsMediaSyncMigration(int $post_id): bool
  {
      return (int) get_post_meta($post_id, 'casawp_media_sync_version', true) < self::MEDIA_SYNC_SCHEMA_VERSION;
  }

  private function maybeMarkMediaSyncMigrationComplete(int $post_id, string $casawp_id): void
  {
      $attachment_failures = $this->transcript[$casawp_id]['attachments']['failed_to_create'] ?? [];

      if (!empty($attachment_failures)) {
          $this->markFileCleanupFailed();
          return;
      }

      update_post_meta($post_id, 'casawp_media_sync_version', self::MEDIA_SYNC_SCHEMA_VERSION);
  }


  private function fetchFileFromCasaGateway(): string
  {
      $this->logger()->setStage('gateway_fetch');
      $started_at = microtime(true);
      $this->addToLog('CASAWP: Start fetching fresh XML from CasaGateway at ' . time());

      $apikey     = get_option('casawp_api_key');
      $privatekey = get_option('casawp_private_key');
      $apiurl     = 'https://casagateway.ch/rest/publisher-properties';
      $options    = [
          'format' => 'casa-xml',
          'debug'  => 1,
      ];

      if (!$apikey || !$privatekey) {
          $this->logImport('CASAWP: gateway keys missing', ['event' => 'gateway.credentials_missing'], 'error');
          throw new \Exception('API Keys missing.');
      }
      if (!function_exists('curl_version')) {
          $this->logImport('CASAWP: gateway ERR (CURL MISSING!!!)', [], 'error');
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
          $curl_errno = curl_errno($ch);
          $this->logImport('CasaGateway request finished.', [
              'event' => 'gateway.response',
              'endpoint' => $apiurl,
              'http_status' => (int) $httpCode,
              'curl_errno' => $curl_errno,
              'curl_error' => curl_error($ch),
              'duration_seconds' => round(microtime(true) - $started_at, 3),
              'response_bytes' => is_string($response) ? strlen($response) : 0,
              'content_type' => curl_getinfo($ch, CURLINFO_CONTENT_TYPE),
          ], ($curl_errno || $httpCode >= 400 || !$httpCode) ? 'error' : 'info');
          if ($httpCode == 404) {
              throw new \Exception('Received 404 from CasaGateway.');
          }
      } catch (\Exception $e) {
          throw $e;
      } finally {
          curl_close($ch);
      }

      if (!$response || is_numeric($response)) {
          $this->addToLog('CASAWP: Invalid response from gateway');
          throw new \Exception('Invalid response from CasaGateway.');
      }

      $importDir = CASASYNC_CUR_UPLOAD_BASEDIR . '/casawp/import';
      $this->logger()->setStage('feed_save', ['feed_file' => $importDir . '/data.xml']);
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
    // Manual and scheduled gateway entry points already hold the import lock.
    if ($already_locked) { $this->logImportStart(); }
    return $this->logger()->run('gateway_import', [], function () use ($already_locked) {
      return $this->retrieveGatewayImport($already_locked);
    });
  }

  private function retrieveGatewayImport(bool $already_locked)
  {
    if ( ! $already_locked && ! casawp_acquire_lock() ) {
        casawp_set_pending();
        $this->addToLog( 'Import already running - poke queued.' );
        return;
    }

    $this->logImportStart();
    $this->addToLog('gateway file retrieval start: ' . time());
    $this->current_run_id = (int) get_option('casawp_current_run_id', 0);

    try {

      $filePath = $this->fetchFileFromCasaGateway();

      if ($filePath && $this->getImportFile()) {
        // A successful download is not necessarily a valid property feed.
        // Validate before scheduling deletion work or changing existing posts.
        $this->readImportXml($this->getImportFile());

        $run_id = (int) get_option( 'casawp_current_run_id', 0 );
        if ( ! $run_id ) {
          $run_id = time();
          update_option( 'casawp_current_run_id', $run_id, 'no' );
          ManualCleanup::dismissCompletionNotice();
          update_option('casawp_import_progress_run_id', $run_id, false);
        }
        $this->current_run_id = $run_id;

        if (function_exists('as_unschedule_all_actions')) {
          as_unschedule_all_actions('casawp_delete_outdated_properties');
        }
        $this->logger()->setStage('import_initialization');
        $action_id = as_schedule_single_action(
          time(),
          'casawp_delete_outdated_properties',
          [ 'batch_number' => 1, 'run_id' => $run_id ],
          $this->group()
        );
        $this->logScheduledAction($action_id, 'casawp_delete_outdated_properties', 1, $run_id, 0);

        delete_option('casawp_import_canceled');
        $this->deactivate_all_properties();
        $this->start_import();
        $this->addToLog('import start');

      }

    } catch (\Exception $e) {
        $this->logger()->exception('Import failed during gateway retrieval or initialization.', $e);
        if ($e instanceof \UnexpectedValueException) {
          $this->abortInvalidFeed(true);
        } else {
          update_option('casawp_import_failed', true);
        }
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
      'suppress_filters' => true,
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
  public function finalize_import_cleanup(): bool {

    $run_id = (int) get_option( 'casawp_current_run_id', 0 );
    if ( ! $run_id ) {
      return true;                                    // no active run → bail
    }
    if ( (int) get_option( 'casawp_cleanup_done_for_run', 0 ) === $run_id ) {
      return true;                                    // already cleaned up → bail
    }

    $this->current_run_id = $run_id;
    $this->addToLog( 'Finalizing import cleanup.' );

    if ( $this->has_pending_import_batches() ) {
        $this->addToLog( 'Cleanup postponed - batches still pending.' );
        return false;
    }

    if ( get_option( 'casawp_import_canceled' ) ) {
      $this->addToLog( 'Import was canceled - cleanup skips deletions.' );
      casawp_release_lock();
      delete_option( 'casawp_current_run_id' );
      return true;
    }

    if ( get_option( 'casawp_import_failed' ) ) {
      $this->addToLog( 'Import marked as failed. Skipping deletion of inactive properties.' );
      delete_option( 'casawp_import_failed' );
      casawp_release_lock();
      delete_option( 'casawp_current_run_id' );
      return true;
    }

    $this->logCleanupStart($run_id);
    if ( function_exists( 'as_schedule_single_action' ) ) {
      if ( ! as_next_scheduled_action( 'casawp_finalize_import_cleanup_batch', null, $this->group() ) ) {
        $this->schedule_finalize_import_cleanup_batch( $run_id, 1, 0 );
        $this->addToLog( 'Import cleanup queued: database properties, database attachments, files, taxonomy terms, then old logs.' );
      }
      return false;
    }

    $this->handle_finalize_import_cleanup_batch( 1, $run_id );
    return (int) get_option( 'casawp_cleanup_done_for_run', 0 ) === $run_id;
  }

  private function cleanup_batch_size(): int {
    return max( 1, (int) apply_filters( 'casawp_cleanup_batch_size', self::CLEANUP_BATCH_SIZE ) );
  }

  private function cleanup_time_limit(): int {
    return max( 5, (int) apply_filters( 'casawp_cleanup_time_limit', self::CLEANUP_TIME_LIMIT ) );
  }

  /**
   * Check only future import batches in this run.
   *
   * as_next_scheduled_action() also returns a running action. When this is
   * called by the final batch, that action is the current batch itself, which
   * used to postpone cleanup forever and leave the import lock in place.
   */
  private function has_pending_import_batches(): bool {
    if ( ! class_exists( '\\ActionScheduler' ) || ! class_exists( '\\ActionScheduler_Store' ) ) {
      return false;
    }

    $action_id = \ActionScheduler::store()->query_action( [
      'hook'   => 'casawp_batch_import',
      'status' => \ActionScheduler_Store::STATUS_PENDING,
      'group'  => $this->group(),
    ] );

    return null !== $action_id;
  }

  private function schedule_finalize_import_cleanup_batch( int $run_id, int $batch_number, int $delay = 0 ): void {
    $action_id = as_schedule_single_action(
      time() + $delay,
      'casawp_finalize_import_cleanup_batch',
      [ 'batch_number' => $batch_number, 'run_id' => $run_id ],
      'casawp_run_' . $run_id
    );
    $this->logScheduledAction($action_id, 'casawp_finalize_import_cleanup_batch', $batch_number, $run_id, $delay);
  }

  private function get_posts_to_remove_for_run( int $run_id, int $limit ): array {
    return get_posts( [
      'posts_per_page' => $limit,
      'post_type'      => 'casawp_property',
      'post_status'    => 'publish',
      'fields'         => 'ids',
      'suppress_filters' => true,
      'meta_query'     => [
        'relation' => 'OR',
        [
          'key'     => 'last_processed_run',
          'compare' => 'NOT EXISTS',
        ],
        [
          'key'     => 'last_processed_run',
          'value'   => $run_id,
          'compare' => '!=',
          'type'    => 'NUMERIC',
        ],
      ],
    ] );
  }

  private function delete_property_with_attachments( int $post_id ): array {
    $this->logger()->setStage('property_delete', ['post_id' => $post_id, 'attachment_id' => null]);
    $attachments = get_posts( [
      'post_type'      => 'attachment',
      'posts_per_page' => -1,
      'post_status'    => 'any',
      'post_parent'    => $post_id,
      'fields'         => 'ids',
      'suppress_filters' => true,
    ] );

    $deleted_attachments = 0;
    $failed_attachments  = 0;

    foreach ( $attachments as $attachment_id ) {
      $this->logger()->setStage('attachment_delete', ['attachment_id' => (int) $attachment_id]);
      if ( wp_delete_attachment( $attachment_id, true ) ) {
        $deleted_attachments++;
      } else {
        $failed_attachments++;
        $this->logImport( "Failed to delete attachment ID: $attachment_id", [], 'error' );
      }
    }

    $this->logger()->setStage('property_delete', ['attachment_id' => null]);
    if ( wp_delete_post( $post_id, true ) ) {
      return [
        'properties'         => 1,
        'attachments'        => $deleted_attachments,
        'attachment_failures' => $failed_attachments,
      ];
    }

    $fallback = wp_update_post( [
      'ID'          => $post_id,
      'post_status' => 'draft',
    ], true );

    if ( ! is_wp_error( $fallback ) && $fallback ) {
      $this->logImport( "Failed to delete property ID: $post_id; moved to draft instead.", [], 'warning' );
      return [
        'properties'         => 1,
        'attachments'        => $deleted_attachments,
        'attachment_failures' => $failed_attachments,
      ];
    }

    if ( $this->current_run_id ) {
      update_post_meta( $post_id, 'last_processed_run', $this->current_run_id );
    }

    $this->logImport( "Failed to delete property ID: $post_id", [
      'fallback_error' => is_wp_error($fallback) ? $fallback->get_error_message() : 'Draft update returned no post ID',
    ], 'error' );
    return [
      'properties'         => 0,
      'attachments'        => $deleted_attachments,
      'attachment_failures' => $failed_attachments,
    ];
  }

  public function handle_finalize_import_cleanup_batch( $batch_number = 1, $run_id = 0 ): void {
    $phase = $this->cleanupPhaseForRun($run_id ? (int) $run_id : (int) get_option('casawp_current_run_id', 0));
    $this->logger()->run('final_cleanup', array_merge(['batch_number' => (int) $batch_number, 'cleanup_phase' => $phase], $run_id ? ['run_id' => (int) $run_id] : []), function () use ($batch_number, $run_id) {
      return $this->finalizeImportCleanupBatch($batch_number, $run_id);
    }, 'info', function (int $finished_run) {
      if ($finished_run) { $this->complete_import_cleanup($finished_run); }
    });
  }

  private function cleanupPhaseForRun(int $run_id): string
  {
    $state = get_option('casawp_import_file_cleanup', []);
    if (is_array($state) && ($state['run_id'] ?? 0) === $run_id) {
      return empty($state['legacy_done']) ? 'attachments' : (!empty($state['stack']) ? 'files' : 'terms');
    }
    return $run_id && $this->get_posts_to_remove_for_run($run_id, 1) ? 'properties' : 'attachments';
  }

  private function finalizeImportCleanupBatch($batch_number, $run_id): int {
    $run_id = $run_id ? (int) $run_id : (int) get_option( 'casawp_current_run_id', 0 );
    if ( ! $run_id || $run_id !== (int) get_option('casawp_current_run_id', 0) ) {
      return 0;
    }

    if ( (int) get_option( 'casawp_cleanup_done_for_run', 0 ) === $run_id ) {
      return 0;
    }

    $this->current_run_id = $run_id;

    if ( get_option( 'casawp_import_canceled' ) ) {
      $this->addToLog( 'Import was canceled - cleanup batch skips deletions.' );
      casawp_release_lock();
      delete_option( 'casawp_current_run_id' );
      return 0;
    }

    if ( get_option( 'casawp_import_failed' ) ) {
      $this->addToLog( 'Import marked as failed. Cleanup batch skips deletions.' );
      delete_option( 'casawp_import_failed' );
      casawp_release_lock();
      delete_option( 'casawp_current_run_id' );
      return 0;
    }

    try {
      // data.xml may have been replaced since the import batches ran.
      $this->readImportXml($this->getImportFile());
    } catch (\UnexpectedValueException $e) {
      $this->logger()->exception('Import cleanup stopped; existing properties were retained.', $e);
      $this->abortInvalidFeed();
      return 0;
    }

    $this->logCleanupStart($run_id);
    $progress = get_option('casawp_import_cleanup_progress', []);
    $progress['batch_number'] = max(1, (int) $batch_number);
    update_option('casawp_import_cleanup_progress', $progress, false);
    $started_at       = microtime( true );
    $deleted_posts    = 0;
    $deleted_media    = 0;
    $failed_media     = 0;
    $deleted_post_ids = [];
    $limit            = $this->cleanup_batch_size();

    foreach ( $this->get_posts_to_remove_for_run( $run_id, $limit ) as $post_id ) {
      $result = $this->delete_property_with_attachments( (int) $post_id );

      if ( $result['properties'] ) {
        $deleted_posts++;
        $deleted_post_ids[] = (int) $post_id;
      }
      $deleted_media += $result['attachments'];
      $failed_media  += $result['attachment_failures'];

      if ( ( microtime( true ) - $started_at ) >= $this->cleanup_time_limit() ) {
        break;
      }
    }

    $this->logger()->setStage('final_cleanup', ['post_id' => null, 'attachment_id' => null]);
    if ( $deleted_posts || $deleted_media || $failed_media ) {
      $this->addToLog(
        sprintf(
          'Database property cleanup batch %d deleted %d properties (%s), %d attachments, %d attachment failures.',
          (int) $batch_number,
          $deleted_posts,
          implode( ',', $deleted_post_ids ),
          $deleted_media,
          $failed_media
        )
      );
    }

    $remaining = $this->get_posts_to_remove_for_run( $run_id, 1 );
    if ( ! empty( $remaining ) ) {
      $this->schedule_finalize_import_cleanup_batch( $run_id, (int) $batch_number + 1 );
      return 0;
    }

    if (!$this->cleanupImportFiles($run_id)) {
      $this->schedule_finalize_import_cleanup_batch($run_id, (int) $batch_number + 1);
      return 0;
    }
    return $run_id;
  }

  private function cleanupImportFiles(int $run_id): bool
  {
    $this->logger()->setStage('import_file_cleanup');
    try {
      $xml = $this->readImportXml($this->getImportFile());
      if (!isset($xml->properties)) {
        throw new \RuntimeException('Feed has no properties node; file cleanup was skipped.');
      }
      $ids = [];
      foreach ($xml->properties->property as $property) {
        $id = (string) $property['id'];
        if ($id === '') {
          throw new \RuntimeException('Feed contains a property without an ID; file cleanup was skipped.');
        }
        $ids[] = $id;
      }
      $this->logger()->setStage('import_file_cleanup');
      $cleanup = new ImportFileCleanup(CASASYNC_CUR_UPLOAD_BASEDIR, $this->logger());
      return $cleanup->runBatch($run_id, $ids);
    } catch (\Throwable $e) {
      $this->logger()->exception('Import file cleanup stopped; remaining files were retained.', $e);
      return true;
    }
  }

  private function markFileCleanupFailed(): void
  {
    if ($this->current_run_id) {
      update_option('casawp_file_cleanup_failed_run', $this->current_run_id, false);
    }
  }

  private function rememberImportedFeed(): void
  {
    update_option('casawp_import_feed_fingerprint', [
      'run_id' => $this->current_run_id, 'sha256' => $this->feed_fingerprint,
    ], false);
  }

  private function complete_import_cleanup( int $run_id ): void {
    $progress = get_option('casawp_import_cleanup_progress', []);
    if (($progress['run_id'] ?? 0) !== $run_id) { $progress = ['run_id' => $run_id]; }
    $progress['phase'] = 'logs';
    update_option('casawp_import_cleanup_progress', $progress, false);
    $this->logger()->run('log_cleanup', ['cleanup_phase' => 'logs'], function () { $this->cleanup_log_files(); });
    $this->logger()->setStage('cleanup_finalize', ['cleanup_phase' => 'complete']);
    $file_totals = get_option('casawp_import_file_cleanup_totals', []);
    $file_bytes = is_array($file_totals) && ($file_totals['run_id'] ?? 0) === $run_id
      ? (int) ($file_totals['stats']['bytes'] ?? 0) : 0;
    $this->logImport(sprintf('Import cleanup total: %.2f MB freed (attachment/XML files: %.2f MB; old logs: %.2f MB).',
      ($file_bytes + $this->log_cleanup_bytes) / 1048576, $file_bytes / 1048576, $this->log_cleanup_bytes / 1048576), [
        'event' => 'cleanup.total',
        'freed_bytes' => $file_bytes + $this->log_cleanup_bytes,
        'attachment_xml_bytes' => $file_bytes,
        'old_log_bytes' => $this->log_cleanup_bytes,
      ]);
    $cleanup_summary = [
      'run_id' => $run_id, 'phase' => 'logs',
      'batch_number' => (int) ($progress['batch_number'] ?? 0),
      'stats' => is_array($file_totals) && ($file_totals['run_id'] ?? 0) === $run_id ? ($file_totals['stats'] ?? []) : [],
      'freed_bytes' => $file_bytes + $this->log_cleanup_bytes,
    ];
    update_option('casawp_import_cleanup_progress', $cleanup_summary, false);
    if (is_array($file_totals) && ($file_totals['run_id'] ?? 0) === $run_id) {
      delete_option('casawp_import_file_cleanup_totals');
    }
    flush_rewrite_rules();

    if ( class_exists( '\WpeCommon' ) ) {
      \WpeCommon::purge_varnish_cache();
      \WpeCommon::purge_memcached();
      $this->addToLog( 'Triggered WP Engine cache purge.' );
    }

    $cleanup_summary['finished_at'] = time();
    update_option('casawp_import_cleanup_progress', $cleanup_summary, false);
    update_option( 'casawp_cleanup_done_for_run', $run_id );

    if ( function_exists( 'as_unschedule_all_actions' ) ) {
      as_unschedule_all_actions( 'casawp_batch_import', null, 'casawp_run_' . $run_id );
      as_unschedule_all_actions( 'casawp_finalize_import_cleanup_batch', null, 'casawp_run_' . $run_id );
    }

    casawp_release_lock();
    delete_option( 'casawp_current_run_id' );
    $this->addToLog( 'Import lock cleared.' );
    $this->logger()->cleanupCompleted($cleanup_summary['stats'], $cleanup_summary['freed_bytes']);

    if ( casawp_has_pending() ) {
      casawp_clear_pending();
      $this->addToLog( 'Pending import triggered right after cleanup.' );
      casawp_start_new_import( 'Pending after previous run', false );
    }

    do_action( 'casawp_import_finished' );
  }


  public function handle_properties_import_batch($batch_number, $run_id = 0)
  {
    return $this->logger()->run('properties_batch', array_merge(['batch_number' => (int) $batch_number], $run_id ? ['run_id' => (int) $run_id] : []), function () use ($batch_number, $run_id) {
      return $this->importPropertiesBatch($batch_number, $run_id);
    });
  }

  private function importPropertiesBatch($batch_number, $run_id)
  {

    if ( ! $this->current_run_id ) {
        $this->current_run_id = $run_id
            ? (int) $run_id
            : (int) get_option( 'casawp_current_run_id', 0 );

        if ( ! $this->current_run_id && $batch_number == 1 ) {
            $this->current_run_id = time();
            update_option( 'casawp_current_run_id', $this->current_run_id, 'no' );
            ManualCleanup::dismissCompletionNotice();
            update_option('casawp_import_progress_run_id', $this->current_run_id, false);
        }
    }

    $started_at = microtime( true );

    // Temporarily suspend cache additions for speed
    wp_suspend_cache_addition(true);
    if ( get_option('casawp_import_failed') ) {
      $this->logImport('Import already marked as failed. Skipping batch number: ' . $batch_number, ['event' => 'batch.skipped', 'reason' => 'failed'], 'warning');
      wp_suspend_cache_addition(false);
      return;
    }

    if (get_option('casawp_import_canceled', false)) {
      $this->logImport('Import has been canceled. Skipping batch number: ' . $batch_number, ['event' => 'batch.skipped', 'reason' => 'canceled'], 'warning');
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

      $xml = $this->readImportXml($this->getImportFile());
      if ((int) $batch_number === 1) {
        $this->rememberImportedFeed();
      }

      // Only an explicitly valid empty properties list permits full cleanup.
      $properties = $xml->properties->property;

      if ( count($properties) === 0 ) {
        $this->addToLog('Feed contained zero properties - treating as full unpublish.');
        $this->finalize_import_cleanup();
        wp_suspend_cache_addition(false);
        return;
      }

      $properties_array = array();
      foreach ($properties as $property) {
        $this->logger()->setStage('property_conversion', ['property_id' => (string) $property['id']]);
        $properties_array[] = $this->property2Array($property);
      }
      $this->logger()->setStage('properties_batch', ['property_id' => null]);

      $total_items   = count($properties_array);
      $total_batches = ceil($total_items / $batch_size);

      if ($batch_number == 1) {
        update_option('casawp_total_batches', $total_batches);
        update_option('casawp_completed_batches', 0);
        update_option('casawp_current_rank', 0);
        $this->addToLog(
          sprintf(
            'Initialized import: %d properties, batch size %d, total batches %d.',
            $total_items,
            $batch_size,
            $total_batches
          )
        );
      }

      $items_for_current_batch = array_slice($properties_array, ($batch_number - 1) * $batch_size, $batch_size, true);

      $this->logImport('Processing import batch.', [
        'event' => 'batch.processing',
        'batch_size' => $batch_size,
        'total_batches' => (int) $total_batches,
        'property_ids' => array_column($items_for_current_batch, 'exportproperty_id'),
      ]);

      $this->updateOffers($items_for_current_batch);

      update_option('casawp_ranksort', $this->ranksort);
      update_option('casawp_completed_batches', $batch_number);
      $this->logImport('Completed import batch.', [
        'event' => 'batch.processed',
        'duration_seconds' => round(microtime(true) - $started_at, 3),
        'properties_processed' => count($items_for_current_batch),
      ], 'debug');

      if ($batch_number >= $total_batches) {
        $cleanup_finished = $this->finalize_import_cleanup();
        update_option('casawp_completed_batches', $total_batches);
        delete_option('casawp_current_rank');
        delete_option('casawp_ranksort');
        if ( $cleanup_finished ) {
          $this->addToLog('Import process completed.');
        } else {
          $this->addToLog('Import batches completed; database cleanup is queued, followed by filesystem, taxonomy, and old-log cleanup.');
        }
      } else {
        $next_batch_number = $batch_number + 1;

        /* ── calculate self-tuning delay ───────────── */
        $runtime = microtime( true ) - $started_at;   // seconds
        $delay   = min( max( ceil( $runtime ) + 8, 12 ), 90 );

        $this->logImport(
            sprintf(
                'Batch %d took %.2fs - next in %ds',
                $batch_number,
                $runtime,
                $delay
            ), [], 'debug'
        );

        /* If another batch with that number isn’t
           already queued in this run, schedule it.   */
        if ( ! as_next_scheduled_action(
                 'casawp_batch_import',
                 [ 'batch_number' => $next_batch_number, 'run_id' => $this->current_run_id ],
                 $this->group()
             ) ) {

            $action_id = as_schedule_single_action(
                time() + $delay,
                'casawp_batch_import',
                [ 'batch_number' => $next_batch_number, 'run_id' => $this->current_run_id ],
                $this->group()
            );
            $this->logScheduledAction($action_id, 'casawp_batch_import', (int) $next_batch_number, $this->current_run_id, (int) $delay);
        }
      }
    } catch (\Throwable $e) {
      if ($e instanceof \UnexpectedValueException) {
        $this->abortInvalidFeed();
      } else {
        $this->markFileCleanupFailed();
      }
      $this->logger()->exception('Import batch failed.', $e, [
        'event' => 'batch.failed',
        'duration_seconds' => round(microtime(true) - $started_at, 3),
      ]);
      if ($e->getMessage() === 'No properties found in XML.') {
        set_transient('casawp_no_properties_alert', 'No properties were found during the import. Please verify the data.', 60);
        casawp_release_lock();
      }
    }
    wp_suspend_cache_addition(false);
  }

  public function handle_single_request_import()
  {
    $this->logImportStart();
    return $this->logger()->run('single_request_import', ['batch_number' => 1], function () {
      try {
        return $this->importSingleRequest();
      } catch (\UnexpectedValueException $e) {
        $this->abortInvalidFeed();
        throw $e;
      } finally {
        wp_suspend_cache_addition(false);
      }
    });
  }

  private function importSingleRequest()
  {
      wp_suspend_cache_addition(true);

      // Possibly throws exceptions if something goes wrong
      $this->fetchFileFromCasaGateway(); 

      $xml = $this->readImportXml($this->getImportFile());
      $this->rememberImportedFeed();
      $this->deactivate_all_properties();

      $properties = $xml->properties->property;

      if ( count($properties) === 0 ) {
        $this->addToLog('Feed contained zero properties – treating as full unpublish.');
        $this->finalize_import_cleanup();
        wp_suspend_cache_addition(false);
        return;
      }

      // If we get here, parse the properties and import
      $properties_array = [];
      foreach ($properties as $property) {
          $this->logger()->setStage('property_conversion', ['property_id' => (string) $property['id']]);
          $properties_array[] = $this->property2Array($property);
      }
      $this->logger()->setStage('single_request_import', ['property_id' => null]);

      // Mark single batch for consistency
      update_option('casawp_total_batches', 1);
      update_option('casawp_completed_batches', 0);

      // Do the update
      $this->updateOffers($properties_array);

      // Mark done
      update_option('casawp_completed_batches', 1);

      // Clean up leftover properties
      $this->finalize_import_cleanup();
      wp_suspend_cache_addition(false);
  }

  /** Stop this run without letting queued workers delete data or clearing another run. */
  private function abortInvalidFeed(bool $owns_uninitialized_run = false): void
  {
    $active_run = (int) get_option('casawp_current_run_id', 0);
    if ($this->current_run_id !== $active_run || (!$active_run && !$owns_uninitialized_run)) {
      return;
    }
    update_option('casawp_import_failed', true);
    $this->markFileCleanupFailed();
    if ($this->current_run_id && function_exists('as_unschedule_all_actions')) {
      foreach (['casawp_batch_import', 'casawp_delete_outdated_properties', 'casawp_finalize_import_cleanup_batch'] as $hook) {
        as_unschedule_all_actions($hook, null, $this->group());
      }
    }
    delete_option('casawp_current_run_id');
    casawp_release_lock();
  }

  /** Parse and validate feeds before any property changes, restoring libxml's error mode. */
  private function readImportXml($file_path): \SimpleXMLElement
  {
    $this->logger()->setStage('feed_read', ['feed_file' => $file_path ?: null]);
    if (!$file_path || !is_readable($file_path)) {
      throw new \UnexpectedValueException('Failed to read import file.');
    }
    $xml_string = @file_get_contents($file_path);
    if ($xml_string === false) {
      throw new \UnexpectedValueException('Failed to read import file.');
    }

    $this->feed_fingerprint = hash('sha256', $xml_string);
    $this->logger()->setStage('feed_parse', [
      'feed_bytes' => strlen($xml_string),
      'feed_sha256' => $this->feed_fingerprint,
    ]);
    $previous = libxml_use_internal_errors(true);
    libxml_clear_errors();
    try {
      $xml = simplexml_load_string($xml_string, 'SimpleXMLElement', LIBXML_NOCDATA);
      if ($xml === false) {
        $errors = [];
        foreach (array_slice(libxml_get_errors(), 0, 10) as $error) {
          $errors[] = [
            'code' => $error->code,
            'line' => $error->line,
            'column' => $error->column,
            'message' => trim($error->message),
          ];
        }
        $this->logImport('XML parser diagnostics.', ['event' => 'feed.parse_errors', 'xml_errors' => $errors], 'warning');
        throw new \UnexpectedValueException('Failed to parse XML.');
      }
      $this->logger()->setStage('feed_validation');
      if (!isset($xml->properties) || count($xml->properties) !== 1) {
        throw new \UnexpectedValueException('Invalid property feed: expected one properties node.');
      }
      $properties = $xml->properties;
      if (trim((string) $properties) !== '') {
        throw new \UnexpectedValueException('Invalid property feed: unexpected text in properties node.');
      }
      // Include namespaced children that ordinary ->property access would ignore.
      if (count($properties->xpath('./*')) !== count($properties->property)) {
        throw new \UnexpectedValueException('Invalid property feed: unexpected element in properties node.');
      }
      foreach ($properties->property as $property) {
        if (trim((string) $property['id']) === '') {
          throw new \UnexpectedValueException('Invalid property feed: property ID is missing or blank.');
        }
      }
      return $xml;
    } finally {
      libxml_clear_errors();
      libxml_use_internal_errors($previous);
    }
  }

  private function get_feed_property_ids(): ?array {
    $xml_file_path = CASASYNC_CUR_UPLOAD_BASEDIR . '/casawp/import/data.xml';
    if ( ! file_exists( $xml_file_path ) ) {
      $this->logImport( "XML file not found at: $xml_file_path", ['event' => 'feed.missing'], 'error' );
      return null;
    }

    try {
      $xml = $this->readImportXml($xml_file_path);
    } catch (\Throwable $e) {
      $this->logger()->exception('Unable to read feed for outdated property deletion.', $e);
      if ($e instanceof \UnexpectedValueException) {
        $this->abortInvalidFeed();
      }
      return null;
    }

    $xml_property_ids = [];
    foreach ( $xml->properties->property as $property ) {
      $id = (string) $property['id'];
      if ( $id !== '' ) {
        $xml_property_ids[] = $id;
      }
    }

    return array_values( array_unique( $xml_property_ids ) );
  }

  private function get_outdated_property_posts( array $xml_property_ids, int $limit ): array {
    $xml_property_lookup = array_fill_keys( $xml_property_ids, true );

    $query = new \WP_Query( [
      'post_type'      => 'casawp_property',
      'post_status'    => 'publish',
      'posts_per_page' => -1,
      'fields'         => 'ids',
      'meta_key'       => 'exportproperty_id',
      'suppress_filters' => true,
    ] );

    $posts = [];
    foreach ( $query->posts as $post_id ) {
      $exportproperty_id = (string) get_post_meta( $post_id, 'exportproperty_id', true );
      if ( $exportproperty_id && ! isset( $xml_property_lookup[ $exportproperty_id ] ) ) {
        $posts[] = (int) $post_id;
        if ( count( $posts ) >= $limit ) {
          break;
        }
      }
    }

    return $posts;
  }

  private function schedule_outdated_properties_delete_batch( int $run_id, int $batch_number, int $delay = 5 ): void {
    $action_id = as_schedule_single_action(
      time() + $delay,
      'casawp_delete_outdated_properties',
      [ 'batch_number' => $batch_number, 'run_id' => $run_id ],
      $run_id ? 'casawp_run_' . $run_id : 'casawp_delete_outdated_properties'
    );
    $this->logScheduledAction($action_id, 'casawp_delete_outdated_properties', $batch_number, $run_id, $delay);
  }

  public function delete_outdated_properties( $batch_number = 1, $run_id = 0 ): void {
    $this->logger()->run('outdated_cleanup', array_merge(['batch_number' => (int) $batch_number], $run_id ? ['run_id' => (int) $run_id] : []), function () use ($batch_number, $run_id) {
      $this->deleteOutdatedPropertiesBatch($batch_number, $run_id);
    });
  }

  private function deleteOutdatedPropertiesBatch($batch_number, $run_id): void {
    $run_id = $run_id ? (int) $run_id : (int) get_option('casawp_current_run_id', 0);
    if (!$run_id || $run_id !== (int) get_option('casawp_current_run_id', 0)) {
      return;
    }
    if (get_option('casawp_import_failed') || get_option('casawp_import_canceled')) {
      return;
    }
    $this->current_run_id = $run_id;

    if ( (int) $batch_number === 1 ) {
      $this->addToLog( 'Starting deletion of outdated properties.' );
    }

    $xml_property_ids = $this->get_feed_property_ids();
    if ( $xml_property_ids === null ) {
      return;
    }

    if ( empty( $xml_property_ids ) ) {
      $this->addToLog( 'Feed contained zero properties - skipping outdated pass, full clean-up will handle.' );
      return;
    }

    $this->logger()->setStage('outdated_cleanup');

    $started_at       = microtime( true );
    $deleted_posts    = 0;
    $deleted_media    = 0;
    $failed_media     = 0;
    $deleted_post_ids = [];

    foreach ( $this->get_outdated_property_posts( $xml_property_ids, $this->cleanup_batch_size() ) as $post_id ) {
      $result = $this->delete_property_with_attachments( (int) $post_id );

      if ( $result['properties'] ) {
        $deleted_posts++;
        $deleted_post_ids[] = (int) $post_id;
      }
      $deleted_media += $result['attachments'];
      $failed_media  += $result['attachment_failures'];

      if ( ( microtime( true ) - $started_at ) >= $this->cleanup_time_limit() ) {
        break;
      }
    }

    if ( $deleted_posts || $deleted_media || $failed_media ) {
      $this->addToLog(
        sprintf(
          'Outdated deletion batch %d deleted %d properties (%s), %d attachments, %d attachment failures.',
          (int) $batch_number,
          $deleted_posts,
          implode( ',', $deleted_post_ids ),
          $deleted_media,
          $failed_media
        )
      );

      if ( class_exists( '\WpeCommon' ) ) {
        \WpeCommon::purge_varnish_cache();
        \WpeCommon::purge_memcached();
        $this->addToLog( 'Triggered WP Engine cache purge after outdated deletion batch.' );
      }
    }

    if ( ! empty( $this->get_outdated_property_posts( $xml_property_ids, 1 ) ) ) {
      if ( function_exists( 'as_schedule_single_action' ) ) {
        $this->schedule_outdated_properties_delete_batch( $run_id, (int) $batch_number + 1 );
      }
      return;
    }

    $this->logger()->setStage('outdated_cleanup', ['post_id' => null, 'attachment_id' => null]);
    $this->addToLog( 'Deletion of outdated properties completed.' );
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

  private function getCurrentWPMLLanguage(): ?string
  {
    if (!$this->hasWPML()) {
      return null;
    }

    $lang = apply_filters('wpml_current_language', null);
    if (is_string($lang) && $lang !== '') {
      return $lang;
    }

    global $sitepress;
    if ($sitepress && method_exists($sitepress, 'get_current_language')) {
      $lang = $sitepress->get_current_language();
      if (is_string($lang) && $lang !== '') {
        return $lang;
      }
    }

    return null;
  }

  private function switchWPMLLanguageContext(?string $lang): void
  {
    if (!$this->hasWPML() || !$lang) {
      return;
    }

    global $sitepress;
    if ($sitepress && method_exists($sitepress, 'switch_lang')) {
      $sitepress->switch_lang($lang, true);
      return;
    }

    do_action('wpml_switch_language', $lang);
  }

  private function withWPMLLanguage(?string $lang, callable $callback)
  {
    if (!$this->hasWPML() || !$lang) {
      return $callback();
    }

    $previous_lang = $this->getCurrentWPMLLanguage();

    if ($previous_lang !== $lang) {
      $this->switchWPMLLanguageContext($lang);
    }

    try {
      return $callback();
    } finally {
      if ($previous_lang && $previous_lang !== $lang) {
        $this->switchWPMLLanguageContext($previous_lang);
      }
    }
  }

  private function insertPostWithLanguage(array $post_data, string $lang): int
  {
    $previous_post_language = $_POST['icl_post_language'] ?? null;

    if ($this->hasWPML() && $lang !== '') {
      $_POST['icl_post_language'] = $lang;
    }

    try {
      return (int) $this->withWPMLLanguage($lang, function () use ($post_data) {
        $result = wp_insert_post($post_data, true);
        if (is_wp_error($result)) {
          $this->logImport('WordPress could not insert the offer.', [
            'event' => 'offer.insert_failed',
            'error_code' => $result->get_error_code(),
            'error' => $result->get_error_message(),
          ], 'error');
          return 0;
        }
        return $result;
      });
    } finally {
      if ($previous_post_language === null) {
        unset($_POST['icl_post_language']);
      } else {
        $_POST['icl_post_language'] = $previous_post_language;
      }
    }
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
      $post_type    = get_post_type($wp_post);
      $element_type = $post_type ? 'post_' . $post_type : 'post_casawp_property';

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

      $existing_translation = $wpdb->get_row( $wpdb->prepare(
              "SELECT translation_id, language_code, source_language_code
               FROM {$wpdb->prefix}icl_translations
               WHERE element_id = %d AND trid = %d LIMIT 1",
              $wp_post->ID, $trid
          ) );

      $expected_source_lang = ( $lang === $main_lang ? null : $default_lang );

      /* Skip if mapping already matches the intended language. */
      if (
          $existing_translation
          && $existing_translation->language_code === $lang
          && $existing_translation->source_language_code === $expected_source_lang
      ) {
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
      // If this offer was delivered by the feed, its own language is the source.
      if (!isset($offerData['source_lang'])) {
        $offerData['source_lang'] = $offerData['lang'];
      }
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
        $copy['source_lang'] = $carbon['source_lang'] ?? $carbon['lang'];
        $autoTranslate  = (bool) get_option('casawp_auto_translate_properties');
        $forceContent   = (bool) get_option('casawp_force_import_content');
        if ($autoTranslate) {
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
          if (!$forceContent) {
            $copy['descriptions'] = [];
            $copy['excerpt'] = '';
          }
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
        $this->logger()->setStage('property_offers', ['property_id' => $property['exportproperty_id']]);
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

          try {
            $this->logger()->run('offer', [
              'property_id' => $property['exportproperty_id'],
              'casawp_id' => $casawp_id,
              'language' => $offerData['lang'],
              'post_id' => null,
            ], function () use (&$found_posts, $curRank, $offer_pos, $property, $offerData, $casawp_id) {
              $this->logger()->setStage('offer_lookup');
              $this->withWPMLLanguage($offerData['lang'], function () use (&$found_posts, $curRank, $offer_pos, $property, $offerData, $casawp_id) {
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

                  $this->logger()->setStage('offer_insert');
                  $insert_id = $this->insertPostWithLanguage($the_post, $offerData['lang']);
                  if (!$insert_id) {
                    $this->logImport('WordPress did not return a post ID for the new offer.', ['event' => 'offer.insert_failed'], 'error');
                  }
                  update_post_meta($insert_id, 'casawp_id', $casawp_id);
                  update_post_meta($insert_id, 'is_active', true);
                  $wp_post = get_post($insert_id, OBJECT, 'raw');
                }

                wp_reset_postdata();

                $this->logger()->setStage('offer_update', [
                  'post_id' => $wp_post->ID,
                  'action' => $this->transcript[$casawp_id]['action'],
                ]);
                $rank_result = wp_update_post(array(
                  'ID' => $wp_post->ID,
                  'menu_order' => $curRank,
                ), true);
                if (is_wp_error($rank_result)) {
                  $this->logImport('WordPress could not update the offer rank.', [
                    'error_code' => $rank_result->get_error_code(),
                    'error' => $rank_result->get_error_message(),
                  ], 'error');
                }

                $this->ranksort[$wp_post->ID] = $curRank;

                $found_posts[] = $wp_post->ID;

                $this->updateOffer( $casawp_id, $offer_pos, $property, $offerData, $wp_post );

                $this->logger()->setStage('offer_wpml_link');
                $this->updateInsertWPMLconnection($wp_post, $offerData['lang'], $property['exportproperty_id']);
              });
            }, 'debug');
          } catch (\Throwable $e) {
            $this->markFileCleanupFailed();
            $this->transcript[$casawp_id]['action'] = 'error';
            $this->transcript[$casawp_id]['error'] = $e->getMessage();
            wp_reset_postdata();
            continue;
          }
        }
      }
    }

    $this->logger()->setStage('archive_statistics', ['property_id' => null]);
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
            $insert_id = $this->insertPostWithLanguage($the_post, $lang);

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
    $this->logger()->setStage('offer_prepare', [
      'property_id' => $property['exportproperty_id'],
      'casawp_id' => $casawp_id,
      'language' => $offer['lang'],
      'post_id' => $wp_post->ID,
    ]);

   /*  echo 'offer ' . '<pre>' . print_r($offer, true) . '</pre>';
    echo 'property ' . '<pre>' . print_r($property, true) . '</pre>';
    die(); */

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

    $heating = array();
    if (isset($property['heating'])) {
      foreach ($property['heating'] as $heat) {
        $heating[] = array(
          'generation' => $heat['generation'],
          'distribution' => $heat['distribution']
        );
      }
    }
    $new_meta_data['heating'] = $heating;

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

    if (isset($custom_metas['keywordsString'])) {
      $new_meta_data['keywords_string'] = $custom_metas['keywordsString'];
    }


    /* if ( $casawp_id === '353184de' ) { 
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
     *     - _hash_media:        membership (order-insensitive)
     *     - _hash_media_order:  sequence (order-sensitive)
     * ----------------------------------------------------------------*/
    $media_refs       = [];
    $media_refs_order = [];

    if ( ! empty( $offer['offer_medias'] ) && is_array( $offer['offer_medias'] ) ) {
        $pos = 0;
        foreach ( $offer['offer_medias'] as $m ) {
            $pos++;

            if ( ! empty( $m['media']['original_file'] ) ) {
                $ref = $m['media']['original_file'];
            } elseif ( ! empty( $m['url'] ) ) {
                $ref = $m['url'];
            } else {
                continue;
            }

            $ref = $this->normalizeCasawpOrigin($ref);
            if ($ref === '') {
                continue;
            }

            $media_refs[]       = $ref;
            $media_refs_order[] = $pos . ':' . $ref;
        }

        if ( $media_refs ) {
            $sorted = $media_refs;
            sort( $sorted, SORT_STRING | SORT_FLAG_CASE );
            $new_meta_data['_hash_media'] = md5( implode( '|', $sorted ) );
            $new_meta_data['_hash_media_order'] = md5( implode( '|', $media_refs_order ) );
        }
    }


    // Persist the true textual source language for this post (2-letter code)
    $source_lang = strtolower(
        sanitize_text_field( $offer['source_lang'] ?? ($offer['lang'] ?? $this->getMainLang()) )
    );
    $new_meta_data['content_source_lang'] = $source_lang;

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
    $newHash    = $this->fingerprint( $new_meta_data );
    $needs_media_sync_migration = $this->needsMediaSyncMigration($wp_post->ID);

    

    /* DEBUGGING HASHING */

   /*  if ( $casawp_id === '353188de' ) {  
        $this->addToLog( 'HASH_OLD '.$hashFromDb );
        $this->addToLog( 'HASH_NEW '.$newHash );
    }

    if ( $casawp_id === '353188de' ) { 

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
                $mismatch[ $k ] = [
                    'old' => is_scalar( $old_meta_data[ $k ] ) ? $old_meta_data[ $k ] : 'array('.count($old_meta_data[ $k ]).')',
                    'new' => is_scalar( $new_meta_data[ $k ] ) ? $new_meta_data[ $k ] : 'array('.count($new_meta_data[ $k ]).')',
                ];
            }
        }

        if ( $mismatch ) {
            $this->addToLog( 'MISMATCH '. print_r( $mismatch, true ) );
        }
    } */

    $main_unchanged = ( $new_main_data === $old_main_data );

    if ( $hashFromDb === $newHash && $main_unchanged && ! $needs_media_sync_migration ) {
        $this->logImport( "Skip {$casawp_id} - unchanged (hash)", ['event' => 'offer.unchanged'], 'debug' );
        update_post_meta( $wp_post->ID, 'is_active', true );
        update_post_meta( $wp_post->ID, 'last_processed_run', $this->current_run_id );
        return;
    }

    if ( $hashFromDb === $newHash && $main_unchanged && $needs_media_sync_migration ) {
        $this->logger()->setStage('offer_media');
        $this->addToLog( "Force media self-heal for {$casawp_id} - media sync migration pending" );
        $this->setOfferAttachments(
            isset($offer['offer_medias']) ? $offer['offer_medias'] : array(),
            $wp_post,
            $property['exportproperty_id'],
            $casawp_id,
            $property,
            !empty($offer['has_media_node'])
        );
        $this->maybeMarkMediaSyncMigrationComplete($wp_post->ID, $casawp_id);
        update_post_meta( $wp_post->ID, 'is_active', true );
        update_post_meta( $wp_post->ID, 'last_processed_run', $this->current_run_id );
        return;
    }

    $new_meta_data['last_import_hash'] = $newHash;



    /* ---------- main-post update ---------- */
    $this->logger()->setStage('offer_post_update');
    if ( $new_main_data !== $old_main_data ) {
      if ( ! $wp_post->post_name ) {
        $new_main_data['post_name'] = $this->casawp_sanitize_title( $casawp_id . '-' . $offer['name'] );
      } else {
        $new_main_data['post_name'] = $wp_post->post_name;
      }
      $post_result = wp_update_post( $new_main_data, true );
      if (is_wp_error($post_result)) {
        $this->logImport('WordPress could not update the offer.', [
          'event' => 'offer.update_failed',
          'error_code' => $post_result->get_error_code(),
          'error' => $post_result->get_error_message(),
        ], 'error');
      }
    }

    /* ---------- meta update (single statement) ---------- */
    $this->logger()->setStage('offer_meta_update');
    if ( $new_meta_data !== $old_meta_data ) {
      CASAWP_Bulk_Meta::sync( $wp_post->ID, $new_meta_data );
      update_post_meta( $wp_post->ID, 'last_import_hash', $newHash );
      $this->logImport( "Meta updated for {$casawp_id}", ['event' => 'offer.meta_updated'], 'debug' );
    }

    $this->logger()->setStage('offer_categories');
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

    $this->logger()->setStage('offer_regions');
    $this->setOfferRegions($wp_post, $custom_regions, $casawp_id);

    #$this->addToLog('updating features');
    $this->logger()->setStage('offer_features');
    $this->setOfferFeatures($wp_post, $property['features'], $casawp_id);

    #$this->addToLog('updating utilities');
    $this->logger()->setStage('offer_utilities');
    $this->setOfferUtilities($wp_post, $property['property_utilities'], $casawp_id);

    #$this->addToLog('updating salestypes');
    $this->logger()->setStage('offer_salestype');
    $this->setOfferSalestype($wp_post, $property['type'], $casawp_id);

    #$this->addToLog('updating availabilities');
    $this->logger()->setStage('offer_availability');
    $this->setOfferAvailability($wp_post, $property['availability'], $casawp_id);

    #$this->addToLog('updating localities');
    $this->logger()->setStage('offer_localities');
    $this->setOfferLocalities($wp_post, $property['address'], $casawp_id);

    #$this->addToLog('updating attachments');
    $this->logger()->setStage('offer_media');
    $this->setOfferAttachments(
        isset($offer['offer_medias']) ? $offer['offer_medias'] : array(),
        $wp_post,
        $property['exportproperty_id'],
        $casawp_id,
        $property,
        !empty($offer['has_media_node'])
    );
    $this->maybeMarkMediaSyncMigrationComplete($wp_post->ID, $casawp_id);
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

  private function isRemoteMediaUrl($value): bool
  {
      return is_string($value) && preg_match('#^https?://#i', $value) === 1;
  }

  private function normalizeCasawpOrigin($origin)
  {
      $origin = trim((string) $origin);
      if ($origin === '') {
          return '';
      }

      $origin = str_replace('%3F', '?', $origin);
      $origin = str_replace('%3D', '=', $origin);
      $origin = str_replace('http://', 'https://', $origin);
      $origin = str_replace('casagateway.ch', 'cdn.casasoft.com', $origin);

      return $origin;
  }

  private function shouldUseRemoteCasawpAttachment(array $the_mediaitem): bool
  {
      $source = !empty($the_mediaitem['url']) ? $the_mediaitem['url'] : $the_mediaitem['file'];

      return ImportMedia::usesRemote((string) ($the_mediaitem['type'] ?? ''), (string) $source,
          (bool) get_option('casawp_use_casagateway_cdn', false));
  }

  public function getCasawpImportedAttachments($post_id)
  {
      $attachments = get_posts(array(
          'post_type'      => 'attachment',
          'posts_per_page' => -1,
          'post_status'    => 'any',
          'post_parent'    => $post_id,
          'suppress_filters' => true,
          'meta_query'     => array(
              'relation' => 'OR',
              array(
                  'key'   => '_casawp_import',
                  'value' => '1',
              ),
              array(
                  'key'     => '_origin',
                  'compare' => 'EXISTS',
              ),
          ),
          'fields'  => 'all',
          'orderby' => 'ID',
          'order'   => 'ASC',
      ));

      $indexed = array();

      foreach ($attachments as $attachment) {
          $origin = $this->normalizeCasawpOrigin(get_post_meta($attachment->ID, '_origin', true));
          if (!$origin) {
              continue;
          }

          if (!isset($indexed[$origin])) {
              $indexed[$origin] = $attachment;
          } else {
              $this->addToLog(
                  'duplicate casawp attachment found for post ' . $post_id .
                  ' origin: ' . $origin .
                  ' keeping ' . $indexed[$origin]->ID .
                  ', duplicate ' . $attachment->ID
              );
          }
      }

      return $indexed;
  }

  public function casawpUploadAttachmentFromGateway($property_id, $fileurl)
  {
    $this->media_download_error = '';
    $download_source = strpos($fileurl, '://') ? urldecode($fileurl) : $fileurl;
    $is_remote = $this->isRemoteMediaUrl($download_source);

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

    // A working local copy remains usable even while this URL has a cooldown.
    if (is_file($full_path)) {
      return $filename;
    }

    if ($is_remote) {
      $cache_key = $this->mediaFailureCacheKey($download_source);
      $failure = get_transient($cache_key);
      if (is_array($failure) && (int) ($failure['retry_at'] ?? 0) > time()) {
        $this->media_download_error = sprintf(
          'Download skipped: cached %s; retry after %s',
          $failure['error_code'],
          gmdate('c', (int) $failure['retry_at'])
        );
        if (!isset($this->logged_media_failure_skips[$cache_key])) {
          $this->logImport('Remote media cooldown for requested URL: ' . $download_source . ' - ' . $this->media_download_error, ['event' => 'media.cooldown'], 'warning');
          $this->logged_media_failure_skips[$cache_key] = true;
        }
        return false;
      }
    }

    $directory = dirname($full_path);
    if (!is_dir($directory)) {
      if (!mkdir($directory, 0755, true)) {
        $this->media_download_error = 'Could not create media download directory';
        return false;
      }
    }

    if (!is_file($full_path)) {
      if (!isset($this->transcript['attachments'][$property_id]["uploaded_from_gateway"])) {
        $this->transcript['attachments'][$property_id]["uploaded_from_gateway"] = array();
      }
      $this->transcript['attachments'][$property_id]["uploaded_from_gateway"][] = $filename;

      if ($is_remote) {
        $could_copy = $this->downloadRemoteMedia($download_source, $full_path);
      } else {
        $could_copy = copy($download_source, $full_path);
      }

      if (!$could_copy) {
        $this->transcript['attachments'][$property_id]["uploaded_from_gateway"][] = 'FAILED: ' . $filename;
        return false;
      }
    }

    return $filename;
  }

  private function mediaFailureCacheKey(string $url): string
  {
    // Stay below the 45-character transient key limit in older WordPress versions.
    return 'casawp_mfail_' . md5($url);
  }

  private function cacheMediaDownloadFailure(string $url, int $status, string $error_code, string $message, float $started_at): void
  {
    $default_ttl = in_array($status, [404, 410], true) ? 86400 : 900;
    $ttl = max(1, (int) apply_filters('casawp_media_failure_cache_ttl', $default_ttl, $status, $error_code, $url));
    $retry_at = time() + $ttl;
    set_transient($this->mediaFailureCacheKey($url), [
      'http_status' => $status,
      'error_code'  => $error_code,
      'retry_at'    => $retry_at,
    ], $ttl);

    $this->media_download_error = sprintf('%s: %s; retry after %s', $error_code, $message, gmdate('c', $retry_at));
    $this->logImport(sprintf(
      'Remote media download failed for requested URL: %s status: %d duration: %.2fs reason: %s',
      $url,
      $status,
      microtime(true) - $started_at,
      $this->media_download_error
    ), [
      'event' => 'media.download_failed',
      'http_status' => $status,
      'error_code' => $error_code,
      'duration_seconds' => round(microtime(true) - $started_at, 3),
      'retry_at' => gmdate('c', $retry_at),
    ], 'error');
  }

  private function downloadRemoteMedia(string $url, string $full_path): bool
  {
    // Use the destination filesystem so the completed download can be renamed atomically.
    $temporary_path = tempnam(dirname($full_path), '.casawp-');
    if ($temporary_path === false) {
      $this->media_download_error = 'Could not create temporary media download file';
      return false;
    }
    if (realpath(dirname($temporary_path)) !== realpath(dirname($full_path))) {
      unlink($temporary_path);
      $this->media_download_error = 'Could not create temporary media download file in the destination directory';
      return false;
    }

    $started_at = microtime(true);
    try {
      $timeout = max(1, (int) apply_filters('casawp_media_download_timeout', 15, $url));
      $response = wp_safe_remote_get($url, [
        'timeout'     => $timeout,
        'redirection' => 3,
        'stream'      => true,
        'filename'    => $temporary_path,
      ]);

      if (is_wp_error($response)) {
        $this->cacheMediaDownloadFailure($url, 0, $response->get_error_code(), $response->get_error_message(), $started_at);
        return false;
      }

      $status = (int) wp_remote_retrieve_response_code($response);
      if ($status !== 200) {
        $this->cacheMediaDownloadFailure($url, $status, 'http_' . $status, wp_remote_retrieve_response_message($response), $started_at);
        return false;
      }

      clearstatcache(true, $temporary_path);
      if (!is_file($temporary_path) || filesize($temporary_path) === 0) {
        $this->cacheMediaDownloadFailure($url, $status, 'empty_file', 'Downloaded media file is empty', $started_at);
        return false;
      }

      // tempnam() creates private files; match WordPress upload permissions before publishing.
      $directory_permissions = fileperms(dirname($full_path));
      if ($directory_permissions === false || !chmod($temporary_path, $directory_permissions & 0666)) {
        $this->media_download_error = 'Could not set media download file permissions';
        return false;
      }

      if (!rename($temporary_path, $full_path)) {
        $this->media_download_error = 'Could not move completed media download into place';
        return false;
      }

      delete_transient($this->mediaFailureCacheKey($url));
      return true;
    } finally {
      if (is_file($temporary_path)) {
        unlink($temporary_path);
      }
    }
  }

  private function getAttachmentTypeTermBySlug(string $slug)
  {
      global $wpdb;

      $term_id = (int) $wpdb->get_var($wpdb->prepare(
          "SELECT t.term_id
           FROM {$wpdb->terms} t
           INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id
           WHERE tt.taxonomy = %s AND t.slug = %s
           ORDER BY tt.term_taxonomy_id ASC
           LIMIT 1",
          'casawp_attachment_type',
          $slug
      ));

      if (!$term_id) {
          return false;
      }

      $term = get_term($term_id, 'casawp_attachment_type');
      if (!$term || is_wp_error($term)) {
          return false;
      }

      return $term;
  }

  public function casawpUploadAttachment($the_mediaitem, $post_id, $property_id)
  {
      $source = '';

      if (!empty($the_mediaitem['url'])) {
          $source = trim((string) $the_mediaitem['url']);
      } elseif (!empty($the_mediaitem['file'])) {
          $source = trim((string) $the_mediaitem['file']);
      }

      if ($source === '') {
          return 'Missing media source';
      }

      $origin = $this->normalizeCasawpOrigin($source);

      $term = $this->getAttachmentTypeTermBySlug((string) $the_mediaitem['type']);
      if (!$term || is_wp_error($term)) {
          return 'Missing taxonomy term casawp_attachment_type:' . $the_mediaitem['type'];
      }

      $title = !empty($the_mediaitem['title'])
          ? sanitize_text_field($the_mediaitem['title'])
          : wp_basename(parse_url($origin, PHP_URL_PATH) ?: $origin);

      $caption = isset($the_mediaitem['caption']) ? (string) $the_mediaitem['caption'] : '';
      $alt     = isset($the_mediaitem['alt']) ? (string) $the_mediaitem['alt'] : '';

      // CDN attachments retain WordPress records without downloading local copies.
      if ($this->shouldUseRemoteCasawpAttachment($the_mediaitem)) {
          $mime = wp_check_filetype(parse_url($origin, PHP_URL_PATH), null);
          $mime_type = !empty($mime['type']) ? $mime['type']
              : ($the_mediaitem['type'] === 'image' ? 'image/jpeg' : 'application/octet-stream');

          $attachment = array(
              'guid'           => $origin,
              'post_mime_type' => $mime_type,
              'post_title'     => $title,
              'post_name'      => sanitize_title($title . '-' . md5($origin)),
              'post_content'   => '',
              'post_excerpt'   => $caption,
              'post_status'    => 'inherit',
              'post_parent'    => (int) $post_id,
              'menu_order'     => isset($the_mediaitem['order']) ? (int) $the_mediaitem['order'] : 0,
          );

          $attach_id = wp_insert_attachment($attachment, false, $post_id, true);

          if (is_wp_error($attach_id)) {
              return $attach_id->get_error_message();
          }

          update_post_meta($attach_id, '_origin', $origin);
          update_post_meta($attach_id, '_is_remote', 1);
          update_post_meta($attach_id, '_casawp_import', 1);
          update_post_meta($attach_id, '_wp_attachment_image_alt', $alt);

          if (!empty($the_mediaitem['width'])) {
              update_post_meta($attach_id, '_remote_width', (int) $the_mediaitem['width']);
          }
          if (!empty($the_mediaitem['height'])) {
              update_post_meta($attach_id, '_remote_height', (int) $the_mediaitem['height']);
          }

          wp_set_post_terms($attach_id, array((int) $term->term_id), 'casawp_attachment_type', false);

          return (int) $attach_id;
      }

      // Local file mode
      if (!empty($the_mediaitem['file'])) {
          $filename = '/casawp/import/attachment/' . ltrim($the_mediaitem['file'], '/');
      } else {
          $filename = $this->casawpUploadAttachmentFromGateway($property_id, $source);
      }

      if (!$filename) {
          return $this->media_download_error ?: 'File could not be downloaded';
      }

      $full_path = CASASYNC_CUR_UPLOAD_BASEDIR . $filename;
      if (!is_file($full_path)) {
          return $filename . ' could not be found!';
      }

      $wp_filetype = wp_check_filetype(basename($filename), null);
      $mime_type = !empty($wp_filetype['type'])
          ? $wp_filetype['type']
          : (($the_mediaitem['type'] === 'image') ? 'image/jpeg' : 'application/octet-stream');

      $guid = CASASYNC_CUR_UPLOAD_BASEURL . $filename;

      $attachment = array(
          'guid'           => $guid,
          'post_mime_type' => $mime_type,
          'post_title'     => $title,
          'post_name'      => sanitize_title($title . '-' . md5($origin)),
          'post_content'   => '',
          'post_excerpt'   => $caption,
          'post_status'    => 'inherit',
          'post_parent'    => (int) $post_id,
          'menu_order'     => isset($the_mediaitem['order']) ? (int) $the_mediaitem['order'] : 0,
      );

      $attach_id = wp_insert_attachment($attachment, $full_path, $post_id, true);

      if (is_wp_error($attach_id)) {
          return $attach_id->get_error_message();
      }

      if (strpos($mime_type, 'image/') === 0) {
          require_once(ABSPATH . 'wp-admin/includes/image.php');
          $attach_data = wp_generate_attachment_metadata($attach_id, $full_path);
          if (!empty($attach_data) && !is_wp_error($attach_data)) {
              wp_update_attachment_metadata($attach_id, $attach_data);
          }
      }

      update_post_meta($attach_id, '_origin', $origin);
      delete_post_meta($attach_id, '_is_remote');
      update_post_meta($attach_id, '_casawp_import', 1);
      update_post_meta($attach_id, '_wp_attachment_image_alt', $alt);

      wp_set_post_terms($attach_id, array((int) $term->term_id), 'casawp_attachment_type', false);

      return (int) $attach_id;
  }

  public function setOfferAttachments($offer_medias, $wp_post, $property_id, $casawp_id, $property, $has_media_node = true)
  {
      $allowed_types = ImportMedia::TYPES;
      $the_casawp_attachments = array();
      $order = 0;

      if (!empty($offer_medias)) {
          foreach ($offer_medias as $offer_media) {
              $type = isset($offer_media['type']) ? $offer_media['type'] : 'image';
              if (!in_array($type, $allowed_types, true)) {
                  continue;
              }

              $source = '';
              if (!empty($offer_media['media']['original_file'])) {
                  $source = $offer_media['media']['original_file'];
              } elseif (!empty($offer_media['url'])) {
                  $source = $offer_media['url'];
              }

              if ($source === '') {
                  continue;
              }

              $order++;

              $fallback_title = wp_basename(parse_url($source, PHP_URL_PATH) ?: $source);

              $the_casawp_attachments[] = array(
                  'type'    => $type,
                  'alt'     => isset($offer_media['alt']) ? $offer_media['alt'] : '',
                  'title'   => !empty($offer_media['title']) ? $offer_media['title'] : $fallback_title,
                  'file'    => '',
                  'url'     => $source,
                  'caption' => isset($offer_media['caption']) ? $offer_media['caption'] : '',
                  'order'   => $order,
              );
          }
      }

      if (get_option('casawp_limit_reference_images') && isset($property['availability']) && $property['availability'] === 'reference') {
          $title_image = false;
          foreach ($the_casawp_attachments as $attachment) {
              if ($attachment['type'] === 'image') {
                  $title_image = $attachment;
                  break;
              }
          }
          $the_casawp_attachments = $title_image ? array($title_image) : array();
      }

      if (empty($the_casawp_attachments)) {
          if (!$has_media_node) {
              $this->addToLog('skipping attachment cleanup because media node was missing for property ' . $casawp_id);
              return;
          }

          $existing_imported = $this->getCasawpImportedAttachments($wp_post->ID);

          foreach ($existing_imported as $origin => $attachment) {
              $this->logImport('removing casawp attachment because source list is empty: ' . $attachment->ID . ' origin: ' . $origin, ['event' => 'media.removed'], 'debug');
              $this->transcript[$casawp_id]['attachments']['removed'][] = $attachment->ID;
              wp_delete_attachment($attachment->ID, true);
          }

          delete_post_thumbnail($wp_post->ID);
          return;
      }

      $existing_by_origin = $this->getCasawpImportedAttachments($wp_post->ID);
      $to_remove = $existing_by_origin;

      $featured_attachment_id = 0;
      $seen_desired_origins = array();

      foreach ($the_casawp_attachments as $the_mediaitem) {
          $origin = !empty($the_mediaitem['url']) ? $the_mediaitem['url'] : $the_mediaitem['file'];
          $origin = $this->normalizeCasawpOrigin($origin);

          if ($origin === '' || isset($seen_desired_origins[$origin])) {
              continue;
          }
          $seen_desired_origins[$origin] = true;

          $term = $this->getAttachmentTypeTermBySlug((string) $the_mediaitem['type']);
          if (!$term || is_wp_error($term)) {
              $this->logImport('ERROR missing casawp_attachment_type term "' . $the_mediaitem['type'] . '"', ['event' => 'media.taxonomy_missing'], 'error');
              $this->transcript[$casawp_id]['attachments']['failed_to_create'][] =
                  'Missing taxonomy term casawp_attachment_type:' . $the_mediaitem['type'];
              continue;
          }
          $term_id = (int) $term->term_id;

          $should_be_remote = $this->shouldUseRemoteCasawpAttachment($the_mediaitem);

          $existing_attachment = isset($existing_by_origin[$origin]) ? $existing_by_origin[$origin] : null;
          if ($existing_attachment) {
              unset($to_remove[$origin]);
          }

          if ($existing_attachment) {
              $attachment_id = (int) $existing_attachment->ID;
              $current_is_remote = (bool) get_post_meta($attachment_id, '_is_remote', true);

              // If this was previously remote but now should be local, recreate it locally.
              if ($current_is_remote && !$should_be_remote) {
                  $replacement_id = $this->casawpUploadAttachment($the_mediaitem, $wp_post->ID, $property_id);

                  if (is_int($replacement_id)) {
                      $this->logImport('converted remote attachment ' . $attachment_id . ' to local attachment ' . $replacement_id . ' origin: ' . $origin, [], 'debug');
                      $this->transcript[$casawp_id]['attachments']['converted_to_local'][] = array(
                          'from' => $attachment_id,
                          'to'   => $replacement_id,
                      );

                      wp_delete_attachment($attachment_id, true);

                      $attachment_id = $replacement_id;
                      $existing_attachment = get_post($attachment_id);
                      $existing_by_origin[$origin] = $existing_attachment;
                  } else {
                      $this->logImport('failed to convert remote attachment to local for origin: ' . $origin . ' reason: ' . print_r($replacement_id, true), ['event' => 'media.conversion_failed', 'attachment_id' => $attachment_id], 'error');
                      $this->transcript[$casawp_id]['attachments']['failed_to_create'][] = array(
                          'origin' => $origin,
                          'reason' => $replacement_id,
                      );
                      continue;
                  }
              }

              $existing_attachment = get_post($attachment_id);
              $changed = false;
              $update_post = array('ID' => $attachment_id);

              if ((int) $existing_attachment->post_parent !== (int) $wp_post->ID) {
                  $update_post['post_parent'] = (int) $wp_post->ID;
                  $changed = true;
              }

              if ((string) $existing_attachment->post_title !== (string) $the_mediaitem['title']) {
                  $update_post['post_title'] = sanitize_text_field($the_mediaitem['title']);
                  $changed = true;
              }

              $new_excerpt = isset($the_mediaitem['caption']) ? (string) $the_mediaitem['caption'] : '';
              if ((string) $existing_attachment->post_excerpt !== $new_excerpt) {
                  $update_post['post_excerpt'] = $new_excerpt;
                  $changed = true;
              }

              if ((int) $existing_attachment->menu_order !== (int) $the_mediaitem['order']) {
                  $update_post['menu_order'] = (int) $the_mediaitem['order'];
                  $changed = true;
              }

              if (count($update_post) > 1) {
                  wp_update_post($update_post);
              }

              update_post_meta($attachment_id, '_origin', $origin);
              update_post_meta($attachment_id, '_casawp_import', 1);

              if ($should_be_remote) {
                  update_post_meta($attachment_id, '_is_remote', 1);
              } else {
                  delete_post_meta($attachment_id, '_is_remote');
              }

              $current_alt = (string) get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
              $new_alt = isset($the_mediaitem['alt']) ? (string) $the_mediaitem['alt'] : '';
              if ($current_alt !== $new_alt) {
                  update_post_meta($attachment_id, '_wp_attachment_image_alt', $new_alt);
                  $changed = true;
              }

              $current_terms = wp_get_post_terms($attachment_id, 'casawp_attachment_type', array('fields' => 'ids'));
              $current_terms = array_map('intval', is_array($current_terms) ? $current_terms : array());

              if ($current_terms !== array($term_id)) {
                  wp_set_post_terms($attachment_id, array($term_id), 'casawp_attachment_type', false);
                  $changed = true;
              }

              if ($changed) {
                  $this->logImport('updated casawp attachment ' . $attachment_id . ' origin: ' . $origin, ['event' => 'media.updated'], 'debug');
                  $this->transcript[$casawp_id]['attachments']['updated'][] = $attachment_id;
              } else {
                  $this->logImport('kept casawp attachment ' . $attachment_id . ' origin: ' . $origin, ['event' => 'media.kept'], 'debug');
              }
          } else {
              $new_id = $this->casawpUploadAttachment($the_mediaitem, $wp_post->ID, $property_id);

              if (is_int($new_id)) {
                  $attachment_id = $new_id;
                  $this->logImport('created casawp attachment ' . $attachment_id . ' origin: ' . $origin, ['event' => 'media.created'], 'debug');
                  $this->transcript[$casawp_id]['attachments']['created'][] = $attachment_id;

                  $existing_by_origin[$origin] = get_post($attachment_id);
                  unset($to_remove[$origin]);
              } else {
                  $this->logImport('failed to create casawp attachment for origin: ' . $origin . ' reason: ' . print_r($new_id, true), ['event' => 'media.create_failed'], 'error');
                  $this->transcript[$casawp_id]['attachments']['failed_to_create'][] = array(
                      'origin' => $origin,
                      'reason' => $new_id,
                  );
                  continue;
              }
          }

          if ($the_mediaitem['type'] === 'image' && (int) $the_mediaitem['order'] === 1 && empty($featured_attachment_id)) {
              $featured_attachment_id = (int) $attachment_id;
          }
      }

      if (!empty($to_remove)) {
          $this->logImport('removing ' . count($to_remove) . ' obsolete casawp attachments', [], 'debug');
      }

      foreach ($to_remove as $origin => $attachment) {
          $attachment_id = (int) $attachment->ID;
          $this->logImport('removing obsolete casawp attachment ' . $attachment_id . ' origin: ' . $origin, ['event' => 'media.removed'], 'debug');
          $this->transcript[$casawp_id]['attachments']['removed'][] = $attachment_id;
          wp_delete_attachment($attachment_id, true);
      }

      if (!empty($featured_attachment_id)) {
          $current_thumbnail_id = (int) get_post_thumbnail_id($wp_post->ID);
          if ($current_thumbnail_id !== $featured_attachment_id) {
              set_post_thumbnail($wp_post->ID, $featured_attachment_id);
              $this->transcript[$casawp_id]['attachments']['featured_image_set'] = $featured_attachment_id;
          }
      } else {
          delete_post_thumbnail($wp_post->ID);
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
          $this->logImport('Error inserting salestype term "' . $salestype_label . '": ' . $inserted_term->get_error_message(), [], 'error');
          $new_term_id = null;
        } else {
          $new_term_id = $inserted_term['term_id'];
          $term = get_term($new_term_id, 'casawp_salestype');
          if (is_wp_error($term)) {
            $this->logImport('Error retrieving term after creation: ' . $term->get_error_message(), [], 'error');
            $new_term_id = null;
          }
        }
      } else {
        $new_term_id = $term->term_id;
      }
    }

    $current_terms = wp_get_object_terms($wp_post->ID, 'casawp_salestype', array('fields' => 'ids'));
    if (is_wp_error($current_terms)) {
      $this->logImport('Error retrieving current salestype terms: ' . $current_terms->get_error_message(), [], 'error');
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
          $this->logImport('Error inserting availability term "' . $availability_label . '": ' . $inserted_term->get_error_message(), [], 'error');
          $new_term_id = null;
        } else {
          $new_term_id = $inserted_term['term_id'];
          $term = get_term($new_term_id, 'casawp_availability');
          if (is_wp_error($term)) {
            $this->logImport('Error retrieving term after creation: ' . $term->get_error_message(), [], 'error');
            $new_term_id = null;
          }
        }
      } else {
        $new_term_id = $term->term_id;
      }
    }

    $current_terms = wp_get_object_terms($wp_post->ID, 'casawp_availability', array('fields' => 'ids'));
    if (is_wp_error($current_terms)) {
      $this->logImport('Error retrieving current availability terms: ' . $current_terms->get_error_message(), [], 'error');
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
        $this->logImport('Error assigning location terms to post: ' . $result->get_error_message(), [], 'error');
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
        $this->logImport('Error inserting term "' . $label . '": ' . $inserted_term->get_error_message(), [], 'error');
        return null;
      } else {
        $term_id = $inserted_term['term_id'];
        $this->transcript['new_locations'][] = array($label, $slug);
        $term = get_term($term_id, $taxonomy);
        if (is_wp_error($term)) {
          $this->logImport('Error retrieving term after creation: ' . $term->get_error_message(), [], 'error');
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

    $new_features = $this->filterSkippedFeatures($features);

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
            $this->logImport('Error inserting feature term "' . $label . '": ' . $inserted_term->get_error_message(), [], 'error');
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

  /**
   * Exclude known invalid feature identifiers sent by upstream systems.
   *
   * Keeping this here also removes an already-imported invalid term during
   * the next successful import of the affected property.
   */
  private function filterSkippedFeatures($features)
  {
    return array_values(array_filter(
      !empty($features) ? (array) $features : array(),
      static function ($feature) {
        $slug = trim((string) $feature);
        return $slug !== '' && !in_array($slug, self::SKIPPED_FEATURE_SLUGS, true);
      }
    ));
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
            $this->logImport('Error inserting utility term "' . $label . '": ' . $inserted_term->get_error_message(), [], 'error');
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
            $this->logImport('Error inserting term "' . $label . '": ' . $inserted_term->get_error_message(), [], 'error');
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
    $this->log_cleanup_bytes = 0;
    $log_dir = CASASYNC_CUR_UPLOAD_BASEDIR . '/casawp/logs';
    $uploads = realpath(CASASYNC_CUR_UPLOAD_BASEDIR);
    $resolved = realpath($log_dir);
    if (!$uploads || !$resolved || !is_dir($log_dir) || is_link($log_dir)
        || strpos($resolved, $uploads . '/') !== 0) {
      return;
    }

    // Use the same site-local month as the logger, anchored on day one to avoid
    // month-end overflow when calculating the previous calendar month.
    $current_month = get_date_from_gmt('', 'Ym');
    $month_start = \DateTimeImmutable::createFromFormat('!Ym', $current_month, new \DateTimeZone('UTC'));
    if ($month_start === false) {
      return;
    }
    $keep = [$current_month, $month_start->modify('-1 month')->format('Ym')];
    $files = glob($log_dir . '/*');
    if (!$files) {
      return;
    }

    $deleted = 0;
    $bytes = 0;
    foreach ($files as $file) {
      $month = $this->logFileMonth(basename($file));
      if ($month === null || in_array($month, $keep, true) || !is_file($file) || is_link($file)) {
        continue;
      }
      $size = filesize($file);
      if (@unlink($file)) {
        $deleted++;
        $bytes += $size === false ? 0 : $size;
        $this->log_cleanup_bytes = $bytes;
        $this->logImport('Deleted old import log: ' . basename($file), [], 'debug');
      } else {
        $this->logImport('Could not delete old import log: ' . $file, ['event' => 'logs.delete_failed'], 'warning');
      }
    }
    if ($deleted) {
      $this->logImport(sprintf('Import log cleanup: deleted %d files; freed %.2f MB. Keeping %s and %s.',
        $deleted, $bytes / 1048576, $keep[0], $keep[1]), ['event' => 'logs.cleaned']);
    }
  }

  /** Recognize monthly log names without relying on locale or file modification time. */
  private function logFileMonth(string $filename): ?string
  {
    if (preg_match('/^(\d{4})(0[1-9]|1[0-2])\.log$/iD', $filename, $parts)) {
      return $parts[1] . $parts[2];
    }
    // Older releases used names such as "2020 Aug.log". Also accept full month
    // names and common numeric/separator variants, but never arbitrary log names.
    if (!preg_match('/^(\d{4})[ _-]+([a-z]+\.?|0?[1-9]|1[0-2])\.log$/iD', $filename, $parts)) {
      return null;
    }
    $months = [
      'jan' => 1, 'january' => 1, 'feb' => 2, 'february' => 2,
      'mar' => 3, 'march' => 3, 'apr' => 4, 'april' => 4,
      'may' => 5, 'jun' => 6, 'june' => 6, 'jul' => 7, 'july' => 7,
      'aug' => 8, 'august' => 8, 'sep' => 9, 'sept' => 9, 'september' => 9,
      'oct' => 10, 'october' => 10, 'nov' => 11, 'november' => 11,
      'dec' => 12, 'december' => 12,
    ];
    $name = strtolower(rtrim($parts[2], '.'));
    $month = ctype_digit($name) ? (int) $name : ($months[$name] ?? null);
    return $month === null ? null : sprintf('%s%02d', $parts[1], $month);
  }

  public function getLogCleanupFreedBytes(): int
  {
    return $this->log_cleanup_bytes;
  }

  public function addToLog($transcript)
  {
    $this->logger()->write($transcript);
  }

  private function logImport($transcript, array $context = [], string $level = 'info')
  {
    if ($level === 'error') {
      $this->markFileCleanupFailed();
    }
    $this->logger()->write($transcript, $context, $level);
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

    if ($property_xml->heating) {
      $propertydata['heating'] = array();
      foreach ($property_xml->heating as $xml_heating) {
        $propertydata['heating'][] = array(
          'generation' => $xml_heating->generation->__toString(),
          'distribution' => $xml_heating->distribution->__toString()
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
    $propertydata['features'] = $this->filterSkippedFeatures($propertydata['features']);

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

        $offerData['has_media_node'] = isset($offer_xml->attachments);
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
          $insert_id = $this->insertPostWithLanguage($the_post, $lang);
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
