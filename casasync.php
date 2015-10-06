<?php
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

/**
 * This makes our life easier when dealing with paths. Everything is relative
 * to the application root now.
 */
chdir(dirname(__DIR__));

// Setup autoloading
include 'vendor/autoload.php';
include 'modules/Casasync/Module.php';


$configuration=array(
		'modules' => array(
			'CasasoftStandards',
			'CasasoftMessenger',
			'Casasync'
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

use Zend\EventManager\EventManager;
use Zend\Http\PhpEnvironment;
use Zend\ModuleManager\ModuleManager;
use Zend\Mvc\Application;
use Zend\Mvc\MvcEvent;
use Zend\ServiceManager\ServiceManager;

$config = $configuration;

$serviceManager = new ServiceManager();
$eventManager=new EventManager();

use Zend\Loader\AutoloaderFactory;
use Zend\Mvc\Service\ServiceManagerConfig;
AutoloaderFactory::factory();
// setup service manager
$serviceManager = new ServiceManager(new ServiceManagerConfig());
$serviceManager->setService('ApplicationConfig', $configuration);

// set translator
use Zend\I18n\Translator\Translator;
$translator = new Translator();
$translator->addTranslationFilePattern('gettext', CASASYNC_PLUGIN_DIR. 'vendor/casasoft/casamodules/src/CasasoftStandards/language/', '%s.mo', 'casasoft-standards');
$translator->setLocale(substr(get_bloginfo('language'), 0, 2));
$serviceManager->setService('Translator', $translator);

// load modules -- which will provide services, configuration, and more
$serviceManager->get('ModuleManager')->loadModules();
$casasync = new CasaSync\Plugin($serviceManager);

global $casasync;

if (is_admin()) {
	$casaSyncAdmin = new CasaSync\Admin();

	if (isset($casaSyncAdmin)) {
		register_activation_hook(__FILE__, array($casaSyncAdmin,'casasync_install'));
		register_deactivation_hook(__FILE__, array($casaSyncAdmin, 'casasync_remove'));
	}
}
if (get_option('casasync_live_import') || isset($_GET['do_import']) ) {
	if (get_option('casasync_legacy')) {
		$import = new CasaSync\ImportLegacy(true, false);	
	} else {
		$import = new CasaSync\Import(true, false);
	}
	$transcript = $import->getLastTranscript();
}
if (isset($_GET['gatewayupdate'])) {
	$import = new CasaSync\Import(false, true);
	$import = new CasaSync\Import(true, false);
	$transcript = $import->getLastTranscript();
}
if (isset($_GET['gatewaypoke'])) {
	//$import = new Import(false, false);
	//$import->gatewaypoke();

	$import = new CasaSync\Import(false, true);
	$import = new CasaSync\Import(true, false);
	$transcript = $import->getLastTranscript();
}


