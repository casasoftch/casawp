<?php
/*
 *	Plugin Name: 	CASAWP
 *  Plugin URI: 	http://immobilien-plugin.ch
 *	Description:    Import your properties directly from your real-estate managment software!
 *	Author:         Casasoft AG
 *	Author URI:     https://casasoft.ch
 *	Version: 		2.0.1
 *	Text Domain: 	casawp
 *	Domain Path: 	languages/
 *	License: 		GPL2
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/* Das WP Immobilien-Plugin für Ihre Website importiert Immobilien aus Ihrer Makler-Software! */
$dummy_desc = __( 'Import your properties directly from your real-estate managment software!', 'casawp' );

define('CASASYNC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CASASYNC_PLUGIN_DIR', plugin_dir_path(__FILE__) . '');

$upload = wp_upload_dir();
define('CASASYNC_CUR_UPLOAD_PATH', $upload['path'] );
define('CASASYNC_CUR_UPLOAD_URL', $upload['url'] );
define('CASASYNC_CUR_UPLOAD_BASEDIR', $upload['basedir'] );
define('CASASYNC_CUR_UPLOAD_BASEURL', $upload['baseurl'] );

chdir(dirname(__DIR__));

// Setup autoloading
include 'vendor/autoload.php';
include 'modules/casawp/Module.php';
$configuration = array(
	'modules' => array(
		'CasasoftStandards',
		'CasasoftMessenger',
		'casawp'
	),
	'module_listener_options' => array(
		'config_glob_paths'    => array(
				__DIR__.'/config/autoload/{,*.}{global,local}.php',
		),
		'module_paths' => array(
				__DIR__.'/module',
				__DIR__.'/vendor',
		),
	),
);

use Zend\Loader\AutoloaderFactory;
AutoloaderFactory::factory();

$casawp = new casawp\Plugin($configuration);

global $casawp;

if (is_admin()) {
	$casaSyncAdmin = new casawp\Admin();
	register_activation_hook(__FILE__, array($casaSyncAdmin,'casawp_install'));
	register_deactivation_hook(__FILE__, array($casaSyncAdmin, 'casawp_remove'));
}

if (get_option('casawp_live_import') || isset($_GET['do_import']) ) {
	$import = new casawp\Import(true, false);
}

if (isset($_GET['gatewayupdate'])) {
	$import = new casawp\Import(false, true);
	//$import = new casawp\Import(true, false);
}

if (isset($_GET['gatewaypoke'])) {
	echo "<script>console.log('import_start');</script>";
	$import = new casawp\Import(false, true);
	$import->addToLog('Poke from casagateway caused import');
	echo "<script>console.log('import_end');</script>";
	//$import = new casawp\Import(true, false);
}


function this_plugin_after_wmpl() {
	// ensure path to this file is via main wp plugin path
	$wp_path_to_this_file = preg_replace('/(.*)plugins\/(.*)$/', WP_PLUGIN_DIR."/$2", __FILE__);
	$this_plugin = plugin_basename(trim($wp_path_to_this_file));
	$active_plugins = get_option('active_plugins');
	$this_plugin_key = array_search($this_plugin, $active_plugins);
	if ($this_plugin_key) { // if it's 0 it's the first plugin already, no need to continue
		//array_splice($active_plugins, $this_plugin_key, 1);
		//array_unshift($active_plugins, $this_plugin);
		$v = $active_plugins[$this_plugin_key];
		unset($active_plugins[$this_plugin_key]);
		$active_plugins = array_values($active_plugins);
		$active_plugins[] = $v;

		update_option('active_plugins', $active_plugins);
	}
}
add_action("activated_plugin", "this_plugin_after_wmpl");

/*if (function_exists('date_default_timezone_set')) {
	date_default_timezone_set();
}*/