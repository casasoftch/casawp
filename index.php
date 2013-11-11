<?php
namespace CasaSync;
/**
 * @package CasaSync
 */
/*
Plugin Name: CasaSync
Plugin URI: http://immobilien-plugin.ch
Description: Das WP Immobilien-Plugin für Ihre Website importiert Immobilien aus Ihrer Makler-Software!
Version: 3.0.0
Author: Casasoft AG
Author URI: http://casasoft.ch
License: GPL2
*/

$foldername = 'casasync';

define('CASASYNC_PLUGIN_URL', home_url() . '/wp-content/plugins/' . $foldername . '/');
define('CASASYNC_PLUGIN_URI', home_url() . '/wp-content/plugins/' . $foldername . '/');
define('CASASYNC_PLUGIN_DIR', plugin_dir_path(__FILE__) . '');

include(CASASYNC_PLUGIN_DIR . 'classes/Conversion.php');
include(CASASYNC_PLUGIN_DIR . 'classes/Templateable.php');
include(CASASYNC_PLUGIN_DIR . 'classes/Casasync.php');
include(CASASYNC_PLUGIN_DIR . 'classes/Single.php');
include(CASASYNC_PLUGIN_DIR . 'classes/Archive.php');

$casaSync = new CasaSync();
if (is_admin()) {
	include(CASASYNC_PLUGIN_DIR . 'classes/Admin.php');
	$casaSyncAdmin = new Admin();
}
?>