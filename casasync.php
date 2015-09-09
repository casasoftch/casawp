<?php
namespace CasaSync;

/*
 *	Plugin Name: 	CasaSync
 *  Plugin URI: 	http://immobilien-plugin.ch
 *	Description:    Das WP Immobilien-Plugin für Ihre Website importiert Immobilien aus Ihrer Makler-Software!
 *	Author:         Casasoft AG
 *	Author URI:     http://casasoft.ch
 *	Version: 		3.0.9
 *	Text Domain: 	casasync
 *	Domain Path: 	languages/
 *	License: 		GPL2
 */


$foldername = 'casasync';

define('CASASYNC_PLUGIN_URL', home_url() . '/wp-content/plugins/' . $foldername . '/');
define('CASASYNC_PLUGIN_URI', home_url() . '/wp-content/plugins/' . $foldername . '/');
define('CASASYNC_PLUGIN_DIR', plugin_dir_path(__FILE__) . '');

include(CASASYNC_PLUGIN_DIR . 'classes/Conversion.php');
include(CASASYNC_PLUGIN_DIR . 'classes/Templateable.php');
include(CASASYNC_PLUGIN_DIR . 'classes/Casasync.php');
if (!is_admin()) {
	include(CASASYNC_PLUGIN_DIR . 'classes/Single.php');
	include(CASASYNC_PLUGIN_DIR . 'classes/Archive.php');
}
include(CASASYNC_PLUGIN_DIR . 'classes/Import.php');
include(CASASYNC_PLUGIN_DIR . 'classes/ImportLegacy.php');

$casaSync = new CasaSync();
if (is_admin()) {
	include(CASASYNC_PLUGIN_DIR . 'classes/Admin.php');
	$casaSyncAdmin = new Admin();

	if (isset($casaSyncAdmin)) {
		register_activation_hook(__FILE__, array($casaSyncAdmin,'casasync_install'));
		register_deactivation_hook(__FILE__, array($casaSyncAdmin, 'casasync_remove'));
	}
}
if (get_option('casasync_live_import') || isset($_GET['do_import']) ) {
	if (get_option('casasync_legacy')) {
		$import = new ImportLegacy(true, false);	
	} else {
		$import = new Import(true, false);
		
	}
	$transcript = $import->getLastTranscript();
}
if (isset($_GET['gatewayupdate'])) {
	$import = new Import(false, true);
	$transcript = $import->getLastTranscript();
}