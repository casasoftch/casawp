<?php
/*
 *	Plugin Name: 	CASAWP
 *  Plugin URI: 	http://immobilien-plugin.ch
 *	Description:    Import your properties directly from your real-estate managment software!
 *	Author:         Casasoft AG
 *	Author URI:     https://casasoft.ch
 *	Version: 		2.0.3
 *	Text Domain: 	casawp
 *	Domain Path: 	languages/
 *	License: 		GPL2
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

//update system
require_once ( 'wp_autoupdate.php' );
$plugin_current_version = '2.0.3';
$plugin_slug = plugin_basename( __FILE__ );
$plugin_remote_path = 'http://wp.casasoft.ch/casawp/update.php';	
$license_user = 'user';
$license_key = 'abcd';
new WP_AutoUpdate ( $plugin_current_version, $plugin_remote_path, $plugin_slug, $license_user, $license_key );	

function casawpPostInstall( $true, $hook_extra, $result ) {
    // Remember if our plugin was previously activated
    $wasActivated = is_plugin_active( 'casawp' );

    // Since we are hosted in GitHub, our plugin folder would have a dirname of
    // reponame-tagname change it to our original one:
    global $wp_filesystem;
    $pluginFolder = WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . dirname( 'casawp' );
    $wp_filesystem->move( $result['destination'], $pluginFolder );
    $result['destination'] = $pluginFolder;

    // Re-activate plugin if needed
    if ( $wasActivated ) {
        $activate = activate_plugin( 'casawp'  );
    }

    return $result;
}

add_filter( "upgrader_post_install", "casawpPostInstall", 10, 3 );


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


function this_plugin_after_wpml() {
	// ensure path to this file is via main wp plugin path
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

	update_option('active_plugins', $new_sort);
}
add_action("activated_plugin", "this_plugin_after_wpml");
