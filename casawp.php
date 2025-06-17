<?php
/*
 * Plugin Name: CASAWP
 * Plugin URI: http://immobilien-plugin.ch
 * Description: Import your properties directly from your real-estate management software!
 * Author: Casasoft AG
 * Author URI: https://casasoft.ch
 * Version: 3.2.2
 * Text Domain: casawp
 * Domain Path: languages/
 * License: GPL2
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

// Include Action Scheduler
require_once __DIR__ . '/action-scheduler/action-scheduler.php';

// Ensure Action Scheduler is initialized
add_action('plugins_loaded', function() {
	if (!class_exists('ActionScheduler')) {
		do_action('action_scheduler_initialize');
	}
});

add_filter('action_scheduler_retention_period', function() {
	return 7 * DAY_IN_SECONDS; // Keep logs for 7 days
});

add_filter( 'action_scheduler_queue_runner_concurrent_batches', function () {
	return 1;          // default = 5
} );

// Update system
require_once('wp_autoupdate.php');
$plugin_current_version = '3.2.2';
$plugin_slug = plugin_basename(__FILE__);
$plugin_remote_path = 'https://wp.casasoft.com/casawp/update.php';
$license_user = 'user';
$license_key = 'abcd';
new WP_AutoUpdate($plugin_current_version, $plugin_remote_path, $plugin_slug, $license_user, $license_key);

function casawpPostInstall($true, $hook_extra, $result) {
  // Remember if our plugin was previously activated
  $wasActivated = is_plugin_active('casawp');

  // Since we are hosted in GitHub, our plugin folder would have a dirname of
  // reponame-tagname change it to our original one:
  global $wp_filesystem;
  $pluginFolder = WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . dirname('casawp');
  $wp_filesystem->move($result['destination'], $pluginFolder);
  $result['destination'] = $pluginFolder;

  // Re-activate plugin if needed
  if ($wasActivated) {
	  $activate = activate_plugin('casawp');
  }

  return $result;
}


add_filter("upgrader_post_install", "casawpPostInstall", 10, 3);

define('CASASYNC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CASASYNC_PLUGIN_DIR', plugin_dir_path(__FILE__) . '');

$upload = wp_upload_dir();
define('CASASYNC_CUR_UPLOAD_PATH', $upload['path']);
define('CASASYNC_CUR_UPLOAD_URL', $upload['url']);
define('CASASYNC_CUR_UPLOAD_BASEDIR', $upload['basedir']);
define('CASASYNC_CUR_UPLOAD_BASEURL', $upload['baseurl']);

// Setup autoloading
include 'vendor/autoload.php';
include 'modules/casawp/Module.php';


$applicationConfig = [
	'modules' => [
		'CasasoftStandards',
		'CasasoftMessenger',
		'casawp',
	],
	'module_listener_options' => [
		'config_glob_paths' => [],
		'module_paths' => [
			__DIR__ . '/module',
			__DIR__ . '/vendor',
		],
	],
];

// Define service manager configuration separately
$serviceManagerConfig = [
	'factories' => [
		'ModuleManager' => Laminas\Mvc\Service\ModuleManagerFactory::class,
		'ServiceListener' => Laminas\Mvc\Service\ServiceListenerFactory::class,
		'SharedEventManager' => Laminas\EventManager\SharedEventManagerFactory::class,
		'Application' => Laminas\Mvc\Service\ApplicationFactory::class,
		'Config' => Laminas\Mvc\Service\ConfigFactory::class,
		'EventManager' => Laminas\Mvc\Service\EventManagerFactory::class,
		'MvcTranslator' => Laminas\Mvc\I18n\TranslatorFactory::class,
	],
	'services' => [
		'ApplicationConfig' => $applicationConfig,
	],
];

// Combine into the final configuration array for Plugin.php
$configuration = [
	'service_manager' => $serviceManagerConfig,
	// No separate 'application_config'
];

// Initialize Autoloader
/* use Laminas\Loader\AutoloaderFactory;
AutoloaderFactory::factory(); */

// Instantiate the Plugin with the configuration
$casawp = new casawp\Plugin($configuration);
global $casawp;

if (is_admin()) {
	$casaSyncAdmin = new casawp\Admin();
	register_activation_hook(__FILE__, array($casaSyncAdmin,'casawp_install'));
	register_deactivation_hook(__FILE__, array($casaSyncAdmin, 'casawp_remove'));
}

$import = new casawp\Import(false, false);
$import->register_hooks();


/**
 * Try to become the *one* importer that may run right now.
 * Returns TRUE iff we won the race.
 *
 * If a lock is older than 4 h it is considered stale and recycled.
 */
function casawp_acquire_lock( int $max_age = 14400 ): bool {

	$now = time();

	/* 1) attempt to insert a brand-new row (atomic) */
	if ( add_option( 'casawp_import_lock', $now, '', 'no' ) ) {
		return true;                                // we own it
	}

	/* 2) row already there – is it stale? */
	$existing = (int) get_option( 'casawp_import_lock', 0 );
	if ( $existing && ( $now - $existing ) > $max_age ) {
		// try to steal the stale lock (race-safe because delete+add flips order)
		delete_option( 'casawp_import_lock' );
		return add_option( 'casawp_import_lock', $now, '', 'no' );
	}

	return false;   
}

/** Remove the lock – *always* call once the run ends (success, cancel, fail). */
function casawp_release_lock(): void {
	delete_option( 'casawp_import_lock' );
}

/** Flag that a fresh import should run when the lock is free. */
function casawp_set_pending(): void  { update_option( 'casawp_import_pending', 1, 'no' ); }
function casawp_clear_pending(): void { delete_option( 'casawp_import_pending' ); }

/** Is a pending poke queued? */
function casawp_has_pending(): bool { return (bool) get_option( 'casawp_import_pending', 0 ); }


add_action('admin_init', function() {
	if (get_option('casawp_single_request_import', '') === '') {
		update_option('casawp_single_request_import', '1');
	}
});

add_action('admin_init', 'casawp_handle_import_requests');
function casawp_handle_import_requests() {
	if (!is_admin() || !current_user_can('manage_options')) {
		return;
	}

	if (isset($_GET['casawp_run_single_import']) && $_GET['casawp_run_single_import'] == '1') {
		check_admin_referer('casawp_single_import');

		try {

			if (! casawp_acquire_lock()) {
				casawp_cancel_import();
				sleep(2);
			}

			// *** Now we call the Import method that can throw exceptions
			casawp_start_single_request_import('Manual single request import');

			// If no exception was thrown = success
			set_transient('casawp_single_import_result', [
				'status'  => 'success',
				'message' => 'Import erfolgreich ausgeführt!'
			], 60);

		} catch (Exception $e) {
			// If an exception was thrown in handle_single_request_import()
			set_transient('casawp_single_import_result', [
				'status'  => 'error',
				'message' => 'Fehler beim Import: ' . esc_html($e->getMessage())
			], 60);
		}

		// Redirect to remove the query args so refresh won't re-import
		wp_safe_redirect(remove_query_arg(['casawp_run_single_import','_wpnonce']));
		exit;
	}
}

add_action('admin_notices', 'casawp_display_single_import_notice');
function casawp_display_single_import_notice() {
	$result = get_transient('casawp_single_import_result');
	if ($result) {
		// Delete so it only shows once
		delete_transient('casawp_single_import_result');

		$status_class = ($result['status'] === 'success') ? 'notice-success' : 'notice-error';

		echo '<div class="notice ' . esc_attr($status_class) . ' is-dismissible">'
		   . '<p>' . esc_html($result['message']) . '</p>'
		   . '</div>';
	}
}


function casawp_remove_old_crons() {
	$cron_hooks = [
		'casawp_import_midnight',
		'casawp_import_noon',
	];

	foreach ($cron_hooks as $hook) {
		while (wp_next_scheduled($hook)) {
			wp_clear_scheduled_hook($hook);
		}
	}
}

add_action('init', 'casawp_remove_old_crons');

function eg_increase_time_limit( $time_limit ) {
	return 60;
}
add_filter( 'action_scheduler_queue_runner_time_limit', 'eg_increase_time_limit' );


add_action('action_scheduler_failed_execution', 'casawp_handle_failed_action');
add_action('action_scheduler_failed_action', 'casawp_handle_failed_action');
add_action( 'action_scheduler_unexpected_shutdown', 'casawp_handle_failed_action' );

function casawp_handle_failed_action($action_id) {
	error_log('Action Scheduler Failed Hook Triggered for Action ID: ' . $action_id);
	$action = ActionScheduler::store()->fetch_action($action_id);
	if ($action && $action->get_hook() == 'casawp_batch_import') {
		$args = $action->get_args();
		$batch_number = isset($args['batch_number']) ? $args['batch_number'] : 'unknown';
		$site_domain = home_url();
		$to = get_option('admin_email');
		$subject = 'CASAWP Import Batch Failed on ' . $site_domain;
		$message = "Batch number " . $batch_number . " has failed after maximum retries.\n\nSite: " . $site_domain;
		wp_mail($to, $subject, $message);

		$import = new casawp\Import(false, false);
		$import->addToLog('Import canceled due to batch failure on ' . $site_domain . '. Notification sent.');
		update_option('casawp_import_failed', true);
		casawp_release_lock();
	}
}


function casawp_start_new_import($source = '', $force_cancel = false) {

	if ( ! casawp_acquire_lock() ) {          // someone else is running
		if ( $force_cancel ) {
			casawp_cancel_import();           // clears jobs & releases lock
			sleep( 2 );
			casawp_acquire_lock();            // we *must* own it now
		} else {
			casawp_set_pending();             // queue & bail
			$import = new casawp\Import(false, false);
			$import->addToLog('Import already in progress. New import request queued.');
			return;
		}
	}

	update_option('casawp_total_batches', 0);
	update_option('casawp_completed_batches', 0);
	delete_option('casawp_import_failed');
	delete_option('casawp_import_canceled');

	$import = new casawp\Import(); 
	$import->updateImportFileThroughCasaGateway( true );
	$import->addToLog($source . ' import started');
	return $import;

}


add_action('init', 'casawp_initialize_cleanup_cron');

function casawp_initialize_cleanup_cron() {
	if (!wp_next_scheduled('casawp_cleanup_logs')) {
		wp_schedule_event(time(), 'monthly', 'casawp_cleanup_logs');
	}
}

register_deactivation_hook(__FILE__, 'casawp_unschedule_cleanup_cron');

function casawp_unschedule_cleanup_cron() {
	// Unschedule Cleanup Cron Event
	$timestamp = wp_next_scheduled('casawp_cleanup_logs');
	if ($timestamp) {
		wp_unschedule_event($timestamp, 'casawp_cleanup_logs');
	}
}

add_action('casawp_cleanup_logs', 'casawp_cleanup_log_files');

function casawp_cleanup_log_files() {
	$import = new casawp\Import(false, false);
	$import->cleanup_log_files();
}

add_filter('cron_schedules', 'casawp_add_cron_schedule');

function casawp_add_cron_schedule($schedules) {
	if (!isset($schedules['monthly'])) {
		$schedules['monthly'] = array(
			'interval' => 30 * DAY_IN_SECONDS, // Approximate 1 month (30 days)
			'display'  => __('Once Monthly')
		);
	}
	return $schedules;
}


function casawp_cancel_import() {
	if (class_exists('ActionScheduler')) {
		$store = ActionScheduler::store();
		$run_id = (int) get_option( 'casawp_current_run_id', 0 );
		$group  = 'casawp_run_' . $run_id;

		$pending_actions = $store->query_actions( [
			'hook'   => 'casawp_batch_import',
			'status' => [ 'pending', 'in-progress' ],
			'group'  => $group,                    // ←  only this run
		] );
		foreach ($pending_actions as $action_id) {
			$store->cancel_action($action_id);
		}
		update_option('casawp_import_canceled', true);
		casawp_release_lock();
		delete_option('casawp_import_failed');
		$import = new casawp\Import(false, false);
		$import->addToLog('All pending import actions canceled and import lock cleared.');
		return true;
	} else {
		error_log('Action Scheduler class not found. Could not cancel pending import actions.');
		return false;
	}
}

add_action('wp_ajax_casawp_get_import_progress', 'casawp_get_import_progress');

function casawp_get_import_progress() {
	$total_batches = get_option('casawp_total_batches', 0);
	$completed_batches = get_option('casawp_completed_batches', 0);

	if ($total_batches > 0) {
		$progress = ($completed_batches / $total_batches) * 100;
	} else {
		$progress = 0;
	}

	wp_send_json_success(['progress' => $progress]);
}

add_action('wp_ajax_casawp_check_no_properties_alert', 'casawp_check_no_properties_alert');

function casawp_check_no_properties_alert() {
	$alert_message = get_transient('casawp_no_properties_alert');
	if ($alert_message) {
		delete_transient('casawp_no_properties_alert');
		wp_send_json_success(['message' => $alert_message]);
	} else {
		wp_send_json_success(['message' => '']);
	}
}

add_action('wp_ajax_casawp_start_import', 'casawp_start_import');
function casawp_start_import() {
	if (!current_user_can('manage_options')) {
		wp_send_json_error(['message' => 'Unauthorized']);
		return;
	}

	if (isset($_POST['gatewayupdate']) && $_POST['gatewayupdate'] == 1) {
	$use_single_request = get_option('casawp_single_request_import', false);

	if ($use_single_request) {
		casawp_start_single_request_import('Update from CASAGATEWAY');
		wp_send_json_success(['message' => 'Single-request import started/finished']);
	} else {
		casawp_start_new_import('Update from CASAGATEWAY', true);
		wp_send_json_success(['message' => 'Batch import started successfully']);
	}

	} else {
		wp_send_json_error(['message' => 'Invalid request']);
	}
}

/**
 * Run a one-shot (single-request) import:
 * – fetch feed, process everything, remove stale posts – all in one HTTP request
 */
function casawp_start_single_request_import( string $source = '' ) {

	/* ── 1. exclusive lock ─────────────────────────────────────── */
	if ( ! casawp_acquire_lock() ) {          // someone else is running
		casawp_cancel_import();               // clear their jobs + lock
		sleep( 2 );                           // tiny safety pause
		casawp_acquire_lock();                // we must own it now
	}

	/* ── 2. mint / store a fresh run-id ────────────────────────── */
	$run_id = time();                         // seconds since 1970 ⇒ unique
	update_option( 'casawp_current_run_id', $run_id, 'no' );

	update_option( 'casawp_total_batches',     1 );
	update_option( 'casawp_completed_batches', 0 );
	delete_option( 'casawp_import_failed' );
	delete_option( 'casawp_import_canceled' );

	/* ── 3. create importer (no auto-update in ctor) ───────────── */
	$import = new \casawp\Import( false, false );   // (poke = false, update = false)
	$import->init_single_run( $run_id );            // hand over the run-id

	$import->addToLog( $source . ' import (single-request) started' );

	/* ── 4. do the actual work ─────────────────────────────────── */
	$import->deactivate_all_properties();           // mark every post inactive
	$import->handle_single_request_import();        // fetch + process feed
	/*   ↑ calls finalize_import_cleanup() internally,
		   which in turn releases the lock.                 */

	$import->addToLog( 'Single-request import completed' );
}


add_action('admin_init', function() {
	if (!is_admin() || !current_user_can('manage_options')) {
		return;
	}

	if (isset($_GET['casawp_run_single_import']) && $_GET['casawp_run_single_import'] == '1') {

		check_admin_referer('casawp_single_import');

		casawp_start_single_request_import('Manual GET link');

		add_action('admin_notices', function() {
			echo '<div class="notice notice-success is-dismissible">'
			   . '<p>Single-request Import erfolgreich ausgeführt!</p>'
			   . '</div>';
		});

		wp_safe_redirect(remove_query_arg(['casawp_run_single_import','_wpnonce']));
		exit;
	}
});



add_action('wp_ajax_casawp_cancel_import', 'casawp_cancel_import_ajax');
function casawp_cancel_import_ajax() {
	if (!current_user_can('manage_options')) {
		wp_send_json_error(['message' => 'Unauthorized']);
		return;
	}
	if (casawp_cancel_import()) {
		wp_send_json_success(['message' => 'Import canceled successfully.']);
	} else {
		wp_send_json_error(['message' => 'Failed to cancel the current import.']);
	}
}


add_action('wp_ajax_casawp_reset_import_progress', 'casawp_reset_import_progress');
function casawp_reset_import_progress() {
	if (!current_user_can('manage_options')) {
		wp_send_json_error(['message' => 'Unauthorized']);
		return;
	}

	// Delete the options to reset the progress
	delete_option('casawp_total_batches');
	delete_option('casawp_completed_batches');

	wp_send_json_success(['message' => 'Import progress reset']);
}

add_action('init', 'casawp_handle_gatewaypoke');
function casawp_handle_gatewaypoke() {
	if ( isset($_GET['gatewaypoke']) ) {
		$use_single = get_option('casawp_single_request_import', false);
		if ($use_single) {
			casawp_start_single_request_import('Poke from CasaGateway');
		} else {
			casawp_start_new_import('Poke from CasaGateway', false);
		}
	}
}

function this_plugin_after_wpml() {
	$wp_path_to_this_file = preg_replace('/(.*)plugins\/(.*)$/', WP_PLUGIN_DIR."/$2", __FILE__);
	$this_plugin = plugin_basename(trim($wp_path_to_this_file));
	$active_plugins = get_option('active_plugins');
	$this_plugin_key = array_search($this_plugin, $active_plugins);

	$dependency = 'sitepress-multilingual-cms/sitepress.php';
	unset($active_plugins[$this_plugin_key]);
	$new_sort = array();
	foreach ($active_plugins as $active_plugin) {
		$new_sort[] = $active_plugin;
		if ($active_plugin == $dependency) {
			$new_sort[] = $this_plugin;
		}
	}

	if (!in_array($this_plugin, $new_sort)) {
		$new_sort[] = $this_plugin;
	}

	$new_sort = array_values($new_sort);

	update_option('active_plugins', $new_sort);
}
add_action("activated_plugin", "this_plugin_after_wpml");

/**
 * Delete every CASAWP object (properties + media + stray meta).
 *
 * Runs on the current blog only – in Multisite the user needs to click
 * the button inside each sub-site where they want to wipe the data.
 */
add_action( 'admin_post_casawp_delete_all_properties', function () {

	// ── security ──────────────────────────────────────────────────
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Insufficient privileges.' );
	}
	check_admin_referer( 'casawp_delete_all' );

	global $wpdb;

	$p      = $wpdb->prefix;                // honour sub-site prefixes
	$posts  = "{$p}posts";
	$meta   = "{$p}postmeta";
	$terms  = "{$p}terms";
	$tax    = "{$p}term_taxonomy";
	$rel    = "{$p}term_relationships";

	/* ----------------------------------------------------------------
	 *  1.  Delete all casawp_property posts (+meta)
	 * ----------------------------------------------------------------*/
	$wpdb->query(
		"DELETE p, pm
		   FROM {$posts} p
		   LEFT JOIN {$meta} pm ON pm.post_id = p.ID
		  WHERE p.post_type = 'casawp_property'"
	);

	/* ----------------------------------------------------------------
	 *  2.  Attachment term-taxonomy IDs we need to purge
	 * ----------------------------------------------------------------*/
	$slugs = [ 'image', 'document', 'plan', 'offer-logo', 'sales-brochue' ];
	$place = implode( ',', array_fill( 0, count( $slugs ), '%s' ) );

	$tt_ids = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT tt.term_taxonomy_id
			   FROM {$tax} tt
			   JOIN {$terms} t ON t.term_id = tt.term_id
			  WHERE tt.taxonomy = 'casawp_attachment_type'
				AND t.slug IN ( {$place} )",
			$slugs
		)
	);

	if ( $tt_ids ) {
		$tt_in = implode( ',', array_map( 'intval', $tt_ids ) );

		/* 2a. Delete attachments & their meta that are linked to those terms */
		$wpdb->query(
			"DELETE p, pm
			   FROM {$posts} p
			   LEFT JOIN {$meta} pm ON pm.post_id = p.ID
			   JOIN {$rel}  tr ON tr.object_id = p.ID
			  WHERE tr.term_taxonomy_id IN ( {$tt_in} )
				AND p.post_type = 'attachment'"
		);

		/* 2b. Finally remove the now-unused term relationships themselves */
		$wpdb->query(
			"DELETE FROM {$rel}
			  WHERE term_taxonomy_id IN ( {$tt_in} )"
		);
	}

	/* ----------------------------------------------------------------
	 *  2-b.  WPML glue (only if tables exist)
	 * ----------------------------------------------------------------*/
	$icl_trans = "{$p}icl_translations";
	if ( $wpdb->get_var( $wpdb->prepare(
			"SHOW TABLES LIKE %s", $icl_trans
		) ) ) {

		$icl_status = "{$p}icl_translation_status";
		$icl_jobs   = "{$p}icl_translate_job";

		// translations + status rows for casawp_property
		$wpdb->query(
			"DELETE t , ts
			   FROM {$icl_trans}             AS t
			   LEFT JOIN {$icl_status}       AS ts
					 ON ts.translation_id = t.translation_id
			  WHERE t.element_type = 'post_casawp_property'"
		);

		// orphaned jobs (defensive)
		$wpdb->query(
			"DELETE
			   FROM {$icl_jobs}
			  WHERE rid NOT IN ( SELECT rid FROM {$icl_status} )"
		);
	}

	/* ----------------------------------------------------------------
	 *  3.  Strip orphaned post-meta rows
	 * ----------------------------------------------------------------*/
	$wpdb->query(
		"DELETE pm
		   FROM {$meta} pm
	  LEFT JOIN {$posts} p ON p.ID = pm.post_id
		  WHERE p.ID IS NULL"
	);

	/* ----------------------------------------------------------------
	 *  4.  House-keeping: term counts, caches, feedback
	 * ----------------------------------------------------------------*/
	// update term counts for the attachment taxonomy
	$wpdb->query(
		"UPDATE {$tax} SET count = 0 WHERE taxonomy = 'casawp_attachment_type'"
	);

	clean_term_cache( array(), 'casawp_attachment_type' );
	wp_cache_flush();

	/* ----------------------------------------------------------------
	 *  5.  Redirect back with a success notice
	 * ----------------------------------------------------------------*/
	$redirect = add_query_arg(
		[ 'casawp_deleted_all' => 1 ],
		wp_get_referer() ?: admin_url( 'admin.php?page=casawp' )
	);
	wp_safe_redirect( $redirect );
	exit;
} );


add_action( 'admin_notices', function () {
	if ( isset( $_GET['casawp_deleted_all'] ) ) {
		echo '<div class="notice notice-success is-dismissible"><p>'
		   . esc_html__( 'Alle Objektdaten wurden vollständig gelöscht.', 'casawp' )
		   . '</p></div>';
	}
} );




function casawp_unicode_dirty_replace($str)
{
	$charset = [
		"!" => "u0021", // (alt-033)	EXCLAMATION MARK = factorial = bang
		"\"" => "u0022", // (alt-034)	QUOTATION MARK
		"#" => "u0023", // (alt-035)	NUMBER SIGN = pound sign, hash, crosshatch, octothorpe
		"$" => "u0024", // (alt-036)	DOLLAR SIGN = milreis, escudo
		"%" => "u0025", // (alt-037)	PERCENT SIGN
		"&" => "u0026", // (alt-038)	AMPERSAND
		"'" => "u0027", // (alt-039)	APOSTROPHE = apostrophe-quote = APL quote
		"(" => "u0028", // (alt-040)	LEFT PARENTHESIS = opening parenthesis
		")" => "u0029", // (alt-041)	RIGHT PARENTHESIS = closing parenthesis
		"*" => "u002a", // (alt-042)	ASTERISK = star (on phone keypads)
		"+" => "u002b", // (alt-043)	PLUS SIGN
		"," => "u002c", // (alt-044)	COMMA = decimal separator
		"-" => "u002d", // (alt-045)	HYPHEN-MINUS = hyphen or minus sign
		"." => "u002e", // (alt-046)	FULL STOP (period, dot, decimal point)
		"/" => "u002f", // (alt-047)	SOLIDUS (slash, virgule)
		"0" => "u0030", // (alt-048)	DIGIT ZERO
		"1" => "u0031", // (alt-049)	DIGIT ONE
		"2" => "u0032", // (alt-050)	DIGIT TWO
		"3" => "u0033", // (alt-051)	DIGIT THREE
		"4" => "u0034", // (alt-052)	DIGIT FOUR
		"5" => "u0035", // (alt-053)	DIGIT FIVE
		"6" => "u0036", // (alt-054)	DIGIT SIX
		"7" => "u0037", // (alt-055)	DIGIT SEVEN
		"8" => "u0038", // (alt-056)	DIGIT EIGHT
		"9" => "u0039", // (alt-057)	DIGIT NINE
		":" => "u003a", // (alt-058)	COLON
		";" => "u003b", // (alt-059)	SEMICOLON
		"<" => "u003c", // (alt-060)	LESS-THAN SIGN
		"=" => "u003d", // (alt-061)	EQUALS SIGN
		">" => "u003e", // (alt-062)	GREATER-THAN SIGN
		"?" => "u003f", // (alt-063)	QUESTION MARK
		"@" => "u0040", // (alt-064)	COMMERCIAL AT (at sign)
		"A" => "u0041", // (alt-065)	LATIN CAPITAL LETTER A
		"B" => "u0042", // (alt-066)	LATIN CAPITAL LETTER B
		"C" => "u0043", // (alt-067)	LATIN CAPITAL LETTER C
		"D" => "u0044", // (alt-068)	LATIN CAPITAL LETTER D
		"E" => "u0045", // (alt-069)	LATIN CAPITAL LETTER E
		"F" => "u0046", // (alt-070)	LATIN CAPITAL LETTER F
		"G" => "u0047", // (alt-071)	LATIN CAPITAL LETTER G
		"H" => "u0048", // (alt-072)	LATIN CAPITAL LETTER H
		"I" => "u0049", // (alt-073)	LATIN CAPITAL LETTER I
		"J" => "u004a", // (alt-074)	LATIN CAPITAL LETTER J
		"K" => "u004b", // (alt-075)	LATIN CAPITAL LETTER K
		"L" => "u004c", // (alt-076)	LATIN CAPITAL LETTER L
		"M" => "u004d", // (alt-077)	LATIN CAPITAL LETTER M
		"N" => "u004e", // (alt-078)	LATIN CAPITAL LETTER N
		"O" => "u004f", // (alt-079)	LATIN CAPITAL LETTER O
		"P" => "u0050", // (alt-080)	LATIN CAPITAL LETTER P
		"Q" => "u0051", // (alt-081)	LATIN CAPITAL LETTER Q
		"R" => "u0052", // (alt-082)	LATIN CAPITAL LETTER R
		"S" => "u0053", // (alt-083)	LATIN CAPITAL LETTER S
		"T" => "u0054", // (alt-084)	LATIN CAPITAL LETTER T
		"U" => "u0055", // (alt-085)	LATIN CAPITAL LETTER U
		"V" => "u0056", // (alt-086)	LATIN CAPITAL LETTER V
		"W" => "u0057", // (alt-087)	LATIN CAPITAL LETTER W
		"X" => "u0058", // (alt-088)	LATIN CAPITAL LETTER X
		"Y" => "u0059", // (alt-089)	LATIN CAPITAL LETTER Y
		"Z" => "u005a", // (alt-090)	LATIN CAPITAL LETTER Z
		"[" => "u005b", // (alt-091)	LEFT SQUARE BRACKET = opening square bracket
		"\\" => "u005c", // (alt-092)	REVERSE SOLIDUS = backslash
		"]" => "u005d", // (alt-093)	RIGHT SQUARE BRACKET = closing square bracket
		"^" => "u005e", // (alt-094)	CIRCUMFLEX ACCENT
		"_" => "u005f", // (alt-095)	LOW LINE (spacing underscore)
		"`" => "u0060", // (alt-096)	GRAVE ACCENT
		"a" => "u0061", // (alt-097)	LATIN SMALL LETTER A
		"b" => "u0062", // (alt-098)	LATIN SMALL LETTER B
		"c" => "u0063", // (alt-099)	LATIN SMALL LETTER C
		"d" => "u0064", // (alt-0100)	LATIN SMALL LETTER D
		"e" => "u0065", // (alt-0101)	LATIN SMALL LETTER E
		"f" => "u0066", // (alt-0102)	LATIN SMALL LETTER F
		"g" => "u0067", // (alt-0103)	LATIN SMALL LETTER G
		"h" => "u0068", // (alt-0104)	LATIN SMALL LETTER H
		"i" => "u0069", // (alt-0105)	LATIN SMALL LETTER I
		"j" => "u006a", // (alt-0106)	LATIN SMALL LETTER J
		"k" => "u006b", // (alt-0107)	LATIN SMALL LETTER K
		"l" => "u006c", // (alt-0108)	LATIN SMALL LETTER L
		"m" => "u006d", // (alt-0109)	LATIN SMALL LETTER M
		"n" => "u006e", // (alt-0110)	LATIN SMALL LETTER N
		"o" => "u006f", // (alt-0111)	LATIN SMALL LETTER O
		"p" => "u0070", // (alt-0112)	LATIN SMALL LETTER P
		"q" => "u0071", // (alt-0113)	LATIN SMALL LETTER Q
		"r" => "u0072", // (alt-0114)	LATIN SMALL LETTER R
		"s" => "u0073", // (alt-0115)	LATIN SMALL LETTER S
		"t" => "u0074", // (alt-0116)	LATIN SMALL LETTER T
		"u" => "u0075", // (alt-0117)	LATIN SMALL LETTER U
		"v" => "u0076", // (alt-0118)	LATIN SMALL LETTER V
		"w" => "u0077", // (alt-0119)	LATIN SMALL LETTER W
		"x" => "u0078", // (alt-0120)	LATIN SMALL LETTER X
		"y" => "u0079", // (alt-0121)	LATIN SMALL LETTER Y
		"z" => "u007a", // (alt-0122)	LATIN SMALL LETTER Z
		"{" => "u007b", // (alt-0123)	LEFT CURLY BRACKET = opening curly bracket = left brace
		"|" => "u007c", // (alt-0124)	VERTICAL LINE = vertical bar
		"}" => "u007d", // (alt-0125)	RIGHT CURLY BRACKET = closing curly bracket = right brace
		"~" => "u007e", // (alt-0126)	TILDE
		// "" => "u007f", // (alt-0127)	<control> = DELETE
		// " " => "u00a0", // (alt-0160)	NO-BREAK SPACE (commonly abbreviated as NBSP)
		"¡" => "u00a1", // (alt-0161)	INVERTED EXCLAMATION MARK (Spanish, Asturian, Galician)
		"¢" => "u00a2", // (alt-0162)	CENT SIGN
		"£" => "u00a3", // (alt-0163)	POUND SIGN (pound sterling, Irish punt, Italian lira, Turkish lira, etc.)
		"¤" => "u00a4", // (alt-0164)	CURRENCY SIGN
		"¥" => "u00a5", // (alt-0165)	YEN SIGN = yuan sign
		"¦" => "u00a6", // (alt-0166)	BROKEN BAR = broken vertical bar = parted rule (in typography)
		"§" => "u00a7", // (alt-0167)	SECTION SIGN
		"¨" => "u00a8", // (alt-0168)	DIAERESIS
		"©" => "u00a9", // (alt-0169)	COPYRIGHT SIGN
		"ª" => "u00aa", // (alt-0170)	FEMININE ORDINAL INDICATOR (windows "alt 166")
		"«" => "u00ab", // (alt-0171)	LEFT-POINTING DOUBLE ANGLE QUOTATION MARK = left guillemet = chevrons (in typography)
		"¬" => "u00ac", // (alt-0172)	NOT SIGN = angled dash (in typography)
		// " " =>	"00Ad(alt", //-0173)	SOFT HYPHEN = discretionary hyphen
		"®" => "u00ae", // (alt-0174)	REGISTERED SIGN = registered trade mark sign
		"¯" => "u00af", // (alt-0175)	MACRON = overline, APL overbar
		"°" => "u00b0", // (alt-0176)	DEGREE SIGN
		"±" => "u00b1", // (alt-0177)	PLUS-MINUS SIGN
		"²" => "u00b2", // (alt-0178)	SUPERSCRIPT TWO = squared
		"³" => "u00b3", // (alt-0179)	SUPERSCRIPT THREE = cubed
		"´" => "u00b4", // (alt-0180)	ACUTE ACCENT
		"µ" => "u00b5", // (alt-0181)	MICRO SIGN
		"¶" => "u00b6", // (alt-0182)	PILCROW SIGN = paragraph sign
		"·" => "u00b7", // (alt-0183)	MIDDLE DOT = midpoint (in typography) = Georgian comma = Greek middle dot (ano teleia)
		"¸" => "u00b8", // (alt-0184)	CEDILLA
		"¹" => "u00b9", // (alt-0185)	SUPERSCRIPT ONE
		"º" => "u00ba", // (alt-0186)	MASCULINE ORDINAL INDICATOR (windows "alt 167")
		"»" => "u00bb", // (alt-0187)	RIGHT-POINTING DOUBLE ANGLE QUOTATION MARK = right guillemet
		"¼" => "u00bc", // (alt-0188)	VULGAR FRACTION ONE QUARTER
		"½" => "u00bd", // (alt-0189)	VULGAR FRACTION ONE HALF
		"¾" => "u00be", // (alt-0190)	VULGAR FRACTION THREE QUARTERS
		"¿" => "u00bf", // (alt-0191)	INVERTED QUESTION MARK = turned question mark
		"À" => "u00c0", // (alt-0192)	LATIN CAPITAL LETTER A WITH GRAVE
		"Á" => "u00c1", // (alt-0193)	LATIN CAPITAL LETTER A WITH ACUTE
		"Â" => "u00c2", // (alt-0194)	LATIN CAPITAL LETTER A WITH CIRCUMFLEX
		"Ã" => "u00c3", // (alt-0195)	LATIN CAPITAL LETTER A WITH TILDE
		"Ä" => "u00c4", // (alt-0196)	LATIN CAPITAL LETTER A WITH DIAERESIS
		"Å" => "u00c5", // (alt-0197)	LATIN CAPITAL LETTER A WITH RING ABOVE
		"Æ" => "u00c6", // (alt-0198)	LATIN CAPITAL LETTER AE = latin capital ligature ae
		"Ç" => "u00c7", // (alt-0199)	LATIN CAPITAL LETTER C WITH CEDILLA
		"È" => "u00c8", // (alt-0200)	LATIN CAPITAL LETTER E WITH GRAVE
		"É" => "u00c9", // (alt-0201)	LATIN CAPITAL LETTER E WITH ACUTE
		"Ê" => "u00ca", // (alt-0202)	LATIN CAPITAL LETTER E WITH CIRCUMFLEX
		"Ë" => "u00cb", // (alt-0203)	LATIN CAPITAL LETTER E WITH DIAERESIS
		"Ì" => "u00cc", // (alt-0204)	LATIN CAPITAL LETTER I WITH GRAVE
		"Í" => "u00cd", // (alt-0205)	LATIN CAPITAL LETTER I WITH ACUTE
		"Î" => "u00ce", // (alt-0206)	LATIN CAPITAL LETTER I WITH CIRCUMFLEX
		"Ï" => "u00cf", // (alt-0207)	LATIN CAPITAL LETTER I WITH DIAERESIS
		"Ð" => "u00d0", // (alt-0208)	LATIN CAPITAL LETTER ETH
		"Ñ" => "u00d1", // (alt-0209)	LATIN CAPITAL LETTER N WITH TILDE
		"Ò" => "u00d2", // (alt-0210)	LATIN CAPITAL LETTER O WITH GRAVE
		"Ó" => "u00d3", // (alt-0211)	LATIN CAPITAL LETTER O WITH ACUTE
		"Ô" => "u00d4", // (alt-0212)	LATIN CAPITAL LETTER O WITH CIRCUMFLEX
		"Õ" => "u00d5", // (alt-0213)	LATIN CAPITAL LETTER O WITH TILDE
		"Ö" => "u00d6", // (alt-0214)	LATIN CAPITAL LETTER O WITH DIAERESIS
		"×" => "u00d7", // (alt-0215)	MULTIPLICATION SIGN = z notation Cartesian product
		"Ø" => "u00d8", // (alt-0216)	LATIN CAPITAL LETTER O WITH STROKE = o slash
		"Ù" => "u00d9", // (alt-0217)	LATIN CAPITAL LETTER U WITH GRAVE
		"Ú" => "u00da", // (alt-0218)	LATIN CAPITAL LETTER U WITH ACUTE
		"Û" => "u00db", // (alt-0219)	LATIN CAPITAL LETTER U WITH CIRCUMFLEX
		"Ü" => "u00dc", // (alt-0220)	LATIN CAPITAL LETTER U WITH DIAERESIS
		"Ý" => "u00dd", // (alt-0221)	LATIN CAPITAL LETTER Y WITH ACUTE
		"Þ" => "u00de", // (alt-0222)	LATIN CAPITAL LETTER THORN
		"ß" => "u00df", // (alt-0223)	LATIN SMALL LETTER SHARP S = Eszett
		"à" => "u00e0", // (alt-0224)	LATIN SMALL LETTER A WITH GRAVE
		"á" => "u00e1", // (alt-0225)	LATIN SMALL LETTER A WITH ACUTE
		"â" => "u00e2", // (alt-0226)	LATIN SMALL LETTER A WITH CIRCUMFLEX
		"ã" => "u00e3", // (alt-0227)	LATIN SMALL LETTER A WITH TILDE
		"ä" => "u00e4", // (alt-0228)	LATIN SMALL LETTER A WITH DIAERESIS
		"å" => "u00e5", // (alt-0229)	LATIN SMALL LETTER A WITH RING ABOVE
		"æ" => "u00e6", // (alt-0230)	LATIN SMALL LETTER AE = latin small ligature ae = ash (from Old English æsc)
		"ç" => "u00e7", // (alt-0231)	LATIN SMALL LETTER C WITH CEDILLA
		"è" => "u00e8", // (alt-0232)	LATIN SMALL LETTER E WITH GRAVE
		"é" => "u00e9", // (alt-0233)	LATIN SMALL LETTER E WITH ACUTE
		"ê" => "u00ea", // (alt-0234)	LATIN SMALL LETTER E WITH CIRCUMFLEX
		"ë" => "u00eb", // (alt-0235)	LATIN SMALL LETTER E WITH DIAERESIS
		"ì" => "u00ec", // (alt-0236)	LATIN SMALL LETTER I WITH GRAVE
		"í" => "u00ed", // (alt-0237)	LATIN SMALL LETTER I WITH ACUTE
		"î" => "u00ee", // (alt-0238)	LATIN SMALL LETTER I WITH CIRCUMFLEX
		"ï" => "u00ef", // (alt-0239)	LATIN SMALL LETTER I WITH DIAERESIS
		"ð" => "u00f0", // (alt-0240)	LATIN SMALL LETTER ETH
		"ñ" => "u00f1", // (alt-0241)	LATIN SMALL LETTER N WITH TILDE
		"ò" => "u00f2", // (alt-0242)	LATIN SMALL LETTER O WITH GRAVE
		"ó" => "u00f3", // (alt-0243)	LATIN SMALL LETTER O WITH ACUTE
		"ô" => "u00f4", // (alt-0244)	LATIN SMALL LETTER O WITH CIRCUMFLEX
		"õ" => "u00f5", // (alt-0245)	LATIN SMALL LETTER O WITH TILDE
		"ö" => "u00f6", // (alt-0246)	LATIN SMALL LETTER O WITH DIAERESIS
		"÷" => "u00f7", // (alt-0247)	DIVISION SIGN
		"ø" => "u00f8", // (alt-0248)	LATIN SMALL LETTER O WITH STROKE = o slash
		"ù" => "u00f9", // (alt-0249)	LATIN SMALL LETTER U WITH GRAVE
		"ú" => "u00fa", // (alt-0250)	LATIN SMALL LETTER U WITH ACUTE
		"û" => "u00fb", // (alt-0251)	LATIN SMALL LETTER U WITH CIRCUMFLEX
		"ü" => "u00fc", // (alt-0252)	LATIN SMALL LETTER U WITH DIAERESIS
		"ý" => "u00fd", // (alt-0253)	LATIN SMALL LETTER Y WITH ACUTE
		"þ" => "u00fe", // (alt-0254)	LATIN SMALL LETTER THORN
		"ÿ" => "u00ff", // (alt-0255)	LATIN SMALL LETTER Y WITH DIAERESIS
		"Ł" => "u0141", // (alt-0321)	LATIN CAPITAL LETTER L WITH STROKE
		"ł" => "u0142", // (alt-0322)	LATIN SMALL LETTER L WITH STROKE
		"Ń" => "u0143", // (alt-0323)	LATIN CAPITAL LETTER N WITH ACUTE
		"ń" => "u0144", // (alt-0324)	LATIN SMALL LETTER N WITH ACUTE
		"Ņ" => "u0145", // (alt-0325)	LATIN CAPITAL LETTER N WITH CEDILLA
		"ņ" => "u0146", // (alt-0326)	LATIN SMALL LETTER N WITH CEDILLA
		"Ň" => "u0147", // (alt-0327)	LATIN CAPITAL LETTER N WITH CARON
		"ň" => "u0148", // (alt-0328)	LATIN SMALL LETTER N WITH CARON
		"Ŋ" => "u014a", // (alt-0330)	LATIN CAPITAL LETTER ENG
		"ŋ" => "u014b", // (alt-0331)	LATIN SMALL LETTER ENG = engma, angma
		"Ō" => "u014c", // (alt-0332)	LATIN CAPITAL LETTER O WITH MACRON
		"ō" => "u014d", // (alt-0333)	LATIN SMALL LETTER O WITH MACRON
		"Ŏ" => "u014e", // (alt-0334)	LATIN CAPITAL LETTER O WITH BREVE
		"ŏ" => "u014f", // (alt-0335)	LATIN SMALL LETTER O WITH BREVE
		"Ő" => "u0150", // (alt-0336)	LATIN CAPITAL LETTER O WITH DOUBLE ACUTE
		"ő" => "u0151", // (alt-0337)	LATIN SMALL LETTER O WITH DOUBLE ACUTE
		"Œ" => "u0152", // (alt-0338)	LATIN CAPITAL LIGATURE OE
		"œ" => "u0153", // (alt-0339)	LATIN SMALL LIGATURE OE = ethel (from Old English)
		"Ŕ" => "u0154", // (alt-0340)	LATIN CAPITAL LETTER R WITH ACUTE
		"ŕ" => "u0155", // (alt-0341)	LATIN SMALL LETTER R WITH ACUTE
		"Ŗ" => "u0156", // (alt-0342)	LATIN CAPITAL LETTER R WITH CEDILLA
		"ŗ" => "u0157", // (alt-0343)	LATIN SMALL LETTER R WITH CEDILLA
		"Ř" => "u0158", // (alt-0344)	LATIN CAPITAL LETTER R WITH CARON
		"ř" => "u0159", // (alt-0345)	LATIN SMALL LETTER R WITH CARON
		"Ś" => "u015a", // (alt-0346)	LATIN CAPITAL LETTER S WITH ACUTE
		"ś" => "u015b", // (alt-0347)	LATIN SMALL LETTER S WITH ACUTE
		"Ŝ" => "u015c", // (alt-0348)	LATIN CAPITAL LETTER S WITH CIRCUMFLEX
		"ŝ" => "u015d", // (alt-0349)	LATIN SMALL LETTER S WITH CIRCUMFLEX
		"Ş" => "u015e", // (alt-0350)	LATIN CAPITAL LETTER S WITH CEDILLA
		"ş" => "u015f", // (alt-0351)	LATIN SMALL LETTER S WITH CEDILLA
		"Š" => "u0160", // (alt-0352)	LATIN CAPITAL LETTER S WITH CARON
		"š" => "u0161", // (alt-0353)	LATIN SMALL LETTER S WITH CARON
		"Ţ" => "u0162", // (alt-0354)	LATIN CAPITAL LETTER T WITH CEDILLA
		"ţ" => "u0163", // (alt-0355)	LATIN SMALL LETTER T WITH CEDILLA
		"Ť" => "u0164", // (alt-0356)	LATIN CAPITAL LETTER T WITH CARON
		"ť" => "u0165", // (alt-0357)	LATIN SMALL LETTER T WITH CARON
		"Ŧ" => "u0166", // (alt-0358)	LATIN CAPITAL LETTER T WITH STROKE
		"ŧ" => "u0167", // (alt-0359)	LATIN SMALL LETTER T WITH STROKE
		"Ũ" => "u0168", // (alt-0360)	LATIN CAPITAL LETTER U WITH TILDE
		"ũ" => "u0169", // (alt-0361)	LATIN SMALL LETTER U WITH TILDE
		"Ū" => "u016a", // (alt-0362)	LATIN CAPITAL LETTER U WITH MACRON
		"ū" => "u016b", // (alt-0363)	LATIN SMALL LETTER U WITH MACRON
		"Ŭ" => "u016c", // (alt-0364)	LATIN CAPITAL LETTER U WITH BREVE
		"ŭ" => "u016d", // (alt-0365)	LATIN SMALL LETTER U WITH BREVE
		"Ů" => "u016e", // (alt-0366)	LATIN CAPITAL LETTER U WITH RING ABOVE
		"ů" => "u016f", // (alt-0367)	LATIN SMALL LETTER U WITH RING ABOVE
		"Ű" => "u0170", // (alt-0368)	LATIN CAPITAL LETTER U WITH DOUBLE ACUTE
		"ű" => "u0171", // (alt-0369)	LATIN SMALL LETTER U WITH DOUBLE ACUTE
		"Ŵ" => "u0174", // (alt-0372)	LATIN CAPITAL LETTER W WITH CIRCUMFLEX
		"ŵ" => "u0175", // (alt-0373)	LATIN SMALL LETTER W WITH CIRCUMFLEX
		"Ŷ" => "u0176", // (alt-0374)	LATIN CAPITAL LETTER Y WITH CIRCUMFLEX
		"ŷ" => "u0177", // (alt-0375)	LATIN SMALL LETTER Y WITH CIRCUMFLEX
		"Ÿ" => "u0178", // (alt-0376)	LATIN CAPITAL LETTER Y WITH DIAERESIS
		"Ź" => "u0179", // (alt-0377)	LATIN CAPITAL LETTER Z WITH ACUTE
		"ź" => "u017a", // (alt-0378)	LATIN SMALL LETTER Z WITH ACUTE
		"Ż" => "u017b", // (alt-0379)	LATIN CAPITAL LETTER Z WITH DOT ABOVE
		"ż" => "u017c", // (alt-0380)	LATIN SMALL LETTER Z WITH DOT ABOVE
		"Ž" => "u017d", // (alt-0381)	LATIN CAPITAL LETTER Z WITH CARON
		"ž" => "u017e", // (alt-0382)	LATIN SMALL LETTER Z WITH CARON
		"ſ" => "u017f", // (alt-0383)	LATIN SMALL LETTER LONG S
		"Ɔ" => "u0186", // (alt-0390)	LATIN CAPITAL LETTER OPEN O
		"Ǝ" => "u018e", // (alt-0398)	LATIN CAPITAL LETTER REVERSED E = turned e
		"Ɯ" => "u019c", // (alt-0412)	LATIN CAPITAL LETTER TURNED M
		"ɐ" => "u0250", // (alt-0592)	LATIN SMALL LETTER TURNED A
		"ɑ" => "u0251", // (alt-0593)	LATIN SMALL LETTER ALPHA = latin small letter script a
		"ɒ" => "u0252", // (alt-0594)	LATIN SMALL LETTER TURNED ALPHA
		"ɔ" => "u0254", // (alt-0596)	LATIN SMALL LETTER OPEN O
		"ɘ" => "u0258", // (alt-0600)	LATIN SMALL LETTER REVERSED E
		"ə" => "u0259", // (alt-0601)	LATIN SMALL LETTER SCHWA
		"ɛ" => "u025b", // (alt-0603)	LATIN SMALL LETTER OPEN E = epsilon
		"ɜ" => "u025c", // (alt-0604)	LATIN SMALL LETTER REVERSED OPEN E
		"ɞ" => "u025e", // (alt-0606)	LATIN SMALL LETTER CLOSED REVERSED OPEN E = closed reversed epsilon
		"ɟ" => "u025f", // (alt-0607)	LATIN SMALL LETTER DOTLESS J WITH STROKE
		"ɡ" => "u0261", // (alt-0609)	LATIN SMALL LETTER SCRIPT G
		"ɢ" => "u0262", // (alt-0610)	LATIN LETTER SMALL CAPITAL G
		"ɣ" => "u0263", // (alt-0611)	LATIN SMALL LETTER GAMMA
		"ɤ" => "u0264", // (alt-0612)	LATIN SMALL LETTER RAMS HORN = latin small letter baby gamma
		"ɥ" => "u0265", // (alt-0613)	LATIN SMALL LETTER TURNED H
		"ɨ" => "u0268", // (alt-0616)	LATIN SMALL LETTER I WITH STROKE = barred i, i bar
		"ɪ" => "u026a", // (alt-0618)	LATIN LETTER SMALL CAPITAL I
		"ɬ" => "u026c", // (alt-0620)	LATIN SMALL LETTER L WITH BELT
		"ɮ" => "u026e", // (alt-0622)	LATIN SMALL LETTER LEZH
		"ɯ" => "u026f", // (alt-0623)	LATIN SMALL LETTER TURNED M
		"ɰ" => "u0270", // (alt-0624)	LATIN SMALL LETTER TURNED M WITH LONG LEG
		"ɴ" => "u0274", // (alt-0628)	LATIN LETTER SMALL CAPITAL N
		"ɵ" => "u0275", // (alt-0629)	LATIN SMALL LETTER BARRED O = o bar
		"ɶ" => "u0276", // (alt-0630)	LATIN LETTER SMALL CAPITAL OE
		"ɷ" => "u0277", // (alt-0631)	LATIN SMALL LETTER CLOSED OMEGA
		"ɸ" => "u0278", // (alt-0632)	LATIN SMALL LETTER PHI
		"ɹ" => "u0279", // (alt-0633)	LATIN SMALL LETTER TURNED R
		"ʁ" => "u0281", // (alt-0641)	LATIN LETTER SMALL CAPITAL INVERTED R
		"ʇ" => "u0287", // (alt-0647)	LATIN SMALL LETTER TURNED T
		"ʌ" => "u028c", // (alt-0652)	LATIN SMALL LETTER TURNED V = caret, wedge
		"ʍ" => "u028d", // (alt-0653)	LATIN SMALL LETTER TURNED W
		"ʎ" => "u028e", // (alt-0654)	LATIN SMALL LETTER TURNED Y
		"ʞ" => "u029e", // (alt-0670)	LATIN SMALL LETTER TURNED K
		"Α" => "u0391", // (alt-0913)	GREEK CAPITAL LETTER ALPHA
		"Β" => "u0392", // (alt-0914)	GREEK CAPITAL LETTER BETA
		"Γ" => "u0393", // (alt-0915)	GREEK CAPITAL LETTER GAMMA = gamma function
		"Δ" => "u0394", // (alt-0916)	GREEK CAPITAL LETTER DELTA
		"Ε" => "u0395", // (alt-0917)	GREEK CAPITAL LETTER EPSILON
		"Ζ" => "u0396", // (alt-0918)	GREEK CAPITAL LETTER ZETA
		"Η" => "u0397", // (alt-0919)	GREEK CAPITAL LETTER ETA
		"Θ" => "u0398", // (alt-0920)	GREEK CAPITAL LETTER THETA
		"Ι" => "u0399", // (alt-0921)	GREEK CAPITAL LETTER IOTA = iota adscript
		"Κ" => "u039a", // (alt-0922)	GREEK CAPITAL LETTER KAPPA
		"Λ" => "u039b", // (alt-0923)	GREEK CAPITAL LETTER LAMDA
		"Μ" => "u039c", // (alt-0924)	GREEK CAPITAL LETTER MU
		"Ν" => "u039d", // (alt-0925)	GREEK CAPITAL LETTER NU
		"Ξ" => "u039e", // (alt-0926)	GREEK CAPITAL LETTER XI
		"Ο" => "u039f", // (alt-0927)	GREEK CAPITAL LETTER OMICRON
		"Π" => "u03a0", // (alt-0928)	GREEK CAPITAL LETTER PI
		"Ρ" => "u03a1", // (alt-0929)	GREEK CAPITAL LETTER RHO
		"Σ" => "u03a3", // (alt-0931)	GREEK CAPITAL LETTER SIGMA
		"Τ" => "u03a4", // (alt-0932)	GREEK CAPITAL LETTER TAU
		"Υ" => "u03a5", // (alt-0933)	GREEK CAPITAL LETTER UPSILON
		"Φ" => "u03a6", // (alt-0934)	GREEK CAPITAL LETTER PHI
		"Χ" => "u03a7", // (alt-0935)	GREEK CAPITAL LETTER CHI
		"Ψ" => "u03a8", // (alt-0936)	GREEK CAPITAL LETTER PSI
		"Ω" => "u03a9", // (alt-0937)	GREEK CAPITAL LETTER OMEGA
		"α" => "u03b1", // (alt-0945)	GREEK SMALL LETTER ALPHA
		"β" => "u03b2", // (alt-0946)	GREEK SMALL LETTER BETA
		"γ" => "u03b3", // (alt-0947)	GREEK SMALL LETTER GAMMA
		"δ" => "u03b4", // (alt-0948)	GREEK SMALL LETTER DELTA
		"ε" => "u03b5", // (alt-0949)	GREEK SMALL LETTER EPSILON
		"ζ" => "u03b6", // (alt-0950)	GREEK SMALL LETTER ZETA
		"η" => "u03b7", // (alt-0951)	GREEK SMALL LETTER ETA
		"θ" => "u03b8", // (alt-0952)	GREEK SMALL LETTER THETA
		"ι" => "u03b9", // (alt-0953)	GREEK SMALL LETTER IOTA
		"κ" => "u03ba", // (alt-0954)	GREEK SMALL LETTER KAPPA
		"λ" => "u03bb", // (alt-0955)	GREEK SMALL LETTER LAMDA = lambda
		"μ" => "u03bc", // (alt-0956)	GREEK SMALL LETTER MU
		"ν" => "u03bd", // (alt-0957)	GREEK SMALL LETTER NU
		"ξ" => "u03be", // (alt-0958)	GREEK SMALL LETTER XI
		"ο" => "u03bf", // (alt-0959)	GREEK SMALL LETTER OMICRON
		"π" => "u03c0", // (alt-0960)	GREEK SMALL LETTER PI
		"ρ" => "u03c1", // (alt-0961)	GREEK SMALL LETTER RHO
		"ς" => "u03c2", // (alt-0962)	GREEK SMALL LETTER FINAL SIGMA = stigma (the Modern Greek name for this letterform)
		"σ" => "u03c3", // (alt-0963)	GREEK SMALL LETTER SIGMA
		"τ" => "u03c4", // (alt-0964)	GREEK SMALL LETTER TAU
		"υ" => "u03c5", // (alt-0965)	GREEK SMALL LETTER UPSILON
		"φ" => "u03c6", // (alt-0966)	GREEK SMALL LETTER PHI
		"χ" => "u03c7", // (alt-0967)	GREEK SMALL LETTER CHI
		"ψ" => "u03c8", // (alt-0968)	GREEK SMALL LETTER PSI
		"ω" => "u03c9", // (alt-0969)	GREEK SMALL LETTER OMEGA
		"А" => "u0410", // (alt-01040)	CYRILLIC CAPITAL LETTER A
		"Б" => "u0411", // (alt-01041)	CYRILLIC CAPITAL LETTER BE
		"В" => "u0412", // (alt-01042)	CYRILLIC CAPITAL LETTER VE
		"Г" => "u0413", // (alt-01043)	CYRILLIC CAPITAL LETTER GHE
		"Д" => "u0414", // (alt-01044)	CYRILLIC CAPITAL LETTER DE
		"Е" => "u0415", // (alt-01045)	CYRILLIC CAPITAL LETTER IE
		"Ж" => "u0416", // (alt-01046)	CYRILLIC CAPITAL LETTER ZHE
		"З" => "u0417", // (alt-01047)	CYRILLIC CAPITAL LETTER ZE
		"И" => "u0418", // (alt-01048)	CYRILLIC CAPITAL LETTER I
		"Й" => "u0419", // (alt-01049)	CYRILLIC CAPITAL LETTER SHORT I
		"К" => "u041a", // (alt-01050)	CYRILLIC CAPITAL LETTER KA
		"Л" => "u041b", // (alt-01051)	CYRILLIC CAPITAL LETTER EL
		"М" => "u041c", // (alt-01052)	CYRILLIC CAPITAL LETTER EM
		"Н" => "u041d", // (alt-01053)	CYRILLIC CAPITAL LETTER EN
		"О" => "u041e", // (alt-01054)	CYRILLIC CAPITAL LETTER O
		"П" => "u041f", // (alt-01055)	CYRILLIC CAPITAL LETTER PE
		"Р" => "u0420", // (alt-01056)	CYRILLIC CAPITAL LETTER ER
		"С" => "u0421", // (alt-01057)	CYRILLIC CAPITAL LETTER ES
		"Т" => "u0422", // (alt-01058)	CYRILLIC CAPITAL LETTER TE
		"У" => "u0423", // (alt-01059)	CYRILLIC CAPITAL LETTER U
		"Ф" => "u0424", // (alt-01060)	CYRILLIC CAPITAL LETTER EF
		"Х" => "u0425", // (alt-01061)	CYRILLIC CAPITAL LETTER HA
		"Ц" => "u0426", // (alt-01062)	CYRILLIC CAPITAL LETTER TSE
		"Ч" => "u0427", // (alt-01063)	CYRILLIC CAPITAL LETTER CHE
		"Ш" => "u0428", // (alt-01064)	CYRILLIC CAPITAL LETTER SHA
		"Щ" => "u0429", // (alt-01065)	CYRILLIC CAPITAL LETTER SHCHA
		"Ъ" => "u042a", // (alt-01066)	CYRILLIC CAPITAL LETTER HARD SIGN
		"Ы" => "u042b", // (alt-01067)	CYRILLIC CAPITAL LETTER YERU
		"Ь" => "u042c", // (alt-01068)	CYRILLIC CAPITAL LETTER SOFT SIGN
		"Э" => "u042d", // (alt-01069)	CYRILLIC CAPITAL LETTER E
		"Ю" => "u042e", // (alt-01070)	CYRILLIC CAPITAL LETTER YU
		"Я" => "u042f", // (alt-01071)	CYRILLIC CAPITAL LETTER YA
		"а" => "u0430", // (alt-01072)	CYRILLIC SMALL LETTER A
		"б" => "u0431", // (alt-01073)	CYRILLIC SMALL LETTER BE
		"в" => "u0432", // (alt-01074)	CYRILLIC SMALL LETTER VE
		"г" => "u0433", // (alt-01075)	CYRILLIC SMALL LETTER GHE
		"д" => "u0434", // (alt-01076)	CYRILLIC SMALL LETTER DE
		"е" => "u0435", // (alt-01077)	CYRILLIC SMALL LETTER IE
		"ж" => "u0436", // (alt-01078)	CYRILLIC SMALL LETTER ZHE
		"з" => "u0437", // (alt-01079)	CYRILLIC SMALL LETTER ZE
		"и" => "u0438", // (alt-01080)	CYRILLIC SMALL LETTER I
		"й" => "u0439", // (alt-01081)	CYRILLIC SMALL LETTER SHORT I
		"к" => "u043a", // (alt-01082)	CYRILLIC SMALL LETTER KA
		"л" => "u043b", // (alt-01083)	CYRILLIC SMALL LETTER EL
		"м" => "u043c", // (alt-01084)	CYRILLIC SMALL LETTER EM
		"н" => "u043d", // (alt-01085)	CYRILLIC SMALL LETTER EN
		"о" => "u043e", // (alt-01086)	CYRILLIC SMALL LETTER O
		"п" => "u043f", // (alt-01087)	CYRILLIC SMALL LETTER PE
		"р" => "u0440", // (alt-01088)	CYRILLIC SMALL LETTER ER
		"с" => "u0441", // (alt-01089)	CYRILLIC SMALL LETTER ES
		"т" => "u0442", // (alt-01090)	CYRILLIC SMALL LETTER TE
		"у" => "u0443", // (alt-01091)	CYRILLIC SMALL LETTER U
		"ф" => "u0444", // (alt-01092)	CYRILLIC SMALL LETTER EF
		"х" => "u0445", // (alt-01093)	CYRILLIC SMALL LETTER HA
		"ц" => "u0446", // (alt-01094)	CYRILLIC SMALL LETTER TSE
		"ч" => "u0447", // (alt-01095)	CYRILLIC SMALL LETTER CHE
		"ш" => "u0448", // (alt-01096)	CYRILLIC SMALL LETTER SHA
		"щ" => "u0449", // (alt-01097)	CYRILLIC SMALL LETTER SHCHA
		"ъ" => "u044a", // (alt-01098)	CYRILLIC SMALL LETTER HARD SIGN
		"ы" => "u044b", // (alt-01099)	CYRILLIC SMALL LETTER YERU
		"ь" => "u044c", // (alt-01100)	CYRILLIC SMALL LETTER SOFT SIGN
		"э" => "u044d", // (alt-01101)	CYRILLIC SMALL LETTER E
		"ю" => "u044e", // (alt-01102)	CYRILLIC SMALL LETTER YU
		"я" => "u044f", // (alt-01103)	CYRILLIC SMALL LETTER YA
		"ᴀ" => "u1d00", // (alt-07424)	LATIN LETTER SMALL CAPITAL A
		"ᴁ" => "u1d01", // (alt-07425)	LATIN LETTER SMALL CAPITAL AE
		"ᴂ" => "u1d02", // (alt-07426)	LATIN SMALL LETTER TURNED AE
		"ᴃ" => "u1d03", // (alt-07427)	LATIN LETTER SMALL CAPITAL BARRED B
		"ᴄ" => "u1d04", // (alt-07428)	LATIN LETTER SMALL CAPITAL C
		"ᴅ" => "u1d05", // (alt-07429)	LATIN LETTER SMALL CAPITAL D
		"ᴆ" => "u1d06", // (alt-07430)	LATIN LETTER SMALL CAPITAL ETH
		"ᴇ" => "u1d07", // (alt-07431)	LATIN LETTER SMALL CAPITAL E
		"ᴈ" => "u1d08", // (alt-07432)	LATIN SMALL LETTER TURNED OPEN E
		"ᴉ" => "u1d09", // (alt-07433)	LATIN SMALL LETTER TURNED I
		"ᴊ" => "u1d0a", // (alt-07434)	LATIN LETTER SMALL CAPITAL J
		"ᴋ" => "u1d0b", // (alt-07435)	LATIN LETTER SMALL CAPITAL K
		"ᴌ" => "u1d0c", // (alt-07436)	LATIN LETTER SMALL CAPITAL L WITH STROKE
		"ᴍ" => "u1d0d", // (alt-07437)	LATIN LETTER SMALL CAPITAL M
		"ᴎ" => "u1d0e", // (alt-07438)	LATIN LETTER SMALL CAPITAL REVERSED N
		"ᴏ" => "u1d0f", // (alt-07439)	LATIN LETTER SMALL CAPITAL O
		"ᴐ" => "u1d10", // (alt-07440)	LATIN LETTER SMALL CAPITAL OPEN O
		"ᴑ" => "u1d11", // (alt-07441)	LATIN SMALL LETTER SIDEWAYS O
		"ᴒ" => "u1d12", // (alt-07442)	LATIN SMALL LETTER SIDEWAYS OPEN O
		"ᴓ" => "u1d13", // (alt-07443)	LATIN SMALL LETTER SIDEWAYS O WITH STROKE
		"ᴔ" => "u1d14", // (alt-07444)	LATIN SMALL LETTER TURNED OE
		"ᴕ" => "u1d15", // (alt-07445)	LATIN LETTER SMALL CAPITAL OU
		"ᴖ" => "u1d16", // (alt-07446)	LATIN SMALL LETTER TOP HALF O
		"ᴗ" => "u1d17", // (alt-07447)	LATIN SMALL LETTER BOTTOM HALF O
		"ᴘ" => "u1d18", // (alt-07448)	LATIN LETTER SMALL CAPITAL P
		"ᴙ" => "u1d19", // (alt-07449)	LATIN LETTER SMALL CAPITAL REVERSED R
		"ᴚ" => "u1d1a", // (alt-07450)	LATIN LETTER SMALL CAPITAL TURNED R
		"ᴛ" => "u1d1b", // (alt-07451)	LATIN LETTER SMALL CAPITAL T
		"ᴜ" => "u1d1c", // (alt-07452)	LATIN LETTER SMALL CAPITAL U
		"ᴝ" => "u1d1d", // (alt-07453)	LATIN SMALL LETTER SIDEWAYS U
		"ᴞ" => "u1d1e", // (alt-07454)	LATIN SMALL LETTER SIDEWAYS DIAERESIZED U
		"ᴟ" => "u1d1f", // (alt-07455)	LATIN SMALL LETTER SIDEWAYS TURNED M
		"ᴠ" => "u1d20", // (alt-07456)	LATIN LETTER SMALL CAPITAL V
		"ᴡ" => "u1d21", // (alt-07457)	LATIN LETTER SMALL CAPITAL W
		"ᴢ" => "u1d22", // (alt-07458)	LATIN LETTER SMALL CAPITAL Z
		"ᴣ" => "u1d23", // (alt-07459)	LATIN LETTER SMALL CAPITAL EZH
		"ᴤ" => "u1d24", // (alt-07460)	LATIN LETTER VOICED LARYNGEAL SPIRANT
		"ᴥ" => "u1d25", // (alt-07461)	LATIN LETTER AIN
		"ᴦ" => "u1d26", // (alt-07462)	GREEK LETTER SMALL CAPITAL GAMMA
		"ᴧ" => "u1d27", // (alt-07463)	GREEK LETTER SMALL CAPITAL LAMDA
		"ᴨ" => "u1d28", // (alt-07464)	GREEK LETTER SMALL CAPITAL PI
		"ᴩ" => "u1d29", // (alt-07465)	GREEK LETTER SMALL CAPITAL RHO
		"ᴪ" => "u1d2a", // (alt-07466)	GREEK LETTER SMALL CAPITAL PSI
		"ẞ" => "u1e9e", // (alt-07838)	LATIN CAPITAL LETTER SHARP S
		"Ỳ" => "u1ef2", // (alt-07922)	LATIN CAPITAL LETTER Y WITH GRAVE
		"ỳ" => "u1ef3", // (alt-07923)	LATIN SMALL LETTER Y WITH GRAVE
		"Ỵ" => "u1ef4", // (alt-07924)	LATIN CAPITAL LETTER Y WITH DOT BELOW
		"ỵ" => "u1ef5", // (alt-07925)	LATIN SMALL LETTER Y WITH DOT BELOW
		"Ỹ" => "u1ef8", // (alt-07928)	LATIN CAPITAL LETTER Y WITH TILDE
		"ỹ" => "u1ef9", // (alt-07929)	LATIN SMALL LETTER Y WITH TILDE
		"‐" => "u2010", // (alt-08208)	HYPHEN
		"‑" => "u2011", // (alt-08209)	NON-BREAKING HYPHEN
		"‒" => "u2012", // (alt-08210)	FIGURE DASH
		"–" => "u2013", // (alt-08211)	EN DASH
		"—" => "u2014", // (alt-08212)	EM DASH
		"―" => "u2015", // (alt-08213)	HORIZONTAL BAR = quotation dash
		"‖" => "u2016", // (alt-08214)	DOUBLE VERTICAL LINE
		"‗" => "u2017", // (alt-08215)	DOUBLE LOW LINE
		"‘" => "u2018", // (alt-08216)	LEFT SINGLE QUOTATION MARK = single turned comma quotation mark
		"’" => "u2019", // (alt-08217)	RIGHT SINGLE QUOTATION MARK = single comma quotation mark
		"‚" => "u201a", // (alt-08218)	SINGLE LOW-9 QUOTATION MARK = low single comma quotation mark
		"‛" => "u201b", // (alt-08219)	SINGLE HIGH-REVERSED-9 QUOTATION MARK = single reversed comma quotation mark
		"“" => "u201c", // (alt-08220)	LEFT DOUBLE QUOTATION MARK = double turned comma quotation mark
		"”" => "u201d", // (alt-08221)	RIGHT DOUBLE QUOTATION MARK = double comma quotation mark
		"„" => "u201e", // (alt-08222)	DOUBLE LOW-9 QUOTATION MARK = low double comma quotation mark
		"‟" => "u201f", // (alt-08223)	DOUBLE HIGH-REVERSED-9 QUOTATION MARK = double reversed comma quotation mark
		"†" => "u2020", // (alt-08224)	DAGGER = obelisk, obelus, long cross
		"‡" => "u2021", // (alt-08225)	DOUBLE DAGGER = diesis, double obelisk
		"•" => "u2022", // (alt-08226)	BULLET = black small circle
		"‣" => "u2023", // (alt-08227)	TRIANGULAR BULLET
		"․" => "u2024", // (alt-08228)	ONE DOT LEADER
		"‥" => "u2025", // (alt-08229)	TWO DOT LEADER
		"…" => "u2026", // (alt-08230)	HORIZONTAL ELLIPSIS = three dot leader
		"‧" => "u2027", // (alt-08231)	HYPHENATION POINT
		"‰" => "u2030", // (alt-08240)	PER MILLE SIGN = permille, per thousand
		"‱" => "u2031", // (alt-08241)	PER TEN THOUSAND SIGN = permyriad
		"′" => "u2032", // (alt-08242)	PRIME = minutes, feet
		"″" => "u2033", // (alt-08243)	DOUBLE PRIME = seconds, inches
		"‴" => "u2034", // (alt-08244)	TRIPLE PRIME = lines (old measure, 1/12 of an inch)
		"‵" => "u2035", // (alt-08245)	REVERSED PRIME
		"‶" => "u2036", // (alt-08246)	REVERSED DOUBLE PRIME
		"‷" => "u2037", // (alt-08247)	REVERSED TRIPLE PRIME
		"‸" => "u2038", // (alt-08248)	CARET
		"‹" => "u2039", // (alt-08249)	SINGLE LEFT-POINTING ANGLE QUOTATION MARK = left pointing single guillemet
		"›" => "u203a", // (alt-08250)	SINGLE RIGHT-POINTING ANGLE QUOTATION MARK = right pointing single guillemet
		"※" => "u203b", // (alt-08251)	REFERENCE MARK = Japanese kome = Urdu paragraph separator
		"‼" => "u203c", // (alt-08252)	DOUBLE EXCLAMATION MARK
		"‽" => "u203d", // (alt-08253)	INTERROBANG
		"‾" => "u203e", // (alt-08254)	OVERLINE = spacing overscore
		"‿" => "u203f", // (alt-08255)	UNDERTIE = Greek enotikon
		"⁀" => "u2040", // (alt-08256)	CHARACTER TIE = z notation sequence concatenation
		"⁁" => "u2041", // (alt-08257)	CARET INSERTION POINT
		"⁂" => "u2042", // (alt-08258)	ASTERISM
		"⁃" => "u2043", // (alt-08259)	HYPHEN BULLET
		"⁄" => "u2044", // (alt-08260)	FRACTION SLASH = solidus (in typography)
		"⁅" => "u2045", // (alt-08261)	LEFT SQUARE BRACKET WITH QUILL
		"⁆" => "u2046", // (alt-08262)	RIGHT SQUARE BRACKET WITH QUILL
		"⁇" => "u2047", // (alt-08263)	DOUBLE QUESTION MARK
		"⁈" => "u2048", // (alt-08264)	QUESTION EXCLAMATION MARK
		"⁉" => "u2049", // (alt-08265)	EXCLAMATION QUESTION MARK
		"⁊" => "u204a", // (alt-08266)	TIRONIAN SIGN ET
		"⁋" => "u204b", // (alt-08267)	REVERSED PILCROW SIGN
		"⁌" => "u204c", // (alt-08268)	BLACK LEFTWARDS BULLET
		"⁍" => "u204d", // (alt-08269)	BLACK RIGHTWARDS BULLET
		"⁎" => "u204e", // (alt-08270)	LOW ASTERISK
		"⁏" => "u204f", // (alt-08271)	REVERSED SEMICOLON
		"⁐" => "u2050", // (alt-08272)	CLOSE UP
		"⁑" => "u2051", // (alt-08273)	TWO ASTERISKS ALIGNED VERTICALLY
		"⁒" => "u2052", // (alt-08274)	COMMERCIAL MINUS SIGN = abzüglich (German), med avdrag av (Swedish), piska (Swedish, "whip")
		"⁓" => "u2053", // (alt-08275)	SWUNG DASH
		"⁔" => "u2054", // (alt-08276)	INVERTED UNDERTIE
		"⁕" => "u2055", // (alt-08277)	FLOWER PUNCTUATION MARK = phul, puspika
		"⁗" => "u2057", // (alt-08279)	QUADRUPLE PRIME
		"⁰" => "u2070", // (alt-08304)	SUPERSCRIPT ZERO
		"ⁱ" => "u2071", // (alt-08305)	SUPERSCRIPT LATIN SMALL LETTER I
		"⁴" => "u2074", // (alt-08308)	SUPERSCRIPT FOUR
		"⁵" => "u2075", // (alt-08309)	SUPERSCRIPT FIVE
		"⁶" => "u2076", // (alt-08310)	SUPERSCRIPT SIX
		"⁷" => "u2077", // (alt-08311)	SUPERSCRIPT SEVEN
		"⁸" => "u2078", // (alt-08312)	SUPERSCRIPT EIGHT
		"⁹" => "u2079", // (alt-08313)	SUPERSCRIPT NINE
		"⁺" => "u207a", // (alt-08314)	SUPERSCRIPT PLUS SIGN
		"⁻" => "u207b", // (alt-08315)	SUPERSCRIPT MINUS
		"⁼" => "u207c", // (alt-08316)	SUPERSCRIPT EQUALS SIGN
		"⁽" => "u207d", // (alt-08317)	SUPERSCRIPT LEFT PARENTHESIS
		"⁾" => "u207e", // (alt-08318)	SUPERSCRIPT RIGHT PARENTHESIS
		"ⁿ" => "u207f", // (alt-08319)	SUPERSCRIPT LATIN SMALL LETTER N
		"₀" => "u2080", // (alt-08320)	SUBSCRIPT ZERO
		"₁" => "u2081", // (alt-08321)	SUBSCRIPT ONE
		"₂" => "u2082", // (alt-08322)	SUBSCRIPT TWO
		"₃" => "u2083", // (alt-08323)	SUBSCRIPT THREE
		"₄" => "u2084", // (alt-08324)	SUBSCRIPT FOUR
		"₅" => "u2085", // (alt-08325)	SUBSCRIPT FIVE
		"₆" => "u2086", // (alt-08326)	SUBSCRIPT SIX
		"₇" => "u2087", // (alt-08327)	SUBSCRIPT SEVEN
		"₈" => "u2088", // (alt-08328)	SUBSCRIPT EIGHT
		"₉" => "u2089", // (alt-08329)	SUBSCRIPT NINE
		"₊" => "u208a", // (alt-08330)	SUBSCRIPT PLUS SIGN
		"₋" => "u208b", // (alt-08331)	SUBSCRIPT MINUS
		"₌" => "u208c", // (alt-08332)	SUBSCRIPT EQUALS SIGN
		"₍" => "u208d", // (alt-08333)	SUBSCRIPT LEFT PARENTHESIS
		"₎" => "u208e", // (alt-08334)	SUBSCRIPT RIGHT PARENTHESIS
		"₠" => "u20a0", // (alt-08352)	EURO-CURRENCY SIGN
		"₡" => "u20a1", // (alt-08353)	COLON SIGN
		"₢" => "u20a2", // (alt-08354)	CRUZEIRO SIGN
		"₣" => "u20a3", // (alt-08355)	FRENCH FRANC SIGN
		"₤" => "u20a4", // (alt-08356)	LIRA SIGN
		"₥" => "u20a5", // (alt-08357)	MILL SIGN
		"₦" => "u20a6", // (alt-08358)	NAIRA SIGN
		"₧" => "u20a7", // (alt-08359)	PESETA SIGN
		"₨" => "u20a8", // (alt-08360)	RUPEE SIGN
		"₩" => "u20a9", // (alt-08361)	WON SIGN
		"₪" => "u20aa", // (alt-08362)	NEW SHEQEL SIGN
		"₫" => "u20ab", // (alt-08363)	DONG SIGN
		"€" => "u20ac", // (alt-08364)	EURO SIGN
		"₭" => "u20ad", // (alt-08365)	KIP SIGN
		"₮" => "u20ae", // (alt-08366)	TUGRIK SIGN
		"₯" => "u20af", // (alt-08367)	DRACHMA SIGN
		"₰" => "u20b0", // (alt-08368)	GERMAN PENNY SIGN
		"₱" => "u20b1", // (alt-08369)	PESO SIGN
		"₲" => "u20b2", // (alt-08370)	GUARANI SIGN
		"₳" => "u20b3", // (alt-08371)	AUSTRAL SIGN
		"₴" => "u20b4", // (alt-08372)	HRYVNIA SIGN
		"₵" => "u20b5", // (alt-08373)	CEDI SIGN
		"₶" => "u20b6", // (alt-08374)	LIVRE TOURNOIS SIGN
		"₷" => "u20b7", // (alt-08375)	SPESMILO SIGN
		"₸" => "u20b8", // (alt-08376)	TENGE SIGN
		"₹" => "u20b9", // (alt-08377)	INDIAN RUPEE SIGN
		"℀" => "u2100", // (alt-08448)	ACCOUNT OF
		"℁" => "u2101", // (alt-08449)	ADDRESSED TO THE SUBJECT
		"ℂ" => "u2102", // (alt-08450)	DOUBLE-STRUCK CAPITAL C = the set of complex numbers
		"℃" => "u2103", // (alt-08451)	DEGREE CELSIUS = degrees Centigrade
		"℄" => "u2104", // (alt-08452)	CENTRE LINE SYMBOL = clone
		"℅" => "u2105", // (alt-08453)	CARE OF
		"℆" => "u2106", // (alt-08454)	CADA UNA
		"ℇ" => "u2107", // (alt-08455)	EULER CONSTANT
		"℈" => "u2108", // (alt-08456)	SCRUPLE
		"℉" => "u2109", // (alt-08457)	DEGREE FAHRENHEIT
		"ℊ" => "u210a", // (alt-08458)	SCRIPT SMALL G = real number symbol
		"ℋ" => "u210b", // (alt-08459)	SCRIPT CAPITAL H = Hamiltonian operator
		"ℌ" => "u210c", // (alt-08460)	BLACK-LETTER CAPITAL H = Hilbert space
		"ℍ" => "u210d", // (alt-08461)	DOUBLE-STRUCK CAPITAL H
		"ℎ" => "u210e", // (alt-08462)	PLANCK CONSTANT = height, specific enthalpy, ...
		"ℏ" => "u210f", // (alt-08463)	PLANCK CONSTANT OVER TWO PI
		"ℐ" => "u2110", // (alt-08464)	SCRIPT CAPITAL I
		"ℑ" => "u2111", // (alt-08465)	BLACK-LETTER CAPITAL I = imaginary part
		"ℒ" => "u2112", // (alt-08466)	SCRIPT CAPITAL L = Laplace transform
		"ℓ" => "u2113", // (alt-08467)	SCRIPT SMALL L = mathematical symbol 'ell' = liter (traditional symbol)
		"℔" => "u2114", // (alt-08468)	L B BAR SYMBOL = pounds
		"ℕ" => "u2115", // (alt-08469)	DOUBLE-STRUCK CAPITAL N = natural number
		"№" => "u2116", // (alt-08470)	NUMERO SIGN
		"℗" => "u2117", // (alt-08471)	SOUND RECORDING COPYRIGHT = published = phonorecord sign
		"℘" => "u2118", // (alt-08472)	SCRIPT CAPITAL P
		"ℙ" => "u2119", // (alt-08473)	DOUBLE-STRUCK CAPITAL P
		"ℚ" => "u211a", // (alt-08474)	DOUBLE-STRUCK CAPITAL Q = the set of rational numbers
		"ℛ" => "u211b", // (alt-08475)	SCRIPT CAPITAL R = Riemann Integral
		"ℜ" => "u211c", // (alt-08476)	BLACK-LETTER CAPITAL R = real part
		"ℝ" => "u211d", // (alt-08477)	DOUBLE-STRUCK CAPITAL R = the set of real numbers
		"℞" => "u211e", // (alt-08478)	PRESCRIPTION TAKE = recipe = cross ratio
		"℟" => "u211f", // (alt-08479)	RESPONSE
		"℠" => "u2120", // (alt-08480)	SERVICE MARK
		"℡" => "u2121", // (alt-08481)	TELEPHONE SIGN
		"™" => "u2122", // (alt-08482)	TRADE MARK SIGN
		"℣" => "u2123", // (alt-08483)	VERSICLE
		"ℤ" => "u2124", // (alt-08484)	DOUBLE-STRUCK CAPITAL Z = the set of integers
		"℥" => "u2125", // (alt-08485)	OUNCE SIGN
		"Ω" => "u2126", // (alt-08486)	OHM SIGN
		"℧" => "u2127", // (alt-08487)	INVERTED OHM SIGN = mho
		"ℨ" => "u2128", // (alt-08488)	BLACK-LETTER CAPITAL Z
		"℩" => "u2129", // (alt-08489)	TURNED GREEK SMALL LETTER IOTA
		"K" => "u212a", // (alt-08490)	KELVIN SIGN
		"Å" => "u212b", // (alt-08491)	ANGSTROM SIGN
		"ℬ" => "u212c", // (alt-08492)	SCRIPT CAPITAL B = Bernoulli function
		"ℭ" => "u212d", // (alt-08493)	BLACK-LETTER CAPITAL C
		"℮" => "u212e", // (alt-08494)	ESTIMATED SYMBOL
		"ℯ" => "u212f", // (alt-08495)	SCRIPT SMALL E = error = natural exponent
		"ℰ" => "u2130", // (alt-08496)	SCRIPT CAPITAL E = emf (electromotive force)
		"ℱ" => "u2131", // (alt-08497)	SCRIPT CAPITAL F = Fourier transform
		"Ⅎ" => "u2132", // (alt-08498)	TURNED CAPITAL F = Claudian digamma inversum
		"ℳ" => "u2133", // (alt-08499)	SCRIPT CAPITAL M = M-matrix (physics) = German Mark currency symbol, before WWII
		"ℴ" => "u2134", // (alt-08500)	SCRIPT SMALL O = order, of inferior order to
		"ℵ" => "u2135", // (alt-08501)	ALEF SYMBOL = first transfinite cardinal (countable)
		"ℶ" => "u2136", // (alt-08502)	BET SYMBOL = second transfinite cardinal (the continuum)
		"ℷ" => "u2137", // (alt-08503)	GIMEL SYMBOL = third transfinite cardinal (functions of a real variable)
		"ℸ" => "u2138", // (alt-08504)	DALET SYMBOL = fourth transfinite cardinal
		"⅁" => "u2141", // (alt-08513)	TURNED SANS-SERIF CAPITAL G = game
		"⅂" => "u2142", // (alt-08514)	TURNED SANS-SERIF CAPITAL L
		"⅃" => "u2143", // (alt-08515)	REVERSED SANS-SERIF CAPITAL L
		"⅄" => "u2144", // (alt-08516)	TURNED SANS-SERIF CAPITAL Y
		"ⅅ" => "u2145", // (alt-08517)	DOUBLE-STRUCK ITALIC CAPITAL D
		"ⅆ" => "u2146", // (alt-08518)	DOUBLE-STRUCK ITALIC SMALL D
		"ⅇ" => "u2147", // (alt-08519)	DOUBLE-STRUCK ITALIC SMALL E
		"ⅈ" => "u2148", // (alt-08520)	DOUBLE-STRUCK ITALIC SMALL I
		"ⅉ" => "u2149", // (alt-08521)	DOUBLE-STRUCK ITALIC SMALL J
		"⅋" => "u214b", // (alt-08523)	TURNED AMPERSAND
		"ⅎ" => "u214e", // (alt-08526)	TURNED SMALL F
		"⅐" => "u2150", // (alt-08528)	VULGAR FRACTION ONE SEVENTH
		"⅑" => "u2151", // (alt-08529)	VULGAR FRACTION ONE NINTH
		"⅒" => "u2152", // (alt-08530)	VULGAR FRACTION ONE TENTH
		"⅓" => "u2153", // (alt-08531)	VULGAR FRACTION ONE THIRD
		"⅔" => "u2154", // (alt-08532)	VULGAR FRACTION TWO THIRDS
		"⅕" => "u2155", // (alt-08533)	VULGAR FRACTION ONE FIFTH
		"⅖" => "u2156", // (alt-08534)	VULGAR FRACTION TWO FIFTHS
		"⅗" => "u2157", // (alt-08535)	VULGAR FRACTION THREE FIFTHS
		"⅘" => "u2158", // (alt-08536)	VULGAR FRACTION FOUR FIFTHS
		"⅙" => "u2159", // (alt-08537)	VULGAR FRACTION ONE SIXTH
		"⅚" => "u215a", // (alt-08538)	VULGAR FRACTION FIVE SIXTHS
		"⅛" => "u215b", // (alt-08539)	VULGAR FRACTION ONE EIGHTH
		"⅜" => "u215c", // (alt-08540)	VULGAR FRACTION THREE EIGHTHS
		"⅝" => "u215d", // (alt-08541)	VULGAR FRACTION FIVE EIGHTHS
		"⅞" => "u215e", // (alt-08542)	VULGAR FRACTION SEVEN EIGHTHS
		"⅟" => "u215f", // (alt-08543)	FRACTION NUMERATOR ONE
		"Ⅰ" => "u2160", // (alt-08544)	ROMAN NUMERAL ONE
		"Ⅱ" => "u2161", // (alt-08545)	ROMAN NUMERAL TWO
		"Ⅲ" => "u2162", // (alt-08546)	ROMAN NUMERAL THREE
		"Ⅳ" => "u2163", // (alt-08547)	ROMAN NUMERAL FOUR
		"Ⅴ" => "u2164", // (alt-08548)	ROMAN NUMERAL FIVE
		"Ⅵ" => "u2165", // (alt-08549)	ROMAN NUMERAL SIX
		"Ⅶ" => "u2166", // (alt-08550)	ROMAN NUMERAL SEVEN
		"Ⅷ" => "u2167", // (alt-08551)	ROMAN NUMERAL EIGHT
		"Ⅸ" => "u2168", // (alt-08552)	ROMAN NUMERAL NINE
		"Ⅹ" => "u2169", // (alt-08553)	ROMAN NUMERAL TEN
		"Ⅺ" => "u216a", // (alt-08554)	ROMAN NUMERAL ELEVEN
		"Ⅻ" => "u216b", // (alt-08555)	ROMAN NUMERAL TWELVE
		"Ⅼ" => "u216c", // (alt-08556)	ROMAN NUMERAL FIFTY
		"Ⅽ" => "u216d", // (alt-08557)	ROMAN NUMERAL ONE HUNDRED
		"Ⅾ" => "u216e", // (alt-08558)	ROMAN NUMERAL FIVE HUNDRED
		"Ⅿ" => "u216f", // (alt-08559)	ROMAN NUMERAL ONE THOUSAND
		"ⅰ" => "u2170", // (alt-08560)	SMALL ROMAN NUMERAL ONE
		"ⅱ" => "u2171", // (alt-08561)	SMALL ROMAN NUMERAL TWO
		"ⅲ" => "u2172", // (alt-08562)	SMALL ROMAN NUMERAL THREE
		"ⅳ" => "u2173", // (alt-08563)	SMALL ROMAN NUMERAL FOUR
		"ⅴ" => "u2174", // (alt-08564)	SMALL ROMAN NUMERAL FIVE
		"ⅵ" => "u2175", // (alt-08565)	SMALL ROMAN NUMERAL SIX
		"ⅶ" => "u2176", // (alt-08566)	SMALL ROMAN NUMERAL SEVEN
		"ⅷ" => "u2177", // (alt-08567)	SMALL ROMAN NUMERAL EIGHT
		"ⅸ" => "u2178", // (alt-08568)	SMALL ROMAN NUMERAL NINE
		"ⅹ" => "u2179", // (alt-08569)	SMALL ROMAN NUMERAL TEN
		"ⅺ" => "u217a", // (alt-08570)	SMALL ROMAN NUMERAL ELEVEN
		"ⅻ" => "u217b", // (alt-08571)	SMALL ROMAN NUMERAL TWELVE
		"ⅼ" => "u217c", // (alt-08572)	SMALL ROMAN NUMERAL FIFTY
		"ⅽ" => "u217d", // (alt-08573)	SMALL ROMAN NUMERAL ONE HUNDRED
		"ⅾ" => "u217e", // (alt-08574)	SMALL ROMAN NUMERAL FIVE HUNDRED
		"ⅿ" => "u217f", // (alt-08575)	SMALL ROMAN NUMERAL ONE THOUSAND
		"ↄ" => "u2184", // (alt-08580)	LATIN SMALL LETTER REVERSED C
		"←" => "u2190", // (alt-08592)	LEFTWARDS ARROW
		"↑" => "u2191", // (alt-08593)	UPWARDS ARROW
		"→" => "u2192", // (alt-08594)	RIGHTWARDS ARROW = z notation total function
		"↓" => "u2193", // (alt-08595)	DOWNWARDS ARROW
		"↔" => "u2194", // (alt-08596)	LEFT RIGHT ARROW = z notation relation
		"↕" => "u2195", // (alt-08597)	UP DOWN ARROW
		"↖" => "u2196", // (alt-08598)	NORTH WEST ARROW
		"↗" => "u2197", // (alt-08599)	NORTH EAST ARROW
		"↘" => "u2198", // (alt-08600)	SOUTH EAST ARROW
		"↙" => "u2199", // (alt-08601)	SOUTH WEST ARROW
		"↚" => "u219a", // (alt-08602)	LEFTWARDS ARROW WITH STROKE
		"↛" => "u219b", // (alt-08603)	RIGHTWARDS ARROW WITH STROKE
		"↜" => "u219c", // (alt-08604)	LEFTWARDS WAVE ARROW
		"↝" => "u219d", // (alt-08605)	RIGHTWARDS WAVE ARROW
		"↞" => "u219e", // (alt-08606)	LEFTWARDS TWO HEADED ARROW = fast cursor left
		"↟" => "u219f", // (alt-08607)	UPWARDS TWO HEADED ARROW = fast cursor up
		"↠" => "u21a0", // (alt-08608)	RIGHTWARDS TWO HEADED ARROW = z notation total surjection = fast cursor right
		"↡" => "u21a1", // (alt-08609)	DOWNWARDS TWO HEADED ARROW = form feed = fast cursor down
		"↢" => "u21a2", // (alt-08610)	LEFTWARDS ARROW WITH TAIL
		"↣" => "u21a3", // (alt-08611)	RIGHTWARDS ARROW WITH TAIL = z notation total injection
		"↤" => "u21a4", // (alt-08612)	LEFTWARDS ARROW FROM BAR
		"↥" => "u21a5", // (alt-08613)	UPWARDS ARROW FROM BAR
		"↦" => "u21a6", // (alt-08614)	RIGHTWARDS ARROW FROM BAR = z notation maplet
		"↧" => "u21a7", // (alt-08615)	DOWNWARDS ARROW FROM BAR = depth symbol
		"↨" => "u21a8", // (alt-08616)	UP DOWN ARROW WITH BASE
		"↩" => "u21a9", // (alt-08617)	LEFTWARDS ARROW WITH HOOK
		"↪" => "u21aa", // (alt-08618)	RIGHTWARDS ARROW WITH HOOK
		"↫" => "u21ab", // (alt-08619)	LEFTWARDS ARROW WITH LOOP
		"↬" => "u21ac", // (alt-08620)	RIGHTWARDS ARROW WITH LOOP
		"↭" => "u21ad", // (alt-08621)	LEFT RIGHT WAVE ARROW
		"↮" => "u21ae", // (alt-08622)	LEFT RIGHT ARROW WITH STROKE
		"↯" => "u21af", // (alt-08623)	DOWNWARDS ZIGZAG ARROW = electrolysis
		"↰" => "u21b0", // (alt-08624)	UPWARDS ARROW WITH TIP LEFTWARDS
		"↱" => "u21b1", // (alt-08625)	UPWARDS ARROW WITH TIP RIGHTWARDS
		"↲" => "u21b2", // (alt-08626)	DOWNWARDS ARROW WITH TIP LEFTWARDS
		"↳" => "u21b3", // (alt-08627)	DOWNWARDS ARROW WITH TIP RIGHTWARDS
		"↴" => "u21b4", // (alt-08628)	RIGHTWARDS ARROW WITH CORNER DOWNWARDS = line feed
		"↵" => "u21b5", // (alt-08629)	DOWNWARDS ARROW WITH CORNER LEFTWARDS
		"↶" => "u21b6", // (alt-08630)	ANTICLOCKWISE TOP SEMICIRCLE ARROW
		"↷" => "u21b7", // (alt-08631)	CLOCKWISE TOP SEMICIRCLE ARROW
		"↸" => "u21b8", // (alt-08632)	NORTH WEST ARROW TO LONG BAR = home
		"↹" => "u21b9", // (alt-08633)	LEFTWARDS ARROW TO BAR OVER RIGHTWARDS ARROW TO BAR = tab with shift tab
		"↺" => "u21ba", // (alt-08634)	ANTICLOCKWISE OPEN CIRCLE ARROW
		"↻" => "u21bb", // (alt-08635)	CLOCKWISE OPEN CIRCLE ARROW
		"↼" => "u21bc", // (alt-08636)	LEFTWARDS HARPOON WITH BARB UPWARDS
		"↽" => "u21bd", // (alt-08637)	LEFTWARDS HARPOON WITH BARB DOWNWARDS
		"↾" => "u21be", // (alt-08638)	UPWARDS HARPOON WITH BARB RIGHTWARDS
		"↿" => "u21bf", // (alt-08639)	UPWARDS HARPOON WITH BARB LEFTWARDS
		"⇀" => "u21c0", // (alt-08640)	RIGHTWARDS HARPOON WITH BARB UPWARDS
		"⇁" => "u21c1", // (alt-08641)	RIGHTWARDS HARPOON WITH BARB DOWNWARDS
		"⇂" => "u21c2", // (alt-08642)	DOWNWARDS HARPOON WITH BARB RIGHTWARDS
		"⇃" => "u21c3", // (alt-08643)	DOWNWARDS HARPOON WITH BARB LEFTWARDS
		"⇄" => "u21c4", // (alt-08644)	RIGHTWARDS ARROW OVER LEFTWARDS ARROW
		"⇅" => "u21c5", // (alt-08645)	UPWARDS ARROW LEFTWARDS OF DOWNWARDS ARROW
		"⇆" => "u21c6", // (alt-08646)	LEFTWARDS ARROW OVER RIGHTWARDS ARROW
		"⇇" => "u21c7", // (alt-08647)	LEFTWARDS PAIRED ARROWS
		"⇈" => "u21c8", // (alt-08648)	UPWARDS PAIRED ARROWS
		"⇉" => "u21c9", // (alt-08649)	RIGHTWARDS PAIRED ARROWS
		"⇊" => "u21ca", // (alt-08650)	DOWNWARDS PAIRED ARROWS
		"⇋" => "u21cb", // (alt-08651)	LEFTWARDS HARPOON OVER RIGHTWARDS HARPOON
		"⇌" => "u21cc", // (alt-08652)	RIGHTWARDS HARPOON OVER LEFTWARDS HARPOON
		"⇍" => "u21cd", // (alt-08653)	LEFTWARDS DOUBLE ARROW WITH STROKE
		"⇎" => "u21ce", // (alt-08654)	LEFT RIGHT DOUBLE ARROW WITH STROKE
		"⇏" => "u21cf", // (alt-08655)	RIGHTWARDS DOUBLE ARROW WITH STROKE
		"⇐" => "u21d0", // (alt-08656)	LEFTWARDS DOUBLE ARROW
		"⇑" => "u21d1", // (alt-08657)	UPWARDS DOUBLE ARROW
		"⇒" => "u21d2", // (alt-08658)	RIGHTWARDS DOUBLE ARROW
		"⇓" => "u21d3", // (alt-08659)	DOWNWARDS DOUBLE ARROW
		"⇔" => "u21d4", // (alt-08660)	LEFT RIGHT DOUBLE ARROW
		"⇕" => "u21d5", // (alt-08661)	UP DOWN DOUBLE ARROW
		"⇖" => "u21d6", // (alt-08662)	NORTH WEST DOUBLE ARROW
		"⇗" => "u21d7", // (alt-08663)	NORTH EAST DOUBLE ARROW
		"⇘" => "u21d8", // (alt-08664)	SOUTH EAST DOUBLE ARROW
		"⇙" => "u21d9", // (alt-08665)	SOUTH WEST DOUBLE ARROW
		"⇚" => "u21da", // (alt-08666)	LEFTWARDS TRIPLE ARROW
		"⇛" => "u21db", // (alt-08667)	RIGHTWARDS TRIPLE ARROW
		"⇜" => "u21dc", // (alt-08668)	LEFTWARDS SQUIGGLE ARROW
		"⇝" => "u21dd", // (alt-08669)	RIGHTWARDS SQUIGGLE ARROW
		"⇞" => "u21de", // (alt-08670)	UPWARDS ARROW WITH DOUBLE STROKE = page up
		"⇟" => "u21df", // (alt-08671)	DOWNWARDS ARROW WITH DOUBLE STROKE = page down
		"⇠" => "u21e0", // (alt-08672)	LEFTWARDS DASHED ARROW
		"⇡" => "u21e1", // (alt-08673)	UPWARDS DASHED ARROW
		"⇢" => "u21e2", // (alt-08674)	RIGHTWARDS DASHED ARROW
		"⇣" => "u21e3", // (alt-08675)	DOWNWARDS DASHED ARROW
		"⇤" => "u21e4", // (alt-08676)	LEFTWARDS ARROW TO BAR = leftward tab
		"⇥" => "u21e5", // (alt-08677)	RIGHTWARDS ARROW TO BAR = rightward tab
		"⇦" => "u21e6", // (alt-08678)	LEFTWARDS WHITE ARROW
		"⇧" => "u21e7", // (alt-08679)	UPWARDS WHITE ARROW = shift = level 2 select (ISO 9995-7)
		"⇨" => "u21e8", // (alt-08680)	RIGHTWARDS WHITE ARROW = group select (ISO 9995-7)
		"⇩" => "u21e9", // (alt-08681)	DOWNWARDS WHITE ARROW
		"⇪" => "u21ea", // (alt-08682)	UPWARDS WHITE ARROW FROM BAR = caps lock
		"⇫" => "u21eb", // (alt-08683)	UPWARDS WHITE ARROW ON PEDESTAL = level 2 lock
		"⇬" => "u21ec", // (alt-08684)	UPWARDS WHITE ARROW ON PEDESTAL WITH HORIZONTAL BAR = capitals (caps) lock
		"⇭" => "u21ed", // (alt-08685)	UPWARDS WHITE ARROW ON PEDESTAL WITH VERTICAL BAR = numeric lock
		"⇮" => "u21ee", // (alt-08686)	UPWARDS WHITE DOUBLE ARROW = level 3 select
		"⇯" => "u21ef", // (alt-08687)	UPWARDS WHITE DOUBLE ARROW ON PEDESTAL = level 3 lock
		"⇰" => "u21f0", // (alt-08688)	RIGHTWARDS WHITE ARROW FROM WALL = group lock
		"⇱" => "u21f1", // (alt-08689)	NORTH WEST ARROW TO CORNER = home
		"⇲" => "u21f2", // (alt-08690)	SOUTH EAST ARROW TO CORNER = end
		"⇳" => "u21f3", // (alt-08691)	UP DOWN WHITE ARROW = scrolling
		"⇴" => "u21f4", // (alt-08692)	RIGHT ARROW WITH SMALL CIRCLE
		"⇵" => "u21f5", // (alt-08693)	DOWNWARDS ARROW LEFTWARDS OF UPWARDS ARROW
		"⇶" => "u21f6", // (alt-08694)	THREE RIGHTWARDS ARROWS
		"⇷" => "u21f7", // (alt-08695)	LEFTWARDS ARROW WITH VERTICAL STROKE
		"⇸" => "u21f8", // (alt-08696)	RIGHTWARDS ARROW WITH VERTICAL STROKE = z notation partial function
		"⇹" => "u21f9", // (alt-08697)	LEFT RIGHT ARROW WITH VERTICAL STROKE = z notation partial relation
		"⇺" => "u21fa", // (alt-08698)	LEFTWARDS ARROW WITH DOUBLE VERTICAL STROKE
		"⇻" => "u21fb", // (alt-08699)	RIGHTWARDS ARROW WITH DOUBLE VERTICAL STROKE = z notation finite function
		"⇼" => "u21fc", // (alt-08700)	LEFT RIGHT ARROW WITH DOUBLE VERTICAL STROKE = z notation finite relation
		"⇽" => "u21fd", // (alt-08701)	LEFTWARDS OPEN-HEADED ARROW
		"⇾" => "u21fe", // (alt-08702)	RIGHTWARDS OPEN-HEADED ARROW
		"⇿" => "u21ff", // (alt-08703)	LEFT RIGHT OPEN-HEADED ARROW
		"∀" => "u2200", // (alt-08704)	FOR ALL = universal quantifier
		"∁" => "u2201", // (alt-08705)	COMPLEMENT
		"∂" => "u2202", // (alt-08706)	PARTIAL DIFFERENTIAL
		"∃" => "u2203", // (alt-08707)	THERE EXISTS = existential quantifier
		"∄" => "u2204", // (alt-08708)	THERE DOES NOT EXIST
		"∅" => "u2205", // (alt-08709)	EMPTY SET = null set
		"∆" => "u2206", // (alt-08710)	INCREMENT = Laplace operator = forward difference = symmetric difference (in set theory)
		"∇" => "u2207", // (alt-08711)	NABLA = backward difference = gradient, del
		"∈" => "u2208", // (alt-08712)	ELEMENT OF
		"∉" => "u2209", // (alt-08713)	NOT AN ELEMENT OF
		"∊" => "u220a", // (alt-08714)	SMALL ELEMENT OF
		"∋" => "u220b", // (alt-08715)	CONTAINS AS MEMBER = such that
		"∌" => "u220c", // (alt-08716)	DOES NOT CONTAIN AS MEMBER
		"∍" => "u220d", // (alt-08717)	SMALL CONTAINS AS MEMBER
		"∎" => "u220e", // (alt-08718)	END OF PROOF = q.e.d.
		"∏" => "u220f", // (alt-08719)	N-ARY PRODUCT = product sign
		"∐" => "u2210", // (alt-08720)	N-ARY COPRODUCT = coproduct sign
		"∑" => "u2211", // (alt-08721)	N-ARY SUMMATION = summation sign
		"−" => "u2212", // (alt-08722)	MINUS SIGN
		"∓" => "u2213", // (alt-08723)	MINUS-OR-PLUS SIGN
		"∔" => "u2214", // (alt-08724)	DOT PLUS
		"∕" => "u2215", // (alt-08725)	DIVISION SLASH
		"∖" => "u2216", // (alt-08726)	SET MINUS
		"∗" => "u2217", // (alt-08727)	ASTERISK OPERATOR
		"∘" => "u2218", // (alt-08728)	RING OPERATOR = composite function = APL jot
		"∙" => "u2219", // (alt-08729)	BULLET OPERATOR
		"√" => "u221a", // (alt-08730)	SQUARE ROOT = radical sign
		"∛" => "u221b", // (alt-08731)	CUBE ROOT
		"∜" => "u221c", // (alt-08732)	FOURTH ROOT
		"∝" => "u221d", // (alt-08733)	PROPORTIONAL TO
		"∞" => "u221e", // (alt-08734)	INFINITY
		"∟" => "u221f", // (alt-08735)	RIGHT ANGLE
		"∠" => "u2220", // (alt-08736)	ANGLE
		"∡" => "u2221", // (alt-08737)	MEASURED ANGLE
		"∢" => "u2222", // (alt-08738)	SPHERICAL ANGLE = angle arc
		"∣" => "u2223", // (alt-08739)	DIVIDES = such that = APL stile
		"∤" => "u2224", // (alt-08740)	DOES NOT DIVIDE
		"∥" => "u2225", // (alt-08741)	PARALLEL TO
		"∦" => "u2226", // (alt-08742)	NOT PARALLEL TO
		"∧" => "u2227", // (alt-08743)	LOGICAL AND = wedge, conjunction
		"∨" => "u2228", // (alt-08744)	LOGICAL OR = vee, disjunction
		"∩" => "u2229", // (alt-08745)	INTERSECTION = cap, hat
		"∪" => "u222a", // (alt-08746)	UNION = cup
		"∫" => "u222b", // (alt-08747)	INTEGRAL
		"∬" => "u222c", // (alt-08748)	DOUBLE INTEGRAL
		"∭" => "u222d", // (alt-08749)	TRIPLE INTEGRAL
		"∮" => "u222e", // (alt-08750)	CONTOUR INTEGRAL
		"∯" => "u222f", // (alt-08751)	SURFACE INTEGRAL
		"∰" => "u2230", // (alt-08752)	VOLUME INTEGRAL
		"∱" => "u2231", // (alt-08753)	CLOCKWISE INTEGRAL
		"∲" => "u2232", // (alt-08754)	CLOCKWISE CONTOUR INTEGRAL
		"∳" => "u2233", // (alt-08755)	ANTICLOCKWISE CONTOUR INTEGRAL
		"∴" => "u2234", // (alt-08756)	THEREFORE (Freemason symbol - three dots - 3 points symbol). This 3 dos can also be &there4; in HTML.
		"∵" => "u2235", // (alt-08757)	BECAUSE
		"∶" => "u2236", // (alt-08758)	RATIO
		"∷" => "u2237", // (alt-08759)	PROPORTION
		"∸" => "u2238", // (alt-08760)	DOT MINUS = saturating subtraction
		"∹" => "u2239", // (alt-08761)	EXCESS
		"∺" => "u223a", // (alt-08762)	GEOMETRIC PROPORTION
		"∻" => "u223b", // (alt-08763)	HOMOTHETIC
		"∼" => "u223c", // (alt-08764)	TILDE OPERATOR = varies with (proportional to) = difference between = similar to = not = cycle = APL tilde
		"∽" => "u223d", // (alt-08765)	REVERSED TILDE = lazy S
		"∾" => "u223e", // (alt-08766)	INVERTED LAZY S = most positive
		"∿" => "u223f", // (alt-08767)	SINE WAVE = alternating current
		"≀" => "u2240", // (alt-08768)	WREATH PRODUCT
		"≁" => "u2241", // (alt-08769)	NOT TILDE
		"≂" => "u2242", // (alt-08770)	MINUS TILDE
		"≃" => "u2243", // (alt-08771)	ASYMPTOTICALLY EQUAL TO
		"≄" => "u2244", // (alt-08772)	NOT ASYMPTOTICALLY EQUAL TO
		"≅" => "u2245", // (alt-08773)	APPROXIMATELY EQUAL TO
		"≆" => "u2246", // (alt-08774)	APPROXIMATELY BUT NOT ACTUALLY EQUAL TO
		"≇" => "u2247", // (alt-08775)	NEITHER APPROXIMATELY NOR ACTUALLY EQUAL TO
		"≈" => "u2248", // (alt-08776)	ALMOST EQUAL TO = asymptotic to
		"≉" => "u2249", // (alt-08777)	NOT ALMOST EQUAL TO
		"≊" => "u224a", // (alt-08778)	ALMOST EQUAL OR EQUAL TO
		"≋" => "u224b", // (alt-08779)	TRIPLE TILDE
		"≌" => "u224c", // (alt-08780)	ALL EQUAL TO
		"≍" => "u224d", // (alt-08781)	EQUIVALENT TO
		"≎" => "u224e", // (alt-08782)	GEOMETRICALLY EQUIVALENT TO
		"≏" => "u224f", // (alt-08783)	DIFFERENCE BETWEEN
		"≐" => "u2250", // (alt-08784)	APPROACHES THE LIMIT
		"≑" => "u2251", // (alt-08785)	GEOMETRICALLY EQUAL TO
		"≒" => "u2252", // (alt-08786)	APPROXIMATELY EQUAL TO OR THE IMAGE OF = nearly equals
		"≓" => "u2253", // (alt-08787)	IMAGE OF OR APPROXIMATELY EQUAL TO
		"≔" => "u2254", // (alt-08788)	COLON EQUALS
		"≕" => "u2255", // (alt-08789)	EQUALS COLON
		"≖" => "u2256", // (alt-08790)	RING IN EQUAL TO
		"≗" => "u2257", // (alt-08791)	RING EQUAL TO = approximately equal to
		"≘" => "u2258", // (alt-08792)	CORRESPONDS TO
		"≙" => "u2259", // (alt-08793)	ESTIMATES = corresponds to
		"≚" => "u225a", // (alt-08794)	EQUIANGULAR TO
		"≛" => "u225b", // (alt-08795)	STAR EQUALS
		"≜" => "u225c", // (alt-08796)	DELTA EQUAL TO = equiangular = equal to by definition
		"≝" => "u225d", // (alt-08797)	EQUAL TO BY DEFINITION
		"≞" => "u225e", // (alt-08798)	MEASURED BY
		"≟" => "u225f", // (alt-08799)	QUESTIONED EQUAL TO
		"≠" => "u2260", // (alt-08800)	NOT EQUAL TO
		"≡" => "u2261", // (alt-08801)	IDENTICAL TO
		"≢" => "u2262", // (alt-08802)	NOT IDENTICAL TO
		"≣" => "u2263", // (alt-08803)	STRICTLY EQUIVALENT TO
		"≤" => "u2264", // (alt-08804)	LESS-THAN OR EQUAL TO
		"≥" => "u2265", // (alt-08805)	GREATER-THAN OR EQUAL TO
		"≦" => "u2266", // (alt-08806)	LESS-THAN OVER EQUAL TO
		"≧" => "u2267", // (alt-08807)	GREATER-THAN OVER EQUAL TO
		"≨" => "u2268", // (alt-08808)	LESS-THAN BUT NOT EQUAL TO
		"≩" => "u2269", // (alt-08809)	GREATER-THAN BUT NOT EQUAL TO
		"≪" => "u226a", // (alt-08810)	MUCH LESS-THAN
		"≫" => "u226b", // (alt-08811)	MUCH GREATER-THAN
		"≬" => "u226c", // (alt-08812)	BETWEEN = plaintiff, quantic
		"≭" => "u226d", // (alt-08813)	NOT EQUIVALENT TO
		"≮" => "u226e", // (alt-08814)	NOT LESS-THAN
		"≯" => "u226f", // (alt-08815)	NOT GREATER-THAN
		"≰" => "u2270", // (alt-08816)	NEITHER LESS-THAN NOR EQUAL TO
		"≱" => "u2271", // (alt-08817)	NEITHER GREATER-THAN NOR EQUAL TO
		"≲" => "u2272", // (alt-08818)	LESS-THAN OR EQUIVALENT TO
		"≳" => "u2273", // (alt-08819)	GREATER-THAN OR EQUIVALENT TO
		"≴" => "u2274", // (alt-08820)	NEITHER LESS-THAN NOR EQUIVALENT TO
		"≵" => "u2275", // (alt-08821)	NEITHER GREATER-THAN NOR EQUIVALENT TO
		"≶" => "u2276", // (alt-08822)	LESS-THAN OR GREATER-THAN
		"≷" => "u2277", // (alt-08823)	GREATER-THAN OR LESS-THAN
		"≸" => "u2278", // (alt-08824)	NEITHER LESS-THAN NOR GREATER-THAN
		"≹" => "u2279", // (alt-08825)	NEITHER GREATER-THAN NOR LESS-THAN
		"≺" => "u227a", // (alt-08826)	PRECEDES = lower rank than
		"≻" => "u227b", // (alt-08827)	SUCCEEDS = higher rank than
		"≼" => "u227c", // (alt-08828)	PRECEDES OR EQUAL TO
		"≽" => "u227d", // (alt-08829)	SUCCEEDS OR EQUAL TO
		"≾" => "u227e", // (alt-08830)	PRECEDES OR EQUIVALENT TO
		"≿" => "u227f", // (alt-08831)	SUCCEEDS OR EQUIVALENT TO
		"⊀" => "u2280", // (alt-08832)	DOES NOT PRECEDE
		"⊁" => "u2281", // (alt-08833)	DOES NOT SUCCEED
		"⊂" => "u2282", // (alt-08834)	SUBSET OF = included in set
		"⊃" => "u2283", // (alt-08835)	SUPERSET OF = includes in set
		"⊄" => "u2284", // (alt-08836)	NOT A SUBSET OF
		"⊅" => "u2285", // (alt-08837)	NOT A SUPERSET OF
		"⊆" => "u2286", // (alt-08838)	SUBSET OF OR EQUAL TO
		"⊇" => "u2287", // (alt-08839)	SUPERSET OF OR EQUAL TO
		"⊈" => "u2288", // (alt-08840)	NEITHER A SUBSET OF NOR EQUAL TO
		"⊉" => "u2289", // (alt-08841)	NEITHER A SUPERSET OF NOR EQUAL TO
		"⊊" => "u228a", // (alt-08842)	SUBSET OF WITH NOT EQUAL TO
		"⊋" => "u228b", // (alt-08843)	SUPERSET OF WITH NOT EQUAL TO
		"⊌" => "u228c", // (alt-08844)	MULTISET
		"⊍" => "u228d", // (alt-08845)	MULTISET MULTIPLICATION
		"⊎" => "u228e", // (alt-08846)	MULTISET UNION = z notation bag addition
		"⊏" => "u228f", // (alt-08847)	SQUARE IMAGE OF
		"⊐" => "u2290", // (alt-08848)	SQUARE ORIGINAL OF
		"⊑" => "u2291", // (alt-08849)	SQUARE IMAGE OF OR EQUAL TO
		"⊒" => "u2292", // (alt-08850)	SQUARE ORIGINAL OF OR EQUAL TO
		"⊓" => "u2293", // (alt-08851)	SQUARE CAP
		"⊔" => "u2294", // (alt-08852)	SQUARE CUP
		"⊕" => "u2295", // (alt-08853)	CIRCLED PLUS = direct sum = vector pointing into page
		"⊖" => "u2296", // (alt-08854)	CIRCLED MINUS = symmetric difference
		"⊗" => "u2297", // (alt-08855)	CIRCLED TIMES = tensor product = vector pointing into page
		"⊘" => "u2298", // (alt-08856)	CIRCLED DIVISION SLASH
		"⊙" => "u2299", // (alt-08857)	CIRCLED DOT OPERATOR = direct product = vector pointing out of page
		"⊚" => "u229a", // (alt-08858)	CIRCLED RING OPERATOR
		"⊛" => "u229b", // (alt-08859)	CIRCLED ASTERISK OPERATOR
		"⊜" => "u229c", // (alt-08860)	CIRCLED EQUALS
		"⊝" => "u229d", // (alt-08861)	CIRCLED DASH
		"⊞" => "u229e", // (alt-08862)	SQUARED PLUS
		"⊟" => "u229f", // (alt-08863)	SQUARED MINUS
		"⊠" => "u22a0", // (alt-08864)	SQUARED TIMES
		"⊡" => "u22a1", // (alt-08865)	SQUARED DOT OPERATOR
		"⊢" => "u22a2", // (alt-08866)	RIGHT TACK = turnstile = proves, implies, yields = reducible
		"⊣" => "u22a3", // (alt-08867)	LEFT TACK = reverse turnstile = non-theorem, does not yield
		"⊤" => "u22a4", // (alt-08868)	DOWN TACK = top
		"⊥" => "u22a5", // (alt-08869)	UP TACK = base, bottom
		"⊦" => "u22a6", // (alt-08870)	ASSERTION = reduces to
		"⊧" => "u22a7", // (alt-08871)	MODELS
		"⊨" => "u22a8", // (alt-08872)	TRUE = statement is true, valid = is a tautology = satisfies = results in
		"⊩" => "u22a9", // (alt-08873)	FORCES
		"⊪" => "u22aa", // (alt-08874)	TRIPLE VERTICAL BAR RIGHT TURNSTILE
		"⊫" => "u22ab", // (alt-08875)	DOUBLE VERTICAL BAR DOUBLE RIGHT TURNSTILE
		"⊬" => "u22ac", // (alt-08876)	DOES NOT PROVE
		"⊭" => "u22ad", // (alt-08877)	NOT TRUE
		"⊮" => "u22ae", // (alt-08878)	DOES NOT FORCE
		"⊯" => "u22af", // (alt-08879)	NEGATED DOUBLE VERTICAL BAR DOUBLE RIGHT TURNSTILE
		"⊰" => "u22b0", // (alt-08880)	PRECEDES UNDER RELATION
		"⊱" => "u22b1", // (alt-08881)	SUCCEEDS UNDER RELATION
		"⊲" => "u22b2", // (alt-08882)	NORMAL SUBGROUP OF
		"⊳" => "u22b3", // (alt-08883)	CONTAINS AS NORMAL SUBGROUP
		"⊴" => "u22b4", // (alt-08884)	NORMAL SUBGROUP OF OR EQUAL TO
		"⊵" => "u22b5", // (alt-08885)	CONTAINS AS NORMAL SUBGROUP OR EQUAL TO
		"⊶" => "u22b6", // (alt-08886)	ORIGINAL OF
		"⊷" => "u22b7", // (alt-08887)	IMAGE OF
		"⊸" => "u22b8", // (alt-08888)	MULTIMAP
		"⊹" => "u22b9", // (alt-08889)	HERMITIAN CONJUGATE MATRIX
		"⊺" => "u22ba", // (alt-08890)	INTERCALATE
		"⊻" => "u22bb", // (alt-08891)	XOR
		"⊼" => "u22bc", // (alt-08892)	NAND
		"⊽" => "u22bd", // (alt-08893)	NOR
		"⊾" => "u22be", // (alt-08894)	RIGHT ANGLE WITH ARC
		"⊿" => "u22bf", // (alt-08895)	RIGHT TRIANGLE
		"⋀" => "u22c0", // (alt-08896)	N-ARY LOGICAL AND
		"⋁" => "u22c1", // (alt-08897)	N-ARY LOGICAL OR
		"⋂" => "u22c2", // (alt-08898)	N-ARY INTERSECTION = z notation generalised intersection
		"⋃" => "u22c3", // (alt-08899)	N-ARY UNION = z notation generalised union
		"⋄" => "u22c4", // (alt-08900)	DIAMOND OPERATOR
		"⋅" => "u22c5", // (alt-08901)	DOT OPERATOR
		"⋆" => "u22c6", // (alt-08902)	STAR OPERATOR
		"⋇" => "u22c7", // (alt-08903)	DIVISION TIMES
		"⋈" => "u22c8", // (alt-08904)	BOWTIE
		"⋉" => "u22c9", // (alt-08905)	LEFT NORMAL FACTOR SEMIDIRECT PRODUCT
		"⋊" => "u22ca", // (alt-08906)	RIGHT NORMAL FACTOR SEMIDIRECT PRODUCT
		"⋋" => "u22cb", // (alt-08907)	LEFT SEMIDIRECT PRODUCT
		"⋌" => "u22cc", // (alt-08908)	RIGHT SEMIDIRECT PRODUCT
		"⋍" => "u22cd", // (alt-08909)	REVERSED TILDE EQUALS
		"⋎" => "u22ce", // (alt-08910)	CURLY LOGICAL OR
		"⋏" => "u22cf", // (alt-08911)	CURLY LOGICAL AND
		"⋐" => "u22d0", // (alt-08912)	DOUBLE SUBSET
		"⋑" => "u22d1", // (alt-08913)	DOUBLE SUPERSET
		"⋒" => "u22d2", // (alt-08914)	DOUBLE INTERSECTION
		"⋓" => "u22d3", // (alt-08915)	DOUBLE UNION
		"⋔" => "u22d4", // (alt-08916)	PITCHFORK = proper intersection
		"⋕" => "u22d5", // (alt-08917)	EQUAL AND PARALLEL TO
		"⋖" => "u22d6", // (alt-08918)	LESS-THAN WITH DOT
		"⋗" => "u22d7", // (alt-08919)	GREATER-THAN WITH DOT
		"⋘" => "u22d8", // (alt-08920)	VERY MUCH LESS-THAN
		"⋙" => "u22d9", // (alt-08921)	VERY MUCH GREATER-THAN
		"⋚" => "u22da", // (alt-08922)	LESS-THAN EQUAL TO OR GREATER-THAN
		"⋛" => "u22db", // (alt-08923)	GREATER-THAN EQUAL TO OR LESS-THAN
		"⋜" => "u22dc", // (alt-08924)	EQUAL TO OR LESS-THAN
		"⋝" => "u22dd", // (alt-08925)	EQUAL TO OR GREATER-THAN
		"⋞" => "u22de", // (alt-08926)	EQUAL TO OR PRECEDES
		"⋟" => "u22df", // (alt-08927)	EQUAL TO OR SUCCEEDS
		"⋠" => "u22e0", // (alt-08928)	DOES NOT PRECEDE OR EQUAL
		"⋡" => "u22e1", // (alt-08929)	DOES NOT SUCCEED OR EQUAL
		"⋢" => "u22e2", // (alt-08930)	NOT SQUARE IMAGE OF OR EQUAL TO
		"⋣" => "u22e3", // (alt-08931)	NOT SQUARE ORIGINAL OF OR EQUAL TO
		"⋤" => "u22e4", // (alt-08932)	SQUARE IMAGE OF OR NOT EQUAL TO
		"⋥" => "u22e5", // (alt-08933)	SQUARE ORIGINAL OF OR NOT EQUAL TO
		"⋦" => "u22e6", // (alt-08934)	LESS-THAN BUT NOT EQUIVALENT TO
		"⋧" => "u22e7", // (alt-08935)	GREATER-THAN BUT NOT EQUIVALENT TO
		"⋨" => "u22e8", // (alt-08936)	PRECEDES BUT NOT EQUIVALENT TO
		"⋩" => "u22e9", // (alt-08937)	SUCCEEDS BUT NOT EQUIVALENT TO
		"⋪" => "u22ea", // (alt-08938)	NOT NORMAL SUBGROUP OF
		"⋫" => "u22eb", // (alt-08939)	DOES NOT CONTAIN AS NORMAL SUBGROUP
		"⋬" => "u22ec", // (alt-08940)	NOT NORMAL SUBGROUP OF OR EQUAL TO
		"⋭" => "u22ed", // (alt-08941)	DOES NOT CONTAIN AS NORMAL SUBGROUP OR EQUAL
		"⋮" => "u22ee", // (alt-08942)	VERTICAL ELLIPSIS
		"⋯" => "u22ef", // (alt-08943)	MIDLINE HORIZONTAL ELLIPSIS
		"⋰" => "u22f0", // (alt-08944)	UP RIGHT DIAGONAL ELLIPSIS
		"⋱" => "u22f1", // (alt-08945)	DOWN RIGHT DIAGONAL ELLIPSIS
		"⌀" => "u2300", // (alt-08960)	DIAMETER SIGN
		"⌁" => "u2301", // (alt-08961)	ELECTRIC ARROW
		"⌂" => "u2302", // (alt-08962)	HOUSE
		"⌃" => "u2303", // (alt-08963)	UP ARROWHEAD
		"⌄" => "u2304", // (alt-08964)	DOWN ARROWHEAD
		"⌅" => "u2305", // (alt-08965)	PROJECTIVE
		"⌆" => "u2306", // (alt-08966)	PERSPECTIVE
		"⌇" => "u2307", // (alt-08967)	WAVY LINE
		"⌈" => "u2308", // (alt-08968)	LEFT CEILING = APL upstile
		"⌉" => "u2309", // (alt-08969)	RIGHT CEILING
		"⌊" => "u230a", // (alt-08970)	LEFT FLOOR = APL downstile
		"⌋" => "u230b", // (alt-08971)	RIGHT FLOOR
		"⌐" => "u2310", // (alt-08976)	REVERSED NOT SIGN = beginning of line
		"⌑" => "u2311", // (alt-08977)	SQUARE LOZENGE = Kissen (pillow)
		"⌒" => "u2312", // (alt-08978)	ARC = position of any line
		"⌓" => "u2313", // (alt-08979)	SEGMENT = position of a surface
		"⌔" => "u2314", // (alt-08980)	SECTOR
		"⌕" => "u2315", // (alt-08981)	TELEPHONE RECORDER
		"⌖" => "u2316", // (alt-08982)	POSITION INDICATOR = true position
		"⌗" => "u2317", // (alt-08983)	VIEWDATA SQUARE
		"⌘" => "u2318", // (alt-08984)	PLACE OF INTEREST SIGN = command key = operating system key (ISO 9995-7)
		"⌙" => "u2319", // (alt-08985)	TURNED NOT SIGN = line marker
		"⌚" => "u231a", // (alt-08986)	WATCH
		"⌛" => "u231b", // (alt-08987)	HOURGLASS = alchemical symbol for hour
		"⌠" => "u2320", // (alt-08992)	TOP HALF INTEGRAL
		"⌡" => "u2321", // (alt-08993)	BOTTOM HALF INTEGRAL
		"⌢" => "u2322", // (alt-08994)	FROWN
		"⌣" => "u2323", // (alt-08995)	SMILE
		"⌤" => "u2324", // (alt-08996)	UP ARROWHEAD BETWEEN TWO HORIZONTAL BARS = enter key
		"⌥" => "u2325", // (alt-08997)	OPTION KEY
		"⌦" => "u2326", // (alt-08998)	ERASE TO THE RIGHT = delete to the right key
		"⌧" => "u2327", // (alt-08999)	X IN A RECTANGLE BOX = clear key
		"⌨" => "u2328", // (alt-09000)	KEYBOARD
		"⌫" => "u232b", // (alt-09003)	ERASE TO THE LEFT = delete to the left key
		"⌬" => "u232c", // (alt-09004)	BENZENE RING
		"⎛" => "u239b", // (alt-09115)	LEFT PARENTHESIS UPPER HOOK
		"⎜" => "u239c", // (alt-09116)	LEFT PARENTHESIS EXTENSION
		"⎝" => "u239d", // (alt-09117)	LEFT PARENTHESIS LOWER HOOK
		"⎞" => "u239e", // (alt-09118)	RIGHT PARENTHESIS UPPER HOOK
		"⎟" => "u239f", // (alt-09119)	RIGHT PARENTHESIS EXTENSION
		"⎠" => "u23a0", // (alt-09120)	RIGHT PARENTHESIS LOWER HOOK
		"⎡" => "u23a1", // (alt-09121)	LEFT SQUARE BRACKET UPPER CORNER
		"⎢" => "u23a2", // (alt-09122)	LEFT SQUARE BRACKET EXTENSION
		"⎣" => "u23a3", // (alt-09123)	LEFT SQUARE BRACKET LOWER CORNER
		"⎤" => "u23a4", // (alt-09124)	RIGHT SQUARE BRACKET UPPER CORNER
		"⎥" => "u23a5", // (alt-09125)	RIGHT SQUARE BRACKET EXTENSION
		"⎦" => "u23a6", // (alt-09126)	RIGHT SQUARE BRACKET LOWER CORNER
		"⎧" => "u23a7", // (alt-09127)	LEFT CURLY BRACKET UPPER HOOK
		"⎨" => "u23a8", // (alt-09128)	LEFT CURLY BRACKET MIDDLE PIECE
		"⎩" => "u23a9", // (alt-09129)	LEFT CURLY BRACKET LOWER HOOK
		"⎪" => "u23aa", // (alt-09130)	CURLY BRACKET EXTENSION
		"⎫" => "u23ab", // (alt-09131)	RIGHT CURLY BRACKET UPPER HOOK
		"⎬" => "u23ac", // (alt-09132)	RIGHT CURLY BRACKET MIDDLE PIECE
		"⎭" => "u23ad", // (alt-09133)	RIGHT CURLY BRACKET LOWER HOOK
		"⏎" => "u23ce", // (alt-09166)	RETURN SYMBOL
		"⏏" => "u23cf", // (alt-09167)	EJECT SYMBOL
		"⏚" => "u23da", // (alt-09178)	EARTH GROUND
		"⏛" => "u23db", // (alt-09179)	FUSE
		"⏰" => "u23f0", // (alt-09200)	ALARM CLOCK
		"⏱" => "u23f1", // (alt-09201)	STOPWATCH
		"⏲" => "u23f2", // (alt-09202)	TIMER CLOCK
		"⏳" => "u23f3", // (alt-09203)	HOURGLASS WITH FLOWING SAND
		"␢" => "u2422", // (alt-09250)	BLANK SYMBOL
		"␣" => "u2423", // (alt-09251)	OPEN BOX
		"─" => "u2500", // (alt-09472)	BOX DRAWINGS LIGHT HORIZONTAL = Videotex Mosaic DG 15
		"━" => "u2501", // (alt-09473)	BOX DRAWINGS HEAVY HORIZONTAL
		"│" => "u2502", // (alt-09474)	BOX DRAWINGS LIGHT VERTICAL = Videotex Mosaic DG 14
		"┃" => "u2503", // (alt-09475)	BOX DRAWINGS HEAVY VERTICAL
		"┄" => "u2504", // (alt-09476)	BOX DRAWINGS LIGHT TRIPLE DASH HORIZONTAL
		"┅" => "u2505", // (alt-09477)	BOX DRAWINGS HEAVY TRIPLE DASH HORIZONTAL
		"┆" => "u2506", // (alt-09478)	BOX DRAWINGS LIGHT TRIPLE DASH VERTICAL
		"┇" => "u2507", // (alt-09479)	BOX DRAWINGS HEAVY TRIPLE DASH VERTICAL
		"┈" => "u2508", // (alt-09480)	BOX DRAWINGS LIGHT QUADRUPLE DASH HORIZONTAL
		"┉" => "u2509", // (alt-09481)	BOX DRAWINGS HEAVY QUADRUPLE DASH HORIZONTAL
		"┊" => "u250a", // (alt-09482)	BOX DRAWINGS LIGHT QUADRUPLE DASH VERTICAL
		"┋" => "u250b", // (alt-09483)	BOX DRAWINGS HEAVY QUADRUPLE DASH VERTICAL
		"┌" => "u250c", // (alt-09484)	BOX DRAWINGS LIGHT DOWN AND RIGHT = Videotex Mosaic DG 16
		"┍" => "u250d", // (alt-09485)	BOX DRAWINGS DOWN LIGHT AND RIGHT HEAVY
		"┎" => "u250e", // (alt-09486)	BOX DRAWINGS DOWN HEAVY AND RIGHT LIGHT
		"┏" => "u250f", // (alt-09487)	BOX DRAWINGS HEAVY DOWN AND RIGHT
		"┐" => "u2510", // (alt-09488)	BOX DRAWINGS LIGHT DOWN AND LEFT = Videotex Mosaic DG 17
		"┑" => "u2511", // (alt-09489)	BOX DRAWINGS DOWN LIGHT AND LEFT HEAVY
		"┒" => "u2512", // (alt-09490)	BOX DRAWINGS DOWN HEAVY AND LEFT LIGHT
		"┓" => "u2513", // (alt-09491)	BOX DRAWINGS HEAVY DOWN AND LEFT
		"└" => "u2514", // (alt-09492)	BOX DRAWINGS LIGHT UP AND RIGHT = Videotex Mosaic DG 18
		"┕" => "u2515", // (alt-09493)	BOX DRAWINGS UP LIGHT AND RIGHT HEAVY
		"┖" => "u2516", // (alt-09494)	BOX DRAWINGS UP HEAVY AND RIGHT LIGHT
		"┗" => "u2517", // (alt-09495)	BOX DRAWINGS HEAVY UP AND RIGHT
		"┘" => "u2518", // (alt-09496)	BOX DRAWINGS LIGHT UP AND LEFT = Videotex Mosaic DG 19
		"┙" => "u2519", // (alt-09497)	BOX DRAWINGS UP LIGHT AND LEFT HEAVY
		"┚" => "u251a", // (alt-09498)	BOX DRAWINGS UP HEAVY AND LEFT LIGHT
		"┛" => "u251b", // (alt-09499)	BOX DRAWINGS HEAVY UP AND LEFT
		"├" => "u251c", // (alt-09500)	BOX DRAWINGS LIGHT VERTICAL AND RIGHT = Videotex Mosaic DG 20
		"┝" => "u251d", // (alt-09501)	BOX DRAWINGS VERTICAL LIGHT AND RIGHT HEAVY = Videotex Mosaic DG 03
		"┞" => "u251e", // (alt-09502)	BOX DRAWINGS UP HEAVY AND RIGHT DOWN LIGHT
		"┟" => "u251f", // (alt-09503)	BOX DRAWINGS DOWN HEAVY AND RIGHT UP LIGHT
		"┠" => "u2520", // (alt-09504)	BOX DRAWINGS VERTICAL HEAVY AND RIGHT LIGHT
		"┡" => "u2521", // (alt-09505)	BOX DRAWINGS DOWN LIGHT AND RIGHT UP HEAVY
		"┢" => "u2522", // (alt-09506)	BOX DRAWINGS UP LIGHT AND RIGHT DOWN HEAVY
		"┣" => "u2523", // (alt-09507)	BOX DRAWINGS HEAVY VERTICAL AND RIGHT
		"┤" => "u2524", // (alt-09508)	BOX DRAWINGS LIGHT VERTICAL AND LEFT = Videotex Mosaic DG 21
		"┥" => "u2525", // (alt-09509)	BOX DRAWINGS VERTICAL LIGHT AND LEFT HEAVY = Videotex Mosaic DG 04
		"┦" => "u2526", // (alt-09510)	BOX DRAWINGS UP HEAVY AND LEFT DOWN LIGHT
		"┧" => "u2527", // (alt-09511)	BOX DRAWINGS DOWN HEAVY AND LEFT UP LIGHT
		"┨" => "u2528", // (alt-09512)	BOX DRAWINGS VERTICAL HEAVY AND LEFT LIGHT
		"┩" => "u2529", // (alt-09513)	BOX DRAWINGS DOWN LIGHT AND LEFT UP HEAVY
		"┪" => "u252a", // (alt-09514)	BOX DRAWINGS UP LIGHT AND LEFT DOWN HEAVY
		"┫" => "u252b", // (alt-09515)	BOX DRAWINGS HEAVY VERTICAL AND LEFT
		"┬" => "u252c", // (alt-09516)	BOX DRAWINGS LIGHT DOWN AND HORIZONTAL = Videotex Mosaic DG 22
		"┭" => "u252d", // (alt-09517)	BOX DRAWINGS LEFT HEAVY AND RIGHT DOWN LIGHT
		"┮" => "u252e", // (alt-09518)	BOX DRAWINGS RIGHT HEAVY AND LEFT DOWN LIGHT
		"┯" => "u252f", // (alt-09519)	BOX DRAWINGS DOWN LIGHT AND HORIZONTAL HEAVY = Videotex Mosaic DG 02
		"┰" => "u2530", // (alt-09520)	BOX DRAWINGS DOWN HEAVY AND HORIZONTAL LIGHT
		"┱" => "u2531", // (alt-09521)	BOX DRAWINGS RIGHT LIGHT AND LEFT DOWN HEAVY
		"┲" => "u2532", // (alt-09522)	BOX DRAWINGS LEFT LIGHT AND RIGHT DOWN HEAVY
		"┳" => "u2533", // (alt-09523)	BOX DRAWINGS HEAVY DOWN AND HORIZONTAL
		"┴" => "u2534", // (alt-09524)	BOX DRAWINGS LIGHT UP AND HORIZONTAL = Videotex Mosaic DG 23
		"┵" => "u2535", // (alt-09525)	BOX DRAWINGS LEFT HEAVY AND RIGHT UP LIGHT
		"┶" => "u2536", // (alt-09526)	BOX DRAWINGS RIGHT HEAVY AND LEFT UP LIGHT
		"┷" => "u2537", // (alt-09527)	BOX DRAWINGS UP LIGHT AND HORIZONTAL HEAVY = Videotex Mosaic DG 01
		"┸" => "u2538", // (alt-09528)	BOX DRAWINGS UP HEAVY AND HORIZONTAL LIGHT
		"┹" => "u2539", // (alt-09529)	BOX DRAWINGS RIGHT LIGHT AND LEFT UP HEAVY
		"┺" => "u253a", // (alt-09530)	BOX DRAWINGS LEFT LIGHT AND RIGHT UP HEAVY
		"┻" => "u253b", // (alt-09531)	BOX DRAWINGS HEAVY UP AND HORIZONTAL
		"┼" => "u253c", // (alt-09532)	BOX DRAWINGS LIGHT VERTICAL AND HORIZONTAL = Videotex Mosaic DG 24
		"┽" => "u253d", // (alt-09533)	BOX DRAWINGS LEFT HEAVY AND RIGHT VERTICAL LIGHT
		"┾" => "u253e", // (alt-09534)	BOX DRAWINGS RIGHT HEAVY AND LEFT VERTICAL LIGHT
		"┿" => "u253f", // (alt-09535)	BOX DRAWINGS VERTICAL LIGHT AND HORIZONTAL HEAVY = Videotex Mosaic DG 13
		"╀" => "u2540", // (alt-09536)	BOX DRAWINGS UP HEAVY AND DOWN HORIZONTAL LIGHT
		"╁" => "u2541", // (alt-09537)	BOX DRAWINGS DOWN HEAVY AND UP HORIZONTAL LIGHT
		"╂" => "u2542", // (alt-09538)	BOX DRAWINGS VERTICAL HEAVY AND HORIZONTAL LIGHT
		"╃" => "u2543", // (alt-09539)	BOX DRAWINGS LEFT UP HEAVY AND RIGHT DOWN LIGHT
		"╄" => "u2544", // (alt-09540)	BOX DRAWINGS RIGHT UP HEAVY AND LEFT DOWN LIGHT
		"╅" => "u2545", // (alt-09541)	BOX DRAWINGS LEFT DOWN HEAVY AND RIGHT UP LIGHT
		"╆" => "u2546", // (alt-09542)	BOX DRAWINGS RIGHT DOWN HEAVY AND LEFT UP LIGHT
		"╇" => "u2547", // (alt-09543)	BOX DRAWINGS DOWN LIGHT AND UP HORIZONTAL HEAVY
		"╈" => "u2548", // (alt-09544)	BOX DRAWINGS UP LIGHT AND DOWN HORIZONTAL HEAVY
		"╉" => "u2549", // (alt-09545)	BOX DRAWINGS RIGHT LIGHT AND LEFT VERTICAL HEAVY
		"╊" => "u254a", // (alt-09546)	BOX DRAWINGS LEFT LIGHT AND RIGHT VERTICAL HEAVY
		"╋" => "u254b", // (alt-09547)	BOX DRAWINGS HEAVY VERTICAL AND HORIZONTAL
		"╌" => "u254c", // (alt-09548)	BOX DRAWINGS LIGHT DOUBLE DASH HORIZONTAL
		"╍" => "u254d", // (alt-09549)	BOX DRAWINGS HEAVY DOUBLE DASH HORIZONTAL
		"╎" => "u254e", // (alt-09550)	BOX DRAWINGS LIGHT DOUBLE DASH VERTICAL
		"╏" => "u254f", // (alt-09551)	BOX DRAWINGS HEAVY DOUBLE DASH VERTICAL
		"═" => "u2550", // (alt-09552)	BOX DRAWINGS DOUBLE HORIZONTAL
		"║" => "u2551", // (alt-09553)	BOX DRAWINGS DOUBLE VERTICAL
		"╒" => "u2552", // (alt-09554)	BOX DRAWINGS DOWN SINGLE AND RIGHT DOUBLE
		"╓" => "u2553", // (alt-09555)	BOX DRAWINGS DOWN DOUBLE AND RIGHT SINGLE
		"╔" => "u2554", // (alt-09556)	BOX DRAWINGS DOUBLE DOWN AND RIGHT
		"╕" => "u2555", // (alt-09557)	BOX DRAWINGS DOWN SINGLE AND LEFT DOUBLE
		"╖" => "u2556", // (alt-09558)	BOX DRAWINGS DOWN DOUBLE AND LEFT SINGLE
		"╗" => "u2557", // (alt-09559)	BOX DRAWINGS DOUBLE DOWN AND LEFT
		"╘" => "u2558", // (alt-09560)	BOX DRAWINGS UP SINGLE AND RIGHT DOUBLE
		"╙" => "u2559", // (alt-09561)	BOX DRAWINGS UP DOUBLE AND RIGHT SINGLE
		"╚" => "u255a", // (alt-09562)	BOX DRAWINGS DOUBLE UP AND RIGHT
		"╛" => "u255b", // (alt-09563)	BOX DRAWINGS UP SINGLE AND LEFT DOUBLE
		"╜" => "u255c", // (alt-09564)	BOX DRAWINGS UP DOUBLE AND LEFT SINGLE
		"╝" => "u255d", // (alt-09565)	BOX DRAWINGS DOUBLE UP AND LEFT
		"╞" => "u255e", // (alt-09566)	BOX DRAWINGS VERTICAL SINGLE AND RIGHT DOUBLE
		"╟" => "u255f", // (alt-09567)	BOX DRAWINGS VERTICAL DOUBLE AND RIGHT SINGLE
		"╠" => "u2560", // (alt-09568)	BOX DRAWINGS DOUBLE VERTICAL AND RIGHT
		"╡" => "u2561", // (alt-09569)	BOX DRAWINGS VERTICAL SINGLE AND LEFT DOUBLE
		"╢" => "u2562", // (alt-09570)	BOX DRAWINGS VERTICAL DOUBLE AND LEFT SINGLE
		"╣" => "u2563", // (alt-09571)	BOX DRAWINGS DOUBLE VERTICAL AND LEFT
		"╤" => "u2564", // (alt-09572)	BOX DRAWINGS DOWN SINGLE AND HORIZONTAL DOUBLE
		"╥" => "u2565", // (alt-09573)	BOX DRAWINGS DOWN DOUBLE AND HORIZONTAL SINGLE
		"╦" => "u2566", // (alt-09574)	BOX DRAWINGS DOUBLE DOWN AND HORIZONTAL
		"╧" => "u2567", // (alt-09575)	BOX DRAWINGS UP SINGLE AND HORIZONTAL DOUBLE
		"╨" => "u2568", // (alt-09576)	BOX DRAWINGS UP DOUBLE AND HORIZONTAL SINGLE
		"╩" => "u2569", // (alt-09577)	BOX DRAWINGS DOUBLE UP AND HORIZONTAL
		"╪" => "u256a", // (alt-09578)	BOX DRAWINGS VERTICAL SINGLE AND HORIZONTAL DOUBLE
		"╫" => "u256b", // (alt-09579)	BOX DRAWINGS VERTICAL DOUBLE AND HORIZONTAL SINGLE
		"╬" => "u256c", // (alt-09580)	BOX DRAWINGS DOUBLE VERTICAL AND HORIZONTAL
		"╭" => "u256d", // (alt-09581)	BOX DRAWINGS LIGHT ARC DOWN AND RIGHT
		"╮" => "u256e", // (alt-09582)	BOX DRAWINGS LIGHT ARC DOWN AND LEFT
		"╯" => "u256f", // (alt-09583)	BOX DRAWINGS LIGHT ARC UP AND LEFT
		"╰" => "u2570", // (alt-09584)	BOX DRAWINGS LIGHT ARC UP AND RIGHT
		"╱" => "u2571", // (alt-09585)	BOX DRAWINGS LIGHT DIAGONAL UPPER RIGHT TO LOWER LEFT
		"╲" => "u2572", // (alt-09586)	BOX DRAWINGS LIGHT DIAGONAL UPPER LEFT TO LOWER RIGHT
		"╳" => "u2573", // (alt-09587)	BOX DRAWINGS LIGHT DIAGONAL CROSS
		"╴" => "u2574", // (alt-09588)	BOX DRAWINGS LIGHT LEFT
		"╵" => "u2575", // (alt-09589)	BOX DRAWINGS LIGHT UP
		"╶" => "u2576", // (alt-09590)	BOX DRAWINGS LIGHT RIGHT
		"╷" => "u2577", // (alt-09591)	BOX DRAWINGS LIGHT DOWN
		"╸" => "u2578", // (alt-09592)	BOX DRAWINGS HEAVY LEFT
		"╹" => "u2579", // (alt-09593)	BOX DRAWINGS HEAVY UP
		"╺" => "u257a", // (alt-09594)	BOX DRAWINGS HEAVY RIGHT
		"╻" => "u257b", // (alt-09595)	BOX DRAWINGS HEAVY DOWN
		"╼" => "u257c", // (alt-09596)	BOX DRAWINGS LIGHT LEFT AND HEAVY RIGHT
		"╽" => "u257d", // (alt-09597)	BOX DRAWINGS LIGHT UP AND HEAVY DOWN
		"╾" => "u257e", // (alt-09598)	BOX DRAWINGS HEAVY LEFT AND LIGHT RIGHT
		"╿" => "u257f", // (alt-09599)	BOX DRAWINGS HEAVY UP AND LIGHT DOWN
		"▀" => "u2580", // (alt-09600)	UPPER HALF BLOCK
		"▁" => "u2581", // (alt-09601)	LOWER ONE EIGHTH BLOCK
		"▂" => "u2582", // (alt-09602)	LOWER ONE QUARTER BLOCK
		"▃" => "u2583", // (alt-09603)	LOWER THREE EIGHTHS BLOCK
		"▄" => "u2584", // (alt-09604)	LOWER HALF BLOCK
		"▅" => "u2585", // (alt-09605)	LOWER FIVE EIGHTHS BLOCK
		"▆" => "u2586", // (alt-09606)	LOWER THREE QUARTERS BLOCK
		"▇" => "u2587", // (alt-09607)	LOWER SEVEN EIGHTHS BLOCK
		"█" => "u2588", // (alt-09608)	FULL BLOCK = solid
		"▉" => "u2589", // (alt-09609)	LEFT SEVEN EIGHTHS BLOCK
		"▊" => "u258a", // (alt-09610)	LEFT THREE QUARTERS BLOCK
		"▋" => "u258b", // (alt-09611)	LEFT FIVE EIGHTHS BLOCK
		"▌" => "u258c", // (alt-09612)	LEFT HALF BLOCK
		"▍" => "u258d", // (alt-09613)	LEFT THREE EIGHTHS BLOCK
		"▎" => "u258e", // (alt-09614)	LEFT ONE QUARTER BLOCK
		"▏" => "u258f", // (alt-09615)	LEFT ONE EIGHTH BLOCK
		"▐" => "u2590", // (alt-09616)	RIGHT HALF BLOCK
		"░" => "u2591", // (alt-09617)	LIGHT SHADE
		"▒" => "u2592", // (alt-09618)	MEDIUM SHADE
		"▓" => "u2593", // (alt-09619)	DARK SHADE
		"▔" => "u2594", // (alt-09620)	UPPER ONE EIGHTH BLOCK
		"▕" => "u2595", // (alt-09621)	RIGHT ONE EIGHTH BLOCK
		"▖" => "u2596", // (alt-09622)	QUADRANT LOWER LEFT
		"▗" => "u2597", // (alt-09623)	QUADRANT LOWER RIGHT
		"▘" => "u2598", // (alt-09624)	QUADRANT UPPER LEFT
		"▙" => "u2599", // (alt-09625)	QUADRANT UPPER LEFT AND LOWER LEFT AND LOWER RIGHT
		"▚" => "u259a", // (alt-09626)	QUADRANT UPPER LEFT AND LOWER RIGHT
		"▛" => "u259b", // (alt-09627)	QUADRANT UPPER LEFT AND UPPER RIGHT AND LOWER LEFT
		"▜" => "u259c", // (alt-09628)	QUADRANT UPPER LEFT AND UPPER RIGHT AND LOWER RIGHT
		"▝" => "u259d", // (alt-09629)	QUADRANT UPPER RIGHT
		"▞" => "u259e", // (alt-09630)	QUADRANT UPPER RIGHT AND LOWER LEFT
		"▟" => "u259f", // (alt-09631)	QUADRANT UPPER RIGHT AND LOWER LEFT AND LOWER RIGHT
		"■" => "u25a0", // (alt-09632)	BLACK SQUARE = moding mark (in ideographic text)
		"□" => "u25a1", // (alt-09633)	WHITE SQUARE = quadrature = alchemical symbol for salt
		"▢" => "u25a2", // (alt-09634)	WHITE SQUARE WITH ROUNDED CORNERS
		"▣" => "u25a3", // (alt-09635)	WHITE SQUARE CONTAINING BLACK SMALL SQUARE
		"▤" => "u25a4", // (alt-09636)	SQUARE WITH HORIZONTAL FILL
		"▥" => "u25a5", // (alt-09637)	SQUARE WITH VERTICAL FILL
		"▦" => "u25a6", // (alt-09638)	SQUARE WITH ORTHOGONAL CROSSHATCH FILL
		"▧" => "u25a7", // (alt-09639)	SQUARE WITH UPPER LEFT TO LOWER RIGHT FILL
		"▨" => "u25a8", // (alt-09640)	SQUARE WITH UPPER RIGHT TO LOWER LEFT FILL
		"▩" => "u25a9", // (alt-09641)	SQUARE WITH DIAGONAL CROSSHATCH FILL
		"▪" => "u25aa", // (alt-09642)	BLACK SMALL SQUARE = square bullet
		"▫" => "u25ab", // (alt-09643)	WHITE SMALL SQUARE
		"▬" => "u25ac", // (alt-09644)	BLACK RECTANGLE
		"▭" => "u25ad", // (alt-09645)	WHITE RECTANGLE
		"▮" => "u25ae", // (alt-09646)	BLACK VERTICAL RECTANGLE = histogram marker
		"▯" => "u25af", // (alt-09647)	WHITE VERTICAL RECTANGLE
		"▰" => "u25b0", // (alt-09648)	BLACK PARALLELOGRAM
		"▱" => "u25b1", // (alt-09649)	WHITE PARALLELOGRAM
		"▲" => "u25b2", // (alt-09650)	BLACK UP-POINTING TRIANGLE
		"△" => "u25b3", // (alt-09651)	WHITE UP-POINTING TRIANGLE = trine
		"▴" => "u25b4", // (alt-09652)	BLACK UP-POINTING SMALL TRIANGLE
		"▵" => "u25b5", // (alt-09653)	WHITE UP-POINTING SMALL TRIANGLE
		"▶" => "u25b6", // (alt-09654)	BLACK RIGHT-POINTING TRIANGLE
		"▷" => "u25b7", // (alt-09655)	WHITE RIGHT-POINTING TRIANGLE = z notation range restriction
		"▸" => "u25b8", // (alt-09656)	BLACK RIGHT-POINTING SMALL TRIANGLE
		"▹" => "u25b9", // (alt-09657)	WHITE RIGHT-POINTING SMALL TRIANGLE
		"►" => "u25ba", // (alt-09658)	BLACK RIGHT-POINTING POINTER
		"▻" => "u25bb", // (alt-09659)	WHITE RIGHT-POINTING POINTER = forward arrow indicator
		"▼" => "u25bc", // (alt-09660)	BLACK DOWN-POINTING TRIANGLE
		"▽" => "u25bd", // (alt-09661)	WHITE DOWN-POINTING TRIANGLE = Hamilton operator
		"▾" => "u25be", // (alt-09662)	BLACK DOWN-POINTING SMALL TRIANGLE
		"▿" => "u25bf", // (alt-09663)	WHITE DOWN-POINTING SMALL TRIANGLE
		"◀" => "u25c0", // (alt-09664)	BLACK LEFT-POINTING TRIANGLE
		"◁" => "u25c1", // (alt-09665)	WHITE LEFT-POINTING TRIANGLE = z notation domain restriction
		"◂" => "u25c2", // (alt-09666)	BLACK LEFT-POINTING SMALL TRIANGLE
		"◃" => "u25c3", // (alt-09667)	WHITE LEFT-POINTING SMALL TRIANGLE
		"◄" => "u25c4", // (alt-09668)	BLACK LEFT-POINTING POINTER
		"◅" => "u25c5", // (alt-09669)	WHITE LEFT-POINTING POINTER = backward arrow indicator
		"◆" => "u25c6", // (alt-09670)	BLACK DIAMOND
		"◇" => "u25c7", // (alt-09671)	WHITE DIAMOND
		"◈" => "u25c8", // (alt-09672)	WHITE DIAMOND CONTAINING BLACK SMALL DIAMOND
		"◉" => "u25c9", // (alt-09673)	FISHEYE = tainome (Japanese, a kind of bullet)
		"◊" => "u25ca", // (alt-09674)	LOZENGE
		"○" => "u25cb", // (alt-09675)	WHITE CIRCLE
		"◌" => "u25cc", // (alt-09676)	DOTTED CIRCLE
		"◍" => "u25cd", // (alt-09677)	CIRCLE WITH VERTICAL FILL
		"◎" => "u25ce", // (alt-09678)	BULLSEYE
		"●" => "u25cf", // (alt-09679)	BLACK CIRCLE
		"◐" => "u25d0", // (alt-09680)	CIRCLE WITH LEFT HALF BLACK
		"◑" => "u25d1", // (alt-09681)	CIRCLE WITH RIGHT HALF BLACK
		"◒" => "u25d2", // (alt-09682)	CIRCLE WITH LOWER HALF BLACK
		"◓" => "u25d3", // (alt-09683)	CIRCLE WITH UPPER HALF BLACK
		"◔" => "u25d4", // (alt-09684)	CIRCLE WITH UPPER RIGHT QUADRANT BLACK
		"◕" => "u25d5", // (alt-09685)	CIRCLE WITH ALL BUT UPPER LEFT QUADRANT BLACK
		"◖" => "u25d6", // (alt-09686)	LEFT HALF BLACK CIRCLE
		"◗" => "u25d7", // (alt-09687)	RIGHT HALF BLACK CIRCLE
		"◘" => "u25d8", // (alt-09688)	INVERSE BULLET
		"◙" => "u25d9", // (alt-09689)	INVERSE WHITE CIRCLE
		"◚" => "u25da", // (alt-09690)	UPPER HALF INVERSE WHITE CIRCLE
		"◛" => "u25db", // (alt-09691)	LOWER HALF INVERSE WHITE CIRCLE
		"◜" => "u25dc", // (alt-09692)	UPPER LEFT QUADRANT CIRCULAR ARC
		"◝" => "u25dd", // (alt-09693)	UPPER RIGHT QUADRANT CIRCULAR ARC
		"◞" => "u25de", // (alt-09694)	LOWER RIGHT QUADRANT CIRCULAR ARC
		"◟" => "u25df", // (alt-09695)	LOWER LEFT QUADRANT CIRCULAR ARC
		"◠" => "u25e0", // (alt-09696)	UPPER HALF CIRCLE
		"◡" => "u25e1", // (alt-09697)	LOWER HALF CIRCLE
		"◢" => "u25e2", // (alt-09698)	BLACK LOWER RIGHT TRIANGLE
		"◣" => "u25e3", // (alt-09699)	BLACK LOWER LEFT TRIANGLE
		"◤" => "u25e4", // (alt-09700)	BLACK UPPER LEFT TRIANGLE
		"◥" => "u25e5", // (alt-09701)	BLACK UPPER RIGHT TRIANGLE
		"◦" => "u25e6", // (alt-09702)	WHITE BULLET
		"◧" => "u25e7", // (alt-09703)	SQUARE WITH LEFT HALF BLACK
		"◨" => "u25e8", // (alt-09704)	SQUARE WITH RIGHT HALF BLACK
		"◩" => "u25e9", // (alt-09705)	SQUARE WITH UPPER LEFT DIAGONAL HALF BLACK
		"◪" => "u25ea", // (alt-09706)	SQUARE WITH LOWER RIGHT DIAGONAL HALF BLACK
		"◫" => "u25eb", // (alt-09707)	WHITE SQUARE WITH VERTICAL BISECTING LINE
		"◬" => "u25ec", // (alt-09708)	WHITE UP-POINTING TRIANGLE WITH DOT
		"◭" => "u25ed", // (alt-09709)	UP-POINTING TRIANGLE WITH LEFT HALF BLACK
		"◮" => "u25ee", // (alt-09710)	UP-POINTING TRIANGLE WITH RIGHT HALF BLACK
		"◯" => "u25ef", // (alt-09711)	LARGE CIRCLE
		"◰" => "u25f0", // (alt-09712)	WHITE SQUARE WITH UPPER LEFT QUADRANT
		"◱" => "u25f1", // (alt-09713)	WHITE SQUARE WITH LOWER LEFT QUADRANT
		"◲" => "u25f2", // (alt-09714)	WHITE SQUARE WITH LOWER RIGHT QUADRANT
		"◳" => "u25f3", // (alt-09715)	WHITE SQUARE WITH UPPER RIGHT QUADRANT
		"◴" => "u25f4", // (alt-09716)	WHITE CIRCLE WITH UPPER LEFT QUADRANT
		"◵" => "u25f5", // (alt-09717)	WHITE CIRCLE WITH LOWER LEFT QUADRANT
		"◶" => "u25f6", // (alt-09718)	WHITE CIRCLE WITH LOWER RIGHT QUADRANT
		"◷" => "u25f7", // (alt-09719)	WHITE CIRCLE WITH UPPER RIGHT QUADRANT
		"◸" => "u25f8", // (alt-09720)	UPPER LEFT TRIANGLE
		"◹" => "u25f9", // (alt-09721)	UPPER RIGHT TRIANGLE
		"◺" => "u25fa", // (alt-09722)	LOWER LEFT TRIANGLE
		"◻" => "u25fb", // (alt-09723)	WHITE MEDIUM SQUARE = always (modal operator)
		"◼" => "u25fc", // (alt-09724)	BLACK MEDIUM SQUARE
		"◽" => "u25fd", // (alt-09725)	WHITE MEDIUM SMALL SQUARE
		"◾" => "u25fe", // (alt-09726)	BLACK MEDIUM SMALL SQUARE
		"◿" => "u25ff", // (alt-09727)	LOWER RIGHT TRIANGLE
		"☀" => "u2600", // (alt-09728)	BLACK SUN WITH RAYS = clear weather
		"☁" => "u2601", // (alt-09729)	CLOUD = cloudy weather
		"☂" => "u2602", // (alt-09730)	UMBRELLA = rainy weather
		"☃" => "u2603", // (alt-09731)	SNOWMAN = snowy weather
		"☄" => "u2604", // (alt-09732)	COMET
		"★" => "u2605", // (alt-09733)	BLACK STAR
		"☆" => "u2606", // (alt-09734)	WHITE STAR
		"☇" => "u2607", // (alt-09735)	LIGHTNING
		"☈" => "u2608", // (alt-09736)	THUNDERSTORM
		"☉" => "u2609", // (alt-09737)	SUN = alchemical symbol for gold
		"☊" => "u260a", // (alt-09738)	ASCENDING NODE = alchemical symbol for sublimate
		"☋" => "u260b", // (alt-09739)	DESCENDING NODE = alchemical symbol for purify
		"☌" => "u260c", // (alt-09740)	CONJUNCTION = alchemical symbol for day
		"☍" => "u260d", // (alt-09741)	OPPOSITION
		"☎" => "u260e", // (alt-09742)	BLACK TELEPHONE
		"☏" => "u260f", // (alt-09743)	WHITE TELEPHONE
		"☐" => "u2610", // (alt-09744)	BALLOT BOX
		"☑" => "u2611", // (alt-09745)	BALLOT BOX WITH CHECK
		"☒" => "u2612", // (alt-09746)	BALLOT BOX WITH X
		"☓" => "u2613", // (alt-09747)	SALTIRE = St. Andrew's Cross
		"☔" => "u2614", // (alt-09748)	UMBRELLA WITH RAIN DROPS = showery weather
		"☕" => "u2615", // (alt-09749)	HOT BEVERAGE = tea or coffee, depending on locale
		"☖" => "u2616", // (alt-09750)	WHITE SHOGI PIECE
		"☗" => "u2617", // (alt-09751)	BLACK SHOGI PIECE
		"☘" => "u2618", // (alt-09752)	SHAMROCK
		"☙" => "u2619", // (alt-09753)	REVERSED ROTATED FLORAL HEART BULLET
		"☚" => "u261a", // (alt-09754)	BLACK LEFT POINTING INDEX
		"☛" => "u261b", // (alt-09755)	BLACK RIGHT POINTING INDEX
		"☜" => "u261c", // (alt-09756)	WHITE LEFT POINTING INDEX
		"☝" => "u261d", // (alt-09757)	WHITE UP POINTING INDEX
		"☞" => "u261e", // (alt-09758)	WHITE RIGHT POINTING INDEX = fist (typographic term)
		"☟" => "u261f", // (alt-09759)	WHITE DOWN POINTING INDEX
		"☠" => "u2620", // (alt-09760)	SKULL AND CROSSBONES (poison)
		"☡" => "u2621", // (alt-09761)	CAUTION SIGN
		"☢" => "u2622", // (alt-09762)	RADIOACTIVE SIGN
		"☣" => "u2623", // (alt-09763)	BIOHAZARD SIGN
		"☤" => "u2624", // (alt-09764)	CADUCEUS
		"☥" => "u2625", // (alt-09765)	ANKH
		"☦" => "u2626", // (alt-09766)	ORTHODOX CROSS
		"☧" => "u2627", // (alt-09767)	CHI RHO = Constantine's cross, Christogram
		"☨" => "u2628", // (alt-09768)	CROSS OF LORRAINE
		"☩" => "u2629", // (alt-09769)	CROSS OF JERUSALEM
		"☪" => "u262a", // (alt-09770)	STAR AND CRESCENT
		"☫" => "u262b", // (alt-09771)	FARSI SYMBOL = symbol of iran
		"☬" => "u262c", // (alt-09772)	ADI SHAKTI = Gurmukhi khanda
		"☭" => "u262d", // (alt-09773)	HAMMER AND SICKLE
		"☮" => "u262e", // (alt-09774)	PEACE SYMBOL
		"☯" => "u262f", // (alt-09775)	YIN YANG
		"☰" => "u2630", // (alt-09776)	TRIGRAM FOR HEAVEN = qian2
		"☱" => "u2631", // (alt-09777)	TRIGRAM FOR LAKE = dui4
		"☲" => "u2632", // (alt-09778)	TRIGRAM FOR FIRE = li2
		"☳" => "u2633", // (alt-09779)	TRIGRAM FOR THUNDER = zhen4
		"☴" => "u2634", // (alt-09780)	TRIGRAM FOR WIND = xun4
		"☵" => "u2635", // (alt-09781)	TRIGRAM FOR WATER = kan3
		"☶" => "u2636", // (alt-09782)	TRIGRAM FOR MOUNTAIN = gen4
		"☷" => "u2637", // (alt-09783)	TRIGRAM FOR EARTH = kun1
		"☸" => "u2638", // (alt-09784)	WHEEL OF DHARMA
		"☹" => "u2639", // (alt-09785)	WHITE FROWNING FACE
		"☺" => "u263a", // (alt-09786)	WHITE SMILING FACE = have a nice day!
		"☻" => "u263b", // (alt-09787)	BLACK SMILING FACE
		"☼" => "u263c", // (alt-09788)	WHITE SUN WITH RAYS = compass
		"☽" => "u263d", // (alt-09789)	FIRST QUARTER MOON = alchemical symbol for silver
		"☾" => "u263e", // (alt-09790)	LAST QUARTER MOON = alchemical symbol for silver
		"☿" => "u263f", // (alt-09791)	MERCURY = alchemical symbol for quicksilver
		"♀" => "u2640", // (alt-09792)	FEMALE SIGN = Venus = alchemical symbol for copper
		"♁" => "u2641", // (alt-09793)	EARTH = alchemical symbol for antimony
		"♂" => "u2642", // (alt-09794)	MALE SIGN = Mars = alchemical symbol for iron
		"♃" => "u2643", // (alt-09795)	JUPITER = alchemical symbol for tin
		"♄" => "u2644", // (alt-09796)	SATURN = alchemical symbol for lead
		"♅" => "u2645", // (alt-09797)	URANUS
		"♆" => "u2646", // (alt-09798)	NEPTUNE = alchemical symbol for bismuth/tinglass
		"♇" => "u2647", // (alt-09799)	PLUTO
		"♈" => "u2648", // (alt-09800)	ARIES
		"♉" => "u2649", // (alt-09801)	TAURUS
		"♊" => "u264a", // (alt-09802)	GEMINI
		"♋" => "u264b", // (alt-09803)	CANCER
		"♌" => "u264c", // (alt-09804)	LEO
		"♍" => "u264d", // (alt-09805)	VIRGO = minim (alternate glyph)
		"♎" => "u264e", // (alt-09806)	LIBRA
		"♏" => "u264f", // (alt-09807)	SCORPIUS = scorpio = minim, drop
		"♐" => "u2650", // (alt-09808)	SAGITTARIUS
		"♑" => "u2651", // (alt-09809)	CAPRICORN
		"♒" => "u2652", // (alt-09810)	AQUARIUS
		"♓" => "u2653", // (alt-09811)	PISCES
		"♔" => "u2654", // (alt-09812)	WHITE CHESS KING
		"♕" => "u2655", // (alt-09813)	WHITE CHESS QUEEN
		"♖" => "u2656", // (alt-09814)	WHITE CHESS ROOK
		"♗" => "u2657", // (alt-09815)	WHITE CHESS BISHOP
		"♘" => "u2658", // (alt-09816)	WHITE CHESS KNIGHT
		"♙" => "u2659", // (alt-09817)	WHITE CHESS PAWN
		"♚" => "u265a", // (alt-09818)	BLACK CHESS KING
		"♛" => "u265b", // (alt-09819)	BLACK CHESS QUEEN
		"♜" => "u265c", // (alt-09820)	BLACK CHESS ROOK
		"♝" => "u265d", // (alt-09821)	BLACK CHESS BISHOP
		"♞" => "u265e", // (alt-09822)	BLACK CHESS KNIGHT
		"♟" => "u265f", // (alt-09823)	BLACK CHESS PAWN
		"♠" => "u2660", // (alt-09824)	BLACK SPADE SUIT
		"♡" => "u2661", // (alt-09825)	WHITE HEART SUIT
		"♢" => "u2662", // (alt-09826)	WHITE DIAMOND SUIT
		"♣" => "u2663", // (alt-09827)	BLACK CLUB SUIT
		"♤" => "u2664", // (alt-09828)	WHITE SPADE SUIT
		"♥" => "u2665", // (alt-09829)	BLACK HEART SUIT = valentine
		"♦" => "u2666", // (alt-09830)	BLACK DIAMOND SUIT
		"♧" => "u2667", // (alt-09831)	WHITE CLUB SUIT
		"♨" => "u2668", // (alt-09832)	HOT SPRINGS
		"♩" => "u2669", // (alt-09833)	QUARTER NOTE = crotchet
		"♪" => "u266a", // (alt-09834)	EIGHTH NOTE = quaver
		"♫" => "u266b", // (alt-09835)	BEAMED EIGHTH NOTES = beamed quavers
		"♬" => "u266c", // (alt-09836)	BEAMED SIXTEENTH NOTES = beamed semiquavers
		"♭" => "u266d", // (alt-09837)	MUSIC FLAT SIGN
		"♮" => "u266e", // (alt-09838)	MUSIC NATURAL SIGN
		"♯" => "u266f", // (alt-09839)	MUSIC SHARP SIGN = z notation infix bag count
		"♲" => "u2672", // (alt-09842)	UNIVERSAL RECYCLING SYMBOL
		"♳" => "u2673", // (alt-09843)	RECYCLING SYMBOL FOR TYPE-1 PLASTICS
		"♴" => "u2674", // (alt-09844)	RECYCLING SYMBOL FOR TYPE-2 PLASTICS
		"♵" => "u2675", // (alt-09845)	RECYCLING SYMBOL FOR TYPE-3 PLASTICS
		"♶" => "u2676", // (alt-09846)	RECYCLING SYMBOL FOR TYPE-4 PLASTICS
		"♷" => "u2677", // (alt-09847)	RECYCLING SYMBOL FOR TYPE-5 PLASTICS
		"♸" => "u2678", // (alt-09848)	RECYCLING SYMBOL FOR TYPE-6 PLASTICS
		"♹" => "u2679", // (alt-09849)	RECYCLING SYMBOL FOR TYPE-7 PLASTICS
		"♺" => "u267a", // (alt-09850)	RECYCLING SYMBOL FOR GENERIC MATERIALS
		"♻" => "u267b", // (alt-09851)	BLACK UNIVERSAL RECYCLING SYMBOL
		"♼" => "u267c", // (alt-09852)	RECYCLED PAPER SYMBOL
		"♽" => "u267d", // (alt-09853)	PARTIALLY-RECYCLED PAPER SYMBOL
		"♾" => "u267e", // (alt-09854)	PERMANENT PAPER SIGN
		"♿" => "u267f", // (alt-09855)	WHEELCHAIR SYMBOL
		"⚀" => "u2680", // (alt-09856)	DIE FACE-1
		"⚁" => "u2681", // (alt-09857)	DIE FACE-2
		"⚂" => "u2682", // (alt-09858)	DIE FACE-3
		"⚃" => "u2683", // (alt-09859)	DIE FACE-4
		"⚄" => "u2684", // (alt-09860)	DIE FACE-5
		"⚅" => "u2685", // (alt-09861)	DIE FACE-6
		"⚐" => "u2690", // (alt-09872)	WHITE FLAG
		"⚑" => "u2691", // (alt-09873)	BLACK FLAG
		"⚒" => "u2692", // (alt-09874)	HAMMER AND PICK = mining, working day (in timetables)
		"⚓" => "u2693", // (alt-09875)	ANCHOR = nautical term, harbor (on maps)
		"⚔" => "u2694", // (alt-09876)	CROSSED SWORDS = military term, battleground (on maps), killed in action
		"⚕" => "u2695", // (alt-09877)	STAFF OF AESCULAPIUS = medical term
		"⚖" => "u2696", // (alt-09878)	SCALES = legal term, jurisprudence
		"⚗" => "u2697", // (alt-09879)	ALEMBIC = chemical term, chemistry
		"⚘" => "u2698", // (alt-09880)	FLOWER = botanical term
		"⚙" => "u2699", // (alt-09881)	GEAR = technology, tools
		"⚚" => "u269a", // (alt-09882)	STAFF OF HERMES
		"⚛" => "u269b", // (alt-09883)	ATOM SYMBOL = nuclear installation (on maps)
		"⚜" => "u269c", // (alt-09884)	FLEUR-DE-LIS
		"⚝" => "u269d", // (alt-09885)	OUTLINED WHITE STAR
		"⚞" => "u269e", // (alt-09886)	THREE LINES CONVERGING RIGHT = someone speaking
		"⚟" => "u269f", // (alt-09887)	THREE LINES CONVERGING LEFT = background speaking
		"⚠" => "u26a0", // (alt-09888)	WARNING SIGN
		"⚡" => "u26a1", // (alt-09889)	HIGH VOLTAGE SIGN = thunder = lightning symbol
		"⚢" => "u26a2", // (alt-09890)	DOUBLED FEMALE SIGN = lesbianism
		"⚣" => "u26a3", // (alt-09891)	DOUBLED MALE SIGN = male homosexuality
		"⚤" => "u26a4", // (alt-09892)	INTERLOCKED FEMALE AND MALE SIGN = bisexuality
		"⚥" => "u26a5", // (alt-09893)	MALE AND FEMALE SIGN = transgendered sexuality = hermaphrodite (in entomology)
		"⚦" => "u26a6", // (alt-09894)	MALE WITH STROKE SIGN = transgendered sexuality = alchemical symbol for iron or crocus of iron
		"⚧" => "u26a7", // (alt-09895)	MALE WITH STROKE AND MALE AND FEMALE SIGN = transgendered sexuality
		"⚨" => "u26a8", // (alt-09896)	VERTICAL MALE WITH STROKE SIGN = alchemical symbol for iron
		"⚩" => "u26a9", // (alt-09897)	HORIZONTAL MALE WITH STROKE SIGN = alchemical symbol for iron
		"⚪" => "u26aa", // (alt-09898)	MEDIUM WHITE CIRCLE = asexuality, sexless, genderless = engaged, betrothed
		"⚫" => "u26ab", // (alt-09899)	MEDIUM BLACK CIRCLE
		"⚬" => "u26ac", // (alt-09900)	MEDIUM SMALL WHITE CIRCLE = engaged, betrothed (genealogy)
		"⚭" => "u26ad", // (alt-09901)	MARRIAGE SYMBOL
		"⚮" => "u26ae", // (alt-09902)	DIVORCE SYMBOL
		"⚯" => "u26af", // (alt-09903)	UNMARRIED PARTNERSHIP SYMBOL
		"⚰" => "u26b0", // (alt-09904)	COFFIN = buried (genealogy)
		"⚱" => "u26b1", // (alt-09905)	FUNERAL URN = cremated (genealogy)
		"⚲" => "u26b2", // (alt-09906)	NEUTER
		"⚳" => "u26b3", // (alt-09907)	CERES
		"⚴" => "u26b4", // (alt-09908)	PALLAS
		"⚵" => "u26b5", // (alt-09909)	JUNO
		"⚶" => "u26b6", // (alt-09910)	VESTA
		"⚷" => "u26b7", // (alt-09911)	CHIRON
		"⚸" => "u26b8", // (alt-09912)	BLACK MOON LILITH
		"⚹" => "u26b9", // (alt-09913)	SEXTILE
		"⚺" => "u26ba", // (alt-09914)	SEMISEXTILE
		"⚻" => "u26bb", // (alt-09915)	QUINCUNX
		"⚼" => "u26bc", // (alt-09916)	SESQUIQUADRATE
		"⛀" => "u26c0", // (alt-09920)	WHITE DRAUGHTS MAN
		"⛁" => "u26c1", // (alt-09921)	WHITE DRAUGHTS KING
		"⛂" => "u26c2", // (alt-09922)	BLACK DRAUGHTS MAN
		"⛃" => "u26c3", // (alt-09923)	BLACK DRAUGHTS KING
		"⛢" => "u26e2", // (alt-09954)	ASTRONOMICAL SYMBOL FOR URANUS
		"⛤" => "u26e4", // (alt-09956)	PENTAGRAM = pentalpha, pentangle
		"⛥" => "u26e5", // (alt-09957)	RIGHT-HANDED INTERLACED PENTAGRAM
		"⛦" => "u26e6", // (alt-09958)	LEFT-HANDED INTERLACED PENTAGRAM
		"⛧" => "u26e7", // (alt-09959)	INVERTED PENTAGRAM
		"⛨" => "u26e8", // (alt-09960)	BLACK CROSS ON SHIELD = hospital
		"⛩" => "u26e9", // (alt-09961)	SHINTO SHRINE = torii
		"⛪" => "u26ea", // (alt-09962)	CHURCH
		"⛫" => "u26eb", // (alt-09963)	CASTLE
		"⛬" => "u26ec", // (alt-09964)	HISTORIC SITE
		"⛭" => "u26ed", // (alt-09965)	GEAR WITHOUT HUB = factory
		"⛮" => "u26ee", // (alt-09966)	GEAR WITH HANDLES = power plant, power substation
		"⛯" => "u26ef", // (alt-09967)	MAP SYMBOL FOR LIGHTHOUSE
		"⛰" => "u26f0", // (alt-09968)	MOUNTAIN
		"⛱" => "u26f1", // (alt-09969)	UMBRELLA ON GROUND = bathing beach
		"⛲" => "u26f2", // (alt-09970)	FOUNTAIN = park
		"⛳" => "u26f3", // (alt-09971)	FLAG IN HOLE = golf course
		"⛴" => "u26f4", // (alt-09972)	FERRY = ferry boat terminal
		"⛵" => "u26f5", // (alt-09973)	SAILBOAT = marina or yacht harbour
		"⛶" => "u26f6", // (alt-09974)	SQUARE FOUR CORNERS = intersection
		"⛷" => "u26f7", // (alt-09975)	SKIER = ski resort
		"⛸" => "u26f8", // (alt-09976)	ICE SKATE = ice skating rink
		"⛹" => "u26f9", // (alt-09977)	PERSON WITH BALL = track and field, gymnasium
		"⛺" => "u26fa", // (alt-09978)	TENT = camping site
		"⛻" => "u26fb", // (alt-09979)	JAPANESE BANK SYMBOL
		"⛼" => "u26fc", // (alt-09980)	HEADSTONE GRAVEYARD SYMBOL = graveyard, memorial park, cemetery
		"⛽" => "u26fd", // (alt-09981)	FUEL PUMP = petrol station, gas station
		"⛾" => "u26fe", // (alt-09982)	CUP ON BLACK SQUARE = drive-in restaurant
		"⛿" => "u26ff", // (alt-09983)	WHITE FLAG WITH HORIZONTAL MIDDLE BLACK STRIPE = Japanese self-defence force site
		"✁" => "u2701", // (alt-09985)	UPPER BLADE SCISSORS
		"✂" => "u2702", // (alt-09986)	BLACK SCISSORS
		"✃" => "u2703", // (alt-09987)	LOWER BLADE SCISSORS
		"✄" => "u2704", // (alt-09988)	WHITE SCISSORS
		"✅" => "u2705", // (alt-09989)	WHITE HEAVY CHECK MARK - OK symbol (Accepted mark)
		"✆" => "u2706", // (alt-09990)	TELEPHONE LOCATION SIGN
		"✇" => "u2707", // (alt-09991)	TAPE DRIVE
		"✈" => "u2708", // (alt-09992)	AIRPLANE
		"✉" => "u2709", // (alt-09993)	ENVELOPE
		"✊" => "u270a", // (alt-09994)	RAISED FIST = rock in Rock, Paper, Scissors game
		"✋" => "u270b", // (alt-09995)	RAISED HAND = paper in Rock, Paper, Scissors game
		"✌" => "u270c", // (alt-09996)	VICTORY HAND = scissors in Rock, Paper, Scissors game
		"✍" => "u270d", // (alt-09997)	WRITING HAND
		"✎" => "u270e", // (alt-09998)	LOWER RIGHT PENCIL
		"✏" => "u270f", // (alt-09999)	PENCIL
		"✐" => "u2710", // (alt-010000)	UPPER RIGHT PENCIL
		"✑" => "u2711", // (alt-010001)	WHITE NIB
		"✒" => "u2712", // (alt-010002)	BLACK NIB
		"✓" => "u2713", // (alt-010003)	CHECK MARK
		"✔" => "u2714", // (alt-010004)	HEAVY CHECK MARK
		"✕" => "u2715", // (alt-010005)	MULTIPLICATION X
		"✖" => "u2716", // (alt-010006)	HEAVY MULTIPLICATION X
		"✗" => "u2717", // (alt-010007)	BALLOT X
		"✘" => "u2718", // (alt-010008)	HEAVY BALLOT X
		"✙" => "u2719", // (alt-010009)	OUTLINED GREEK CROSS
		"✚" => "u271a", // (alt-010010)	HEAVY GREEK CROSS
		"✛" => "u271b", // (alt-010011)	OPEN CENTRE CROSS
		"✜" => "u271c", // (alt-010012)	HEAVY OPEN CENTRE CROSS
		"✝" => "u271d", // (alt-010013)	LATIN CROSS
		"✞" => "u271e", // (alt-010014)	SHADOWED WHITE LATIN CROSS
		"✟" => "u271f", // (alt-010015)	OUTLINED LATIN CROSS
		"✠" => "u2720", // (alt-010016)	MALTESE CROSS
		"✡" => "u2721", // (alt-010017)	STAR OF DAVID
		"✢" => "u2722", // (alt-010018)	FOUR TEARDROP-SPOKED ASTERISK
		"✣" => "u2723", // (alt-010019)	FOUR BALLOON-SPOKED ASTERISK
		"✤" => "u2724", // (alt-010020)	HEAVY FOUR BALLOON-SPOKED ASTERISK
		"✥" => "u2725", // (alt-010021)	FOUR CLUB-SPOKED ASTERISK
		"✦" => "u2726", // (alt-010022)	BLACK FOUR POINTED STAR
		"✧" => "u2727", // (alt-010023)	WHITE FOUR POINTED STAR
		"✨" => "u2728", // (alt-010024)	SPARKLES
		"✩" => "u2729", // (alt-010025)	STRESS OUTLINED WHITE STAR
		"✪" => "u272a", // (alt-010026)	CIRCLED WHITE STAR
		"✫" => "u272b", // (alt-010027)	OPEN CENTRE BLACK STAR
		"✬" => "u272c", // (alt-010028)	BLACK CENTRE WHITE STAR
		"✭" => "u272d", // (alt-010029)	OUTLINED BLACK STAR
		"✮" => "u272e", // (alt-010030)	HEAVY OUTLINED BLACK STAR
		"✯" => "u272f", // (alt-010031)	PINWHEEL STAR
		"✰" => "u2730", // (alt-010032)	SHADOWED WHITE STAR
		"✱" => "u2731", // (alt-010033)	HEAVY ASTERISK
		"✲" => "u2732", // (alt-010034)	OPEN CENTRE ASTERISK
		"✳" => "u2733", // (alt-010035)	EIGHT SPOKED ASTERISK
		"✴" => "u2734", // (alt-010036)	EIGHT POINTED BLACK STAR
		"✵" => "u2735", // (alt-010037)	EIGHT POINTED PINWHEEL STAR
		"✶" => "u2736", // (alt-010038)	SIX POINTED BLACK STAR = sextile
		"✷" => "u2737", // (alt-010039)	EIGHT POINTED RECTILINEAR BLACK STAR
		"✸" => "u2738", // (alt-010040)	HEAVY EIGHT POINTED RECTILINEAR BLACK STAR
		"✹" => "u2739", // (alt-010041)	TWELVE POINTED BLACK STAR
		"✺" => "u273a", // (alt-010042)	SIXTEEN POINTED ASTERISK = starburst
		"✻" => "u273b", // (alt-010043)	TEARDROP-SPOKED ASTERISK
		"✼" => "u273c", // (alt-010044)	OPEN CENTRE TEARDROP-SPOKED ASTERISK
		"✽" => "u273d", // (alt-010045)	HEAVY TEARDROP-SPOKED ASTERISK
		"✾" => "u273e", // (alt-010046)	SIX PETALLED BLACK AND WHITE FLORETTE
		"✿" => "u273f", // (alt-010047)	BLACK FLORETTE
		"❀" => "u2740", // (alt-010048)	WHITE FLORETTE
		"❁" => "u2741", // (alt-010049)	EIGHT PETALLED OUTLINED BLACK FLORETTE
		"❂" => "u2742", // (alt-010050)	CIRCLED OPEN CENTRE EIGHT POINTED STAR
		"❃" => "u2743", // (alt-010051)	HEAVY TEARDROP-SPOKED PINWHEEL ASTERISK
		"❄" => "u2744", // (alt-010052)	SNOWFLAKE
		"❅" => "u2745", // (alt-010053)	TIGHT TRIFOLIATE SNOWFLAKE
		"❆" => "u2746", // (alt-010054)	HEAVY CHEVRON SNOWFLAKE
		"❇" => "u2747", // (alt-010055)	SPARKLE
		"❈" => "u2748", // (alt-010056)	HEAVY SPARKLE
		"❉" => "u2749", // (alt-010057)	BALLOON-SPOKED ASTERISK = jack
		"❊" => "u274a", // (alt-010058)	EIGHT TEARDROP-SPOKED PROPELLER ASTERISK
		"❋" => "u274b", // (alt-010059)	HEAVY EIGHT TEARDROP-SPOKED PROPELLER ASTERISK = turbofan
		"❌" => "u274c", // (alt-010060)	CROSS MARK
		"❍" => "u274d", // (alt-010061)	SHADOWED WHITE CIRCLE
		"❎" => "u274e", // (alt-010062)	NEGATIVE SQUARED CROSS MARK
		"❏" => "u274f", // (alt-010063)	LOWER RIGHT DROP-SHADOWED WHITE SQUARE
		"❐" => "u2750", // (alt-010064)	UPPER RIGHT DROP-SHADOWED WHITE SQUARE
		"❑" => "u2751", // (alt-010065)	LOWER RIGHT SHADOWED WHITE SQUARE
		"❒" => "u2752", // (alt-010066)	UPPER RIGHT SHADOWED WHITE SQUARE
		"❓" => "u2753", // (alt-010067)	BLACK QUESTION MARK ORNAMENT
		"❔" => "u2754", // (alt-010068)	WHITE QUESTION MARK ORNAMENT
		"❕" => "u2755", // (alt-010069)	WHITE EXCLAMATION MARK ORNAMENT
		"❖" => "u2756", // (alt-010070)	BLACK DIAMOND MINUS WHITE X
		"❗" => "u2757", // (alt-010071)	HEAVY EXCLAMATION MARK SYMBOL = obstacles on the road, ARIB STD B24
		"❘" => "u2758", // (alt-010072)	LIGHT VERTICAL BAR
		"❙" => "u2759", // (alt-010073)	MEDIUM VERTICAL BAR
		"❚" => "u275a", // (alt-010074)	HEAVY VERTICAL BAR
		"❛" => "u275b", // (alt-010075)	HEAVY SINGLE TURNED COMMA QUOTATION MARK ORNAMENT
		"❜" => "u275c", // (alt-010076)	HEAVY SINGLE COMMA QUOTATION MARK ORNAMENT
		"❝" => "u275d", // (alt-010077)	HEAVY DOUBLE TURNED COMMA QUOTATION MARK ORNAMENT
		"❞" => "u275e", // (alt-010078)	HEAVY DOUBLE COMMA QUOTATION MARK ORNAMENT
		"❟" => "u275f", // (alt-010079)	HEAVY LOW SINGLE COMMA QUOTATION MARK ORNAMENT
		"❠" => "u2760", // (alt-010080)	HEAVY LOW DOUBLE COMMA QUOTATION MARK ORNAMENT
		"❡" => "u2761", // (alt-010081)	CURVED STEM PARAGRAPH SIGN ORNAMENT
		"❢" => "u2762", // (alt-010082)	HEAVY EXCLAMATION MARK ORNAMENT
		"❣" => "u2763", // (alt-010083)	HEAVY HEART EXCLAMATION MARK ORNAMENT
		"❤" => "u2764", // (alt-010084)	HEAVY BLACK HEART
		"❥" => "u2765", // (alt-010085)	ROTATED HEAVY BLACK HEART BULLET
		"❦" => "u2766", // (alt-010086)	FLORAL HEART = Aldus leaf
		"❧" => "u2767", // (alt-010087)	ROTATED FLORAL HEART BULLET = hedera, ivy leaf
		"➔" => "u2794", // (alt-010132)	HEAVY WIDE-HEADED RIGHTWARDS ARROW
		"➘" => "u2798", // (alt-010136)	HEAVY SOUTH EAST ARROW
		"➙" => "u2799", // (alt-010137)	HEAVY RIGHTWARDS ARROW
		"➚" => "u279a", // (alt-010138)	HEAVY NORTH EAST ARROW
		"➛" => "u279b", // (alt-010139)	DRAFTING POINT RIGHTWARDS ARROW
		"➜" => "u279c", // (alt-010140)	HEAVY ROUND-TIPPED RIGHTWARDS ARROW
		"➝" => "u279d", // (alt-010141)	TRIANGLE-HEADED RIGHTWARDS ARROW
		"➞" => "u279e", // (alt-010142)	HEAVY TRIANGLE-HEADED RIGHTWARDS ARROW
		"➟" => "u279f", // (alt-010143)	DASHED TRIANGLE-HEADED RIGHTWARDS ARROW
		"➠" => "u27a0", // (alt-010144)	HEAVY DASHED TRIANGLE-HEADED RIGHTWARDS ARROW
		"➡" => "u27a1", // (alt-010145)	BLACK RIGHTWARDS ARROW
		"➢" => "u27a2", // (alt-010146)	THREE-D TOP-LIGHTED RIGHTWARDS ARROWHEAD
		"➣" => "u27a3", // (alt-010147)	THREE-D BOTTOM-LIGHTED RIGHTWARDS ARROWHEAD
		"➤" => "u27a4", // (alt-010148)	BLACK RIGHTWARDS ARROWHEAD
		"➥" => "u27a5", // (alt-010149)	HEAVY BLACK CURVED DOWNWARDS AND RIGHTWARDS ARROW
		"➦" => "u27a6", // (alt-010150)	HEAVY BLACK CURVED UPWARDS AND RIGHTWARDS ARROW
		"➧" => "u27a7", // (alt-010151)	SQUAT BLACK RIGHTWARDS ARROW
		"➨" => "u27a8", // (alt-010152)	HEAVY CONCAVE-POINTED BLACK RIGHTWARDS ARROW
		"➩" => "u27a9", // (alt-010153)	RIGHT-SHADED WHITE RIGHTWARDS ARROW
		"➪" => "u27aa", // (alt-010154)	LEFT-SHADED WHITE RIGHTWARDS ARROW
		"➫" => "u27ab", // (alt-010155)	BACK-TILTED SHADOWED WHITE RIGHTWARDS ARROW
		"➬" => "u27ac", // (alt-010156)	FRONT-TILTED SHADOWED WHITE RIGHTWARDS ARROW
		"➭" => "u27ad", // (alt-010157)	HEAVY LOWER RIGHT-SHADOWED WHITE RIGHTWARDS ARROW
		"➮" => "u27ae", // (alt-010158)	HEAVY UPPER RIGHT-SHADOWED WHITE RIGHTWARDS ARROW
		"➯" => "u27af", // (alt-010159)	NOTCHED LOWER RIGHT-SHADOWED WHITE RIGHTWARDS ARROW
		"➱" => "u27b1", // (alt-010161)	NOTCHED UPPER RIGHT-SHADOWED WHITE RIGHTWARDS ARROW
		"➲" => "u27b2", // (alt-010162)	CIRCLED HEAVY WHITE RIGHTWARDS ARROW
		"➳" => "u27b3", // (alt-010163)	WHITE-FEATHERED RIGHTWARDS ARROW
		"➴" => "u27b4", // (alt-010164)	BLACK-FEATHERED SOUTH EAST ARROW
		"➵" => "u27b5", // (alt-010165)	BLACK-FEATHERED RIGHTWARDS ARROW
		"➶" => "u27b6", // (alt-010166)	BLACK-FEATHERED NORTH EAST ARROW
		"➷" => "u27b7", // (alt-010167)	HEAVY BLACK-FEATHERED SOUTH EAST ARROW
		"➸" => "u27b8", // (alt-010168)	HEAVY BLACK-FEATHERED RIGHTWARDS ARROW
		"➹" => "u27b9", // (alt-010169)	HEAVY BLACK-FEATHERED NORTH EAST ARROW
		"➺" => "u27ba", // (alt-010170)	TEARDROP-BARBED RIGHTWARDS ARROW
		"➻" => "u27bb", // (alt-010171)	HEAVY TEARDROP-SHANKED RIGHTWARDS ARROW
		"➼" => "u27bc", // (alt-010172)	WEDGE-TAILED RIGHTWARDS ARROW
		"➽" => "u27bd", // (alt-010173)	HEAVY WEDGE-TAILED RIGHTWARDS ARROW
		"➾" => "u27be", // (alt-010174)	OPEN-OUTLINED RIGHTWARDS ARROW
		"⟰" => "u27f0", // (alt-010224)	UPWARDS QUADRUPLE ARROW
		"⟱" => "u27f1", // (alt-010225)	DOWNWARDS QUADRUPLE ARROW
		"⟲" => "u27f2", // (alt-010226)	ANTICLOCKWISE GAPPED CIRCLE ARROW
		"⟳" => "u27f3", // (alt-010227)	CLOCKWISE GAPPED CIRCLE ARROW
		"⟴" => "u27f4", // (alt-010228)	RIGHT ARROW WITH CIRCLED PLUS
		"⟵" => "u27f5", // (alt-010229)	LONG LEFTWARDS ARROW
		"⟶" => "u27f6", // (alt-010230)	LONG RIGHTWARDS ARROW
		"⟷" => "u27f7", // (alt-010231)	LONG LEFT RIGHT ARROW
		"⟸" => "u27f8", // (alt-010232)	LONG LEFTWARDS DOUBLE ARROW
		"⟹" => "u27f9", // (alt-010233)	LONG RIGHTWARDS DOUBLE ARROW
		"⟺" => "u27fa", // (alt-010234)	LONG LEFT RIGHT DOUBLE ARROW
		"⟻" => "u27fb", // (alt-010235)	LONG LEFTWARDS ARROW FROM BAR = maps from
		"⟼" => "u27fc", // (alt-010236)	LONG RIGHTWARDS ARROW FROM BAR = maps to
		"⟽" => "u27fd", // (alt-010237)	LONG LEFTWARDS DOUBLE ARROW FROM BAR
		"⟾" => "u27fe", // (alt-010238)	LONG RIGHTWARDS DOUBLE ARROW FROM BAR
		"⟿" => "u27ff", // (alt-010239)	LONG RIGHTWARDS SQUIGGLE ARROW
		"⤀" => "u2900", // (alt-010496)	RIGHTWARDS TWO-HEADED ARROW WITH VERTICAL STROKE = z notation partial surjection
		"⤁" => "u2901", // (alt-010497)	RIGHTWARDS TWO-HEADED ARROW WITH DOUBLE VERTICAL STROKE = z notation finite surjection
		"⤂" => "u2902", // (alt-010498)	LEFTWARDS DOUBLE ARROW WITH VERTICAL STROKE
		"⤃" => "u2903", // (alt-010499)	RIGHTWARDS DOUBLE ARROW WITH VERTICAL STROKE
		"⤄" => "u2904", // (alt-010500)	LEFT RIGHT DOUBLE ARROW WITH VERTICAL STROKE
		"⤅" => "u2905", // (alt-010501)	RIGHTWARDS TWO-HEADED ARROW FROM BAR = maps to
		"⤆" => "u2906", // (alt-010502)	LEFTWARDS DOUBLE ARROW FROM BAR = maps from
		"⤇" => "u2907", // (alt-010503)	RIGHTWARDS DOUBLE ARROW FROM BAR = maps to
		"⤈" => "u2908", // (alt-010504)	DOWNWARDS ARROW WITH HORIZONTAL STROKE
		"⤉" => "u2909", // (alt-010505)	UPWARDS ARROW WITH HORIZONTAL STROKE
		"⤊" => "u290a", // (alt-010506)	UPWARDS TRIPLE ARROW
		"⤋" => "u290b", // (alt-010507)	DOWNWARDS TRIPLE ARROW
		"⤌" => "u290c", // (alt-010508)	LEFTWARDS DOUBLE DASH ARROW
		"⤍" => "u290d", // (alt-010509)	RIGHTWARDS DOUBLE DASH ARROW
		"⤎" => "u290e", // (alt-010510)	LEFTWARDS TRIPLE DASH ARROW
		"⤏" => "u290f", // (alt-010511)	RIGHTWARDS TRIPLE DASH ARROW
		"⤐" => "u2910", // (alt-010512)	RIGHTWARDS TWO-HEADED TRIPLE DASH ARROW
		"⤑" => "u2911", // (alt-010513)	RIGHTWARDS ARROW WITH DOTTED STEM
		"⤒" => "u2912", // (alt-010514)	UPWARDS ARROW TO BAR
		"⤓" => "u2913", // (alt-010515)	DOWNWARDS ARROW TO BAR
		"⤔" => "u2914", // (alt-010516)	RIGHTWARDS ARROW WITH TAIL WITH VERTICAL STROKE = z notation partial injection
		"⤕" => "u2915", // (alt-010517)	RIGHTWARDS ARROW WITH TAIL WITH DOUBLE VERTICAL STROKE = z notation finite injection
		"⤖" => "u2916", // (alt-010518)	RIGHTWARDS TWO-HEADED ARROW WITH TAIL = bijective mapping = z notation bijection
		"⤗" => "u2917", // (alt-010519)	RIGHTWARDS TWO-HEADED ARROW WITH TAIL WITH VERTICAL STROKE = z notation surjective injection
		"⤘" => "u2918", // (alt-010520)	RIGHTWARDS TWO-HEADED ARROW WITH TAIL WITH DOUBLE VERTICAL STROKE = z notation finite surjective injection
		"⤙" => "u2919", // (alt-010521)	LEFTWARDS ARROW-TAIL
		"⤚" => "u291a", // (alt-010522)	RIGHTWARDS ARROW-TAIL
		"⤛" => "u291b", // (alt-010523)	LEFTWARDS DOUBLE ARROW-TAIL
		"⤜" => "u291c", // (alt-010524)	RIGHTWARDS DOUBLE ARROW-TAIL
		"⤝" => "u291d", // (alt-010525)	LEFTWARDS ARROW TO BLACK DIAMOND
		"⤞" => "u291e", // (alt-010526)	RIGHTWARDS ARROW TO BLACK DIAMOND
		"⤟" => "u291f", // (alt-010527)	LEFTWARDS ARROW FROM BAR TO BLACK DIAMOND
		"⤠" => "u2920", // (alt-010528)	RIGHTWARDS ARROW FROM BAR TO BLACK DIAMOND
		"⤡" => "u2921", // (alt-010529)	NORTH WEST AND SOUTH EAST ARROW
		"⤢" => "u2922", // (alt-010530)	NORTH EAST AND SOUTH WEST ARROW
		"⤣" => "u2923", // (alt-010531)	NORTH WEST ARROW WITH HOOK
		"⤤" => "u2924", // (alt-010532)	NORTH EAST ARROW WITH HOOK
		"⤥" => "u2925", // (alt-010533)	SOUTH EAST ARROW WITH HOOK
		"⤦" => "u2926", // (alt-010534)	SOUTH WEST ARROW WITH HOOK
		"⤧" => "u2927", // (alt-010535)	NORTH WEST ARROW AND NORTH EAST ARROW
		"⤨" => "u2928", // (alt-010536)	NORTH EAST ARROW AND SOUTH EAST ARROW
		"⤩" => "u2929", // (alt-010537)	SOUTH EAST ARROW AND SOUTH WEST ARROW
		"⤪" => "u292a", // (alt-010538)	SOUTH WEST ARROW AND NORTH WEST ARROW
		"⤫" => "u292b", // (alt-010539)	RISING DIAGONAL CROSSING FALLING DIAGONAL
		"⤬" => "u292c", // (alt-010540)	FALLING DIAGONAL CROSSING RISING DIAGONAL
		"⤭" => "u292d", // (alt-010541)	SOUTH EAST ARROW CROSSING NORTH EAST ARROW
		"⤮" => "u292e", // (alt-010542)	NORTH EAST ARROW CROSSING SOUTH EAST ARROW
		"⤯" => "u292f", // (alt-010543)	FALLING DIAGONAL CROSSING NORTH EAST ARROW
		"⤰" => "u2930", // (alt-010544)	RISING DIAGONAL CROSSING SOUTH EAST ARROW
		"⤱" => "u2931", // (alt-010545)	NORTH EAST ARROW CROSSING NORTH WEST ARROW
		"⤲" => "u2932", // (alt-010546)	NORTH WEST ARROW CROSSING NORTH EAST ARROW
		"⤳" => "u2933", // (alt-010547)	WAVE ARROW POINTING DIRECTLY RIGHT
		"⤴" => "u2934", // (alt-010548)	ARROW POINTING RIGHTWARDS THEN CURVING UPWARDS
		"⤵" => "u2935", // (alt-010549)	ARROW POINTING RIGHTWARDS THEN CURVING DOWNWARDS
		"⤶" => "u2936", // (alt-010550)	ARROW POINTING DOWNWARDS THEN CURVING LEFTWARDS
		"⤷" => "u2937", // (alt-010551)	ARROW POINTING DOWNWARDS THEN CURVING RIGHTWARDS
		"⤸" => "u2938", // (alt-010552)	RIGHT-SIDE ARC CLOCKWISE ARROW
		"⤹" => "u2939", // (alt-010553)	LEFT-SIDE ARC ANTICLOCKWISE ARROW
		"⤺" => "u293a", // (alt-010554)	TOP ARC ANTICLOCKWISE ARROW
		"⤻" => "u293b", // (alt-010555)	BOTTOM ARC ANTICLOCKWISE ARROW
		"⤼" => "u293c", // (alt-010556)	TOP ARC CLOCKWISE ARROW WITH MINUS
		"⤽" => "u293d", // (alt-010557)	TOP ARC ANTICLOCKWISE ARROW WITH PLUS
		"⤾" => "u293e", // (alt-010558)	LOWER RIGHT SEMICIRCULAR CLOCKWISE ARROW
		"⤿" => "u293f", // (alt-010559)	LOWER LEFT SEMICIRCULAR ANTICLOCKWISE ARROW
		"⥀" => "u2940", // (alt-010560)	ANTICLOCKWISE CLOSED CIRCLE ARROW
		"⥁" => "u2941", // (alt-010561)	CLOCKWISE CLOSED CIRCLE ARROW
		"⥂" => "u2942", // (alt-010562)	RIGHTWARDS ARROW ABOVE SHORT LEFTWARDS ARROW
		"⥃" => "u2943", // (alt-010563)	LEFTWARDS ARROW ABOVE SHORT RIGHTWARDS ARROW
		"⥄" => "u2944", // (alt-010564)	SHORT RIGHTWARDS ARROW ABOVE LEFTWARDS ARROW
		"⥅" => "u2945", // (alt-010565)	RIGHTWARDS ARROW WITH PLUS BELOW
		"⥆" => "u2946", // (alt-010566)	LEFTWARDS ARROW WITH PLUS BELOW
		"⥇" => "u2947", // (alt-010567)	RIGHTWARDS ARROW THROUGH X
		"⥈" => "u2948", // (alt-010568)	LEFT RIGHT ARROW THROUGH SMALL CIRCLE
		"⥉" => "u2949", // (alt-010569)	UPWARDS TWO-HEADED ARROW FROM SMALL CIRCLE
		"⥊" => "u294a", // (alt-010570)	LEFT BARB UP RIGHT BARB DOWN HARPOON
		"⥋" => "u294b", // (alt-010571)	LEFT BARB DOWN RIGHT BARB UP HARPOON
		"⥌" => "u294c", // (alt-010572)	UP BARB RIGHT DOWN BARB LEFT HARPOON
		"⥍" => "u294d", // (alt-010573)	UP BARB LEFT DOWN BARB RIGHT HARPOON
		"⥎" => "u294e", // (alt-010574)	LEFT BARB UP RIGHT BARB UP HARPOON
		"⥏" => "u294f", // (alt-010575)	UP BARB RIGHT DOWN BARB RIGHT HARPOON
		"⥐" => "u2950", // (alt-010576)	LEFT BARB DOWN RIGHT BARB DOWN HARPOON
		"⥑" => "u2951", // (alt-010577)	UP BARB LEFT DOWN BARB LEFT HARPOON
		"⬀" => "u2b00", // (alt-011008)	NORTH EAST WHITE ARROW
		"⬁" => "u2b01", // (alt-011009)	NORTH WEST WHITE ARROW
		"⬂" => "u2b02", // (alt-011010)	SOUTH EAST WHITE ARROW
		"⬃" => "u2b03", // (alt-011011)	SOUTH WEST WHITE ARROW
		"⬄" => "u2b04", // (alt-011012)	LEFT RIGHT WHITE ARROW
		"⬅" => "u2b05", // (alt-011013)	LEFTWARDS BLACK ARROW
		"⬆" => "u2b06", // (alt-011014)	UPWARDS BLACK ARROW
		"⬇" => "u2b07", // (alt-011015)	DOWNWARDS BLACK ARROW
		"⬈" => "u2b08", // (alt-011016)	NORTH EAST BLACK ARROW
		"⬉" => "u2b09", // (alt-011017)	NORTH WEST BLACK ARROW
		"⬊" => "u2b0a", // (alt-011018)	SOUTH EAST BLACK ARROW
		"⬋" => "u2b0b", // (alt-011019)	SOUTH WEST BLACK ARROW
		"⬌" => "u2b0c", // (alt-011020)	LEFT RIGHT BLACK ARROW
		"⬍" => "u2b0d", // (alt-011021)	UP DOWN BLACK ARROW
		"⬎" => "u2b0e", // (alt-011022)	RIGHTWARDS ARROW WITH TIP DOWNWARDS
		"⬏" => "u2b0f", // (alt-011023)	RIGHTWARDS ARROW WITH TIP UPWARDS
		"⬐" => "u2b10", // (alt-011024)	LEFTWARDS ARROW WITH TIP DOWNWARDS
		"⬑" => "u2b11", // (alt-011025)	LEFTWARDS ARROW WITH TIP UPWARDS
		"⬒" => "u2b12", // (alt-011026)	SQUARE WITH TOP HALF BLACK
		"⬓" => "u2b13", // (alt-011027)	SQUARE WITH BOTTOM HALF BLACK
		"⬔" => "u2b14", // (alt-011028)	SQUARE WITH UPPER RIGHT DIAGONAL HALF BLACK
		"⬕" => "u2b15", // (alt-011029)	SQUARE WITH LOWER LEFT DIAGONAL HALF BLACK
		"⬖" => "u2b16", // (alt-011030)	DIAMOND WITH LEFT HALF BLACK
		"⬗" => "u2b17", // (alt-011031)	DIAMOND WITH RIGHT HALF BLACK
		"⬘" => "u2b18", // (alt-011032)	DIAMOND WITH TOP HALF BLACK
		"⬙" => "u2b19", // (alt-011033)	DIAMOND WITH BOTTOM HALF BLACK
		"⬚" => "u2b1a", // (alt-011034)	DOTTED SQUARE
		"Ⱡ" => "u2c60", // (alt-011360)	LATIN CAPITAL LETTER L WITH DOUBLE BAR
		"ⱡ" => "u2c61", // (alt-011361)	LATIN SMALL LETTER L WITH DOUBLE BAR
		"Ᵽ" => "u2c63", // (alt-011363)	LATIN CAPITAL LETTER P WITH STROKE
		"ⱥ" => "u2c65", // (alt-011365)	LATIN SMALL LETTER A WITH STROKE
		"ⱦ" => "u2c66", // (alt-011366)	LATIN SMALL LETTER T WITH DIAGONAL STROKE
		"Ɑ" => "u2c6d", // (alt-011373)	LATIN CAPITAL LETTER ALPHA
		"Ɐ" => "u2c6f", // (alt-011375)	LATIN CAPITAL LETTER TURNED A
		"Ɒ" => "u2c70", // (alt-011376)	LATIN CAPITAL LETTER TURNED ALPHA
		"⸢" => "u2e22", // (alt-011810)	TOP LEFT HALF BRACKET
		"⸣" => "u2e23", // (alt-011811)	TOP RIGHT HALF BRACKET
		"⸤" => "u2e24", // (alt-011812)	BOTTOM LEFT HALF BRACKET
		"⸥" => "u2e25", // (alt-011813)	BOTTOM RIGHT HALF BRACKET
		"⸮" => "u2e2e", // (alt-011822)	REVERSED QUESTION MARK = punctus percontativus
		"〃" => "u3003", // (alt-012291)	DITTO MARK
		"〄" => "u3004", // (alt-012292)	JAPANESE INDUSTRIAL STANDARD SYMBOL
		"ﬀ" => "ufb00", // (alt-064256)	LATIN SMALL LIGATURE FF
		"ﬁ" => "ufb01", // (alt-064257)	LATIN SMALL LIGATURE FI
		"ﬂ" => "ufb02", // (alt-064258)	LATIN SMALL LIGATURE FL
		"ﬃ" => "ufb03", // (alt-064259)	LATIN SMALL LIGATURE FFI
		"ﬄ" => "ufb04", // (alt-064260)	LATIN SMALL LIGATURE FFL
		"ﬅ" => "ufb05", // (alt-064261)	LATIN SMALL LIGATURE LONG S T
		"ﬆ" => "ufb06", // (alt-064262)	LATIN SMALL LIGATURE ST
		"﴾" => "ufd3e", // (alt-064830)	ORNATE LEFT PARENTHESIS
		"﴿" => "ufd3f", // (alt-064831)	ORNATE RIGHT PARENTHESIS
		"﷼" => "ufdfc", // (alt-065020)	RIAL SIGN
		"︐" => "ufe10", // (alt-065040)	PRESENTATION FORM FOR VERTICAL COMMA
		"︑" => "ufe11", // (alt-065041)	PRESENTATION FORM FOR VERTICAL IDEOGRAPHIC COMMA
		"︒" => "ufe12", // (alt-065042)	PRESENTATION FORM FOR VERTICAL IDEOGRAPHIC FULL STOP
		"︓" => "ufe13", // (alt-065043)	PRESENTATION FORM FOR VERTICAL COLON
		"︔" => "ufe14", // (alt-065044)	PRESENTATION FORM FOR VERTICAL SEMICOLON
		"︕" => "ufe15", // (alt-065045)	PRESENTATION FORM FOR VERTICAL EXCLAMATION MARK
		"︖" => "ufe16", // (alt-065046)	PRESENTATION FORM FOR VERTICAL QUESTION MARK
		"︗" => "ufe17", // (alt-065047)	PRESENTATION FORM FOR VERTICAL LEFT WHITE LENTICULAR BRACKET
		"︘" => "ufe18", // (alt-065048)	PRESENTATION FORM FOR VERTICAL RIGHT WHITE LENTICULAR BRAKCET
		"︙" => "ufe19", // (alt-065049)	PRESENTATION FORM FOR VERTICAL HORIZONTAL ELLIPSIS
		"︰" => "ufe30", // (alt-065072)	PRESENTATION FORM FOR VERTICAL TWO DOT LEADER
		"︱" => "ufe31", // (alt-065073)	PRESENTATION FORM FOR VERTICAL EM DASH
		"︲" => "ufe32", // (alt-065074)	PRESENTATION FORM FOR VERTICAL EN DASH
		"︳" => "ufe33", // (alt-065075)	PRESENTATION FORM FOR VERTICAL LOW LINE
		"︴" => "ufe34", // (alt-065076)	PRESENTATION FORM FOR VERTICAL WAVY LOW LINE
		"︵" => "ufe35", // (alt-065077)	PRESENTATION FORM FOR VERTICAL LEFT PARENTHESIS
		"︶" => "ufe36", // (alt-065078)	PRESENTATION FORM FOR VERTICAL RIGHT PARENTHESIS
		"︷" => "ufe37", // (alt-065079)	PRESENTATION FORM FOR VERTICAL LEFT CURLY BRACKET
		"︸" => "ufe38", // (alt-065080)	PRESENTATION FORM FOR VERTICAL RIGHT CURLY BRACKET
		"︹" => "ufe39", // (alt-065081)	PRESENTATION FORM FOR VERTICAL LEFT TORTOISE SHELL BRACKET
		"︺" => "ufe3a", // (alt-065082)	PRESENTATION FORM FOR VERTICAL RIGHT TORTOISE SHELL BRACKET
		"︻" => "ufe3b", // (alt-065083)	PRESENTATION FORM FOR VERTICAL LEFT BLACK LENTICULAR BRACKET
		"︼" => "ufe3c", // (alt-065084)	PRESENTATION FORM FOR VERTICAL RIGHT BLACK LENTICULAR BRACKET
		"︽" => "ufe3d", // (alt-065085)	PRESENTATION FORM FOR VERTICAL LEFT DOUBLE ANGLE BRACKET
		"︾" => "ufe3e", // (alt-065086)	PRESENTATION FORM FOR VERTICAL RIGHT DOUBLE ANGLE BRACKET
		"︿" => "ufe3f", // (alt-065087)	PRESENTATION FORM FOR VERTICAL LEFT ANGLE BRACKET
		"﹀" => "ufe40", // (alt-065088)	PRESENTATION FORM FOR VERTICAL RIGHT ANGLE BRACKET
		"﹁" => "ufe41", // (alt-065089)	PRESENTATION FORM FOR VERTICAL LEFT CORNER BRACKET
		"﹂" => "ufe42", // (alt-065090)	PRESENTATION FORM FOR VERTICAL RIGHT CORNER BRACKET
		"﹃" => "ufe43", // (alt-065091)	PRESENTATION FORM FOR VERTICAL LEFT WHITE CORNER BRACKET
		"﹄" => "ufe44", // (alt-065092)	PRESENTATION FORM FOR VERTICAL RIGHT WHITE CORNER BRACKET
		"﹅" => "ufe45", // (alt-065093)	SESAME DOT
		"﹆" => "ufe46", // (alt-065094)	WHITE SESAME DOT
		"﹉" => "ufe49", // (alt-065097)	DASHED OVERLINE
		"﹊" => "ufe4a", // (alt-065098)	CENTRELINE OVERLINE
		"﹋" => "ufe4b", // (alt-065099)	WAVY OVERLINE
		"﹌" => "ufe4c", // (alt-065100)	DOUBLE WAVY OVERLINE
		"﹍" => "ufe4d", // (alt-065101)	DASHED LOW LINE
		"﹎" => "ufe4e", // (alt-065102)	CENTRELINE LOW LINE
		"﹏" => "ufe4f", // (alt-065103)	WAVY LOW LINE
		"﹐" => "ufe50", // (alt-065104)	SMALL COMMA
		"﹑" => "ufe51", // (alt-065105)	SMALL IDEOGRAPHIC COMMA
		"﹒" => "ufe52", // (alt-065106)	SMALL FULL STOP
		"﹔" => "ufe54", // (alt-065108)	SMALL SEMICOLON
		"﹕" => "ufe55", // (alt-065109)	SMALL COLON
		"﹖" => "ufe56", // (alt-065110)	SMALL QUESTION MARK
		"﹗" => "ufe57", // (alt-065111)	SMALL EXCLAMATION MARK
		"﹘" => "ufe58", // (alt-065112)	SMALL EM DASH
		"﹙" => "ufe59", // (alt-065113)	SMALL LEFT PARENTHESIS
		"﹚" => "ufe5a", // (alt-065114)	SMALL RIGHT PARENTHESIS
		"﹛" => "ufe5b", // (alt-065115)	SMALL LEFT CURLY BRACKET
		"﹜" => "ufe5c", // (alt-065116)	SMALL RIGHT CURLY BRACKET
		"﹝" => "ufe5d", // (alt-065117)	SMALL LEFT TORTOISE SHELL BRACKET
		"﹞" => "ufe5e", // (alt-065118)	SMALL RIGHT TORTOISE SHELL BRACKET
		"﹟" => "ufe5f", // (alt-065119)	SMALL NUMBER SIGN
		"﹠" => "ufe60", // (alt-065120)	SMALL AMPERSAND
		"﹡" => "ufe61", // (alt-065121)	SMALL ASTERISK
		"﹢" => "ufe62", // (alt-065122)	SMALL PLUS SIGN
		"﹣" => "ufe63", // (alt-065123)	SMALL HYPHEN-MINUS
		"﹤" => "ufe64", // (alt-065124)	SMALL LESS-THAN SIGN
		"﹥" => "ufe65", // (alt-065125)	SMALL GREATER-THAN SIGN
		"﹦" => "ufe66", // (alt-065126)	SMALL EQUALS SIGN
		"﹨" => "ufe68", // (alt-065128)	SMALL REVERSE SOLIDUS
		"﹩" => "ufe69", // (alt-065129)	SMALL DOLLAR SIGN
		"﹪" => "ufe6a", // (alt-065130)	SMALL PERCENT SIGN
		"﹫" => "ufe6b", // (alt-065131)	SMALL COMMERCIAL AT
		"﻿" => "ufeff", // (alt-065279)	ZERO WIDTH NO-BREAK SPACE = BYTE ORDER MARK (BOM), ZWNBSP
		"！" => "uff01", // (alt-065281)	FULLWIDTH EXCLAMATION MARK
		"＂" => "uff02", // (alt-065282)	FULLWIDTH QUOTATION MARK
		"＃" => "uff03", // (alt-065283)	FULLWIDTH NUMBER SIGN
		"＄" => "uff04", // (alt-065284)	FULLWIDTH DOLLAR SIGN
		"％" => "uff05", // (alt-065285)	FULLWIDTH PERCENT SIGN
		"＆" => "uff06", // (alt-065286)	FULLWIDTH AMPERSAND
		"＇" => "uff07", // (alt-065287)	FULLWIDTH APOSTROPHE
		"（" => "uff08", // (alt-065288)	FULLWIDTH LEFT PARENTHESIS
		"）" => "uff09", // (alt-065289)	FULLWIDTH RIGHT PARENTHESIS
		"＊" => "uff0a", // (alt-065290)	FULLWIDTH ASTERISK
		"＋" => "uff0b", // (alt-065291)	FULLWIDTH PLUS SIGN
		"，" => "uff0c", // (alt-065292)	FULLWIDTH COMMA
		"－" => "uff0d", // (alt-065293)	FULLWIDTH HYPHEN-MINUS
		"．" => "uff0e", // (alt-065294)	FULLWIDTH FULL STOP
		"／" => "uff0f", // (alt-065295)	FULLWIDTH SOLIDUS
		"０" => "uff10", // (alt-065296)	FULLWIDTH DIGIT ZERO
		"１" => "uff11", // (alt-065297)	FULLWIDTH DIGIT ONE
		"２" => "uff12", // (alt-065298)	FULLWIDTH DIGIT TWO
		"３" => "uff13", // (alt-065299)	FULLWIDTH DIGIT THREE
		"４" => "uff14", // (alt-065300)	FULLWIDTH DIGIT FOUR
		"５" => "uff15", // (alt-065301)	FULLWIDTH DIGIT FIVE
		"６" => "uff16", // (alt-065302)	FULLWIDTH DIGIT SIX
		"７" => "uff17", // (alt-065303)	FULLWIDTH DIGIT SEVEN
		"８" => "uff18", // (alt-065304)	FULLWIDTH DIGIT EIGHT
		"９" => "uff19", // (alt-065305)	FULLWIDTH DIGIT NINE
		"：" => "uff1a", // (alt-065306)	FULLWIDTH COLON
		"；" => "uff1b", // (alt-065307)	FULLWIDTH SEMICOLON
		"＜" => "uff1c", // (alt-065308)	FULLWIDTH LESS-THAN SIGN
		"＝" => "uff1d", // (alt-065309)	FULLWIDTH EQUALS SIGN
		"＞" => "uff1e", // (alt-065310)	FULLWIDTH GREATER-THAN SIGN
		"？" => "uff1f", // (alt-065311)	FULLWIDTH QUESTION MARK
		"＠" => "uff20", // (alt-065312)	FULLWIDTH COMMERCIAL AT
		"Ａ" => "uff21", // (alt-065313)	FULLWIDTH LATIN CAPITAL LETTER A
		"Ｂ" => "uff22", // (alt-065314)	FULLWIDTH LATIN CAPITAL LETTER B
		"Ｃ" => "uff23", // (alt-065315)	FULLWIDTH LATIN CAPITAL LETTER C
		"Ｄ" => "uff24", // (alt-065316)	FULLWIDTH LATIN CAPITAL LETTER D
		"Ｅ" => "uff25", // (alt-065317)	FULLWIDTH LATIN CAPITAL LETTER E
		"Ｆ" => "uff26", // (alt-065318)	FULLWIDTH LATIN CAPITAL LETTER F
		"Ｇ" => "uff27", // (alt-065319)	FULLWIDTH LATIN CAPITAL LETTER G
		"Ｈ" => "uff28", // (alt-065320)	FULLWIDTH LATIN CAPITAL LETTER H
		"Ｉ" => "uff29", // (alt-065321)	FULLWIDTH LATIN CAPITAL LETTER I
		"Ｊ" => "uff2a", // (alt-065322)	FULLWIDTH LATIN CAPITAL LETTER J
		"Ｋ" => "uff2b", // (alt-065323)	FULLWIDTH LATIN CAPITAL LETTER K
		"Ｌ" => "uff2c", // (alt-065324)	FULLWIDTH LATIN CAPITAL LETTER L
		"Ｍ" => "uff2d", // (alt-065325)	FULLWIDTH LATIN CAPITAL LETTER M
		"Ｎ" => "uff2e", // (alt-065326)	FULLWIDTH LATIN CAPITAL LETTER N
		"Ｏ" => "uff2f", // (alt-065327)	FULLWIDTH LATIN CAPITAL LETTER O
		"Ｐ" => "uff30", // (alt-065328)	FULLWIDTH LATIN CAPITAL LETTER P
		"Ｑ" => "uff31", // (alt-065329)	FULLWIDTH LATIN CAPITAL LETTER Q
		"Ｒ" => "uff32", // (alt-065330)	FULLWIDTH LATIN CAPITAL LETTER R
		"Ｓ" => "uff33", // (alt-065331)	FULLWIDTH LATIN CAPITAL LETTER S
		"Ｔ" => "uff34", // (alt-065332)	FULLWIDTH LATIN CAPITAL LETTER T
		"Ｕ" => "uff35", // (alt-065333)	FULLWIDTH LATIN CAPITAL LETTER U
		"Ｖ" => "uff36", // (alt-065334)	FULLWIDTH LATIN CAPITAL LETTER V
		"Ｗ" => "uff37", // (alt-065335)	FULLWIDTH LATIN CAPITAL LETTER W
		"Ｘ" => "uff38", // (alt-065336)	FULLWIDTH LATIN CAPITAL LETTER X
		"Ｙ" => "uff39", // (alt-065337)	FULLWIDTH LATIN CAPITAL LETTER Y
		"Ｚ" => "uff3a", // (alt-065338)	FULLWIDTH LATIN CAPITAL LETTER Z
		"［" => "uff3b", // (alt-065339)	FULLWIDTH LEFT SQUARE BRACKET
		"＼" => "uff3c", // (alt-065340)	FULLWIDTH REVERSE SOLIDUS
		"］" => "uff3d", // (alt-065341)	FULLWIDTH RIGHT SQUARE BRACKET
		"＾" => "uff3e", // (alt-065342)	FULLWIDTH CIRCUMFLEX ACCENT
		"＿" => "uff3f", // (alt-065343)	FULLWIDTH LOW LINE
		"｀" => "uff40", // (alt-065344)	FULLWIDTH GRAVE ACCENT
		"ａ" => "uff41", // (alt-065345)	FULLWIDTH LATIN SMALL LETTER A
		"ｂ" => "uff42", // (alt-065346)	FULLWIDTH LATIN SMALL LETTER B
		"ｃ" => "uff43", // (alt-065347)	FULLWIDTH LATIN SMALL LETTER C
		"ｄ" => "uff44", // (alt-065348)	FULLWIDTH LATIN SMALL LETTER D
		"ｅ" => "uff45", // (alt-065349)	FULLWIDTH LATIN SMALL LETTER E
		"ｆ" => "uff46", // (alt-065350)	FULLWIDTH LATIN SMALL LETTER F
		"ｇ" => "uff47", // (alt-065351)	FULLWIDTH LATIN SMALL LETTER G
		"ｈ" => "uff48", // (alt-065352)	FULLWIDTH LATIN SMALL LETTER H
		"ｉ" => "uff49", // (alt-065353)	FULLWIDTH LATIN SMALL LETTER I
		"ｊ" => "uff4a", // (alt-065354)	FULLWIDTH LATIN SMALL LETTER J
		"ｋ" => "uff4b", // (alt-065355)	FULLWIDTH LATIN SMALL LETTER K
		"ｌ" => "uff4c", // (alt-065356)	FULLWIDTH LATIN SMALL LETTER L
		"ｍ" => "uff4d", // (alt-065357)	FULLWIDTH LATIN SMALL LETTER M
		"ｎ" => "uff4e", // (alt-065358)	FULLWIDTH LATIN SMALL LETTER N
		"ｏ" => "uff4f", // (alt-065359)	FULLWIDTH LATIN SMALL LETTER O
		"ｐ" => "uff50", // (alt-065360)	FULLWIDTH LATIN SMALL LETTER P
		"ｑ" => "uff51", // (alt-065361)	FULLWIDTH LATIN SMALL LETTER Q
		"ｒ" => "uff52", // (alt-065362)	FULLWIDTH LATIN SMALL LETTER R
		"ｓ" => "uff53", // (alt-065363)	FULLWIDTH LATIN SMALL LETTER S
		"ｔ" => "uff54", // (alt-065364)	FULLWIDTH LATIN SMALL LETTER T
		"ｕ" => "uff55", // (alt-065365)	FULLWIDTH LATIN SMALL LETTER U
		"ｖ" => "uff56", // (alt-065366)	FULLWIDTH LATIN SMALL LETTER V
		"ｗ" => "uff57", // (alt-065367)	FULLWIDTH LATIN SMALL LETTER W
		"ｘ" => "uff58", // (alt-065368)	FULLWIDTH LATIN SMALL LETTER X
		"ｙ" => "uff59", // (alt-065369)	FULLWIDTH LATIN SMALL LETTER Y
		"ｚ" => "uff5a", // (alt-065370)	FULLWIDTH LATIN SMALL LETTER Z
		"｛" => "uff5b", // (alt-065371)	FULLWIDTH LEFT CURLY BRACKET
		"｜" => "uff5c", // (alt-065372)	FULLWIDTH VERTICAL LINE
		"｝" => "uff5d", // (alt-065373)	FULLWIDTH RIGHT CURLY BRACKET
		"～" => "uff5e", // (alt-065374)	FULLWIDTH TILDE
		"｟" => "uff5f", // (alt-065375)	FULLWIDTH LEFT WHITE PARENTHESIS
		"｠" => "uff60", // (alt-065376)	FULLWIDTH RIGHT WHITE PARENTHESIS
		"￠" => "uffe0", // (alt-065504)	FULLWIDTH CENT SIGN
		"￡" => "uffe1", // (alt-065505)	FULLWIDTH POUND SIGN
		"￢" => "uffe2", // (alt-065506)	FULLWIDTH NOT SIGN
		"￣" => "uffe3", // (alt-065507)	FULLWIDTH MACRON
		"￤" => "uffe4", // (alt-065508)	FULLWIDTH BROKEN BAR
		"￥" => "uffe5", // (alt-065509)	FULLWIDTH YEN SIGN
		"￦" => "uffe6", // (alt-065510)	FULLWIDTH WON SIGN
	];
	
	foreach ($charset as $rChar => $unicode) {
		$str = str_replace($unicode, "\\" . $unicode, (string)$str);
	}
	
	return $str;
}
