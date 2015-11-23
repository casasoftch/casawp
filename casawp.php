<?php
/*
 *	Plugin Name: 	CASAWP
 *  Plugin URI: 	http://immobilien-plugin.ch
 *	Description:    Import your properties directly from your real-estate managment software!
 *	Author:         Casasoft AG
 *	Author URI:     https://casasoft.ch
 *	Version: 		2.0.0
 *	Text Domain: 	casawp
 *	Domain Path: 	languages/
 *	License: 		GPL2
 */

/* Das WP Immobilien-Plugin für Ihre Website importiert Immobilien aus Ihrer Makler-Software! */
$dummy_desc = __( 'Import your properties directly from your real-estate managment software!', 'casawp' );

define('CASASYNC_PLUGIN_URL', home_url() . '/wp-content/plugins/casawp/');
define('CASASYNC_PLUGIN_URI', home_url() . '/wp-content/plugins/casawp/');
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

/*if (function_exists('date_default_timezone_set')) {
	date_default_timezone_set();
}*/